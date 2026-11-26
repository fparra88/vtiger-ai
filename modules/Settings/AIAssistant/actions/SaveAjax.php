<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Settings_AIAssistant_SaveAjax_Action extends Settings_Vtiger_Index_Action {

	public function checkPermission(Vtiger_Request $request) {
		parent::checkPermission($request);
		return true;
	}

	public function process(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);

		$recordModel = Settings_AIAssistant_Record_Model::getInstanceById(1, $qualifiedModuleName);

		$editableFields = $recordModel->getEditableFields();
		foreach ($editableFields as $fieldName => $fieldModel) {
			if ($fieldModel->getFieldDataType() === 'checkbox') {
				// Unchecked checkboxes are not submitted at all - treat missing as 0/off.
				$recordModel->set($fieldName, $request->get($fieldName) ? 1 : 0);
			} else if ($fieldModel->getFieldDataType() === 'rolemultiselect') {
				// No roles checked means the field is not submitted at all - treat missing as "no restriction".
				$recordModel->set($fieldName, (array) $request->get($fieldName));
			} else {
				$fieldValue = $request->get($fieldName);
				if (isset($fieldValue)) {
					$recordModel->set($fieldName, $fieldValue);
				}
			}
		}

		$response = new Vtiger_Response();
		try {
			$recordModel->save();
			$response->setResult(array('message' => vtranslate('LBL_SAVED_SUCCESSFULLY', $qualifiedModuleName)));
		} catch (Exception $e) {
			$response->setError($e->getCode(), $e->getMessage());
		}
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
