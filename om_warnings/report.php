<?php
/**
 * Page for warnings reporting.
 *
 * Admin and moderators can here report a warning.
 *
 * @copyright (C) 2008-2014 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 * @author keeshii
 */

if (!defined('FORUM_ROOT'))
	die();

($hook = get_hook('om_warnings_add_start')) ? eval($hook) : null;

// Check permission
if (($forum_user['g_moderator'] != '1' || $forum_user['g_mod_om_warnings'] != '1') && $forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

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

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Set pages url based on what we are warning - user or post
if ($post_id >= 1) {
	$forum_page['form_action'] = forum_link($forum_url['om_warnings_report'], $post_id);
	$forum_page['redirect_page'] = forum_link($forum_url['post'], $post_id);
} elseif ($user_id >= 1) {
	$forum_page['form_action'] = forum_link($forum_url['om_warnings_report_user'], $user_id);
	$forum_page['redirect_page'] = forum_link($forum_url['om_warnings_profile'], $user_id);
} else
	message($lang_common['Bad request']);

// User pressed the cancel button
if (isset($_POST['cancel']))
	redirect($forum_page['redirect_page'], $lang_common['Cancel redirect']);

// Add warning
if (isset($_POST['form_sent']))
{
	$warn_id = isset($_POST['warn_id']) ? intval($_POST['warn_id']) : 0;

	($hook = get_hook('om_warnings_report_form_submitted')) ? eval($hook) : null;

	// Invalid warning ID
	if ($warn_id < 1)
		message($lang_common['Bad request']);

	// Start with a clean slate
	$errors = array();

	// Clean up reason from POST
	$message = forum_linebreaks(forum_trim($_POST['message']));
	if ($message == '')
		message($lang_om_warnings['No message']);

	if (strlen($message) > FORUM_MAX_POSTSIZE_BYTES) {
		$errors[] = sprintf($lang_om_warnings['Too long message'], forum_number_format(strlen($message)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES));
	}

	if (empty($errors)) {

		if ($post_id >= 1) {
			// Get some info about the topic we're reporting
			$query = array(
				'SELECT'	=> 'p.poster_id, p.poster, u.email AS poster_email, u.language AS poster_language, t.id, t.subject, t.forum_id, f.moderators',
				'FROM'		=> 'posts AS p',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'topics AS t',
						'ON'			=> 't.id=p.topic_id'
					),
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					),
					array(
						'INNER JOIN'	=> 'users AS u',
						'ON'			=> 'u.id=p.poster_id'
					)
				),
				'WHERE'		=> 'p.id='.$post_id
			);

			($hook = get_hook('om_warning_report_qr_get_topic_data')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$topic_info = $forum_db->fetch_assoc($result);
			$user_id = $topic_info['poster_id'];
			$username = $topic_info['poster'];
			$user_email = $topic_info['poster_email'];
			$user_language = $topic_info['poster_language'];

			// Check if post really exists
			if (!$topic_info)
				message($lang_common['Bad request']);

			// Check if moderator has permission to this topic
			$mods_array = ($topic_info['moderators'] != '') ? unserialize($topic_info['moderators']) : array();
			if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || !array_key_exists($forum_user['username'], $mods_array))) {
				message($lang_common['No permission']);
			}
		} else {
			// Get some info about user we are reporting
			// we need his username and e-mail to send the notification
			$query = array(
				'SELECT'	=> 'id, username, email, language',
				'FROM'		=> 'users',
				'WHERE'		=> 'id='.$user_id
			);

			($hook = get_hook('om_warning_report_qr_get_user_data')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$user_info = $forum_db->fetch_assoc($result);
			$username = $user_info['username'];
			$user_email = $user_info['email'];
			$user_language = $user_info['language'];
			$topic_info['id'] = $topic_info['forum_id'] = $post_id = 'NULL';
			$topic_info['poster_id'] = $user_id;

			// Check if user really exists
			if (!$user_info)
				message($lang_common['Bad request']);
		}

		// Get the expire time of selected warning type
		$query = array(
			'SELECT'	=> 'expire',
			'FROM'		=> 'om_warnings_types',
			'WHERE'		=> 'id='.$warn_id
		);

		($hook = get_hook('om_warning_report_qr_get_warning_data')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$warn_info = $forum_db->fetch_assoc($result);

		if (!$warn_info)
			message($lang_common['Bad request']);

		if ($topic_info['poster_id'] <= 1)
			message($lang_om_warnings['Must not be guest']);

		// Add warning report
		$now = time();
		$expire_seconds = ($warn_info['expire'] > 0 ? 24 * 3600 * $warn_info['expire'] : null);
		$query = array(
			'INSERT'	=> 'user_id, warn_id, post_id, topic_id, forum_id, reporter, reporter_id, expire_date, created, message',
			'INTO'		=> 'om_warnings_reports',
			'VALUES'	=> $topic_info['poster_id'].', '.$warn_id.', '.$post_id.', '.$topic_info['id'].', '.$topic_info['forum_id'].', \''.$forum_db->escape($forum_user['username']).'\', '.$forum_user['id'].', '
					.($expire_seconds ? $now + $expire_seconds : 'NULL').', '.$now.', \''.$forum_db->escape($message).'\''
		);

		($hook = get_hook('om_warnings_report_qr_add_warning')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// If notifications are turned on, send an e-mail to user
		if ($forum_config['o_om_warnings_email'] == '1')
		{
			// Load the "warning" template
			if (file_exists($ext_info['path'].'/lang/'.$user_language.'/mail_templates/warning_notify.tpl'))
				$mail_tpl = forum_trim(file_get_contents($ext_info['path'].'/lang/'.$user_language.'/mail_templates/warning_notify.tpl'));
			else
				$mail_tpl = forum_trim(file_get_contents($ext_info['path'].'/lang/English/mail_templates/warning_notify.tpl'));

			// The first row contains the subject
			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = forum_trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = forum_trim(substr($mail_tpl, $first_crlf));

			// Do the generic replacements first (they apply to all e-mails sent out here)
			$mail_subject = str_replace('<username>', $username, $mail_subject);
			$mail_message = str_replace('<username>', $username, $mail_message);
			$mail_message = str_replace('<base_url>', $base_url.'/', $mail_message);
			$mail_message = str_replace('<warning_list>', str_replace('&amp;', '&', forum_link($forum_url['om_warnings_profile'], $user_id)), $mail_message);
			$mail_message = str_replace('<warning_help>', str_replace('&amp;', '&', forum_link($forum_url['help'], 'om_warnings')), $mail_message);
			$mail_message = str_replace('<message>', $message, $mail_message);
			$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $forum_config['o_board_title']), $mail_message);

			if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
				require FORUM_ROOT.'include/email.php';

			($hook = get_hook('om_warnings_modify_message')) ? eval($hook) : null;

			// Send e-mail
			forum_mail($user_email, $mail_subject, $mail_message);
		}

		// Refresh user warnings cache
		om_warnings_refresh_user($topic_info['poster_id']);

		$forum_flash->add_info($lang_om_warnings['Warning reported']);

		($hook = get_hook('om_warnings_report_pre_redirect')) ? eval($hook) : null;

		redirect($forum_page['redirect_page'], $lang_om_warnings['Warning reported']);
	}
}

// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

$forum_page['hidden_fields'] = array(
	'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

$om_warnings_types = om_warnings_get_warning_types();
if (!$om_warnings_types)
	message($lang_om_warnings['Must define warning types']);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	$lang_om_warnings['Report warning']
);

// Setup main heading
$forum_page['main_head'] = end($forum_page['crumbs']);

($hook = get_hook('om_warning_report_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'om_warning_report');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('om_warning_report_output_start')) ? eval($hook) : null;

?>
	<div class="main-head">
		<h2 class="hn"><span><?php echo $forum_page['main_head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div id="req-msg" class="req-warn ct-box error-box">
			<p class="important"><?php echo $lang_common['Required warn'] ?></p>
		</div>
<?php
		// If there were any errors, show them
		if (!empty($errors)) {
			$forum_page['errors'] = array();
			foreach ($errors as $cur_error) {
				$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';
			}

			($hook = get_hook('om_warnings_report_pre_errors')) ? eval($hook) : null;
?>
		<div class="ct-box error-box">
			<h2 class="warn hn"><?php echo $lang_om_warnings['Report errors'] ?></h2>
			<ul class="error-list">
				<?php echo implode("\n\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php
		}
?>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('om_warnings_report_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_om_warnings['Reporting warning'] ?></strong></legend>
<?php ($hook = get_hook('om_warnings_report_pre_warning_type')) ? eval($hook) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Warning type'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="warn_id">
<?php
	foreach ($om_warnings_types as $cur_type)
	{
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_type['id'].'">'.forum_htmlencode($cur_type['warn_name']).' '.sprintf($lang_om_warnings['Num of points'], $cur_type['points']).'</option>'."\n";
	}
?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_report_pre_message')) ? eval($hook) : null; ?>
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_om_warnings['Message'] ?></span> <small><?php echo $lang_om_warnings['Message help'] ?></small></label><br />
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="message" rows="5" cols="60" required></textarea></span></div>
					</div>
				</div>
<?php ($hook = get_hook('om_warnings_report_pre_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('om_warnings_report_fieldset_end')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" formnovalidate /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('om_warnings_report_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
 
