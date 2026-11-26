{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{* modules/Settings/AIAssistant/views/Edit.php *}

{strip}
	<div class="editViewPageDiv editViewContainer" id="EditViewAIAssistant" style="padding-top:0px;">
		<div class="col-lg-12 col-md-12 col-sm-12">
			<div>
				<h3 style="margin-top: 0px;">{vtranslate('LBL_AI_ASSISTANT_SETTINGS', $QUALIFIED_MODULE_NAME)}</h3>
				{vtranslate('LBL_AI_ASSISTANT_SETTINGS_DESC', $QUALIFIED_MODULE_NAME)}
			</div>
			<div id="aiAssistantSaveMessage" class="alert" style="display:none;"></div>
			<form id="aiAssistantConfigForm" method="POST">
				<div class="blockData">
					<br>
					<div class="block">
						<h4>{vtranslate('LBL_OPENAI_CONFIGURATION', $QUALIFIED_MODULE_NAME)}</h4>
						<hr>
						<table class="table editview-table no-border">
							<tbody>
								{foreach item=FIELD_MODEL from=$EDITABLE_FIELDS}
									{assign var=FIELD_NAME value=$FIELD_MODEL->get('name')}
									{assign var=FIELD_TYPE value=$FIELD_MODEL->getFieldDataType()}
									{assign var=FIELD_LABEL value=$FIELD_MODEL->get('label')}
									<tr>
										<td class="fieldLabel"><label for="{$FIELD_NAME}">{vtranslate($FIELD_LABEL, $QUALIFIED_MODULE_NAME)}</label></td>
										<td class="fieldValue">
											<div class="col-lg-6 col-md-6 col-sm-12">
												{if $FIELD_TYPE == 'password'}
													<input type="password" autocomplete="new-password" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" placeholder="{vtranslate('LBL_LEAVE_BLANK_TO_KEEP_CURRENT', $QUALIFIED_MODULE_NAME)}" value="{$RECORD_MODEL->getDisplayValue($FIELD_NAME)}" />
												{elseif $FIELD_TYPE == 'checkbox'}
													<input type="checkbox" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="1" {if $RECORD_MODEL->get($FIELD_NAME)}checked{/if} />
												{elseif $FIELD_TYPE == 'textarea'}
													<textarea class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" rows="10" maxlength="6000" style="width:100%; min-height:180px;" placeholder="{vtranslate('LBL_SYSTEM_PROMPT_PLACEHOLDER', $QUALIFIED_MODULE_NAME)}">{$RECORD_MODEL->get($FIELD_NAME)|escape:'html'}</textarea>
													<p class="help-block">{vtranslate('LBL_SYSTEM_PROMPT_DESC', $QUALIFIED_MODULE_NAME)}</p>
												{elseif $FIELD_TYPE == 'number'}
													<input type="number" min="1" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="{$RECORD_MODEL->get($FIELD_NAME)}" />
												{elseif $FIELD_TYPE == 'rolemultiselect'}
													<div>
														{foreach item=ROLE from=$ALL_ROLES}
															<label style="display:block; font-weight:normal; margin-left:{$ROLE.indent}px;">
																<input type="checkbox" name="{$FIELD_NAME}[]" value="{$ROLE.roleid}" {if $ROLE.checked}checked{/if} />
																{$ROLE.rolename}
															</label>
														{/foreach}
													</div>
													<p class="help-block">{vtranslate('LBL_ALLOWED_ROLES_DESC', $QUALIFIED_MODULE_NAME)}</p>
												{elseif $FIELD_NAME == 'model'}
													{assign var=CURRENT_MODEL value=$RECORD_MODEL->get($FIELD_NAME)}
													{assign var=MODEL_IS_KNOWN value=false}
													<select class="inputElement form-control" id="aiAssistantModelSelect">
														{foreach key=MODEL_ID item=MODEL_LABEL from=$COMMON_MODELS}
															{if $MODEL_ID == $CURRENT_MODEL}{assign var=MODEL_IS_KNOWN value=true}{/if}
															<option value="{$MODEL_ID}" {if $MODEL_ID == $CURRENT_MODEL}selected{/if}>{$MODEL_LABEL}</option>
														{/foreach}
														<option value="__custom__" {if !$MODEL_IS_KNOWN}selected{/if}>{vtranslate('LBL_MODEL_CUSTOM', $QUALIFIED_MODULE_NAME)}</option>
													</select>
													<input type="text" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="{$CURRENT_MODEL}" style="margin-top:6px; {if $MODEL_IS_KNOWN}display:none;{/if}" placeholder="{vtranslate('LBL_MODEL_CUSTOM_PLACEHOLDER', $QUALIFIED_MODULE_NAME)}" />
													<p class="help-block">{vtranslate('LBL_MODEL_DESC', $QUALIFIED_MODULE_NAME)}</p>
												{else}
													<input type="text" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="{$RECORD_MODEL->get($FIELD_NAME)}" />
												{/if}
											</div>
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
					<br>
					<div class='modal-overlay-footer clearfix'>
						<div class="row clearfix">
							<div class='textAlignCenter col-lg-12 col-md-12 col-sm-12'>
								<button type="submit" class="btn btn-success saveButton" id="aiAssistantSaveButton">{vtranslate('LBL_SAVE', $QUALIFIED_MODULE_NAME)}</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('#aiAssistantModelSelect').on('change', function() {
				var modelInput = jQuery('#model');
				if (this.value === '__custom__') {
					modelInput.show().focus();
				} else {
					modelInput.val(this.value).hide();
				}
			});
			jQuery('#aiAssistantConfigForm').on('submit', function(e) {
				e.preventDefault();
				var form = jQuery(this);
				var messageBox = jQuery('#aiAssistantSaveMessage');
				var params = form.serializeArray();
				var postData = { module: 'AIAssistant', parent: 'Settings', action: 'SaveAjax' };
				jQuery.each(params, function(i, field) {
					postData[field.name] = field.value;
				});
				// Explicitly include unchecked checkboxes as 0 so the server can tell
				// "off" apart from "field not present".
				form.find('input[type=checkbox]').each(function() {
					if (!this.checked) {
						postData[this.name] = '0';
					}
				});

				jQuery.post('index.php', postData, function(response) {
					messageBox.removeClass('alert-success alert-danger');
					if (response && response.success) {
						messageBox.addClass('alert-success').text(response.result.message || 'Saved').show();
					} else {
						var errorMessage = (response && response.error && response.error.message) ? response.error.message : 'Save failed';
						messageBox.addClass('alert-danger').text(errorMessage).show();
					}
				}, 'json').fail(function() {
					messageBox.removeClass('alert-success').addClass('alert-danger').text('Save failed (request error)').show();
				});
			});
		});
	</script>
{/strip}
