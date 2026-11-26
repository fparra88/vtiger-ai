<?php
/*+***********************************************************************************
 * Throwaway one-off script: registers VTWhatsAppTask into com_vtiger_workflow_tasktypes.
 * Safe to run multiple times (idempotent). Delete once packaged as a real vtlib
 * module manifest for deployment.
 *************************************************************************************/

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/database/PearDatabase.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Functions.php';
require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';

WhatsAppNotifier_Setup::registerWorkflowTaskTypeIfNeeded();

$adb = PearDatabase::getInstance();
$result = $adb->pquery('SELECT * FROM com_vtiger_workflow_tasktypes WHERE tasktypename = ?', array('VTWhatsAppTask'));
if ($result && $adb->num_rows($result) > 0) {
	echo "VTWhatsAppTask registered:\n";
	print_r($adb->query_result_rowdata($result, 0));
} else {
	echo "FAILED: VTWhatsAppTask not found after registration attempt\n";
	exit(1);
}
