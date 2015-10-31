<?php
// this file is mainly copied from index.php (showing list of forums)


if (!defined('FORUM')) {
	die();
}


// correct the last post listed in the index page
if(!defined('OM_SUBFORUMS_FUNCTIONS_LOADED'))
	require $ext_info['path'].'/functions.php';


if (count(om_subforums_get_subforums($id)) > 0) {

	if (!$forum_user['is_guest'])
	{
		$query = array(
			'SELECT'	=> 't.forum_id, t.id, t.last_post',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$forum_user['last_visit'].' AND t.moved_to IS NULL'
		);

		($hook = get_hook('in_qr_get_new_topics')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$new_topics = array();
		while ($cur_topic = $forum_db->fetch_assoc($result))
			$new_topics[$cur_topic['forum_id']][$cur_topic['id']] = $cur_topic['last_post'];


		$tracked_topics = get_tracked_topics();
	}

	// Load the index.php language file
	require FORUM_ROOT.'lang/'.$forum_user['language'].'/index.php';

	if (!isset($lang_om_subforums))
	{
		if (file_exists($ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php'))
			include $ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php';
		else
			include $ext_info['path'].'/lang/English/'.$ext_info['id'].'.php';
	}

	// Headers
	// -> .main-head
	$forum_page['subforum_header'] = array();
	$forum_page['subforum_header']['header'] = $lang_om_subforums['Subforums'] . ': ' . count(om_subforums_get_subforums($id));
	$forum_page['subforum_header']['subject']['title'] = '<strong class="subject-title">'.$lang_om_subforums['Subforums'].'</strong>';
	$forum_page['subforum_header']['info']['topics'] = '<strong class="info-topics">'.$lang_index['topics'].'</strong>';
	$forum_page['subforum_header']['info']['post'] = '<strong class="info-posts">'.$lang_index['posts'].'</strong>';
	$forum_page['subforum_header']['info']['lastpost'] = '<strong class="info-lastpost">'.$lang_index['last post'].'</strong>';

?>	<div class="main-head">
		<h2 class="hn"><span><?php echo forum_htmlencode($forum_page['subforum_header']['header']) ?></span></h2>
	</div>
	<div class="main-subhead">
		<p class="item-summary"><span><?php printf($lang_index['Category subtitle'], implode(' ', $forum_page['subforum_header']['subject']), implode(', ', $forum_page['subforum_header']['info'])) ?></span></p>
	</div>
	<div id="category1" class="main-content main-forum forum-views">
<?php

	$forum_page['item_count'] = 0;

	// -> .main-content
	foreach (om_subforums_get_subforums($id) as $cur_forum) {

	($hook = get_hook('om_subforums_forum_loop_start')) ? eval($hook) : null;

	++$forum_page['item_count'];

	// Reset arrays and globals for each forum
	$forum_page['item_status'] = $forum_page['item_subject'] = $forum_page['item_body'] = $forum_page['item_title'] = array();

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_page['item_body']['subject']['title'] = '<h3 class="hn"><a class="external" href="'.forum_htmlencode($cur_forum['redirect_url']).'" title="'.sprintf($lang_index['Link to'], forum_htmlencode($cur_forum['redirect_url'])).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a></h3>';
		$forum_page['item_status']['redirect'] = 'redirect';

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['desc'] = $cur_forum['forum_desc'];

		$forum_page['item_subject']['redirect'] = '<span>'.$lang_index['External forum'].'</span>';

		($hook = get_hook('om_subforums_redirect_row_pre_item_subject_merge')) ? eval($hook) : null;

		if (!empty($forum_page['item_subject']))
			$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';

		// Forum topic and post count
		$forum_page['item_body']['info']['topics'] = '<li class="info-topics"><span class="label">'.$lang_index['No topic info'].'</span></li>';
		$forum_page['item_body']['info']['posts'] = '<li class="info-posts"><span class="label">'.$lang_index['No post info'].'</span></li>';
		$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_index['No lastpost info'].'</span></li>';

		($hook = get_hook('om_subforums_redirect_row_pre_display')) ? eval($hook) : null;
	}
	else
	{
		// Setup the title and link to the forum
		$forum_page['item_title']['title'] = '<a href="'.forum_link($forum_url['forum'], array($cur_forum['fid'], sef_friendly($cur_forum['forum_name']))).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a>';

		// Are there new posts since our last visit?
		if (!$forum_user['is_guest'] && $cur_forum['last_post'] > $forum_user['last_visit'] && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_forum['fid']]))
		{
			// There are new posts in this forum, but have we read all of them already?
			foreach ($new_topics[$cur_forum['fid']] as $check_topic_id => $check_last_post)
			{
				if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $tracked_topics['forums'][$cur_forum['fid']] < $check_last_post))
				{
					$forum_page['item_status']['new'] = 'new';
					$forum_page['item_title']['status'] = '<small>'.sprintf($lang_index['Forum has new'], '<a href="'.forum_link($forum_url['search_new_results'], $cur_forum['fid']).'" title="'.$lang_index['New posts title'].'">'.$lang_index['Forum new posts'].'</a>').'</small>';

					break;
				}
			}
		}

		($hook = get_hook('om_subforums_normal_row_pre_item_title_merge')) ? eval($hook) : null;

		$forum_page['item_body']['subject']['title'] = '<h3 class="hn">'.implode(' ', $forum_page['item_title']).'</h3>';


		// Setup the forum description and mod list
		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['desc'] = $cur_forum['forum_desc'];

		if ($forum_config['o_show_moderators'] == '1' && $cur_forum['moderators'] != '')
		{
			$forum_page['mods_array'] = unserialize($cur_forum['moderators']);
			$forum_page['item_mods'] = array();

			foreach ($forum_page['mods_array'] as $mod_username => $mod_id)
				$forum_page['item_mods'][] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $mod_id).'">'.forum_htmlencode($mod_username).'</a>' : forum_htmlencode($mod_username);

			($hook = get_hook('om_subforums_row_modify_modlist')) ? eval($hook) : null;

			$forum_page['item_subject']['modlist'] = '<span class="modlist">'.sprintf($lang_index['Moderated by'], implode(', ', $forum_page['item_mods'])).'</span>';
		}

		($hook = get_hook('om_subforums_normal_row_pre_item_subject_merge')) ? eval($hook) : null;

		if (!empty($forum_page['item_subject']))
			$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';


		// Setup forum topics, post count and last post
		$forum_page['item_body']['info']['topics'] = '<li class="info-topics"><strong>'.forum_number_format($cur_forum['num_topics']).'</strong> <span class="label">'.(($cur_forum['num_topics'] == 1) ? $lang_index['topic'] : $lang_index['topics']).'</span></li>';
		$forum_page['item_body']['info']['posts'] = '<li class="info-posts"><strong>'.forum_number_format($cur_forum['num_posts']).'</strong> <span class="label">'.(($cur_forum['num_posts'] == 1) ? $lang_index['post'] : $lang_index['posts']).'</span></li>';

		if ($cur_forum['last_post'] != '')
			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_index['Last post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $cur_forum['last_post_id']).'">'.format_time($cur_forum['last_post']).'</a></strong> <cite>'.sprintf($lang_index['Last poster'], forum_htmlencode($cur_forum['last_poster'])).'</cite></li>';
		else
			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><strong>'.$lang_common['Never'].'</strong></li>';

		($hook = get_hook('om_subforums_normal_row_pre_display')) ? eval($hook) : null;
	}

	// Generate classes for this forum depending on its status
	$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? ' odd' : ' even').(($forum_page['item_count'] == 1) ? ' main-first-item' : '').((!empty($forum_page['item_status'])) ? ' '.implode(' ', $forum_page['item_status']) : '');

	($hook = get_hook('om_subforums_row_pre_display')) ? eval($hook) : null;

?>		<div id="forum<?php echo $cur_forum['fid'] ?>" class="main-item<?php echo $forum_page['item_style'] ?>">
			<span class="icon <?php echo implode(' ', $forum_page['item_status']) ?>"><!-- --></span>
			<div class="item-subject">
				<?php echo implode("\n\t\t\t\t", $forum_page['item_body']['subject'])."\n" ?>
			</div>
			<ul class="item-info">
				<?php echo implode("\n\t\t\t\t", $forum_page['item_body']['info'])."\n" ?>
			</ul>
		</div>
<?php
	}
?>
	</div>
<?php
}
