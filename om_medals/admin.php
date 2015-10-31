<?php
/**
 * Medal management page.
 *
 * Allows administrators to add, modify, and remove medals.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_medals
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('om_medals_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';

if (!isset($lang_om_medals))
{
	if (file_exists($ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php'))
		include $ext_info['path'].'/lang/'.$forum_user['language'].'/'.$ext_info['id'].'.php';
	else
		include $ext_info['path'].'/lang/English/'.$ext_info['id'].'.php';
}

if (!defined('OM_MEDALS_FUNCTIONS_LOADED'))
	require $ext_info['path'].'/functions.php';

// Add a "default" medal
if (isset($_POST['add_medal']))
{
	($hook = get_hook('om_medals_add_medal_form_submitted')) ? eval($hook) : null;

	$medal_name = forum_trim($_POST['medal_name']);
	$description = forum_trim($_POST['medal_desc']);
	$position = intval($_POST['position']);

	if ($medal_name == '') {
		$errors[] = $lang_om_medals['Must enter medal name'];
	} else {
		om_medals_upload_image_file($_FILES['medal_img'], $medal_tmp_file, $medal_type, $medal_width, $medal_height);

		if (empty($errors)) {
	
			$query = array(
				'INSERT'	=> 'medal_name, medal_desc, disp_position, '.
				                   'medal_type, medal_width, medal_height',
				'INTO'		=> 'om_medals',
				'VALUES'	=> '\''.$forum_db->escape($medal_name).'\',\''
						.$forum_db->escape($description).'\','.$position.','
						.$medal_type.','.$medal_width.','.$medal_height,
			);

			($hook = get_hook('om_medals_add_medal_qr_add_medal')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			om_medals_move_image($medal_tmp_file, $forum_db->insert_id(), $medal_type);
			
			// regenerate medals cache
			om_medals_generate_medals_cache();

			// Add flash message
			$forum_flash->add_info($lang_om_medals['Medal added']);

			($hook = get_hook('om_medals_add_medal_pre_redirect')) ? eval($hook) : null;

			redirect(forum_link($forum_url['om_medals_admin']), $lang_om_medals['Medal added']);
		}
	}
}


// Delete a medal
else if (isset($_GET['del_medal']))
{
	$medal_id = intval($_GET['del_medal']);
	if ($medal_id < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_medal_cancel']))
		redirect(forum_link($forum_url['om_medals_admin']), $lang_admin_common['Cancel redirect']);

	($hook = get_hook('om_medals_del_medal_form_submitted')) ? eval($hook) : null;

	if (isset($_POST['del_medal_comply']))	// Delete a medal and all its assigments
	{
		@set_time_limit(0);

		// delete medal img
		om_medals_delete_image($medal_id);

		// Delete the medal and any medal specific group permissions
		$query = array(
			'DELETE'	=> 'om_medals',
			'WHERE'		=> 'id='.$medal_id
		);

		($hook = get_hook('om_medals_del_medal_qr_delete_medal')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// delete user assigments
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'om_medals=REPLACE(om_medals,\'['.$medal_id.']\',\'\')',
			'WHERE'		=> 'om_medals LIKE \'%['.$medal_id.']%\'',
		);

		($hook = get_hook('om_medals_del_medal_qr_delete_medal_assign')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// regenerate medals cache
		om_medals_generate_medals_cache();

		// Add flash message
		$forum_flash->add_info($lang_om_medals['Medal deleted']);

		($hook = get_hook('om_medals_del_medal_pre_redirect')) ? eval($hook) : null;

		redirect(forum_link($forum_url['om_medals_admin']), $lang_om_medals['Medal deleted']);
	}
	else	// If the user hasn't confirmed the delete
	{
		$query = array(
			'SELECT'	=> 'm.medal_name',
			'FROM'		=> 'om_medals AS m',
			'WHERE'		=> 'm.id='.$medal_id
		);

		($hook = get_hook('om_medals_del_medal_qr_get_medal_name')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$medal_name = $forum_db->result($result);

		if (is_null($medal_name) || $medal_name === false)
			message($lang_common['Bad request']);


		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin_common['Start'], forum_link($forum_url['admin_index'])),
			array($lang_om_medals['Medals'], forum_link($forum_url['om_medals_admin'])),
			$lang_om_medals['Delete medal']
		);

		($hook = get_hook('om_medals_del_medal_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-om_medals');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('om_medals_del_medal_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_om_medals['Confirm delete medal'], forum_htmlencode($medal_name)) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_medals_admin_delete'], $medal_id) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_medals_admin_delete'], $medal_id)) ?>" />
			</div>
			<div class="ct-box warn-box">
				<p class="warn"><?php echo $lang_om_medals['Delete medal warning'] ?></p>
			</div>
			<div class="frm-buttons">
				<span class="submit primary caution"><input type="submit" name="del_medal_comply" value="<?php echo $lang_om_medals['Delete medal'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_medal_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

<?php

		($hook = get_hook('om_medals_del_medal_end')) ? eval($hook) : null;

		$tpl_temp = forum_trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}
}


// Update medal positions
else if (isset($_POST['update_positions']))
{
	$positions = array_map('intval', $_POST['position']);

	($hook = get_hook('om_medals_update_positions_form_submitted')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'm.id, m.disp_position',
		'FROM'		=> 'om_medals AS m',
		'ORDER BY'	=> 'm.disp_position, m.id'
	);

	($hook = get_hook('om_medals_update_positions_qr_get_medals')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_medal = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a medal that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($positions[$cur_medal['id']]))
		{
			$new_disp_position = $positions[$cur_medal['id']];

			if ($new_disp_position < 0)
				message($lang_om_medals['Must be integer']);

			// We only want to update if we changed the position
			if ($cur_medal['disp_position'] != $new_disp_position)
			{
				$query = array(
					'UPDATE'	=> 'om_medals',
					'SET'		=> 'disp_position='.$new_disp_position,
					'WHERE'		=> 'id='.$cur_medal['id']
				);

				($hook = get_hook('om_medals_update_positions_qr_update_medal_position')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// regenerate medals cache
	om_medals_generate_medals_cache();

	// Add flash message
	$forum_flash->add_info($lang_om_medals['Medal updated']);

	($hook = get_hook('om_medals_update_positions_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['om_medals_admin']), $lang_om_medals['Medal updated']);
}


else if (isset($_GET['edit_medal']))
{
	$medal_id = intval($_GET['edit_medal']);
	if ($medal_id < 1)
		message($lang_common['Bad request']);

	($hook = get_hook('om_medals_edit_medal_selected')) ? eval($hook) : null;

	// Fetch medal info
	$query = array(
		'SELECT'	=> 'm.id, m.medal_name, m.medal_desc, m.disp_position, m.medal_type, m.medal_width, m.medal_height',
		'FROM'		=> 'om_medals AS m',
		'WHERE'		=> 'm.id='.$medal_id
	);

	($hook = get_hook('om_medals_edit_medal_qr_get_medal_details')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$cur_medal = $forum_db->fetch_assoc($result);

	if (is_null($cur_medal) || $cur_medal === false)
		message($lang_common['Bad request']);

	// Update group permissions for $medal_id
	if (isset($_POST['save']))
	{
		($hook = get_hook('om_medals_save_medal_form_submitted')) ? eval($hook) : null;

		// Start with the medal details
		$medal_name = forum_trim($_POST['medal_name']);
		$description = forum_trim($_POST['medal_desc']);
		$position = intval($_POST['position']);

		if ($medal_name == '') {
			$errors[] = $lang_om_medals['Must enter medal name'];
		} else {

			$om_medals_image_submitted = (isset($_FILES['medal_img']) && (!isset($_FILES['medal_img']['error']) || $_FILES['medal_img']['error'] != 4));
			// dont change image if no image file submitted
			if ($om_medals_image_submitted) {
				om_medals_upload_image_file($_FILES['medal_img'], $medal_tmp_file, $medal_type, $medal_width, $medal_height);
			}

			if (empty($errors)) {
		
				$query = array(
					'UPDATE'	=> 'om_medals',
					'SET'		=> 'medal_name=\''.$forum_db->escape($medal_name).'\','.
							   'medal_desc=\''.$forum_db->escape($description).'\','.
							   'disp_position='.$position,
					'WHERE'		=> 'id='.$medal_id,
				);

				// update image data if image was submitted
				if ($om_medals_image_submitted) {
					$query['SET']	.= ',medal_type='.$medal_type.', medal_width='.$medal_width.',medal_height='.$medal_height;
				}

				($hook = get_hook('om_medals_add_medal_qr_add_medal')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);

				// move image to new directory (if submitted)
				if ($om_medals_image_submitted) {
					om_medals_move_image($medal_tmp_file, $medal_id, $medal_type);
				}

				// regenerate medals cache
				om_medals_generate_medals_cache();

				// Add flash message
				$forum_flash->add_info($lang_om_medals['Medal updated']);

				($hook = get_hook('om_medals_save_medal_pre_redirect')) ? eval($hook) : null;

				redirect(forum_link($forum_url['om_medals_admin'], $medal_id), $lang_om_medals['Medal updated']);
			}
		}
	}

	// Setup the form
	$forum_page['item_count'] = $forum_page['group_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Start'], forum_link($forum_url['admin_index'])),
		array($lang_om_medals['Medals'], forum_link($forum_url['om_medals_admin'])),
		$lang_om_medals['Edit medal']
	);

	($hook = get_hook('om_medals_edit_medal_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'start');
	define('FORUM_PAGE', 'admin-om_medals');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('om_medals_edit_medal_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_om_medals['Edit medal head'], forum_htmlencode($cur_medal['medal_name'])) ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php
		// If there were any errors, show them
		if (!empty($errors))
		{
			$forum_page['errors'] = array();
			foreach ($errors as $cur_error)
				$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

			($hook = get_hook('om_medals_edit_medal_pre_errors')) ? eval($hook) : null;

?>
		<div class="ct-box error-box">
			<h2 class="warn hn"><?php echo $lang_om_medals['Medal update errors'] ?></h2>
			<ul class="error-list">
				<?php echo implode("\n\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php
		}
?>
		<form method="post" class="frm-form" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_medals_admin_edit'], $cur_medal['id']) ?>" enctype="multipart/form-data">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_medals_admin_edit'], $cur_medal['id'])) ?>" />
			</div>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_om_medals['Edit medal details head'] ?></span></h3>
			</div>
<?php ($hook = get_hook('om_medals_edit_medal_pre_details_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_medals['Edit medal details legend'] ?></strong></legend>
<?php ($hook = get_hook('om_medals_edit_medal_pre_medal_name')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Medal name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="medal_name" size="35" maxlength="80" value="<?php echo forum_htmlencode($cur_medal['medal_name']) ?>" required /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_medals_edit_medal_pre_medal_descrip')) ? eval($hook) : null; ?>
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Medal description'] ?></span> <small><?php echo $lang_om_medals['Medal description help'] ?></small></label><br />
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="medal_desc" rows="3" cols="50"><?php echo forum_htmlencode($cur_medal['medal_desc']) ?></textarea></span></div>
					</div>
				</div>

<?php ($hook = get_hook('om_medals_edit_medal_pre_position')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" value="<?php echo forum_htmlencode($cur_medal['disp_position']) ?>" name="position" size="3" maxlength="3" /></span>
					</div>
				</div>

<?php ($hook = get_hook('om_medals_edit_medal_pre_cur_img')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label><span><?php echo $lang_om_medals['Current image'] ?></span></label><br />
						<?php
							$om_medals_tag = om_medals_generate_medal_tag($cur_medal['id']);
							if ($om_medals_tag == null) :
						?>
							<span class="fld-input"><?php echo $lang_om_medals['No medal image'] ?></span>
						<?php else : ?>
							<span class="fld-input"><?php echo $om_medals_tag ?></span>
						<?php endif; ?>
					</div>
				</div>

<?php ($hook = get_hook('om_medals_edit_medal_pre_img')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Upload image file'] ?></span><small><?php echo $lang_om_medals['Image upload edit help'] ?></small></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" name="medal_img" type="file" size="40" /></span>
					</div>
				</div>


<?php ($hook = get_hook('om_medals_edit_medal_pre_details_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php
($hook = get_hook('om_medals_edit_medal_details_fieldset_end')) ? eval($hook) : null;
?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('om_medals_edit_medal_end')) ? eval($hook) : null;

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
	array($lang_admin_common['Start'], forum_link($forum_url['admin_index'])),
	array($lang_om_medals['Medals'], forum_link($forum_url['om_medals_admin']))
);

($hook = get_hook('om_medals_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-om_medals');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('om_medals_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_medals['Add medal head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php
		// If there were any errors, show them
		if (!empty($errors))
		{
			$forum_page['errors'] = array();
			foreach ($errors as $cur_error)
				$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

			($hook = get_hook('om_medals_pre_new_medal_pre_errors')) ? eval($hook) : null;

?>
		<div class="ct-box error-box">
			<h2 class="warn hn"><?php echo $lang_om_medals['Medal update errors'] ?></h2>
			<ul class="error-list">
				<?php echo implode("\n\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php
		}
?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_medals_admin']) ?>" enctype="multipart/form-data">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_medals_admin'])) ?>" />
			</div>
<?php ($hook = get_hook('om_medals_pre_add_medal_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_medals['Add medal legend'] ?></strong></legend>
<?php ($hook = get_hook('om_medals_pre_new_medal_name')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Medal name label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" <?php echo isset($_POST['medal_name']) ? 'value="'.forum_htmlencode($_POST['medal_name']).'"' : ''; ?> name="medal_name" size="35" maxlength="80" required /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_medals_pre_new_medal_descrip')) ? eval($hook) : null; ?>
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Medal description'] ?></span> <small><?php echo $lang_om_medals['Medal description help'] ?></small></label><br />
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="medal_desc" rows="3" cols="50"><?php echo isset($_POST['medal_desc']) ? forum_htmlencode($_POST['medal_desc']) : ''; ?></textarea></span></div>
					</div>
				</div>


<?php ($hook = get_hook('om_medals_pre_new_medal_img')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Upload image file'] ?></span><small><?php echo $lang_om_medals['Image upload help'] ?></small></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" name="medal_img" type="file" size="40" /></span>
					</div>
				</div>
<?php ($hook = get_hook('om_medals_pre_new_medal_position')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" <?php echo isset($_POST['position']) ? 'value="'.forum_htmlencode($_POST['position']).'"' : ''; ?>  name="position" size="3" maxlength="3" /></span>
					</div>
				</div>
				
				

				
				
<?php ($hook = get_hook('om_medals_pre_add_medal_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_medals_add_medal_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="add_medal" value=" <?php echo $lang_om_medals['Add medal'] ?> " /></span>
			</div>
		</form>
	</div>

<?php

// Display all the medals
om_medals_load_medals_cache();

if (!empty($forum_om_medals))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_medals['Edit medals head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['om_medals_admin_positions']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['om_medals_admin_positions'])) ?>" />
			</div>

			<div class="content-head">
				<h3 class="hn">
					<span><?php echo $lang_om_medals['List of medals']; ?></span>
				</h3>
			</div>

			<div class="frm-group frm-hdgroup group1">
<?php

	$forum_page['item_count'] = 0;

	foreach ($forum_om_medals as $cur_medal)
	{

($hook = get_hook('om_medals_pre_edit_cur_medal_fieldset')) ? eval($hook) : null;
?>
				<fieldset id="forum<?php echo $cur_medal['id'] ?>" class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php printf($lang_om_medals['Edit or delete'], '<a href="'.forum_link($forum_url['om_medals_admin_edit'], $cur_medal['id']).'">'.$lang_om_medals['Edit'].'</a>', '<a href="'.forum_link($forum_url['om_medals_admin_delete'], $cur_medal['id']).'">'.$lang_om_medals['Delete'].'</a>') ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('om_medals_pre_edit_cur_medal_name')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_medals['Medal name'] ?></span>
							<span class="fld-input"><?php echo forum_htmlencode($cur_medal['name']) ?></span>
						</div>
<?php ($hook = get_hook('om_medals_pre_edit_cur_medal_img')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_medals['Medal image'] ?></span>
							<span class="fld-input"><?php
								$tag = om_medals_generate_medal_tag($cur_medal['id']);
								if ($tag == null)
									echo $lang['No medal image'];
								else
									echo $tag;
							?></span>
						</div>
<?php ($hook = get_hook('om_medals_pre_edit_cur_medal_position')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_medals['Position label'] ?></span></label><br />
							<span class="fld-input"><input type="number" id="fld<?php echo $forum_page['fld_count'] ?>" name="position[<?php echo $cur_medal['id'] ?>]" size="3" maxlength="3" value="<?php echo $cur_medal['position'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('om_medals_pre_edit_cur_medal_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php

		($hook = get_hook('om_medals_edit_cur_medal_fieldset_end')) ? eval($hook) : null;
	}

?>
			</div>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="update_positions" value="<?php echo $lang_om_medals['Update positions'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}


($hook = get_hook('om_medals_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
