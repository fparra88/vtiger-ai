/*+***********************************************************************************
 * Minimal floating chat panel for the AI Assistant. Loaded via a HEADERSCRIPT
 * link (see setup_ai_chat_links.php), which vtiger loads on every single page
 * regardless of module/view - so this script injects its own launcher button
 * site-wide rather than depending on a per-module DETAILVIEW link.
 * Deliberately plain jQuery - no extra UI framework - since this is a single
 * small widget, mirroring how modules/SMSNotifier/SMSNotifierCommon.js keeps
 * its own send-SMS wizard self-contained.
 *************************************************************************************/

var AIAssistantCommon = {

	panelId: 'aiAssistantChatPanel',
	launcherId: 'aiAssistantLauncherBtn',

	// Conversation memory: the server keeps the history (keyed by this opaque id);
	// sessionStorage only keeps the id and the visible transcript so the chat
	// survives page navigation inside the same browser tab.
	storageKeyId: 'aiAssistantConversationId',
	storageKeyLog: 'aiAssistantTranscript',

	getConversationId: function() {
		var id = '';
		try { id = sessionStorage.getItem(this.storageKeyId) || ''; } catch (e) {}
		if (!id) {
			id = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
			try { sessionStorage.setItem(this.storageKeyId, id); } catch (e) {}
		}
		return id;
	},

	resetConversation: function() {
		try {
			sessionStorage.removeItem(this.storageKeyId);
			sessionStorage.removeItem(this.storageKeyLog);
		} catch (e) {}
		jQuery('#aiAssistantChatMessages').empty();
		jQuery('#aiAssistantChatError').hide();
	},

	loadTranscript: function() {
		try { return JSON.parse(sessionStorage.getItem(this.storageKeyLog) || '[]'); } catch (e) { return []; }
	},

	saveTranscript: function(who, text) {
		var log = this.loadTranscript();
		log.push({ who: who, text: text });
		try { sessionStorage.setItem(this.storageKeyLog, JSON.stringify(log.slice(-40))); } catch (e) {}
	},

	/**
	 * Called once on every page load. Asks the server whether the current
	 * user is allowed to use the assistant (role/enable_chat check - see
	 * AIAssistant_Permission_Action) and, if so, injects a small floating
	 * launcher button site-wide. This is a UX convenience only: the actual
	 * permission boundary is re-checked server-side on every message in
	 * AIAssistant_Chat_Action, independently of this check.
	 */
	init: function() {
		if (jQuery('#' + this.launcherId).length) {
			return;
		}
		jQuery.get('index.php', { module: 'AIAssistant', action: 'Permission' }, function(response) {
			if (response && response.success && response.result && response.result.allowed) {
				AIAssistantCommon.renderLauncher();
			}
		}, 'json');
	},

	renderLauncher: function() {
		if (jQuery('#' + this.launcherId).length) {
			return;
		}
		var btn = jQuery(
			'<div id="' + this.launcherId + '" title="AI Assistant" style="position:fixed; bottom:20px; right:20px; ' +
			'width:48px; height:48px; border-radius:50%; background:#f04e23; color:#fff; ' +
			'display:flex; align-items:center; justify-content:center; cursor:pointer; ' +
			'box-shadow:0 2px 8px rgba(0,0,0,0.3); z-index:99998; font-size:20px;">AI</div>'
		);
		btn.on('click', function() {
			var moduleName = (typeof app !== 'undefined' && app.getModuleName) ? app.getModuleName() : '';
			var recordId = (typeof app !== 'undefined' && app.view && app.view() === 'Detail' && app.getRecordId) ? app.getRecordId() : '';
			AIAssistantCommon.openChat(moduleName, recordId);
		});
		jQuery('body').append(btn);
	},

	openChat: function(moduleName, recordId) {
		if (jQuery('#' + this.panelId).length) {
			jQuery('#' + this.panelId).toggle();
			return;
		}

		var panel = jQuery(
			'<div id="' + this.panelId + '" style="position:fixed; bottom:20px; right:20px; width:380px; ' +
			'height:60vh; max-height:720px; min-height:420px; background:#fff; border:1px solid #ccc; border-radius:6px; ' +
			'box-shadow:0 2px 10px rgba(0,0,0,0.2); z-index:99999; display:flex; flex-direction:column; font-size:13px;">' +
				'<div style="background:#f04e23; color:#fff; padding:8px 12px; border-radius:6px 6px 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">' +
					'<span>AI Assistant</span>' +
					'<span><span id="aiAssistantChatNew" title="New conversation" style="cursor:pointer; margin-right:12px; font-size:12px; text-decoration:underline;">Nueva</span>' +
					'<span id="aiAssistantChatClose" style="cursor:pointer;">&times;</span></span>' +
				'</div>' +
				'<div id="aiAssistantChatMessages" style="flex:1 1 auto; overflow-y:auto; padding:10px;"></div>' +
				'<div id="aiAssistantChatError" style="color:#b94a48; padding:0 10px; display:none; flex-shrink:0;"></div>' +
				'<div style="display:flex; border-top:1px solid #eee; padding:8px; flex-shrink:0;">' +
					'<input type="text" id="aiAssistantChatInput" placeholder="Ask something..." style="flex:1; border:1px solid #ddd; border-radius:3px; padding:5px;" />' +
					'<button id="aiAssistantChatSend" class="btn btn-success" style="margin-left:6px;">Send</button>' +
				'</div>' +
			'</div>'
		);

		jQuery('body').append(panel);

		jQuery.each(AIAssistantCommon.loadTranscript(), function(i, entry) {
			AIAssistantCommon.appendMessage(entry.who, entry.text, true);
		});
		jQuery('#aiAssistantChatNew').on('click', function() {
			AIAssistantCommon.resetConversation();
		});

		jQuery('#aiAssistantChatClose').on('click', function() {
			jQuery('#' + AIAssistantCommon.panelId).hide();
		});

		var send = function() {
			var input = jQuery('#aiAssistantChatInput');
			var message = jQuery.trim(input.val());
			if (!message) {
				return;
			}
			input.val('');
			AIAssistantCommon.appendMessage('You', message);
			jQuery('#aiAssistantChatError').hide();
			jQuery('#aiAssistantChatMessages').append('<div id="aiAssistantChatThinking" style="margin-bottom:8px; color:#888;"><em>Pensando...</em></div>');

			jQuery.post('index.php', {
				module: 'AIAssistant',
				action: 'Chat',
				message: message,
				record: recordId,
				sourceModule: moduleName,
				conversation: AIAssistantCommon.getConversationId()
			}, function(response) {
				jQuery('#aiAssistantChatThinking').remove();
				if (response && response.success) {
					AIAssistantCommon.appendMessage('Assistant', response.result.reply);
				} else {
					var errorMessage = (response && response.error && response.error.message) ? response.error.message : 'Something went wrong.';
					jQuery('#aiAssistantChatError').text(errorMessage).show();
				}
			}, 'json').fail(function() {
				jQuery('#aiAssistantChatThinking').remove();
				jQuery('#aiAssistantChatError').text('Request failed. Please try again.').show();
			});
		};

		jQuery('#aiAssistantChatSend').on('click', send);
		jQuery('#aiAssistantChatInput').on('keypress', function(e) {
			if (e.which === 13) {
				send();
			}
		});
	},

	appendMessage: function(who, text, skipSave) {
		var messages = jQuery('#aiAssistantChatMessages');
		var escaped = jQuery('<div>').text(text).html().replace(/\n/g, '<br>');
		messages.append('<div style="margin-bottom:8px;"><strong>' + who + ':</strong> ' + escaped + '</div>');
		messages.scrollTop(messages[0].scrollHeight);
		if (!skipSave) {
			this.saveTranscript(who, text);
		}
	}
};

jQuery(document).ready(function() {
	AIAssistantCommon.init();
});
