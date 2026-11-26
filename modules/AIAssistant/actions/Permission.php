<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 *************************************************************************************/

// AIAssistant_OpenAIProvider_Model lives under modules/AIAssistant/providers/, which
// does not match Vtiger_Loader's "_Model suffix -> models/ dir" autoload convention,
// so it is required explicitly (same reason as Chat.php).
require_once 'modules/AIAssistant/providers/OpenAIProvider.php';

/**
 * Lightweight AJAX endpoint the globally-loaded AIAssistantCommon.js calls on
 * every page load to decide whether to render the floating launcher button
 * for the current user. This is a UX convenience only - the real security
 * boundary is the same role/enable_chat check enforced server-side inside
 * AIAssistant_Chat_Action::process() (see Chat.php), which runs independently
 * of whatever this endpoint returns.
 * Reachable at index.php?module=AIAssistant&action=Permission.
 */
class AIAssistant_Permission_Action extends Vtiger_Action_Controller {

	public function requiresPermission(Vtiger_Request $request) {
		return array();
	}

	public function process(Vtiger_Request $request) {
		$response = new Vtiger_Response();
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$allowed = AIAssistant_OpenAIProvider_Model::isChatAllowedForUser($currentUser->getId(), $currentUser->isAdminUser());
		$response->setResult(array('allowed' => $allowed));
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateReadAccess();
	}
}
