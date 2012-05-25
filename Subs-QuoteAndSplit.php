<?php
/**
 * Quote and Split (qas)
 *
 * @package qas
 * @author emanuele
 * @copyright 2012 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 *
 * Functions
 *
 */

function qas_prepareNewPostNotice ($topic_id = false, $msg_id = false)
{
	global $txt, $scripturl;

	$old_msgInfo = qas_getOriginalTopicInfo($topic_id, $msg_id);
	$return = '';

	if (!empty($old_msgInfo))
		$return = '<br /><br />' . str_replace(
			array(
				'{TOPIC_URL}',
				'{MSG_URL}',
				'{MSG_SUBJECT}',
				'{TOPIC_LINK}',
				'{MSG_LINK}',
			),
			array(
				$scripturl . '?topic=' . $topic_id,
				$scripturl . '?msg=' . $msg_id,
				$old_msgInfo['subject'],
				'[iurl=' . $scripturl . '?topic=' . $topic_id . '.0]' . $old_msgInfo['subject'] . '[/iurl]',
				'[iurl=' . $scripturl . '?topic=' . $topic_id . '.msg' . $msg_id . '#' . $msg_id . ']' . $old_msgInfo['subject'] . '[/iurl]',
			),
			$txt['qas_topicOriginated_from']);

	return $return;
}

function qas_updateOriginalPost ($new_topic_id, $msgOptions, $old_msg_id)
{
	global $smcFunc, $scripturl, $txt;

	if (empty($old_msg_id))
		return false;

	$message = str_replace(
		array(
			'{TOPIC_URL}',
			'{TOPIC_SUBJECT}',
			'{TOPIC_LINK}',
		),
		array(
			$scripturl . '?topic=' . $new_topic_id . '.0',
			$msgOptions['subject'],
			'[iurl=' . $scripturl . '?topic=' . $new_topic_id . '.0]' . $msgOptions['subject'] . '[/iurl]',
		),
		$txt['qas_topicGenerates']
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET body = CONCAT(body, {string:new_topic_msg})
		WHERE id_msg = {int:message_id}
		AND {query_see_board}
		LIMIT 1',
		array(
			'new_topic_msg' => '<br /><br />' . $message,
			'message_id' => $old_msg_id,
	));
}

