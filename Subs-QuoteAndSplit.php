<?php
/**
 * Quote and Split (qas)
 *
 * @package qas
 * @author emanuele
 * @copyright 2012 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function qas_updateOriginalPost ($new_topic_id, $old_msg_id)
{
	global $smcFunc, $scripturl, $txt;

	if (empty($old_msg_id))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages as m
		LEFT JOIN {db_prefix}boards as b ON (m.id_board = b.id_board)
		WHERE id_msg = {int:message_id}
			AND {query_see_board}
		LIMIT 1',
		array(
			'message_id' => $old_msg_id,
	));

	// Not worth unset
	if ($smcFunc['db_num_rows']($request) == 0)
		return false;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET split_into = CONCAT(split_into, \',\', {string:new_topic_id})
		WHERE id_msg = {int:message_id}
		LIMIT 1',
		array(
			'new_topic_id' => $new_topic_id,
			'message_id' => $old_msg_id,
	));
}

function qas_setBoardContext()
{
	global $board, $context, $txt, $sourcedir, $topic;
	$qas = isset($_GET['qas']) ? (int) $_GET['qas'] : 0;
	$id_msg = isset($_GET['quote']) ? (int) $_GET['quote'] : 0;

	$topic_info = qas_getOriginalTopicInfo($id_msg, $qas);

	if (empty($topic_info))
		fatal_lang_error('no_board', false);

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
			var iCurBoardId = ' . $topic_info['id_board'] . ';
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

	$board = 0;
}

function qas_getOriginalTopicInfo ($id_msg = null, $id_topic = null)
{
	global $smcFunc, $context;
	static $new_msg;

	if(empty($id_msg) || empty($id_topic))
		return false;
// $context['topic_first_message']
	if (isset($new_msg[$id_msg]))
		return $new_msg[$id_msg];

	// Retrieve the original information
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, t.id_board, b.name, b.child_level, m.poster_time, m.subject, m.body, t.locked, t.id_first_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}messages as m
		LEFT JOIN {db_prefix}topics as t ON (t.id_topic = m.id_topic)
		LEFT JOIN {db_prefix}boards as b ON (m.id_board = b.id_board)
		LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND {query_see_board}',
		array(
			'id_msg' => $id_msg,
	));
	$new_msg = array();
	while ($msg = $smcFunc['db_fetch_assoc']($request))
	{
		$msg['body'] = '[quote author=' . $msg['poster_name'] . ' link=topic=' . $id_topic . '.msg' . $id_msg . '#msg' . $id_msg . ' date=' . $msg['poster_time'] . ']' . "\n" . rtrim($msg['body']) . "\n" . '[/quote]';
		$new_msg[$id_msg] = $msg;
	}
	$smcFunc['db_free_result']($request);

	return $new_msg[$id_msg];
}

function qas_getGeneratedTopics ($topics = array(), &$output)
{
	global $smcFunc, $context, $scripturl, $txt, $user_info, $modSettings;
	static $loaded_topic;


	if ($output['id'] == $context['topic_first_message'] && !empty($context['topic_originated_from']))
	{
		if (!isset($output['member']['custom_fields']))
			$output['member']['custom_fields'] = array();

			$output['member']['custom_fields'][] = array(
				'placement' => 2,
				'value' => '<a href="' . $scripturl . '?msg=' . $context['topic_originated_from'] . '">' . $txt['qas_topicOriginated_from'] . '</a>',
			);
	}

	if(empty($topics))
		return false;

	if (!is_array($topics))
		$topics = explode(',', $topics);

	$query_topic = array();
	$return = array();
	foreach ($topics as $topic)
	{
		$topic = (int) trim($topic);
		if (!empty($topic) && isset($loaded_topic[$topic]))
		{
			if (empty($modSettings['postmod_active']) || (!empty($modSettings['postmod_active']) && !$loaded_topic[$topic]['approved'] && allowedTo('approve_posts')))
				$return[] = $loaded_topic[$topic];
		}
		elseif (!empty($topic))
			$query_topic[] = $topic;
	}

	if (!empty($query_topic))
	{
		// Retrieve the original information
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, m.subject, t.approved
			FROM {db_prefix}topics as t
			LEFT JOIN {db_prefix}messages as m ON (t.id_first_msg = m.id_msg)
			LEFT JOIN {db_prefix}boards as b ON (t.id_board = b.id_board)
			WHERE t.id_topic IN ({array_int:id_topic})
			AND {query_see_board}',
			array(
				'id_topic' => $query_topic,
		));
		$new_msg = array();
		while ($msg = $smcFunc['db_fetch_assoc']($request))
		{
			$msg['url'] = '<a href="' . $scripturl . '?topic=' . $msg['id_topic'] . '.0">' . $msg['subject'] . '</a>';
			$loaded_topic[$msg['id_topic']] = $msg;

			if (empty($modSettings['postmod_active']) || (!empty($modSettings['postmod_active']) && ($msg['approved'] || (!$msg['approved'] && allowedTo('approve_posts')))))
				$return[] = $msg;
		}
		$smcFunc['db_free_result']($request);
	}

	$ret = '';
	if (!empty($return))
	{
		$ret = $txt['qas_topicGenerates'] . '
			<ul class="generated_discussions">';
	foreach ($return as $topic_data)
		$ret .= '
				<li>' . $topic_data['url'] . '</li>';
		$ret .= '
			</ul>';

		if (!isset($output['member']['custom_fields']))
			$output['member']['custom_fields'] = array();

			$output['member']['custom_fields'][] = array(
				'placement' => 2,
				'value' => $ret,
			);
	}
}

?>