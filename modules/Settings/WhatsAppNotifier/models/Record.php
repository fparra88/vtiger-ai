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

class Settings_WhatsAppNotifier_Record_Model extends Settings_Vtiger_Record_Model {

	/**
	 * Function to get Id of this record instance
	 * @return <Integer> Id
	 */
	public function getId() {
		return $this->get('id');
	}

	/**
	 * Function to get Name of this record instance
	 * @return <String> Name
	 */
	public function getName() {
		return '';
	}

	/**
	 * Function to get module of this record instance
	 * @return <Settings_WhatsAppNotifier_Module_Model> $moduleModel
	 */
	public function getModule() {
		return $this->module;
	}

	/**
	 * Function to set module instance to this record instance
	 * @param <Settings_WhatsAppNotifier_Module_Model> $moduleModel
	 * @return <Settings_WhatsAppNotifier_Record_Model> this record
	 */
	public function setModule($moduleModel) {
		$this->module = $moduleModel;
		return $this;
	}

	/**
	 * Function to get Edit view url
	 * @return <String> Url
	 */
	public function getEditViewUrl() {
		$moduleModel = $this->getModule();
		return 'index.php?module='.$moduleModel->getName().'&parent='.$moduleModel->getParentName().'&view=Edit&record='.$this->getId();
	}

	/**
	 * Function to getDisplay value of every field
	 * @param <String> field name
	 * @return <String> field value
	 */
	public function getDisplayValue($key) {
		$value = $this->get($key);
		if ($key === 'isactive') {
			if ($value) {
				$value = 'Yes';
			} else {
				$value = 'No';
			}
		}
		return $value;
	}

	/**
	 * Function to get Editable fields for this instance
	 * @return <Array> field models list <Settings_WhatsAppNotifier_Field_Model>
	 */
	public function getEditableFields() {
		$editableFieldsList = $this->getModule()->getEditableFields();
		return $editableFieldsList;
	}

	/**
	 * Function to save the record. Encrypts the access token before storing it -
	 * if the value coming from the request is already protected (i.e. the edit
	 * form re-submitted the masked placeholder unchanged) it is stored as-is to
	 * avoid double-encrypting it.
	 */
	public function save() {
		WhatsAppNotifier_Setup::createTablesIfNeeded();

		$db = PearDatabase::getInstance();

		$accessToken = $this->get('access_token');
		if (!Vtiger_Functions::isProtectedText($accessToken)) {
			$accessToken = Vtiger_Functions::toProtectedText($accessToken);
		}
		$appSecret = $this->get('app_secret');
		if (!empty($appSecret) && !Vtiger_Functions::isProtectedText($appSecret)) {
			$appSecret = Vtiger_Functions::toProtectedText($appSecret);
		}

		$id = $this->getId();
		$params = array(
			$this->get('phone_number_id'),
			$accessToken,
			$this->get('business_account_id'),
			$this->get('verify_token'),
			$appSecret,
			$this->get('isactive'),
		);

		if ($id) {
			$query = 'UPDATE vtiger_whatsappnotifier_servers SET phone_number_id = ?, access_token = ?, business_account_id = ?, verify_token = ?, app_secret = ?, isactive = ? WHERE id = ?';
			array_push($params, $id);
		} else {
			$query = 'INSERT INTO vtiger_whatsappnotifier_servers(phone_number_id, access_token, business_account_id, verify_token, app_secret, isactive) VALUES(?, ?, ?, ?, ?, ?)';
		}
		$db->pquery($query, $params);

		// Keep the in-memory record consistent with what is now stored, so that a
		// subsequent getDisplayValue()/get() call (e.g. re-rendering the form)
		// shows the protected placeholder rather than the raw submitted value.
		$this->set('access_token', $accessToken);
		$this->set('app_secret', $appSecret);
	}

	/**
	 * Function to get record instance by using id and moduleName
	 * @param <Integer> $recordId
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_WhatsAppNotifier_Record_Model> RecordModel
	 */
	static public function getInstanceById($recordId, $qualifiedModuleName) {
		WhatsAppNotifier_Setup::createTablesIfNeeded();

		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT * FROM vtiger_whatsappnotifier_servers WHERE id = ?', array($recordId));

		if ($db->num_rows($result)) {
			$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
			$rowData = $db->query_result_rowdata($result, 0);

			$recordModel = new self();
			$recordModel->setData($rowData)->setModule($moduleModel);

			return $recordModel;
		}
		return false;
	}

	/**
	 * Function to get the currently active configuration record, if any.
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_WhatsAppNotifier_Record_Model>|false
	 */
	static public function getActiveInstance($qualifiedModuleName) {
		WhatsAppNotifier_Setup::createTablesIfNeeded();

		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT * FROM vtiger_whatsappnotifier_servers WHERE isactive = 1 ORDER BY id DESC LIMIT 1', array());

		if ($result && $db->num_rows($result)) {
			$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
			$rowData = $db->query_result_rowdata($result, 0);

			$recordModel = new self();
			$recordModel->setData($rowData)->setModule($moduleModel);

			return $recordModel;
		}
		return false;
	}

	/**
	 * Function to get clean record instance by using moduleName
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_WhatsAppNotifier_Record_Model>
	 */
	static public function getCleanInstance($qualifiedModuleName) {
		$recordModel = new self();
		$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
		return $recordModel->setModule($moduleModel);
	}
}
