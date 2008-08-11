<?php
/**
 * store.php
 *
 * Database Connector for wp-openid
 * Dual Licence: GPL & Modified BSD
 */
require_once 'Auth/OpenID/DatabaseConnection.php';
require_once 'Auth/OpenID/SQLStore.php';
require_once 'Auth/OpenID/MySQLStore.php';

if (class_exists( 'Auth_OpenID_MySQLStore' ) && !class_exists('WordPressOpenID_Store')):
class WordPressOpenID_Store extends Auth_OpenID_MySQLStore {
	var $associations_table_name;
	var $nonces_table_name;
	var $identity_table_name;
	var $comments_table_name;
	var $usermeta_table_name;

	function WordPressOpenID_Store()
	{
		global $wpdb;

		$table_prefix = isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix;

		$this->associations_table_name = $table_prefix . 'openid_associations';
		$this->nonces_table_name = $table_prefix . 'openid_nonces';
		$this->identity_table_name =  $table_prefix . 'openid_identities';
		$this->comments_table_name =  $table_prefix . 'comments';
		$this->usermeta_table_name =  $wpdb->prefix . 'usermeta';

		if (defined('CUSTOM_OPENID_IDENTITY_TABLE'))
			$this->identity_table_name =  CUSTOM_OPENID_IDENTITY_TABLE;

		if (defined('CUSTOM_USER_META_TABLE'))
			$this->usermeta_table_name =  CUSTOM_USER_META_TABLE;

		$conn = new WordPressOpenID_Connection( $wpdb );
		parent::Auth_OpenID_MySQLStore(
			$conn,
			$this->associations_table_name,
			$this->nonces_table_name
		);
	}

	function isError($value)
	{
		return $value === false;
	}

	function blobEncode($blob)
	{
		return $blob;
	}

	function blobDecode($blob)
	{
		return $blob;
	}


	/**
	 * Check to see whether the nonce, association, and identity tables exist.
	 *
	 * @param bool $retry if true, tables will try to be recreated if they are not okay
	 * @return bool if tables are okay
	 */
	function check_tables($retry=true) {
		global $wpdb, $openid;

		$ok = true;
		$message = array();
		$tables = array(
		$this->associations_table_name,
		$this->nonces_table_name,
		$this->identity_table_name,
		);
		foreach( $tables as $t ) {
			if( $wpdb->get_var("SHOW TABLES LIKE '$t'") != $t ) {
				$ok = false;
				$message[] = "Table $t doesn't exist.";
			} else {
				$message[] = "Table $t exists.";
			}
		}
			
		if( $retry and !$ok) {
			$openid->setStatus( 'Database Tables', false,
					'Tables not created properly. Trying to create..' );
			$this->create_tables();
			$ok = $this->check_tables( false );
		} else {
			$openid->setStatus( 'Database Tables', $ok?'info':false, $message );
		}
		return $ok;
	}


	/**
	 * Create OpenID related tables in the WordPress database.
	 */
	function create_tables()
	{
		global $wp_version, $wpdb, $openid;

		if ($wp_version >= '2.3') {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		} else {
			require_once(ABSPATH . 'wp-admin/admin-db.php');
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		}

		// Create the SQL and call the WP schema upgrade function
		$statements = array(
		$this->sql['nonce_table'],
		$this->sql['assoc_table'],

				"CREATE TABLE $this->identity_table_name (
		uurl_id bigint(20) NOT NULL auto_increment,
		user_id bigint(20) NOT NULL default '0',
		url text,
		hash char(32),
		PRIMARY KEY  (uurl_id),
		UNIQUE KEY uurl (hash),
		KEY url (url(30)),
		KEY user_id (user_id)
		)",
		);

		$sql = implode(';', $statements);
		dbDelta($sql);

		// add column to comments table
		$result = maybe_add_column($this->comments_table_name, 'openid',
				"ALTER TABLE $this->comments_table_name ADD `openid` TINYINT(1) NOT NULL DEFAULT '0'");

		if (!$result) {
			$openid->log->err('unable to add column `openid` to comments table.');
		}

