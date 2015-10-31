<?php

/**
 * om_warnings functions: cache, logic, database and common output
 *
 * @copyright (C) 2014 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 */

if (!defined('FORUM'))
	die();


// Return list of available restrictions
function om_warnings_get_restrictions($allow_none = false)
{
	global $lang_om_warnings;

	$restrictions = array();

	if ($allow_none)
		$restrictions['none'] = $lang_om_warnings['No restriction'];

	$restrictions['om_post'] = $lang_om_warnings['Disallow to write posts'];
	$restrictions['om_edit'] = $lang_om_warnings['Disallow to edit posts'];
	$restrictions['om_topic'] = $lang_om_warnings['Disallow to create topics'];
	$restrictions['om_banned'] = $lang_om_warnings['Ban user'];
	$restrictions['om_signature'] = $lang_om_warnings['Block signature'];
	$restrictions['om_avatar'] = $lang_om_warnings['Block avatar'];

	($hook = get_hook('om_warnings_fn_get_restrictions_pre_return')) ? eval($hook) : null;
	return $restrictions;
}

// Save list of warning levels in the cache
function om_warnings_generate_levels_cache()
{
	global $forum_db;

	// Get list of all warning levels defined by administrator
	$query = array(
		'SELECT'	=> 'id, points, restriction',
		'FROM'		=> 'om_warnings_levels',
	);

	($hook = get_hook('om_warnings_fn_levels_cache_qr_get_levels')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$levels = array();
	while ($cur_level = $forum_db->fetch_assoc($result)) {
		$levels[$cur_level['id']] = $cur_level;
	}

	// Output config as PHP code
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	if (!write_cache_file(FORUM_CACHE_DIR.'cache_om_warnings_levels.php', '<?php'."\n\n".'$om_warnings_levels = '.var_export($levels, true).';'."\n\n".'?>'))
	{
		error('Unable to write configuration cache file to cache directory.<br />Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
	}
}

// Save list of warning types in the cache
function om_warnings_generate_types_cache()
{
	global $forum_db;

	// Get list of all warning types defined by administrator
	$query = array(
		'SELECT'	=> 'id, warn_name, warn_desc, points, expire, restriction',
		'FROM'		=> 'om_warnings_types',
	);

	($hook = get_hook('om_warnings_fn_types_cache_qr_get_types')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$types = array();
	while ($cur_type = $forum_db->fetch_assoc($result)) {
		$types[$cur_type['id']] = $cur_type;
	}

	// Output config as PHP code
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	if (!write_cache_file(FORUM_CACHE_DIR.'cache_om_warnings_types.php', '<?php'."\n\n".'$om_warnings_types = '.var_export($types, true).';'."\n\n".'?>'))
	{
		error('Unable to write configuration cache file to cache directory.<br />Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
	}
}

// Load warning types from cache
function om_warnings_get_warning_types()
{
	// check if cache file exists, not - generate it
	if (!file_exists(FORUM_CACHE_DIR.'cache_om_warnings_types.php'))
		om_warnings_generate_types_cache();

	require FORUM_CACHE_DIR.'cache_om_warnings_types.php';
	return $om_warnings_types;
}

// Load warning levels from cache
function om_warnings_get_warning_levels()
{
	// check if cache file exists, not - generate it
	if (!file_exists(FORUM_CACHE_DIR.'cache_om_warnings_levels.php'))
		om_warnings_generate_levels_cache();

	require FORUM_CACHE_DIR.'cache_om_warnings_levels.php';
	return $om_warnings_levels;
}


// Returns list of warnings with all possible relations
function om_warnings_get_warning_list($start_from, $finish_at, $where_sql, $sort_dir = 'ASC')
{
	global $forum_db;

	// Fetch any unread reports
	$query = array(
		'SELECT'	=> 'o.id, o.user_id, o.warn_id, o.post_id, o.topic_id, o.forum_id, o.reporter_id, o.expire_date, o.created, o.message, p.id AS pid, t.subject, f.forum_name, u.username, u2.username AS reporter, w.warn_name, w.points',
		'FROM'		=> 'om_warnings_reports AS o',
		'JOINS'		=> array(
			array(
				'JOIN'			=> 'om_warnings_types AS w',
				'ON'			=> 'o.warn_id=w.id'
			),
			array(
				'JOIN'			=> 'users AS u',
				'ON'			=> 'o.user_id=u.id'
			),
			array(
				'LEFT JOIN'		=> 'posts AS p',
				'ON'			=> 'o.post_id=p.id'
			),
			array(
				'LEFT JOIN'		=> 'topics AS t',
				'ON'			=> 'o.topic_id=t.id'
			),
			array(
				'LEFT JOIN'		=> 'forums AS f',
				'ON'			=> 'o.forum_id=f.id'
			),
			array(
				'LEFT JOIN'		=> 'users AS u2',
				'ON'			=> 'o.reporter_id=u2.id'
			)
		),
		'ORDER BY'	=> 'o.created '.$sort_dir
	);

	if ($start_from !== null && $finish_at !== null && $start_from <= $finish_at) {
		$query['LIMIT'] = $start_from.', '.($finish_at - $start_from);
	}

	if ($where_sql) {
		$query['WHERE'] = implode(' AND ', $where_sql);
	}

	($hook = get_hook('om_warnings_fn_list_qr_get_warnings')) ? eval($hook) : null;

	// Return warnings as array
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$warnings = array();
	while ($row = $forum_db->fetch_assoc($result))
		$warnings[] = $row;

	return $warnings;
}

// Refresh user restrictions
function om_warnings_refresh_user($user_id)
{
	global $forum_db;

	$user_id = isset($user_id) ? intval($user_id) : 0;

	// Get list of all warning types defined by administrator
	$query = array(
		'SELECT'	=> 'r.id, t.points, r.expire_date, t.restriction',
		'FROM'		=> 'om_warnings_reports AS r',
		'JOINS'		=> array(
			array(
				'JOIN'			=> 'om_warnings_types AS t',
				'ON'			=> 'r.warn_id=t.id'
			),
		),
		'WHERE'		=> '(r.expire_date IS NULL OR r.expire_date > '.time().') AND r.user_id = '.$user_id,
	);

	($hook = get_hook('om_warnings_fn_refresh_user_qr_get_warnings')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Calculate points and get all instant restrictions
	$expire = 0;
	$points = 0;
	$restrictions = array();
	while ($cur_report = $forum_db->fetch_assoc($result)) {
		$points += (!empty($cur_report['points']) ? $cur_report['points'] : 0);

		if (!empty($cur_report['expire_date']) && ($expire == 0 || $cur_report['expire_date'] < $expire)) {
			$expire = $cur_report['expire_date'];
		}

		if (!empty($cur_report['restriction'])) {
			$restrictions[$cur_report['restriction']] = $cur_report['restriction'];
		}
	}

	// Decode restrictions based on point levels
	$levels = om_warnings_get_warning_levels();
	foreach ($levels as $cur_level) {
		if ($cur_level['points'] <= $points) {
			$restrictions[$cur_level['restriction']] = $cur_level['restriction'];
		}
	}

	// Update user restrictions
	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'om_warnings_restrictions=\''.$forum_db->escape(implode(',', $restrictions)).'\', om_warnings_expire = '.$expire,
		'WHERE'		=> 'id='.$user_id
	);
	($hook = get_hook('om_warnings_fn_refresh_user_qr_update_user')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}


// Display warning using the same structure as the reports
function om_warnings_display_warning(&$forum_page, $cur_warning)
{
	global $forum_url, $lang_om_warnings;

		// Prepare warning data
		$forum_page['warning_info'] = array();
		$forum_page['warning_info']['warn_name'] = '<span>'.sprintf($lang_om_warnings['Warning name'], forum_htmlencode($cur_warning['warn_name'])).'</span>';
		$forum_page['warning_info']['points'] = '<span>'.sprintf($lang_om_warnings['Warning points'], $cur_warning['points']).'</span>';
		$forum_page['warning_info']['reporter'] = '<span>'.sprintf($lang_om_warnings['Reported by'], (($cur_warning['reporter'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_warning['reporter_id']).'">'.forum_htmlencode($cur_warning['reporter']).'</a>' : $lang_om_warnings['Deleted reporter'])).'</span>';
		$forum_page['warning_info']['created'] = '<span>'.sprintf($lang_om_warnings['Created'], format_time($cur_warning['created'])).'</span>';
		$forum_page['warning_info']['expire'] = '<span>'.sprintf($lang_om_warnings['Expire'], isset($cur_warning['expire_date']) ? format_time($cur_warning['expire_date']) : $lang_om_warnings['Never']).'</span>';

		$user = ($cur_warning['username'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_warning['user_id']).'">'.forum_htmlencode($cur_warning['username']).'</a>' : $lang_om_warnings['Deleted reporter'];
		$forum = ($cur_warning['forum_name'] != '') ? '<a href="'.forum_link($forum_url['forum'], array($cur_warning['forum_id'], sef_friendly($cur_warning['forum_name']))).'">'.forum_htmlencode($cur_warning['forum_name']).'</a>' : $lang_om_warnings['Deleted forum'];
		$topic = ($cur_warning['subject'] != '') ? '<a href="'.forum_link($forum_url['topic'], array($cur_warning['topic_id'], sef_friendly($cur_warning['subject']))).'">'.forum_htmlencode($cur_warning['subject']).'</a>' : $lang_om_warnings['Deleted topic'];
		$post = ($cur_warning['pid'] != '') ? '<a href="'.forum_link($forum_url['post'], $cur_warning['pid']).'">'.sprintf($lang_om_warnings['Post'], $cur_warning['pid']).'</a>' : $lang_om_warnings['Deleted post'];
		$message = str_replace("\n", '</p><p>', forum_htmlencode($cur_warning['message']));
		$is_expired = ($cur_warning['expire_date'] != null && $cur_warning['expire_date'] < $forum_page['now']);

		$post_path = sprintf('%s  &rarr; %s  &rarr;  %s', $forum, $topic, $post);
		if ($cur_warning['forum_name'] == null && $cur_warning['topic_id'] == null && $cur_warning['pid'] == null) {
			$post_path = $lang_om_warnings['No post'];
		}

		// Setup output
		($hook = get_hook('om_warnings_fn_display_warning_pre_display')) ? eval($hook) : null;
?>
		<div class="ct-set <?php echo $is_expired ? 'data-set' : 'warn-set' ?> report set<?php echo ++$forum_page['item_count'] ?>">
			<div class="ct-box <?php echo $is_expired ? 'data-box' : 'warn-box' ?>">
				<h3 class="ct-legend hn"><strong><?php echo ++$forum_page['item_num'] ?></strong> <cite class="username"><?php printf($lang_om_warnings['User'], $user) ?></cite>
					<?php echo implode("\n\t\t\t\t\t", $forum_page['warning_info'])."\n" ?>
				</h3>
				<h4 class="hn"><?php echo $post_path ?></h4>
				<p><?php echo $message ?></p>
<?php if ($forum_page['om_warnings_admin']) { ?>
				<p class="item-select"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="warn[<?php echo $cur_warning['id'] ?>]" value="<?php echo $cur_warning['id'] ?>" /> <label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_om_warnings['Select report'] ?></label></p>
<?php } ?>
<?php ($hook = get_hook('om_warnings_list_warning_block')) ? eval($hook) : null; ?>
			</div>
		</div>
<?php
}

define('OM_WARNINGS_FUNCTIONS_LOADED', 1);
