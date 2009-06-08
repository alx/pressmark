tinyMCE.importPluginLanguagePack('subscribe2quicktags', 'en_us,nl_nl');

var TinyMCE_Subscribe2Quicktags = {
	getInfo : function() {
		return {
			longname : "Subscribe2 Quicktag",
			author : 'MattyRob',
			authorurl : 'http://subscribe2.wordpress.com/',
			infourl : 'http://subscribe2.wordpress.com/',
			version : tinyMCE.majorVersion + '.' + tinyMCE.minorVersion
		};
	},
	getControlHTML : function(cn) {
		switch (cn) {
			case 'subscribe2quicktags':
				buttons = tinyMCE.getButtonHTML('subscribe2', 'lang_subscribe2quicktags_subscribe2', '{$pluginurl}/../include/s2_button.png', 'subscribe2');
				return buttons;
		}
		return '';
	},
	execCommand : function(editor_id, element, command, user_interface, value) {
		var inst = tinyMCE.getInstanceById(editor_id);
		var focusElm = inst.getFocusElement();
		function getAttrib(elm, name) {
				return elm.getAttribute(name) ? elm.getAttribute(name) : "";
		}

		switch (command) {
			case 'subscribe2':
				//s2_insert_token(); //Replaced by insert image:
				var flag = "";
				// is image is selected
				if (focusElm != null && focusElm.nodeName.toLowerCase() == "img") {
						flag = getAttrib(focusElm, 'class');
						flagIE = getAttrib(focusElm, 'className');

						if ( flag == 'mce_plugin_s2_img' || flagIE == 'mce_plugin_s2_img' )
								alert("Placeholder for Subscribe2 form " + getAttrib(focusElm,'moretext') );
						
						return true;
				}
				
				alt = "Placeholder for Subscribe2 form";
				cssstyle = 'background:url(../wp-content/plugins/subscribe2/include/s2_marker.png) no-repeat 5px 5px;';

				html = ''
					+ '<img src="../wp-content/plugins/subscribe2/include/spacer.gif" '
				  + 'width="210px" height="25px" '
				  + 'alt="'+alt+'" title="'+alt+'" style="'+cssstyle+'" class="mce_plugin_s2_img" />';

				tinyMCE.execInstanceCommand(editor_id, 'mceInsertContent', false, html);
				tinyMCE.selectedInstance.repaint();
				
				return true;
		}
		return false;
	},
	
/* EDIT: added for marker by Ravan */
	cleanup : function(type, content) {
		switch (type) {
		
			case "insert_to_editor":
				// Parse all <!--subscribe2--> tags and replace them with images
				var startPos = 0;
				var alt = "Placeholder for Subscribe2 form";
				var cssstyle = 'background:url(../wp-content/plugins/subscribe2/include/s2_marker.png) no-repeat 5px 5px;';
				while ((startPos = content.indexOf('<!--subscribe2', startPos)) != -1) {
					var endPos = content.indexOf('-->', startPos) + 3;
					// Insert image
					var moreText = content.substring(startPos + 14, endPos - 3);
					var contentAfter = content.substring(endPos);
					content = content.substring(0, startPos);
					content += '<img src="../wp-content/plugins/subscribe2/include/spacer.gif" ';
					content += ' width="210px" height="25px" moretext="'+moreText+'" ';
					content += 'alt="'+alt+'" title="'+alt+'" style="'+cssstyle+'" class="mce_plugin_s2_img" />';
					content += contentAfter;
					startPos++;
				}
				break;
		
			case "get_from_editor":
				// Parse all img tags and replace them with <!--subscribe2-->
				var startPos = -1;
				while ((startPos = content.indexOf('<img', startPos+1)) != -1) {
					var endPos = content.indexOf('/>', startPos);
					var attribs = this._parseAttributes(content.substring(startPos + 4, endPos));
		
					if (attribs['class'] == "mce_plugin_s2_img") {
						endPos += 2;
		
						var moreText = attribs['moretext'] ? attribs['moretext'] : '';
						var embedHTML = '<!--subscribe2'+moreText+'-->';
		
						// Insert embed/object chunk
						chunkBefore = content.substring(0, startPos);
						chunkAfter = content.substring(endPos);
						content = chunkBefore + embedHTML + chunkAfter;
					}
				}
				break;
			}

		// Pass through to next handler in chain
		return content;
	},

	handleNodeChange : function(editor_id, node, undo_index, undo_levels, visual_aid, any_selection) {

		tinyMCE.switchClass(editor_id + '_subscribe2', 'mceButtonNormal');

		if (node == null)
			return;

		do {
			if (node.nodeName.toLowerCase() == "img" && tinyMCE.getAttrib(node, 'class').indexOf('mce_plugin_s2_img') == 0) {
				tinyMCE.switchClass(editor_id + '_subscribe2', 'mceButtonSelected');
			}
		} while ((node = node.parentNode));

		return true;
	},

	_parseAttributes : function(attribute_string) {
		var attributeName = "";
		var attributeValue = "";
		var withInName;
		var withInValue;
		var attributes = new Array();
		var whiteSpaceRegExp = new RegExp('^[ \n\r\t]+', 'g');

		if (attribute_string == null || attribute_string.length < 2)
			return null;

		withInName = withInValue = false;

		for (var i=0; i<attribute_string.length; i++) {
			var chr = attribute_string.charAt(i);

			if ((chr == '"' || chr == "'") && !withInValue)
				withInValue = true;
			else if ((chr == '"' || chr == "'") && withInValue) {
				withInValue = false;

				var pos = attributeName.lastIndexOf(' ');
				if (pos != -1)
					attributeName = attributeName.substring(pos+1);

				attributes[attributeName.toLowerCase()] = attributeValue.substring(1);

				attributeName = "";
				attributeValue = "";
			} else if (!whiteSpaceRegExp.test(chr) && !withInName && !withInValue)
				withInName = true;

			if (chr == '=' && withInName)
				withInName = false;

			if (withInName)
				attributeName += chr;

			if (withInValue)
				attributeValue += chr;
		}

		return attributes;
	}
/* end */
};

tinyMCE.addPlugin('subscribe2quicktags', TinyMCE_Subscribe2Quicktags);