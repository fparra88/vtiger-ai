{strip}
    <div class="row" style="margin-bottom: 70px;">
        <div class="col-lg-9">
            <div class="row form-group">
                <div class="col-lg-2">{vtranslate('LBL_RECEPIENTS',$QUALIFIED_MODULE)}<span class="redColor">*</span></div>
                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-lg-5">
                            <input type="text" class="inputElement fields" data-rule-required="true" name="whatsapp_recepient" value="{$TASK_OBJECT->whatsapp_recepient}" />
                        </div>
                        <div class="col-lg-6">
                            <select class="select2 task-fields" style="min-width: 150px;" data-placeholder="{vtranslate('LBL_SELECT_FIELDS', $QUALIFIED_MODULE)}">
                                <option></option>
                                {foreach from=$RECORD_STRUCTURE_MODEL->getFieldsByType('phone') item=FIELD key=FIELD_VALUE}
                                    <option value=",${$FIELD_VALUE}">({vtranslate($FIELD->getModule()->get('name'),$FIELD->getModule()->get('name'))})  {vtranslate($FIELD->get('label'),$FIELD->getModule()->get('name'))}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-lg-2">{vtranslate('LBL_WHATSAPP_TEMPLATE_NAME',$QUALIFIED_MODULE)}<span class="redColor">*</span></div>
                <div class="col-lg-8">
                    <input type="text" class="inputElement fields" data-rule-required="true" name="template_name" value="{$TASK_OBJECT->template_name}" placeholder="{vtranslate('LBL_WHATSAPP_TEMPLATE_NAME_PLACEHOLDER',$QUALIFIED_MODULE)}" />
                </div>
            </div>
            <div class="row form-group">
                <div class="col-lg-2">{vtranslate('LBL_ADD_FIELDS',$QUALIFIED_MODULE)}</div>
                <div class="col-lg-10">
                    <select class="select2 task-fields" style="min-width: 150px;" data-placeholder="{vtranslate('LBL_SELECT_FIELDS', $QUALIFIED_MODULE)}">
                        <option></option>
                        {$ALL_FIELD_OPTIONS}
                    </select>
                </div>
                <div class="col-lg-2"> &nbsp; </div>
                <div class="col-lg-10"> &nbsp; </div>
                <div class="col-lg-2">{vtranslate('LBL_WHATSAPP_PARAM_VALUES',$QUALIFIED_MODULE)}</div>
                <div class="col-lg-6">
                    <textarea name="param_values" class="inputElement fields" style="height: inherit;" placeholder="{vtranslate('LBL_WHATSAPP_PARAM_VALUES_PLACEHOLDER',$QUALIFIED_MODULE)}">{$TASK_OBJECT->param_values}</textarea>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-lg-2">{vtranslate('LBL_WHATSAPP_USE_AI_PERSONALIZE',$QUALIFIED_MODULE)}</div>
                <div class="col-lg-8">
                    <input type="checkbox" name="use_ai_personalize" value="1" class="inputElement fields" {if $TASK_OBJECT->use_ai_personalize == '1'}checked{/if} />
                </div>
            </div>
        </div>
    </div>
{/strip}
