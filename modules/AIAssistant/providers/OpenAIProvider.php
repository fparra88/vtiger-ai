<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

// Vtiger_Utils / Vtiger_Functions are plain vtlib classes (not under modules/), so
// Vtiger_Loader's naming-convention autoloader does not pick them up. They are
// already loaded as part of a normal web request, but this provider can also be
// used from minimal CLI bootstraps (see test_openai_cli.php), so require them
// explicitly to be safe in both contexts.
require_once 'vtlib/Vtiger/Utils.php';
require_once 'vtlib/Vtiger/Functions.php';

/**
 * Simple wrapper around the OpenAI Chat Completions API.
 *
 * Config is stored in a single row (id=1) of vtiger_aiassistant_config, managed
 * through Settings > AI Assistant (see modules/Settings/AIAssistant/).
 *
 * This is intentionally a single hardcoded-provider implementation (no
 * IAIProvider interface / factory layer) since, unlike SMSNotifier, there is
 * only ever one AI provider for now.
 */
class AIAssistant_OpenAIProvider_Model {

	const CONFIG_TABLE = 'vtiger_aiassistant_config';
	const CONFIG_ID = 1;
	const DEFAULT_MODEL = 'gpt-4o-mini';
	const API_URL = 'https://api.openai.com/v1/chat/completions';
	const MAX_SYSTEM_PROMPT_LENGTH = 6000;
	/** Neutral fallback used until the business defines its own in Settings > AI Assistant. */
	const DEFAULT_SYSTEM_PROMPT = 'You are the AI assistant built into this company\'s CRM. Be clear, concise and professional, and answer in the same language the user writes in.';

	private $apiKey;
	private $model;

