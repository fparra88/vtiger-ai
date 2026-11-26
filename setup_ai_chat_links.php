<?php
/*+***********************************************************************************
 * Throwaway one-off script: wires the AI Assistant chat widget's JS onto every
 * page. Idempotent - safe to run multiple times.
 * Delete once packaged as a real vtlib module manifest for deployment.
 *
 * Note: this used to also add a per-module DETAILVIEW "AI Assistant" link on
 * Leads/Contacts/HelpDesk. That has been superseded by a self-injected
 * site-wide floating launcher button (see AIAssistantCommon.js init()/
 * renderLauncher()), which - unlike the old static DETAILVIEW link - respects
 * the Settings > AI Assistant > Allowed Roles restriction. Any such rows are
 * now removed instead of (re)created - see the cleanup block below.
 *************************************************************************************/

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/database/PearDatabase.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Link.php';

function linkAlreadyExists($tabid, $type, $label) {
	$adb = PearDatabase::getInstance();
	$result = $adb->pquery('SELECT 1 FROM vtiger_links WHERE tabid = ? AND linktype = ? AND linklabel = ?', array($tabid, $type, $label));
	return $result && $adb->num_rows($result) > 0;
}

$targetModules = array('Leads', 'Contacts', 'HelpDesk');
$headerScriptAdded = false;

foreach ($targetModules as $moduleName) {
	$moduleInstance = Vtiger_Module::getInstance($moduleName);
	if (!$moduleInstance) {
		echo "SKIP: module $moduleName not found\n";
		continue;
	}

	if (!$headerScriptAdded) {
		// HEADERSCRIPT links are loaded globally regardless of tabid
		// (Vtiger_Link_Model::getAllByType(Vtiger_Link::IGNORE_MODULE, ...) in
		// modules/Vtiger/views/Header.php) - registering it once under any valid
		// tabid is enough for it to load on every page, same as SMSNotifier does.
		if (!linkAlreadyExists($moduleInstance->id, 'HEADERSCRIPT', 'AIAssistantCommonJS')) {
			$moduleInstance->addLink('HEADERSCRIPT', 'AIAssistantCommonJS', 'modules/AIAssistant/AIAssistantCommon.js');
			echo "Added HEADERSCRIPT link for AIAssistantCommon.js\n";
		}
		$headerScriptAdded = true;
	}

	// Remove the old per-module button in favour of the site-wide floating
	// launcher (which respects Allowed Roles; this static link never did).
	$adb = PearDatabase::getInstance();
	$deleted = $adb->pquery('DELETE FROM vtiger_links WHERE tabid = ? AND linktype = ? AND linklabel = ?', array($moduleInstance->id, 'DETAILVIEW', 'AI Assistant'));
	if ($deleted) {
		echo "Removed legacy DETAILVIEW 'AI Assistant' link from $moduleName (if it existed)\n";
	}
}
