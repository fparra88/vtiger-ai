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

class Settings_AIAssistant_Module_Model extends Settings_Vtiger_Module_Model {

	var $baseTable = 'vtiger_aiassistant_config';
	var $baseIndex = 'id';
	var $nameFields = array();
	var $listFields = array();
	var $name = 'AIAssistant';

	/**
	 * Function to make sure the config table exists (lazy self-create, no
	 * manifest/install step - mirrors the provider's own guard so either
	 * Settings UI code or runtime code can call it safely).
	 */
	public static function createTableIfRequired() {
		AIAssistant_OpenAIProvider_Model::createConfigTableIfRequired();
	}

	/**
	 * Function to get editable fields from this module
	 * @return <Array> list of field models, keyed by field name
	 */
	public function getEditableFields() {
		$fieldsList = array(
			array('name' => 'api_key', 'label' => 'LBL_API_KEY', 'type' => 'password'),
			array('name' => 'model', 'label' => 'LBL_MODEL', 'type' => 'text'),
			array('name' => 'system_prompt', 'label' => 'LBL_SYSTEM_PROMPT', 'type' => 'textarea'),
			array('name' => 'enable_chat', 'label' => 'LBL_ENABLE_CHAT', 'type' => 'checkbox'),
			array('name' => 'allowed_roles', 'label' => 'LBL_ALLOWED_ROLES', 'type' => 'rolemultiselect'),
			array('name' => 'enable_scoring', 'label' => 'LBL_ENABLE_SCORING', 'type' => 'checkbox'),
			array('name' => 'enable_whatsapp_personalize', 'label' => 'LBL_ENABLE_WHATSAPP_PERSONALIZE', 'type' => 'checkbox'),
			array('name' => 'scoring_modules', 'label' => 'LBL_SCORING_MODULES', 'type' => 'text'),
			array('name' => 'scoring_batch_size', 'label' => 'LBL_SCORING_BATCH_SIZE', 'type' => 'number'),
		);

		$fieldModelsList = array();
		foreach ($fieldsList as $fieldInfo) {
			$fieldModelsList[$fieldInfo['name']] = Settings_AIAssistant_Field_Model::getInstanceByRow($fieldInfo);
		}
		return $fieldModelsList;
	}

	/**
	 * Function to get list of editable field names only
	 * @return <Array>
	 */
	public function getEditableFieldsList() {
		return array_keys($this->getEditableFields());
	}

	/**
	 * Function to get Edit view url
	 * @return <String> Url
	 */
	public function getEditViewUrl() {
		return 'index.php?module=' . $this->getName() . '&parent=' . $this->getParentName() . '&view=Edit';
	}

	/**
	 * Function to get the full list of CRM roles, ordered by hierarchy, for
	 * the "Allowed Roles" picker in the Settings UI.
	 * @return <Array> list of array('roleid'=>.., 'rolename'=>.., 'depth'=>..)
	 */
	public function getAllRoles() {
		$adb = PearDatabase::getInstance();
		$result = $adb->pquery('SELECT roleid, rolename, depth FROM vtiger_role ORDER BY parentrole', array());
		$roles = array();
		while ($row = $adb->fetchByAssoc($result)) {
			$roles[] = $row;
		}
		return $roles;
	}
}
