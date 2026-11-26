<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

// AIAssistant_OpenAIProvider_Model lives under modules/AIAssistant/providers/ which
// does not match Vtiger_Loader's automatic "_Model suffix -> models/ dir" convention,
// so it is required explicitly rather than relying on autoload.
require_once 'modules/AIAssistant/providers/OpenAIProvider.php';

/**
 * This module stores a single-row config (fixed id=1), there is only ever
 * one OpenAI configuration for the whole instance.
 */
class Settings_AIAssistant_Record_Model extends Settings_Vtiger_Record_Model {

	const CONFIG_TABLE = 'vtiger_aiassistant_config';
	const CONFIG_ID = 1;

	/** Placeholder shown in the UI when an API key is already saved. Submitting the
	 *  form without changing this value must NOT overwrite / re-encrypt the stored key. */
	const MASKED_API_KEY_PLACEHOLDER = '••••••••';

	/**
	 * Function to get Id of this record instance
	 * @return <Integer> Id
	 */
	public function getId() {
		return self::CONFIG_ID;
	}

	/**
	 * Function to get Name of this record instance
	 * @return <String> Name
	 */
	public function getName() {
		return 'AIAssistant';
	}

	/**
	 * Function to get module of this record instance
	 * @return <Settings_AIAssistant_Module_Model> $moduleModel
	 */
	public function getModule() {
		return $this->module;
	}

	/**
	 * Function to set module instance to this record instance
	 * @param <Settings_AIAssistant_Module_Model> $moduleModel
	 * @return <Settings_AIAssistant_Record_Model> this record
	 */
	public function setModule($moduleModel) {
		$this->module = $moduleModel;
		return $this;
	}

	/**
	 * Function to get Editable fields for this instance
	 * @return <Array> field models list <Settings_AIAssistant_Field_Model>
	 */
	public function getEditableFields() {
		return $this->getModule()->getEditableFields();
	}

	/**
	 * Function to get the value to render for the API key field in the UI.
	 * Never exposes the decrypted (or even encrypted) value to the browser.
	 * @return <String>
	 */
	public function getMaskedApiKeyForDisplay() {
		$storedValue = $this->get('api_key');
		return !empty($storedValue) ? self::MASKED_API_KEY_PLACEHOLDER : '';
	}

	/**
	 * Function to getDisplay value of every field
	 * @param <String> field name
	 * @return <String> field value
	 */
	public function getDisplayValue($key) {
		if ($key === 'api_key') {
			return $this->getMaskedApiKeyForDisplay();
		}
		return $this->get($key);
	}

	/**
	 * Function to get the currently selected allowed roles as a plain array
	 * of roleid strings, for checking "is this role checked" in the UI.
	 * @return <Array>
	 */
	public function getAllowedRolesArray() {
		$stored = $this->get('allowed_roles');
		if (empty($stored)) {
			return array();
		}
		return array_values(array_filter(array_map('trim', explode(',', $stored))));
	}

