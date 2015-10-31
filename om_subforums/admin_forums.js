function om_subforums_change_parent(cat_name, parent_name) {
	if (document.getElementsByName(parent_name).length == 0)
		return;
	
	if (document.getElementsByName(parent_name)[0].value == 0)
		document.getElementsByName(cat_name)[0].disabled=false;
	else 
		document.getElementsByName(cat_name)[0].disabled=true;
}

// execute when page is loaded - alternative to $(document).ready()
(function() {
	var func = window.onload;
		
	if (typeof window.onload != 'function') {
		window.onload = function() { om_subforums_change_parent('cat_id', 'om_subforums_parent_id'); }
	} else {
		window.onload = function() {
			if (func) {
				func();
			}
			
			om_subforums_change_parent('cat_id', 'om_subforums_parent_id');
		}
	}
	
})();
