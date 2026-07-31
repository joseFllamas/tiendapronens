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
<div class="accordion-body accordion-body">
    <form action="index.php?controller=AdminCorreosOficialZonesCarriers" id="CorreosZonesCarriersForm" name="CorreosZonesCarriersForm" method="POST">
        <div class="row">
            <div class="col-sm-12 ZonesAndCarriers">
                <div class="alert alert-secondary d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:">
                        <use xlink:href="#info-fill" />
                    </svg>
                    <div>
                        {l s='Carriers with ** are carriers that are not active' mod='correosoficial'}.</br>
                        {l s='It is recommended to configure them for backward compatibility' mod='correosoficial'}.
                    </div>
                </div>
        
                <div class="input-group mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="showAllCarriersCheck">
                        <label class="form-check-label form-check-label-color" for="showAllCarriersCheck">
                            {l s='Activate all carriers' mod='correosoficial'}
                        </label>
                    </div>
                    <div class="col-sm-12 ProductsAndCarriersList">
                        {foreach from=$zonesandcarriers item=zone}
                        <div>
                            {if !empty($zone['carriers'])}
                                <div class="zone-name">{$zone['zonename']}</div>
                                    {foreach from=$zone['carriers'] item=carrier}
                                        {if ($carrier['active'] == 1)}
                                            <div class="input-group mb-3">
                                        {else}
                                            <div class="input-group mb-3 hidden-product-option">
                                        {/if}
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color">
                                                {if ($carrier['active'] == 0)}**{/if}
                                                {$carrier['name']}
                                            </span>
                                        </div>
                                        <select class="co_dropdown scp_products" id="scp_{$zone['id_zone']}_{$carrier['id_carrier']}" name="scp_{$zone['id_zone']}_{$carrier['id_carrier']}">
                                            <option value=""></option>
                                            {if !empty($zone['products'])}
                                                {foreach from=$zone['products'] item=product} 
                                                    <option value="{$product->id}" {if $product->id == $carrier['product_selected']} selected{/if}>
                                                        {$product->name}
                                                    </option>
                                                {/foreach}
                                            {/if}
                                        </select>
                                    </div>
                                    {/foreach}
                                {/if}
                            </div>
                    {/foreach}
                    </div>
                </div>
            </div>
            
            <div class="col-sm-12 mt-3  mb-3">
                <div id="advice_products" class="advice">
                    <h4>
                        {l s='Automatic product assignment - Carrier relationship' mod='correosoficial'}
                    </h4>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='The title contains' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings"
                                        viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title=""
                                        data-bs-original-title="{l s='Search the carrier name in the following text to capture the automatic change.' mod='correosoficial'}">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z">
                                        </path>
                                    </svg>
                                </span>
                            </div>
                            <input type="text" class="form-control" name="AutomaticProductAssignmentText" id="AutomaticProductAssignmentText" value="{$AutomaticProductAssignmentText->value|escape:'htmlall':'UTF-8'}">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='Product to assign' mod='correosoficial'}
                                </span>
                            </div>
                            <select class="co_dropdown" id="AutomaticProductAssignmentProduct" name="AutomaticProductAssignmentProduct">
                                <option value="">{l s='Select Product' mod='correosoficial'}</option>
                                {if !empty($active_products)}
                                    {foreach from=$active_products item=product}
                                        <option value="{$product->id}"
                                            {if $product->product_type == "office" || $product->product_type == "citypaq"}
                                                disabled{/if} {if $product->id == $AutomaticProductAssignmentProduct->value}
                                            selected{/if}>
                                            {$product->name}
                                        </option>
                                    {/foreach}
                                {/if}
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <a class="cron-button" href="{$co_base_dir}log/log_automatic_product_assignment.txt" download>{l s='Download log' mod='correosoficial'}</a>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <input class="co_primary_button" name="ZonesCarriersSaveButton" id="ZonesCarriersSaveButton"
                        type="submit" value="{l s='SAVE ZONES AND CARRIERS' mod='correosoficial'}">
                </div>
            </div>
        </div>
    </form>
</div>
<script>
    var zonesCarriersSaved = "{l s='Zones and carriers successfully saved' mod='correosoficial'}";
</script>
