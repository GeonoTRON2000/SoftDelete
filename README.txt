##
##
##        Mod title:  SoftDelete
##
##      Mod version:  1.0
##  Works on FluxBB:  1.5
##     Release date:  2013-12-24
##           Author:  GeonoTRON2000 (geono@thegt.org)
##
##      Description:  This modification makes the delete button move deleted posts to a selected forum.
##
##   Repository URL:  http://fluxbb.org/resources/mods/softdelete
##
##   Affected files:  include/functions.php
##
##       Affects DB:  Yes
##
##            Notes:  This is just a template. Don't try to install it! Rows
##                    in this header should be no longer than 78 characters
##                    wide. Edit this file and save it as readme.txt. Include
##                    the file in the archive of your mod. The mod disclaimer
##                    below this paragraph must be left intact. This space
##                    would otherwise be a good space to brag about your mad
##                    modding skills :)
##
##       DISCLAIMER:  Please note that "mods" are not officially supported by
##                    FluxBB. Installation of this modification is done at 
##                    your own risk. Backup your forum database and any and
##                    all applicable files before proceeding.
##
##


#
#---------[ 1. UPLOAD ]-------------------------------------------------------
#

install_mod.php to /

plugins/AP_SoftDelete.php to /plugins/AP_SoftDelete.php

lang/English/admin_plugin_softdelete.php to /lang/English/admin_plugin_softdelete.php


#
#---------[ 2. RUN ]----------------------------------------------------------
#

install_mod.php


#
#---------[ 3. DELETE ]-------------------------------------------------------
#

install_mod.php


#
#---------[ 4. OPEN ]---------------------------------------------------------
#

include/functions.php


#
#---------[ 5. FIND (line: 712) ]---------------------------------------------
#

//
// Delete a topic and all of its posts
//
function delete_topic($topic_id)
{
	global $db;

	// Delete the topic and any redirect topics
	$db->query('DELETE FROM '.$db->prefix.'topics WHERE id='.$topic_id.' OR moved_to='.$topic_id) or error('Unable to delete topic', __FILE__, __LINE__, $db->error());

	// Create a list of the post IDs in this topic
	$post_ids = '';
	$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());
	while ($row = $db->fetch_row($result))
		$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

	// Make sure we have a list of post IDs
	if ($post_ids != '')
	{
		strip_search_index($post_ids);

		// Delete posts in topic
		$db->query('DELETE FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to delete posts', __FILE__, __LINE__, $db->error());
	}

	// Delete any subscriptions for this topic
	$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE topic_id='.$topic_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());
}


//
// Delete a single post
//
function delete_post($post_id, $topic_id)
{
	global $db;

	$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' ORDER BY id DESC LIMIT 2') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	list($last_id, ,) = $db->fetch_row($result);
	list($second_last_id, $second_poster, $second_posted) = $db->fetch_row($result);

	// Delete the post
	$db->query('DELETE FROM '.$db->prefix.'posts WHERE id='.$post_id) or error('Unable to delete post', __FILE__, __LINE__, $db->error());

	strip_search_index($post_id);

	// Count number of replies in the topic
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch post count for topic', __FILE__, __LINE__, $db->error());
	$num_replies = $db->result($result, 0) - 1;

	// If the message we deleted is the most recent in the topic (at the end of the topic)
	if ($last_id == $post_id)
	{
		// If there is a $second_last_id there is more than 1 reply to the topic
		if (!empty($second_last_id))
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$second_posted.', last_post_id='.$second_last_id.', last_poster=\''.$db->escape($second_poster).'\', num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
		else
			// We deleted the only reply, so now last_post/last_post_id/last_poster is posted/id/poster from the topic itself
			$db->query('UPDATE '.$db->prefix.'topics SET last_post=posted, last_post_id=id, last_poster=poster, num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
	}
	else
		// Otherwise we just decrement the reply counter
		$db->query('UPDATE '.$db->prefix.'topics SET num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
}


#
#---------[ 6. REPLACE WITH ]-------------------------------------------------
#

//
// Recycle a topic
//
function delete_topic($topic_id)
{
	global $db, $pun_user, $pun_config;

	// Verify that the move to forum ID is valid
	$result = $db->query('SELECT 1 FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.group_id='.$pun_user['g_id'].' AND fp.forum_id='.$pun_config['o_softdelete_forum'].') WHERE f.redirect_url IS NULL AND (fp.post_topics IS NULL OR fp.post_topics=1)') or error('Unable to fetch forum permissions', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	$result = $db->query('SELECT forum_id FROM '.$db->prefix.'topics WHERE id='.$topic_id) or error('Unable to fetch topic information');
	$topic = $db->fetch_assoc($result);

	if (intval($pun_config['o_softdelete_forum']) > 0 && $topic['forum_id'] != $pun_config['o_softdelete_forum']) {
		if ($topic['forum_id'] != $pun_config['o_softdelete_forum']) {
			// Delete any redirect topics
			$db->query('DELETE FROM '.$db->prefix.'topics WHERE moved_to='.$topic_id) or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());
		
			$db->query('UPDATE '.$db->prefix.'topics SET forum_id='.$pun_config['o_softdelete_forum'].' WHERE id='.$topic_id) or error('Unable to move topic', __FILE__, __LINE__, $db->error());

			update_forum($topic['forum_id']);
			update_forum($pun_config['o_softdelete_forum']);
		} else if ($pun_user['g_id'] == PUN_ADMIN) {
			hard_delete_topic($topic_id);
		}
	} else {
		hard_delete_topic($topic_id);
	}
}

//
// Delete a topic and all of its posts
//
function hard_delete_topic($topic_id)
{
	global $db;

	// Delete the topic and any redirect topics
	$db->query('DELETE FROM '.$db->prefix.'topics WHERE id='.$topic_id.' OR moved_to='.$topic_id) or error('Unable to delete topic', __FILE__, __LINE__, $db->error());

	// Create a list of the post IDs in this topic
	$post_ids = '';
	$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());
	while ($row = $db->fetch_row($result))
		$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

	// Make sure we have a list of post IDs
	if ($post_ids != '')
	{
		strip_search_index($post_ids);

		// Delete posts in topic
		$db->query('DELETE FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to delete posts', __FILE__, __LINE__, $db->error());
	}

	// Delete any subscriptions for this topic
	$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE topic_id='.$topic_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());
	global $pun_user;
	require PUN_ROOT.'include/poll.php';
	poll_delete($topic_id);
}

