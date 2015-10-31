<?php
/**
 * om_friends functions: database
 *
 * @copyright (C) 2013 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_friends
 */

if (!defined('FORUM'))
	die();


// returns array of friends of user given as parameter id=>username
function om_friends_get_friends($user_id)
{
	global $forum_db;

	$result = ($hook = get_hook('om_friends_fn_get_friends_start')) ? eval($hook) : null;
	if ($result !== null)
		return $result;

	if ($user_id <= 1)
		return array();

	$query = array(
		'SELECT' 	=> 'f.friend_id, u.username',
		'FROM' 		=> 'om_friends AS f',
		'JOINS'		=> array(
			array(
				'JOIN' => 'users AS u',
				'ON' => 'u.id = f.friend_id',
			),
		),
		'WHERE'		=> 'f.user_id = '.$user_id,
		'ORDER'		=> 'u.username ASC',
	);

	($hook = get_hook('om_friends_fn_get_friends_qr_get_friends')) ? eval($hook) : null; 
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$friend_list = array();
	while ($row = $forum_db->fetch_assoc($result)) {
		$friend_list[$row['friend_id']] = $row['username'];
	}

	($hook = get_hook('om_friends_fn_get_friends_pre_return')) ? eval($hook) : null; 
	return $friend_list;
}

// checks if current is friend of an another user
function om_friends_is_friend($friend_id)
{
	global $forum_db, $forum_user;

	$result = ($hook = get_hook('om_friends_fn_is_friend_start')) ? eval($hook) : null;
	if ($result !== null)
		return $result;

	if ($forum_user['id'] <= 1) {
		return false;
	}

	$query = array(
		'SELECT' 	=> 'count(1)',
		'FROM' 		=> 'om_friends AS f',
		'WHERE'		=> 'f.user_id = '.$forum_user['id'].' AND f.friend_id = '.$friend_id,
	);

	($hook = get_hook('om_friends_fn_is_friend_qr_is_friend')) ? eval($hook) : null; 
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$result = $forum_db->result($result) != 0;

	($hook = get_hook('om_friends_fn_is_friends_pre_return')) ? eval($hook) : null; 
	return $result;
}

// adds a friend to database
function om_friends_add_friend($friend_id)
{
	global $forum_db, $forum_user;

	$result = ($hook = get_hook('om_friends_fn_add_friend_start')) ? eval($hook) : null;
	if ($result)
		return;

	if ($forum_user['is_guest']) {
		return;
	}

	$query = array(
		'INSERT' 	=> 'user_id, friend_id',
		'INTO' 		=> 'om_friends',
		'VALUES'	=> $forum_user['id']. ', '. $friend_id
	);
	($hook = get_hook('om_friends_fn_add_friend_qr_add_friend')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
}

// removes a friend from database
function om_friends_del_friend($friend_id)
{
	global $forum_db, $forum_user;

	$result = ($hook = get_hook('om_friends_fn_del_friend_start')) ? eval($hook) : null;
	if ($result)
		return;

	$query = array(
		'DELETE' 	=> 'om_friends',
		'WHERE' 	=> 'user_id = ' . $forum_user['id'] . ' AND friend_id = ' . $friend_id
	);
	($hook = get_hook('om_friends_fn_add_friend_qr_add_friend')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
}


define('OM_FRIENDS_FUNCTION_LOADED', 1);
