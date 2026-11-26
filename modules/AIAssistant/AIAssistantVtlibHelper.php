<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 *************************************************************************************/

require_once 'modules/AIAssistant/providers/OpenAIProvider.php';

/**
 * Small static helper called from VTWhatsAppTask::doTask() when the task's
 * "Personalize with AI" checkbox is set. Takes the template parameter values
 * already rendered (merge fields resolved) by VTSimpleTemplate, and asks
 * OpenAI to personalize their wording using the triggering record as context.
 *
 * Deliberately a thin, single-purpose helper (not a generic "AI module API")
 * since this is its only caller today.
 */
class AIAssistant_Vtlib_Helper {

	/**
	 * Function to personalize a list of WhatsApp template parameter values.
	 *
	 * @param <Array> $paramValues Already merge-field-rendered parameter strings
	 * @param <VTEntityData> $entity The workflow-triggering entity (VTSMSTask-style)
	 * @return <Array> Personalized parameter values, same count/order as input
	 * @throws Exception if the AI feature is disabled, misconfigured, or the call fails -
	 *                    callers (VTWhatsAppTask) are expected to catch and fall back to
	 *                    the original $paramValues on any exception.
	 */
	public static function draftMessage($paramValues, $entity) {
		require_once 'vtlib/Vtiger/Utils.php';
		require_once 'vtlib/Vtiger/Functions.php';

		$config = AIAssistant_OpenAIProvider_Model::getRawConfig();
		if (!$config || empty($config['enable_whatsapp_personalize'])) {
			throw new Exception('AI WhatsApp personalization is not enabled in Settings > AI Assistant.');
		}

		if (empty($paramValues)) {
			return $paramValues;
		}

		$recordLabel = '';
		$moduleName = '';
		if (is_object($entity)) {
			if (method_exists($entity, 'getModuleName')) {
				$moduleName = $entity->getModuleName();
			}
			if (method_exists($entity, 'get')) {
				foreach (array('label', 'name', 'firstname', 'lastname', 'subject', 'account_name') as $fieldName) {
					$value = $entity->get($fieldName);
					if (!empty($value)) {
						$recordLabel .= ($recordLabel ? ' ' : '') . $value;
					}
				}
			}
		}

		$delimiter = '|';
		$originalJoined = implode($delimiter, $paramValues);

		$systemPrompt = 'You personalize short WhatsApp message template parameters for a CRM. '
			. 'You will be given a list of parameter values separated by "' . $delimiter . '". '
			. 'Rewrite each value to sound natural and personalized for the customer, using the record '
			. 'context provided, but keep the SAME NUMBER of values in the SAME ORDER, separated by the '
			. 'same "' . $delimiter . '" character. Keep each value short (it fills a WhatsApp template '
			. 'placeholder). Reply with ONLY the pipe-separated values, nothing else - no explanation.';

		$userPrompt = 'Module: ' . ($moduleName ?: 'Unknown') . "\n"
			. 'Record: ' . ($recordLabel ?: 'Unknown') . "\n"
			. 'Parameter values: ' . $originalJoined;

		$provider = AIAssistant_OpenAIProvider_Model::getInstance();
		$reply = $provider->chat(array(
			array('role' => 'system', 'content' => $systemPrompt),
			array('role' => 'user', 'content' => $userPrompt),
		));

		$reply = trim($reply);
		if ($reply === '') {
			throw new Exception('AI personalization returned an empty response.');
		}

		$personalized = explode($delimiter, $reply);
		if (count($personalized) !== count($paramValues)) {
			// Model didn't follow the format instructions - safer to fall back
			// than to send a mismatched/garbled template to the customer.
			throw new Exception('AI personalization returned an unexpected number of values (' . count($personalized) . ' vs ' . count($paramValues) . ').');
		}

		return array_map('trim', $personalized);
	}
}
