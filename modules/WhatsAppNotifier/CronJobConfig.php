<?php
/*+***********************************************************************************
 * WhatsAppNotifier - shared config/dedup helper for the 3 predefined cron jobs
 * (birthday_contact, birthday_employee, inactive_contact). Each job's .service
 * handler uses this instead of going through the Workflow engine: it reads its
 * row from vtiger_whatsappnotifier_cronjobs and checks vtiger_whatsappnotifier_log
 * to avoid re-notifying the same record more than once per occurrence.
 *************************************************************************************/

class WhatsAppNotifier_CronJobConfig_Model {

	/**
	 * Function to get the config row for a predefined job.
	 * @param String $jobKey one of WhatsAppNotifier_Setup::getPredefinedCronJobKeys()
	 * @return Array|false ['isactive'=>, 'template_name'=>, 'language_code'=>, 'days_threshold'=>] or false if disabled/unconfigured
	 */
	public static function getActiveConfig($jobKey) {
		require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';
		WhatsAppNotifier_Setup::createCronJobConfigTableIfNeeded();

		$adb = PearDatabase::getInstance();
		$result = $adb->pquery('SELECT * FROM vtiger_whatsappnotifier_cronjobs WHERE job_key = ?', array($jobKey));
		if (!$result || !$adb->num_rows($result)) {
			return false;
		}

		$row = $adb->query_result_rowdata($result, 0);
		if (empty($row['isactive']) || empty($row['template_name'])) {
			return false;
		}
		return $row;
	}

	/**
	 * Function to check whether a 'sent' notification was already logged for this
	 * record/template on/after a given reference timestamp. Used so a job does not
	 * re-send to the same record every time the cron runs (e.g. a birthday job
	 * running more than once on the same day, or an inactivity job re-triggering
	 * daily for a record that never recovers).
	 * @param String $relatedModule e.g. 'Contacts', 'Users'
	 * @param Integer $relatedCrmId
	 * @param String $templateName
	 * @param String $sinceDateTime 'Y-m-d H:i:s' - only a log row created on/after this counts
	 * @return Boolean
	 */
	public static function wasAlreadyNotifiedSince($relatedModule, $relatedCrmId, $templateName, $sinceDateTime) {
		$adb = PearDatabase::getInstance();
		$result = $adb->pquery(
			"SELECT 1 FROM vtiger_whatsappnotifier_log
				WHERE related_module = ? AND related_crmid = ? AND template_name = ? AND status = 'sent' AND created_time >= ?
				LIMIT 1",
			array($relatedModule, $relatedCrmId, $templateName, $sinceDateTime)
		);
		return $result && $adb->num_rows($result) > 0;
	}
}
