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
<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
    <symbol id="check-circle-fill" fill="currentColor" viewBox="0 0 16 16">
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
    </symbol>
    <symbol id="info-fill" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
    </symbol>
    <symbol id="exclamation-triangle-fill" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
    </symbol>
</svg>

{include file='./header.tpl'}

<div id="correos_oficial">
    <div id="settings-container" class="container-fluid">
        <div class="row">
            <div class="row">
                <div class="col-md-12 settings-accordion">
                    <div class="gdpr-links mb-3">
                        <label class="mx-2">
                            <a href="{$co_base_dir}views/gdpr/condiciones_servicio.pdf" target="_blank">
                                {l s=' Terms and conditions' mod='correosoficial'}
                            </a>
                        </label>
                        <label>
                            <a href="{$co_base_dir}views/gdpr/proteccion_datos.pdf" target="_blank">
                                {l s=' Data protection policy.' mod='correosoficial'}
                            </a>
                        </label>
                    </div>
                    <div class="accordion" id="accordionFlushExample">  
                                                <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingOne">
                                <button id="customer_data" class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                                    {l s=' CUSTOMER DATA' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-customer-data.tpl'}
                            </div>
                        </div>
                            <div class="accordion-item" id="sender-anchor">
                                <h2 class="accordion-header" id="flush-headingTwo">
                                    <button id="sender_block" class="accordion-button collapsed accordion-button-custom{if $ActivateMarketplace == 'on'} co-accordion-locked{/if}" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo" {if $ActivateMarketplace == 'on'}disabled="disabled"{/if}>
                                        {l s=' SENDERS' mod='correosoficial'}
                                    </button>
                                </h2>
                                <div id="flush-collapseTwo" class="accordion-collapse collapse" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
                                    {include file='./settings-senders.tpl'}
                                </div>
                            </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingThree">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                                    {l s=' USER CONFIGURATION' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseThree" class="accordion-collapse collapse" aria-labelledby="flush-headingThree" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-user-configuration.tpl'}
                            </div>
                        </div>
                        {* <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingFour">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseFour">
                                    {l s=' CRON' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseFour" class="accordion-collapse collapse" aria-labelledby="flush-headingFour" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-cron.tpl'}
                            </div>
                        </div> *}
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingFive">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFive" aria-expanded="false" aria-controls="flush-collapseFive">
                                    {l s=' PRODUCTS' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseFive" class="accordion-collapse collapse" aria-labelledby="flush-headingFive" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-products.tpl'}
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingSix">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseSix" aria-expanded="false" aria-controls="flush-collapseSix">
                                    {l s=' ZONES AND CARRIERS' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseSix" class="accordion-collapse collapse" aria-labelledby="flush-headingSix" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-zones-carriers.tpl'}
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingSeven">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseSeven" aria-expanded="false" aria-controls="flush-collapseSeven">
                                    {l s=' CUSTOMS PROCESSING' mod='correosoficial'}&nbsp;<span class="co_small">({l s='Only for Correos shippings' mod='correosoficial'})</span>
                                </button>
                            </h2>
                            <div id="flush-collapseSeven" class="accordion-collapse collapse" aria-labelledby="flush-headingSeven" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-customs-processing.tpl'}
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingEight">
                                <button id="fulfillment_block" class="accordion-button collapsed accordion-button-custom{if $ActivateMarketplace == 'on'} co-accordion-locked{/if}" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseEight" aria-expanded="false" aria-controls="flush-collapseEight" {if $ActivateMarketplace == 'on'}disabled="disabled"{/if}>
                                    {l s=' FULFILLMENT SERVICE' mod='correosoficial'}</span>
                                </button>
                            </h2>
                            <div id="flush-collapseEight" class="accordion-collapse collapse" aria-labelledby="flush-headingEight" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-sga.tpl'}
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingNine">
                                <button class="accordion-button collapsed accordion-button-custom" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseNine" aria-expanded="false" aria-controls="flush-collapseNine">
                                    {l s=' MARKETPLACE' mod='correosoficial'}
                                </button>
                            </h2>
                            <div id="flush-collapseNine" class="accordion-collapse collapse" aria-labelledby="flush-headingNine" data-bs-parent="#accordionFlushExample">
                                {include file='./settings-marketplace.tpl'}
                            </div>
                        </div>
                    </div> 
                </div> 
            </div> 
        </div> 
    </div> 
</div> 

<script type="text/javascript">
    var sga_module = {if $sga_module}true{else}false{/if};
</script>
<script type="text/javascript" src="{$co_base_dir}views/js/datatables/datatables.min.js"></script>
