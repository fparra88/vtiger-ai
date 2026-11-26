{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{* modules/Settings/WhatsAppCronJobs/views/Edit.php *}

{strip}
	<div class="editViewPageDiv editViewContainer" id="EditViewWhatsAppCronJobs" style="padding-top:0px;">
		<div class="col-lg-12 col-md-12 col-sm-12">
			<div>
				<h3 style="margin-top: 0px;">{vtranslate('WhatsAppCronJobs', $QUALIFIED_MODULE_NAME)}</h3>
				<p>{vtranslate('LBL_WHATSAPP_CRONJOBS_INTRO', $QUALIFIED_MODULE_NAME)}</p>
			</div>
			<div id="whatsAppCronJobsSaveMessage" class="alert" style="display:none;"></div>
			<form id="whatsAppCronJobsConfigForm" method="POST">
				{foreach item=JOB key=JOB_KEY from=$JOBS}
					<div class="blockData">
						<br>
						<div class="block">
							<h4>{vtranslate($JOB.label, $QUALIFIED_MODULE_NAME)}</h4>
							<p class="text-muted">{vtranslate($JOB.description, $QUALIFIED_MODULE_NAME)}</p>
							<hr>
							<table class="table editview-table no-border">
								<tbody>
									<tr>
										<td class="fieldLabel"><label>{vtranslate('LBL_ACTIVE', $QUALIFIED_MODULE_NAME)}</label></td>
										<td class="fieldValue">
											<div class="col-lg-6 col-md-6 col-sm-12">
												<input type="radio" name="cronjobs[{$JOB_KEY}][isactive]" value="1" {if $JOB.isactive} checked {/if} />&nbsp;{vtranslate('LBL_YES', $QUALIFIED_MODULE_NAME)}&nbsp;&nbsp;
												<input type="radio" name="cronjobs[{$JOB_KEY}][isactive]" value="0" {if !$JOB.isactive} checked {/if} />&nbsp;{vtranslate('LBL_NO', $QUALIFIED_MODULE_NAME)}
											</div>
										</td>
									</tr>
									<tr>
										<td class="fieldLabel"><label for="{$JOB_KEY}_template_name">{vtranslate('LBL_WHATSAPP_TEMPLATE_NAME', $QUALIFIED_MODULE_NAME)}</label></td>
										<td class="fieldValue">
											<div class="col-lg-6 col-md-6 col-sm-12">
												<input type="text" class="inputElement form-control" id="{$JOB_KEY}_template_name" name="cronjobs[{$JOB_KEY}][template_name]" value="{$JOB.template_name}" placeholder="{vtranslate('LBL_WHATSAPP_TEMPLATE_NAME_PLACEHOLDER', $QUALIFIED_MODULE_NAME)}" />
											</div>
										</td>
									</tr>
									<tr>
										<td class="fieldLabel"><label for="{$JOB_KEY}_language_code">{vtranslate('LBL_LANGUAGE_CODE', $QUALIFIED_MODULE_NAME)}</label></td>
										<td class="fieldValue">
											<div class="col-lg-6 col-md-6 col-sm-12">
												<input type="text" class="inputElement form-control" id="{$JOB_KEY}_language_code" name="cronjobs[{$JOB_KEY}][language_code]" value="{$JOB.language_code}" />
											</div>
										</td>
									</tr>
									{if $JOB.hasThreshold}
										<tr>
											<td class="fieldLabel"><label for="{$JOB_KEY}_days_threshold">{vtranslate('LBL_DAYS_THRESHOLD', $QUALIFIED_MODULE_NAME)}</label></td>
											<td class="fieldValue">
												<div class="col-lg-6 col-md-6 col-sm-12">
													<input type="number" min="1" class="inputElement form-control" id="{$JOB_KEY}_days_threshold" name="cronjobs[{$JOB_KEY}][days_threshold]" value="{$JOB.days_threshold}" />
												</div>
											</td>
										</tr>
									{/if}
								</tbody>
							</table>
						</div>
					</div>
				{/foreach}
				<br>
				<div class='modal-overlay-footer clearfix'>
					<div class="row clearfix">
						<div class='textAlignCenter col-lg-12 col-md-12 col-sm-12'>
							<button type="submit" class="btn btn-success saveButton" id="whatsAppCronJobsSaveButton">{vtranslate('LBL_SAVE', $QUALIFIED_MODULE_NAME)}</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('#whatsAppCronJobsConfigForm').on('submit', function(e) {
				e.preventDefault();
				var form = jQuery(this);
				var messageBox = jQuery('#whatsAppCronJobsSaveMessage');
				var postData = form.serializeArray();
				var requestData = { module: 'WhatsAppCronJobs', parent: 'Settings', action: 'SaveAjax' };
				jQuery.each(postData, function(i, field) {
					requestData[field.name] = field.value;
				});

				jQuery.post('index.php', requestData, function(response) {
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
