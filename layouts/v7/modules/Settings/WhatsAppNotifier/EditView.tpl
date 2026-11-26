{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{* modules/Settings/WhatsAppNotifier/views/Edit.php *}

{strip}
	<div class="editViewPageDiv editViewContainer" id="EditViewWhatsAppNotifier" style="padding-top:0px;">
		<div class="col-lg-12 col-md-12 col-sm-12">
			<div>
				<h3 style="margin-top: 0px;">{vtranslate('WhatsAppNotifier', $QUALIFIED_MODULE_NAME)}</h3>
			</div>
			<div id="whatsAppNotifierSaveMessage" class="alert" style="display:none;"></div>
			<form id="whatsAppNotifierConfigForm" method="POST">
				{if $RECORD_ID}
					<input type="hidden" value="{$RECORD_ID}" name="record" id="recordId"/>
				{/if}
				<div class="blockData">
					<br>
					<div class="block">
						<h4>{vtranslate('WhatsAppNotifier', $QUALIFIED_MODULE_NAME)}</h4>
						<hr>
						<table class="table editview-table no-border">
							<tbody>
								{foreach item=FIELD_MODEL from=$EDITABLE_FIELDS}
									{assign var=FIELD_NAME value=$FIELD_MODEL->get('name')}
									{assign var=FIELD_TYPE value=$FIELD_MODEL->getFieldDataType()}
									{assign var=FIELD_VALUE value=$RECORD_MODEL->get($FIELD_NAME)}
									<tr>
										<td class="fieldLabel"><label for="{$FIELD_NAME}">{vtranslate($FIELD_MODEL->get('label'), $QUALIFIED_MODULE_NAME)}</label></td>
										<td class="fieldValue">
											<div class="col-lg-6 col-md-6 col-sm-12">
												{if $FIELD_TYPE == 'password'}
													<input type="password" autocomplete="new-password" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="{$FIELD_VALUE}" />
												{elseif $FIELD_TYPE == 'radio'}
													<input type="radio" name="{$FIELD_NAME}" value="1" {if $FIELD_VALUE} checked {/if} />&nbsp;{vtranslate('LBL_YES', $QUALIFIED_MODULE_NAME)}&nbsp;&nbsp;
													<input type="radio" name="{$FIELD_NAME}" value="0" {if !$FIELD_VALUE} checked {/if} />&nbsp;{vtranslate('LBL_NO', $QUALIFIED_MODULE_NAME)}
												{else}
													<input type="text" class="inputElement form-control" id="{$FIELD_NAME}" name="{$FIELD_NAME}" value="{$FIELD_VALUE}" />
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
								<button type="submit" class="btn btn-success saveButton" id="whatsAppNotifierSaveButton">{vtranslate('LBL_SAVE', $QUALIFIED_MODULE_NAME)}</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('#whatsAppNotifierConfigForm').on('submit', function(e) {
				e.preventDefault();
				var form = jQuery(this);
				var messageBox = jQuery('#whatsAppNotifierSaveMessage');
				var params = form.serializeArray();
				var postData = { module: 'WhatsAppNotifier', parent: 'Settings', action: 'SaveAjax' };
				jQuery.each(params, function(i, field) {
					postData[field.name] = field.value;
				});

				jQuery.post('index.php', postData, function(response) {
					messageBox.removeClass('alert-success alert-danger');
					if (response && response.success) {
						messageBox.addClass('alert-success').text((response.result && response.result[0]) || 'Saved').show();
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
