<?php
/**
 * List of user's warnings.
 *
 * The user can check the list of his warnings.
 *
 * @copyright (C) 2008-2014 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

// New section in profile - om_warnings
if ($section == 'om_warnings')
{
	// Load functions
	if (!defined('OM_WARNINGS_FUNCTIONS_LOADED'))
		require $ext_info['path'].'/functions.php';

	// Save current time to variable to make it consistent on whole page
	$forum_page['now'] = time();
	$forum_page['sort_dir'] = 'DESC';
	$forum_page['show_expired'] = '0';

	$where_sql[0] = 'u.id = '.$id;
	if ($forum_page['show_expired'] != '1') {
		$where_sql[] = '(o.expire_date IS NULL OR o.expire_date >= '.$forum_page['now'].')';
	}

	// Get list of warnings of current user
	$warnings = om_warnings_get_warning_list(null, null, $where_sql, $forum_page['sort_dir']);

	$forum_page['items_info'] = $lang_om_warnings['User warnings'];

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array(sprintf($lang_profile['Users profile'], $user['username']), forum_link($forum_url['user'], $id)),
		$lang_om_warnings['Section warnings']
	);

	// Setup form
	$forum_page['om_warnings_admin'] = false;
	$forum_page['om_warnings_form'] = ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_om_warnings'] == '1' || $forum_user['g_id'] == FORUM_ADMIN);
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['om_warnings_report_user'], $id);

	$forum_page['hidden_fields'] = array(
		'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
	);

	($hook = get_hook('om_warnings_profile_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'profile-om_warnings');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('om_warnings_profile_output_start')) ? eval($hook) : null;
?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $forum_page['items_info'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php printf($lang_om_warnings['Profile info'], '<a class="exthelp" href="'.forum_link($forum_url['help'], 'om_warnings').'">'.$lang_om_warnings['Help link'].'</a>') ?></p>
		</div>

		<div class="content-head">
			<h3 class="hn">
				<span><?php echo $lang_om_warnings['List of user warnings']; ?></span>
			</h3>
		</div>
<?php
	if (empty($warnings)) {
?>
		<div class="ct-box info-box"><span><?php echo $lang_om_warnings['No user warnings'] ?></span></div>
<?php
	} else {

		$forum_page['item_count'] = $forum_page['fld_count'] = $forum_page['item_num'] = 0;

		foreach ($warnings as $cur_warning) {
			om_warnings_display_warning($forum_page, $cur_warning);
		}
	}

	if ($forum_page['om_warnings_form']) {
?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="add_warn" value="<?php echo $lang_om_warnings['Add warning'] ?>" /></span>
			</div>
		</form>
<?php
	}
?>
	</div>
<?php

	($hook = get_hook('om_warnings_profile_end')) ? eval($hook) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}
