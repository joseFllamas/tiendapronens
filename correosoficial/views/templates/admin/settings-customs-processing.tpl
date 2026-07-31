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

    <form id="CustomProcessingForm" name="CustomProcessingForm" method="POST">
        <fieldset>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='Reference of customs consignor' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Deja establecida por defecto su referencia aduanera por si necesita indicarla en envíos con trámite aduanero.">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <input type="text" name="ShippCustomsReference" id="ShippCustomsReference" class="form-control"
                                value="{$ShippCustomsReference}">
                        </div>
                    </div>
                </div>
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-checkbox-custom">
                    <input class="form-check-input mt-0" type="checkbox" name="MessageToWarnBuyer"
                        id="MessageToWarnBuyer" {$MessageToWarnBuyer|escape:'htmlall':'UTF-8'} />
                </div>
                <span class="input-group-text input-group-text-color">
                    {l s='Message to warn the buyer about customs formalities (max. 100 characters)' mod='correosoficial'}
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Al activarlo, el comprador será advertido durante el proceso de pago de que su envío podría verse afectado por trámites aduaneros. Usted podrá definir el mensaje que quiere mostrar.">
                        <path
                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                    </svg>
                </span>
            </div>

            <div id="MessageToWarnBuyer2" class="row justify-content-md-center">
                <div class="col-sm-10">
                    <div class="input-group mb-4">
                        <div class="input-group-addon input-group-text-custom">
                            <span class="input-group-text input-group-text-color">
                                {l s='Message to warn the buyer' mod='correosoficial'}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                    class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Este mensaje se mostrará en el checkout">
                                    <path
                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                </svg>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="TranslatableInput" name="TranslatableInput"
                            value="{$TranslatableInput|escape:'htmlall':'UTF-8'}"
                            placeholder="{l s='This shipment is subject to customs clearance. The price of the shipment may be increased' mod='correosoficial'}">
                        <input type="hidden" class="form-control" id="TranslatableInputH" name="TranslatableInputH"
                            value="{$TranslatableInputH|escape:'htmlall':'UTF-8'}">
                    </div>
                </div>
                <div class="col-sm-1">
                    <div class="input-group mb-4">
                        <span class="form_switch_language">
                            <select class="custom-select" name="FormSwitchLanguage" id="FormSwitchLanguage">
                                <option selected disabled value=""></option>
                                {html_options options=$array_languages selected=$selected_language_id}
                            </select>
                        </span>
                    </div>
                </div>
            </div>
                <label>{l s='Select the source of the customs data' mod='correosoficial'}</label>

                <div id="tabs_customs_doc" class="tabs_customs_doc" class="row">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a id="customs_desc"
                                class="nav-link  {if $config_default_aduanera == 0}active{/if} customs_desc"
                                data-type="customs_desc" aria-current="page" href="#">
                                {l s='Customs default description' mod='correosoficial'}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a id="customs_code" class="nav-link {if $config_default_aduanera == 1}active{/if} customs_code"
                                data-type="customs_code" href="#">
                                {l s='Tariff Code' mod='correosoficial'}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a id="customs_product" class="nav-link {if $config_default_aduanera == 2}active{/if} customs_product"
                                data-type="customs_product" href="#">
                                {l s='Product HS Code' mod='correosoficial'}
                            </a>
                        </li>
                    </ul>
                </div>

                <input type="hidden" name="CustomsDesriptionAndTariffSelected" id="CustomsDesriptionAndTariffSelected" value="{$config_default_aduanera}" />

                <div id="customs_desc_tab" class="row content-tab {if $config_default_aduanera != 0}hidden-block{/if} ">
                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <input class="form-check-input" type="radio" name="CustomsDesriptionAndTariff[]"
                                id="DescriptionRadio" value="0" {if $config_default_aduanera == 0}checked="checked"{/if}>
                            <div class="input-group-addon input-group-text-custom">
                                <label class="input-group-text input-group-text-color">
                                    {l s='Default customs description' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Utilice este campo si todos (o una gran parte de sus envíos) se ajusta a una única descripción. Se usará por defecto en sus envíos con trámite aduanero pero la podrá modificar en los casos que considere durante la gestión del envío.">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </label>
                            </div>
                            <select class="co_dropdown" name="DefaultCustomsDescription" id="DefaultCustomsDescription">
                                {html_options options=$customs_desc_array selected=$customs_desc_selected}
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='Country origin of the goods' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{l s='Enter 2-letter ISO of the country (ISO 3166-1 alfa-2)' mod='correosoficial'}">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <input type="text" name="CountryOriginByDefault" id="CountryOriginByDefault"
                                class="form-control" value="{$CountryOriginByDefault}" maxlength="3">
                        </div>
                    </div>
                </div>

                <div id="customs_code_tab" class="row content-tab {if $config_default_aduanera != 1}hidden-block{/if}">
                    <div class="col-sm-3">
                        <div class="input-group mb-1">
                            <input class="form-check-input" type="radio" name="CustomsDesriptionAndTariff[]"
                                id="TariffRadio" value="1" {if $config_default_aduanera == 1}checked="checked"{/if}>
                            <div class="input-group-addon input-group-text-custom">
                                <label class="input-group-text input-group-text-color">
                                    {l s='Tariff code' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Utilice este campo si todos (o una gran parte de sus envíos) se ajusta a una única descripción. Se usará por defecto en sus envíos con trámite aduanero pero lo podrá modificar en los casos que considere durante la gestión del envío.">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </label>
                            </div>
                            <input type="text" class="form-control" name="Tariff" id="Tariff" value="{$Tariff}" />
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color" id="basic-addon1">
                                    {l s='Description' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Utilice este campo si todos (o una gran parte de sus envíos) se ajusta a una única descripción. Se usará por defecto en sus envíos con trámite aduanero pero la podrá modificar en los casos que considere durante la gestión del envío.">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <input type="text" name="TariffDescription" id="TariffDescription" class="form-control"
                                value="{$TariffDescription|escape:'htmlall':'UTF-8'}">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='Country origin of the goods' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{l s='Enter 2-letter ISO of the country (ISO 3166-1 alfa-2)' mod='correosoficial'}">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <input type="text" name="CountryOriginByDefault" id="CountryOriginByDefault2"
                                class="form-control" value="{$CountryOriginByDefault}" maxlength="3">
                        </div>
                    </div>
                </div>

                <div id="customs_product_tab" class="row content-tab {if $config_default_aduanera != 2}hidden-block{/if}">
                    <input class="form-check-input" type="radio" name="CustomsDesriptionAndTariff[]"
                        id="ProductRadio" value="2" style="display: none;" {if $config_default_aduanera == 2}checked="checked"{/if}>
                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='HS Code' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{l s='Select which product attribute contains the HS tariff code' mod='correosoficial'}">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <select class="co_dropdown" name="MappedHsFeature" id="MappedHsFeature">
                                <option value="">{l s='-- Select attribute --' mod='correosoficial'}</option>
                                {html_options options=$product_attributes_array selected=$MappedHsFeature}
                            </select>
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-text-custom">
                                <span class="input-group-text input-group-text-color">
                                    {l s='Origin of the goods' mod='correosoficial'}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                        class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{l s='Select which product attribute contains the country code' mod='correosoficial'}">
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                    </svg>
                                </span>
                            </div>
                            <select class="co_dropdown" name="MappedOriginFeature" id="MappedOriginFeature">
                                <option value="">{l s='-- Select attribute --' mod='correosoficial'}</option>
                                {html_options options=$product_attributes_array selected=$MappedOriginFeature}
                            </select>
                        </div>
                    </div>

                    <div class="col-sm-12">
                        <div class="input-group mb-4">
                            <div class="input-group-addon input-group-checkbox-custom">
                                <input class="form-check-input mt-0" type="checkbox" name="UseModuleFeatures"
                                    id="UseModuleFeatures" {$UseModuleFeatures|escape:'htmlall':'UTF-8'} />
                            </div>
                            <span class="input-group-text input-group-text-color">
                                {l s='Create the HS Code and Country of origin features from the Correos module' mod='correosoficial'}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                    class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="{l s='Enable to use module features for HS codes instead of attributes' mod='correosoficial'}">
                                    <path
                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>


            <div class="col-sm-12">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <input class="co_primary_button" name="CustomsProcessingSaveButton" id="CustomsProcessingSaveButton"
                        type="submit" value="{l s='SAVE CUSTOMS PROCESSING' mod='correosoficial'}">
                </div>
            </div>

        </fieldset>
    </form>
</div>

<script>
    var customsProcessingSaved = "{l s='Customs Processing successfully saved' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var tariffLength = "{l s='Input data must be 6, 8 or 10 characters long' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
</script>

<script>
    jQuery(document).ready(function($) {
        function clearTariffFields() {
            try {
                $('#Tariff').val('');
                $('#TariffDescription').val('');
                $('#CountryOriginByDefault2').val('');
            } catch (e) {
                // ignore if elements not present
            }
        }

        // When UseModuleFeatures checkbox is toggled, clear tariff fields if checked
        $(document).on('change', '#UseModuleFeatures', function() {
            var checked = $(this).is(':checked');
            if (checked) {
                clearTariffFields();
            }
        });

        // When user selects a mapped HS or mapped Origin attribute, clear tariff fields
        $(document).on('change', '#MappedHsFeature, #MappedOriginFeature', function() {
            var hs = $('#MappedHsFeature').val();
            var origin = $('#MappedOriginFeature').val();

            if ((hs && hs !== '') || (origin && origin !== '')) {
                clearTariffFields();
            }
        });
    });
</script>