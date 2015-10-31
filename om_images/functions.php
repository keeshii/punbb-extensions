<?php

/**
 * om_images functions: logic, database and output
 *
 * @copyright (C) 2013 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_images
 */

if (!defined('FORUM'))
	die();

// FUNCTIONS

define ('OM_IMAGES_EXT_ROOT', $ext_info['path']);
define ('OM_IMAGES_EXT_URL', preg_replace('/^https/', 'http', $ext_info['url']));
define ('OM_IMAGES_BLOCK_SIZE', 1024*8); // 8 kB

//
// Generate directory name of unique name
//
function om_images_generate_dir()
{
	do {
		$dir_name = uniqid();
	} while(file_exists(OM_IMAGES_EXT_ROOT . '/img/' . $dir_name));

	return $dir_name;
}

//
// Check if value exists in array in key 'name'.
// I know it could be implemented more efficient,
// but i think it is ok this way.
//
function om_images_img_in_array(&$images_array, $name)
{
	foreach($images_array as $image) {
		if ($image['name'] == $name)
			return true;
	}

	return false;
}

//
// Check if image need to be downloaded and add it to list.
// return the bbcode tag with new url
//
function om_images_add_link(&$images_array, $tag, $dir_name, $url, $link = '')
{
	$ext = strrchr($url, '.');
	$allowed_extensions = array('.gif', '.jpeg', '.jpg', '.png');
	$new_url = $url;

	($hook = get_hook('om_images_fn_add_link_allowed_extensions')) ? eval($hook) : null;

	// if file has an extension of image and it wasn't processed before
	if (!isset($images_array[$url]) && in_array($ext, $allowed_extensions)) {

		// don't process the image, if url suggest it was already uploaded
		$img_path = OM_IMAGES_EXT_URL.'/img/'.$dir_name;
		$old_name = substr(strrchr($url, '/'), 1);
		$skip = (substr($url, 0, strlen($img_path)) == $img_path);

		($hook = get_hook('om_images_fn_add_link_pre_ommit')) ? eval($hook) : null;

		if (!$skip) {
			$img_id = 0;

			// finding first free id
			do {
				$img_id++;
				$new_name = ($img_id == 1 ? '' : $img_id . '_')
					. str_replace(array('/','\\',':','?','"','<','>','|','=','&'),'_',$old_name);
			} while (file_exists(OM_IMAGES_EXT_ROOT.'/img/'.$dir_name.'/'.$new_name) ||
			         om_images_img_in_array($images_array, $new_name));

			// build new urls for our image
			$rel_path = '/img/' . $dir_name . '/' . $new_name;
			$new_url = OM_IMAGES_EXT_URL . $rel_path;
			$new_path = OM_IMAGES_EXT_ROOT . $rel_path;

			// add a new image to array
			$images_array[$url] = array(
				'url' => $new_url,
				'name' => $new_name,
				'path' => $new_path,
			);
		} else {
			// add an ommitted image to array
			$images_array[$url] = array(
				'url' => $url,
				'name' => $old_name,
			);
		}
	}

	if (isset($images_array[$url])) {
		$new_url = $images_array[$url]['url'];
	}

	($hook = get_hook('om_images_fn_add_link_pre_return')) ? eval($hook) : null;

	// replace message with new links
	if (empty($link)) {
		return '['.$tag.']'.$new_url.'[/'.$tag.']';
	}

	if ($tag == 'img') {
		return '['.$tag.'='.$link.']'.$new_url.'[/'.$tag.']';
	}

	return '['.$tag.'='.$new_url.']'.$link.'[/'.$tag.']';
}

