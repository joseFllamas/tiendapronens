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
<div class="tab-pane fade" id="collected" role="tabpanel" aria-labelledby="pickups-tab">

    <div class="row">
        <div class="col-sm-4 offset-md-4">
            <div class="input-group mb-3">
                <h2 class="buscador-pedidos-h2">{l s='Pickups' mod='correosoficial'}</h2>
            </div>
            <form id="searchPickupform" name="searchPickupform" class="needs-validation" novalidate>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='From' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputFromDatePickups" name="inputFromDatePickups" class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Untill' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputToDatePickups" name="inputToDatePickups" class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <input id="PickupsSearchButton" name="PickupsSearchButton" class="btn-lg co_primary_button center-block" type="button" value="{l s='Search orders' mod='correosoficial'}">
                </div>
            </form>
        </div>
    </div>

    <div class="card card-margin card-table-utilities" id="card4">
        <div class="card-header">
            {l s='PICKUPS' mod='correosoficial'}
        </div>
        <div id="pickupsTable" class="card-body card-body-custom">
            <div class="alert alert-warning">{l s='In this tab only show the Correos orders, anywhere show CEX orders' mod='correosoficial'}</div>
            <a class="show-cols4">{l s='Show/Hide columns' mod='correosoficial'}</a> 
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#002e6d" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="right" title="Click en cada elemento de la lista para mostrar/ocultar columnas de la tabla">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
            <div class="showButtonsContainer4 hidden-block">
                <a class="toggle-vis4" data-column="1">ID</a> 
                <a class="toggle-vis4" data-column="2">{l s='Reference' mod='correosoficial'}</a> 
                <a class="toggle-vis4" data-column="3">{l s='Shipping Code' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="4">{l s='Carrier' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="5">{l s='Receiver' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="6">{l s='Sender Address' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="7">{l s='Date/Hour recording ' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="8">{l s='Packages' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="9">{l s='Size' mod='correosoficial'}</a>
                <a class="toggle-vis4" data-column="10">{l s='Pickup label' mod='correosoficial'}</a> 
                <a class="toggle-vis4" data-column="11">{l s='Pickup recorded' mod='correosoficial'}</a>
            </div>
            <table id="PickupDataTable" class="table table-striped" width="100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" name="table-select-all-pickups" value="1" id="table-select-all-pickups"></th>
                        <th>ID</th>
                        <th>{l s='Reference' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th>  
                        <th>{l s='Carrier' mod='correosoficial'}</th>            
                        <th>{l s='Receiver ' mod='correosoficial'}</th>
                        <th>{l s='Sender Address ' mod='correosoficial'}</th>
                        <th>{l s='Date/Hour recording ' mod='correosoficial'}</th>
                        <th>{l s='Packages' mod='correosoficial'}</th>
                        <th>{l s='Size' mod='correosoficial'}</th>
                        <th>{l s='Pickup label' mod='correosoficial'}</th>
                        <th>{l s='Pickup recorded' mod='correosoficial'}</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>
                        <th><input type="text"></th>                        
                    </tr> 
                    <tr class="correos_oficial_datatable_footer">
                        <th></th>
                        <th>ID</th>
                        <th>{l s='Reference' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th>
                        <th>{l s='Carrier' mod='correosoficial'}</th>
                        <th>{l s='Receiver ' mod='correosoficial'}</th>
                        <th>{l s='Sender Address ' mod='correosoficial'}</th>
                        <th>{l s='Date/Hour recording ' mod='correosoficial'}</th>
                        <th>{l s='Packages' mod='correosoficial'}</th>
                        <th>{l s='Size' mod='correosoficial'}</th>
                        <th>{l s='Pickup label' mod='correosoficial'}</th>
                        <th>{l s='Pickup recorded' mod='correosoficial'}</th>
                    </tr>
                </tfoot>
            </table>

            <div class="row bottom-utilities-form-row">
                <form id="PickupsForm">
                    <div class="col-sm-8">

                        <div id="success_pickup_msg" class="alert alert-success alert-dismissible alert-utilities fade hidden-block" role="alert">
                            <strong>Recogidas grabadas.</strong>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                            
                        <div class="col-sm-12" id="datatable_errors_pickups_container">

                            <div id="response_pickups" class="col-sm-12"></div>

                            <div class="card card-custom card-margin">
                                <div class="card-header">
                                    {l s='ERRORS IN PICKUP ORDERS' mod='correosoficial'}
                                </div>
                                <div class="card-body card-body-custom">
                                    <table id="datatableResultsRecogidas" class="table table-striped" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>mensajeRetorno</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                            
                        </div>

                    </div>
                    <div class="col-sm-4">
                        <div class="input-group mb-3">
                            <div class="card">
                                <div class="card-body">

                                    <div class="input-group input-group-custom mb-3 pickup-date-container">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color">
                                                {l s='Pickup date' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Fecha de recogida">
                                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <input type="date" id="PickupDate" name="PickupDate" class="form-control" placeholder="" >
                                    </div>
                
                                    <div class="row pickup-date-container">
                                        <div class="col-sm-6">
                                            <div class="input-group input-group-custom mb-3" id="">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text input-group-text-color">
                                                        {l s='From hour' mod='correosoficial'}
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Desde">
                                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <input type="time" id="PickupFrom" name="PickupFrom" class="form-control" value="{$pickup_from|escape:'htmlall':'UTF-8'}">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="input-group input-group-custom mb-3" id="">
                                                <div class="input-group-addon input-group-text-custom">
                                                    <span class="input-group-text  input-group-text-color">
                                                        {l s='To hour' mod='correosoficial'}
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Hasta">
                                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <input type="time" id="PickupTo" name="PickupTo" class="form-control"  value="{$pickup_to|escape:'htmlall':'UTF-8'}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="print_and_TamLabelPickups">

                                        <div class="input-group mt-3" id="input_imprimir_etiqueta_container2">
                                            <div class="input-group-addon input-group-checkbox-custom">
                                                <input class="form-check-input mt-0" type="checkbox" name="inputPrintLabelPickups" id="inputPrintLabelPickups" aria-label="">
                                            </div>
                                            <span class="input-group-text input-group-text-color" id="basic-addon2">
                                                {l s='Print label at pickup' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Imprimir etiqueta en la recogida">
                                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <span class="register-options-advice"><span class="register-options-advice-red">* </span>{l s='Only Correos shipments. Click if Correos must have the labels printed on pickup' mod='correosoficial'}.</span>
                                        
                                        <div class="input-group mt-3" id="input_tamanio_paquete_container2">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text input-group-text-color" for="inputTamLabelPickups">
                                                    {l s='Package Size' mod='correosoficial'}
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Tamaño de paquete">
                                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                    </svg>
                                                </span>                                                
                                            </div>
                                            
                                            <select class="custom-select" id="inputTamLabelPickups" name="inputTamLabelPickups" required>
                                                <option value="0" selected>&nbsp;</option>
                                                <option value="10">{l s='Envelopes' mod='correosoficial'}</option>
                                                <option value="20">{l s='Small (Shoes Box)' mod='correosoficial'}</option>
                                                <option value="30">{l s='Medium (Sheet Box)' mod='correosoficial'}</option>
                                                <option value="40">{l s='Big (box 80x80x80cm)' mod='correosoficial'}</option>
                                                <option value="50">{l s='Very Big (greather than box 80x80x80cm)' mod='correosoficial'}</option>
                                                <option value="60">{l s='Pallete' mod='correosoficial'}</option>
                                            </select>
                                        </div>
                                        <span class="register-options-advice"><span class="register-options-advice-red">* </span>{l s='Only Correos shipments. Select package size for pickup.' mod='correosoficial'}</span>

                                    </div>

                                    <button id="generatePickupsButton" class="co_primary_button pull-right mt-3" type="button">
                                        <span id="processingPickupsButtonMsg" class="hidden-block">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span id="generatePickupsButtonMsg" role="status" aria-hidden="true">{l s='Generate pickups' mod='correosoficial'}</span>
                                    </button>

                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>

<script>
    var order_string_translate = "{l s='ORDER' mod='correosoficial'}";
    var size_pickup_string_translate = "{l s='Choose a package size for pickup' mod='correosoficial'}";
</script>