<?php
/*+**********************************************************************************
 * WhatsApp workflow task. Mirrors VTSMSTask.inc's structure: resolves the recipient
 * number and template parameters through VTSimpleTemplate/VTEntityCache merge-field
 * rendering, then sends via WhatsAppNotifier_MetaCloudAPIProvider_Model.
 ************************************************************************************/

require_once('modules/com_vtiger_workflow/VTEntityCache.inc');
require_once('modules/com_vtiger_workflow/VTWorkflowUtils.php');
require_once('modules/com_vtiger_workflow/VTSimpleTemplate.inc');

require_once('modules/WhatsAppNotifier/providers/MetaCloudAPIProvider.php');

class VTWhatsAppTask extends VTTask {
	public $executeImmediately = true;

	public function getFieldNames() {
		return array('whatsapp_recepient', 'template_name', 'param_values', 'use_ai_personalize');
	}

	public function doTask($entity) {

		global $adb, $current_user, $log;

		$util = new VTWorkflowUtils();
		$admin = $util->adminUser();
		$ws_id = $entity->getId();
		$entityCache = new VTEntityCache($admin);

		$rt = new VTSimpleTemplate($this->whatsapp_recepient);
		$recepient = $rt->render($entityCache, $ws_id);
		$recepients = array();
		foreach (explode(',', $recepient) as $tonumber) {
			$tonumber = preg_replace('/[^0-9]/', '', $tonumber);
			if (!empty($tonumber)) {
				$recepients[] = $tonumber;
			}
		}

		if (empty($recepients)) {
			return;
		}

		$relatedCRMid = substr($ws_id, stripos($ws_id, 'x') + 1);
		$relatedModule = $entity->getModuleName();

		$paramValues = array();
		if (!empty($this->param_values)) {
			$pt = new VTSimpleTemplate($this->param_values);
			$renderedParams = $pt->render($entityCache, $ws_id);
			foreach (explode('|', $renderedParams) as $param) {
				$paramValues[] = strip_tags(br2nl($param));
			}
		}

		if ($this->use_ai_personalize == '1' || $this->use_ai_personalize === true) {
			if (class_exists('AIAssistant_Vtlib_Helper') || is_file('modules/AIAssistant/AIAssistantVtlibHelper.php')) {
				require_once('modules/AIAssistant/AIAssistantVtlibHelper.php');
				try {
					$paramValues = AIAssistant_Vtlib_Helper::draftMessage($paramValues, $entity);
				} catch (Exception $e) {
					if ($log) {
						$log->warn('AI personalize failed for WhatsApp task, falling back to template params: ' . $e->getMessage());
					}
				}
			}
		}

		try {
			$provider = WhatsAppNotifier_MetaCloudAPIProvider_Model::getInstance();
			foreach ($recepients as $tonumber) {
				$provider->sendTemplate($tonumber, $this->template_name, $paramValues, 'en_US', $relatedCRMid, $relatedModule);
			}
		} catch (Exception $e) {
			if ($log) {
				$log->warn('VTWhatsAppTask could not send: ' . $e->getMessage());
			}
		}

		$util->revertUser();
	}

	public function getRelatedIdsFromTemplate($template, $entityCache, $entityId) {
		$this->template = $template;
		$this->cache = $entityCache;
		$this->parent = $this->cache->forId($entityId);
		return preg_replace_callback('/\\$(\w+|\((\w+) : \(([_\w]+)\) (\w+)\))/', array($this, "matchHandler"), $this->template);
	}

	public function matchHandler($match) {
		preg_match('/\((\w+) : \(([_\w]+)\) (\w+)\)/', $match[1], $matches);
		$result = '';
		if (!empty($this->parent)) {
			if (php7_count($matches) != 0) {
				list($full, $referenceField, $referenceModule, $fieldname) = $matches;
				$referenceId = $this->parent->get($referenceField);
				if ($referenceModule === "Users" || $referenceId == null) {
					$result = "";
				} else {
					$result = $referenceId;
				}
			}
		}
		return $result;
	}
}
?>
