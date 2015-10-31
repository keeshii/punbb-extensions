<?php
/**
 * Default SEF URL scheme.
 *
 * @copyright (C) 2014 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_warnings
 */

$forum_url['om_warnings_list'] = 'misc.php?section=om_warnings_list';
$forum_url['om_warnings_browse'] = 'misc.php?section=om_warnings_list&amp;username=$3&amp;show_expired=$2&amp;sort_dir=$1';
$forum_url['om_warnings_levels'] = 'misc.php?section=om_warnings_levels';
$forum_url['om_warnings_levels_add'] = 'misc.php?section=om_warnings_levels&amp;action=add_level';
$forum_url['om_warnings_levels_del'] = 'misc.php?section=om_warnings_levels&amp;del_level=$1';
$forum_url['om_warnings_types'] = 'misc.php?section=om_warnings_types';
$forum_url['om_warnings_types_add'] = 'misc.php?section=om_warnings_types&amp;action=add_warning';
$forum_url['om_warnings_types_del'] = 'misc.php?section=om_warnings_types&amp;del_warning=$1';
$forum_url['om_warnings_types_edit'] = 'misc.php?section=om_warnings_types&amp;edit_warning=$1';
$forum_url['om_warnings_report'] = 'misc.php?section=om_warnings_report&amp;post_id=$1';
$forum_url['om_warnings_report_user'] = 'misc.php?section=om_warnings_report&amp;user_id=$1';
$forum_url['om_warnings_profile'] = 'profile.php?section=om_warnings&amp;id=$1';
