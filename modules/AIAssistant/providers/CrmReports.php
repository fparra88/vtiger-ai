<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 *************************************************************************************/

/**
 * Fixed, read-only, parameterless report queries the AI Assistant is allowed
 * to run on behalf of the user via OpenAI function calling (see Chat.php).
 *
 * Deliberately NOT a generic "run this SQL" tool - the model can only ever
 * pick one of the report names below (an enum in the tool schema), each of
 * which maps to one hardcoded, already-reviewed query. This keeps the AI
 * Assistant from being able to read arbitrary data or be prompt-injected
 * into running arbitrary SQL.
 */
class AIAssistant_CrmReports_Model {

	/**
	 * @return <Array> list of report names valid for the "report" tool argument
	 */
	public static function getAllowedReportNames() {
		return array('product_rotation', 'sales_pipeline', 'open_cases', 'top_accounts', 'leads_by_status', 'pending_invoicing', 'activity_by_salesperson');
	}

	/**
	 * Function to run one of the allowed reports.
	 * @param <String> $reportName One of getAllowedReportNames()
	 * @return <Array> rows (list of associative arrays), ready to json_encode back to the model
	 * @throws Exception if $reportName is not in the allowlist
	 */
	public static function run($reportName) {
		$adb = PearDatabase::getInstance();

		switch ($reportName) {
			case 'product_rotation':
				// Units actually sold per product across Invoices (not Quotes - those
				// aren't confirmed sales yet), ascending so lowest-rotation items are first.
				// The SUM is wrapped in a CASE (not a plain WHERE/ON filter) because this is
				// a LEFT JOIN: an unmatched setype would otherwise still leave the
				// vtiger_inventoryproductrel row (and its quantity) in the result set with
				// only the vtiger_crmentity columns nulled out, silently over-counting.
				$result = $adb->pquery(
					"SELECT p.productname,
					        COALESCE(SUM(CASE WHEN ce.crmid IS NOT NULL THEN ipr.quantity ELSE 0 END), 0) AS units_sold,
					        p.qtyinstock
					 FROM vtiger_products p
					 LEFT JOIN vtiger_inventoryproductrel ipr ON ipr.productid = p.productid
					 LEFT JOIN vtiger_crmentity ce ON ce.crmid = ipr.id AND ce.deleted = 0 AND ce.setype = 'Invoice'
					 WHERE p.discontinued = 0
					 GROUP BY p.productid, p.productname, p.qtyinstock
					 ORDER BY units_sold ASC, p.productname ASC",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'sales_pipeline':
				$result = $adb->pquery(
					"SELECT p.sales_stage, COUNT(*) AS deal_count, COALESCE(SUM(p.amount), 0) AS total_amount
					 FROM vtiger_potential p
					 INNER JOIN vtiger_crmentity ce ON ce.crmid = p.potentialid AND ce.deleted = 0
					 GROUP BY p.sales_stage
					 ORDER BY total_amount DESC",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'open_cases':
				// Note: vtiger_troubletickets' actual columns (title/priority/status) differ
				// from the MVC field names shown in the UI (ticket_title/ticketpriorities/ticketstatus).
				$result = $adb->pquery(
					"SELECT t.title AS ticket_title, t.priority AS ticketpriorities, t.status AS ticketstatus, a.accountname
					 FROM vtiger_troubletickets t
					 INNER JOIN vtiger_crmentity ce ON ce.crmid = t.ticketid AND ce.deleted = 0
					 LEFT JOIN vtiger_account a ON a.accountid = t.parent_id
					 WHERE t.status != 'Closed'
					 ORDER BY FIELD(t.priority, 'Urgent', 'High', 'Normal', 'Low')",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'top_accounts':
				// Same CASE-wrapped SUM/COUNT reasoning as product_rotation above: this is a
				// LEFT JOIN, so a deleted potential would otherwise still be summed/counted.
				$result = $adb->pquery(
					"SELECT a.accountname,
					        COALESCE(SUM(CASE WHEN ce.crmid IS NOT NULL THEN p.amount ELSE 0 END), 0) AS pipeline_value,
					        COUNT(CASE WHEN ce.crmid IS NOT NULL THEN p.potentialid END) AS deal_count
					 FROM vtiger_account a
					 LEFT JOIN vtiger_potential p ON p.related_to = a.accountid
					 LEFT JOIN vtiger_crmentity ce ON ce.crmid = p.potentialid AND ce.deleted = 0
					 GROUP BY a.accountid, a.accountname
					 ORDER BY pipeline_value DESC
					 LIMIT 10",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'pending_invoicing':
				// "Pending" = anything with an outstanding balance that isn't cancelled -
				// balance (not just status) is the actual financial source of truth.
				$result = $adb->pquery(
					"SELECT i.subject, a.accountname, i.invoicestatus, i.total, i.received, i.balance, i.duedate
					 FROM vtiger_invoice i
					 INNER JOIN vtiger_crmentity ce ON ce.crmid = i.invoiceid AND ce.deleted = 0
					 LEFT JOIN vtiger_account a ON a.accountid = i.accountid
					 WHERE i.balance > 0 AND i.invoicestatus != 'Cancel'
					 ORDER BY i.duedate ASC, i.balance DESC",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'activity_by_salesperson':
				$result = $adb->pquery(
					"SELECT COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.user_name) AS salesperson,
					        a.activitytype,
					        COUNT(*) AS activity_count
					 FROM vtiger_activity a
					 INNER JOIN vtiger_crmentity ce ON ce.crmid = a.activityid AND ce.deleted = 0
					 LEFT JOIN vtiger_users u ON u.id = ce.smownerid
					 GROUP BY ce.smownerid, a.activitytype
					 ORDER BY salesperson ASC, activity_count DESC",
					array()
				);
				return self::rowsAsArray($adb, $result);

			case 'leads_by_status':
				$result = $adb->pquery(
					"SELECT l.leadstatus, COUNT(*) AS lead_count
					 FROM vtiger_leaddetails l
					 INNER JOIN vtiger_crmentity ce ON ce.crmid = l.leadid AND ce.deleted = 0
					 GROUP BY l.leadstatus
					 ORDER BY lead_count DESC",
					array()
				);
				return self::rowsAsArray($adb, $result);

			default:
				throw new Exception('Unknown report: ' . $reportName);
		}
	}

	private static function rowsAsArray($adb, $result) {
		$rows = array();
		while ($row = $adb->fetchByAssoc($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}
