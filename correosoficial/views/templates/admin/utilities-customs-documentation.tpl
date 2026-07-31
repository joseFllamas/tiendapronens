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
 <div class="tab-pane fade" id="documentacion" role="tabpanel" aria-labelledby="documentacion-tab">

 <div class="row">
     <div class="col-sm-4 offset-md-4">
         <div class="input-group mb-3">
             <h2 class="buscador-pedidos-h2">{l s='Customs Documentation' mod='correosoficial'}</h2>
         </div>
         <form id="searchDocAduaneraOrdersForm" name="searchDocAduaneraOrdersForm" class="needs-validation" novalidate>
             <div class="input-group mb-3">
                 <div class="input-group-addon input-group-text-custom">
                     <span class="input-group-text input-group-text-color">{l s='From' mod='correosoficial'}</span>
                 </div>
                 <input type="date" id="inputFromDateCustomsDoc" name="inputFromDateCustomsDoc" class="form-control search-utilities-input">
             </div>
             <div class="input-group mb-3">
                 <div class="input-group-addon input-group-text-custom">
                     <span class="input-group-text input-group-text-color">{l s='Untill' mod='correosoficial'}</span>
                 </div>
                 <input type="date" id="inputToDateCustomsDoc" name="inputToDateCustomsDoc" class="form-control search-utilities-input">
             </div>
             <div class="input-group mb-3">
                 <input id="DocAduaneraSearchButton" name="DocAduaneraSearchButton" class="btn-lg co_primary_button center-block" type="button" value="{l s='Search shipments' mod='correosoficial'}">
             </div>
         </form>
     </div>
 </div>

 <div class="card card-margin card-table-utilities" id="card5">
     <div class="card-header">
         {l s='GENERATION OF CUSTOMS DOCUMENTATION' mod='correosoficial'}
     </div>
     <div id="customsDocumentationTable"  class="card-body card-body-custom">
         <a class="show-cols5">{l s='Show/Hide columns' mod='correosoficial'}</a> 
         <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#002e6d" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="right" title="Click en cada elemento de la lista para mostrar/ocultar columnas de la tabla">
             <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
         </svg>
         <div class="showButtonsContainer5 hidden-block">
             <a class="toggle-vis5" data-column="1">ID</a> 
             <a class="toggle-vis5" data-column="2">{l s='Shipping Ref.' mod='correosoficial'}</a> 
             <a class="toggle-vis5" data-column="3">{l s='Shipping Code' mod='correosoficial'}</a>
             <a class="toggle-vis5" data-column="4">{l s='Carrier' mod='correosoficial'}</a>
             <a class="toggle-vis5" data-column="5">{l s='Name of addressee' mod='correosoficial'}</a>
             <a class="toggle-vis5" data-column="6">{l s='Address of addressee' mod='correosoficial'}</a>
             <a class="toggle-vis5" data-column="7">{l s='Country of addressee' mod='correosoficial'}</a>
             <a class="toggle-vis5" data-column="8">{l s='Date of creation' mod='correosoficial'}</a>
         </div>
         <table id="DocAduaneraDataTable" class="table table-striped" width="100%">
             <thead>
                 <tr>
                     <th><input type="checkbox" name="table-select-all-doc-aduanera" value="1" id="table-select-all-doc-aduanera"></th>
                     <th>ID</th>
                     <th>{l s='Shipping Ref' mod='correosoficial'}</th>
                     <th>{l s='Shipping Code' mod='correosoficial'}</th>
                     <th>{l s='Carrier' mod='correosoficial'}</th>                            
                     <th>{l s='Name of addressee' mod='correosoficial'}</th>
                     <th>{l s='Address of addressee' mod='correosoficial'}</th>
                     <th>{l s='Country of addressee' mod='correosoficial'}</th>
                     <th>{l s='Date of creation' mod='correosoficial'}</th>
                     <th>{if isset($Customs) && $Customs neq ''}{$Customs}{/if}</th>
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
                    </tr>                 
                    <tr class="correos_oficial_datatable_footer">
                        <th></th>
                        <th>ID</th>
                        <th>{l s='Shipping Ref' mod='correosoficial'}</th>
                        <th>{l s='Shipping Code' mod='correosoficial'}</th>
                        <th>{l s='Carrier' mod='correosoficial'}</th>
                        <th>{l s='Name of addressee' mod='correosoficial'}</th>
                        <th>{l s='Address of addressee' mod='correosoficial'}</th>
                        <th>{l s='Country of addressee' mod='correosoficial'}</th>
                        <th>{l s='Date of creation' mod='correosoficial'}</th>
                    </tr>
                </tfoot>
         </table>

         <div class="row bottom-utilities-form-row">
             <form id="ImprimirDocAcuaneraForm">
                 <div class="col-sm-8" id="datatable_results_aduanera_container">
                     <div id="responseDocAduanera"></div>

                     <div class="card card-custom card-margin">
                         <div class="card-header">
                             {l s='ERRORS IN GENERATION OF CUSTOMS DOCUMENTATION' mod='correosoficial'}
                         </div>
                         <div class="card-body card-body-custom">
                             <table id="datatableResultsAduanera" class="table table-striped" style="width:100%">
                                 <thead>
                                     <tr>
                                         <th>ID</th>
                                         <th>{l s='Reference' mod='correosoficial'}</th>
                                         <th>Error</th>
                                     </tr>
                                 </thead>
                             </table>
                         </div>
                     </div>

                 </div>
                 <div class="col-sm-12">
                     
                     <div class="input-group mb-3">
                         <div class="card align-content-center">
                             <div class="card-body center-block">
                                 <button id="ImprimirCN23Button" class="btn-lg co_primary_button PrintGestionAduaneraLabels" type="button">
                                    <span class="spin hidden-block">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                        </span>
                                        <span class="label-message" role="status" aria-hidden="true">{l s='Print CN23' mod='correosoficial'}</span>
                                 </button>
                                 <button id="ImprimirDUAButton" class="btn-lg co_primary_button PrintGestionAduaneraLabels" type="button">
                                    <span class="spin hidden-block">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                    </span>
                                    <span class="label-message" role="status" aria-hidden="true">{l s='Print DCAF' mod='correosoficial'}</span>
                                 </button>
                                 <button id="ImprimirDDPButton" class="btn-lg co_primary_button PrintGestionAduaneraLabels" type="button" value="{l s='Print DDP' mod='correosoficial'}">
                                    <span class="spin hidden-block">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                                    </span>
                                    <span class="label-message" role="status" aria-hidden="true">{l s='Print DDP' mod='correosoficial'}</span>
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