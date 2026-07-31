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
<!-- INICIO BLOQUE DEVOLUCIONES -->
<div id="returns_container" class="card {if !$order_returnable}hidden-block{/if} container-bulto">

    <div class="card-header card-header-yellow">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bootstrap-reboot custom-icon icon-margin-top" viewBox="0 0 16 16">
        <path d="M1.161 8a6.84 6.84 0 1 0 6.842-6.84.58.58 0 1 1 0-1.16 8 8 0 1 1-6.556 3.412l-.663-.577a.58.58 0 0 1 .227-.997l2.52-.69a.58.58 0 0 1 .728.633l-.332 2.592a.58.58 0 0 1-.956.364l-.643-.56A6.812 6.812 0 0 0 1.16 8z"/>
        <path d="M6.641 11.671V8.843h1.57l1.498 2.828h1.314L9.377 8.665c.897-.3 1.427-1.106 1.427-2.1 0-1.37-.943-2.246-2.456-2.246H5.5v7.352h1.141zm0-3.75V5.277h1.57c.881 0 1.416.499 1.416 1.32 0 .84-.504 1.324-1.386 1.324h-1.6z"/>
        </svg>
        <h3 class="card-header-title">{l s='Returns' mod='correosoficial'}</h3>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-sm-12">

                <div class="row">
                    <div class="col-sm-6">
                        <div id="generate-return-container" class="row {if $exist_return}hidden-block{/if}">
                            <div class="col-sm-12">
                                <div class="input-group input-group-custom">
                                    <div class="input-group-addon input-group-text-custom">
                                        <label class="input-group-text-bulto">{l s='Product Return' mod='correosoficial'}</label>
                                    </div>
                                    <select class="form-select select-product-return" id="input_select_carrier_return" name="input_select_carrier_return">
                                        {* SELECTOR DINÁMICO BASADO EN PAÍS *}
                                        {if $returns_supported_country && $return_products_filtered}
                                            {foreach from=$return_products_filtered item=product}
                                                <option value="{$product.code}" data-company="{$product.company}" {if ($carrier_type eq "Correos" && $product.company eq "Correos") || ($carrier_type eq "CEX" && $product.company eq "CEX")}selected{/if}>
                                                    {$product.name}
                                                </option>
                                            {/foreach}
                                        {else}
                                            {* FALLBACK: SELECTOR ORIGINAL BASADO EN ACTIVE_CLIENT *}
                                            {if $active_client eq "both"}
                                            <option value="S0148" data-company="Correos" {if $carrier_type eq "Correos"}selected{/if}>Paq Retorno - Correos</option>
                                            <option value="63" data-company="CEX" {if $carrier_type eq "CEX"}selected{/if}>Paq24 - Correos Express</option>
                                            {/if}
                                            {if $active_client eq "Correos"}
                                            <option value="S0148" data-company="Correos">Paq Retorno - Correos</option>
                                            {/if}
                                            {if $active_client eq "CEX"}
                                            <option value="63" data-company="CEX">Paq24 - Correos Express</option>
                                            {/if}
                                        {/if}
                                    </select>
                                </div>
                                
                                <div class="input-group input-group-custom correos-num-parcels-return-container {if $carrier_type eq "Correos"} hidden-block {/if}">
                                    <span class="input-group-text-bulto">
                                    {l s='Number of packages return' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Indique si su devolución se compone de un o más bultos.">
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                        </svg>
                                    </span>
                                    <select class="form-select select-bulto-principal" id="correos-num-parcels-return" name="correos-num-parcels-return">
                                        <option value="1" {if $bultos_return == 1}selected{/if}>1</option>
                                        <option value="2" {if $bultos_return == 2}selected{/if}>2</option>
                                        <option value="3" {if $bultos_return == 3}selected{/if}>3</option>
                                        <option value="4" {if $bultos_return == 4}selected{/if}>4</option>
                                        <option value="5" {if $bultos_return == 5}selected{/if}>5</option>
                                        <option value="6" {if $bultos_return == 6}selected{/if}>6</option>
                                        <option value="7" {if $bultos_return == 7}selected{/if}>7</option>
                                        <option value="8" {if $bultos_return == 8}selected{/if}>8</option>
                                        <option value="9" {if $bultos_return == 9}selected{/if}>9</option>
                                        <option value="10" {if $bultos_return == 10}selected{/if}>10</option>
                                    </select>
                                </div>
                            </div> 
                        </div> 
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                    
                        <div class="card {if !$exist_return}hidden-block{/if}" id="return-done-info">
                            <div class="card-header card-header-date">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-square custom-icon" viewBox="0 0 16 16">
                                <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"></path>
                                <path d="M10.97 4.97a.75.75 0 0 1 1.071 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.235.235 0 0 1 .02-.022z"></path>
                                </svg>
                                <span>{l s='Return data' mod='correosoficial'}</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-7">
                                    
                                        {if $exist_return}
                                            <p>{l s='Return codes' mod='correosoficial'}</p> 
                                        {/if}
                                        
                                        {$num_bulto = 1}
                                        <div class="shipping-numbers-container-return">
                                        {foreach from=$array_packages_return item=package}
                                            <span class="return-done-info-text">{l s='Package' mod='correosoficial'} {$num_bulto}: {$package['shipping_number']}</span><br>
                                            <input type="hidden" id="hidden_return_code_{$num_bulto}" name="hidden_return_code_{$num_bulto}" value="{$package['shipping_number']}">
                                            {$num_bulto = $num_bulto + 1}
                                        {/foreach}
                                        </div>
                                    </div>
                                    <div class="col-sm-5">
                                        <p>{l s='Status' mod='correosoficial'}: <br>
                                            <span id="return-status" class="return-status">{$return_status}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12 mt-3">
                                        <div class="card">
                                            <div class="card-body">
                                    <div id="select_label_return_options" class="input-group input-group-custom {if !$exist_return} hidden-block{/if}">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <label class="input-group-text-bulto">{l s='Label type' mod='correosoficial'}</label>
                                                    </div>
                                                    <select class="form-select select-label select-label-return" id="input_tipo_etiqueta_reimpresion_return" name="input_tipo_etiqueta_reimpresion_return" >
                                                        {html_options options=$select_label_options selected=$DefaultLabel}
                                                    </select>
                                                </div>
                                                <div class="input-group input-group-custom mb-3">
                                                    <button type="button" id="ReimprimirEtiquetasDevolucionButton" name="ReimprimirEtiquetasDevolucionButton" class="btn-lg co_primary_button button-width RePrintReturnLabels2">
                                                        <span id="ProcessingReimprimirEtiquetasDevolucionButton" class="hidden-block">
                                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                        </span>
                                                        <span id="ProcessingMsgEtiquetasDevolucionButton" class="label-message" role="status" aria-hidden="true">{l s='Print label' mod='correosoficial'}</span>
                                                    </button>
                                                </div>                                               
                                                <div class="input-group input-group-custom mb-3 {if !$return_require_customs_doc || $carrier_type == "CEX"}hidden-block{/if} " id="customs-labels-container">
                                                    <button id="ImprimirCN23ButtonReturn" class="btn-lg co_primary_button button-width PrintGestionAduaneraLabels2" type="button"> 
                                                        <span id="ProcessingImprimirCN23Button2" class="spin hidden-block">
                                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                        </span>
                                                        <span id="ProcessingMsgImprimirCN23Button2" class="label-message" role="status" aria-hidden="true">{l s='Print CN23' mod='correosoficial'}</span>
                                                    </button>
                                                    {* <input id="ImprimirDUAButton2" class="btn-sm co_primary_button PrintGestionAduaneraLabels2" type="button" value="{l s='Print DCAF' mod='correosoficial'}">
                                                    <input id="ImprimirDDPButton2" class="btn-sm co_primary_button PrintGestionAduaneraLabels2" type="button" value="{l s='Print DDP' mod='correosoficial'}"> *}
                                                </div>
                                                <div class="input-group input-group-custom mb-3">
                                                    <button type="button" id="SendDocumentationByEmail" name="SendDocumentationByEmail" class="btn-md co_primary_button button-width PrintDocByEmail2">
                                                        <span id="ProcessingSendDocumentationByEmailButton" class="hidden-block">
                                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                        </span>
                                                        <span id="ProcessingMsgSendDocumentationByEmailButton" class="label-message" role="status" aria-hidden="true">{l s='Send Documentation by email' mod='correosoficial'}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                        </div>
                        
                    </div>
                    <div class="col-sm-6">
                    
                        <div class="alert alert-success alert-dismissible fade show hidden-block" role="alert" id="success_register_return_email">
                            <strong>{l s='Done operation' mod='correosoficial'}:</strong>
                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="alert alert-danger alert-dismissible fade show hidden-block" role="alert" id="error_register_return_email">
                            <strong>{l s='Done operation' mod='correosoficial'}:</strong>
                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div id="cancel-return-container" class="row {if !$exist_return}hidden-block{/if}">
                            <!--  Recogidas de devoluciones -->
                            <div class="col-sm-12 {if $pickup_return == 0}hidden-block{/if}" id="general-return-pickup-container">

                            <div class="card {if !$saved_return_pickup}hidden-block{/if}" id="data-return-pickup-container">
                                    <div class="card-header card-header-date">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check custom-icon" viewBox="0 0 16 16">
                                            <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"></path>
                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"></path>
                                        </svg>
                                        <span>{l s='Return pickup data' mod='correosoficial'}</span>
                                    </div>

                                    <div class="card-body">
                                        <div class="row {if $pickup_return == 0 && $carrier_order['company'] == "Correos"}hidden-block{/if}">
                                            
                                            {if $saved_return_pickup}
                                            <div class="col-sm-6">
                                                <p>{l s='Return pickup code' mod='correosoficial'}: <br><span class="pickup-codSolicitud">{$pickup_return_number}</span></p>
                                            </div>
                                            {/if}

                                            <div class="col-sm-6">
                                                <p>{l s='status' mod='correosoficial'}: <br>
                                                    <span id="pickup-status-return" class="pickup-status">{$pickup_return_data_response['status']}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <button id="cancel_return_pickup" class="btn btn-lg co_primary_button {if $carrier_type == 'CEX'} hidden-block {/if}" type="button"  {if $pickup_return_cancelable}disabled{/if}>
                                                    <span id="processingCancelReturnPickupButtonMsg" class="hidden-block">
                                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                        <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                    </span>
                                                    <span id="returnPickupCancelButtonMsg" role="status" aria-hidden="true">{l s='Cancel pickup' mod='correosoficial'}</span>
                                                </button>
                                            </div>
                                            <div class="col-sm-6">
                                                <p>{l s='Pickup data' mod='correosoficial'}: <br>
                                                    <span class="pickup-data">{$pickup_return_data_response['pickup_date']}

                                                        {if isset($pickup_return_data_response['pickup_from_hour'])}
                                                            {$pickup_return_data_response['pickup_from_hour']}
                                                        {/if}

                                                        {if isset($pickup_return_data_response['pickup_to_hour'])}
                                                            {$pickup_return_data_response['pickup_to_hour']}
                                                        {/if}
                                                        
                                                    </span><br>
                                                    <span class="pickup-data">{$pickup_return_data_response['pickup_address']}</span><br>
                                                    <span class="pickup-data">{$pickup_return_data_response['pickup_city']}</span><br>
                                                    <span class="pickup-data">{$pickup_return_data_response['pickup_cp']}</span><br>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>  
                    </div>
                </div>

                <div class="row container-bultos-return {if $exist_return}hidden-block{/if}">
                    <!-- BULTO -->
                    {for $bulto=1 to $bultos_return}
                    <div id="containerBultoReturn_{$bulto}" class="col-sm-6 container-bulto-return">
                        <div class="card card-custom">
                            <div class="card-header card-header-date">
                                <span>{l s='Package return' mod='correosoficial'} {$bulto}</span>
                            </div>
                            <div class="card-body">
                                
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text-bulto">{l s='Weight' mod='correosoficial'} (Kg)</span>
                                            </div>
                                            <input type="text" id="packageWeightReturn_{$bulto}" name="packageWeightReturn_{$bulto}" class="form-control input-bulto text-center">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text-bulto">{l s='Long' mod='correosoficial'} (cm)</span>
                                            </div>
                                            <input type="text" id="packageLargeReturn_{$bulto}" name="packageLargeReturn_{$bulto}" class="form-control input-bulto text-center" >
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text-bulto">{l s='Width' mod='correosoficial'} (cm)</span>
                                            </div>
                                            <input type="text" id="packageWidthReturn_{$bulto}" name="packageWidthReturn_{$bulto}" class="form-control input-bulto text-center" >
                                        </div>
                                    </div>

                                    <div class="col-sm-4">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text-bulto">{l s='Height' mod='correosoficial'} (cm)</span>
                                            </div>
                                            <input type="text" id="packageHeightReturn_{$bulto}" name="packageHeightReturn_{$bulto}" class="form-control input-bulto text-center" >
                                        </div>
                                    </div>
                                </div>

                                <div id="customs_correos_container_return" class=" mt-3 customs-correos-container customs-correos-container-return {if !$return_require_customs_doc || $active_client == "CEX"}hidden-block{/if}">
                                    <div class="row">
                                        <div class="col-sm-12 mr-0 pr-0">
                                            <p class="text-inputs-customs-info">{l s='Customs related data' mod='correosoficial'}</p>
                                        </div>
                                    </div>

                                    <div id="tabs_customs_doc_{$bulto}" class="tabs_customs_doc" class="row">
                                        <ul class="nav nav-pills">
                                            <li class="nav-item">
                                              <a id="customs_desc_{$bulto}" class="nav-link  {if $config_default_aduanera == 0}active{/if} customs_desc" data-type="customs_desc"  data-number="{$bulto}" aria-current="page" href="#">
                                                {l s='Customs default description' mod='correosoficial'}
                                            </a>
                                            </li>
                                            <li class="nav-item">
                                              <a id="customs_code_{$bulto}" class="nav-link {if $config_default_aduanera == 1}active{/if} customs_code" data-type="customs_code" data-number="{$bulto}" href="#">
                                                {l s='Tariff Code' mod='correosoficial'}
                                              </a>
                                            </li>
                                          </ul>
                                    </div>

                                    <div id="customs_desc_tab_{$bulto}"class="row content-tab {if $config_default_aduanera == 1}hidden-block{/if} ">
                                        <div class="col-sm-12 mb-3">
                                            <div class="input-group input-group-custom-bottom">
                                                <div class="input-group-addon input-group-text-custom">
                                                        <input class="form-check-input" type="hidden" id="DescriptionRadioDesc_{$bulto}" name="DescriptionRadioReturn_{$bulto}" class="DescriptionRadio hidden-block" value="0" {if $config_default_aduanera == 0}checked{/if}>
                                                    <span class="input-group-text-bulto input-group-text-bulto-radio">
                                                        {l s='Customs default description' mod='correosoficial'}
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Para envíos que requieran una desscripción del contenido o trámites aduaneros.">
                                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <select class="form-select select-bulto" id="packageCustomDesc_{$bulto}" name="packageCustomDesc_{$bulto}">
                                                    {html_options options=$customs_desc_array selected=$customs_desc_selected}
                                                </select>
                                            </div>
                                        </div>
                                     </div>

                                     <div id="customs_code_tab_{$bulto}" class="row content-tab customs_code_tab{if $config_default_aduanera == 0}hidden-block{/if}">
                                        <div class="col-sm-5">
                                            <div class="input-group input-group-custom-bottom">
                                                        <input class="form-check-input" type="hidden" id="DescriptionRadioTariff_{$bulto}" name="DescriptionRadio_{$bulto}" class="DescriptionRadio hidden-block" value="1" {if $config_default_aduanera == 1}checked{/if}>
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text-bulto input-group-text-bulto-radio">
                                                        {l s='Tariff' mod='correosoficial'}
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Para envíos que requieran una desscripción del contenido o trámites aduaneros.">
                                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control input-bulto" id="packageTariffCode_{$bulto}" name="packageTariffCode_{$bulto}" value="{$customs_tariff_selected}" maxlength="10"/>
                                            </div>
                                        </div>
                                        <div class="col-sm-7">
                                            <div class="input-group input-group-custom">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text-bulto">
                                                        {l s='Description' mod='correosoficial'}
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Para envíos que requieran una desscripción del contenido o trámites aduaneros.">
                                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <input type="text" id="packageTariffDesc_{$bulto}" name="packageTariffDesc_{$bulto}" class="form-control input-bulto" value="{$customs_tariff_description}">
                                            </div>
                                        </div>
                                     </div>

                                    <div class="row customs-descriptions-details2">
                                        <div class="col-sm-4">
                                            <div class="input-group input-group-custom">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <label class="input-group-text-bulto">{l s='Net value' mod='correosoficial'} (€)</label>
                                                </div>
                                                <input type="number" id="packageAmount_{$bulto}" name="packageAmount_{$bulto}" class="form-control input-bulto text-center"  value="{$orderTotalValue|string_format:"%.2f"}" min="0">
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="input-group input-group-custom">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text-bulto">{l s='Weight' mod='correosoficial'} (Kg)</span>
                                                </div>
                                                <input type="number" id="packageWeightDesc_{$bulto}" name="packageWeightDesc_{$bulto}" class="form-control input-bulto text-center" value="" min="0">
                                            </div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="input-group input-group-custom">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text-bulto">{l s='Units' mod='correosoficial'}</span>
                                                </div>
                                                <input type="number" id="packageUnits_{$bulto}" name="packageUnits_{$bulto}" class="form-control input-bulto text-center" value="" min="1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row add_and_delete_buttons">

                                        <div class="row ml-0">
                                            <p>{l s='Max. 5 Descriptions' mod='correosoficial'}</p>
                                            <div id="added_customs_description_{$bulto}" class="col-sm-12 added_customs_description"></div>
                                            <button id="add_description_{$bulto}" class="col-sm-4 add_description" data-number="{$bulto}">{l s='Add and save' mod='correosoficial'}</button>
                                            <button id="del_description_{$bulto}" class="col-sm-4 del_description" data-number="{$bulto}" disabled>{l s='Delete' mod='correosoficial'}</button>
                                        </div>
                                    </div>

                                </div><!-- correos_custom-container -->

                            </div>
                        </div>
                    </div>
                    
                    {/for}
                    <!-- FIN BULTO -->
                
                </div>
                <!-- RECOGIDA DE LA DEVOLUCIÓN -->
                <div class="col-sm-6">
                    <div class="card {if $carrier_type == '' || ($carrier_type == "Correos" && !$exist_return)}hidden-block{/if}{if $pickup_return == 1}hidden-block{/if}" id="save-return-pickup-container">
                        <div class="card-header card-header-date">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar custom-icon" viewBox="0 0 16 16">
                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                            </svg>
                            <span>{l s='Generate return pickup' mod='correosoficial'}</span>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='Pickup date' mod='correosoficial'}</span>
                                        </div>
                                        <input type="date" id="return_pickup_date" name="return_pickup_date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row pickup-date-container-registered">
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='From' mod='correosoficial'}</span>
                                        </div>
                                        
                                        {if $default_sender}
                                        <input type="time" id="return_sender_from_time" name="return_sender_from_time" class="form-control" value="{$default_sender.sender_from_time}">
                                        {/if}
                                        
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='To' mod='correosoficial'}</span>
                                        </div>
                                        
                                        {if $default_sender}
                                        <input type="time" id="return_sender_to_time" name="return_sender_to_time" class="form-control" value="{$default_sender.sender_to_time}">
                                        {/if}

                                    </div>
                                </div>
                            </div>

                            <div id="correos-options-pickup-return-container" class="{if $carrier_type != "Correos"}hidden-block{/if}">
                                <div class="input-group mb-3">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="checkbox" name="return_print_label" id="return_print_label" {if $bultos > 5}disabled{/if}>
                                    </div>
                                    <span class="input-group-text input-group-checkbox">{l s='Request labelling from Correos' mod='correosoficial'}</span>
                                </div>                                            
                                
                                <div class="alert alert-danger alert-dismissible fade show alert-more-5-labels {if $bultos <= 5}hidden-block{/if}" role="alert">
                                    <strong>{l s='Request labelling from Correos' mod='correosoficial'}:</strong> {l s='Not available for shipments with more than 5 packages' mod='correosoficial'}
                                </div>
                                                                        
                                <div class="input-group input-group-custom">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text">{l s='Package size' mod='correosoficial'}</span>
                                    </div>
                                    <select class="form-select" id="return_package_type" name="return_package_type">
                                        <option selected="" disabled="" value="">&nbsp;</option>
                                        <option value="10">{l s='Envelopes' mod='correosoficial'}</option>
                                        <option value="20">{l s='Small (shoebox)' mod='correosoficial'}</option>
                                        <option value="30">{l s='Medium (Folio box)' mod='correosoficial'}</option>
                                        <option value="40">{l s='Large (box 80x80x80cm)' mod='correosoficial'}</option>
                                        <option value="50">{l s='Very large (larger than box 80x80x80cm)' mod='correosoficial'}</option>
                                        <option value="60">{l s='Pallet' mod='correosoficial'}</option>
                                    </select>
                                </div>
                            </div>
                            
                            
                            <input type="hidden" id="customer_cp" name="customer_cp" value="{$address->postcode}" />
                           
                            {if isset($pais)}
                            <input type="hidden" id="customer_country" name="customer_country" value="{$pais.iso_code}" />
                            {/if}

                            <button id="generate_return_pickup" class="btn btn-lg co_primary_button {if $carrier_type != "Correos"}hidden-block {/if}" type="button">
                                <span id="processingReturnPickupButtonMsg" class="hidden-block">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                </span>
                                <span id="returnPickupButtonMsg" role="status" aria-hidden="true">{l s='Generate return pickup' mod='correosoficial'}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- FIN RECOGIDA DE LA DEVOLUCIÓN -->
                <div class="row">
                    <div class="col-sm-6 mb-4">
                        <button id="generateReturnButton" class="btn btn-lg co_primary_button {if $exist_return}hidden-block{/if}" type="button" {if empty($default_sender) || empty($carriers)}disabled{/if}>
                            <span id="processingReturnButtonMsg" class="hidden-block">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                            </span>
                            <span id="generateReturnButtonMsg" role="status" aria-hidden="true">{l s='Generate return' mod='correosoficial'}</span>
                        </button>
                        <button id="cancelReturnButton" class="btn btn-lg co_primary_button {if !$exist_return}hidden-block{/if}" type="button" {if !$return_cancelable}disabled{/if}>
                            <span id="processingCancelReturnButtonMsg" class="hidden-block">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                            </span>
                            <span id="cancelReturnButtonMsg" role="status" aria-hidden="true">{l s='Cancel return' mod='correosoficial'}</span>
                        </button>
                    </div>
                    <div class="col-sm-6">
                        <div class="alert alert-danger alert-dismissible fade show hidden-block" role="alert" id="error_register_return">
                            <strong>{l s='Error while registering' mod='correosoficial'}:</strong>
                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="alert alert-success alert-dismissible fade show hidden-block" role="alert" id="success_register_return">
                            <strong>{l s='Done operation' mod='correosoficial'}:</strong>
                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- FIN BLOQUE DEVOLUCIONES -->
