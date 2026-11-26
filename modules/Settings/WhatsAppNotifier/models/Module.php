<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';

class Settings_WhatsAppNotifier_Module_Model extends Settings_Vtiger_Module_Model {

	var $baseTable = 'vtiger_whatsappnotifier_servers';
	var $baseIndex = 'id';
	var $nameFields = array();
	var $listFields = array('phone_number_id' => 'Phone Number Id', 'business_account_id' => 'WhatsApp Business Account Id', 'isactive' => 'Active');
	var $name = 'WhatsAppNotifier';

	/**
	 * Function to get editable fields from this module
	 * @return <Array> list of editable fields
	 */
	public function getEditableFields() {
		$fieldsList = array(
				array('name' => 'phone_number_id',		'label' => 'Phone Number Id',			'type' => 'text'),
				array('name' => 'access_token',			'label' => 'Access Token',				'type' => 'password'),
				array('name' => 'business_account_id',	'label' => 'WhatsApp Business Account Id', 'type' => 'text'),
				array('name' => 'verify_token',			'label' => 'Verify Token',				'type' => 'text'),
				array('name' => 'app_secret',				'label' => 'App Secret (for webhook signature verification)', 'type' => 'password'),
				array('name' => 'isactive',				'label' => 'Active',					'type' => 'radio'),
		);

		$fieldModelsList = array();
		foreach ($fieldsList as $fieldInfo) {
			$fieldModelsList[$fieldInfo['name']] = Settings_WhatsAppNotifier_Field_Model::getInstanceByRow($fieldInfo);
		}
		return $fieldModelsList;
	}

	/**
	 * Function to get Create view url
	 * @return <String> Url
	 */
	public function getCreateRecordUrl() {
		return 'index.php?module='.$this->getName().'&parent='.$this->getParentName().'&view=Edit';
	}

	/**
	 * Function to get List view url (not implemented in this lightweight skeleton -
	 * this module only has a single static config record edited directly)
	 * @return <String> Url
	 */
	public function getListViewUrl() {
		return 'index.php?module='.$this->getName().'&parent='.$this->getParentName().'&view=Edit';
	}

	/**
	 * Function to delete records
	 * @param <Array> $recordIdsList
	 * @return <Boolean> true/false
	 */
	public static function deleteRecords($recordIdsList = array()) {
		if ($recordIdsList) {
			$db = PearDatabase::getInstance();
			$query = 'DELETE FROM vtiger_whatsappnotifier_servers WHERE id IN (' . generateQuestionMarks($recordIdsList). ')';
			$db->pquery($query, $recordIdsList);
			return true;
		}
		return false;
	}
}
