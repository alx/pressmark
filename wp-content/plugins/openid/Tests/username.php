<?php
include('logic.php');

$logic = new WordPressOpenIDLogic(null);

generate('http://willnorris.com');
generate('https://will.norris.name/');
generate('http://xri.net/=will.norris');
generate('xri://=will.norris');
generate('xri://=!C714.538.BD92.E2C6');
generate('http://xri.net/@will.norris');
generate('http://claimid.com/willnorris');

function generate($username) {
	global $logic; 

	echo "$username =>\t\t" . $logic->normalize_username($username) . "\n";
}

function sanitize_user($user) { 
	return $user; 
}

?>
