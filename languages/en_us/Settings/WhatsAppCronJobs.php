<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
$languageStrings = array(
	'WhatsAppCronJobs' => 'WhatsApp Cron Jobs',
	'LBL_WHATSAPP_CRONJOBS_INTRO' => 'Configure the 3 predefined WhatsApp jobs below, then enable each one in Settings > CRON Tasks. Any other use case should be built in Settings > Workflows using the "WhatsApp Task" action.',
	'LBL_ACTIVE' => 'Active',
	'LBL_WHATSAPP_TEMPLATE_NAME' => 'WhatsApp Template Name',
	'LBL_WHATSAPP_TEMPLATE_NAME_PLACEHOLDER' => 'Name of the template pre-approved in Meta Business Manager',
	'LBL_LANGUAGE_CODE' => 'Template Language Code',
	'LBL_DAYS_THRESHOLD' => 'Days Without Activity',
	'LBL_JOB_BIRTHDAY_CONTACT' => 'Cumpleaños Cliente',
	'LBL_JOB_BIRTHDAY_CONTACT_DESC' => 'Sends the template to every Contact whose birthday is today. Recipient: Contact mobile/phone. Template params: [Contact Name].',
	'LBL_JOB_BIRTHDAY_EMPLOYEE' => 'Cumpleaños Empleado',
	'LBL_JOB_BIRTHDAY_EMPLOYEE_DESC' => 'Sends the template to every active CRM user whose birthday is today. Recipient: User mobile phone. Template params: [Employee Name]. Requires running setup_whatsapp_cron_jobs.php once to add the Birthday field to Users.',
	'LBL_JOB_INACTIVE_CONTACT' => 'Cliente Sin Actividad',
	'LBL_JOB_INACTIVE_CONTACT_DESC' => 'Sends the template to Contacts with no related activity (own record changes, HelpDesk tickets, Calls, Emails) for longer than the configured days. Recipient: Contact mobile/phone. Template params: [Contact Name, Days Inactive]. Only sent once per inactivity episode.',
);
