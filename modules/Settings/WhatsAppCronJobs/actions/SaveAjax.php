<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

require_once 'modules/Settings/WhatsAppCronJobs/models/Record.php';

class Settings_WhatsAppCronJobs_SaveAjax_Action extends Settings_Vtiger_Index_Action {

	public function process(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);
		$recordModel = Settings_WhatsAppCronJobs_Record_Model::getConfigInstance($qualifiedModuleName);

		$postedJobs = $request->get('cronjobs');
		if (!is_array($postedJobs)) {
			$postedJobs = array();
		}

		$response = new Vtiger_Response();
		try {
			$recordModel->save($postedJobs);
			$response->setResult(array(vtranslate('LBL_SAVED_SUCCESSFULLY', $qualifiedModuleName)));
		} catch (Exception $e) {
			$response->setError($e->getMessage());
		}
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
