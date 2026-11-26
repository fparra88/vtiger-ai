<?php
/*+***********************************************************************************
 * Standalone webhook endpoint for Meta's WhatsApp Cloud API. Reachable directly at
 * this file's URL (e.g. https://your-public-host/modules/WhatsAppNotifier/callbacks/MetaWebhook.php)
 * - NOT routed through index.php, since Meta calls this with no vtiger session.
 * Mirrors the self-bootstrapping pattern of modules/SMSNotifier/callbacks/ClickATellNew.php
 * and the standalone-script style of shorturl.php/webservice.php.
 *
 * Handles:
 *  - GET  : Meta's one-time webhook verification handshake (hub.mode/hub.verify_token/hub.challenge)
 *  - POST : inbound message + delivery-status notifications
 *
 * Requires a public HTTPS URL to actually receive traffic from Meta (e.g. via an
 * ngrok tunnel in local development) - see the project plan for details. This
 * file's own logic can be exercised locally over plain HTTP regardless.
 *************************************************************************************/

// Apache sets PHP's cwd to this script's own directory, but config.php (and
// files it includes) use relative requires that assume cwd is the repo root -
// chdir first so those resolve correctly (same reason vtigercron.php/webservice.php
// don't need this: they already live at the repo root).
chdir(dirname(__FILE__) . '/../../../');

include_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/database/PearDatabase.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Functions.php';
require_once 'modules/WhatsAppNotifier/WhatsAppNotifierSetup.php';

WhatsAppNotifier_Setup::createTablesIfNeeded();

function whatsapp_webhook_get_active_config() {
	$adb = PearDatabase::getInstance();
	$result = $adb->pquery('SELECT * FROM vtiger_whatsappnotifier_servers WHERE isactive = 1 LIMIT 1', array());
	if ($result && $adb->num_rows($result)) {
		return $adb->query_result_rowdata($result, 0);
	}
	return false;
}

function whatsapp_webhook_log_inbound($fromNumber, $messageId, $body) {
	$adb = PearDatabase::getInstance();
	$adb->pquery(
		"INSERT INTO vtiger_whatsappnotifier_log (direction, from_number, message_body, provider_message_id, status, created_time)
		 VALUES ('inbound', ?, ?, ?, 'received', ?)",
		array($fromNumber, $body, $messageId, date('Y-m-d H:i:s'))
	);
}

function whatsapp_webhook_update_status($messageId, $status) {
	$adb = PearDatabase::getInstance();
	$adb->pquery(
		'UPDATE vtiger_whatsappnotifier_log SET status = ? WHERE provider_message_id = ?',
		array($status, $messageId)
	);
}

$config = whatsapp_webhook_get_active_config();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	// Meta's verification handshake: https://developers.facebook.com/docs/graph-api/webhooks/getting-started
	$mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : null;
	$token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : null;
	$challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : null;

	$configuredToken = $config ? $config['verify_token'] : null;

	if ($mode === 'subscribe' && $configuredToken && hash_equals((string) $configuredToken, (string) $token)) {
		header('Content-Type: text/plain');
		echo $challenge;
		exit;
	}

	http_response_code(403);
	echo 'Verification failed';
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$rawBody = file_get_contents('php://input');

	if ($config && !empty($config['app_secret'])) {
		$appSecret = Vtiger_Functions::fromProtectedText($config['app_secret']);
		$signatureHeader = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';
		$expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

		if (!hash_equals($expectedSignature, (string) $signatureHeader)) {
			http_response_code(403);
			echo 'Invalid signature';
			exit;
		}
	}
	// If no app_secret is configured yet, signature verification is skipped so the
	// endpoint remains usable while the admin is still setting things up - but this
	// should be configured before relying on this endpoint in production.

	$payload = json_decode($rawBody, true);

	if (is_array($payload) && !empty($payload['entry'])) {
		foreach ($payload['entry'] as $entry) {
			if (empty($entry['changes'])) {
				continue;
			}
			foreach ($entry['changes'] as $change) {
				$value = isset($change['value']) ? $change['value'] : array();

				if (!empty($value['messages'])) {
					foreach ($value['messages'] as $message) {
						$from = isset($message['from']) ? $message['from'] : null;
						$messageId = isset($message['id']) ? $message['id'] : null;
						$body = isset($message['text']['body']) ? $message['text']['body'] : '[' . ($message['type'] ?? 'non-text') . ' message]';
						whatsapp_webhook_log_inbound($from, $messageId, $body);
					}
				}

				if (!empty($value['statuses'])) {
					foreach ($value['statuses'] as $statusUpdate) {
						$messageId = isset($statusUpdate['id']) ? $statusUpdate['id'] : null;
						$status = isset($statusUpdate['status']) ? $statusUpdate['status'] : null;
						if ($messageId && $status) {
							whatsapp_webhook_update_status($messageId, $status);
						}
					}
				}
			}
		}
	}

	// Meta expects a fast 200 OK regardless of internal processing outcome -
	// returning anything else (or timing out) causes it to retry and can
	// eventually disable the webhook subscription.
	http_response_code(200);
	echo 'OK';
	exit;
}

http_response_code(405);
echo 'Method not allowed';
