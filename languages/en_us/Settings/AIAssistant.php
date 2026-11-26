<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 *************************************************************************************/

$languageStrings = array(
	'LBL_AI_ASSISTANT_SETTINGS' => 'AI Assistant Settings',
	'LBL_AI_ASSISTANT_SETTINGS_DESC' => 'Configure the OpenAI integration used by the conversational assistant, lead/case scoring, and WhatsApp message personalization.',
	'LBL_OPENAI_CONFIGURATION' => 'OpenAI Configuration',
	'LBL_API_KEY' => 'API Key',
	'LBL_MODEL' => 'Model',
	'LBL_SYSTEM_PROMPT' => 'System Prompt',
	'LBL_SYSTEM_PROMPT_DESC' => 'Describe your business so the assistant answers in context: what the company does, products/services, currency and country, tone, language, and any rules. The CRM tools, today\'s date, the current user and the open record are added automatically - do not repeat them here. Leave empty to use a neutral default. Max 6000 characters.',
	'LBL_SYSTEM_PROMPT_PLACEHOLDER' => 'Example: You are the assistant of ACME, a company that sells ... in ... Prices are in ... Always answer in ... with a ... tone.',
	'LBL_SYSTEM_PROMPT_TOO_LONG' => 'The system prompt is too long (maximum 6000 characters).',
	'LBL_MODEL_CUSTOM' => 'Other (type a model name)...',
	'LBL_MODEL_CUSTOM_PLACEHOLDER' => 'e.g. gpt-4.1',
	'LBL_MODEL_DESC' => 'OpenAI model used by the chat, scoring and WhatsApp personalization. Larger models answer better but cost more per message; the new model applies from the next message.',
	'LBL_ENABLE_CHAT' => 'Enable Conversational Assistant',
	'LBL_ALLOWED_ROLES' => 'Allowed Roles',
	'LBL_ALLOWED_ROLES_DESC' => 'Restrict the conversational assistant to the checked roles only. Leave every role unchecked to allow all users (default). Admin users can always use it.',
	'LBL_ENABLE_SCORING' => 'Enable Lead/Case Scoring',
	'LBL_ENABLE_WHATSAPP_PERSONALIZE' => 'Enable WhatsApp Message Personalization',
	'LBL_SCORING_MODULES' => 'Scoring Modules (comma separated)',
	'LBL_SCORING_BATCH_SIZE' => 'Scoring Batch Size',
	'LBL_LEAVE_BLANK_TO_KEEP_CURRENT' => 'Leave blank to keep current key',
);
