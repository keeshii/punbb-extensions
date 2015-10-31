// disable timezone
function om_server_timezone_click() {

	if (document.getElementsByName("form[om_server_timezone]")[0].checked) {
		document.getElementsByName("form[dst]")[0].disabled = true;
		document.getElementsByName("form[timezone]")[0].disabled = true;
	} else {
		document.getElementsByName("form[dst]")[0].disabled = false;
		document.getElementsByName("form[timezone]")[0].disabled = false;
	}

}

// execute when page is loaded - alternative to $(document).ready()
(function() {
	var func = window.onload;
		
	if (typeof window.onload != 'function') {
		window.onload = om_server_timezone_click;
	} else {
		window.onload = function() {
			if (func) {
				func();
			}
			
			om_server_timezone_click();
		}
	}
	
})();
