<?php
/**
* om_shoutbox - json encoder
*
*/

header("Cache-Control: no-cache");
header("Content-type: text/text");  
define('FORUM_ROOT', '../../');
define ("HEADER_UNLOCK", TRUE);
define('OM_SHOUTBOX_IGNORE_PREV_URL', true);

// new message
if (isset($_GET['message'])) {

	require FORUM_ROOT.'include/common.php';

	if ($forum_user['id'] != 1 && ($forum_user['g_id'] == FORUM_ADMIN || !isset($forum_user['om_warnings_restrictions']) || !in_array('om_shoutbox', $forum_user['om_warnings_restrictions']))) {
		$message = forum_htmlencode($_GET['message']);
		$username = forum_htmlencode($forum_user['username']);
		$color = intval($forum_user['om_shoutbox_color']);

		// insert new message with current time
		if( (strlen($message) > 0) && (strlen($message) <= $forum_config['o_om_shoutbox_msg_length']) )
		{	
			$query = array(
				'INSERT'	=> 'poster, date, message, color',
				'INTO'		=> 'om_shoutbox',
				'VALUES'	=> '\''.$forum_db->escape($username).'\', '.time()
						.', \''.$forum_db->escape($message).'\','.$color,
			);
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		echo json_encode(array('status'=>true));

		// clean up after posting the message
		$time_before = time()-$forum_config['o_om_shoutbox_msg_time']*60;
		$query = array(
			'DELETE'	=> 'om_shoutbox',
			'WHERE'		=> 'date <= '.$time_before
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Close the db connection (and free up any result data)
		$forum_db->end_transaction();
		$forum_db->close();
	}
}

// this is critical section, it will be executed every 3 by everyone looking at main page.
// get list of messages
elseif (isset($_GET['list'])) {

	require FORUM_ROOT.'include/common.php';

	if ($forum_config['o_om_shoutbox_show_smilies'] == '1') {
		require FORUM_ROOT.'include/parser.php';
	}

	$time_before = time()-$forum_config['o_om_shoutbox_msg_time']*60;

	// do not load the messages, that 
	if ($_GET['list'] > $time_before) {
		$time_before = $_GET['list'];
	}

	$query = array(
		'SELECT'	=> 'date, poster, message, color',
		'FROM'		=> 'om_shoutbox',
		'ORDER BY'	=> 'date DESC',
		'WHERE'		=> 'date > '.$time_before,
		'LIMIT'		=> '0,'.$forum_config['o_om_shoutbox_msg_count'],
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$messages = array();
	while ($msg = $forum_db->fetch_assoc($result)) {
		$text = $msg['message'];
		if (!empty($text) && $text[0] == '@' && strpos($text, '@'.$forum_user['username'].' ') !== 0 && $msg['poster'] != $forum_user['username']) {
			$msg_json = array('date'=>$msg['date'], 'message'=>null);
			$messages[] = $msg_json;
			continue;
		}
		$text = preg_replace('/(https?:\/\/[^\s^\[^\]]+)/', '<a class="exthelp" href="$1" rel="nofollow">$1</a>', $text);
		$text = preg_replace('/\[b\](.+?)\[\/b\]/', '<strong>$1</strong>', $text);
		if ($forum_config['o_om_shoutbox_show_smilies'] == '1') {
			$text = do_smilies($text);
		}
		$msg_json = array('date'=>$msg['date'], 'poster'=>$msg['poster'], 'message'=>$text, 'color'=>$msg['color']);
		$messages[] = $msg_json;
	}
	
	$messages = array_reverse($messages);
	echo json_encode(array('messages'=>$messages));

	// Close the db connection (and free up any result data)
	$forum_db->end_transaction();
	$forum_db->close();
}