function qas_setBoardContext()
{
	global $board, $context, $txt, $sourcedir;
	$qas = isset($_GET['qas']) ? (int) $_GET['qas'] : 0;
	$id_msg = isset($_GET['quote']) ? (int) $_GET['quote'] : 0;

	$topic_info = qas_getOriginalTopicInfo($qas, $id_msg);

	if (empty($topic_info))
		fatal_lang_error('no_board', false);

	$board = $topic_info['id_board'];

	$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars($txt['board'])),
		'board_name' => htmlspecialchars(strtr(strip_tags($topic_info['name']), array('&amp;' => '&'))),
		'child_level' => $topic_info['child_level'],
	);
	require_once($sourcedir . '/Subs-Post.php');
	$_REQUEST['message'] = un_preparsecode($topic_info['body']);
	$_REQUEST['subject'] = $topic_info['subject'];
	$context['qas_original_topic'] = $qas;
	$context['qas_original_post'] = $id_msg;

	$context['insert_after_template'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
			var sCurBoardName = "' . $context['jump_to']['board_name'] . '".removeEntities();
			var iCurBoardId = ' . $board . ';
			var sCatSeparator = "-----------------------------";
			var sBoardChildLevelIndicator = "==";
			var sCatPrefix = "";
			var sBoardPrefix = "=>";
			setInnerHTML(document.getElementById("post_jump_to"), \'<select name="\' + "post_jump_to" + \'_select" id="\' + "post_jump_to" + \'_select" \' + (\'implementation\' in document ? \'\' : \'onmouseover="getBoards();" \') + (\'onbeforeactivate\' in document ? \'onbeforeactivate\' : \'onfocus\') + \'="getBoards();"><option value="\' + iCurBoardId + \'">\' + \'' . str_repeat('==', $context['jump_to']['child_level']) . '=> \' + sCurBoardName.removeEntities() + \'</option></select>\')

			function getBoards ()
			{
				var oXMLDoc = getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=xmlhttp;sa=jumpto;xml\');
				var aBoardsAndCategories = new Array();

				ajax_indicator(true);

				if (oXMLDoc.responseXML)
				{
					var items = oXMLDoc.responseXML.getElementsByTagName(\'smf\')[0].getElementsByTagName(\'item\');
					for (var i = 0, n = items.length; i < n; i++)
					{
						aBoardsAndCategories[aBoardsAndCategories.length] = {
							id: parseInt(items[i].getAttribute(\'id\')),
							isCategory: items[i].getAttribute(\'type\') == \'category\',
							name: items[i].firstChild.nodeValue.removeEntities(),
							is_current: false,
							childLevel: parseInt(items[i].getAttribute(\'childlevel\'))
						}
					}
				}

				ajax_indicator(false);
				fillSelect(aBoardsAndCategories);
			}

			function fillSelect (aBoardsAndCategories)
			{
				var bIE5x = !(\'implementation\' in document);
				var iIndexPointer = 0;
				var dropdownList = document.getElementById(\'post_jump_to_select\');

				// Create an option that\'ll be above and below the category.
				var oDashOption = document.createElement(\'option\');
				oDashOption.appendChild(document.createTextNode(sCatSeparator));
				oDashOption.disabled = \'disabled\';
				oDashOption.value = \'\';

				// Reset the events and clear the list (IE5.x only).
				if (bIE5x)
				{
					dropdownList.onmouseover = null;
					dropdownList.remove(0);
				}
				if (\'onbeforeactivate\' in document)
					dropdownList.onbeforeactivate = null;
				else
					dropdownList.onfocus = null;

				// Create a document fragment that\'ll allowing inserting big parts at once.
				var oListFragment = bIE5x ? dropdownList : document.createDocumentFragment();

				// Loop through all items to be added.
				for (var i = 0, n = aBoardsAndCategories.length; i < n; i++)
				{
					var j, sChildLevelPrefix, oOption;

					// If we\'ve reached the currently selected board add all items so far.
					if (!aBoardsAndCategories[i].isCategory && aBoardsAndCategories[i].id == iCurBoardId)
					{
						if (bIE5x)
							iIndexPointer = dropdownList.options.length;
						else
						{
							dropdownList.insertBefore(oListFragment, dropdownList.options[0]);
							oListFragment = document.createDocumentFragment();
							continue;
						}
					}

					if (aBoardsAndCategories[i].isCategory)
						oListFragment.appendChild(oDashOption.cloneNode(true));
					else
						for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = \'\'; j > 0; j--)
							sChildLevelPrefix += sBoardChildLevelIndicator;

					oOption = document.createElement(\'option\');
					oOption.appendChild(document.createTextNode((aBoardsAndCategories[i].isCategory ? sCatPrefix : sChildLevelPrefix + sBoardPrefix) + aBoardsAndCategories[i].name));
					if (aBoardsAndCategories[i].isCategory)
						oOption.disabled = \'disabled\';
					oOption.value = aBoardsAndCategories[i].isCategory ? \'\' : aBoardsAndCategories[i].id;
					oListFragment.appendChild(oOption);

					if (aBoardsAndCategories[i].isCategory)
						oListFragment.appendChild(oDashOption.cloneNode(true));
				}

				// Add the remaining items after the currently selected item.
				dropdownList.appendChild(oListFragment);

				if (bIE5x)
					dropdownList.options[iIndexPointer].selected = true;

				// Internet Explorer needs this to keep the box dropped down.
				dropdownList.style.width = \'auto\';
				dropdownList.focus();
			}
		// ]]></script>';

}

function qas_getOriginalTopicInfo ($topic_id = null, $id_msg = null)
{
	global $smcFunc, $context;
	static $new_msg;

	if(empty($topic_id) || empty($id_msg))
		return false;

	if (isset($new_msg[$id_msg]))
		return $new_msg[$id_msg];

	// Retrieve the original information
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, t.id_board, b.name, b.child_level, m.subject, m.body, t.locked, t.id_first_msg
		FROM {db_prefix}messages as m
		LEFT JOIN {db_prefix}topics as t ON (t.id_topic = m.id_topic)
		LEFT JOIN {db_prefix}boards as b ON (m.id_board = b.id_board)
		WHERE m.id_msg = {int:id_msg}
		AND {query_see_board}',
		array(
			'id_msg' => $id_msg,
	));
	$new_msg = array();
	while ($msg = $smcFunc['db_fetch_assoc']($request))
		$new_msg[$id_msg] = $msg;
	$smcFunc['db_free_result']($request);

	return $new_msg[$id_msg];
}

?>