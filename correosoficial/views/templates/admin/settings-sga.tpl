<div id="SGAConfigurationBlock" class="accordion-body">
    {* Aviso: información de cuenta para el servicio de logística. *}
    <div class="notice notice-info inline" style="padding: 10px 14px; margin-bottom: 16px; border-left-color: #0073aa;">
        <p style="margin: 0;">
            {l s='This account information is used for the fulfillment service. If you use the module to create shipping labels, you will need to set at least one sender in the module settings.' mod='correosoficial'}
        </p>
    </div>
    <form id="SGAConfigurationDataForm" name="SGAConfigurationDataForm" method="POST" enctype="multipart/form-data">
        <fieldset>
            <div class="row">
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="ActivateSGA" id="ActivateSGA" {if $ActivateSGA} checked{/if}>
                                </div>
                                <span class="input-group-text input-group-text-color w-50">
                                    {l s='Use Correos fulfillment service' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12" id="SGAConfigurationDataBlock" {if !$ActivateSGA}style="display: none;" {/if}>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4" id="BankAndIBANBlock">
                                <div class="input-group-addon input-group-text-custom w-25">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Owner' mod='correosoficial'} </br>
                                    </span>
                                </div>
                                <input type="text" name="SGAOwner" id="SGAOwner"
                                    value="{$SGAOwner|escape:'htmlall':'UTF-8'}" class="form-control w-50">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4" id="BankAndIBANBlock">
                                <div class="input-group-addon input-group-text-custom w-25">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Customer' mod='correosoficial'} </br>
                                    </span>
                                </div>
                                <input type="text" name="SGACustomer" id="SGACustomer"
                                    value="{$SGACustomer|escape:'htmlall':'UTF-8'}" class="form-control w-50">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4" id="BankAndIBANBlock">
                                <div class="input-group-addon input-group-text-custom w-25">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Warehouse' mod='correosoficial'} </br>
                                    </span>
                                </div>
                                <select id="SGAStore" name="SGAStore" required="required" class="co_dropdown  w-50">
                                    <option></option>
                                    <option {if $SGAStore==='Z01' }selected{/if} value="Z01">PRO Z01</option>
                                    <option {if $SGAStore==='PL1' }selected{/if} value="PL1">Illescas</option>
                                    <option {if $SGAStore==='PL2' }selected{/if} value="PL2">Sant Esteve</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4">
                            <span class="input-group-text-color">
                                {l s='Process orders automatically in this status' mod='correosoficial'} </br>
                            </span>
                            <select id="SGAProcessStatus" name="SGAProcessStatus"
                                class="co_dropdown sgaOrderStatusSelect">
                                <option></option>
                                {html_options options=$sga_statuses selected=$SGAProcessStatus}
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-5">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="SGAUpdateStock"
                                        id="SGAUpdateStock" {$SGAUpdateStock|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span class="input-group-text input-group-text-color w-75">
                                    {l s='Update stock with the logistics platform' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-5">
                            <!-- Checkbox: Synchronize stock -->
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="SGAOrderUpdateStock"
                                        id="SGAOrderUpdateStock" {$SGAOrderUpdateStock|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span class="input-group-text input-group-text-color w-75">
                                    {l s='Synchronize stock after a sales order' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-5">
                            <!-- Checkbox: Enable order tracking -->
                            <div class="input-group mb-1">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="SGAOrderStatusTracking"
                                        id="SGAOrderStatusTracking" {$SGAOrderStatusTracking|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span class="input-group-text input-group-text-color w-75">
                                    {l s='Show order status progress' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-7">
                            <!-- Opciones desplegables -->
                            <div id="order-tracking-options" {if !$SGAOrderStatusTracking}style="display: none;" {/if}>
                                <div class="input-group mb-3">
                                    <div class="input-group-addon input-group-text-custom"
                                        style="width:50%; text-align:right;">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Pending order in warehouse' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select id="SGAOrderStatusTrackingPE" class="sgaOrderStatusSelect"
                                        name="SGAOrderStatusTrackingPE" style="width:100%;">
                                        {html_options options=$sga_statuses selected=$selected_status_PE}
                                    </select>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-addon input-group-text-custom"
                                        style="width:50%; text-align:right;">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Order extracted from the warehouse' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select id="SGAOrderStatusTrackingEX" class="sgaOrderStatusSelect"
                                        name="SGAOrderStatusTrackingEX" style="width:100%;">
                                        {html_options options=$sga_statuses selected=$selected_status_EX}
                                    </select>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-addon input-group-text-custom"
                                        style="width:50%; text-align:right;">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Order with an error' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select id="SGAOrderStatusTrackingError" class="sgaOrderStatusSelect"
                                        name="SGAOrderStatusTrackingError" style="width:100%;">
                                        {html_options options=$sga_statuses selected=$selected_status_Error}
                                    </select>
                                </div>

                                <div class="col-sm-12">
                                    <span class=" input-group-text-color">
                                        {l s='Order status tracking update time:' mod='correosoficial'}
                                    </span>
                                </div>

                                <div class="col-sm-3 input-space slider-custom-ost">
                                    <div class="input-group col-sm-12">
                                        <input type="range" name="SGAOrderStatusTrackingCronInterval"
                                            id="SGAOrderStatusTrackingCronInterval" max="8" min="4"
                                            value="{$SGAOrderStatusTrackingCronInterval}">
                                        <div class="col-sm-12">
                                            <span
                                                id="order_status_tracking_cron_interval_TEXT">{$SGAOrderStatusTrackingCronInterval}</span>
                                            <span>{l s='hours' mod='correosoficial'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <input class="co_primary_button" name="SGAConfigurationSaveButton"
                            id="SGAConfigurationSaveButton" type="submit"
                            value="{l s='SAVE FULFILLMENT DATA' mod='correosoficial'}">
                    </div>
                </div>
            </div>



        </fieldset>
    </form>
</div>

<script>
    var sgaConfigurationSaved = "{l s='SGA data successfully saved' mod='correosoficial'}";
    var sgaOwnerRequired = "{l s='Owner is required' mod='correosoficial'}";
    var sgaCustomerRequired = "{l s='Customer is required' mod='correosoficial'}";
    var sgaStoreRequired = "{l s='Warehouse is required' mod='correosoficial'}";
</script>