	/**
	 * Make sure the config table exists. Mirrors the lazy
	 * "self-create on first use" pattern used by
	 * VTTaskManager::registerTaskType() - no manifest/install step needed.
	 */
	public static function createConfigTableIfRequired() {
		if (!Vtiger_Utils::CheckTable(self::CONFIG_TABLE)) {
			Vtiger_Utils::CreateTable(
				self::CONFIG_TABLE,
				"(id int(11) NOT NULL,
					api_key text,
					model varchar(100) DEFAULT '" . self::DEFAULT_MODEL . "',
					enable_chat tinyint(1) NOT NULL DEFAULT 0,
					enable_scoring tinyint(1) NOT NULL DEFAULT 0,
					enable_whatsapp_personalize tinyint(1) NOT NULL DEFAULT 0,
					scoring_modules varchar(255) DEFAULT '',
					scoring_batch_size int(11) NOT NULL DEFAULT 20,
					allowed_roles text,
					PRIMARY KEY (id))",
				true
			);
		}
		// Upgrade path for instances where the table already existed before
		// allowed_roles was introduced.
		Vtiger_Utils::AddColumn(self::CONFIG_TABLE, 'allowed_roles', 'TEXT');
		Vtiger_Utils::AddColumn(self::CONFIG_TABLE, 'system_prompt', 'TEXT');
	}

	/**
	 * Function to get the list of roleids the admin restricted chat access to.
	 * An empty list means "no restriction configured" - every user who can
	 * already see the module keeps today's behaviour.
	 * @param <Array|null> $config Optional already-fetched raw config row
	 * @return <Array> list of roleid strings
	 */
	public static function getAllowedRolesList($config = null) {
		if ($config === null) {
			$config = self::getRawConfig();
		}
		if (empty($config['allowed_roles'])) {
			return array();
		}
		return array_values(array_filter(array_map('trim', explode(',', $config['allowed_roles']))));
	}

	/**
	 * Function to decide whether the given user is allowed to use the
	 * conversational chat, combining the global enable_chat switch with the
	 * optional per-role restriction set in Settings > AI Assistant.
	 * Admin users always pass, so an admin can never lock themselves out.
	 * @param <Integer> $userId
	 * @param <Boolean> $isAdmin
	 * @return <Boolean>
	 */
	public static function isChatAllowedForUser($userId, $isAdmin = false) {
		$config = self::getRawConfig();
		if (!$config || empty($config['enable_chat'])) {
			return false;
		}
		if ($isAdmin) {
			return true;
		}

		$allowedRoles = self::getAllowedRolesList($config);
		if (empty($allowedRoles)) {
			return true;
		}

		$adb = PearDatabase::getInstance();
		$result = $adb->pquery('SELECT roleid FROM vtiger_user2role WHERE userid = ?', array($userId));
		if (!$result || !$adb->num_rows($result)) {
			return false;
		}
		$userRoleId = $adb->query_result($result, 0, 'roleid');
		return in_array($userRoleId, $allowedRoles, true);
	}

	/**
	 * Function to get the business-defined system prompt (the "main body" describing the
	 * company, tone and domain), falling back to a neutral default when none is saved.
	 * @param <Array|null> $config Optional already-fetched raw config row
	 * @return <String>
	 */
	public static function getSystemPrompt($config = null) {
		if ($config === null) {
			$config = self::getRawConfig();
		}
		$prompt = ($config && isset($config['system_prompt'])) ? trim((string) $config['system_prompt']) : '';
		return $prompt !== '' ? $prompt : self::DEFAULT_SYSTEM_PROMPT;
	}

	/**
	 * Function to get the raw (undecrypted) config row, or false if none saved yet.
	 * @return <Array|false>
	 */
	public static function getRawConfig() {
		self::createConfigTableIfRequired();

		$adb = PearDatabase::getInstance();
		$result = $adb->pquery('SELECT * FROM ' . self::CONFIG_TABLE . ' WHERE id = ?', array(self::CONFIG_ID));
		if ($result && $adb->num_rows($result)) {
			return $adb->query_result_rowdata($result, 0);
		}
		return false;
	}

	/**
	 * Function to get an instance configured from the saved settings.
	 * @return <AIAssistant_OpenAIProvider_Model>
	 */
	public static function getInstance() {
		$instance = new self();

		$config = self::getRawConfig();
		if ($config) {
			$instance->apiKey = Vtiger_Functions::fromProtectedText($config['api_key']);
			$instance->model = !empty($config['model']) ? $config['model'] : self::DEFAULT_MODEL;
		} else {
			$instance->apiKey = '';
			$instance->model = self::DEFAULT_MODEL;
		}
		return $instance;
	}

	/**
	 * Function to send a Chat Completions request to OpenAI, optionally
	 * letting the model call tools (OpenAI function calling) to fetch CRM
	 * data before producing its final answer.
	 *
	 * @param <Array> $messages List of ['role' => 'system'|'user'|'assistant', 'content' => '...']
	 * @param <String> $model Optional model override, defaults to the configured model
	 * @param <Array|null> $tools Optional OpenAI "tools" array (function definitions)
	 * @param <callable|null> $toolExecutor Optional callable($functionName, $argumentsArray) => mixed,
	 *        invoked when the model requests a tool call; its return value is JSON-encoded
	 *        back to the model as the tool result.
	 * @return <String> assistant reply text
	 * @throws Exception on misconfiguration, transport error, API error, or too many tool-call rounds
	 */
	public function chat($messages, $model = null, $tools = null, $toolExecutor = null) {
		if (empty($this->apiKey)) {
			throw new Exception('No API key configured for AI Assistant. Please set it in Settings > AI Assistant.');
		}
		if (empty($messages) || !is_array($messages)) {
			throw new Exception('AIAssistant_OpenAIProvider_Model::chat() requires a non-empty messages array.');
		}

		$useModel = !empty($model) ? $model : $this->model;
		if (empty($useModel)) {
			$useModel = self::DEFAULT_MODEL;
		}

		// A handful of rounds is enough for "call a couple of reports, then
		// answer" - caps runaway tool-call loops rather than looping forever.
		$maxRounds = 6;
		for ($round = 0; $round < $maxRounds; $round++) {
			$requestData = array(
				'model' => $useModel,
				'messages' => $messages,
			);
			if (!empty($tools)) {
				$requestData['tools'] = $tools;
			}

			$requestBody = json_encode($requestData);
			if ($requestBody === false) {
				throw new Exception('Failed to encode request body as JSON: ' . json_last_error_msg());
			}

			$ch = curl_init(self::API_URL);
			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $requestBody,
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'Authorization: Bearer ' . $this->apiKey,
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_SSL_VERIFYPEER => true,
			));

			$responseBody = curl_exec($ch);
			$curlErrno = curl_errno($ch);
			$curlError = curl_error($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($curlErrno !== 0) {
				throw new Exception('HTTP request to OpenAI failed: ' . $curlError);
			}

			$decoded = json_decode($responseBody, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception('OpenAI returned a malformed JSON response (HTTP ' . $httpCode . '): ' . json_last_error_msg());
			}

			if (isset($decoded['error'])) {
				$apiMessage = is_array($decoded['error']) && isset($decoded['error']['message'])
					? $decoded['error']['message']
					: json_encode($decoded['error']);
				throw new Exception('OpenAI API error (HTTP ' . $httpCode . '): ' . $apiMessage);
			}

			if ($httpCode < 200 || $httpCode >= 300) {
				throw new Exception('OpenAI API returned HTTP ' . $httpCode . ': ' . substr((string)$responseBody, 0, 500));
			}

			if (!isset($decoded['choices'][0]['message'])) {
				throw new Exception('OpenAI response did not contain an assistant message. Raw response: ' . substr((string)$responseBody, 0, 500));
			}

			$assistantMessage = $decoded['choices'][0]['message'];

			if (!empty($assistantMessage['tool_calls']) && is_callable($toolExecutor)) {
				$messages[] = $assistantMessage;
				foreach ($assistantMessage['tool_calls'] as $toolCall) {
					$functionName = isset($toolCall['function']['name']) ? $toolCall['function']['name'] : '';
					$rawArguments = isset($toolCall['function']['arguments']) ? $toolCall['function']['arguments'] : '{}';
					$arguments = json_decode($rawArguments, true);
					if (!is_array($arguments)) {
						$arguments = array();
					}

					try {
						$toolResult = call_user_func($toolExecutor, $functionName, $arguments);
						$toolResultJson = json_encode($toolResult);
					} catch (Exception $e) {
						$toolResultJson = json_encode(array('error' => $e->getMessage()));
					}

					$messages[] = array(
						'role' => 'tool',
						'tool_call_id' => $toolCall['id'],
						'content' => ($toolResultJson !== false) ? $toolResultJson : '{"error":"Failed to encode tool result."}',
					);
				}
				continue; // Ask the model again with the tool results appended.
			}

			if (!isset($assistantMessage['content'])) {
				throw new Exception('OpenAI response did not contain assistant text content. Raw response: ' . substr((string)$responseBody, 0, 500));
			}

			return $assistantMessage['content'];
		}

		throw new Exception('AI Assistant exceeded the maximum number of tool-call rounds.');
	}
}
