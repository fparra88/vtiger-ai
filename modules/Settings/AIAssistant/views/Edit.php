<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Settings_AIAssistant_Edit_View extends Settings_Vtiger_Index_View {

	public function process(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);

		$recordModel = Settings_AIAssistant_Record_Model::getInstanceById(1, $qualifiedModuleName);

		$viewer = $this->getViewer($request);
		$viewer->assign('RECORD_MODEL', $recordModel);
		$viewer->assign('EDITABLE_FIELDS', $recordModel->getEditableFields());
		$viewer->assign('QUALIFIED_MODULE_NAME', $qualifiedModuleName);
		$viewer->assign('COMMON_MODELS', array(
			'gpt-4o-mini' => 'gpt-4o-mini - rapido y economico (recomendado para empezar)',
			'gpt-4.1-mini' => 'gpt-4.1-mini - economico, mejor razonamiento',
			'gpt-4o' => 'gpt-4o - mayor calidad',
			'gpt-4.1' => 'gpt-4.1 - mayor calidad, contexto largo',
			'gpt-5-mini' => 'gpt-5-mini - razonamiento avanzado, costo medio',
			'gpt-5' => 'gpt-5 - maxima calidad, costo mas alto',
			'o4-mini' => 'o4-mini - razonamiento, analisis complejos',
		));

		// Pre-compute the "checked" state per role here (rather than in the
		// template) since Smarty in this codebase is not set up to call
		// arbitrary PHP functions like in_array() from inside a .tpl file.
		$selectedRoles = $recordModel->getAllowedRolesArray();
		$allRoles = $recordModel->getModule()->getAllRoles();
		foreach ($allRoles as &$role) {
			$role['checked'] = in_array($role['roleid'], $selectedRoles, true);
			$role['indent'] = ((int) $role['depth']) * 16;
		}
		unset($role);
		$viewer->assign('ALL_ROLES', $allRoles);

		$viewer->view('EditView.tpl', $qualifiedModuleName);
	}

	public function getPageTitle(Vtiger_Request $request) {
		return vtranslate('LBL_AI_ASSISTANT_SETTINGS', $request->getModule(false));
	}
}
