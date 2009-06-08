jQuery(document).ready(function(){
	jQuery('input[name=checkall]').click(function(){
		var checked_status = this.checked;
		jQuery('input[class=' + this.value + ']').each(function(){
			this.checked = checked_status;
		});
	});
	jQuery('input[class*=_checkall]').click(function(){
		var checked_status = true;
		var s2_class = this.className;
		jQuery('input[class=' + s2_class + ']').each(function(){
			if ((this.checked == true) && (checked_status == true)){
				checked_status = true;
			} else {
				checked_status = false;
			}
			jQuery('input[@value=' + s2_class + ']').attr('checked', checked_status);
		});
	});
});