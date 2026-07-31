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
<!-- INICIO BLOQUE DESTINATARIO -->
<div class="card">

    <div class="card-header card-header-blue">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-bounding-box custom-icon icon-margin-top" viewBox="0 0 16 16">
        <path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1h-3zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5zM.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5zm15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5z"/>
        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
        </svg>
        <h3 class="card-header-title">{l s='Recipient Data' mod='correosoficial'}</h3>
    </div>

    <div class="card-body" id="container_customer">
        <div class="row">
            <div class="col-sm-6">
                
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Name' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_firstname" name="customer_firstname" class="form-control" value="{$address->firstname}">
                </div>
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Surnames' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_lastname" name="customer_lastname" class="form-control" value="{$address->lastname}">
                </div>
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Company' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_company" name="customer_company" class="form-control" value="{$address->company}">
                </div>
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Contact person' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_contact" name="customer_contact" class="form-control">
                </div>
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Address' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_address" name="customer_address" class="form-control" value="{$address->address1} {$address->address2}" >
                </div>
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='City' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_city" name="customer_city" class="form-control" value="{$address->city}" >
                </div>
            </div>

            <div class="col-sm-6">

                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Country' mod='correosoficial'}</span>
                    </div>
                    <select class="form-select" id="customer_country" name="customer_country" >
                        <option disabled value="">{l s='Select a country' mod='correosoficial'}</option>
                        {foreach from=$countries item=pais}
                            {if $pais.id_country == $address->id_country}
                                    <option value="{$pais.iso_code}" selected>{$pais.name|escape:'html':'UTF-8'}</option>
                                {else}
                                    <option value="{$pais.iso_code}">{$pais.name|escape:'html':'UTF-8'}</option>
                            {/if}
                        {/foreach}
                    </select>
                </div>

                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">
                            {l s='Phone number' mod='correosoficial'}
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Obligatorio en los envíos de Correos Express">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                        </span>
                    </div>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control" value="{$address->phone}">
                </div>

                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Email' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_email" name="customer_email" class="form-control" value="{$customer->email}">
                </div>

                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='NIF/CIF' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_dni" name="customer_dni" class="form-control" value="{$address->dni}">
                </div>

                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Postal code' mod='correosoficial'}</span>
                    </div>
                    <input type="text" id="customer_cp" name="customer_cp" class="form-control" value="{$address->postcode}">
                </div>
                
            </div>

        </div>
    </div>
</div>
<!-- FIN BLOQUE DESTINATARIO -->