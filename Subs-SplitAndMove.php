<?php
/**
 * Split and Move (sam)
 *
 * @package sam
 * @author emanuele
 * @copyright 2011 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 *
 * Hooks
 *
 */

function sam_add_modsettings (&$config_vars)
{
	$config_vars[] = array('check', 'post_split_notification');
	$config_vars[] = array('text', 'notify_bot_name');
	$config_vars[] = array('text', 'notify_bot_email');
	$config_vars[] = array('large_text', 'sam_default_notify_message');
	$config_vars[] = array('large_text', 'sam_default_new_message');
}


/**
 *
 * Functions
 *
 */

function sam_PostMovedToTopic ($old_topic_id, $new_topic_id)
{
	global $smcFunc, $modSettings, $context, $board_info, $board, $scripturl, $txt;

	$old_topic_id = (int) $old_topic_id;

	if (empty($old_topic_id) || empty($new_topic_id))
		return false;

	$topics = sam_getOriginalTopicInfo(array($old_topic_id, $new_topic_id));

	$message = str_replace(
		array(
			'{NEXT_TOPIC_URL}',
			'{NEXT_TOPIC_SUBJECT}',
			'{NEXT_TOPIC_LINK}',
		),
		array(
			$scripturl . '?topic=' . $new_topic_id . '.0',
			$topics[$new_topic_id]['subject'],
			'[iurl=' . $scripturl . '?topic=' . $new_topic_id . '.0]' . $topics[$new_topic_id]['subject'] . '[/iurl]',
		),
		(!empty($modSettings['sam_default_notify_message']) ? $modSettings['sam_default_notify_message'] : $txt['sam_default_text_notify_msg'])
	);

	// Collect all parameters for the creation the notify post.
	$msgOptions = array(
		'id' => 0,
		'subject' => $topics[$new_topic_id]['subject'],
		'body' => $message,
		'smileys_enabled' => true,
		'attachments' => array(),
		'approved' => true,
	);
	$topicOptions = array(
		'id' => $old_topic_id,
		'board' => $board,
		'lock_mode' => $topics[$old_topic_id]['locked'],
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
	);
	$posterOptions = array(
		'id' => 0,
		// Just want to be sure there is actually something to throw in the db. ;)
		'name' => (!empty($modSettings['notify_bot_name']) ? $modSettings['notify_bot_name'] : $txt['sam_default_notify_bot_name']),
		'email' => (!empty($modSettings['notify_bot_email']) ? $modSettings['notify_bot_email'] : 'notifybot@email.filler'),
		'update_post_count' => false,
	);

	createPost($msgOptions, $topicOptions, $posterOptions);

	$message = str_replace(
		array(
			'{PREV_TOPIC_URL}',
			'{PREV_TOPIC_SUBJECT}',
			'{PREV_TOPIC_LINK}',
		),
		array(
			$scripturl . '?topic=' . $old_topic_id . '.0',
			$topics[$old_topic_id]['subject'],
			'[iurl=' . $scripturl . '?topic=' . $old_topic_id . '.0]' . $topics[$old_topic_id]['subject'] . '[/iurl]',
		),
		(!empty($modSettings['sam_default_new_message']) ? $modSettings['sam_default_new_message'] : $txt['sam_default_text_new_msg'])
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET body = CONCAT({string:new_topic_msg}, body)
		WHERE id_msg = {int:message_id}
		LIMIT 1',
		array(
			'new_topic_msg' => $message . '<br /><br />',
			'message_id' => $topics[$new_topic_id]['id_first_msg'],
	));
}

function sam_getOriginalTopicInfo ($topics_id = null)
{
	global $smcFunc, $context;

	if(!is_array($topics_id))
		return false;

	// Retrieve the original information
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, m.subject, t.locked, t.id_first_msg
		FROM {db_prefix}messages as m
		LEFT JOIN {db_prefix}topics as t ON (t.id_first_msg = m.id_msg)
		WHERE t.id_topic IN ({array_int:id_topic})',
		array(
			'id_topic' => $topics_id,
	));
	$new_msg = array();
	while ($msg = $smcFunc['db_fetch_assoc']($request))
		$new_msg[$msg['id_topic']] = $msg;
	$smcFunc['db_free_result']($request);

	return $new_msg;
}

?>