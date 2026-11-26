<?php
/*+***********************************************************************************
 * WhatsAppNotifier - Meta WhatsApp Cloud API provider.
 *
 * Lightweight, single-provider send client. Loads the active config row from
 * vtiger_whatsappnotifier_servers (decrypting the access token), sends either a
 * pre-approved template message (for business-initiated / proactive sends) or a
 * free-form text message (only valid inside an active 24h customer session), and
 * logs every attempt to vtiger_whatsappnotifier_log.
 *************************************************************************************/

require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';
include_once 'include/Zend/Json.php';

class WhatsAppNotifier_MetaCloudAPIProvider_Model {

	const API_VERSION = 'v19.0';
	const API_BASE_URL = 'https://graph.facebook.com';

	private $phoneNumberId;
	private $accessToken;
	private $businessAccountId;
	private $verifyToken;

	/**
	 * Function to get an instance of the provider, initialised with the currently
	 * active configuration row.
	 * @return WhatsAppNotifier_MetaCloudAPIProvider_Model
	 * @throws Exception if no active configuration exists
	 */
	public static function getInstance() {
		WhatsAppNotifier_Setup::createTablesIfNeeded();

		$adb = PearDatabase::getInstance();
		$result = $adb->pquery('SELECT * FROM vtiger_whatsappnotifier_servers WHERE isactive = 1 LIMIT 1', array());

		if (!$result || !$adb->num_rows($result)) {
			throw new Exception('WhatsApp provider not configured');
		}

		$row = $adb->query_result_rowdata($result, 0);

		$instance = new self();
		$instance->phoneNumberId = $row['phone_number_id'];
		$instance->accessToken = Vtiger_Functions::fromProtectedText($row['access_token']);
		$instance->businessAccountId = $row['business_account_id'];
		$instance->verifyToken = $row['verify_token'];

		return $instance;
	}

	/**
	 * Function to send a pre-approved template message (usable outside an active
	 * 24h customer session window).
	 * @param String $toNumber E.164 number without the leading '+' (e.g. 15551234567)
	 * @param String $templateName Name of the pre-approved template in Meta Business Manager
	 * @param Array $bodyParams List of text parameters to fill the template body placeholders
	 * @param String $languageCode Template language code (default en_US)
	 * @return Array ['success'=>bool, 'message_id'=>?, 'error'=>?]
	 */
	public function sendTemplate($toNumber, $templateName, array $bodyParams = array(), $languageCode = 'en_US', $relatedCrmId = null, $relatedModule = null) {
		$components = array();
		if (!empty($bodyParams)) {
			$parameters = array();
			foreach ($bodyParams as $param) {
				$parameters[] = array('type' => 'text', 'text' => (string) $param);
			}
			$components[] = array('type' => 'body', 'parameters' => $parameters);
		}

		$template = array(
			'name' => $templateName,
			'language' => array('code' => $languageCode),
		);
		if (!empty($components)) {
			$template['components'] = $components;
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'to' => $toNumber,
			'type' => 'template',
			'template' => $template,
		);

		$messageBody = 'template:' . $templateName . ' params:' . Zend_Json::encode($bodyParams);

		return $this->dispatch($payload, $toNumber, $templateName, $messageBody, $relatedCrmId, $relatedModule);
	}

	/**
	 * Function to send a free-form text message. Only valid inside an active 24h
	 * customer session (e.g. replying from the live-chat feature) - not usable for
	 * proactive / business-initiated sends.
	 * @param String $toNumber E.164 number without the leading '+'
	 * @param String $body Message text
	 * @return Array ['success'=>bool, 'message_id'=>?, 'error'=>?]
	 */
	public function sendText($toNumber, $body) {
		$payload = array(
			'messaging_product' => 'whatsapp',
			'to' => $toNumber,
			'type' => 'text',
			'text' => array('body' => $body),
		);

		return $this->dispatch($payload, $toNumber, null, $body);
	}

	/**
	 * Function to POST the message payload to the Graph API and log the attempt.
	 * @param Array $payload
	 * @param String $toNumber
	 * @param String|null $templateName
	 * @param String $messageBody Rendered content being sent, for audit logging
	 * @return Array ['success'=>bool, 'message_id'=>?, 'error'=>?]
	 */
	private function dispatch(array $payload, $toNumber, $templateName, $messageBody, $relatedCrmId = null, $relatedModule = null) {
		$url = self::API_BASE_URL . '/' . self::API_VERSION . '/' . $this->phoneNumberId . '/messages';
		$jsonPayload = Zend_Json::encode($payload);

		$success = false;
		$messageId = null;
		$errorMessage = null;

		$curlHandle = curl_init($url);
		curl_setopt($curlHandle, CURLOPT_POST, true);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $this->accessToken,
			'Content-Type: application/json',
		));
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, 30);

		$responseBody = curl_exec($curlHandle);
		$curlError = curl_error($curlHandle);
		curl_close($curlHandle);

		if ($responseBody === false) {
			$errorMessage = 'cURL error: ' . $curlError;
		} else {
			$responseData = Zend_Json::decode($responseBody, Zend_Json::TYPE_ARRAY);
			if (is_array($responseData) && isset($responseData['messages'][0]['id'])) {
				$success = true;
				$messageId = $responseData['messages'][0]['id'];
			} elseif (is_array($responseData) && isset($responseData['error']['message'])) {
				$errorMessage = $responseData['error']['message'];
			} else {
				$errorMessage = 'Unexpected response from WhatsApp API: ' . $responseBody;
			}
		}

		$this->logAttempt($toNumber, $templateName, $messageBody, $messageId, $success ? 'sent' : 'failed', $errorMessage, $relatedCrmId, $relatedModule);

		if (!$success) {
			return array('success' => false, 'message_id' => null, 'error' => $errorMessage);
		}
		return array('success' => true, 'message_id' => $messageId, 'error' => null);
	}

	/**
	 * Function to write a row to vtiger_whatsappnotifier_log for every send attempt.
	 */
	private function logAttempt($toNumber, $templateName, $messageBody, $messageId, $status, $errorMessage, $relatedCrmId = null, $relatedModule = null) {
		$adb = PearDatabase::getInstance();
		$adb->pquery(
			'INSERT INTO vtiger_whatsappnotifier_log
				(to_number, template_name, message_body, provider_message_id, status, error_message, created_time, related_crmid, related_module)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array($toNumber, $templateName, $messageBody, $messageId, $status, $errorMessage, date('Y-m-d H:i:s'), $relatedCrmId, $relatedModule)
		);
	}
}
