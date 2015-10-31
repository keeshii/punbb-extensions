<?php

if (!defined('FORUM'))
	die();


($hook = get_hook('om_markitup_tags_start')) ? eval($hook) : null;

$om_markitup_tags[] = '{name:"'.$lang_om_markitup['bold'].'",     className:"bold",      key:"B", openWith:"[b]", closeWith:"[/b]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['italic'].'",   className:"italic",    key:"I", openWith:"[i]", closeWith:"[/i]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['underline'].'",className:"underline", key:"U", openWith:"[u]", closeWith:"[/u]"}';
($hook = get_hook('om_markitup_group_1_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{separator:"---------------"}';

$om_markitup_tags[] = '{name:"'.$lang_om_markitup['picture'].'",  className:"picture",   key:"P", replaceWith:"[img][![Url]!][/img]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['link'].'",     className:"link",      key:"L", openWith:"[url=[![Url]!]]", closeWith:"[/url]", placeHolder:"'.$lang_om_markitup['Put texe here'].'"}';

($hook = get_hook('om_markitup_group_2_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{separator:"---------------"}';

$om_markitup_colors = array();
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['yellow'].'", className:"yellow",    openWith:"[color=#CCCC00]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['orange'].'", className:"orange",    openWith:"[color=orange]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['red'].'",    className:"red",       openWith:"[color=red]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['blue'].'",   className:"blue",      openWith:"[color=blue]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['purple'].'", className:"purple",    openWith:"[color=purple]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['green'].'",  className:"green",     openWith:"[color=green]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['white'].'",  className:"white",     openWith:"[color=white]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['gray'].'",   className:"gray",      openWith:"[color=gray]", closeWith:"[/color]"}';
$om_markitup_colors[] = '{name:"'.$lang_om_markitup['black'].'",  className:"black",     openWith:"[color=black]", closeWith:"[/color]"}';
($hook = get_hook('om_markitup_colors_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['colors'].'",    className:"colors",    openWith:"[color=[![Color]!]]", closeWith:"[/color]", dropMenu:['.implode(',',$om_markitup_colors).']}';

$om_markitup_sizes = array();
$om_markitup_sizes[] = '{name:"'.$lang_om_markitup['small'].'",   className:"small",     openWith:"[size=normal]", closeWith:"[/size]"}';
$om_markitup_sizes[] = '{name:"'.$lang_om_markitup['big'].'",     className:"big",       openWith:"[size=big]", closeWith:"[/size]"}';
$om_markitup_sizes[] = '{name:"'.$lang_om_markitup['large'].'",   className:"large",     openWith:"[size=large]", closeWith:"[/size]"}';
($hook = get_hook('om_markitup_sizes_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['size'].'",     className:"size",      openWith:"[size=[![Text size]!]]", closeWith:"[/size]", dropMenu:['.implode(',',$om_markitup_sizes).']}';

($hook = get_hook('om_markitup_group_3_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{separator:"---------------"}';

$om_markitup_tags[] = '{name:"'.$lang_om_markitup['list'].'",     className:"list",      openWith:"[list]\\n", closeWith:"\\n[/list]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['list-decimal'].'",className:"list-decimal", openWith:"[list=1]\\n", closeWith:"\\n[/list]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['list-element'].'",className:"list-element", openWith:"[*]"}';


$om_markitup_smilies = array();
$om_markitup__prev_url = '';
foreach($smilies as $code => $url) {
	// skip duplicated smilies (only if they are next to each other)
	if ($om_markitup__prev_url != $url) {
		$om_markitup__prev_url = $url;
		$om_markitup_smilies[] = '{replaceWith:"'.$code.'", styleValue:"background-image:url('.$base_url.'/img/smilies/'.$url.');"}';
	}
}
unset($om_markitup__prev_url);

($hook = get_hook('om_markitup_smilies_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['smilies'].'",  className:"smilies",   dropMenu:['.implode(',',$om_markitup_smilies).']}';

($hook = get_hook('om_markitup_group_4_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{separator:"---------------"}';

$om_markitup_tags[] = '{name:"'.$lang_om_markitup['quote'].'",    className:"quote",     openWith:"[quote]", closeWith:"[/quote]"}';
$om_markitup_tags[] = '{name:"'.$lang_om_markitup['code'].'",     className:"code",      openWith:"[code]", closeWith:"[/list]"}';

($hook = get_hook('om_markitup_group_5_end')) ? eval($hook) : null;
$om_markitup_tags[] = '{separator:"---------------"}';

$om_markitup_tags[] = '{name:"'.$lang_om_markitup['clear'].'",    className:"clear",     replaceWith:function(h) { return h.selection.replace(/\[(.*?)\]/g, "") } }';

($hook = get_hook('om_markitup_tags_end')) ? eval($hook) : null;

