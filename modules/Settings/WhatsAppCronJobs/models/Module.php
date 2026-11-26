<?php
/*+***********************************************************************************
 * Settings page for the 3 predefined WhatsApp cron jobs (birthday_contact,
 * birthday_employee, inactive_contact). Lightweight non-entity module, same style
 * as Settings_WhatsAppNotifier - no menu entry, accessed directly via
 * index.php?module=WhatsAppCronJobs&parent=Settings&view=Edit.
 *************************************************************************************/

require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';

class Settings_WhatsAppCronJobs_Module_Model extends Settings_Vtiger_Module_Model {

	var $baseTable = 'vtiger_whatsappnotifier_cronjobs';
	var $baseIndex = 'id';
	var $name = 'WhatsAppCronJobs';

	/**
	 * Function to get the static definition (label/description/whether the
	 * days_threshold field applies) of each predefined job, keyed by job_key.
	 * @return <Array>
	 */
	public function getJobDefinitions() {
		return array(
			'birthday_contact' => array(
				'label' => 'LBL_JOB_BIRTHDAY_CONTACT',
				'description' => 'LBL_JOB_BIRTHDAY_CONTACT_DESC',
				'hasThreshold' => false,
			),
			'birthday_employee' => array(
				'label' => 'LBL_JOB_BIRTHDAY_EMPLOYEE',
				'description' => 'LBL_JOB_BIRTHDAY_EMPLOYEE_DESC',
				'hasThreshold' => false,
			),
			'inactive_contact' => array(
				'label' => 'LBL_JOB_INACTIVE_CONTACT',
				'description' => 'LBL_JOB_INACTIVE_CONTACT_DESC',
				'hasThreshold' => true,
			),
		);
	}

	/**
	 * Function to get Edit view url
	 * @return <String> Url
	 */
	public function getCreateRecordUrl() {
		return 'index.php?module=' . $this->getName() . '&parent=' . $this->getParentName() . '&view=Edit';
	}

	/**
	 * Function to get List view url (not implemented - this module only ever edits
	 * the 3 predefined job rows directly, same skeleton style as WhatsAppNotifier)
	 * @return <String> Url
	 */
	public function getListViewUrl() {
		return 'index.php?module=' . $this->getName() . '&parent=' . $this->getParentName() . '&view=Edit';
	}
}
