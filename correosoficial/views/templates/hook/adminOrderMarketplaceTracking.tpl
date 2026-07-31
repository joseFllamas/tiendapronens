<div id="correos_oficial_main_container" class="correos-oficial">
    <div class="card card-custom detail-order-container">

        <div class="card-header card-header-oder">
            <img src="{$co_base_dir}views/commons/img/logos/logo-order.png" alt="Correos" class="order-logo">
            <h2 class="order-title">
                {l s='Order tracking (Marketplace)' mod='correosoficial'}
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

            {if $marketplace_tracking_number neq ''}
                {* Tracking number available: DataTable populated via AJAX *}
                <table id="marketplace-historic-table" class="display" style="width:100%">
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
            {else}
                {* No tracking number yet: show placeholder — no API call made *}
                <div class="alert alert-info mt-2" style="margin:1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                         class="bi bi-hourglass-split me-1" viewBox="0 0 16 16" style="margin-right:6px">
                        <path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 13v1h1a.5.5 0 0 1 0 1h-11z"/>
                    </svg>
                    {l s='Awaiting tracking number from Marketplace.' mod='correosoficial'}
                    {l s='The status table will appear once the shipment is registered.' mod='correosoficial'}
                </div>
            {/if}
        </div>

    </div>
</div>

{*
    #order_exp_number_hidden with empty value prevents the library's
    setDatatableHistory() from making an unintended AJAX call when
    sga_module = true is set below.
*}
<input type="hidden" id="order_exp_number_hidden"             value="">
<input type="hidden" id="marketplace_id_order_hidden"         value="{$marketplace_id_order}">
<input type="hidden" id="marketplace_tracking_number_hidden"  value="{$marketplace_tracking_number}">

<script>
    /* Block the standard admin-order.js / ajax_wc_admin_order.js flows */
    var sga_module       = true;
    var marketplace_mode = true;

    var wrongDniCif              = "{l s='Incorrect DNI/CIF number, please correct it before continuing' mod='correosoficial'}";
    var invalidNumber            = "{l s='Input a valid number without symbols or blank spaces' mod='correosoficial'}";
    var requiredCustomMessage    = "{l s='Required field' mod='correosoficial'}";
    var wrongACCAndIBAN          = "{l s='Please specify a valid Bank Account number/IBAN' mod='correosoficial'}";
    var minLengthMessage         = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage         = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters               = "{l s='characters' mod='correosoficial'}";
    var invalidEmail             = "{l s='Please enter a valid email address' mod='correosoficial'}";
    var homeTechnicalError       = "{l s='Error submitting the form. Please try again later. If the error persists, please contact Correos Technical Support.' mod='correosoficial'}";

    {if $marketplace_tracking_number neq ''}
    jQuery(document).ready(function () {
        var marketplace_table = jQuery('#marketplace-historic-table').DataTable({
            paging    : false,
            info      : false,
            searching : false,
            orderable : false,
            columns   : [
                { data: 'codEnvio' },
                { data: 'codProducto' },
                { data: 'desTextoResumen', className: 'text-center' },
                { data: 'fecEvento',       className: 'text-center' },
                { data: 'horEvento',       className: 'text-center' }
            ],
            columnDefs: [
                {
                    targets : 2,
                    render  : function (data) {
                        switch (data) {
                            case 'Prerregistrado':
                                return '<div class="preregistrado">' + data + '</div>';
                            case 'Admitido':
                            case 'En tránsito':
                            case 'En reparto':
                            case 'Alta en la unidad de reparto':
                            case 'Clasificado':
                                return '<div class="en_curso">' + data + '</div>';
                            case 'Admisión anulada':
                                return '<div class="anulado">' + data + '</div>';
                            case 'A disposición del destinatario':
                            case 'Entregado':
                                return '<div class="entregado">' + data + '</div>';
                            case 'No informado':
                                return '<div class="no-informado">' + data + '</div>';
                            default:
                                return '<div class="intermedio">' + data + '</div>';
                        }
                    }
                }
            ],
            order: [
                [3, 'desc'],
                [4, 'desc']
            ]
        });

        jQuery.ajax({
            type : 'post',
            url  : varsAjax.ajaxUrl,
            data : {
                action     : 'correosOficialDispacher',
                _nonce     : varsAjax.nonce,
                dispatcher : {
                    controller     : 'CorreosOficialAdminOrderModuleFrontController',
                    action         : 'getMarketplaceOrderStatus',
                    shipping_number: jQuery('#marketplace_tracking_number_hidden').val(),
                    id_order       : jQuery('#marketplace_id_order_hidden').val()
                }
            },
            success: function (parsed_data) {
                marketplace_table.clear().draw();
                marketplace_table.rows.add(parsed_data.events);
                marketplace_table.columns.adjust().draw();
                jQuery('.history-container').removeClass('hidden-block');
            }
        });
    });
    {/if}
</script>
