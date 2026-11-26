<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 *************************************************************************************/

/**
 * Read-only record search/lookup tools the AI Assistant can call through
 * OpenAI function calling (see Chat.php).
 *
 * Unlike AIAssistant_CrmReports_Model (fixed, global aggregate queries), these
 * tools return individual records, so every lookup goes through vtiger's own
 * QueryGenerator / isPermitted() checks and is therefore scoped to what the
 * *current user* is allowed to see (module access, field access, sharing rules).
 * The module name is validated against an allowlist; no free-form SQL is ever
 * accepted from the model.
 */
class AIAssistant_CrmSearch_Model {

	const MAX_LIMIT = 15;

	/** Modules the assistant may search / read. */
	public static function getSearchableModules() {
		return array('Contacts', 'Accounts', 'Leads', 'Potentials', 'Products', 'Services',
			'Quotes', 'SalesOrder', 'Invoice', 'PurchaseOrder', 'HelpDesk', 'Vendors');
	}

	/** Inventory modules with line items (products/services). */
	public static function getInventoryModules() {
		return array('Quotes', 'SalesOrder', 'Invoice', 'PurchaseOrder');
	}

	private static function assertModuleAllowed($module) {
		if (!in_array($module, self::getSearchableModules(), true)) {
			throw new Exception('Module not allowed: ' . $module);
		}
		$moduleModel = Vtiger_Module_Model::getInstance($module);
		if (!$moduleModel || !$moduleModel->isActive()) {
			throw new Exception('Module not available: ' . $module);
		}
		$privileges = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!$privileges->hasModuleActionPermission($moduleModel->getId(), 'DetailView')) {
			throw new Exception('You do not have access to the ' . $module . ' module.');
		}
		return $moduleModel;
	}

	private static function clampLimit($limit, $default = 10) {
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = $default;
		}
		return min($limit, self::MAX_LIMIT);
	}

	/**
	 * Free-text search over a module's name fields, optionally limited to a
	 * creation date range and/or ordered by total amount (biggest first).
	 */
	public static function searchRecords($module, $text = '', $limit = 10, $orderByTotal = false, $createdFrom = '', $createdTo = '') {
		$moduleModel = self::assertModuleAllowed($module);
		$limit = self::clampLimit($limit);
		$user = Users_Record_Model::getCurrentUserModel();
		$adb = PearDatabase::getInstance();

		$totalField = self::getTotalFieldName($module);
		$fields = array('id');
		if ($orderByTotal && $totalField) {
			$fields[] = $totalField;
		}
		$queryGenerator = new QueryGenerator($module, $user);
		$queryGenerator->setFields($fields);

		$text = trim((string) $text);
		$hasCondition = false;
		if ($text !== '') {
			$nameFields = array_values(array_filter($moduleModel->getNameFields()));
			if (empty($nameFields)) {
				throw new Exception('This module cannot be searched by name.');
			}
			$queryGenerator->startGroup('');
			foreach ($nameFields as $i => $fieldName) {
				$queryGenerator->addCondition($fieldName, $text, 'c', $i === 0 ? null : QueryGenerator::$OR);
			}
			$queryGenerator->endGroup();
			$hasCondition = true;
		}

		$from = self::normalizeDate($createdFrom);
		$to = self::normalizeDate($createdTo);
		if ($from && $to) {
			if ($hasCondition) {
				$queryGenerator->addConditionGlue(QueryGenerator::$AND);
			}
			$queryGenerator->addCondition('createdtime', $from . ' 00:00:00,' . $to . ' 23:59:59', 'bw');
		}

		$query = $queryGenerator->getQuery();
		$orderBy = 'vtiger_crmentity.modifiedtime DESC';
		if ($orderByTotal && $totalField) {
			$column = self::getTotalColumn($module);
			if ($column) {
				$orderBy = $column . ' DESC';
			}
		}
		$query .= ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $limit;

		$result = $adb->pquery($query, array());
		$ids = array();
		// The first selected column is always the record id (its alias differs per module).
		for ($i = 0, $n = $adb->num_rows($result); $i < $n; $i++) {
			$ids[] = $adb->query_result($result, $i, 0);
		}

		$records = array();
		foreach ($ids as $id) {
			$summary = self::describeRecord($module, $id, 12);
			if ($summary) {
				$records[] = $summary;
			}
		}
		return array('module' => $module, 'count' => count($records), 'records' => $records);
	}

	/**
	 * Find Quotes/SalesOrder/Invoice/PurchaseOrder whose *line items* mention a
	 * product or service (e.g. "cotizaciones de varilla").
	 */
	public static function searchByProduct($module, $productText, $limit = 10) {
		if (!in_array($module, self::getInventoryModules(), true)) {
			throw new Exception('Module must be one of: ' . implode(', ', self::getInventoryModules()));
		}
		self::assertModuleAllowed($module);
		$productText = trim((string) $productText);
		if ($productText === '') {
			throw new Exception('product_text is required.');
		}
		$limit = self::clampLimit($limit);
		$adb = PearDatabase::getInstance();
		$like = '%' . $productText . '%';

		$result = $adb->pquery(
			"SELECT DISTINCT ipr.id
			 FROM vtiger_inventoryproductrel ipr
			 INNER JOIN vtiger_crmentity ce ON ce.crmid = ipr.id AND ce.deleted = 0 AND ce.setype = ?
			 LEFT JOIN vtiger_products p ON p.productid = ipr.productid
			 LEFT JOIN vtiger_service s ON s.serviceid = ipr.productid
			 WHERE p.productname LIKE ? OR s.servicename LIKE ?
			 ORDER BY ce.modifiedtime DESC
			 LIMIT " . ((int) $limit * 3),
			array($module, $like, $like)
		);

		$records = array();
		while ($row = $adb->fetchByAssoc($result)) {
			if (count($records) >= $limit) {
				break;
			}
			$summary = self::describeRecord($module, $row['id'], 12);
			if ($summary) {
				$summary['matching_line_items'] = self::getLineItems($row['id'], $productText);
				$records[] = $summary;
			}
		}
		return array('module' => $module, 'product_text' => $productText, 'count' => count($records), 'records' => $records);
	}

	/** Full read of one record the current user can see (+ line items for inventory modules). */
	public static function getRecord($module, $id) {
		self::assertModuleAllowed($module);
		$id = (int) $id;
		$summary = self::describeRecord($module, $id, 60);
		if (!$summary) {
			throw new Exception('Record not found or not accessible.');
		}
		if (in_array($module, self::getInventoryModules(), true)) {
			$summary['line_items'] = self::getLineItems($id, '');
		}
		return $summary;
	}

	/**
	 * Compact, permission-checked description of a record: id, label and the
	 * non-empty fields the current user may view. Returns null when the record
	 * does not exist or the user is not allowed to see it.
	 */
	public static function describeRecord($module, $id, $maxFields = 12) {
		// The id must really belong to $module (otherwise isPermitted() below would be
		// evaluated against the wrong module's permissions).
		if (!in_array($module, self::getSearchableModules(), true) || getSalesEntityType($id) !== $module) {
			return null;
		}
		if (!Users_Privileges_Model::isPermitted($module, 'DetailView', $id)) {
			return null;
		}
		try {
			$record = Vtiger_Record_Model::getInstanceById($id, $module);
		} catch (Exception $e) {
			return null;
		}
		$moduleModel = $record->getModule();
		$nameFields = $moduleModel->getNameFields();
		$summaryFields = array_keys($moduleModel->getSummaryViewFieldsList());
		$preferred = array_merge($nameFields, $summaryFields);

		$all = $moduleModel->getFields();
		$ordered = array();
		foreach ($preferred as $name) {
			if (isset($all[$name])) {
				$ordered[$name] = $all[$name];
			}
		}
		foreach ($all as $name => $fieldModel) {
			if (!isset($ordered[$name])) {
				$ordered[$name] = $fieldModel;
			}
		}

		$data = array();
		foreach ($ordered as $name => $fieldModel) {
			if (count($data) >= $maxFields) {
				break;
			}
			if (!$fieldModel->isViewable() || in_array((int) $fieldModel->get('uitype'), array(99, 106, 1013), true)) {
				continue;
			}
			$raw = $record->get($name);
			if ($raw === null || $raw === '' || $raw === '0' && in_array($fieldModel->getFieldDataType(), array('reference', 'owner'), true)) {
				continue;
			}
			try {
				$display = $record->getDisplayValue($name, $id);
			} catch (Exception $e) {
				$display = $raw;
			}
			$display = trim(html_entity_decode(strip_tags((string) $display), ENT_QUOTES, 'UTF-8'));
			if ($display === '') {
				continue;
			}
			if (mb_strlen($display) > 300) {
				$display = mb_substr($display, 0, 300) . '...';
			}
			$data[html_entity_decode(vtranslate($fieldModel->get('label'), $module), ENT_QUOTES, 'UTF-8')] = $display;
		}

		return array(
			'id' => (int) $id,
			'module' => $module,
			'name' => $record->getName(),
			'fields' => $data,
		);
	}

	private static function getLineItems($id, $filterText = '') {
		$adb = PearDatabase::getInstance();
		$result = $adb->pquery(
			"SELECT COALESCE(p.productname, s.servicename) AS item, ipr.quantity, ipr.listprice,
			        ipr.discount_percent, ipr.discount_amount, ipr.comment
			 FROM vtiger_inventoryproductrel ipr
			 LEFT JOIN vtiger_products p ON p.productid = ipr.productid
			 LEFT JOIN vtiger_service s ON s.serviceid = ipr.productid
			 WHERE ipr.id = ?
			 ORDER BY ipr.sequence_no ASC
			 LIMIT 40",
			array($id)
		);
		$items = array();
		while ($row = $adb->fetchByAssoc($result)) {
			if ($filterText !== '' && stripos((string) $row['item'], $filterText) === false) {
				continue;
			}
			$items[] = array(
				'item' => $row['item'],
				'quantity' => (float) $row['quantity'],
				'unit_price' => (float) $row['listprice'],
				'line_total' => round((float) $row['quantity'] * (float) $row['listprice'], 2),
			);
		}
		return $items;
	}

	private static function getTotalFieldName($module) {
		if (in_array($module, self::getInventoryModules(), true)) {
			return 'hdnGrandTotal';
		}
		if ($module === 'Potentials') {
			return 'amount';
		}
		return null;
	}

	private static function getTotalColumn($module) {
		$map = array(
			'Quotes' => 'vtiger_quotes.total',
			'SalesOrder' => 'vtiger_salesorder.total',
			'Invoice' => 'vtiger_invoice.total',
			'PurchaseOrder' => 'vtiger_purchaseorder.total',
			'Potentials' => 'vtiger_potential.amount',
		);
		return isset($map[$module]) ? $map[$module] : null;
	}

	private static function normalizeDate($value) {
		$value = trim((string) $value);
		if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return '';
		}
		return $value;
	}
}
