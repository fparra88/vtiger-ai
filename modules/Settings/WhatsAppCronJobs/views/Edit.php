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

class Settings_WhatsAppCronJobs_Edit_View extends Settings_Vtiger_Index_View {

	public function getPageTitle(Vtiger_Request $request) {
		return vtranslate('WhatsAppCronJobs', $request->getModule(false));
	}

	public function process(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);
		$recordModel = Settings_WhatsAppCronJobs_Record_Model::getConfigInstance($qualifiedModuleName);

		$viewer = $this->getViewer($request);
		$viewer->assign('RECORD_MODEL', $recordModel);
		$viewer->assign('JOBS', $recordModel->getJobs());
		$viewer->assign('QUALIFIED_MODULE_NAME', $qualifiedModuleName);

		$viewer->view('EditView.tpl', $qualifiedModuleName);
	}
}