//
// Replace images in message with new links.
// Create the list of images that need to be downloaded
//
function om_images_do_replace(&$images_array, $message, $dir_name)
{
	global $forum_config, $om_images_array;

	($hook = get_hook('om_images_fn_do_replace_start')) ? eval($hook) : null;

	$om_images_array = array();
	$pattern_callback = array();
	$replace_callback = array();

	$pattern_callback[] = '#\[img\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#';
	$pattern_callback[] = '#\[img=([^\[]*?)\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#';

	$replace_callback[] = 'om_images_add_link($om_images_array, \'img\', \''.$dir_name.'\', $matches[1].$matches[3])';
	$replace_callback[] = 'om_images_add_link($om_images_array, \'img\', \''.$dir_name.'\', $matches[2].$matches[4], $matches[1])';

	if ($forum_config['o_om_images_url'] == '1') {
		$pattern_callback[] = '#\[url\]([^\[]*?)\[/url\]#';
		$pattern_callback[] = '#\[url=([^\[]+?)\](.*?)\[/url\]#';

		$replace_callback[] = 'om_images_add_link($om_images_array, \'url\', \''.$dir_name.'\', $matches[1])';
		$replace_callback[] = 'om_images_add_link($om_images_array, \'url\', \''.$dir_name.'\', $matches[1], $matches[2])';
	}

	($hook = get_hook('om_images_fn_do_replace_new_pattern')) ? eval($hook) : null;

	// This thing takes a while! :)
	$count = count($pattern_callback);
	for ($i = 0; $i < $count; $i++) {
		$message = preg_replace_callback($pattern_callback[$i], create_function('$matches', 'global $om_images_array; return '.$replace_callback[$i].';'), $message);
	}

	$images_array = $om_images_array;
	unset($om_images_array);
	($hook = get_hook('om_images_fn_do_replace_pre_return')) ? eval($hook) : null;

	return $message;
}


//
// Download the image and save it to file
//
function om_images_fetch_file($path, $url) {

	global $forum_config, $lang_om_images, $errors;

	($hook = get_hook('om_images_fn_fetch_file_start')) ? eval($hook) : null;

	// if url is not valid
	if (! ($fsrc = @fopen ($url, "rb"))) {
		$errors[] = sprintf($lang_om_images['Cant download'], $url);
		return false;
	}

	// if cannot save file
	if (!($fdst = @fopen ($path, "wb"))) {
		fclose($fsrc);
		$errors[] = $lang_om_images['No tmp directory'];
		return false;
	}

	$max_size =  intval($forum_config['o_om_images_size']);

	if ($fdst) {
		$size = 0;

		($hook = get_hook('om_images_fn_fetch_file_pre_download')) ? eval($hook) : null;

		// download image piece by piece.
		// we don't want to download a huge files at once, do we?
		while(!feof($fsrc)) {

			if (($read = @fread($fsrc, OM_IMAGES_BLOCK_SIZE)) === false) {
				$errors[] = sprintf($lang_om_images['Partial download'], $url);
				return false;
			}

			if (@fwrite($fdst, $read, OM_IMAGES_BLOCK_SIZE) === false) {
				fclose($fsrc);
				fclose($fdst);
				$errors[] = $lang_om_images['Cant save'];
				return false;
			}

			$size += strlen($read);
			if ($max_size > 0 && $size > $max_size) {
				fclose($fsrc);
				fclose($fdst);
				$errors[] = sprintf($lang_om_images['Too large ini'], ($max_size / 1024));
				return false;
			}
		}
	}

	@fclose($fsrc);
	@fclose($fdst);

	// Now check the width, height, type
	// we have to be sure, we downloaded an image
	list($img_width, $img_height, $type,) = @/**/getimagesize($path);

	($hook = get_hook('om_images_fn_fetch_file_pre_check_size')) ? eval($hook) : null;

	if (empty($img_width) || empty($img_height) ||
		($forum_config['o_om_images_width'] > 0 && $img_width > $forum_config['o_om_images_width']) ||
		($forum_config['o_om_images_height'] > 0 && $img_height > $forum_config['o_om_images_height']))
	{
		$errors[] = sprintf($lang_om_images['Bad width or height'], $forum_config['o_om_images_width'], $forum_config['o_om_images_height']);
		return false;
	}

	$img_width = intval($img_width);
	$img_height = intval($img_height);

	// Determine type
	$allowed_types = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG);

	// Check type from getimagesize type format
	if (!in_array($type, $allowed_types))
	{
		$errors[] = $lang_om_images['Bad type'];
		return false;
	}

	return true;
}