		// update old style of marking openid comments and users
		$wpdb->query("update $this->comments_table_name set `comment_type`='', `openid`=1 where `comment_type`='openid'");
		$wpdb->query("update $this->usermeta_table_name set `meta_key`='has_openid' where `meta_key`='registered_with_openid'");
	}

	
	/**
	 * Remove database tables which hold only transient data - associations and nonces.  Any non-transient data, such
	 * as linkages between OpenIDs and WordPress user accounts are maintained.
	 */
	function destroy_tables() {
		global $wpdb;

		$sql = 'drop table ' . WordPressOpenID::associations_table_name();
		$wpdb->query($sql);
		$sql = 'drop table ' . WordPressOpenID::nonces_table_name();
		$wpdb->query($sql);

		// just in case they've upgraded from an old version
		$settings_table_name = (isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix ).'openid_settings';
		$sql = "drop table if exists $settings_table_name";
		$wpdb->query($sql);
	}


	/**
	 * Set SQL for database calls.
	 * 
	 * @see Auth_OpenID_SQLStore::setSQL
	 */
	function setSQL()
	{
		$this->sql['nonce_table'] =
				"CREATE TABLE %s (
					server_url varchar(255) CHARACTER SET latin1,
					timestamp int(11),
					salt char(40) CHARACTER SET latin1,
					UNIQUE KEY server_url (server_url(255),timestamp,salt)
				)";

		$this->sql['assoc_table'] =
				"CREATE TABLE %s (
					server_url varchar(255) CHARACTER SET latin1,
					handle varchar(255) CHARACTER SET latin1,
					secret blob,
					issued int(11),
					lifetime int(11),
					assoc_type varchar(64),
					PRIMARY KEY  (server_url(235),handle)
				)";

		$this->sql['set_assoc'] =
				"REPLACE INTO %s VALUES (%%s, %%s, %%s, %%d, %%d, %%s)";

		$this->sql['get_assocs'] =
				"SELECT handle, secret, issued, lifetime, assoc_type FROM %s ".
				"WHERE server_url = %%s";

		$this->sql['get_assoc'] =
				"SELECT handle, secret, issued, lifetime, assoc_type FROM %s ".
				"WHERE server_url = %%s AND handle = %%s";

		$this->sql['remove_assoc'] =
				"DELETE FROM %s WHERE server_url = %%s AND handle = %%s";

		$this->sql['add_nonce'] =
				"REPLACE INTO %s (server_url, timestamp, salt) VALUES (%%s, %%d, %%s)";

		$this->sql['get_expired'] =
				"SELECT server_url FROM %s WHERE issued + lifetime < %%s";
	}


	/* Application-specific database operations */

	function get_identities($user_id, $identity_id = 0 ) {
		if( $identity_id ) {
			return $this->connection->getOne(
					"SELECT url FROM $this->identity_table_name WHERE user_id = %s AND uurl_id = %s",
			array( (int)$user_id, (int)$identity_id )
			);
		} else {
			return $this->connection->getAll(
					"SELECT uurl_id,url FROM $this->identity_table_name WHERE user_id = %s",
			array( (int)$user_id )
			);
		}
	}


	function insert_identity($userid, $url) {
		global $wpdb;

		if (!$userid) {
			echo "no userID";
			exit;
		}

		$old_show_errors = $wpdb->show_errors;
		if( $old_show_errors ) $wpdb->hide_errors();
		$ret = @$this->connection->query(
				"INSERT INTO $this->identity_table_name (user_id,url,hash) VALUES ( %s, %s, MD5(%s) )",
		array( (int)$userid, $url, $url ) );
		if( $old_show_errors ) $wpdb->show_errors();

		$this->update_user_openid_status($userid);

		return $ret;
	}


	function drop_all_identities_for_user($userid) {
		return $this->connection->query(
				"DELETE FROM $this->identity_table_name WHERE user_id = %s", 
				array( (int)$userid )
		);
	}

	/**
	 * Drop identity from user.
	 *
	 * @param int $user_id id of WordPress user
	 * @param int $identity_id id of identity
	 * @return unknown result of database operation
	 */
	function drop_identity($user_id, $identity_id) {
		$ret = $this->connection->query(
				"DELETE FROM $this->identity_table_name WHERE user_id = %s AND uurl_id = %s",
		array( (int)$user_id, (int)$identity_id )
		);

		$this->update_user_openid_status($user_id);

		return $ret;
	}

	function update_user_openid_status($user_id) {
		$identities = $this->get_identities($user_id);
		update_usermeta( $user_id, 'has_openid', (empty($identities) ? false : true) );
	}

	function get_user_by_identity($url) {
		if (empty($url)) { return null; }
			
		return $this->connection->getOne(
				"SELECT user_id FROM $this->identity_table_name WHERE url = %s",
		array( $url )
		);
	}
}
endif;


/**
 * WordPressOpenID_Connection class implements a PEAR-style database connection using the WordPress WPDB object.
 * Written by Josh Hoyt
 * Modified to support setFetchMode() by Alan J Castonguay, 2006-06-16
 */
if (class_exists('Auth_OpenID_DatabaseConnection') && !class_exists('WordPressOpenID_Connection')):
class WordPressOpenID_Connection extends Auth_OpenID_DatabaseConnection {
	var $fetchmode = ARRAY_A;  // to fix PHP Fatal error:  Cannot use object of type stdClass as array in /usr/local/php5/lib/php/Auth/OpenID/SQLStore.php on line 495

