<?php

if (!defined('FORUM'))
	die();

//
// Generate the medals cache PHP script
//
function om_move_posts_generate_cache($om_move_posts_max = null)
{
	global $forum_db;

	$return = ($hook = get_hook('om_move_posts_fn_generate_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	if ($om_move_posts_max == null) {
		// get max value om_move_posts of posts in system o.O
		$query = array(
			'SELECT'	=> 'MAX(om_move_posts)',
			'FROM'		=> 'posts',
		);

		($hook = get_hook('om_move_posts_fn_generate_cache_qr_get_max_move_posts')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$om_move_posts_max = $forum_db->result($result);
	}

	// Output config as PHP code
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	if (!write_cache_file(FORUM_CACHE_DIR.'cache_om_move_posts.php', '<?php'."\n\n".'define(\'OM_MOVE_POSTS_CACHE_LOADED\', 1);'."\n\n".'$om_move_posts_max = '.$om_move_posts_max.';'."\n\n".'?>'))
	{
		error('Unable to write configuration cache file to cache directory.<br />Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);
	}
}


//
// Load value from cache and return it
//
function om_move_posts_load_cache() {
	($hook = get_hook('om_move_posts_fn_load_cache_start')) ? eval($hook) : null;

	if (!defined('OM_MOVE_POSTS_CACHE_LOADED') && file_exists(FORUM_CACHE_DIR.'cache_om_move_posts.php'))
		include FORUM_CACHE_DIR.'cache_om_move_posts.php';

	// Regenerate cache only if it not exists
	if (!defined('OM_MOVE_POSTS_CACHE_LOADED')) {
		om_move_posts_generate_cache();
		require FORUM_CACHE_DIR.'cache_om_move_posts.php';
	}

	return $om_move_posts_max;
}

define ('OM_MOVE_POSTS_FUNCTIONS_LOADED', 1);
