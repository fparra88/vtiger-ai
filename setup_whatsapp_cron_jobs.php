<?php
/*+***********************************************************************************
 * Throwaway one-off script: adds the 'birthday' custom field to Users (employees -
 * there is no dedicated employee module, so this reuses the CRM Users module),
 * creates/seeds vtiger_whatsappnotifier_cronjobs, and registers the 3 predefined
 * WhatsApp cron jobs (birthday_contact, birthday_employee, inactive_contact) into
 * vtiger_cron_task. Idempotent - safe to run multiple times. Mirrors
 * setup_ai_scoring_fields.php / register_whatsapp_task.php.
 *
 * All 3 cron tasks start disabled in vtiger_cron_task, and each job also starts
 * disabled (isactive=0, no template_name) in vtiger_whatsappnotifier_cronjobs -
 * nothing sends until an admin configures and enables each job in
 * Settings > WhatsApp Cron Jobs AND enables the matching task in
 * Settings > CRON Tasks.
 *************************************************************************************/

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/database/PearDatabase.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Module.php';
require_once 'vtlib/Vtiger/Block.php';
require_once 'vtlib/Vtiger/Field.php';
require_once 'vtlib/Vtiger/Cron.php';
require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';

function addBirthdayFieldToUsers() {
	$moduleInstance = Vtiger_Module::getInstance('Users');
	if (!$moduleInstance) {
		echo "SKIP: Users module not found\n";
		return;
	}

	$targetBlock = Vtiger_Block::getInstance('LBL_MORE_INFORMATION', $moduleInstance);
	if (!$targetBlock) {
		echo "SKIP: Users has no LBL_MORE_INFORMATION block to attach the field to\n";
		return;
	}

	if (!Vtiger_Field::getInstance('birthday', $moduleInstance)) {
		$field = new Vtiger_Field();
		$field->name = 'birthday';
		$field->label = 'Birthday';
		$field->table = 'vtiger_users';
		$field->column = 'birthday';
		$field->uitype = 5;
		$field->typeofdata = 'D~O';
		$field->columntype = 'DATE';
		$field->displaytype = 1;
		$targetBlock->addField($field);
		echo "Added birthday field to Users\n";
	} else {
		echo "birthday field already exists on Users\n";
	}
}

addBirthdayFieldToUsers();

WhatsAppNotifier_Setup::createTablesIfNeeded();
WhatsAppNotifier_Setup::registerCronTasksIfNeeded();
echo "Created/seeded vtiger_whatsappnotifier_cronjobs and registered the 3 predefined WhatsApp cron tasks (disabled by default)\n";
echo "Next steps: configure templates in Settings > WhatsApp Cron Jobs, then enable each task in Settings > CRON Tasks.\n";
