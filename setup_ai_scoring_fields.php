<?php
/*+***********************************************************************************
 * Throwaway one-off script: adds ai_score / ai_score_reason custom fields to
 * Leads and HelpDesk via vtlib, and registers the AIAssistant scoring cron task.
 * Idempotent - safe to run multiple times. Delete once packaged as a real vtlib
 * module manifest for deployment.
 *************************************************************************************/

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/database/PearDatabase.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Block.php';
require_once 'vtlib/Vtiger/Field.php';
require_once 'vtlib/Vtiger/Cron.php';

function addAiScoreFieldsToModule($moduleName) {
	$moduleInstance = Vtiger_Module::getInstance($moduleName);
	if (!$moduleInstance) {
		echo "SKIP: module $moduleName not found\n";
		return;
	}

	$targetBlock = Vtiger_Block::getInstance('LBL_CUSTOM_INFORMATION', $moduleInstance);
	if (!$targetBlock) {
		echo "SKIP: $moduleName has no LBL_CUSTOM_INFORMATION block to attach fields to\n";
		return;
	}

	if (!Vtiger_Field::getInstance('ai_score', $moduleInstance)) {
		$field = new Vtiger_Field();
		$field->name = 'ai_score';
		$field->label = 'AI Score';
		$field->uitype = 7;
		$field->typeofdata = 'I~O';
		$field->columntype = 'INT(3)';
		$field->displaytype = 2;
		$targetBlock->addField($field);
		echo "Added ai_score to $moduleName\n";
	} else {
		echo "ai_score already exists on $moduleName\n";
	}

	if (!Vtiger_Field::getInstance('ai_score_reason', $moduleInstance)) {
		$field = new Vtiger_Field();
		$field->name = 'ai_score_reason';
		$field->label = 'AI Score Reason';
		$field->uitype = 19;
		$field->typeofdata = 'V~O';
		$field->columntype = 'VARCHAR(500)';
		$field->displaytype = 2;
		$targetBlock->addField($field);
		echo "Added ai_score_reason to $moduleName\n";
	} else {
		echo "ai_score_reason already exists on $moduleName\n";
	}
}

addAiScoreFieldsToModule('Leads');
addAiScoreFieldsToModule('HelpDesk');

Vtiger_Cron::deregister('AIAssistant Lead/Case Scoring');
Vtiger_Cron::register(
	'AIAssistant Lead/Case Scoring',
	'cron/modules/AIAssistant/aiassistant.service',
	3600,
	'AIAssistant',
	Vtiger_Cron::$STATUS_DISABLED,
	0,
	'Scores Leads/Cases using the configured OpenAI model. Configure modules/batch size in Settings > AI Assistant. Starts disabled - enable from Settings > CRON Tasks once an API key is set.'
);
echo "Registered AIAssistant Lead/Case Scoring cron task (disabled by default)\n";
