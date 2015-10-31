<?php
/**
 * Default SEF URL scheme.
 *
 * @copyright (C) 2013 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package om_multiquote
 */

$forum_rewrite_rules['/^new[\/_-]?reply[\/_-]?([0-9]+)[\/_-]?quote[\/_-]?([0-9,]+)(\.html?|\/)?$/i'] = 'post.php?tid=$1&qid=$2';

?>