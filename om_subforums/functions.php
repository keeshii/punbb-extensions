<?php
/**
 * om_subforums functions: logic, database and output
 *
 * @copyright (C) 2013 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_subforums
 */

if (!defined('FORUM'))
	die();


// function returns category of forum
// if forum not exists it return negative value -1.
function om_subforums_get_forum_cat($fid)
{
	global $forum_db;

	$query = array(
		'SELECT'	=> 'f.cat_id',
		'FROM'		=> 'forums f',
		'WHERE'		=> 'f.id = '.$fid,
	);

	$cat_id = -1;

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($row = $forum_db->fetch_assoc($result)) {
		$cat_id = $row['cat_id'];
	}

	return $cat_id;
}

// return list of subforums as array from database
function om_subforums_get_subforums($fid)
{
	global $forum_om_subforums, $forum_db, $forum_user;

	($hook = get_hook('om_subforums_fn_get_subforums_start')) ? eval($hook) : null;

	if (!isset($forum_om_subforums)) {
		// retrieves list of subforums and saves it into memory, this is used in several places:
		// to find "last_post" and when displaying list of forums.
		$query = array(
			'SELECT'   => 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.om_subforums_parent_id',
			'FROM'     => 'categories AS c',
			'JOINS'    => array(
				array(
					'INNER JOIN' => 'forums AS f',
					'ON'         => 'c.id = f.cat_id'
				),
				array(
					'LEFT JOIN'  => 'forum_perms AS fp',
					'ON'         => '(fp.forum_id = f.id AND fp.group_id = ' . $forum_user['g_id'] . ')'
				)
			),
			'WHERE'    => '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.om_subforums_parent_id > 0',
			'ORDER BY' => 'c.disp_position, c.id, f.disp_position'
		);

		($hook = get_hook('om_subforums_qr_get_cats_and_forums')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Generate array of subforums
		$forum_om_subforums = array();
		while ($cur_subforum = $forum_db->fetch_assoc($result)) {
			$forum_om_subforums[$cur_subforum['om_subforums_parent_id']][] = $cur_subforum;
		}
	}

	($hook = get_hook('om_subforums_fn_get_subforums_pre_return')) ? eval($hook) : null;

	// Select all subforums
	if (!empty($forum_om_subforums[$fid])) {
		return $forum_om_subforums[$fid];
	}

	return array();
}

// iterates through all subforums and searches for the "realy" last post
// iterates through all subforums and calculates the sum of all topics and posts
function om_subforums_update_forum_info(&$cur_forum)
{
	($hook = get_hook('om_subforums_fn_update_forum_info_start')) ? eval($hook) : null;

	foreach (om_subforums_get_subforums($cur_forum['fid']) as $cur_subforum) {
		if ($cur_subforum['last_post'] != null && $cur_subforum['last_post'] > $cur_forum['last_post']) {
			$cur_forum['last_post'] = $cur_subforum['last_post'];
			$cur_forum['last_post_id'] = $cur_subforum['last_post_id'];
			$cur_forum['last_poster'] = $cur_subforum['last_poster'];

			($hook = get_hook('om_subforums_fn_update_forum_info_last_post')) ? eval($hook) : null;
		}

		$cur_forum['num_topics'] += $cur_subforum['num_topics'];
		$cur_forum['num_posts'] += $cur_subforum['num_posts'];

		($hook = get_hook('om_subforums_fn_update_forum_info_after_row')) ? eval($hook) : null;
	}

	($hook = get_hook('om_subforums_fn_update_forum_info_end')) ? eval($hook) : null;
}


// Any unreaded topics in subforums?
function om_subforums_get_new_topics($fid, $tracked_topics, $new_topics)
{
	global $forum_user;

	($hook = get_hook('om_subforums_fn_get_new_topics_start')) ? eval($hook) : null;

	// iterate with subforums
	foreach (om_subforums_get_subforums($fid) as $cur_subforum) {

		// Are there new posts since our last visit?
		if (!$forum_user['is_guest'] && isset($new_topics[$cur_subforum['fid']]) && $cur_subforum['last_post'] > $forum_user['last_visit'] && (empty($tracked_topics['forums'][$cur_subforum['fid']]) || $cur_subforum['last_post'] > $tracked_topics['forums'][$cur_subforum['fid']])) {

			// There are new posts in this forum, but have we read all of them already?
			foreach ($new_topics[$cur_subforum['fid']] as $check_topic_id => $check_last_post) {

				if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_subforum['fid']]) || $tracked_topics['forums'][$cur_subforum['fid']] < $check_last_post)) {
					return $cur_subforum['fid'];
				}
			}
		}
	}

	($hook = get_hook('om_subforums_fn_get_new_topics_pre_return')) ? eval($hook) : null;

	return null;
}

// returns subforums as text separated by commas.
function om_subforums_get_plain($fid)
{
	global $forum_url;

	($hook = get_hook('om_subforums_fn_get_plain_start')) ? eval($hook) : null;

	$s = array();

	// iterates through subforums and creates links to them
	foreach (om_subforums_get_subforums($fid) as $cur_subforum) {
		$link = forum_link($forum_url['forum'], array($cur_subforum['fid'], sef_friendly($cur_subforum['forum_name'])));
			
		// $link -> link to subforum
		// $cur_subforum['forum_name'] -> name of forum
		// $cur_subforum['forum_desc'] -> forum description
		$s[] = '<a href="' . $link . '">' . $cur_subforum['forum_name'] .'</a>';
	}

	if (empty($s))
		return '';

	($hook = get_hook('om_subforums_fn_get_plain_pre_return')) ? eval($hook) : null;

	return implode(', ', $s);
}


define ('OM_SUBFORUMS_FUNCTIONS_LOADED', 1);
