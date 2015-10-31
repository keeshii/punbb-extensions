<?php
/**
 * Warning types management page.
 *
 * Allows administrators to add, modify, and remove warning types.
 *
 * @copyright (C) 2008-2014 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('om_warnings_types_start')) ? eval($hook) : null;

// Check permission
if ($forum_user['g_id'] != FORUM_ADMIN)
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


// Add a warning type
if (isset($_POST['add_warning']))
{
	$points = isset($_POST['points']) ? intval($_POST['points']) : 0;
	$expire = isset($_POST['expire']) ? intval($_POST['expire']) : 0;

	$warn_name = forum_trim($_POST['warn_name']);

	($hook = get_hook('om_warnings_add_type_form_submitted')) ? eval($hook) : null;

	// Handle invalid data
	if ($warn_name == '')
		message($lang_om_warnings['Must enter warning name']);

	if ($points < 0)
		message($lang_om_warnings['Must be integer']);

	if ($expire < 0)
		$expire = 0;

	$query = array(
		'INSERT'	=> 'warn_name, points, expire',
		'INTO'		=> 'om_warnings_types',
		'VALUES'	=> '\''.$forum_db->escape($warn_name).'\', '.$points.', '.$expire
	);

	($hook = get_hook('om_warnings_add_type_qr_add_type')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the warnings types cache
	om_warnings_generate_types_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Type added']);

	($hook = get_hook('om_warnings_add_type_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_types']), $lang_om_warnings['Type added']);
}


// Delete a warning type
if (isset($_GET['del_warning']))
{
	$type_to_delete = isset($_GET['del_warning']) ? intval($_GET['del_warning']) : 0;
	if ($type_to_delete < 1)
		message($lang_common['Bad request']);

	($hook = get_hook('om_warnings_del_type_form_submitted')) ? eval($hook) : null;

	// Get users of deleted warnings (to refresh their restrictions)
	$query = array(
		'SELECT'	=> 'DISTINCT user_id',
		'FROM'		=> 'om_warnings_reports',
		'WHERE'		=> 'warn_id = '.$type_to_delete
	);
	($hook = get_hook('om_warnings_list_delete_comply_qr_update users')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$users = array();
	while ($cur_warning = $forum_db->fetch_assoc($result)) {
		$users[$cur_warning['user_id']] = $cur_warning['user_id'];
	}

	// Delete warning reports of given type
	$query = array(
		'DELETE'	=> 'om_warnings_reports',
		'WHERE'		=> 'warn_id = '.$type_to_delete
	);
	($hook = get_hook('om_warnings_list_delete_comply_qr_delete_warnings')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Delete warning types
	$query = array(
		'DELETE'	=> 'om_warnings_types',
		'WHERE'		=> 'id='.$type_to_delete
	);

	($hook = get_hook('om_warnings_del_type_qr_del_type')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Refresh user restrictions
	foreach ($users as $user_id) {
		om_warnings_refresh_user($user_id);
	}

	// Regenerate the warnings types cache
	om_warnings_generate_types_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Type deleted']);

	($hook = get_hook('om_warnings_del_type_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_types']), $lang_om_warnings['Type deleted']);
}


// Update warning points
else if (isset($_POST['update_points']))
{
	$points = array_map('intval', $_POST['points']);

	($hook = get_hook('om_warnings_update_types_form_submitted')) ? eval($hook) : null;

	// Get current list of types with their points
	$query = array(
		'SELECT'	=> 'id, points',
		'FROM'		=> 'om_warnings_types',
		'ORDER BY'	=> 'points, id'
	);

	($hook = get_hook('om_warnings_update_types_qr_get_types')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_warning = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a warning type that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($points[$cur_warning['id']]))
		{
			$new_points = $points[$cur_warning['id']];

			if ($new_points < 0)
				message($lang_om_warnings['Must be integer']);

			// We only want to update if we changed the points
			if ($cur_warning['points'] != $new_points)
			{
				$query = array(
					'UPDATE'	=> 'om_warnings_types',
					'SET'		=> 'points='.$new_points,
					'WHERE'		=> 'id='.$cur_warning['id']
				);

				($hook = get_hook('om_warnings_update_types_qr_update_type_points')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the warnings types cache
	om_warnings_generate_types_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Types updated']);

	($hook = get_hook('om_warnings_update_types_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_types']), $lang_om_warnings['Types updated']);
}

// Edit warning type 
else if (isset($_GET['edit_warning']))
{
	$type_id = intval($_GET['edit_warning']);
	if ($type_id < 1)
		message($lang_common['Bad request']);

	($hook = get_hook('om_warnings_edit_type_selected')) ? eval($hook) : null;

	// Fetch warning type
	$query = array(
		'SELECT'	=> 'o.id, o.warn_name, o.warn_desc, o.points, o.expire, o.restriction',
		'FROM'		=> 'om_warnings_types AS o',
		'WHERE'		=> 'o.id='.$type_id
	);

	($hook = get_hook('om_warnings_edit_type_qr_get_type')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$cur_warning = $forum_db->fetch_assoc($result);

	// Warning type doesn't exist
	if (is_null($cur_warning) || $cur_warning === false)
		message($lang_common['Bad request']);


	// Update warning type
	if (isset($_POST['save']))
	{
		($hook = get_hook('om_warnings_edit_type_form_submitted')) ? eval($hook) : null;

		// Copy values to variables
		$warn_name = forum_trim($_POST['warn_name']);
		$warn_desc = forum_linebreaks(forum_trim($_POST['warn_desc']));
		$points = intval($_POST['points']);
		$expire = intval($_POST['expire']);
		$restriction = (!empty($_POST['restriction']) && $_POST['restriction'] != 'none') ? forum_trim($_POST['restriction']) : null;

		($hook = get_hook('om_warnings_add_type_form_submitted')) ? eval($hook) : null;

		// Check invalid values
		if ($warn_name == '')
			message($lang_om_warnings['Must enter warning name']);

		if ($points < 0)
			message($lang_om_warnings['Must be integer']);

		if ($expire < 0)
			$expire = 0;

		if (!is_null($restriction) && !array_key_exists($restriction, om_warnings_get_restrictions()))
			message($lang_common['Bad request']);

		// Update warning type in database
		$query = array(
			'UPDATE'	=> 'om_warnings_types',
			'SET'		=> 'warn_name=\''.$forum_db->escape($warn_name).'\', warn_desc=\''.$forum_db->escape($warn_desc).'\', points='.$points.', expire='.$expire
					.', restriction='. (is_null($restriction) ? 'NULL' : '\''.$forum_db->escape($restriction).'\''),
			'WHERE'		=> 'id='.$type_id
		);

		($hook = get_hook('om_warnings_add_type_qr_add_type')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the warnings types cache
		om_warnings_generate_types_cache();

		// Add flash message
		$forum_flash->add_info($lang_om_warnings['Type saved']);

		($hook = get_hook('om_warnings_add_type_pre_redirect')) ? eval($hook) : null;

		redirect(forum_link($forum_url['om_warnings_types']), $lang_om_warnings['Type saved']);
	}

	// Setup the form for editing
	$forum_page['fld_count'] = $forum_page['group_count'] = $forum_page['item_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_om_warnings['Warnings'], forum_link($forum_url['om_warnings_list'])),
		array($lang_om_warnings['Warning types'], forum_link($forum_url['om_warnings_types']))
	);

	($hook = get_hook('om_warnings_types_edit_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'om_warnings');
	define('FORUM_PAGE', 'admin-om_warnings_types');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('om_warnings_types_edit_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_warnings['Edit warning type head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_types_edit'], $type_id) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_types_edit'], $type_id)) ?>" />
			</div>
<?php ($hook = get_hook('om_warnings_pre_edit_type_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_warnings['Warning type legend'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_pre_edit_type_name')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="warn_name" size="35" maxlength="80" value="<?php echo forum_htmlencode($cur_warning['warn_name']) ?>" required /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_edit_type_descrip')) ? eval($hook) : null; ?>
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Description label'] ?></span> <small><?php echo $lang_om_warnings['Description label help'] ?></small></label><br />
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="warn_desc" rows="3" cols="50"><?php echo forum_htmlencode($cur_warning['warn_desc']) ?></textarea></span></div>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_edit_type_points')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Points'] ?></span></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="points" size="3" maxlength="3" value="<?php echo $cur_warning['points'] ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_edit_type_expire')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Expire label'] ?></span><small><?php echo $lang_om_warnings['Expire label help'] ?></small></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="expire" size="3" maxlength="3" value="<?php echo $cur_warning['expire'] ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_edit_type_restriction')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Instant restriction'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="restriction">
<?php
	$restrictions = om_warnings_get_restrictions(true);
	foreach ($restrictions as $restriction => $label)
	{
		$selected = ($cur_warning['restriction'] == $restriction) ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t".'<option value="'.$restriction.'"'.$selected.'>'.forum_htmlencode($label).'</option>'."\n";
	}
?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_add_level_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_add_level_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="save" value=" <?php echo $lang_om_warnings['Save warning type'] ?> " /></span>
			</div>
		</form>
	</div>

<?php
	($hook = get_hook('om_warnings_types_edit_end')) ? eval($hook) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Setup the form
$forum_page['fld_count'] = $forum_page['group_count'] = $forum_page['item_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	array($lang_om_warnings['Warnings'], forum_link($forum_url['om_warnings_list'])),
	array($lang_om_warnings['Warning types'], forum_link($forum_url['om_warnings_types']))
);

($hook = get_hook('om_warnings_types_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'om_warnings');
define('FORUM_PAGE', 'admin-om_warnings_types');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('om_warnings_types_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_warnings['Adding warnings'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_types_add']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_types_add'])) ?>" />
			</div>
<?php ($hook = get_hook('om_warnings_pre_add_type_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_warnings['Warning type legend'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_pre_new_type_name')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="warn_name" size="35" maxlength="80" required /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_new_type_points')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Points'] ?></span></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="points" size="3" maxlength="3" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_new_type_expire')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Expire label'] ?></span><small><?php echo $lang_om_warnings['Expire label help'] ?></small></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="expire" size="3" maxlength="3" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_add_level_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_add_level_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="add_warning" value=" <?php echo $lang_om_warnings['Add warning type'] ?> " /></span>
			</div>
		</form>
	</div>

<?php

// Display all warnings
$query = array(
	'SELECT'	=> 'id, warn_name, points',
	'FROM'		=> 'om_warnings_types',
	'ORDER BY'	=> 'points, warn_name, id'
);

($hook = get_hook('om_warnings_types_qr_get_warnings')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

$warnings = array();
while ($cur_warning = $forum_db->fetch_assoc($result))
{
	$warnings[] = $cur_warning;
}

if (!empty($warnings))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_warnings['List of warnings head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_types']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_types'])) ?>" />
			</div>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_om_warnings['List of warnings'] ?></span></h3>
			</div>

			<div class="frm-group frm-hdgroup group1">
<?php
	$forum_page['item_count'] = 0;

	foreach ($warnings as $cur_warning)
	{

($hook = get_hook('om_warnings_pre_edit_cur_type_fieldset')) ? eval($hook) : null;

?>
				<fieldset id="forum<?php echo $cur_warning['id'] ?>" class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php printf($lang_om_warnings['Edit or delete'], '<a href="'.forum_link($forum_url['om_warnings_types_edit'], $cur_warning['id']).'">'.$lang_om_warnings['Edit'].'</a>', '<a href="'.forum_link($forum_url['om_warnings_types_del'], $cur_warning['id']).'">'.$lang_om_warnings['Delete'].'</a>') ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('om_warnings_pre_edit_cur_type_name')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_warnings['Name'] ?></span>
							<span class="fld-input"><?php echo forum_htmlencode($cur_warning['warn_name']) ?></span>
						</div>
<?php ($hook = get_hook('om_warnings_pre_edit_cur_type_points')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Points'] ?></span></label><br />
							<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="points[<?php echo $cur_warning['id'] ?>]" size="3" maxlength="3" value="<?php echo $cur_warning['points'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('om_warnings_pre_edit_cur_type_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php
		($hook = get_hook('om_warnings_edit_cur_type_fieldset_end')) ? eval($hook) : null;
	}
?>
			</div>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="update_points" value="<?php echo $lang_om_warnings['Update points'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}


($hook = get_hook('om_warnings_types_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
