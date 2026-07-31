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
{include file='./header.tpl'}

<link rel="stylesheet" type="text/css" href="{$co_base_dir}views/commons/css/datatables/dataTables.bootstrap5.min.css"/>

<div id="correos_oficial">
    <div id="utilities-container" class="">
                    
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="gestion-tab" data-bs-toggle="tab" data-bs-target="#gestion" type="button" role="tab" aria-controls="gestion" aria-selected="true">{l s='Mass Orders Management' mod='correosoficial'}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reimpresion-tab" data-bs-toggle="tab" data-bs-target="#reimpresion" type="button" role="tab" aria-controls="reimpresion" aria-selected="false">{l s='Label Reprint' mod='correosoficial'}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="generacion-tab" data-bs-toggle="tab" data-bs-target="#generacion" type="button" role="tab" aria-controls="generacion" aria-selected="false">{l s='Order Summary' mod='correosoficial'}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pickups-tab" data-bs-toggle="tab" data-bs-target="#collected" type="button" role="tab" aria-controls="collected" aria-selected="false">{l s='Pickups' mod='correosoficial'}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documentacion-tab" data-bs-toggle="tab" data-bs-target="#documentacion" type="button" role="tab" aria-controls="documentacion" aria-selected="false">{l s='Generation of Customs Documentation' mod='correosoficial'}</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            {include file='./utilities-manage-massive.tpl'}
            {include file='./utilities-reprint-labels.tpl'}            
            {include file='./utilities-orders-summary.tpl'}
            {include file='./utilities-pickups.tpl'}
            {include file='./utilities-customs-documentation.tpl'}            
        </div>
        
    </div>
</div> 

<script type="text/javascript" src="{$co_base_dir}views/js/cloudfare/pdfmake.min.js"></script>
<script type="text/javascript" src="{$co_base_dir}views/js/cloudfare/vfs_fonts.js"></script>
<script type="text/javascript" src="{$co_base_dir}views/js/datatables/datatables.min.js"></script>


<script>
    var order_token = "{$order_token}";

    var customsProcessingSaved = "{l s='Customs Processing successfully saved' mod='correosoficial'}";
    var selectedCarrierMaxParcels = "{l s='The selected carrier does not allow shipments of multiple packages' mod='correosoficial'}";
    var parcelMaxForthisProduct = "{l s='The maximum number of packages for this product is' mod='correosoficial'}";

    var orderNumber="- "+"{l s='Shipping number:' mod='correosoficial'}";
    var youCanOnlyChangeBetweenHome="{l s='You cannot change from a Home delivery method to CityPaq or Office' mod='correosoficial'}";
    var youCanOnlyChangeBetweenCityPaqToOffice="{l s='You cannot change from a CityPaq delivery method to Office' mod='correosoficial'}";
    var youCanOnlyChangeBetweenOfficeToCityPaq="{l s='You cannot change from a Office delivery method to CityPaq' mod='correosoficial'}";

    var mustSelectOneRecord="{l s='Must select at least one record of the table' mod='correosoficial'}";
    var dateFromIsMinor="{l s='Date «to» is minor than date «from»' mod='correosoficial'}";

    var mustSelectPackageSize="{l s='Must select a package size' mod='correosoficial'}";

    var dateTodayWarning="{l s='It is not possible to request collection for the same day in Correos shipments' mod='correosoficial'}";
    var date30DaysAfterTodayWarning="{l s='It is not possible to request collection for dates after 30 days' mod='correosoficial'}";
    var datePastWarning="{l s='It is not possible to request collection for past dates' mod='correosoficial'}";

    var title_must_cancel_preregister="{l s='Please, cancel first the preregister in orders managment to do a new preregistration request' mod='correosoficial'}";
    var title_must_cancel_pickup="{l s='Please, cancel first the preregister in orders managment to do a new collection request' mod='correosoficial'}";
    var title_must_specify_measurements ="{l s='Please, go to order and specify packages measurements and weight' mod='correosoficial'}";

    var AT_Code_Only_CEX_and_Portugal="{l s='Only apply to CEX products and Portugal Shippings' mod='correosoficial'}";
    var ERRORS_IN_PRINT_LABEL_GENERATION="{l s='ERRORS IN PRINT LABEL GENERATION' mod='correosoficial'}";
</script>