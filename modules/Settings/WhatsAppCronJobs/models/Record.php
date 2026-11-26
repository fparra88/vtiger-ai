<?php
/*+***********************************************************************************
 * Holds the 3 predefined WhatsApp cron job config rows as a single record (one
 * admin form edits all 3 at once - there's no per-job list/create/delete, since
 * the set of jobs is fixed).
 *************************************************************************************/

class Settings_WhatsAppCronJobs_Record_Model extends Settings_Vtiger_Record_Model {

	/** @var Array job_key => row data (merged with the static job definition) */
	private $jobs = array();

	public function getId() {
		return 1;
	}

	public function getName() {
		return 'WhatsAppCronJobs';
	}

	public function getModule() {
		return $this->module;
	}

	public function setModule($moduleModel) {
		$this->module = $moduleModel;
		return $this;
	}

	public function getJobs() {
		return $this->jobs;
	}

	public function getJob($jobKey) {
		return isset($this->jobs[$jobKey]) ? $this->jobs[$jobKey] : false;
	}

	/**
	 * Function to load the 3 predefined job rows (creating/seeding the table first
	 * if needed) merged with their static label/description/hasThreshold definition.
	 * @param <String> $qualifiedModuleName
	 * @return <Settings_WhatsAppCronJobs_Record_Model>
	 */
	public static function getConfigInstance($qualifiedModuleName) {
		WhatsAppNotifier_Setup::createCronJobConfigTableIfNeeded();

		$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
		$definitions = $moduleModel->getJobDefinitions();

		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT * FROM vtiger_whatsappnotifier_cronjobs', array());

		$jobs = array();
		while ($row = $db->fetch_array($result)) {
			$jobKey = $row['job_key'];
			if (!isset($definitions[$jobKey])) {
				continue;
			}
			$jobs[$jobKey] = array_merge($definitions[$jobKey], $row);
		}

		$recordModel = new self();
		$recordModel->jobs = $jobs;
		return $recordModel->setModule($moduleModel);
	}

	/**
	 * Function to save posted values for all 3 jobs in one go.
	 * @param <Array> $postedJobs job_key => ['isactive'=>, 'template_name'=>, 'language_code'=>, 'days_threshold'=>]
	 */
	public function save($postedJobs) {
		$db = PearDatabase::getInstance();

		foreach ($this->jobs as $jobKey => $job) {
			if (!isset($postedJobs[$jobKey])) {
				continue;
			}
			$posted = $postedJobs[$jobKey];

			$isActive = empty($posted['isactive']) ? 0 : 1;
			$templateName = isset($posted['template_name']) ? trim($posted['template_name']) : '';
			$languageCode = !empty($posted['language_code']) ? trim($posted['language_code']) : 'en_US';
			$daysThreshold = (isset($posted['days_threshold']) && $posted['days_threshold'] !== '') ? intval($posted['days_threshold']) : null;

			$db->pquery(
				'UPDATE vtiger_whatsappnotifier_cronjobs SET isactive = ?, template_name = ?, language_code = ?, days_threshold = ? WHERE job_key = ?',
				array($isActive, $templateName !== '' ? $templateName : null, $languageCode, $daysThreshold, $jobKey)
			);
		}
	}
}
