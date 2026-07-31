{**
 * This program is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see https://www.gnu.org/licenses/.
 *}
<!-- INICIO BLOQUE VALORES AÑADIDOS -->
<div class="card">

    <div class="card-header card-header-blue">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-send-plus custom-icon icon-margin-top" viewBox="0 0 16 16">
        <path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855a.75.75 0 0 0-.124 1.329l4.995 3.178 1.531 2.406a.5.5 0 0 0 .844-.536L6.637 10.07l7.494-7.494-1.895 4.738a.5.5 0 1 0 .928.372l2.8-7Zm-2.54 1.183L5.93 9.363 1.591 6.602l11.833-4.733Z"/>
        <path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm-3.5-2a.5.5 0 0 0-.5.5v1h-1a.5.5 0 0 0 0 1h1v1a.5.5 0 0 0 1 0v-1h1a.5.5 0 0 0 0-1h-1v-1a.5.5 0 0 0-.5-.5Z"/>
        </svg>
        <h3 class="card-header-title">{l s='Added value of the shipment' mod='correosoficial'}</h3>
    </div>

    <div class="card-body" id="added_values">
        <div class="row">
            <div class="col-sm-7">
                <div class="row">
                    <div class="col-sm-4 pr-0 mr-0">
                        <div class="input-group mb-3">
                            <div class="input-group-addon input-group-checkbox-custom">
                                <input class="form-check-input mt-0" type="checkbox" name="contrareembolsoCheckbox" id="contrareembolsoCheckbox" {if isset($correos_order['added_values_cash_on_delivery']) && $correos_order['added_values_cash_on_delivery'] == true}checked disabled{/if} {if $cash_on_delivery == true}checked{/if}>
                            </div>
                            <span class="input-group-text input-group-checkbox">{l s='Cash on delivery' mod='correosoficial'}</span>
                        </div>
                    </div>
                    <div id="cash_on_delivery_value_container" class="col-sm-6 ml-0 pl-0 {if !isset($correos_order['added_values_cash_on_delivery']) || $correos_order['added_values_cash_on_delivery'] == false}hidden-block{/if}">
                        <div class="input-group input-group-custom">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text">{l s='Cash on delivery value' mod='correosoficial'} (€)</span>
                            </div>
                            <input type="text" id="cash_on_delivery_value" name="cash_on_delivery_value" class="form-control" {if isset($correos_order['added_values_cash_on_delivery']) && $correos_order['added_values_cash_on_delivery'] == true} value="{$correos_order['added_values_cash_on_delivery_value']}" disabled {else} value="{$cash_on_delivery_value}"{/if}>
                        </div>
                    </div>
                    <div id="bank_acc_number_container" class="col-sm-10 {if !(isset($correos_order['added_values_cash_on_delivery'])) || $correos_order['added_values_cash_on_delivery'] == false || ($carrier_order['company'] == 'CEX')}hidden-block{/if}">
                        <div class="input-group input-group-custom">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">{l s='Bank account number / IBAN' mod='correosoficial'}</span>
                            </div>
                            <input type="text" name="bank_acc_number" id="bank_acc_number" {if isset($correos_order['added_values_cash_on_delivery']) && $correos_order['added_values_cash_on_delivery'] == true} value="{$correos_order['added_values_cash_on_delivery_iban']}" disabled {else} value="{$bank_acc_number}"{/if} class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group mb-3 mr-0">
                            <div class="input-group-addon input-group-checkbox-custom">
                                <input class="form-check-input mt-0" type="checkbox" name="seguroCheckbox" id="seguroCheckbox" {if isset($correos_order['added_values_insurance']) && $correos_order['added_values_insurance'] == true}checked disabled{/if}>
                            </div>
                            <span class="input-group-text input-group-checkbox">{l s='Insurance' mod='correosoficial'}</span>
                        </div>
                    </div>
                    <div class="col-sm-6 seguro-info{if !isset($correos_order['added_values_insurance']) || $correos_order['added_values_insurance'] == false} hidden-block{/if}">
                        <div class="input-group input-group-custom">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text">{l s='Insured value' mod='correosoficial'} (€)</span>
                            </div>
                            <input type="text" id="insurance_value" name="insurance_value" class="form-control" {if isset($correos_order['added_values_insurance']) && $correos_order['added_values_insurance'] == true} value="{$correos_order['added_values_insurance_value']}" disabled{/if}>
                        </div>
                    </div>
                </div>

                <div id="partial_delivery_container" class="input-group mb-3 {if (!isset($correos_order['added_values_partial_delivery']) || $correos_order['added_values_partial_delivery'] == false) || $carrier_order['company'] == "CEX" || $bultos == 1}hidden-block{/if}">
                    <div class="input-group-addon input-group-checkbox-custom">
                <input class="form-check-input mt-0" type="checkbox" name="partial_delivery" id="partial_delivery" {if isset($correos_order['added_values_partial_delivery']) && $correos_order['added_values_partial_delivery'] == true}checked disabled{/if}>
                    </div>
                    <span class="input-group-text input-group-checkbox">{l s='Partial delivery' mod='correosoficial'}</span>
                </div>

                <div id="delivery_saturday_container" class="input-group mb-3 {if (!isset($correos_order['added_values_delivery_saturday']) || $correos_order['added_values_delivery_saturday'] == false) && $carrier_order['company'] == "Correos"}hidden-block{/if}">
                    <div class="input-group-addon input-group-checkbox-custom">
                        <input class="form-check-input mt-0" type="checkbox" name="delivery_saturday" id="delivery_saturday" {if isset($correos_order['added_values_delivery_saturday']) && $correos_order['added_values_delivery_saturday'] == true}checked disabled {/if}>
                    </div>
                    <span class="input-group-text input-group-checkbox">{l s='Saturday pick up' mod='correosoficial'}</span>
                </div>

            </div>
        </div>
        
    </div>
</div>
<!-- FIN BLOQUE VALORES AÑADIDOS -->