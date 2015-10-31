<?php

/**
 * om_medals functions: cache, logic, database and output
 *
 * @copyright (C) 2013 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_medals
 */

if (!defined('FORUM'))
	die();

// FUNCTIONS

define ('OM_MEDALS_TYPE_NONE', 0);
define ('OM_MEDALS_TYPE_PNG', 1);
define ('OM_MEDALS_TYPE_GIF', 2);
define ('OM_MEDALS_TYPE_JPG', 3);
define ('OM_MEDALS_EXT_ROOT', $ext_info['path']);
define ('OM_MEDALS_EXT_URL', $ext_info['url']);

//
// Get image extension based on file type (integer)
//
function om_medals_get_image_extension($medal_type) {
	$extensions = array(
		OM_MEDALS_TYPE_PNG => '.png',
		OM_MEDALS_TYPE_GIF => '.gif',
		OM_MEDALS_TYPE_JPG => '.jpg');

	($hook = get_hook('om_medals_fn_get_image_extension_start')) ? eval($hook) : null;

	if (isset($extensions[$medal_type]))
		return $extensions[$medal_type];

	return null;
}

//
// Upload image to temporary folder and check it
// if any error occurs, put notes into $errors variable
//
function om_medals_upload_image_file($uploaded_file, &$tmp_file, &$img_type, &$img_width, &$img_height) {
	global $errors, $lang_om_medals, $forum_user, $forum_config;

	($hook = get_hook('om_medals_fn_upload_image_start')) ? eval($hook) : null;

	if ($uploaded_file == null) {
		$errors[] = $lang_om_medals['No file'];
		return;
	}

	if (isset($uploaded_file['error']) && empty($errors)) {
		switch ($uploaded_file['error'])
		{
			case 1:	// UPLOAD_ERR_INI_SIZE
			case 2:	// UPLOAD_ERR_FORM_SIZE
				$errors[] = $lang_om_medals['Too large ini'];
				return;

			case 3:	// UPLOAD_ERR_PARTIAL
				$errors[] = $lang_om_medals['Partial upload'];
				return;

			case 4:	// UPLOAD_ERR_NO_FILE
				$errors[] = $lang_om_medals['No file'];
				return;

			case 6:	// UPLOAD_ERR_NO_TMP_DIR
				$errors[] = $lang_om_medals['No tmp directory'];
				return;

			default:
				// No error occured, but was something actually uploaded?
				if ($uploaded_file['size'] == 0) {
					$errors[] = $lang_om_medals['No file'];
					return;
				}
		}
	}

	if (!is_uploaded_file($uploaded_file['tmp_name'])) {
		$errors[] = $lang_om_medals['Unknown failure'];
		return;
	}

	// First check simple by size and mime type
	$allowed_mime_types = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');
	$allowed_types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF);

	($hook = get_hook('om_medals_fn_upload_image_allowed_types')) ? eval($hook) : null;

	if (!in_array($uploaded_file['type'], $allowed_mime_types)) {
		$errors[] = $lang_om_medals['Bad type'];
		return;
	}

	$tmp_file = OM_MEDALS_EXT_ROOT.'/img/'.$forum_user['id'].'.tmp';

	// Move the file to the avatar directory. We do this before checking the width/height to circumvent open_basedir restrictions.
	if (!@move_uploaded_file($uploaded_file['tmp_name'], $tmp_file)) {
		$errors[] = $lang_om_medals['Move failed'];
		return;
	}

	// Now check the width, height, type
	list($img_width, $img_height, $type,) = @/**/getimagesize($tmp_file);
	if (empty($img_width) || empty($img_height) || $img_width > $forum_config['o_om_medals_width'] || $img_height > $forum_config['o_om_medals_height'])
	{
		@unlink($tmp_file);
		$errors[] = sprintf($lang_om_medals['Bad width or height'], $forum_config['o_om_medals_width'], $forum_config['o_om_medals_height']);
		return;
	}

	$img_width = intval($img_width);
	$img_height = intval($img_height);

	($hook = get_hook('om_medals_fn_upload_image_allowed_size')) ? eval($hook) : null;

	// Now check the width, height, type
	if ($type == IMAGETYPE_GIF && $uploaded_file['type'] != 'image/gif')	// Prevent dodgy uploads
	{
		@unlink($tmp_file);
		$errors[] = $lang_om_medals['Bad type'];
		return;
	}

	// Determine type
	$img_type = OM_MEDALS_TYPE_NONE;
	if ($type == IMAGETYPE_GIF)
		$img_type = OM_MEDALS_TYPE_GIF;
	else if ($type == IMAGETYPE_JPEG)
		$img_type = OM_MEDALS_TYPE_JPG;
	else if ($type == IMAGETYPE_PNG)
		$img_type = OM_MEDALS_TYPE_PNG;

	$extension = om_medals_get_image_extension($img_type);

	($hook = get_hook('om_medals_fn_upload_image_determine_extension')) ? eval($hook) : null;

	// Check type from getimagesize type format
	if (!in_array($type, $allowed_types) || empty($extension))
	{
		@unlink($om_medals_tmp_file);
		$errors[] = $lang_om_medals['Bad type'];
		return;
	}
}

//
// Move tmp file into extension's /img folder and fix permissions.
//
function om_medals_move_image($tmp_file, $medal_id, $medal_type) {

	($hook = get_hook('om_medals_fn_move_image_start')) ? eval($hook) : null;

	// delete any previous image
	om_medals_delete_image($medal_id);

	$extension = om_medals_get_image_extension($medal_type);

	// Put the new avatar in its place
	@rename($tmp_file, OM_MEDALS_EXT_ROOT.'/img/'.$medal_id.$extension);
	@chmod(OM_MEDALS_EXT_ROOT.'/img/'.$medal_id.$extension, 0644);
}