//
// Remove a directory with its files
//
function om_images_rmdir($dir)
{
	if (!file_exists($dir)) {
		return;
	}

	$files = array_diff(scandir($dir), array('.','..'));
	foreach ($files as $file) {
		(is_dir("$dir/$file")) ? om_images_rmdir("$dir/$file") : @unlink("$dir/$file");
	}
	@rmdir($dir);
}

//
// Download all missing images to disk, delete unused images.
//
function om_images_download(&$images_array, $dir_name)
{
	global $errors;

	$image_dir = OM_IMAGES_EXT_ROOT . '/img/' . $dir_name;

	($hook = get_hook('om_images_fn_download_start')) ? eval($hook) : null;

	// create directory if not exists
	if (!empty($images_array) && !file_exists($image_dir)) {
		@mkdir($image_dir);
	}

	$result = true;

	($hook = get_hook('om_images_fn_download_pre_fetch')) ? eval($hook) : null;

	// download images, one by one
	foreach ($images_array as $url => $image) {

		if ($url == $image['url']) {
			continue;
		}

		$result = om_images_fetch_file($image['path'], $url);
		if (!$result) {
			break;
		}
	}

	($hook = get_hook('om_images_fn_download_pre_fail_clean')) ? eval($hook) : null;

	// if there was an error while downloading,
	// delete all images that was downloaded
	if (!$result) {
		$keep_dir = false;
		foreach ($images_array as $url => $image) {
			if ($url == $image['url']) {
				$keep_dir = true;
				continue;
			}

			@unlink($image['path']);
		}

		// keep folder, if there are images that are in use
		if (!$keep_dir) {
			om_images_rmdir($image_dir);
		}
		return;
	}

	($hook = get_hook('om_images_fn_download_pre_success_clean')) ? eval($hook) : null;

	// delete unused images
	if (file_exists($image_dir)) {
		$files = array_diff(scandir($image_dir), array('.','..'));
		foreach ($files as $file) {
			if (!om_images_img_in_array($images_array, $file)) {
				@unlink($image_dir . '/' . $file);
			}
		}
	}
}

//
// delete all images of selected posts
//
function om_images_delete_posts($posts)
{
	global $forum_db;

	if (!is_array($posts)) {
		$posts = array($posts);
	}

	$query = array(
		'SELECT'	=> 'om_images_dir',
		'FROM'		=> 'posts',
		'WHERE'		=> 'id IN ('.implode(',', $posts).')',
	);

	($hook = get_hook('om_images_fn_delete_posts_qr_delete')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = get_hook('om_images_fn_delete_posts_pre_delete_dir')) ? eval($hook) : null;
	while ($row = $forum_db->fetch_row($result)) {
		if ($row[0] != null) {
			om_images_rmdir(OM_IMAGES_EXT_ROOT . '/img/' . $row[0]);
		}
	}
}


//
// delete all images of selected posts
//
function om_images_delete_topics($topics)
{
	global $forum_db;

	if (!is_array($topics)) {
		$topics = array($topics);
	}

	$query = array(
		'SELECT'	=> 'om_images_dir',
		'FROM'		=> 'posts',
		'WHERE'		=> 'topic_id IN ('.implode(',', $topics).')',
	);
	($hook = get_hook('om_images_fn_delete_topics_qr_delete')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = get_hook('om_images_fn_delete_topics_pre_delete_dir')) ? eval($hook) : null;
	while ($row = $forum_db->fetch_row($result)) {
		if ($row[0] != null) {
			om_images_rmdir(OM_IMAGES_EXT_ROOT . '/img/' . $row[0]);
		}
	}
}


define('OM_IMAGES_FUNCTIONS_LOADED', 1);
