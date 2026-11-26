<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Settings_WhatsAppNotifier_Edit_View extends Settings_Vtiger_Index_View {

	public function getPageTitle(Vtiger_Request $request) {
		return vtranslate('WhatsAppNotifier', $request->getModule(false));
	}

	public function process(Vtiger_Request $request) {
		$recordId = $request->get('record');
		$qualifiedModuleName = $request->getModule(false);

		if ($recordId) {
			$recordModel = Settings_WhatsAppNotifier_Record_Model::getInstanceById($recordId, $qualifiedModuleName);
		} else {
			// No explicit record requested - fall back to the active configuration
			// (if any) so the admin edits the existing config rather than creating
			// a second row, since this module only ever has one meaningful record.
			$recordModel = Settings_WhatsAppNotifier_Record_Model::getActiveInstance($qualifiedModuleName);
			if (!$recordModel) {
				$recordModel = Settings_WhatsAppNotifier_Record_Model::getCleanInstance($qualifiedModuleName);
			}
		}

		// Mask the access token in the rendered HTML - never emit the decrypted
		// value to the browser. The protected ($ve$...) form is unreadable
		// gibberish and doubles as a safe "unchanged" placeholder: if the form is
		// resubmitted without editing the field, Record_Model::save() recognises
		// it as already-protected and will not double-encrypt it.
		$accessToken = $recordModel->get('access_token');
		if (!empty($accessToken) && !Vtiger_Functions::isProtectedText($accessToken)) {
			$recordModel->set('access_token', Vtiger_Functions::toProtectedText($accessToken));
		}
		$appSecret = $recordModel->get('app_secret');
		if (!empty($appSecret) && !Vtiger_Functions::isProtectedText($appSecret)) {
			$recordModel->set('app_secret', Vtiger_Functions::toProtectedText($appSecret));
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('RECORD_ID', $recordModel->getId());
		$viewer->assign('RECORD_MODEL', $recordModel);
		$viewer->assign('EDITABLE_FIELDS', $recordModel->getEditableFields());
		$viewer->assign('QUALIFIED_MODULE_NAME', $qualifiedModuleName);

		$viewer->view('EditView.tpl', $qualifiedModuleName);
	}
}
