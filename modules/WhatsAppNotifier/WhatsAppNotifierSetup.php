<?php
/*+***********************************************************************************
 * WhatsAppNotifier - lightweight non-entity module.
 * Creates the config and send-log tables on first use (mirrors the lazy
 * table-creation style used by VTTaskType::registerTaskType()).
 *************************************************************************************/

class WhatsAppNotifier_Setup {

	/**
	 * Function to create the WhatsAppNotifier tables if they do not already exist.
	 */
	public static function createTablesIfNeeded() {
		if (!Vtiger_Utils::CheckTable('vtiger_whatsappnotifier_servers')) {
			Vtiger_Utils::CreateTable(
				'vtiger_whatsappnotifier_servers',
				"(id int(11) NOT NULL AUTO_INCREMENT,
					phone_number_id varchar(100) DEFAULT NULL,
					access_token text,
					business_account_id varchar(100) DEFAULT NULL,
					verify_token varchar(255) DEFAULT NULL,
					isactive int(1) DEFAULT 0,
					PRIMARY KEY (id))",
				' ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8'
			);
		}
		// app_secret is used to verify the X-Hub-Signature-256 header Meta sends
		// with every webhook POST (see callbacks/MetaWebhook.php). Added via
		// AddColumn (idempotent) rather than in the CREATE TABLE above so it also
		// reaches installs that already created the table before this existed.
		Vtiger_Utils::AddColumn('vtiger_whatsappnotifier_servers', 'app_secret', 'TEXT');