	function WordPressOpenID_Connection(&$wpdb) {
		$this->wpdb =& $wpdb;
	}
	function _fmt($sql, $args) {
		$interp = new MySQLInterpolater($this->wpdb->dbh);
		return $interp->interpolate($sql, $args);
	}
	function query($sql, $args) {
		return $this->wpdb->query($this->_fmt($sql, $args));
	}
	function getOne($sql, $args=null) {
		if($args==null) $args = array();
		return $this->wpdb->get_var($this->_fmt($sql, $args));
	}
	function getRow($sql, $args) {
		return $this->wpdb->get_row($this->_fmt($sql, $args), $this->fetchmode);
	}
	function getAll($sql, $args) {
		return $this->wpdb->get_results($this->_fmt($sql, $args), $this->fetchmode);
	}

	/* This function translates fetch mode constants PEAR=>WPDB
	 * DB_FETCHMODE_ASSOC   => ARRAY_A
	 * DB_FETCHMODE_ORDERED => ARRAY_N
	 * DB_FETCHMODE_OBJECT  => OBJECT  (default)
	 */
	function setFetchMode( $mode ) {
		if( DB_FETCHMODE_ASSOC == $mode ) $this->fetchmode = ARRAY_A;
		if( DB_FETCHMODE_ORDERED == $mode ) $this->fetchmode = ARRAY_N;
		if( DB_FETCHMODE_OBJECT == $mode ) $this->fetchmode = OBJECT;
	}
}
endif;



/**
 * Object for doing SQL substitution
 *
 * The internal state should be consistent across calls, so feel free
 * to re-use this object for more than one formatting operation.
 *
 * Allowed formats:
 *  %s -> string substitution (binary allowed)
 *  %d -> integer substitution
 */
if (!class_exists('Interpolater')):
class Interpolater {

	/**
	 * The pattern to use for substitution
	 */
	var $pattern = '/%([sd])/';

	/**
	 * Constructor
	 *
	 * Just sets the initial state to empty
	 */
	function Interpolater() {
		$this->values = false;
	}

	/**
	 * Escape a string for an SQL engine.
	 *
	 * Override this function to customize string escaping.
	 *
	 * @param string $s The string to escape
	 * @return string $escaped The escaped string
	 */
	function escapeString($s) {
		return addslashes($s);
	}

	/**
	 * Perform one replacement on a value
	 *
	 * Dispatch to the approprate format function
	 *
	 * @param array $matches The matches from this object's pattern
	 *	 with preg_match
	 * @return string $escaped An appropriately escaped value
	 * @access private
	 */
	function interpolate1($matches) {
		if (!$this->values) {
			trigger_error('Not enough values for format string', E_USER_ERROR);
		}
		$value = array_shift($this->values);
		if (is_null($value)) {
			return 'NULL';
		}
		return call_user_func(array($this, 'format_' . $matches[1]), $value);
	}

	/**
	 * Format and quote a string for use in an SQL query
	 *
	 * @param string $value The string to escape. It may contain any
	 *	 characters.
	 * @return string $escaped The escaped string
	 * @access private
	 */

	function format_s($value) {
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		$val_esc = $this->escapeString($value);
		return "'$val_esc'";
	}

	/**
	 * Format an integer for use in an SQL query
	 *
	 * @param integer $value The number to use in the query
	 * @return string $escaped The number formatted as a string
	 * @access private
	 */
	function format_d($value) {
		$val_int = (integer)$value;
		return (string)$val_int;
	}

	/**
	 * Create an escaped query given this format string and these
	 * values to substitute
	 *
	 * @param string $format_string A string to match
	 * @param array $values The values to substitute into the format string
	 */
	function interpolate($format_string, $values) {
		$matches = array();
		$this->values = $values;
		$callback = array(&$this, 'interpolate1');
		$s = preg_replace_callback($this->pattern, $callback, $format_string);
		if ($this->values) {
			trigger_error('Too many values for format string: ' . $format_string . " => " . implode(', ', $this->values), E_USER_ERROR);
		}
		$this->values = false;
		return $s;
	}
}
endif;

/**
 * Interpolate MySQL queries
 */
if (class_exists('Interpolater') && !class_exists('MySQLInterpolater')):
class MySQLInterpolater extends Interpolater {
	function MySQLInterpolater($dbconn=false) {
		$this->dbconn = $dbconn;
		$this->values = false;
	}

	function escapeString($s) {
		if ($this->dbconn === false) {
			return mysql_real_escape_string($s);
		} else {
			return mysql_real_escape_string($s, $this->dbconn);
		}
	}
}
endif;

?>
