/*
 * om_shoutbox - Simple shoutbox sctipt
 * 
 * 
 * */

$(document).ready(function(){

	// base url of json.php script (in case if you want to move it to different server)
	var base_url = "./extensions/om_shoutbox/";
	
	// time between refres of message list
	var interval = 3000;
	
	// color of posters
	var poster_colors = new Array("#000000");
	
	// do not change that, please :)
	// stores the time of last loaded message
	var last_message_time = 0;
	
	// this variable prevents invoking next query, while previous was not completed.
	var last_query_completed = true;
	
	
	// submiting the form - sending new message
	$("form#om_shoutbox").submit(function() {
		
		var form_element = $(this);
		
		var url = base_url + "json.php?" + form_element.serialize();
		
		$('div.om_shoutbox_message textarea').attr('disabled', 'disabled');
		$('div.om_shoutbox_message input').attr('disabled', 'disabled');
		
		function handle_reply(data) {
			$('div.om_shoutbox_message input#om_shoutbox').val('');
			$('div.om_shoutbox_message input#om_shoutbox').removeAttr('disabled');
			$('div.om_shoutbox_message input').removeAttr('disabled');
		}
		
		$.getJSON(url, handle_reply);
		return false;
	});


	// refreshing the message list - executed every [interval] miliseconds.
	function check_shoutbox_messages() {
		
		if (!last_query_completed)
			return;
		
		last_query_completed = false;
		
		var form_element = $('form#shoutform');
		var messages_div = $('div.om_shoutbox div.om_shoutbox_messages');
		
		var url = base_url + "json.php?list=" + last_message_time;
				
		function handle_reply(data) {
			
			
			function poster_to_color(poster) {
				color_id = 0;
				for (i=0; i<poster.length; i++) {
					color_id += poster.charCodeAt(i);
				}
				
				return poster_colors[ color_id % poster_colors.length ];
			}
			
			// remove text "loading messages"
			if (last_message_time == 0)
				$('div.om_shoutbox_messages ul').html('');
			
			var content = '';
			
			// this is true only when scrollbar is completly at bottom.
			var we_are_at_bottom = (messages_div.scrollTop() + 1 >= messages_div.prop("scrollHeight") - messages_div.height());
			
			for (id in data.messages) {
				last_message_time = data.messages[id].date;
				
				// ommit private messages
				if (data.messages[id].message == null)
					continue;
				
				var date = new Date(data.messages[id].date*1000);
				var date_string = '<span class="om_shoutbox_date">['+ date.getHours() + ':' + (date.getMinutes() < 10 ? '0' : '') +date.getMinutes() + ']</span>';
				
				// when color == 0, then user want to random colour, otherwise it is array index - 1.
				var color = data.messages[id].color == 0 ? poster_to_color(data.messages[id].poster)
					: poster_colors[ (data.messages[id].color-1) % poster_colors.length ];
				
				var poster_string = '<span style="color:'+color+';">' + data.messages[id].poster + '</span>';

				var message_string = data.messages[id].message;
				if (data.messages[id].message[0] == '@') {
					message_string = '<em>' + message_string + '</em>';
				}

				content += ' <li>'+ date_string + ' ' + poster_string + ': ' + message_string +"</li>\n";
			}
			
			// append recent messages
			$('div.om_shoutbox_messages ul').append(content);
			
			// follow the messages, but only when we are at the bottom
			if (data.messages.length != 0 && we_are_at_bottom)
				messages_div.animate({ scrollTop: messages_div.prop("scrollHeight") - messages_div.height() }, 500);
			
			last_query_completed = true;
		}
		
		$.getJSON(url, handle_reply);
		return false;
		
	}
	
	
	// execute refresh evey [interval] miliseconds;
	window.om_shoutbox_init = function(refresh_rate, colors) {
		interval = refresh_rate;
		if (colors.length > 0)
			poster_colors = colors;
		
		window.setInterval(function(){check_shoutbox_messages()}, interval);
	};

});
