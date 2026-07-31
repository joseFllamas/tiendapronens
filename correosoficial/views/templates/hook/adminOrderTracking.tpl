{if isset($sga_id_order) && $sga_id_order !== ''}
    <div id="correos_oficial_main_container" class="correos-oficial">
        <div class="card card-custom detail-order-container">
            <div class="card-header card-header-oder">
                <img src="{$co_base_dir}views/commons/img/logos/logo-order.png" alt="Correos" class="order-logo">
                <h2 class="order-title">
                    {l s='Order tracking' mod='correosoficial'}
                </h2>
            </div>

            <div class="container-details sga-container p-2">
                <div class="card-header card-header-date">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-info-square custom-icon" viewBox="0 0 16 16">
                        <path
                            d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z">
                        </path>
                        <path
                            d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z">
                        </path>
                    </svg>
                    <span>{l s='Shipping status' mod='correosoficial'}</span>
                </div>

                <table id="historic-table" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>{l s='Shipping code' mod='correosoficial'}</th>
                            <th>{l s='Carrier' mod='correosoficial'}</th>
                            <th>{l s='Status' mod='correosoficial'}</th>
                            <th>{l s='Date' mod='correosoficial'}</th>
                            <th>{l s='Hour' mod='correosoficial'}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="container-details sga-container p-2">
                <div class="card-header card-header-date clickable-header" data-loading="false">
                    <span>{l s='Order Logs' mod='correosoficial'}</span>
                    <svg class="toggle-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        viewBox="0 0 16 16">
                        <path fill-rule="evenodd"
                            d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </div>

                <div class="card-content-loading" style="display: none; padding: 10px;">
                    <div style="width: 100%; background-color: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div class="progress-bar" style="height: 4px; background-color: #007cba; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <p style="text-align: center; margin-top: 10px; color: #666; font-size: 13px;">{l s='Cargando logs...' mod='correosoficial'}</p>
                </div>

                <div class="card-content" style="overflow-y: scroll; overflow-x: auto;">
                    <style>
                        .clickable-header[data-loading="true"] {
                            cursor: not-allowed;
                            opacity: 0.6;
                            pointer-events: none;
                        }
                        @media (min-width: 1200px) {
                            #order-logs-table {
                                table-layout: fixed;
                            }

                            #order-logs-table th:nth-child(1),
                            #order-logs-table td:nth-child(1) {
                                width: 14%;
                            }

                            #order-logs-table th:nth-child(2),
                            #order-logs-table td:nth-child(2) {
                                width: 7%;
                            }

                            #order-logs-table th:nth-child(3),
                            #order-logs-table td:nth-child(3) {
                                width: 39%;
                            }

                            #order-logs-table th:nth-child(4),
                            #order-logs-table td:nth-child(4) {
                                width: 20%;
                            }

                            #order-logs-table th:nth-child(5),
                            #order-logs-table td:nth-child(5) {
                                width: 20%;
                            }

                            #order-logs-table td:nth-child(3),
                            #order-logs-table td:nth-child(4) {
                                word-break: break-word;
                            }
                        }
                    </style>
                    <table id="order-logs-table" class="display" style="width:100%">
                        <thead>
                        <tr>
                            <th>{l s='Timestamp' mod='correosoficial'}</th>
                            <th>{l s='Acción' mod='correosoficial'}</th>
                            <th>{l s='Mensaje' mod='correosoficial'}</th>
                            <th>{l s='Info' mod='correosoficial'}</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div>
        <input type="hidden" id="sga_id_order_hidden" name="sga_id_order_hidden" value="{$sga_id_order}" />
        <input type="hidden" id="sga_order_company_hidden" name="sga_order_company_hidden" value="{$sga_order_company}" />
    </div>
{else}
    <div class="col-sm-12 {if !$order_done}hidden-block{/if}">
        <div class="card history-container">
            <div class="card-header card-header-date">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-info-square custom-icon" viewBox="0 0 16 16">
                    <path
                        d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z">
                    </path>
                    <path
                        d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z">
                    </path>
                </svg>
                <span>{l s='Shipping status' mod='correosoficial'}</span>
            </div>
            <div class="card-body">
                <table id="historic-table" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>{l s='Shipping code' mod='correosoficial'}</th>
                            <th>{l s='Carrier' mod='correosoficial'}</th>
                            <th>{l s='Status' mod='correosoficial'}</th>
                            <th>{l s='Date' mod='correosoficial'}</th>
                            <th>{l s='Hour' mod='correosoficial'}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
{/if}
<script>
    var wrongDniCif = "{l s='Incorrect DNI/CIF number, please correct it before continuing' mod='correosoficial'}";
    var invalidNumber = "{l s='Input a valid number without symbols or blank spaces' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var wrongDniCif = "{l s='Incorrect DNI/CIF number, please correct it before continuing' mod='correosoficial'}";
    var wrongACCAndIBAN = "{l s='Please specify a valid Bank Account number/IBAN' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
    var invalidEmail = "{l s='Please enter a valid email address' mod='correosoficial'}";
    var homeTechnicalError="{l s='Error submitting the form. Please try again later. If the error persists, please contact Correos Technical Support.' mod='correosoficial'}";
    var sga_module = {if $sga_module}true{else}false{/if};
</script>
