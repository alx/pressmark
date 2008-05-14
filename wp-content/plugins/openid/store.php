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

if( class_exists( 'Auth_OpenID_MySQLStore' ) && !class_exists('WordpressOpenIDStore')) {
	class WordpressOpenIDStore extends Auth_OpenID_MySQLStore {

		var $core;				// WordpressOpenID instance

		var $table_prefix;
		var $associations_table_name;
		var $nonces_table_name;
		var $identity_table_name;
		var $comments_table_name;
		var $usermeta_table_name;

		function WordpressOpenIDStore($core)
		{
			global $wpdb;
			$this->core =& $core;

			$this->table_prefix = isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix;

			$this->associations_table_name = $this->table_prefix . 'openid_associations';
			$this->nonces_table_name = $this->table_prefix . 'openid_nonces';
			$this->identity_table_name =  $this->table_prefix . 'openid_identities';
			$this->comments_table_name =  $this->table_prefix . 'comments';
			$this->usermeta_table_name =  $wpdb->prefix . 'usermeta';

			$conn = new WordpressOpenIDConnection( $wpdb );
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

		/*
		 * Check to see whether the nonce, association, and identity tables exist.
		 */
		function check_tables($retry=true) {
			global $wpdb;

			$ok = true;
			$message = '';
			$tables = array( 
				$this->associations_table_name, 
				$this->nonces_table_name,
				$this->identity_table_name,
			);
			foreach( $tables as $t ) {
				$message .= empty($message) ? '' : '<br/>';
				if( $wpdb->get_var("SHOW TABLES LIKE '$t'") != $t ) {
					$ok = false;
					$message .= "Table $t doesn't exist.";
				} else {
					$message .= "Table $t exists.";
				}
			}
			
			if( $retry and !$ok) {
				$this->core->setStatus( 'database tables', false, 
					'Tables not created properly. Trying to create..' );
				$this->create_tables();
				$ok = $this->check_tables( false );
			} else {
				$this->core->setStatus( 'database tables', $ok?'info':false, $message );
			}
			return $ok;
		}


		/**
		 * WordPress database upgrade functions
		 */
		function create_tables()
		{
			global $wp_version, $wpdb;

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
				$this->core->log->error('unable to add column `openid` to comments table.');
			}

			$wpdb->query("update $this->comments_table_name set `comment_type`='', `openid`=1 where `comment_type`='openid'");
			$wpdb->query("update $this->usermeta_table_name set `meta_key`='has_openid' where `meta_key`='registered_with_openid'");
		}

		function destroy_tables() {
			global $wpdb;
			$sql = 'drop table ' . $this->associations_table_name;
			$wpdb->query($sql);
			$sql = 'drop table ' . $this->nonces_table_name;
			$wpdb->query($sql);

			// just in case they've upgraded from an old version
			$settings_table_name = (isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix ).'openid_settings';
			$sql = "drop table if exists $settings_table_name";
			$wpdb->query($sql);
		}

		function dbCleanup() {
				
		}

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
		function get_my_identities( $id = 0 ) {
			global $userdata;
			if( $id ) {
				return $this->connection->getOne( 
					"SELECT url FROM $this->identity_table_name WHERE user_id = %s AND uurl_id = %s",
					array( (int)$userdata->ID, (int)$id ) 
				);
			} else {
				return $this->connection->getAll( 
					"SELECT uurl_id,url FROM $this->identity_table_name WHERE user_id = %s",
					array( (int)$userdata->ID ) 
				);
			}
		}


		function insert_identity($url) {
			global $userdata, $wpdb;

			$old_show_errors = $wpdb->show_errors;
			if( $old_show_errors ) $wpdb->hide_errors();
			$ret = @$this->connection->query( 
				"INSERT INTO $this->identity_table_name (user_id,url,hash) VALUES ( %s, %s, MD5(%s) )",
				array( (int)$userdata->ID, $url, $url ) );
			if( $old_show_errors ) $wpdb->show_errors();

			$this->update_user_openid_status();

			return $ret;
		}

		
		function drop_all_identities_for_user($userid) {
			return $this->connection->query( 
				"DELETE FROM $this->identity_table_name WHERE user_id = %s", 
				array( (int)$userid ) 
			);
		}
		
		function drop_identity($id) {
			global $userdata;
			$ret = $this->connection->query( 
				"DELETE FROM $this->identity_table_name WHERE user_id = %s AND uurl_id = %s",
				array( (int)$userdata->ID, (int)$id ) 
			);

			$this->update_user_openid_status();

			return $ret;
		}

		function update_user_openid_status() {
			global $userdata;

			$identities = $this->get_my_identities();
			update_usermeta( $userdata->ID, 'has_openid', (empty($identities) ? false : true) );
		}
		
		function get_user_by_identity($url) {
			return $this->connection->getOne( 
				"SELECT user_id FROM $this->identity_table_name WHERE url = %s",
				array( $url ) 
			);
		}
	}
}


/**
 * WordpressOpenIDConnection class implements a PEAR-style database connection using the WordPress WPDB object.
 * Written by Josh Hoyt
 * Modified to support setFetchMode() by Alan J Castonguay, 2006-06-16 
 */
if (  class_exists('Auth_OpenID_DatabaseConnection') && !class_exists('WordpressOpenIDConnection') ) {
	class WordpressOpenIDConnection extends Auth_OpenID_DatabaseConnection {
		var $fetchmode = ARRAY_A;  // to fix PHP Fatal error:  Cannot use object of type stdClass as array in /usr/local/php5/lib/php/Auth/OpenID/SQLStore.php on line 495
		
		function WordpressOpenIDConnection(&$wpdb) {
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
}



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
if  ( !class_exists('Interpolater') ) {
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
}

/**
 * Interpolate MySQL queries
 */
if  ( class_exists('Interpolater') && !class_exists('MySQLInterpolater') ) {
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
}

?>
