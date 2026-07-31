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
<!-- INICIO BLOQUE REGISTRO ENVÍO-->
<div class="card">

    <!-- HEADER BLOQUE REGISTRO ENVÍO-->
    <div class="card-header card-header-yellow">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
            class="bi bi-box-seam custom-icon" viewBox="0 0 16 16">
            <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2l-2.218-.887zm3.564 1.426L5.596 5 8 5.961 14.154 3.5l-2.404-.961zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464L7.443.184z">
            </path>
        </svg>
        <h3 class="card-header-title">{l s='Register shipment' mod='correosoficial'}</h3>
    </div>
    <!-- END HEADER BLOQUE REGISTRO ENVÍO-->

    <!-- BODY BLOQUE REGISTRO ENVÍO-->
    <div class="card-body">

        <!-- BODY ROW -->
        <div class="row">

            <!-- COLUMNA IZQUIERDA -->
            <div class="col-sm-5">

                <!-- CARD PICKUP -->
                <div class="card mt-0">

                    <!-- COLUMNA IZQUIERDA BODY -->
                    <div class="card-body">

                        <!-- BTN CANCELAR ENVÍO -->
                        <div class="input-group input-group-custom mb-0 cancel-container {if !$order_done}hidden-block{/if}">
                            <button id="cancelOrderButton" class="btn btn-lg co_primary_button button-width"
                                type="submit" {if $cancelable == false}disabled{/if}>
                                <span id="processingCancelOrderButtonMsg" class="hidden-block">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                </span>
                                <span id="cancelOrderButtonMsg" role="status" aria-hidden="true">{l s='Cancel shipping' mod='correosoficial'}</span>
                            </button>
                        </div>
                        <!-- END BTN CANCELAR ENVÍO -->


                        <!-- PICKUP -->

                        {$show_masive_pickup_container = false}

                        {if $default_sender && $carrier_order.company === 'CEX' && $default_sender.sender_from_time != $default_sender.sender_to_time}
                            {$show_masive_pickup_container = true}
                        {/if}

                        <!-- CHECKBOX SAVE PICKUP -->
                        <div class="input-group mb-3 {if $order_done}hidden-block{/if}" id="input_grabar_recogida_container">
                            <div class="input-group-addon input-group-checkbox-custom">
                                <input class="form-check-input mt-0" type="checkbox" data-company="{$carrier_order.company}" name="inputCheckSavePickup" id="inputCheckSavePickup" aria-label="" {if $show_masive_pickup_container} checked="true" {/if}>
                            </div>
                            <span class="input-group-text input-group-text-color">
                                {l s='Save pickup' mod='correosoficial'}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Grabar recogida">
                                    <path  d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                </svg>
                            </span>
                        </div>
                        <!-- END CHECKBOX SAVE PICKUP -->

                        <!-- MASIVE PICKUP CONTAINER -->
                        <div id="masive_pickup_container" class="mb-3 {if !$show_masive_pickup_container || $order_done && $carrier_order.company == 'CEX' }hidden-block {/if}">
                            
                            <!-- FECHA PICKUP -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="input-group input-group-custom pickup-date-container">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color">
                                                {l s='Pickup date' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings"
                                                    viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Fecha de recogida">
                                                    <path
                                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <input type="date" id="PickupDateRegister" name="PickupDateRegister"
                                            class="form-control" placeholder=""
                                            {if $default_sender && $current_time_timestamp > $default_sender.sender_to_time_timestamp}
                                                value="{$tomorrow_date}" 
                                            {else}
                                                value="{$current_date}" 
                                        {/if}>
                                    </div>
                                </div>
                            </div>
                            <!-- END FECHA PICKUP -->

                            <!-- HORARIO PICKUP -->
                            <div class="row pickup-date-container">

                                <!-- FROM PICKUP -->
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom" id="">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color">
                                                {l s='From hour' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16"
                                                    height="16" fill="#9b9fa3"
                                                    class="bi bi-info-circle-fill tt_settings"
                                                    viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Desde">
                                                    <path
                                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <input type="time" id="PickupFromRegister" name="PickupFromRegister"
                                            class="form-control"
                                            value="{$default_sender.sender_from_time|default:''}">
                                    </div>
                                </div>
                                <!-- END FROM PICKUP -->

                                <!-- TO PICKUP -->
                                <div class="col-sm-6">
                                    <div class="input-group input-group-custom" id="">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text  input-group-text-color">
                                                {l s='To hour' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16"
                                                    height="16" fill="#9b9fa3"
                                                    class="bi bi-info-circle-fill tt_settings"
                                                    viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Hasta">
                                                    <path
                                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                </svg>
                                            </span>
                                        </div>
                                        <input type="time" id="PickupToRegister" name="PickupToRegister"
                                            class="form-control"
                                            value="{$default_sender.sender_to_time|default:''}">
                                    </div>
                                </div>
                                <!-- END TO PICKUP -->

                            </div>
                            <!-- END HORARIO PICKUP -->

                            <!-- SÓLO PARA CORREOS -->
                            <div {if $carrier_order.company == 'CEX'}class="hidden-block"{/if}>

                                <!-- INPUT IMPRIMIR ETIQUETA -->
                                <div id="orderAdminPrintLabelPickup" class="input-group my-3">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="checkbox" name="inputCheckPrintLabel" id="inputCheckPrintLabel" aria-label="">
                                    </div>
                                    <span class="input-group-text input-group-text-color" id="basic-addon2">
                                        {l s='Print label at pickup' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Imprimir etiqueta en la recogida">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <!-- END INPUT IMPRIMIR ETIQUETA -->

                                <!-- INPUT TAMAÑO PAQUETE -->
                                <div id="orderAdminPackageSize" class="input-group my-3">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text input-group-text-color" for="input_tamanio_paquete">
                                            {l s='Package Size' mod='correosoficial'}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                                class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                                data-bs-toggle="tooltip" data-bs-placement="top" title="Tamaño de paquete">
                                                <path
                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                            </svg>
                                        </span>
                                    </div>
                                    <select class="custom-select" id="input_tamanio_paquete" name="input_tamanio_paquete"required>
                                        <option value="0" selected>&nbsp;</option>
                                        <option value="10">{l s='Envelopes' mod='correosoficial'}</option>
                                        <option value="20">{l s='Small (Shoes Box)' mod='correosoficial'}</option>
                                        <option value="30">{l s='Medium (Sheet Box)' mod='correosoficial'}</option>
                                        <option value="40">{l s='Big (box 80x80x80cm)' mod='correosoficial'}</option>
                                        <option value="50">{l s='Very Big (greather than box 80x80x80cm)' mod='correosoficial'}</option>
                                        <option value="60">{l s='Pallete' mod='correosoficial'}</option>
                                    </select>
                                </div>
                                <!-- END INPUT TAMAÑO PAQUETE -->

                            </div>
                            <!-- END SÓLO PARA CORREOS -->

                        </div>
                        <!-- END MASIVE PICKUP CONTAINER -->

                        <!-- END PICKUP -->

                        <!-- SEND CONTAINER -->
                        <div class="input-group input-group-custom mb-0 send-container {if $order_done}hidden-block{/if}">
                            <!-- GENERATE BUTTON -->
                            <button id="generateOrderButton" class="btn btn-lg co_primary_button button-width" type="submit" {if empty($default_sender) || empty($carriers)}disabled{/if}>
                                <span id="processingOrderButtonMsg" class="hidden-block">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                </span>
                                <span id="generateOrderButtonMsg" role="status" aria-hidden="true">{l s='Generate shipping' mod='correosoficial'}</span>
                            </button>
                            <!-- END GENERATE BUTTON -->
                        </div>
                        <!-- END SEND CONTAINER -->

                    </div>
                    <!-- END COLUMNA IZQUIERDA BODY -->

                </div>
                <!-- END CARD PICKUP -->

                <!-- CARD PICKUP 2 -->
                <div class="{if !$order_done }hidden-block{/if}" id="general-pickup-container">
                    <div class="card {if $pickup == 1 || ($order_done && $carrier_order['company'] == 'CEX') }hidden-block{/if}" id="save-pickup-container">
                        
                        <div class="card-header card-header-date">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar custom-icon" viewBox="0 0 16 16">
                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                            </svg>
                            <span>{l s='Generate pickup' mod='correosoficial'}</span>
                        </div>

                        <div class="card-body">

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="input-group input-group-custom">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text">{l s='Pickup date' mod='correosoficial'}</span>
                                        </div>
                                        <input type="date" id="pickup_date" name="pickup_date" class="form-control">
                                    </div>
                                </div>
                            </div>

                            {if $default_sender}
                                <div class="row pickup-date-container-registered">
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text">{l s='From' mod='correosoficial'}</span>
                                            </div>
                                            <input type="time" id="sender_from_time" name="sender_from_time" class="form-control" value="{$default_sender.sender_from_time}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="input-group input-group-custom">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text">{l s='To' mod='correosoficial'}</span>
                                            </div>
                                            <input type="time" id="sender_to_time" name="sender_to_time" class="form-control" value="{$default_sender.sender_to_time}">
                                        </div>
                                    </div>
                                </div>
                            {/if}

                            <div id="correos-options-pickup-container"
                                class="{if  $carrier_order['company'] != "Correos"}hidden-block{/if}">
                                <div class="input-group">
                                    <div class="input-group-addon input-group-checkbox-custom">
                                        <input class="form-check-input mt-0" type="checkbox" name="print_label" id="print_label" {if $bultos > 5}disabled{/if}>
                                    </div>
                                    <span
                                        class="input-group-text input-group-checkbox">{l s='Request labelling from Correos' mod='correosoficial'}
                                    </span>
                                </div>

                                <div class="alert alert-danger alert-dismissible fade show alert-more-5-labels {if $bultos <= 5}hidden-block{/if}" role="alert">
                                    <strong>{l s='Print Label' mod='correosoficial'}:</strong>
                                    {l s='Not available for shipments with more than 5 packages' mod='correosoficial'}
                                </div>

                                <div id="co_package_size" class="input-group input-group-custom">
                                    <div class="input-group-addon input-group-text-custom">
                                        <span class="input-group-text">{l s='Package size' mod='correosoficial'}</span>
                                    </div>
                                    <select class="form-select" id="package_type" name="package_type">
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

                            <button id="generate_pickup" class="btn btn-lg co_primary_button" type="button" {if !$cancelable}disabled{/if}>
                                <span id="processingPickupButtonMsg" class="hidden-block">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                </span>
                                <span id="pickupButtonMsg" role="status" aria-hidden="true">{l s='Generate pickup' mod='correosoficial'}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- END CARD PICKUP 2 -->

                <!-- CARD DATA PICKUP -->
                <div class="card {if $pickup == 0 }hidden-block{/if}" id="data-pickup-container">

                    <!-- HEADER DATA PICKUP -->
                    <div class="card-header card-header-date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check custom-icon" viewBox="0 0 16 16">
                            <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"></path>
                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"></path>
                        </svg>
                        <span>{l s='Pickup data' mod='correosoficial'}</span>
                    </div>
                    <!-- END HEADER DATA PICKUP -->

                    <!-- BODY DATA PICKUP -->
                    <div class="card-body">
                        <div class="row {if $pickup == 0 && $carrier_order['company'] == "Correos"}hidden-block{/if}">
                            <div class="col-sm-6">
                                <p>
                                    {l s='Pickup code' mod='correosoficial'}: <br />
                                    <span class="pickup-codSolicitud">{$correos_order['pickup_number']}</span>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <p>
                                    {l s='Pickup data' mod='correosoficial'}: <br>
                                    <span class="pickup-data">{$pickup_data_response['pickup_date']}
                                        {$pickup_data_response['pickup_from_hour']}
                                        {$pickup_data_response['pickup_to_hour']}
                                    </span><br>
                                    <span class="pickup-data">{$pickup_data_response['pickup_address']}</span><br>
                                    <span class="pickup-data">{$pickup_data_response['pickup_city']}</span><br>
                                    <span class="pickup-data">{$pickup_data_response['pickup_cp']}</span><br>
                                </p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6 mt-4">
                                {if isset($carrier_order['company']) && $carrier_order['company'] == 'Correos' }
                                    <button id="cancel_pickup" class="btn btn-lg co_primary_button" type="button">
                                        <span id="processingCancelPickupButtonMsg" class="hidden-block">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span id="pickupCancelButtonMsg" role="status" aria-hidden="true">{l s='Cancel pickup' mod='correosoficial'}</span>
                                    </button>
                                {/if}
                            </div>
                            <div class="col-sm-6 mt-4">
                                <p>
                                    {l s='status' mod='correosoficial'}: <br>
                                    <span id="pickup-status" class="pickup-status">{$pickup_data_response['status']}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- END BODY DATA PICKUP -->

                </div>
                <!-- END CARD DATA PICKUP -->

            </div>
            <!-- END COLUMNA IZQUIERDA -->

            <!-- COLUMNA DERECHA -->
            <div class="col-sm-7">

                <!-- CARD ORDER DONE -->
                <div class="card mt-0 {if !$order_done}hidden-block{/if}" id="order-done-info">

                    <!-- HEADER CARD ORDER DONE -->
                    <div class="card-header card-header-date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-square custom-icon" viewBox="0 0 16 16">
                            <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"></path>
                            <path d="M10.97 4.97a.75.75 0 0 1 1.071 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.235.235 0 0 1 .02-.022z"></path>
                        </svg>
                        <span>{l s='Registered shipping data' mod='correosoficial'}</span>
                    </div>
                    <!-- END HEADER CARD ORDER DONE -->

                    <!-- BODY CARD ORDER DONE -->
                    <div class="card-body">

                        <!-- ROW CARD ORDER DONE -->
                        <div class="row">

                            <!-- COL IZQ CARD ORDER DONE -->
                            <div class="col-sm-6" id="shipping-numbers-col">

                                {if $order_done}
                                    {if $bultos == 1}
                                        <p>{l s='Shipping code' mod='correosoficial'}:</p>
                                    {else}
                                        <p>{l s='Shipping codes' mod='correosoficial'}:</p>
                                    {/if}
                                {/if}

                                {$num_bulto = 1}
                                <div class="shipping-numbers-container">
                                    {foreach from=$array_packages_order item=package}
                                        <span class="order-done-info-text">
                                            {l s='Package' mod='correosoficial'} {$num_bulto}: {$package['shipping_number']}
                                        </span>
                                        <br />
                                        {$num_bulto = $num_bulto + 1}
                                    {/foreach}
                                </div>

                            </div>
                            <!-- END COL IZQ CARD ORDER DONE -->

                            <!-- COL DER CARD ORDER DONE -->
                            <div class="col-sm-6">

                                <!-- REGISTER PRINT CARD -->
                                <div id="register_print" class="card mt-0">
                                    <div class="card-body">

                                        <div class="input-group input-group-custom row p-0 m-0">
                                            <div class="input-group-addon input-group-text-custom col-6">
                                                <label class="input-group-text-bulto">{l s='Label type' mod='correosoficial'}</label>
                                            </div>
                                            <select class="form-select select-label col-6" id="input_tipo_etiqueta_reimpresion" name="input_tipo_etiqueta_reimpresion">
                                                {html_options options=$select_label_options selected=$DefaultLabel}
                                            </select>
                                        </div>
                                        
                                        <div class="input-group input-group-custom row p-0 m-0" id="input_format_etiqueta_container_reimpresion">
                                            <div class="input-group-addon input-group-text-custom col-6">
                                                <label class="input-group-text-bulto">{l s='Label Format' mod='correosoficial'}</label>
                                            </div>
                                            <select class="form-select select-label col-6" id="input_format_etiqueta_reimpresion" name="input_format_etiqueta_reimpresion">
                                                {html_options options=$select_label_options_format}
                                            </select>
                                        </div>

                                        <div class="input-group input-group-custom row p-0 m-0" id="input_pos_etiqueta_container_reimpresion">
                                            <div class="input-group-addon input-group-text-custom col-6">
                                                <label class="input-group-text-bulto">{l s='Label position' mod='correosoficial'}</label>
                                            </div>
                                            <select class="form-select select-label-position col-6" id="input_pos_etiqueta_reimpresion" name="input_pos_etiqueta_reimpresion">
                                            </select>
                                        </div>

                                        <div class="input-group input-group-custom mb-0">
                                            <button type="button" id="ReimprimirEtiquetasButton" name="ReimprimirEtiquetasButton" class="btn-lg co_primary_button button-width">
                                                <span id="processingPrintLabelButtonMsg" class="hidden-block">
                                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                    <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                </span>
                                                <span id="PrintLabelMessageButton" role="status" aria-hidden="true">{l s='Print label' mod='correosoficial'}</span>
                                            </button>
                                        </div>

                                    </div>
                                </div>
                                <!-- REGISTER PRINT CARD -->

                            </div>
                            <!-- END COL DER CARD ORDER DONE -->

                            <!-- CUSTOMS LABEL CONTAINER -->
                            <div id="customs-labels-container"
                                class="col-sm-12 customs-labels-container align-self-end mt-4 {if !$require_customs_doc || $carrier_order['company'] != "Correos"}hidden-block{/if}">
                                <div class="card">
                                    <div class="card-body">
                                        <h3>Documentación aduanera</h3>
                                        <div class="mt-2">
                                            <button id="ImprimirCN23Button"
                                                class="btn-sm co_primary_button PrintGestionAduaneraLabels"
                                                type="button" value="{l s='Print CN23' mod='correosoficial'}">
                                                <span class="spin hidden-block">
                                                    <span class="spinner-border spinner-border-sm" role="status"
                                                        aria-hidden="true"></span>
                                                    <span role="status"
                                                        aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                </span>
                                                <span class="label-message" role="status"
                                                    aria-hidden="true">{l s='Print CN23' mod='correosoficial'}</span>
                                            </button>
                                            <button id="ImprimirDUAButton"
                                                class="btn-sm co_primary_button PrintGestionAduaneraLabels"
                                                type="button" value="{l s='Print DCAF' mod='correosoficial'}">
                                                <span class="spin hidden-block">
                                                    <span class="spinner-border spinner-border-sm" role="status"
                                                        aria-hidden="true"></span>
                                                    <span role="status"
                                                        aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                </span>
                                                <span class="label-message" role="status"
                                                    aria-hidden="true">{l s='Print DCAF' mod='correosoficial'}</span>
                                            </button>
                                            <button id="ImprimirDDPButton"
                                                class="btn-sm co_primary_button PrintGestionAduaneraLabels"
                                                type="button" value="{l s='Print DDP' mod='correosoficial'}">
                                                <span class="spin hidden-block">
                                                    <span class="spinner-border spinner-border-sm" role="status"
                                                        aria-hidden="true"></span>
                                                    <span role="status"
                                                        aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                                </span>
                                                <span class="label-message" role="status"
                                                    aria-hidden="true">{l s='Print DDP' mod='correosoficial'}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- END CUSTOMS LABEL CONTAINER -->

                        </div>
                        <!-- END ROW CARD ORDER DONE -->

                    </div>
                    <!-- END BODY CARD ORDER DONE -->

                </div>
                <!-- END CARD ORDER DONE -->

                <!-- ERROR ALERT -->
                <div class="alert alert-danger alert-dismissible fade show hidden-block" role="alert" id="error_register">
                    <strong>{l s='Error operation' mod='correosoficial'}:</strong>
                    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- END ERROR ALERT -->

                <!-- SUCCESS ALERT -->
                <div class="alert alert-success alert-dismissible fade show hidden-block" role="alert" id="success_register">
                    <strong>{l s='Done operation' mod='correosoficial'}:</strong>
                    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- END SUCCESS ALERT -->

            </div>
            <!-- END COLUMNA DERECHA -->

        </div>
        <!-- END BODY ROW -->

        <!-- TABLA TRACKING -->
        {include file='./adminOrderTracking.tpl'}
        <!-- END TABLA TRACKING -->

    </div>
    <!-- END BODY BLOQUE REGISTRO ENVÍO-->

</div>
<!-- FIN BLOQUE REGISTRO ENVÍO -->