//
// Split a post and recycle
//
function delete_post($post_id, $topic_id)
{
	global $db, $pun_user, $pun_config;

	// Verify that the move to forum ID is valid
	$result = $db->query('SELECT 1 FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.group_id='.$pun_user['g_id'].' AND fp.forum_id='.$pun_config['o_softdelete_forum'].') WHERE f.redirect_url IS NULL AND (fp.post_topics IS NULL OR fp.post_topics=1)') or error('Unable to fetch forum permissions', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	$result = $db->query('SELECT p.id, p.poster, p.posted, t.forum_id AS forum_id FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON p.topic_id = t.id WHERE p.id='.$post_id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$post = $db->fetch_assoc($result);

	if (intval($pun_config['o_softdelete_forum']) > 0) {
		if ($post['forum_id'] != $pun_config['o_softdelete_forum']) {
			// Create the new topic
			$db->query('INSERT INTO '.$db->prefix.'topics (poster, subject, posted, first_post_id, forum_id) VALUES (\''.$db->escape($post['poster']).'\', \'p'.intval($post['id']).'\', '.$post['posted'].', '.$post['id'].', '.$pun_config['o_softdelete_forum'].')') or error('Unable to create new topic', __FILE__, __LINE__, $db->error());
			$new_tid = $db->insert_id();

			// Move the posts to the new topic
			$db->query('UPDATE '.$db->prefix.'posts SET topic_id='.$new_tid.' WHERE id='.$post['id']) or error('Unable to move post into new topic', __FILE__, __LINE__, $db->error());

			// Get last_post, last_post_id, and last_poster from the topic and update it
			$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' ORDER BY id DESC LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$last_post_data = $db->fetch_assoc($result);
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$last_post_data['posted'].', last_post_id='.$last_post_data['id'].', last_poster=\''.$db->escape($last_post_data['poster']).'\', num_replies=num_replies-1 WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			// Get last_post, last_post_id, and last_poster from the new topic and update it
			$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$new_tid.' ORDER BY id DESC LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$last_post_data = $db->fetch_assoc($result);
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$last_post_data['posted'].', last_post_id='.$last_post_data['id'].', last_poster=\''.$db->escape($last_post_data['poster']).'\', num_replies=0 WHERE id='.$new_tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_forum($post['forum_id']);
			update_forum($pun_config['o_softdelete_forum']);
		} else if ($pun_user['g_id'] == PUN_ADMIN) {
			hard_delete_post($post_id, $topic_id);
		}
	} else {
		hard_delete_post($post_id, $topic_id);
	}
}

//
// Delete a single post
//
function hard_delete_post($post_id, $topic_id)
{
	global $db;

	$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' ORDER BY id DESC LIMIT 2') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	list($last_id, ,) = $db->fetch_row($result);
	list($second_last_id, $second_poster, $second_posted) = $db->fetch_row($result);

	// Delete the post
	$db->query('DELETE FROM '.$db->prefix.'posts WHERE id='.$post_id) or error('Unable to delete post', __FILE__, __LINE__, $db->error());

	strip_search_index($post_id);

	// Count number of replies in the topic
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch post count for topic', __FILE__, __LINE__, $db->error());
	$num_replies = $db->result($result, 0) - 1;

	// If the message we deleted is the most recent in the topic (at the end of the topic)
	if ($last_id == $post_id)
	{
		// If there is a $second_last_id there is more than 1 reply to the topic
		if (!empty($second_last_id))
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$second_posted.', last_post_id='.$second_last_id.', last_poster=\''.$db->escape($second_poster).'\', num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
		else
			// We deleted the only reply, so now last_post/last_post_id/last_poster is posted/id/poster from the topic itself
			$db->query('UPDATE '.$db->prefix.'topics SET last_post=posted, last_post_id=id, last_poster=poster, num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
	}
	else
		// Otherwise we just decrement the reply counter
		$db->query('UPDATE '.$db->prefix.'topics SET num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
}


#
#---------[ 12. SAVE/UPLOAD ]-------------------------------------------------
#
