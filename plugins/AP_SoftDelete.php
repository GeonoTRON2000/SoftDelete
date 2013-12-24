<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Load the admin_plugin_softdelete.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_softdelete.php';

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

//
// The rest is up to you!
//

if (isset($_POST['save']))
{

	if (intval($_POST['forum']) > 0)
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.intval($_POST['forum']).'\' WHERE conf_name = \'o_softdelete_forum\'') or error('Unable to update config', __FILE__, __LINE__, $db->error());
	else
		message($lang_admin_plugin_softdelete['Invalid forum id']);

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span><?php echo $lang_admin_plugin_softdelete['Plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?=$lang_admin_plugin_softdelete['Saved']; ?></p>
				<p><a href="javascript: history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</div>
		</div>
	</div>
<?php

}
else
{

	$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.post_topics IS NULL OR fp.post_topics=1) AND f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result) < 1)
		message($lang_admin_plugin_softdelete['No forums']);

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_softdelete['Plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_softdelete['Explanation 1'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_plugin_softdelete['Form title'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_softdelete['Legend text'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<thead>
									<th><?=$lang_admin_plugin_softdelete['ForumField']; ?></th>
								</thead>
								<tbody>
									<tr>
										<td>
											<select name="forum">
												<option value="0"><?=$lang_admin_plugin_softdelete['Disable']; ?></option>
<?php
	$cur_category = 0;
	while ($cur_forum = $db->fetch_assoc($result))
	{
		if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
			$cur_category = $cur_forum['cid'];
		}

		if ($cur_forum['fid'] != $fid)
			echo "\t\t\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"'.((intval($pun_config['o_softdelete_forum']) == intval($cur_forum['fid'])) ? ' selected="selected"' : '').'>'.pun_htmlspecialchars($cur_forum['forum_name']).'</option>'."\n";
	}

?>
												</optgroup>
											</select>
										</td>
									</tr>
									<tr>
										<td><input type="submit" name="save" value="<?=$lang_admin_plugin_softdelete['Save']; ?>" /></td>
									</tr>
								</tbody>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

}