//
// Generate the medals cache PHP script
//
function om_medals_generate_medals_cache() {
	global $forum_db;

	($hook = get_hook('om_medals_fn_generate_medals_cache_start')) ? eval($hook) : null;

	$return = ($hook = get_hook('om_medals_fn_generate_medals_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	// Get the forum config from the DB
	$query = array(
		'SELECT'	=> 'm.id AS mid, m.medal_name, m.medal_desc, m.disp_position, m.medal_type, m.medal_width, m.medal_height',
		'FROM'		=> 'om_medals AS m',
		'ORDER BY'	=> 'm.disp_position',
	);

	($hook = get_hook('om_medals_fn_generate_medals_cache_qr_get_medals')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_medal_item = $forum_db->fetch_assoc($result)) {

		$extension = om_medals_get_image_extension($cur_medal_item['medal_type']);
		$path = ($extension != null ? '/img/'.$cur_medal_item['mid'].$extension : null);
		$medal_info = array(
			'id' => $cur_medal_item['mid'],
			'name' => $cur_medal_item['medal_name'],
			'desc' => $cur_medal_item['medal_desc'],
			'path' => $path,
			'width' => $cur_medal_item['medal_width'],
			'height' => $cur_medal_item['medal_height'],
			'position' => $cur_medal_item['disp_position']);

		($hook = get_hook('om_medals_fn_generate_medals_cache_pre_medal_info')) ? eval($hook) : null;
		$output[$cur_medal_item['mid']] = $medal_info;
	}

	// Output config as PHP code
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	if (!write_cache_file(FORUM_CACHE_DIR.'cache_om_medals.php', '<?php'."\n\n".'define(\'FORUM_OM_MEDALS_LOADED\', 1);'."\n\n".'$forum_om_medals = '.var_export($output, true).';'."\n\n".'?>'))
	{
		error('Unable to write configuration cache file to cache directory.<br />Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
	}
}

//
// Delete cache with medals data
//
function om_medals_clean_medals_cache() {
	($hook = get_hook('om_medals_fn_clean_medals_cache_start')) ? eval($hook) : null;

	$cache_file = FORUM_CACHE_DIR.'cache_om_medals.php';
	if (file_exists($cache_file))
	{
		unlink($cache_file);
	}
}

//
// Load medals data into variable $forum_om_medals
// reads from cache, if cache not exists, create it
//
function om_medals_load_medals_cache() {
	global $forum_om_medals;

	($hook = get_hook('om_medals_fn_load_medals_cache_start')) ? eval($hook) : null;

	if (!defined('FORUM_OM_MEDALS_LOADED') && file_exists(FORUM_CACHE_DIR.'cache_om_medals.php'))
		include FORUM_CACHE_DIR.'cache_om_medals.php';

	// Regenerate cache only if the cache is more than 30 minutes old
	if (!defined('FORUM_OM_MEDALS_LOADED'))
	{
		om_medals_generate_medals_cache();
		require FORUM_CACHE_DIR.'cache_om_medals.php';
	}
}

//
// Delete all posible image files of medal with given id
//
function om_medals_delete_image($id) {
	$filetypes = array('png', 'gif', 'jpg');
	($hook = get_hook('om_medals_fn_delete_image_start')) ? eval($hook) : null;

	if ($return != null)
		return;

	// Delete user avatar from FS
	foreach ($filetypes as $cur_type)
	{
		$image = OM_MEDALS_EXT_ROOT.'/img/'.$id.'.'.$cur_type;
		if (file_exists($image))
		{
			@unlink($image);
		}
	}
}

//
// Decide which medal should be first on the list
// this function is used as parameter for usort function (see below)
//
function om_medals_cmp($id1, $id2) {
	global $forum_om_medals;

	if (!isset($forum_om_medals[$id1]) || !isset($forum_om_medals[$id2]))
		return 0;

	return $forum_om_medals[$id1]['position'] - $forum_om_medals[$id2]['position'];
}

//
// Decode string like [1][2][3][4] and return it as array of integers
//
function om_medals_decode_medal_string($string) {
	global $forum_om_medals;

	// load medals cache, generate if not exists
	om_medals_load_medals_cache();

	($hook = get_hook('om_medals_fn_decode_medal_string_start')) ? eval($hook) : null;

	if (empty($string))
		return array();

	$medals = explode('][', substr($string, 1, -1));

	usort($medals, 'om_medals_cmp');
	return $medals;
}


//
// Generate html img element with medal image
//
function om_medals_generate_medal_tag($id) {
	global $forum_om_medals, $forum_url, $forum_config;

	// load medals cache, generate if not exists
	om_medals_load_medals_cache();

	($hook = get_hook('om_medals_fn_generate_medal_tag_start')) ? eval($hook) : null;

	// if medal has no image, then don't output it
	if (!isset($forum_om_medals[$id]))
		return null;

	$tag = '<img src="'.OM_MEDALS_EXT_URL.$forum_om_medals[$id]['path'].'" alt="'.forum_htmlencode($forum_om_medals[$id]['name']).'" '
		.'width="'.$forum_om_medals[$id]['width'].'" height="'.$forum_om_medals[$id]['height'].'"/>';

	// link page to help, if we are not currently in help
	if (!defined('FORUM_PAGE') || FORUM_PAGE != 'help')
		$tag = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'om_medals').'#m'.$id.'" title="'.forum_htmlencode($forum_om_medals[$id]['name']).'">'.$tag.'</a>';

	($hook = get_hook('om_medals_fn_generate_medal_tag_output')) ? eval($hook) : null;

	return $tag;
}


define('OM_MEDALS_FUNCTIONS_LOADED', 1);
