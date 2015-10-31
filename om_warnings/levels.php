<?php
/**
 * Warning levels managment page.
 *
 * Allows administrators to add, modify, and remove warning levels.
 *
 * @copyright (C) 2008-2014 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('om_warnings_levels_start')) ? eval($hook) : null;

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


// Add a level
if (isset($_POST['add_level']))
{
	$restriction = isset($_POST['restriction']) ? $_POST['restriction'] : '';
	if (!array_key_exists($restriction, om_warnings_get_restrictions()))
		message($lang_common['Bad request']);

	$points = isset($_POST['points']) ? intval($_POST['points']) : 0;

	($hook = get_hook('om_warnings_add_level_form_submitted')) ? eval($hook) : null;

	$query = array(
		'INSERT'	=> 'restriction, points',
		'INTO'		=> 'om_warnings_levels',
		'VALUES'	=> '\''.$forum_db->escape($restriction).'\', '.$points
	);

	($hook = get_hook('om_warnings_add_level_qr_add_level')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the warnings level cache
	om_warnings_generate_levels_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Level added']);

	($hook = get_hook('om_warnings_add_level_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_levels']), $lang_om_warnings['Level added']);
}


// Delete a level
if (isset($_GET['del_level']))
{
	$level_to_delete = isset($_GET['del_level']) ? intval($_GET['del_level']) : 0;
	if ($level_to_delete < 1)
		message($lang_common['Bad request']);

	($hook = get_hook('om_warnings_del_level_form_submitted')) ? eval($hook) : null;

	// Delete the level
	$query = array(
		'DELETE'	=> 'om_warnings_levels',
		'WHERE'		=> 'id='.$level_to_delete
	);

	($hook = get_hook('om_warnings_del_level_qr_del_level')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the warnings level cache
	om_warnings_generate_levels_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Level deleted']);

	($hook = get_hook('om_warnings_del_level_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_levels']), $lang_om_warnings['Level deleted']);
}


// Update forum positions
else if (isset($_POST['update_points']))
{
	$points = array_map('intval', $_POST['points']);

	($hook = get_hook('om_warnings_update_levels_form_submitted')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'id, points',
		'FROM'		=> 'om_warnings_levels',
		'ORDER BY'	=> 'points, id'
	);

	($hook = get_hook('om_warnings_update_levels_qr_get_levels')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_level = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a forum that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($points[$cur_level['id']]))
		{
			$new_points = $points[$cur_level['id']];

			if ($new_points < 0)
				message($lang_om_warnings['Must be integer']);

			// We only want to update if we changed the position
			if ($cur_level['points'] != $new_points)
			{
				$query = array(
					'UPDATE'	=> 'om_warnings_levels',
					'SET'		=> 'points='.$new_points,
					'WHERE'		=> 'id='.$cur_level['id']
				);

				($hook = get_hook('om_warnings_update_levels_qr_update_level_points')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the warnings level cache
	om_warnings_generate_levels_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_warnings['Levels updated']);

	($hook = get_hook('om_warnings_update_levels_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_warnings_levels']), $lang_om_warnings['Levels updated']);
}


// Setup the form
$forum_page['fld_count'] = $forum_page['group_count'] = $forum_page['item_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	array($lang_om_warnings['Warnings'], forum_link($forum_url['om_warnings_list'])),
	array($lang_om_warnings['Warning levels'], forum_link($forum_url['om_warnings_levels']))
);

($hook = get_hook('om_warnings_levels_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'om_warnings');
define('FORUM_PAGE', 'admin-om_warnings_levels');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('om_warnings_levels_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_warnings['Adding levels'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_levels_add']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_levels_add'])) ?>" />
			</div>
<?php ($hook = get_hook('om_warnings_pre_add_level_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_warnings['Warning level legend'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_pre_new_level_restriction')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Restriction'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="restriction">
<?php
	$restrictions = om_warnings_get_restrictions();
	foreach ($restrictions as $restriction => $label)
		echo "\t\t\t\t\t\t\t".'<option value="'.$restriction.'">'.forum_htmlencode($label).'</option>'."\n";
?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_new_level_points')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Points'] ?></span></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="points" size="3" maxlength="3" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_pre_add_level_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_add_level_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="add_level" value=" <?php echo $lang_om_warnings['Add level'] ?> " /></span>
			</div>
		</form>
	</div>

<?php

// Display all the levels of restrictions
$query = array(
	'SELECT'	=> 'owl.id AS id, owl.points, owl.restriction',
	'FROM'		=> 'om_warnings_levels AS owl',
	'ORDER BY'	=> 'owl.points, owl.id'
);

($hook = get_hook('om_warnings_levels_qr_get_levels')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

$levels = array();
while ($cur_level = $forum_db->fetch_assoc($result))
{
	$levels[] = $cur_level;
}

if (!empty($levels))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_warnings['List of levels head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_warnings_levels']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_warnings_levels'])) ?>" />
			</div>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_om_warnings['List of levels'] ?></span></h3>
			</div>

			<div class="frm-group frm-hdgroup group1">
<?php
	$forum_page['item_count'] = 0;

	foreach ($levels as $cur_level)
	{

($hook = get_hook('om_warnings_pre_edit_cur_level_fieldset')) ? eval($hook) : null;

?>
				<fieldset id="forum<?php echo $cur_level['id'] ?>" class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><a href="<?php echo forum_link($forum_url['om_warnings_levels_del'], $cur_level['id']) ?>"><?php echo $lang_om_warnings['Delete'] ?></a></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('om_warnings_pre_edit_cur_level_restriction')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_warnings['Restriction'] ?></span>
							<span class="fld-input"><?php echo forum_htmlencode($restrictions[$cur_level['restriction']]) ?></span>
						</div>
<?php ($hook = get_hook('om_warnings_pre_edit_cur_level_points')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Points'] ?></span></label><br />
							<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="points[<?php echo $cur_level['id'] ?>]" size="4" maxlength="4" value="<?php echo $cur_level['points'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('om_warnings_pre_edit_cur_level_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php
		($hook = get_hook('om_warnings_edit_cur_level_fieldset_end')) ? eval($hook) : null;
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


($hook = get_hook('om_warnings_levels_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