	/**
	 * Function to save the record.
	 *
	 * Anti-double-encryption rule (mirrors the intent of
	 * SMSNotifier's Vtiger_Functions::isProtectedText() check):
	 *  - If the submitted api_key equals the masked placeholder, or is empty,
	 *    the admin did not intend to change the key - keep whatever is
	 *    currently stored (already encrypted) untouched.
	 *  - If the submitted api_key is a real new value, encrypt it fresh.
	 *  - If somehow an already-protected value is passed in (isProtectedText),
	 *    do not re-encrypt it.
	 */
	public function save() {
		Settings_AIAssistant_Module_Model::createTableIfRequired();

		$adb = PearDatabase::getInstance();

		$submittedApiKey = $this->get('api_key');
		if ($submittedApiKey === self::MASKED_API_KEY_PLACEHOLDER || $submittedApiKey === null || $submittedApiKey === '') {
			// Keep existing stored (already encrypted) value untouched.
			$existing = AIAssistant_OpenAIProvider_Model::getRawConfig();
			$apiKeyToStore = $existing ? $existing['api_key'] : '';
		} else if (Vtiger_Functions::isProtectedText($submittedApiKey)) {
			// Already encrypted (e.g. round-tripped programmatically) - do not re-encrypt.
			$apiKeyToStore = $submittedApiKey;
		} else {
			$apiKeyToStore = Vtiger_Functions::toProtectedText($submittedApiKey);
		}

		$model = $this->get('model');
		if (empty($model)) {
			$model = AIAssistant_OpenAIProvider_Model::DEFAULT_MODEL;
		}

		$allowedRolesInput = $this->get('allowed_roles');
		if (is_array($allowedRolesInput)) {
			$allowedRoles = implode(',', array_values(array_filter(array_map('trim', $allowedRolesInput))));
		} else {
			$allowedRoles = (string) $allowedRolesInput;
		}

		$systemPrompt = trim((string) $this->get('system_prompt'));
		if (mb_strlen($systemPrompt, 'UTF-8') > AIAssistant_OpenAIProvider_Model::MAX_SYSTEM_PROMPT_LENGTH) {
			throw new Exception(vtranslate('LBL_SYSTEM_PROMPT_TOO_LONG', 'Settings:AIAssistant'));
		}

		$enableChat = $this->get('enable_chat') ? 1 : 0;
		$enableScoring = $this->get('enable_scoring') ? 1 : 0;
		$enableWhatsappPersonalize = $this->get('enable_whatsapp_personalize') ? 1 : 0;
		$scoringModules = (string)$this->get('scoring_modules');
		$scoringBatchSize = (int)$this->get('scoring_batch_size');
		if ($scoringBatchSize <= 0) {
			$scoringBatchSize = 20;
		}

		$sql = 'INSERT INTO ' . self::CONFIG_TABLE . '
				(id, api_key, model, system_prompt, enable_chat, allowed_roles, enable_scoring, enable_whatsapp_personalize, scoring_modules, scoring_batch_size)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					api_key = ?, model = ?, system_prompt = ?, enable_chat = ?, allowed_roles = ?, enable_scoring = ?,
					enable_whatsapp_personalize = ?, scoring_modules = ?, scoring_batch_size = ?';

		$params = array(
			self::CONFIG_ID, $apiKeyToStore, $model, $systemPrompt, $enableChat, $allowedRoles, $enableScoring, $enableWhatsappPersonalize, $scoringModules, $scoringBatchSize,
			$apiKeyToStore, $model, $systemPrompt, $enableChat, $allowedRoles, $enableScoring, $enableWhatsappPersonalize, $scoringModules, $scoringBatchSize,
		);

		$adb->pquery($sql, $params);

		// Reflect the stored (encrypted) value back onto this in-memory instance.
		$this->set('api_key', $apiKeyToStore);
		$this->set('model', $model);
		$this->set('system_prompt', $systemPrompt);
		$this->set('enable_chat', $enableChat);
		$this->set('allowed_roles', $allowedRoles);
		$this->set('enable_scoring', $enableScoring);
		$this->set('enable_whatsapp_personalize', $enableWhatsappPersonalize);
		$this->set('scoring_modules', $scoringModules);
		$this->set('scoring_batch_size', $scoringBatchSize);
	}

	/**
	 * Function to get record instance by using id and moduleName.
	 * Since this module only ever has a single config row (id=1), $recordId
	 * is accepted for signature-compatibility but the single row is always
	 * what gets loaded. If no row has been saved yet, a clean (default)
	 * instance is returned instead of false.
	 * @param <Integer> $recordId
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_AIAssistant_Record_Model>
	 */
	public static function getInstanceById($recordId, $qualifiedModuleName) {
		Settings_AIAssistant_Module_Model::createTableIfRequired();

		$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
		$rowData = AIAssistant_OpenAIProvider_Model::getRawConfig();

		if ($rowData) {
			$recordModel = new self();
			$recordModel->setData($rowData)->setModule($moduleModel);
			return $recordModel;
		}
		return self::getCleanInstance($qualifiedModuleName);
	}

	/**
	 * Function to get clean record instance (defaults, nothing saved yet) by using moduleName
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_AIAssistant_Record_Model>
	 */
	public static function getCleanInstance($qualifiedModuleName) {
		$recordModel = new self();
		$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
		$recordModel->setData(array(
			'id' => self::CONFIG_ID,
			'api_key' => '',
			'model' => AIAssistant_OpenAIProvider_Model::DEFAULT_MODEL,
			'enable_chat' => 0,
			'allowed_roles' => '',
			'enable_scoring' => 0,
			'enable_whatsapp_personalize' => 0,
			'scoring_modules' => '',
			'scoring_batch_size' => 20,
		));
		return $recordModel->setModule($moduleModel);
	}
}