		if (!Vtiger_Utils::CheckTable('vtiger_whatsappnotifier_log')) {
			Vtiger_Utils::CreateTable(
				'vtiger_whatsappnotifier_log',
				"(id int(11) NOT NULL AUTO_INCREMENT,
					direction varchar(10) DEFAULT 'outbound',
					to_number varchar(50) DEFAULT NULL,
					from_number varchar(50) DEFAULT NULL,
					template_name varchar(100) DEFAULT NULL,
					message_body text,
					provider_message_id varchar(100) DEFAULT NULL,
					status varchar(20) DEFAULT NULL,
					error_message text,
					created_time datetime DEFAULT NULL,
					related_crmid int(11) DEFAULT NULL,
					related_module varchar(100) DEFAULT NULL,
					PRIMARY KEY (id))",
				' ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8'
			);
		}
		Vtiger_Utils::AddColumn('vtiger_whatsappnotifier_log', 'direction', "VARCHAR(10) DEFAULT 'outbound'");
		Vtiger_Utils::AddColumn('vtiger_whatsappnotifier_log', 'from_number', 'VARCHAR(50) DEFAULT NULL');

		self::createCronJobConfigTableIfNeeded();
	}

	/**
	 * List of the 3 predefined WhatsApp cron jobs. Any other use case is expected
	 * to be built by hand in Settings > Workflows using the generic VTWhatsAppTask
	 * action instead of being added here.
	 */
	public static function getPredefinedCronJobKeys() {
		return array('birthday_contact', 'birthday_employee', 'inactive_contact');
	}

	/**
	 * Function to create vtiger_whatsappnotifier_cronjobs (one row per predefined
	 * job, keyed by job_key) and seed it with the 3 predefined jobs if missing.
	 * Idempotent - safe to call on every request/setup run.
	 */
	public static function createCronJobConfigTableIfNeeded() {
		if (!Vtiger_Utils::CheckTable('vtiger_whatsappnotifier_cronjobs')) {
			Vtiger_Utils::CreateTable(
				'vtiger_whatsappnotifier_cronjobs',
				"(id int(11) NOT NULL AUTO_INCREMENT,
					job_key varchar(50) NOT NULL,
					isactive int(1) DEFAULT 0,
					template_name varchar(100) DEFAULT NULL,
					language_code varchar(10) DEFAULT 'en_US',
					days_threshold int(11) DEFAULT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY job_key (job_key))",
				' ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8'
			);
		}

		$adb = PearDatabase::getInstance();
		foreach (self::getPredefinedCronJobKeys() as $jobKey) {
			$result = $adb->pquery('SELECT 1 FROM vtiger_whatsappnotifier_cronjobs WHERE job_key = ?', array($jobKey));
			if (!$result || !$adb->num_rows($result)) {
				$defaultThreshold = ($jobKey === 'inactive_contact') ? 90 : null;
				$adb->pquery(
					'INSERT INTO vtiger_whatsappnotifier_cronjobs (job_key, isactive, template_name, language_code, days_threshold) VALUES (?, 0, NULL, ?, ?)',
					array($jobKey, 'en_US', $defaultThreshold)
				);
			}
		}
	}

	/**
	 * Function to register the 3 predefined WhatsApp cron jobs into vtiger_cron_task,
	 * if not already registered. Idempotent. Starts disabled - each job also stays
	 * disabled in vtiger_whatsappnotifier_cronjobs (isactive=0) until an admin sets
	 * a template name for it in Settings > WhatsApp Cron Jobs, so enabling the cron
	 * task alone does not start sending anything.
	 */
	public static function registerCronTasksIfNeeded() {
		require_once 'vtlib/Vtiger/Cron.php';

		$jobs = array(
			'birthday_contact' => array(
				'name' => 'WhatsApp Birthday - Contacts',
				'handler' => 'cron/modules/WhatsAppNotifier/birthday_contact.service',
				'description' => 'Sends a WhatsApp birthday template to Contacts whose birthday is today. Configure the template in Settings > WhatsApp Cron Jobs.',
			),
			'birthday_employee' => array(
				'name' => 'WhatsApp Birthday - Employees',
				'handler' => 'cron/modules/WhatsAppNotifier/birthday_employee.service',
				'description' => 'Sends a WhatsApp birthday template to active CRM users whose birthday is today. Configure the template in Settings > WhatsApp Cron Jobs.',
			),
			'inactive_contact' => array(
				'name' => 'WhatsApp Inactive Contact Reminder',
				'handler' => 'cron/modules/WhatsAppNotifier/inactive_contact.service',
				'description' => 'Sends a WhatsApp re-engagement template to Contacts with no related activity for longer than the configured threshold. Configure the template/threshold in Settings > WhatsApp Cron Jobs.',
			),
		);

		$adb = PearDatabase::getInstance();
		foreach ($jobs as $jobInfo) {
			$result = $adb->pquery('SELECT 1 FROM vtiger_cron_task WHERE name = ?', array($jobInfo['name']));
			$alreadyRegistered = $result && $adb->num_rows($result) > 0;
			if (!$alreadyRegistered) {
				Vtiger_Cron::register(
					$jobInfo['name'],
					$jobInfo['handler'],
					86400,
					'WhatsAppNotifier',
					Vtiger_Cron::$STATUS_DISABLED,
					0,
					$jobInfo['description']
				);
			}
		}
	}

	/**
	 * Function to register the VTWhatsAppTask workflow task type, if not already
	 * registered. Idempotent - safe to call on every request/setup run, unlike a
	 * bare VTTaskType::registerTaskType() call which always inserts a new row.
	 */
	public static function registerWorkflowTaskTypeIfNeeded() {
		require_once('modules/com_vtiger_workflow/VTTaskManager.inc');

		$adb = PearDatabase::getInstance();
		if (!Vtiger_Utils::CheckTable('com_vtiger_workflow_tasktypes')) {
			$alreadyRegistered = false;
		} else {
			$result = $adb->pquery('SELECT 1 FROM com_vtiger_workflow_tasktypes WHERE tasktypename = ?', array('VTWhatsAppTask'));
			$alreadyRegistered = $result && $adb->num_rows($result) > 0;
		}

		if (!$alreadyRegistered) {
			VTTaskType::registerTaskType(array(
				"name" => "VTWhatsAppTask",
				"label" => "WhatsApp Task",
				"classname" => "VTWhatsAppTask",
				"classpath" => "modules/com_vtiger_workflow/tasks/VTWhatsAppTask.inc",
				// NOTE: stock vtiger registers SMSNotifier's own task template as
				// "com_vtiger_workflow/taskforms/VTSMSTask.tpl", but that path does not
				// actually resolve: EditTask.tpl includes {$TASK_TEMPLATE_PATH} via a
				// raw Smarty {include}, which resolves against Vtiger_Viewer's sole
				// template_dir root (layouts/v7/), not layouts/v7/modules/ - so that
				// stock path 404s (verified: no such file exists anywhere in this
				// codebase). We point at the real, existing file location instead.
				"templatepath" => "modules/Settings/Workflows/Tasks/VTWhatsAppTask.tpl",
				"modules" => array('include' => array(), 'exclude' => array()),
				// sourcemodule intentionally left blank: VTTaskManager::getAll() hides
				// any task type whose sourcemodule has no active vtiger_tab entry
				// (modules/com_vtiger_workflow/VTTaskManager.inc:292-296), and this
				// lightweight non-entity module deliberately has no tab. Core task
				// types (VTEmailTask, VTCreateTodoTask, ...) use '' for the same reason.
				"sourcemodule" => '',
			));
		}
	}
}
