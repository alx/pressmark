jQuery(function() {
	jQuery('body.wp-admin #openid_rollup dl').hide();

	jQuery('body.wp-admin #openid_rollup_link').click( function() {
		jQuery('#openid_rollup dl').toggle();
		return false;
	});

	/* 
	var websiteRow = jQuery('body.wp-admin form#adduser input#url ../..');
	openidRow = websiteRow.clone().insertAfter(websiteRow);
	jQuery('th', openidRow).text('OpenID');
	jQuery('td input', openidRow).attr('id', 'openid_url').attr('name', 'openid_url');
	*/
});

function stylize_profilelink() {
	jQuery('#commentform a[@href$=/wp-admin/profile.php]').addClass('openid_link');
}

function add_openid_to_comment_form() {

	jQuery('#commentform').addClass('openid');

	var html = ' <a id="openid_enabled_link" href="http://openid.net">(OpenID Enabled)</a> ' +
				'<div id="openid_text">' +
					'If you have an OpenID, you may fill it in here.  If your OpenID provider provides ' + 
					'a name and email, those values will be used instead of the values here.  ' + 
					'<a href="http://openid.net/what/">Learn more about OpenID</a> or ' + 
					'<a href="http://openid.net/get/">find an OpenID provider</a>.' +
				'</div> ';

	var label = jQuery('#commentform label[@for=url]');
	var children = jQuery(':visible:hastext', label);

	if (children.length > 0)
		children.filter(':last').appendToText(html);
	else if (label.is(':hastext'))
		label.appendToText(html);
	else
		label.append(html);

	// setup action
	jQuery('#openid_text').hide();
	jQuery('#openid_enabled_link').click( function() {
		jQuery('#openid_text').toggle(200); 
		return false;
	});
}

