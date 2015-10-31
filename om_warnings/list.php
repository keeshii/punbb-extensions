<?php
/**
 * Warnings listing.
 *
 * Search for warnings and delete them.
 *
 * @copyright (C) 2008-2014 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

// Number of warnings displaying on 1 page.
define ('OM_WARNINGS_WARN_PER_PAGE', 20);

require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('om_warnings_list_start')) ? eval($hook) : null;

// Check permissions
if (($forum_user['g_moderator'] != '1' || $forum_user['g_mod_om_warnings'] != '1') && $forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';

// Load om_warnings language file
if (!isset($lang_om_warnings))
{
	if (file_exists($ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php'))
		include $ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php';
	else
		include $ext_info['path'].'/lang/English/'.$ext_info['id'].'.php';
}

// Load functions
if (!defined('OM_WARNINGS_FUNCTIONS_LOADED'))
	require $ext_info['path'].'/functions.php';


// Delete the warning report
if (isset($_POST['delete_warn']) || isset($_POST['delete_warn_comply'])) {

		($hook = get_hook('om_warnings_list_delete_selected_form_submitted')) ? eval($hook) : null;

		$warnings = isset($_POST['warn']) && !empty($_POST['warn']) ? $_POST['warn'] : array();
		$warnings = array_map('intval', (is_array($warnings) ? $warnings : explode(',', $warnings)));

		if (empty($warnings))
			message($lang_om_warnings['No warnings selected']);

		if (isset($_POST['delete_warn_comply']))
		{
			if (!isset($_POST['req_confirm']))
				redirect(forum_link($forum_url['om_warnings_list']), $lang_common['No confirm redirect']);

			($hook = get_hook('om_warnings_list_delete_comply_form_submitted')) ? eval($hook) : null;

			// Verify that the warning IDs are valid
			$query = array(
				'SELECT'	=> 'COUNT(o.id)',
				'FROM'		=> 'om_warnings_reports AS o',
				'WHERE'		=> 'o.id IN('.implode(',', $warnings).')'
			);
			($hook = get_hook('om_warnings_list_delete_comply_qr_verify_warning_ids')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if ($forum_db->result($result) != count($warnings))
				message($lang_common['Bad request']);


			// Get users of deleted warnings (to refresh their restrictions)
			$query = array(
				'SELECT'	=> 'DISTINCT user_id',
				'FROM'		=> 'om_warnings_reports',
				'WHERE'		=> 'id IN('.implode(',', $warnings).')'
			);
			($hook = get_hook('om_warnings_list_delete_comply_qr_update users')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$users = array();
			while ($cur_warning = $forum_db->fetch_assoc($result)) {
				$users[$cur_warning['user_id']] = $cur_warning['user_id'];
			}

			// Delete the warning reports
			$query = array(
				'DELETE'	=> 'om_warnings_reports',
				'WHERE'		=> 'id IN('.implode(',', $warnings).')'
			);
			($hook = get_hook('om_warnings_list_delete_comply_qr_delete_warnings')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// Refresh user restrictions
			foreach ($users as $user_id) {
				om_warnings_refresh_user($user_id);
			}

			$forum_flash->add_info($lang_om_warnings['Delete warnings redirect']);

			($hook = get_hook('om_warnings_list_delete_comply_pre_redirect')) ? eval($hook) : null;

			redirect(forum_link($forum_url['om_warnings_list']), $lang_om_warnings['Delete warnings redirect']);
		}

		// From not submitted, display confirmation dialog.
		$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['om_warnings_list']);

		$forum_page['hidden_fields'] = array(
			'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />',
			'warn'		=> '<input type="hidden" name="warn" value="'.implode(',', $warnings).'" />'
		);

		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_om_warnings['Warnings'], forum_link($forum_url['om_warnings_list'])),
			array($lang_om_warnings['Warning list'], forum_link($forum_url['om_warnings_list'])),
			$lang_om_warnings['Delete warnings']
		);

		($hook = get_hook('om_warnings_list_delete_selected_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'dialogue');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('om_warnings_list_delete_selected_output_start')) ? eval($hook) : null;

?>
	<div class="main-head">
		<h2 class="hn"><span><?php echo $lang_om_warnings['Confirm warning delete'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('om_warnings_list_confirm_delete_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_misc['Delete posts'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_list_confirm_delete_pre_confirm_checkbox')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_common['Please confirm'] ?></span> <?php echo $lang_om_warnings['Confirm warning delete message'] ?>.</label>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_list_confirm_delete_pre_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_list_confirm_delete_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary caution"><input type="submit" name="delete_warn_comply" value="<?php echo $lang_common['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" formnovalidate /></span>
			</div>
		</form>
	</div>
<?php
		($hook = get_hook('om_warnings_list_delete_selected_end')) ? eval($hook) : null;

		$tpl_temp = forum_trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
}

// Save current time to variable to make it consistent on whole page
$forum_page['now'] = time();

// Decode filter parameters
$forum_page['username'] = '';
if (isset($_REQUEST['username']) && is_string($_REQUEST['username']) && $_REQUEST['username'] != '-') {
	$forum_page['username'] = $_REQUEST['username'];
}
$forum_page['show_expired'] = (isset($_REQUEST['show_expired']) && $_REQUEST['show_expired'] == '1' ? '1' : '0');
$forum_page['sort_dir'] = (!isset($_REQUEST['sort_dir']) || strtoupper($_REQUEST['sort_dir']) != 'ASC' && strtoupper($_REQUEST['sort_dir']) != 'DESC') ? 'DESC' : strtoupper($_REQUEST['sort_dir']);

// Generate where directive
$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
$where_sql = array();
if ($forum_page['username'] != '') {
	$where_sql[] = 'u.username '.$like_command.' \''.$forum_db->escape(str_replace('*', '%', $forum_page['username'])).'\'';
}
if ($forum_page['show_expired'] != '1') {
	$where_sql[] = '(o.expire_date IS NULL OR o.expire_date >= '.$forum_page['now'].')';
}

// Fetch warnings count
$query = array(
	'SELECT'	=> 'COUNT(o.id)',
	'FROM'		=> 'om_warnings_reports AS o',
	'JOINS'		=> array(
		array(
			'JOIN'			=> 'users AS u',
			'ON'			=> 'o.user_id=u.id'
		)
	)
);

if ($where_sql) {
	$query['WHERE'] = implode(' AND ', $where_sql);
}

($hook = get_hook('om_warnings_list_qr_get_warning_count')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$forum_page['num_warn'] = $forum_db->result($result);

// Handling the pagination
$forum_page['num_pages'] = ceil($forum_page['num_warn'] / OM_WARNINGS_WARN_PER_PAGE);
$forum_page['page'] = (!isset($_GET['p']) || !is_numeric($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : intval($_GET['p']);
$forum_page['start_from'] = OM_WARNINGS_WARN_PER_PAGE * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + OM_WARNINGS_WARN_PER_PAGE), ($forum_page['num_warn']));

$warnings = om_warnings_get_warning_list($forum_page['start_from'], $forum_page['finish_at'], $where_sql, $forum_page['sort_dir']);

$forum_page['items_info'] = $lang_om_warnings['Warning list heading'];

// Generate paging links
$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['om_warnings_browse'], $lang_common['Paging separator'], array($forum_page['sort_dir'], $forum_page['show_expired'], ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'</p>';

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['om_warnings_browse'], $forum_url['page'], $forum_page['num_pages'], array($forum_page['sort_dir'], $forum_page['show_expired'],($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['om_warnings_browse'], $forum_url['page'], ($forum_page['page'] + 1), array($forum_page['sort_dir'], $forum_page['show_expired'],($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['om_warnings_browse'], $forum_url['page'], ($forum_page['page'] - 1), array($forum_page['sort_dir'], $forum_page['show_expired'],($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['om_warnings_browse'], array($forum_page['sort_dir'], $forum_page['show_expired'],($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' 1" />';
}

// Setup the form
$forum_page['om_warnings_admin'] = true;
$forum_page['fld_count'] = $forum_page['group_count'] = $forum_page['item_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	array($lang_om_warnings['Warnings'], forum_link($forum_url['om_warnings_list'])),
	array($lang_om_warnings['Warning list'], forum_link($forum_url['om_warnings_list']))
);

($hook = get_hook('om_warnings_list_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'om_warnings');
define('FORUM_PAGE', 'admin-om_warnings_list');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('om_warnings_list_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $forum_page['items_info'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form id="afocus" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_list']) ?>">
		<div class="hidden">
			<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_list'])) ?>" />
		</div>
		<div class="frm-form">
<?php ($hook = get_hook('om_warnings_list_search_fieldset_start')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_warnings['Warning list legend'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_list_pre_username')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Search for username'] ?></span> <small><?php echo $lang_om_warnings['Username help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="username" value="<?php echo forum_htmlencode($forum_page['username']) ?>" size="35" maxlength="25" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_list_pre_show_expired')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="show_expired" value="1"<?php if ($forum_page['show_expired'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_om_warnings['Show expired label'] ?></label>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_list_pre_sort_order_fieldset')) ? eval($hook) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_om_warnings['Warning sort order'] ?></span></legend>
<?php ($hook = get_hook('om_warnings_list_pre_sort_order')) ? eval($hook) : null; ?>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="sort_dir" value="ASC"<?php if ($forum_page['sort_dir'] == 'ASC') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_om_warnings['Ascending'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="sort_dir" value="DESC"<?php if ($forum_page['sort_dir'] == 'DESC') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_om_warnings['Descending'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('om_warnings_list_pre_sort_order_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php ($hook = get_hook('om_warnings_list_pre_search_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_list_search_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="search" value="<?php echo $lang_om_warnings['Submit warning search'] ?>" /></span>
			</div>
		</div>
		</form>
<?php
if (!empty($warnings))
{
?>
		<form id="arp-new-report-form" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_list']) ?>">
		<div class="hidden">
			<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_list'])) ?>" />
		</div>
<?php

	$forum_page['item_num'] = $forum_page['start_from'];

	foreach ($warnings as $cur_warning)
	{
		om_warnings_display_warning($forum_page, $cur_warning);
	}

?>
		<div class="frm-buttons">
			<span class="submit primary"><input type="submit" name="delete_warn" value="<?php echo $lang_om_warnings['Delete selected'] ?>" /></span>
		</div>
		</form>
<?php
}
else
{

?>
		<div class="ct-box">
			<p><strong><?php echo $lang_om_warnings['No warnings found'] ?></strong></p>
		</div>
<?php

}

?>
	</div>
	<div class="main-foot">
<?php

	if (!empty($forum_page['main_foot_options']))
		echo "\n\t\t\t".'<p class="options">'.implode(' ', $forum_page['main_foot_options']).'</p>';

?>
		<h2 class="hn"><span><?php echo $forum_page['items_info'] ?></span></h2>
	</div>
<?php

($hook = get_hook('om_warnings_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
