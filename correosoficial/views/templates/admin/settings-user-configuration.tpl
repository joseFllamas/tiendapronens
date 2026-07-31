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
<div id="UserConfigurationBlock" class="accordion-body">
    <form id="UserConfigurationDataForm" name="UserConfigurationDataForm" method="POST" enctype="multipart/form-data">
        <fieldset>
            <div class="row">
                <div id="UserConfigNonMarketplaceBlock" {if $ActivateMarketplace == 'on'}style="display: none;"{/if}>
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Default packages' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Número de bultos predeterminado que se utilizará en el preregistro de envíos">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input type="number" max="10" min="1" name="DefaultPackages" id="DefaultPackages"
                                    value="{$DefaultPackages|escape:'htmlall':'UTF-8'}" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Default label type' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Tipo de etiqueta que se utilizará en la impresión de etiquetas">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <select class="co_dropdown" name="DefaultLabel" id="DefaultLabel">
                                    {html_options options=$select_label_options selected=$DefaultLabel}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Allow modifications (Correos Modify)' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{l s='Correos Modify allows the recipient of your shipments to make eventual modifications to the delivery (address, delivery point, authorized third party…)' mod='correosoficial'}">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                        <small class="notice-small">
                                            {l s='See' mod='correosoficial'}&nbsp;
                                            <a href="https://www.correos.es/content/dam/correos/documentos/legales/modify/Condiciones-de-uso-Modify.pdf" target="_blank">
                                                {l s='Terms and conditions' mod='correosoficial'}
                                            </a>
                                            &nbsp;{l s='and' mod='correosoficial'}&nbsp;
                                            <a href="https://www.correos.es/content/dam/correos/documentos/legales/modify/Politica-de-proteccion-de-datos-Modify.pdf" target="_blank">
                                                {l s='Privacy policy' mod='correosoficial'}
                                            </a>
                                        </small>
                                    </span>
                                </div>
                                <select class="co_dropdown" name="CorreosModify" id="CorreosModify">
                                    {html_options options=$select_correosmodify_options selected=$CorreosModify}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Payment method for COD orders' mod='correosoficial'}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                                class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Los pedidos con la forma de pago seleccionada tienen envío contra reembolso">
                                                <path
                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                            </svg>
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="CashOnDeliveryMethod" id="CashOnDeliveryMethod">
                                        {html_options options=$select_payment_method selected=$payment_method_selected}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4" id="BankAndIBANBlock">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Bank account number / IBAN' mod='correosoficial'} </br>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Sólo para envíos con Correos y método de pago contrareembolso (Módulos Contrareembolso, Codfee, Megareembolso)">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                        <small
                                            class="notice-small">{l s='Required for COD shipments with Correos. Input IBAN without blank spaces' mod='correosoficial'}</small>
                                    </span>
                                </div>
                                <input type="text" name="BankAccNumberAndIBAN" id="BankAccNumberAndIBAN"
                                    value="{$BankAccNumberAndIBAN|escape:'htmlall':'UTF-8'}" class="form-control"
                                    autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Google Maps API Key' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Clave proporcionada por Google. Añada su clave para que los compradores puedan disponer de un mapa al elegir los métodos de envío Oficina o Citypaq en el proceso de compra.">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input type="text" class="form-control" name="GoogleMapsApi" id="GoogleMapsApi"
                                    value="{$GoogleMapsApi|escape:'htmlall':'UTF-8'}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="ActivateTrackingLink"
                                        id="ActivateTrackingLink" {$ActivateTrackingLink|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Activate tracking link in the customer purchase history' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Muestra historial de seguimiento en el perfil del comprador">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="LabelObservations"
                                        id="LabelObservations" {$LabelObservations|escape:'htmlall':'UTF-8'} />
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Order remarks to the label  (max. 80 characters)' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Imprime los comentarios del comprador en la etiqueta de envío">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="CustomerAlternativeText"
                                        id="CustomerAlternativeText"
                                        {$CustomerAlternativeText|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Alternative text for sender' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Se imprimirá en la etiqueta en lugar del nombre del remitente">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div id="LabelAlternativeTextInput" class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span
                                        class="input-group-text input-group-text-color">{l s='Alternative label text' mod='correosoficial'}</span>
                                </div>
                                <input type="text" class="form-control" name="LabelAlternativeText"
                                    id="LabelAlternativeText" value="{$LabelAlternativeText|escape:'htmlall':'UTF-8'}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input id="ActivateWeightByDefault" class="form-check-input mt-0" type="checkbox" name="ActivateWeightByDefault" id="ActivateWeightByDefault" {$ActivateWeightByDefault|escape:'htmlall':'UTF-8'}>
                                </div>
                                <span
                                    class="input-group-text input-group-text-color">{l s='Activate default weight' mod='correosoficial'}</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div id="ActivateWeightByDefaultInput" class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Default weight (Kg)' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="{l s='Weight that is used for shipping in case there is no specified weight.' mod='correosoficial'}">
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                        </svg>
                                    </span>
                                </div>
                                <input type="number" class="form-control" name="WeightByDefault" step="0.1"
                                    id="WeightByDefault" value="{$WeightByDefault|escape:'htmlall':'UTF-8'}" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input id="ActivateDimensionsByDefault" class="form-check-input mt-0"
                                        type="checkbox" name="ActivateDimensionsByDefault"
                                        id="ActivateDimensionsByDefault"
                                        {if isset($ActivateDimensionsByDefault) && $ActivateDimensionsByDefault == true}checked{/if}>
                                </div>
                                <span
                                    class="input-group-text input-group-text-color">{l s='Activate default dimensions' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="{l s='Applies to Paq Ligero, Citypaq delivery (Correos) or Paq Punto, PaqEcommerce (CEX). If your shipments have the same dimensions, you can set them as default. They will remain editable in shipping data to handle exceptions.' mod='correosoficial'}">
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div id="ActivateDimensionsByDefaultBLock" class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Large (cm)' mod='correosoficial'}
                                    </span>
                                </div>
                                <input type="number" class="form-control" name="DimensionsByDefaultLarge"
                                    id="DimensionsByDefaultLarge"
                                    {if isset($DimensionsByDefaultWidth)}value="{$DimensionsByDefaultLarge|escape:'htmlall':'UTF-8'}"
                                    {/if} min="0" step="1">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Width (cm)' mod='correosoficial'}
                                    </span>
                                </div>
                                <input type="number" class="form-control" name="DimensionsByDefaultWidth"
                                    id="DimensionsByDefaultWidth"
                                    {if isset($DimensionsByDefaultWidth)}value="{$DimensionsByDefaultWidth|escape:'htmlall':'UTF-8'}"
                                    {/if} min="0" step="1">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Height (cm)' mod='correosoficial'}
                                    </span>
                                </div>
                                <input type="number" class="form-control" name="DimensionsByDefaultHeight"
                                    id="DimensionsByDefaultHeight" {if isset($DimensionsByDefaultHeight)}
                                    value="{$DimensionsByDefaultHeight|escape:'htmlall':'UTF-8'}" {/if} min="0"
                                    step="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="ChangeLogoOnLabel"
                                        id="ChangeLogoOnLabel" {$ChangeLogoOnLabel|escape:'htmlall':'UTF-8'} />
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Change logo on labels' mod='correosoficial'} </br>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Puede subir el logo de su empresa para que se imprima en las etiquetas de los envíos de Correos Express">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                    <small class="notice-small">{l s='Only CEX shippings' mod='correosoficial'}</small>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div id="UploadLogoLabelsBlock" class="input-group mb-3">
                                <div class="input-group-addon input-group-text-custom">
                                    <input class="form-control form-control-sm" id="UploadLogoLabels"
                                        name="UploadLogoLabels" type="file" />
                                    <div class="col-sm-12 d-flex background-logo-input">
                                        {if isset($baseLabel)}
                                            <img alt="LabelLogo" class="image-preview" id="UploadLogoLabelsImg"
                                                src="{$co_base_dir}media/logo_label/{$baseLabel}" class="upload-preview"
                                                width="150" />
                                        {else if $UploadLogoLabels}
                                            <img alt="LabelLogo" class="image-preview" id="UploadLogoLabelsImg"
                                                src="{$UploadLogoLabels}" class="upload-preview" width="150" />
                                        {/if}
                                    </div>
                                    {if isset($UploadLogoLabelsName) && isset($ErrorLogoLabels)}
                                        <div class="col-sm-12 d-flex background-logo-input">
                                            <span id="UploadLogoLabelsText">{$UploadLogoLabelsName}</span>
                                            <span id="ErrorLogoLabels">{$ErrorLogoLabels}</span>
                                        </div>
                                    {/if}
                                </div>
                                <a class="btn btn-danger clean-upload ml-2" id="clean-upload"><i
                                        class="far fa-trash-alt remove"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox" name="SSLAlternative"
                                        id="SSLAlternative" {$SSLAlternative|escape:'htmlall':'UTF-8'} />
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Activate SSL Certificate' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Método alternativo de conexión. (Puede ser una solución si encontrase un problema de conexión al webservice por este motivo)">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                </div>{* /UserConfigNonMarketplaceBlock *}

                {* Bloque relacionado con el CRON *}
                <div class="col-sm-12">

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-3" id="AutomaticTrackingBlock">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input name="ActivateAutomaticTracking" id="ActivateAutomaticTracking"
                                        {$ActivateAutomaticTracking|escape:'htmlall':'UTF-8'}
                                        class="form-check-input mt-0" type="checkbox"
                                        aria-label="Checkbox for following text input">
                                </div>
                                <span
                                    class="input-group-text input-group-text-color">{l s='Activate automatic tracking' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4" id="ShippingStatusProcessBlock">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input class="form-check-input mt-0" type="checkbox"
                                        name="ShowShippingStatusProcess" id="ShowShippingStatusProcess"
                                        {$ShowShippingStatusProcess|escape:'htmlall':'UTF-8'} />
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='Show shipment status progress in shop' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Define qué estados desesas mostrar en tu pedidos en función de la información devuelta por el envío">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="ShippingStatusProcess">
                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Shipment pre-registered' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="ShipmentPreregistered" id="ShipmentPreregistered">
                                        {foreach $select_shipment_status_options as $ssso}
                                            <option value="{$ssso['id_order_state']}"
                                                {if $ssso['id_order_state'] == $ShipmentPreregistered->value} selected{/if}>
                                                {$ssso['name']}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Shipment Canceled' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="ShipmentCanceled" id="ShipmentCanceled">
                                        {foreach $select_shipment_status_options as $ssso}
                                            <option value="{$ssso['id_order_state']}"
                                                {if $ssso['id_order_state'] == $ShipmentCanceled->value} selected{/if}>
                                                {$ssso['name']}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Shipment in progress' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="ShipmentInProgress" id="ShipmentInProgress">
                                        {foreach $select_shipment_status_options as $ssso}
                                            <option value="{$ssso['id_order_state']}"
                                                {if $ssso['id_order_state'] == $ShipmentInProgress->value} selected{/if}>
                                                {$ssso['name']}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Shipment delivered' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="ShipmentDelivered" id="ShipmentDelivered">
                                        {foreach $select_shipment_status_options as $ssso}
                                            <option value="{$ssso['id_order_state']}"
                                                {if $ssso['id_order_state'] == $ShipmentDelivered->value} selected{/if}>
                                                {$ssso['name']}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Shipment returned' mod='correosoficial'}
                                        </span>
                                    </div>
                                    <select class="co_dropdown" name="ShipmentReturned" id="ShipmentReturned">
                                        {foreach $select_shipment_status_options as $ssso}
                                            <option value="{$ssso['id_order_state']}"
                                                {if $ssso['id_order_state'] == $ShipmentReturned->value} selected{/if}>
                                                {$ssso['name']}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-sm-6 CronIntervalBlock">
                            <div class="col-sm-6 StatusUpdateTime">
                                <label
                                    class="font-size16 mb-1 mr-2">{l s='Status update time' mod='correosoficial'}</label>
                                <input type="range" name="CronInterval" id="CronInterval" class="w-10 mx-2" max="8"
                                    min="2" value="{$CronInterval|escape:'htmlall':'UTF-8'}">
                            </div>
                            <div class="col-sm-2">
                                <span class="CronInterval_TEXT"
                                    id="CronInterval_TEXT">{$CronInterval|escape:'htmlall':'UTF-8'}
                                    {l s='hours' mod='correosoficial'}</span>
                            </div>
                            <div class="col-sm-12 input-group CronIntervalText">
                                <small>{l s='The time selected determines how often order statuses are updated.' mod='correosoficial'}</small>
                            </div>
                        </div>
                        <div class="CronButtons col-sm-6">
                            <div class="col-md-6 offset-md-6">
                                <a class="cron-button" href="{$co_base_dir}log/log_cron_register.txt"
                                    download>{l s='Download log' mod='correosoficial'}</a>
                            </div>
                        </div>
                    </div>
                </div>
                {* Fin Bloque relacionado con el CRON *}
                <div id="UserConfigNifBlock" {if $ActivateMarketplace == 'on'}style="display: none;"{/if}>
                {* Bloque relacionado con el NIF *}
                {if isset($showNIF) && $showNIF == 'true'}
                    <div class="col-sm-12">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        {if isset($ActivateNifFieldCheckout)}
                                            <input class="form-check-input mt-0" type="checkbox" name="ActivateNifFieldCheckout"
                                                id="ActivateNifFieldCheckout"
                                                {$ActivateNifFieldCheckout|escape:'htmlall':'UTF-8'}>
                                        {/if}
                                    </div>
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Add VAT Number field at checkout' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Controla el comportamiento del campo NIF en el checkout">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12" id="NifFieldRadioBlock">
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="radio" name="NifFieldRadio"
                                            id="NifFieldOptional" value="optional"
                                            {($NifFieldRadio === 'OPTIONAL') ? 'checked' : ''}>
                                    </div>
                                    <span class="input-group-text input-group-text-color">
                                        {l s='VAT Number Optional' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Activar para hacer opcional el campo NIF en el proceso de pago">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="radio" name="NifFieldRadio"
                                            id="NifFieldObligatory" value="obligatory"
                                            {($NifFieldRadio === 'OBLIGATORY') ? 'checked' : ''}>
                                    </div>
                                    <span class="input-group-text input-group-text-color">
                                        {l s='VAT Number Obligatory' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Activar para hacer obligatorio el campo NIF en el proceso de pago">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="input-group mb-4">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="radio" name="NifFieldRadio"
                                            id="NifFieldPersonalized" value="personalized"
                                            {($NifFieldRadio === 'PERSONALIZED') ? 'checked' : ''}>
                                    </div>
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color">
                                            {l s='VAT Number Personalized' mod='correosoficial'}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                                class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                title="Indicar el ID del campo NIF si usted ha añadido un campo personalizado en el proceso de pago">
                                                <path
                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                            </svg>
                                        </span>
                                    </div>
                                    <input type="text" name="NifFieldPersonalizedValue" id="NifFieldPersonalizedValue"
                                        class="form-control" value="{$NifFieldPersonalizedValue|escape:'htmlall':'UTF-8'}">
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}
                {* Fin Bloque relacionado con el NIF *}
                </div>{* /UserConfigNifBlock *}
                {* DESCOMENTAR PARA ACTIVAR BETATESTER
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom">
                                    <input 
                                        class="form-check-input mt-0" 
                                        type="checkbox" 
                                        name="betatester" 
                                        id="betatesterCorreos" 
                                        {if $betatester}
                                            checked
                                        {/if}
                                    />
                                </div>
                                <span class="input-group-text input-group-text-color">
                                    {l s='I would like to receive versions with new features before publication' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                *}
                <div class="col-sm-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="co_primary_button" name="UserConfigurationSaveButton"
                            id="UserConfigurationSaveButton" type="submit">
                            <span id="ProcessingUserConfigButton" class="hidden-block">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                            </span>
                            <span class="label-message" id="MsgUserConfigButton"
                                role="status">{l s='SAVE USER DATA' mod='correosoficial'}</span>
                        </button>
                    </div>
                </div>

            </div>
        </fieldset>
    </form>
</div>

<script>
    var userConfigurationSaved = "{l s='User data successfully saved' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var minValue1 = "{l s='Please enter a value greater than or equal to 1' mod='correosoficial'}";
    var maxValue10 = "{l s='Please enter a value less than or equal to 10' mod='correosoficial'}";
    var valuesWeightDefault= "{l s='Allowable value between 1 and 30 kg' mod='correosoficial'}";
    var valuesDimensionDefault= "{l s='The minimum dimensions of a shipment are 15 x 10 x 1 cm.' mod='correosoficial'}";
    var wrongACCAndIBAN = "{l s='Please specify a valid Bank Account number/IBAN' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
    var hours = "{l s='hours' mod='correosoficial'}";
    var co_base_dir = "{$co_base_dir}";
</script>