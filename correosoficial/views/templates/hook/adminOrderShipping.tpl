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
<div class="card">

    <div class="card-header card-header-blue">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square custom-icon icon-margin-top" viewBox="0 0 16 16">
    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"></path>
    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"></path>
    </svg>
        <h3 class="card-header-title">{l s='Shipping data' mod='correosoficial'}</h3>
    </div>

    <div class="card-body" id="container_shipping">
    <div id="contentCopied" class="notification-popup hidden-block">{$contentCopied}</div>
        <div class="row">
            <div class="col-sm-6">
                
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">
                            {l s='Billing customer code' mod='correosoficial'}
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Cliente al que se facturará el envío.">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                        </span>
                    </div>
                    <input type="text" id="client_code" name="client_code" class="form-control" value="{$client_code}" disabled>
                </div>

                {if empty($carriers)}
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>{l s='Carriers' mod='correosoficial'}:</strong> {l s='You have not configured any carrier' mod='correosoficial'}
                        <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                {else}
                    <input type="hidden" id="id_zone" name="id_zone" value="{$id_zone}" />

                    <div class="input-group input-group-custom">
                        <div class="input-group-addon input-group-text-custom">
                            <span class="input-group-text">{l s='Carrier' mod='correosoficial'}</span>
                        </div>
                        <select class="form-select" id="input_select_carrier" name="input_select_carrier" >
                            {if $carrier_order['id_carrier'] == ""}
                                <option data-id_product="0" 
                                    data-id_carrier="0" 
                                    data-company="0" 
                                    data-carrier_type="0" 
                                    data-max_packages="0" 
                                    data-client_code="0" 
                                    data-correos_key="0" 
                                    value="0" 
                                    disabled selected>{l s='Select a product' mod='correosoficial'}</option>
                            {/if}
                            {foreach from=$carriers item=carrier} 
                                {if $carrier_order['codigoProducto'] == $carrier.codigoProducto}
                                    <option data-id_product="{$carrier.my_id}" 
                                            data-id_carrier="{$carrier_order['id_carrier']}" 
                                            data-company="{$carrier.company}" 
                                            data-carrier_type="{$carrier.product_type}" 
                                            data-max_packages="{$carrier.max_packages}" 
                                            data-client_code="0" 
                                            data-correos_key="0" 
                                            value="{$carrier.codigoProducto}" 
                                            selected >
                                                {$carrier.name}
                                    </option>
                                {else}
                                    <option data-id_product="{$carrier.my_id}" 
                                            data-company="{$carrier.company}" 
                                            data-carrier_type="{$carrier.product_type}" 
                                            data-max_packages="{$carrier.max_packages}" 
                                            data-client_code="0" 
                                            data-correos_key="0" 
                                            value="{$carrier.codigoProducto}">
                                                {$carrier.name}
                                    </option>
                                {/if}
                            {/foreach} 
                        </select>
                    </div>
                {/if}

                <input type="hidden" id="cod_homepaq" name="cod_homepaq" class="form-control" value="{$cod_homepaq}" >    
                <input type="hidden" id="cod_office" name="cod_office" class="form-control" value="{$cod_office}" >
                <input type="hidden" id="cod_pudocex" name="cod_pudocex" class="form-control" value="{$cod_pudocex}" >    

                <!-- OFICINA --> 
                <div class="col-sm-12 office-container {if $carrier_order['product_type'] != "office"}hidden-block{/if}">
                    <div class="card card-custom">
                        <div class="card-header card-header-date">
                            <span>{l s='SELECTED OFFICE' mod='correosoficial'}</span>
                            <button type="button" id="changeOffice" class="co_change_paq_button">{l s='Change office' mod='correosoficial'}</button>
                            <button type="button" id="copyOfficeContent" class="co_change_paq_button">
                                <svg width="24px" height="18px" viewBox="-2.64 -2.64 29.28 29.28" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g id="SVGRepo_bgCarrier" stroke-whiteidth="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#fdfcfc" stroke-whiteidth="0.048" stroke-width="1.584"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g><g id="SVGRepo_iconCarrier"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="change-container-office">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group mb-3">
                                            <input type="text" id="input_cp_office" name="input_cp_office" class="form-control append-input" placeholder="{l s='Postal code' mod='correosoficial'}">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="searchOfficeButton" type="button">{l s='Search' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="office_cart_total"
                                            id="office_cart_total" value="{$cart_total}" />
                                        <input type="hidden" name="office_total_weight"
                                            id="office_total_weight" value="{$orderWeight}" />
                                        <input type="hidden" name="office_country"
                                            id="office_country" value="{$customer_country}" />
                                    </div>
                                    <div class="col-sm-12 mb-3" id="office-list">
                                        <div class="input-group">
                                            <select class="custom-select append-input" id="inputSelectOffices" name="inputSelectOffices">
                                                <option selected>{l s='Offices found' mod='correosoficial'}</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="selectOfficeButton" type="button">{l s='Choose office' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-sm-12">
                                        <div class="alert alert-warning alert-dismissible fade show hidden-block" role="alert" id="no_offices_zip_message">
                                            <strong>{l s='Office error' mod='correosoficial'}:</strong> {l s='No offices found' mod='correosoficial'}
                                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-8">
                                        <div id="mapOffice" class="map"></div>
                                    </div>
                                    <div class="map-info-office col-sm-4 pl-0">
                                        <h3>{l s='Office information' mod='correosoficial'}</h3>
                                        <p id="hor-office">-</p>
                                        <p id="dir-office"></p>
                                        <p id="loc-office"></p>
                                        <p id="cp-office"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group input-group-custom">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text">{l s='Address' mod='correosoficial'}</span>
                                </div>
                                <input type="text" id="office_address" name="office_address" class="form-control" value="{$address_paq['dir_paq']}" readonly>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='City' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="office_city" name="office_city" class="form-control" value="{$address_paq['loc_paq']}" disabled>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='Postal code' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="office_cp" name="office_cp" class="form-control" value="{$address_paq['cp_paq']}" disabled>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- CITYPAQ -->
                <div class="col-sm-12 citypaq-container {if $carrier_order['product_type'] != "citypaq"}hidden-block{/if}">
                    <div class="card card-custom">
                        <div class="card-header card-header-date">
                            <span>{l s='CityPAQ SELECTED' mod='correosoficial'}</span>
                            <button type="button" id="changeCityPaq" class="co_change_paq_button">{l s='Change CityPAQ' mod='correosoficial'}</button>
                            <button type="button" id="copyCityPaqContent" class="co_change_paq_button">
                                <svg width="24px" height="18px" viewBox="-2.64 -2.64 29.28 29.28" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g id="SVGRepo_bgCarrier" stroke-whiteidth="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#fdfcfc" stroke-whiteidth="0.048" stroke-width="1.584"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g><g id="SVGRepo_iconCarrier"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                            </button>
                        </div>
                        
                        <div class="card-body">
                        
                            <div class="change-container-citypaq">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="input-group mb-3">
                                            <input type="text" id="input_cp_citypaq" name="input_cp_citypaq" class="form-control append-input" placeholder="{l s='Postal code' mod='correosoficial'}">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="searchCityPaqButton" type="button">{l s='Search' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="citypaq_cart_total"
                                            id="citypaq_cart_total" value="{$cart_total}" />
                                        <input type="hidden" name="citypaq_total_weight"
                                            id="citypaq_total_weight" value="{$orderWeight}" />
                                        <input type="hidden" name="citypaq_country"
                                            id="citypaq_country" value="{$customer_country}" />
                                    </div>
                                    <div class="col-sm-12 mb-3" id="citypaq-list">
                                        <div class="input-group">
                                            <select class="custom-select append-input" id="inputSelectCityPaqs">
                                                <option selected>{l s='CityPAQ found' mod='correosoficial'}</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="selectCityPaqButton" type="button">{l s='Choose CityPAQ' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="alert alert-warning alert-dismissible fade show hidden-block" role="alert" id="no_citypaqs_zip_message">
                                            <strong>{l s='Citypaq error' mod='correosoficial'}:</strong> {l s='No citypaqs found' mod='correosoficial'}
                                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-8">
                                        <div id="mapCityPaq" class="map"></div>
                                    </div>
                                    <div class="map-info-citypaq col-sm-4 pl-0">
                                        <h3>{l s='CityPAQ information' mod='correosoficial'}</h3>
                                        <p id="hor-citypaq">-</p>
                                        <p id="dir-citypaq"></p>
                                        <p id="loc-citypaq"></p>
                                        <p id="cp-citypaq"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group input-group-custom">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text">{l s='Address' mod='correosoficial'}</span>
                                </div>
                                <input type="text" id="citypaq_address" name="citypaq_address" class="form-control" value="{$address_paq['dir_paq']}" disabled>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='City' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="citypaq_city" name="citypaq_city" class="form-control" value="{$address_paq['loc_paq']}" disabled>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='Postal code' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="citypaq_cp" name="citypaq_cp" class="form-control" value="{$address_paq['cp_paq']}" disabled>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- PUDO CEX --> 
                <div class="col-sm-12 pudocex-container {if $carrier_order['product_type'] != "pudocex"}hidden-block{/if}">
                    <div class="card card-custom">
                        <div class="card-header card-header-date">
                            <span>{l s='SELECTED PUDO' mod='correosoficial'}</span>
                            <button type="button" id="changePudoCEX" class="co_change_paq_button">{l s='Change PUDO' mod='correosoficial'}</button>
                            <button type="button" id="copyPudoCEXContent" class="co_change_paq_button">
                                <svg width="24px" height="18px" viewBox="-2.64 -2.64 29.28 29.28" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g id="SVGRepo_bgCarrier" stroke-whiteidth="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#fdfcfc" stroke-whiteidth="0.048" stroke-width="1.584"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g><g id="SVGRepo_iconCarrier"> <path d="M10 8V7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5H17C17.9428 5 18.4142 5 18.7071 5.29289C19 5.58579 19 6.05719 19 7V12C19 12.9428 19 13.4142 18.7071 13.7071C18.4142 14 17.9428 14 17 14H16M7 19H12C12.9428 19 13.4142 19 13.7071 18.7071C14 18.4142 14 17.9428 14 17V12C14 11.0572 14 10.5858 13.7071 10.2929C13.4142 10 12.9428 10 12 10H7C6.05719 10 5.58579 10 5.29289 10.2929C5 10.5858 5 11.0572 5 12V17C5 17.9428 5 18.4142 5.29289 18.7071C5.58579 19 6.05719 19 7 19Z" stroke="white" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="change-container-pudocex">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="input-group mb-3">
                                            <input type="text" id="input_cp_pudocex" name="input_cp_pudocex" class="form-control append-input" placeholder="{l s='Postal code' mod='correosoficial'}">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="searchPudocexButton" type="button">{l s='Search' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="pudocex_cart_total"
                                            id="pudocex_cart_total" value="{$cart_total}" />
                                        <input type="hidden" name="pudocex_total_weight"
                                            id="pudocex_total_weight" value="{$orderWeight}" />
                                        <input type="hidden" name="pudocex_country"
                                            id="pudocex_country" value="{$customer_country}" />
                                    </div>
                                    <div class="col-sm-12 mb-3" id="pudocex-list">
                                        <div class="input-group">
                                            <select class="custom-select append-input" id="inputSelectPudocexs" name="inputSelectPudocexs">
                                                <option selected>{l s='PUDOs found' mod='correosoficial'}</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary append-button" id="selectPudocexButton" type="button">{l s='Choose PUDO' mod='correosoficial'}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="alert alert-warning alert-dismissible fade show hidden-block" role="alert" id="no_pudocexs_zip_message">
                                            <strong>{l s='PUDO error' mod='correosoficial'}:</strong> {l s='No PUDOs found' mod='correosoficial'}
                                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-8">
                                        <div id="mapPudoCEX" class="map"></div>
                                    </div>
                                    <div class="map-info-pudocex col-sm-4 pl-0">
                                        <h3>{l s='PUDO information' mod='correosoficial'}</h3>
                                        <p id="hor-pudocex">-</p>
                                        <p id="dir-pudocex"></p>
                                        <p id="loc-pudocex"></p>
                                        <p id="cp-pudocex"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group input-group-custom">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text">{l s='Address' mod='correosoficial'}</span>
                                </div>
                                <input type="text" id="pudocex_address" name="pudocex_address" class="form-control" value="{$address_paq['dir_paq']}" disabled>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='City' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="pudocex_city" name="pudocex_city" class="form-control" value="{$address_paq['loc_paq']}" disabled>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='Postal code' mod='correosoficial'}</span>
                                        </div>
                                        <input type="text" id="pudocex_cp" name="pudocex_cp" class="form-control" value="{$address_paq['cp_paq']}" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WIP -->
                <input type="hidden" id="request_data" name="request_data" value="{$request_data|escape:'html'}"/>
                <input type="hidden" id="reference_code" name="reference_code" value="{$reference_code}"/>
                
            </div>

            <div class="col-sm-6">
                
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Shipping reference' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="order_reference" name="order_reference" class="form-control" value="{$ship_reference}">
                    <input type="hidden" id="order_number" name="order_number" class="form-control" value="{$order_id}">
                </div>
                
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Total Weight' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="total_weight" name="total_weight" class="form-control" value="{$orderWeight}">
                </div>

                <div id="require_customs_doc"class="input-group input-group-custom {if $carrier_order['company'] == 'CEX' || !$require_customs_doc}hidden-block{/if}">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Customs reference of consignor' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="custom_ref_exp" name="custom_ref_exp" class="form-control" value="{$customs_reference}">
                </div>

                <input type="hidden" id="require_customs_doc" name="require_customs_doc" value="{if $require_customs_doc}1{else}0{/if}"/>

                <div id="code_at_container" class="input-group input-group-custom {if !$is_code_at}hidden-block{/if}">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='AT code' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="code_at" name="code_at" class="form-control" value="{$correos_order['AT_code']}" > 
                </div>

            </div>

            <div class="col-sm-12">
                
                <div class="alert alert-warning alert-dismissible fade show alert-max-packages hidden-block" role="alert">
                    <strong>{l s='Package alert: The selected carrier only allows a single package' mod='correosoficial'}</strong>
                    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="card card-custom">
                    <div class="card-header card-header-date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-boxes custom-icon icon-margin-top" viewBox="0 0 16 16">
                            <path d="M7.752.066a.5.5 0 0 1 .496 0l3.75 2.143a.5.5 0 0 1 .252.434v3.995l3.498 2A.5.5 0 0 1 16 9.07v4.286a.5.5 0 0 1-.252.434l-3.75 2.143a.5.5 0 0 1-.496 0l-3.502-2-3.502 2.001a.5.5 0 0 1-.496 0l-3.75-2.143A.5.5 0 0 1 0 13.357V9.071a.5.5 0 0 1 .252-.434L3.75 6.638V2.643a.5.5 0 0 1 .252-.434L7.752.066ZM4.25 7.504 1.508 9.071l2.742 1.567 2.742-1.567L4.25 7.504ZM7.5 9.933l-2.75 1.571v3.134l2.75-1.571V9.933Zm1 3.134 2.75 1.571v-3.134L8.5 9.933v3.134Zm.508-3.996 2.742 1.567 2.742-1.567-2.742-1.567-2.742 1.567Zm2.242-2.433V3.504L8.5 5.076V8.21l2.75-1.572ZM7.5 8.21V5.076L4.75 3.504v3.134L7.5 8.21ZM5.258 2.643 8 4.21l2.742-1.567L8 1.076 5.258 2.643ZM15 9.933l-2.75 1.571v3.134L15 13.067V9.933ZM3.75 14.638v-3.134L1 9.933v3.134l2.75 1.571Z"/>
                        </svg>
                        
                        <span>{l s='Packages' mod='correosoficial'}</span>
                        
                        <div class="input-group input-group-custom mt-2">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text-bulto">
                                    {l s='Number of packages' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Indique si su envío se compone de un o más bultos.">
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                    </svg>
                                </span>
                            </div>
                            <select class="form-select select-bulto-principal" id="correos-num-parcels" name="correos-num-parcels" {if $carrier_order['max_packages'] == "1"}disabled{/if}>
                                <option value="1" {if $bultos == 1}selected{/if}>1</option>
                                <option value="2" {if $bultos == 2}selected{/if}>2</option>
                                <option value="3" {if $bultos == 3}selected{/if}>3</option>
                                <option value="4" {if $bultos == 4}selected{/if}>4</option>
                                <option value="5" {if $bultos == 5}selected{/if}>5</option>
                                <option value="6" {if $bultos == 6}selected{/if}>6</option>
                                <option value="7" {if $bultos == 7}selected{/if}>7</option>
                                <option value="8" {if $bultos == 8}selected{/if}>8</option>
                                <option value="9" {if $bultos == 9}selected{/if}>9</option>
                                <option value="10" {if $bultos == 10}selected{/if}>10</option>
                            </select>
                        </div>

                        <div class="input-group input-group-custom all-packages-equal-container {if $bultos == 1 }hidden-block{/if}">
                            <input class="form-check-input mt-0" type="checkbox" name="all_packages_equal" id="all_packages_equal">{l s='All similar packages' mod='correosoficial'}
                        </div>

                    </div>
                    <div class="card-body">
                        <div class="row container-bultos">
                            <!-- BULTO -->
                            {$bulto = 1}
                            {* Para cuando todavía no se ha registrado el envío inicializamos el array *}
                            {if count($array_packages_order) == 0}
                                {$array_packages_order = [1]}
                            {/if}
                            {foreach $array_packages_order as $bulto_info}
                            <div id="containerBulto_{$bulto}" class="col-sm-6 container-bulto-info container-bulto {if $bultos > 1 && $bulto != 1}container-bulto-cloned {/if}">
                                <div class="card card-custom">
                                    <div class="card-header card-header-date">
                                        <span>{l s='Package' mod='correosoficial'} {$bulto}</span>
                                    </div>
                                    <div class="card-body">
                                        
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto">{l s='Reference' mod='correosoficial'}</span>
                                                    </div>
                                                    <input type="text" name="packageRef_{$bulto}" class="form-control input-bulto" {if isset($bulto_info['reference']) && $bulto_info['reference'] !== ''}value="{$bulto_info['reference']}" {else if $bultos == 1 } value="{$ship_reference}" {/if} >
                                                </div>
                                            </div>

                                            <div class="col-sm-4">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto">{l s='Weight' mod='correosoficial'} (Kg)</span>
                                                    </div>
                                                    <input type="text" id="packageWeight_{$bulto}" name="packageWeight_{$bulto}" class="form-control input-bulto text-center" {if isset($bulto_info['weight']) && $bulto_info['weight'] !== ''}value="{$bulto_info['weight']}" {else}value="{$orderWeight}" {/if} disabled>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="row">

                                            <div class="col-sm-4">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto">{l s='Long' mod='correosoficial'} (cm)</span>
                                                    </div>
                                                    <input type="text" name="packageLarge_{$bulto}" class="form-control input-bulto validate-dimensions text-center" {if isset($bulto_info['large']) && $bulto_info['large'] !== '' && $bulto_info['large'] > 0}value="{$bulto_info['large']}"{elseif isset($large_by_default) && $available_carrier_default_dimensions == 1}value="{$large_by_default}"{/if}>
                                                </div>
                                            </div>

                                            <div class="col-sm-4">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto">{l s='Width' mod='correosoficial'} (cm)</span>
                                                    </div>
                                                    <input type="text" name="packageWidth_{$bulto}" class="form-control input-bulto validate-dimensions text-center" {if isset($bulto_info['width']) && $bulto_info['width'] !== '' && $bulto_info['width'] > 0}value="{$bulto_info['width']}"{elseif isset($width_by_default) && $available_carrier_default_dimensions == 1}value="{$width_by_default}"{/if}>
                                                </div>
                                            </div>

                                            <div class="col-sm-4">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto">{l s='High' mod='correosoficial'} (cm)</span>
                                                    </div>
                                                    <input type="text" name="packageHeight_{$bulto}" class="form-control input-bulto validate-dimensions text-center" {if isset($bulto_info['height']) && $bulto_info['height'] !== '' && $bulto_info['height'] > 0}value="{$bulto_info['height']}"{elseif isset($height_by_default) && $available_carrier_default_dimensions == 1}value="{$height_by_default}"{/if}>
                                                </div>
                                            </div>
                                        </div>
                                        {* Guardamos las variables en js para en caso de cambiar el transportista *}
                                        <script>
                                            var large_by_default = "{$large_by_default}";
                                            var width_by_default = "{$width_by_default}";
                                            var height_by_default = "{$height_by_default}";
                                        </script>
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="input-group input-group-custom">
                                                    <div class="input-group-addon input-group-text-custom">
                                                        <span class="input-group-text-bulto observaciones-entrega">{l s='Delivery remarks' mod='correosoficial'}</span>
                                                    </div>
                                                    <textarea class="form-control" name="deliveryRemarks_{$bulto}" rows="2" cols="50" maxlength="80">{if isset($bulto_info['observations']) && $bulto_info['observations'] !== ''}{$bulto_info['observations']}{else}{$customer_message}{/if}</textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="customs_correos_container_shipping" class="customs-correos-container {if !$require_customs_doc || $carrier_order['company'] != "Correos"}hidden-block{/if}">
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
                                                                <input class="form-check-input" type="hidden" id="DescriptionRadioDesc_{$bulto}" name="DescriptionRadio_{$bulto}" class="DescriptionRadio hidden-block" value="0" {if $config_default_aduanera == 0}checked{/if}>
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
                                                        <input type="number" id="packageWeightDesc_{$bulto}" name="packageWeightDesc_{$bulto}" class="form-control input-bulto text-center" value="{$orderWeight}" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="input-group input-group-custom">
                                                        <div class="input-group-addon input-group-text-custom">
                                                            <span class="input-group-text-bulto">{l s='Units' mod='correosoficial'}</span>
                                                        </div>
                                                        <input type="number" id="packageUnits_{$bulto}" name="packageUnits_{$bulto}" class="form-control input-bulto text-center" value="{$orderUnits}" min="1">
                                                    </div>
                                                </div>
                                                <div class="col-sm-12">
                                                    <div class="input-group input-group-custom">
                                                        <div class="input-group-addon input-group-text-custom">
                                                            <label class="input-group-text-bulto">{l s='Country origin of the goods' mod='correosoficial'}</label>
                                                        </div>
                                                        <input type="text" id="CountryOrigyn_{$bulto}" name="CountryOrigyn_{$bulto}" class="form-control input-bulto text-center" value="{$CountryOriginByDefault}" maxlength="3">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row add_and_delete_buttons">
    
                                                <div class="row ml-0">
                                                    <p>{l s='Max. 5 Descriptions' mod='correosoficial'}</p>
                                                    
                                                    <div id="added_customs_description_{$bulto}" class="col-sm-12 added_customs_description">
                                                        {* Mostrar datos de aduanas de los productos si está configurado *}
                                                        {if $products_customs_data|@count > 0}
                                                        {foreach from=$products_customs_data item=product_data name=customs_loop}
                                                        <div class="chip-row" style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                                            <input type='text' class='chip col-sm-12' readonly id='customs_desc_shipping_{$bulto}_{$smarty.foreach.customs_loop.index+1}' name='customs_desc_{$bulto}_{$smarty.foreach.customs_loop.index+1}' value="{$product_data.hs_code|default:'-'} • {$product_data.product_name|truncate:30|escape:'html'} • {$product_data.price|string_format:"%.2f"} € • {$product_data.weight|string_format:"%.2f"} Kg • {$product_data.quantity} Unid. • {$product_data.country_origin|upper|default:'-'}" />
                                                            <div class="chip-actions">
                                                                <button type="button" class="chip-edit-btn" title="Editar" style="background: none; border: none; padding: 0; cursor: pointer;">
                                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d3e50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                                                </button>
                                                                <button type="button" class="chip-delete-btn" title="Eliminar" style="background: none; border: none; padding: 0; cursor: pointer;">
                                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d3e50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        {/foreach}
                                                        {/if}
                                                    </div>
                                                    <button id="add_description_{$bulto}" class="col-sm-4 add_description" data-number="{$bulto}">{l s='Add and save' mod='correosoficial'}</button>
                                                    <button id="del_description_{$bulto}" class="col-sm-4 del_description" data-number="{$bulto}" disabled>{l s='Delete' mod='correosoficial'}</button>
                                                </div>
                                            </div>

                                        </div><!-- correos_custom-container -->
                                        
                                    </div>
                                </div>
                            </div>
                            {$bulto = $bulto + 1}
                            {/foreach}
                            <!-- FIN BULTO -->

                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Inicializar contadores de descripciones de aduanas considerando las descripciones de productos automáticas
    $('.added_customs_description').each(function() {
        var container = $(this);
        var containerId = container.attr('id');
        var bultoNumber = containerId.split('_').pop();
        var existingDescriptions = container.find('input.chip[readonly]').length;
        if (existingDescriptions > 0 && typeof co_DescriptionCounter !== 'undefined') {
            co_DescriptionCounter[bultoNumber] = existingDescriptions + 1;
        }
    });

    // Eliminar descripción
    $(document).on('click', '.chip-delete-btn', function() {
        var chipRow = $(this).closest('.chip-row');
        var input = chipRow.find('input.chip');
        // Intentar decrementar el contador correspondiente si existe
        try {
            var id = input.attr('id') || '';
            // id formato: customs_desc[_shipping|_return]_bulto_index o customs_desc(type)_clone_index
            var segments = id.split('_');
            var possibleBulto = segments.length >= 3 ? segments[segments.length - 2] : null;
            if (typeof co_DescriptionCounter !== 'undefined' && possibleBulto) {
                var bId = possibleBulto;
                if (co_DescriptionCounter[bId] && co_DescriptionCounter[bId] > 0) {
                    co_DescriptionCounter[bId] = co_DescriptionCounter[bId] - 1;
                }
            }
        } catch (e) {
            // ignore
        }
        chipRow.fadeOut(200, function() {
            chipRow.remove();
        });
    });

    // Editar descripción: parsea la chip y rellena los campos correspondientes
    $(document).on('click', '.chip-edit-btn', function() {
        var chipRow = $(this).closest('.chip-row');
        var input = chipRow.find('input.chip');
        var value = input.val() || '';
        var parts = value.split(' • ');
        var bultoId = input.attr('id').split('_')[3];

        // Mapeo esperado de parts:
        // 0: hs_code
        // 1: product_name
        // 2: price ("11.9 €")
        // 3: weight ("0.3 Kg")
        // 4: quantity ("1 Unid.")
        // 5: country_origin

        var hsCode = parts[0] ? parts[0].trim() : '';
        var productName = parts[1] ? parts[1].trim() : '';
        var priceRaw = parts[2] ? parts[2].trim() : '';
        var weightRaw = parts[3] ? parts[3].trim() : '';
        var qtyRaw = parts[4] ? parts[4].trim() : '';
        var country = parts[5] ? parts[5].trim() : '';

        // Extraer números de price, weight y quantity
        function extractNumber(str) {
            if (!str) return '';
            var num = str.replace(/[^0-9,\.\-]/g, '').replace(',', '.');
            return num === '' ? '' : parseFloat(num);
        }

        var price = extractNumber(priceRaw);
        var weight = extractNumber(weightRaw);
        var qty = extractNumber(qtyRaw);

        // Rellenar campos (si existen)
        var $tariffCode = $('#packageTariffCode_' + bultoId);
        var $tariffDesc = $('#packageTariffDesc_' + bultoId);
        var $amount = $('#packageAmount_' + bultoId);
        var $weight = $('#packageWeightDesc_' + bultoId);
        var $units = $('#packageUnits_' + bultoId);
        var $country = $('#CountryOrigyn_' + bultoId);

        if ($tariffCode.length) $tariffCode.val(hsCode === '-' ? '' : hsCode);
        if ($tariffDesc.length) $tariffDesc.val(productName);
        if ($amount.length && price !== '') $amount.val(price);
        if ($weight.length && weight !== '') $weight.val(weight);
        if ($units.length && qty !== '') $units.val(qty);
        if ($country.length) $country.val(country === '-' ? '' : country);

        // Eliminar la chip editada para evitar duplicados al volver a añadirla
        if (typeof co_DescriptionCounter !== 'undefined') {
            try {
                // Si existe un contador, decrementarlo para el bulto
                if (co_DescriptionCounter[bultoId] && co_DescriptionCounter[bultoId] > 0) {
                    co_DescriptionCounter[bultoId] = co_DescriptionCounter[bultoId] - 1;
                }
            } catch (e) {
                // no hacer nada si el array no existe o acceso falla
            }
        }
        chipRow.fadeOut(150, function() { $(this).remove(); });
    });
});
</script>

<script>
jQuery(document).ready(function($) {
    // If module features are enabled or mapping is configured, populate Units inputs
    var useModule = "{if isset($UseModuleFeatures)}{$UseModuleFeatures|escape:'htmlall':'UTF-8'}{/if}";
    var mappedHs = "{if isset($MappedHsFeature)}{$MappedHsFeature|escape:'htmlall':'UTF-8'}{/if}";
    var mappedOrigin = "{if isset($MappedOriginFeature)}{$MappedOriginFeature|escape:'htmlall':'UTF-8'}{/if}";
    var orderUnits = parseInt("{if isset($orderUnits)}{$orderUnits}{else}0{/if}", 10) || 0;

    if (useModule === 'on' || (mappedHs && mappedHs !== '') || (mappedOrigin && mappedOrigin !== '')) {
        // Set every packageUnits_* input to the computed order units
        if (orderUnits > 0) {
            $('input[id^="packageUnits_"]').each(function() {
                try {
                    $(this).val(orderUnits).trigger('change');
                } catch (e) {
                    // ignore
                }
            });
        }
    }
});
</script>