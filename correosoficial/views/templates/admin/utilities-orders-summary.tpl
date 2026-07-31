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
 <div class="tab-pane fade" id="generacion" role="tabpanel" aria-labelledby="generacion-tab">
            
    <div class="row">
        <div class="col-sm-4 offset-md-4">
            <div class="input-group mb-3">
                <h2 class="buscador-pedidos-h2">{l s='Order Summary' mod='correosoficial'}</h2>
            </div>
            <form id="searchResumeOrdersForm" name="searchResumeOrdersForm" class="needs-validation" novalidate>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='From' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputFromDateSummary" name="inputFromDateSummary" class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Untill' mod='correosoficial'}</span>
                    </div>
                    <input type="date" id="inputToDateSummary" name="inputToDateSummary" class="form-control search-utilities-input">
                </div>
                <div class="input-group mb-3">
                    <span class="input-group-text input-group-text-color">{l s='Search by labeling date' mod='correosoficial'}</span> 
                    <div class="input-group-addon input-group-checkbox-custom">
                        <input class="form-check-input mt-0" type="checkbox" id="checkSearchByLabelingDate" name="checkSearchByLabelingDate" >
                    </div>   
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Senders' mod='correosoficial'}</span>
                    </div>
                    <select class="form-select select-label select-label-return" id="inputOrdersSummarySenders" name="inputOrdersSummarySenders" >
                        <option value="0">{l s='All' mod='correosoficial'}</option>
                        {foreach from=$select_senders_options item='sender'}
                            <option value="{$sender.id}">{$sender.name}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="input-group mb-3">
                    <input id="SummarySearchButton" name="SummarySearchButton" class="btn-lg co_primary_button center-block" type="button" value="{l s='Search orders' mod='correosoficial'}">
                </div>
            </form>
        </div>
    </div>

    <div class="card card-margin card-table-utilities" id="card3">
        <div class="card-header">
            {l s='ORDER SUMMARY' mod='correosoficial'}
        </div>
        <div id= "ordersSummaryTable" class="card-body card-body-custom">
            <a class="show-cols3">{l s='Show/Hide columns' mod='correosoficial'}</a> 
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#002e6d" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="right" title="Click en cada elemento de la lista para mostrar/ocultar columnas de la tabla">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
            <div class="showButtonsContainer3 hidden-block">
                <a class="toggle-vis3" data-column="1">ID</a> 
                <a class="toggle-vis3" data-column="2">{l s='Reference' mod='correosoficial'}</a> 
                <a class="toggle-vis3" data-column="3">{l s='Shipping Code' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="4">{l s='Package code' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="5">{l s='Carrier' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="7">{l s='Recipient name' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="8">{l s='Shipping address' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="9">{l s='CP' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="10">{l s='Date/Hour recording' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="11">{l s='Labeling date' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="12">{l s='Manifested' mod='correosoficial'}</a>
                <a class="toggle-vis3" data-column="13">{l s='Manifest_date' mod='correosoficial'}</a>
            </div>
            <table id="ResumenDataTable" class="table table-striped" width="100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" name="table-select-all-resumen" value="1" id="table-select-all-resumen"></th>
                        <th>ID</th>
                        <th>{l s='Reference' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th> 
                        <th>{l s='Package code' mod='correosoficial'}</th> 
                        <th>{l s='Carrier' mod='correosoficial'}</th>        
                        <th>{l s='Customer code' mod='correosoficial'}</th>              
                        <th>{l s='Recipient name' mod='correosoficial'}</th>
                        <th>{l s='Shipping address' mod='correosoficial'}</th>
                        <th>{l s='CP' mod='correosoficial'}</th>
                        <th>{l s='Date/Hour recording' mod='correosoficial'}</th>
                        <th>{l s='Labeling date' mod='correosoficial'}</th>
                        <th>{l s='Manifested' mod='correosoficial'}</th>
                        <th>{l s='Manifest_date' mod='correosoficial'}</th>
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
                    </tr>  
                    <tr class="correos_oficial_datatable_footer">
                        <th></th>
                        <th>ID</th>
                        <th>{l s='Reference' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th> 
                        <th>{l s='Package code' mod='correosoficial'}</th> 
                        <th>{l s='Carrier' mod='correosoficial'}</th>        
                        <th>{l s='Customer code' mod='correosoficial'}</th>              
                        <th>{l s='Recipient name' mod='correosoficial'}</th>
                        <th>{l s='Shipping address' mod='correosoficial'}</th>
                        <th>{l s='CP' mod='correosoficial'}</th>
                        <th>{l s='Date/Hour recording' mod='correosoficial'}</th>
                        <th>{l s='Labeling date' mod='correosoficial'}</th>
                        <th>{l s='Manifested' mod='correosoficial'}</th>
                        <th>{l s='Manifest_date' mod='correosoficial'}</th>
                     </tr>
                 </tfoot>
            </table>

            <div class="row bottom-utilities-form-row">
                <form id="ImprimirResumenForm">
                    <div class="col-sm-8">
                        
                    </div>
                    <div class="col-sm-4">
                        
                        <div class="input-group mb-3">
                            <div class="card">
                                <div class="card-body" id="button_print">
                                    <button id="ImprimirResumenButton" class="btn-lg co_primary_button center-block" type="button" value="Imprimir manifiesto de pedidos">
                                        <span id="ProcessingImprimirResumenButton" class="hidden-block">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span class="label-message" role="status" aria-hidden="true">Imprimir manifiesto de pedidos</span>
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