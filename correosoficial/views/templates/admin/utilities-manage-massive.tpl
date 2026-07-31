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
<div class="tab-pane fade show active" id="gestion" role="tabpanel" aria-labelledby="gestion-tab">

    <div class="row">
        <div class="col-sm-4 offset-md-4">
            <div class="input-group mb-3">
                <h2 class="buscador-pedidos-h2">{l s='Mass Orders Management' mod='correosoficial'}</h2>
            </div>
            <form id="busquedaEnviosForm" name="busquedaEnviosForm" class="needs-validation" novalidate>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='From' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputFromDateOrdersReg" name="inputFromDateOrdersReg"
                        class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Untill' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputToDateOrdersReg" name="inputToDateOrdersReg"
                        class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <input id="GestionMasivaPedidosSearchButton" name="GestionMasivaPedidosSearchButton"
                        class="btn-lg co_primary_button center-block" type="button"
                        value="{l s='Search orders' mod='correosoficial'}">
                </div>
            </form>
        </div>
    </div>

    <div class="card card-margin card-table-utilities" id="card1">
        <div class="card-header">
            {l s='MASS ORDERS MANAGMENT' mod='correosoficial'}
        </div>
        <div id="massiveProductsTable" class="card-body card-body-custom">
            <a class="show-cols">{l s='Show/Hide columns' mod='correosoficial'}</a>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#002e6d" class="bi bi-info-circle-fill tt_settings"
                viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="right"
                title="Click en cada elemento de la lista para mostrar/ocultar columnas de la tabla">
                <path
                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
            </svg>
            <div class="showButtonsContainer hidden-block">
                <a class="toggle-vis" data-column="1">ID</a>
                <a class="toggle-vis" data-column="2">{l s='Reference' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="3">{l s='Products' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="4">{l s='Shipping Code' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="5">{l s='Carrier' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="6">{l s='Status' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="7">{l s='Customer' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="8">{l s='Date/Hour' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="9">{l s='Office' mod='correosoficial'}/CITYPAQ</a>
                <a class="toggle-vis" data-column="10">{l s='Selected Product' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="11">{l s='Selected Sender' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="12">{l s='Change product' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="13">{l s='Packages' mod='correosoficial'}</a>
                <a class="toggle-vis" data-column="14">{l s='AT Code' mod='correosoficial'}</a>
            </div>
            <table id="GestionDataTable" class="table table-striped" width="100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" name="table-select-all" value="1" id="table-select-all-manage-massive"></th>
                        <th>ID</th>
                        <th>{l s='Reference' mod='correosoficial'}</th>
                        <th>{l s='Products' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th>
                        <th>{l s='Carrier' mod='correosoficial'}</th>
                        <th>{l s='Status ' mod='correosoficial'}</th>
                        <th>{l s='Customer' mod='correosoficial'}</th>
                        <th>{l s='Date/Hour' mod='correosoficial'}</th>
                        <th>{l s='Office' mod='correosoficial'}/CITYPAQ</th>
                        <th>{l s='Selected Product' mod='correosoficial'}</th>
                        <th>{l s='Selected Sender' mod='correosoficial'}</th>
                        <th>{l s='Change product' mod='correosoficial'}</th>
                        <th>{l s='Packages' mod='correosoficial'}</th>
                        <th title="{l s='Only apply to CEX products and Portugal Shippings' mod='correosoficial'}">
                            {l s='AT Code' mod='correosoficial'}</th>
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
                    <th><input type="text"></th>
                    <th><input type="text"></th>
                    <th><input type="text"></th>
                 </tr>                
                <tr style="visibility: hidden; height: 0">
                    <th></th>
                    <th>ID</th>
                    <th>{l s='Reference' mod='correosoficial'}</th>
                    <th>{l s='Products' mod='correosoficial'}</th>
                    <th>{l s='Shipping Code' mod='correosoficial'}</th>
                    <th>{l s='Carrier' mod='correosoficial'}</th>
                    <th>{l s='Status' mod='correosoficial'}</th>
                    <th>{l s='Customer' mod='correosoficial'}</th>
                    <th>{l s='Date/Hour' mod='correosoficial'}</th>
                    <th>{l s='Office' mod='correosoficial'}/CITYPAQ</th>
                    <th>{l s='Selected Product' mod='correosoficial'}</th>
                    <th>{l s='Selected Sender' mod='correosoficial'}</th>
                    <th>{l s='Change product' mod='correosoficial'}</th>
                    <th>{l s='Packages' mod='correosoficial'}</th>
                    <th title="{l s='Only apply to CEX products and Portugal Shippings' mod='correosoficial'}">
                        {l s='AT Code' mod='correosoficial'}</th>
                </tr>
                </tfoot>
            </table>

            <div class="row bottom-utilities-form-row">
                <form id="generacionEnviosForm">
                    <div class="col-sm-7" style="padding-right: 20px;">

                        <!-- Barra de progreso -->
                        <div class="col-sm-12" id="progress_container" style="display:none;">
                            <div class="card card-custom card-margin">
                                <div class="card-header card-header-primary">
                                    {l s='PROCESSING ORDERS' mod='correosoficial'}
                                </div>
                                <div class="card-body card-body-custom">
                                    <div class="progress-info mb-2">
                                        <span id="progress_text">{l s='Processing order' mod='correosoficial'} <span id="current_order">0</span> {l s='of' mod='correosoficial'} <span id="total_orders">0</span></span>
                                        <span class="float-right"><span id="progress_percentage">0</span>%</span>
                                    </div>
                                    <div class="progress" style="height: 25px;">
                                        <div id="progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="progress-stats mt-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="bi bi-check-circle text-success"></i>
                                                    <h4 id="success_count" class="text-success">0</h4>
                                                    <small>{l s='Successful' mod='correosoficial'}</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="bi bi-x-circle text-danger"></i>
                                                    <h4 id="error_count" class="text-danger">0</h4>
                                                    <small>{l s='Errors' mod='correosoficial'}</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="bi bi-hourglass-split text-warning"></i>
                                                    <h4 id="pending_count" class="text-warning">0</h4>
                                                    <small>{l s='Pending' mod='correosoficial'}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de resultados de procesamiento -->
                                    <div class="col-sm-12 mt-3" id="reg_orders_results_container" style="display:none;">
                                        <table id="datatableErrorsRegOrders" class="table table-striped table-bordered" style="width:100%">
                                            <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>{l s='Status' mod='correosoficial'}</th>
                                                <th>{l s='Result' mod='correosoficial'}</th>
                                            </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="col-sm-5">
                        <div class="input-group mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="input-group mb-3" id="input_grabar_recogida_container">
                                        <div class="input-group-addon input-group-checkbox-custom">
                                            <input class="form-check-input mt-0" type="checkbox"
                                                name="inputCheckSavePickup" id="inputCheckSavePickup" aria-label="">
                                        </div>
                                        <span class="input-group-text input-group-text-color">
                                            {l s='Save pickup' mod='correosoficial'}
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings"
                                                viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Grabar recogida">
                                                <path
                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                            </svg>
                                        </span>
                                    </div>

                                    <div id="masive_pickup_container">

                                        <div class="col-sm-12">
                                            <div class="input-group mb-3">
                                                <div class="card">
                                                    <div class="card-body">

                                                        <div
                                                            class="input-group input-group-custom mb-3 pickup-date-container">
                                                            <div class="input-group-addon input-group-text-custom">
                                                                <span class="input-group-text input-group-text-color">
                                                                    {l s='Pickup date' mod='correosoficial'}
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16"
                                                                        height="16" fill="#9b9fa3"
                                                                        class="bi bi-info-circle-fill tt_settings"
                                                                        viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                                                        data-bs-placement="top"
                                                                        title="Fecha de recogida">
                                                                        <path
                                                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                                    </svg>
                                                                </span>
                                                            </div>
                                                            <input type="date" id="PickupDateRegister"
                                                                name="PickupDateRegister" class="form-control"
                                                                placeholder="">
                                                        </div>

                                                        <div class="row pickup-date-container">
                                                            <div class="col-sm-6">
                                                                <div class="input-group input-group-custom mb-3" id="">
                                                                    <div
                                                                        class="input-group-addon input-group-text-custom">
                                                                        <span
                                                                            class="input-group-text input-group-text-color">
                                                                            {l s='From hour' mod='correosoficial'}
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                width="16" height="16" fill="#9b9fa3"
                                                                                class="bi bi-info-circle-fill tt_settings"
                                                                                viewBox="0 0 16 16"
                                                                                data-bs-toggle="tooltip"
                                                                                data-bs-placement="top" title="Desde">
                                                                                <path
                                                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                                            </svg>
                                                                        </span>
                                                                    </div>
                                                                    <input type="time" id="PickupFromRegister"
                                                                        name="PickupFromRegister" class="form-control"
                                                                        value="{$pickup_from|escape:'htmlall':'UTF-8'}">
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="input-group input-group-custom mb-3" id="">
                                                                    <div
                                                                        class="input-group-addon input-group-text-custom">
                                                                        <span
                                                                            class="input-group-text  input-group-text-color">
                                                                            {l s='To hour' mod='correosoficial'}
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                width="16" height="16" fill="#9b9fa3"
                                                                                class="bi bi-info-circle-fill tt_settings"
                                                                                viewBox="0 0 16 16"
                                                                                data-bs-toggle="tooltip"
                                                                                data-bs-placement="top" title="Hasta">
                                                                                <path
                                                                                    d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                                            </svg>
                                                                        </span>
                                                                    </div>
                                                                    <input type="time" id="PickupToRegister"
                                                                        name="PickupToRegister" class="form-control"
                                                                        value="{$pickup_to|escape:'htmlall':'UTF-8'}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="input-group mt-3" id="print_label_on_pickup">
                                            <div class="input-group-addon input-group-checkbox-custom">
                                                <input class="form-check-input mt-0" type="checkbox"
                                                    name="inputCheckPrintLabel" id="inputCheckPrintLabel" aria-label="">
                                            </div>
                                            <span class="input-group-text input-group-text-color" id="basic-addon2">
                                                {l s='Print label at pickup' mod='correosoficial'}
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings"
                                                    viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Imprimir etiqueta en la recogida">
                                                    <path
                                                        d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                </svg>
                                            </span>
                                        </div>

                                        <span class="register-options-advice">
                                            <span class="register-options-advice-red">* </span>
                                            {l s='Only Correos shipments. Click if Correos must have the labels printed on pickup' mod='correosoficial'}
                                        </span>

                                        <div class="input-group mt-3" id="select_package">
                                            <div class="input-group-addon input-group-text-custom">
                                                <span class="input-group-text input-group-text-color"
                                                    for="input_tamanio_paquete">
                                                    {l s='Package Size' mod='correosoficial'}
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                        fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings"
                                                        viewBox="0 0 16 16" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Tamaño de paquete">
                                                        <path
                                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <select class="custom-select" id="input_tamanio_paquete"
                                                name="input_tamanio_paquete" required>
                                                <option value="0" selected>&nbsp;</option>
                                                <option value="10">{l s='Envelopes' mod='correosoficial'}</option>
                                                <option value="20">{l s='Small (Shoes Box)' mod='correosoficial'}
                                                </option>
                                                <option value="30">{l s='Medium (Sheet Box)' mod='correosoficial'}
                                                </option>
                                                <option value="40">{l s='Big (box 80x80x80cm)' mod='correosoficial'}
                                                </option>
                                                <option value="50">
                                                    {l s='Very Big (greather than box 80x80x80cm)' mod='correosoficial'}
                                                </option>
                                                <option value="60">{l s='Pallete' mod='correosoficial'}</option>
                                            </select>
                                        </div>
                                        <span class="register-options-advice"><span
                                                class="register-options-advice-red">*
                                            </span>{l s='Only Correos shipments. Select package size for pickup.' mod='correosoficial'}</span>
                                    </div>
                                    {if empty($default_sender)}
                                        <div class="alert alert-warning alert-dismissible alert-utilities fade show"
                                            role="alert">
                                            <strong>{l s='Default sender' mod='correosoficial'}:</strong>
                                            {l s='You have not configured any default sender' mod='correosoficial'}
                                            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    {/if}

                                    <button id="generateOrdersButton" class="co_primary_button pull-right"
                                        type="button">
                                        <span id="processingOrdersButtonMsg" class="hidden-block">
                                            <span class="spinner-border spinner-border-sm" role="status"
                                                aria-hidden="true"></span>
                                            <span role="status"
                                                aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span id="generateOrdersButtonMsg" role="status"
                                            aria-hidden="true">{l s='Generate shippings' mod='correosoficial'}</span>
                                    </button>

                                </div>
                            </div>
                        </div>

                        <div class="input-group mb-3" id="print_label_reg_container">
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-success alert-dismissible alert-utilities fade show"
                                        role="alert">
                                        <strong>Envíos preregistrados. Puede imprimir las etiquetas</strong>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="input-group mb-3" id="input_tipo_etiqueta_container_gestion">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color"
                                                for="input_tipo_etiqueta_gestion">
                                                Tipo de etiqueta
                                            </span>
                                        </div>
                                        <select class="custom-select" id="input_tipo_etiqueta_gestion"
                                            name="input_tipo_etiqueta_gestion" required>
                                            {html_options options=$select_label_options selected=$DefaultLabel}
                                        </select>
                                    </div>
                                    <div class="input-group mb-3" id="input_format_etiqueta_container_gestion">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color"
                                                for="input_format_etiqueta_gestion">
                                                {l s='Label Format' mod='correosoficial'}
                                            </span>
                                        </div>
                                        <select class="custom-select" id="input_format_etiqueta_gestion"
                                            name="input_format_etiqueta_gestion" required>
                                            {html_options options=$select_label_options_format}
                                        </select>
                                    </div>
                                    <div class="input-group mb-3" id="input_pos_etiqueta_container_gestion">
                                        <div class="input-group-addon input-group-text-custom">
                                            <span class="input-group-text input-group-text-color"
                                                for="input_pos_etiqueta_gestion">
                                                Posición de etiqueta
                                            </span>
                                        </div>
                                        <select class="custom-select" id="input_pos_etiqueta_gestion"
                                            name="input_pos_etiqueta_gestion" required>
                                        </select>
                                    </div>
                                    <button id="printLabelsGenerated" class="btn-lg co_primary_button center-block"
                                        type="button" value="Imprimir etiquetas generadas PDF">
                                        <span id="ProcessingprintLabelsGeneratedButton" class="hidden-block">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span class="label-message" role="status" aria-hidden="true">Imprimir etiquetas generadas PDF</span>
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
    var activateDimensionsByDefault = "{$activateDimensionsByDefault}";
    var dimensionsByDefaultHeight = "{$dimensionsByDefaultHeight}";
    var dimensionsByDefaultLarge = "{$dimensionsByDefaultLarge}";
    var dimensionsByDefaultWidth = "{$dimensionsByDefaultWidth}";
</script>