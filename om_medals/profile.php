<?php
/**
 * Medal assigment page.
 *
 * Allows moderators and administrators to assign medal to users.
 *
 * @copyright (C) 2008-2013 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_medals
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();


if ($section == 'om_medals' && ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_om_medals'] == '1')))
{
	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array(sprintf($lang_profile['Users profile'], $user['username']), forum_link($forum_url['user'], $id)),
		sprintf($lang_om_medals['Section medals'])
	);

	// Setup form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['om_medals_profile_assign'], $id);

	$forum_page['hidden_fields'] = array(
		// 'action'	=> '<input type="hidden" name="action" value="om_medals_assign" />',
		'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
	);

	($hook = get_hook('om_medals_profile_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'profile-om_medals');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();


	if (!defined('OM_MEDALS_FUNCTIONS_LOADED'))
		require $ext_info['path'].'/functions.php';

	// Display all the medals
	om_medals_load_medals_cache();


	($hook = get_hook('om_medals_profile_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_om_medals['Medals assignment'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php
	if (empty($forum_om_medals)) {
?>
		<div class="mf-box"><span><?php echo $lang_om_medals['No medals definied'] ?></span></div>
<?php
	} else {
?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
			<div class="content-head">
				<h3 class="hn">
					<span><?php echo $lang_om_medals['Assignment of medals']; ?></span>
				</h3>
			</div>

			<div class="frm-group frm-hdgroup group1">
<?php

	$forum_page['item_count'] = $forum_page['fld_count'] = 0;
	$om_medals_of_user = om_medals_decode_medal_string($user['om_medals']);

		foreach ($forum_om_medals as $cur_medal)
		{

($hook = get_hook('om_medals_profile_cur_medal_fieldset')) ? eval($hook) : null;
?>
				<fieldset id="forum<?php echo $cur_medal['id'] ?>" class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend>
						<span><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="assign[]" value="<?php echo $cur_medal['id'] ?>" <?php
						echo in_array($cur_medal['id'], $om_medals_of_user) ? 'checked="checked"' : '' ?> /></span>
					</legend>
					<div class="mf-box">
<?php ($hook = get_hook('om_medals_profile_cur_medal_name')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_medals['Medal name'] ?></span>
							<span class="fld-input"><?php echo forum_htmlencode($cur_medal['name']) ?></span>
						</div>

<?php ($hook = get_hook('om_medals_profile_cur_medal_desc')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel"><?php echo $lang_om_medals['Medal description'] ?></span>
							<span class="fld-input"><?php echo empty($cur_medal['desc']) ? '-' : $cur_medal['desc'] ?></span>
						</div>

<?php ($hook = get_hook('om_medals_profile_cur_medal_img')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<span class="aslabel"><?php echo $lang_om_medals['Medal image'] ?></span>
							<span class="fld-input"><?php
								$tag = om_medals_generate_medal_tag($cur_medal['id']);
								if ($tag == null)
									echo $lang['No medal image'];
								else
									echo $tag;
							?></span>
						</div>
					</div>
<?php ($hook = get_hook('om_medals_profile_cur_medal_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php

		}
?>
			</div>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="update_positions" value="<?php echo $lang_om_medals['Update assignment'] ?>" /></span>
			</div>
		</form>
<?php
	}

($hook = get_hook('om_medals_profile_fieldset_end')) ? eval($hook) : null;
?>
	</div>
<?php


	($hook = get_hook('om_medals_profile_end')) ? eval($hook) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}
