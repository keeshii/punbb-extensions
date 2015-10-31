<?php
/**
 * om_thanks functions: database
 *
 * @copyright (C) 2014 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_thanks
 */

if (!defined('FORUM'))
	die();

// refreshes thanks cache for given posts
function om_thanks_rebuild_post_cache($post_id)
{
	global $forum_db;

	($hook = get_hook('om_thanks_fn_rebuild_cache_start')) ? eval($hook) : null;

	// Fetch list of thanks for these posts
	$query = array(
		'SELECT'	=> 'u.id, u.username',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'om_thanks AS t',
				'ON'			=> 't.user_id=u.id'
			)
		),
		'WHERE'		=> 't.post_id = '.$post_id,
		'ORDER BY'	=> 't.added DESC, t.post_id, t.user_id',
	);
	($hook = get_hook('om_thanks_fn_rebuild_cache_qr_get_users')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$post_cache = array();
	while (($cur_thank = $forum_db->fetch_assoc($result))) {
		$post_cache[$cur_thank['id']] = $cur_thank['username'];
	}

	// Update post cache one by one
	$query = array(
		'UPDATE'	=> 'posts',
		'SET'		=> 'om_thanks_cache = \''.$forum_db->escape(serialize($post_cache)).'\'',
		'WHERE'		=> 'id = '.$post_id
	);
	($hook = get_hook('om_thanks_fn_rebuild_cache_qr_update_cache')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

// refreshes number of thanks of specified user
function om_thanks_update_thanks_count($poster_id)
{
	global $forum_db;

	if ($poster_id <= 1)
		return;

	// Count post with thanks
	$query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'om_thanks AS t',
		'WHERE'		=> 'poster_id='.$poster_id
	);
	($hook = get_hook('om_thanks_fn_rebuild_cache_qr_get_poster')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$thanks_count = $forum_db->result($result);

	// Update number of thanks for that user
	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'om_thanks_count = '.$thanks_count,
		'WHERE'		=> 'id = '.$poster_id
	);
	($hook = get_hook('om_thanks_fn_rebuild_cache_qr_update_user')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

// delete thanks together with posts
function om_thanks_delete_post($posts)
{
	global $forum_db;

	if (!is_array($posts)) {
		$posts = array($posts);
	}

	($hook = get_hook('om_thanks_fn_delete_post_start')) ? eval($hook) : null;

	// Count post with thanks
	$query = array(
		'SELECT'	=> 'poster_id',
		'FROM'		=> 'om_thanks AS t',
		'WHERE'		=> 'post_id IN ('.implode(',', $posts).')',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$posters = array();
	while (($cur_thank = $forum_db->fetch_assoc($result))) {
		$posters[] = $cur_thank['poster_id'];
	}

	// Remove him/her from the online list (if they happen to be logged in)
	$query = array(
		'DELETE'	=> 'om_thanks',
		'WHERE'		=> 'post_id IN ('.implode(',', $posts).')'
	);

	($hook = get_hook('om_thanks_fn_delete_post_qr_delete_post')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	foreach($posters as $poster_id) {
		om_thanks_update_thanks_count($poster_id);
	}
}

// delete thanks together with topics
function om_thanks_delete_topic($topics)
{
	global $forum_db;

	if (!is_array($topics)) {
		$topics = array($topics);
	}

	($hook = get_hook('om_thanks_fn_delete_post_start')) ? eval($hook) : null;

	// Count post with thanks
	$query = array(
		'SELECT'	=> 't.post_id, t.poster_id',
		'FROM'		=> 'om_thanks AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'posts AS p',
				'ON'		=> 't.post_id = p.id'
			)
		),
		'WHERE'		=> 'topic_id IN ('.implode(',', $topics).')',
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$posters = array();
	$posts = array();
	while (($cur_thank = $forum_db->fetch_assoc($result))) {
		$posters[$cur_thank['poster_id']] = $cur_thank['poster_id'];
		$posts[$cur_thank['post_id']] = $cur_thank['post_id'];
	}

	// Remove him/her from the online list (if they happen to be logged in)
	if ($posts) {
		$query = array(
			'DELETE'	=> 'om_thanks',
			'WHERE'		=> 'post_id IN ('.implode(',', $posts).')'
		);

		($hook = get_hook('om_thanks_fn_delete_post_qr_delete_post')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	foreach($posters as $poster_id) {
		om_thanks_update_thanks_count($poster_id);
	}
}

// delete thanks together with users
function om_thanks_delete_user($user_id)
{
	global $forum_db;

	($hook = get_hook('om_thanks_fn_delete_post_start')) ? eval($hook) : null;

	// Count post with thanks
	$query = array(
		'SELECT'	=> 'post_id, poster_id',
		'FROM'		=> 'om_thanks',
		'WHERE'		=> 'user_id = '. $user_id,
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$posters = array();
        $posts = array();
	while (($cur_thank = $forum_db->fetch_assoc($result))) {
        	$posts[$cur_thank['post_id']] = $cur_thank['post_id'];
		$posters[$cur_thank['poster_id']] = $cur_thank['poster_id'];
	}

	// Remove him/her from the online list (if they happen to be logged in)
	$query = array(
		'DELETE'	=> 'om_thanks',
		'WHERE'		=> 'user_id = '. $user_id,
	);

	($hook = get_hook('om_thanks_fn_delete_post_qr_delete_post')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	foreach ($posts as $post_id) {
		om_thanks_rebuild_post_cache($post_id);
	}
	foreach ($posters as $poster_id) {
		om_thanks_update_thanks_count($poster_id);
	}
}

// handle nick change of user
function om_thanks_rebuild_user_cache($user_id)
{
	global $forum_db;

	($hook = get_hook('om_thanks_fn_rebuild_user_start')) ? eval($hook) : null;

	// Find all posts made by this user
	$query = array(
		'SELECT'	=> 'post_id',
		'FROM'		=> 'om_thanks AS t',
		'WHERE'		=> 'user_id='.$user_id
	);

	($hook = get_hook('om_thanks_fn_rebuild_user_qr_get_user_posts')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_post = $forum_db->fetch_assoc($result))
	{
		om_thanks_rebuild_post_cache($cur_post['post_id']);
	}
}


define('OM_THANKS_FUNCTION_LOADED', 1);
