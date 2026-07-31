<div class="extra-container ">
    <div class="checkout-paq-advice">
        <span>{l s='Enter the Postcode to locate a collection point' mod='correosoficial'}</span>
    </div>

    <div class="search-paq-section">
        <div class="section-SearchPudoCEXByCPInput">
            <input type="text" name="SearchPudoCEXByCPInput_{$params.id_carrier|intval}"
                id="SearchPudoCEXByCPInput_{$params.id_carrier|intval}"
                class="search-pudocex-by-cp-input form-control frontColorStyle" />
        </div>
        <div class="section-SearchPudoCEXByCp">
            <button class="btn btn-outline SearchPudoCEXByCp co_primary_button"
                id="SearchPudoCEXByCpButton_{$params.id_carrier|intval}" type="button">
                {l s='Search' mod='correosoficial'}
            </button>
        </div>
        <div class="section-frontOptionSelector">
            <select class="pudocexSelector frontOptionSelector" name="PudoCEXSelect_{$params.id_carrier|intval}"
                id="PudoCEXSelect_{$params.id_carrier|intval}">
                <option value="none">{l s='Collection point not found' mod='correosoficial'}</option>
            </select>
            <input type="hidden" name="pudocex_postcode_{$params.id_carrier|intval}"
                id="pudocex_postcode_{$params.id_carrier|intval}" value="{$params.postcode}" />
            <input type="hidden" name="pudocex_reference_{$params.id_carrier|intval}"
                id="pudocex_reference_{$params.id_carrier|intval}" value="{$params.reference|default:''}" />
            <input type="hidden" name="pudocex_selector_{$params.id_carrier|intval}"
                id="pudocex_selector_{$params.id_carrier|intval}" value="{$params.id_carrier|intval}" />
            <input type="hidden" name="pudocex_depth_{$params.id_carrier|intval}"
                id="pudocex_depth_{$params.id_carrier|intval}" value="{$params.depth|intval}" />
            <input type="hidden" name="pudocex_width_{$params.id_carrier|intval}"
                id="pudocex_width_{$params.id_carrier|intval}" value="{$params.width|intval}" />
            <input type="hidden" name="pudocex_height_{$params.id_carrier|intval}"
                id="pudocex_height_{$params.id_carrier|intval}" value="{$params.height|intval}" />
            <input type="hidden" name="pudocex_perimetral_{$params.id_carrier|intval}"
                id="pudocex_perimetral_{$params.id_carrier|intval}" value="{$params.perimetral|intval}" />
            <input type="hidden" name="pudocex_cart_id_{$params.id_carrier|intval}"
                id="pudocex_cart_id_{$params.id_carrier|intval}" value="{$params.id_cart|intval}" />
            <input type="hidden" name="pudocex_company_{$params.id_carrier|intval}"
                id="pudocex_company_{$params.id_carrier|intval}" value="{$params.company}" />
        </div>
    </div>

    {if $aviso_aduanas_interiores eq 'on' && $require_customs_doc eq true}
        <div class="customs-advice-doc">
            <h3>{l s='Customs Notice' mod='correosoficial'}</h3>
            <p> {$string_translated} </p> 
        </div>
    {/if}
    
    <div class="schedule-and-map" id="scheduleAndMap_{$params.id_carrier|intval}">
        
        <div class="office-schedule-and-map">
            
            <div class="office-schedule-and-map-left columna">

                <h3>{l s='Point' mod='correosoficial'}</h3>

                <div id="terminalInfo{$params.id_carrier|intval}" class="office-terminal-info">
                    <p class="nombre mb-2">
                        {if isset($officeOrCityPaqParams[0].nombre)}{$officeOrCityPaqParams[0].nombre}{/if}</p>
                </div>

                <h3>{l s='Timetable' mod='correosoficial'}</h3>

                <div class="scheduleInfo schedule-section" id="scheduleInfo_{$params.id_carrier|intval}">
                    {l s='Monday to Friday' mod='correosoficial'}:
                    <p class="timeScheduleLV">
                        {if isset($officeOrCityPaqParams[0].horarioLV)}{$officeOrCityPaqParams[0].horarioLV}{/if}
                    </p>
                    {l s='Saturday' mod='correosoficial'}:
                    <p class="timeScheduleS">
                        {if isset($officeOrCityPaqParams[0].horarioLS)}{$officeOrCityPaqParams[0].horarioLS}{/if}
                    </p>
                    {l s='Holidays' mod='correosoficial'}:
                    <p class="timeScheduleF">
                        {if isset($officeOrCityPaqParams[0].horarioLF)}{$officeOrCityPaqParams[0].horarioLF}{/if}
                    </p>
                </div>

                
            </div>
            <div class="office-schedule-and-map-right columna">

                <h3>{l s='Location' mod='correosoficial'}</h3>

                <div class="office-address-info" id="addressInfo{$params.id_carrier|intval}">
                    {l s='Address' mod='correosoficial'}:
                    <p class="address">
                        {if isset($officeOrCityPaqParams[0].direccion)}{$officeOrCityPaqParams[0].direccion}{/if}
                    </p>
                    {l s='City' mod='correosoficial'}:
                    <p class="city">
                        {if isset($officeOrCityPaqParams[0].descLocalidad)}{$officeOrCityPaqParams[0].descLocalidad}{/if}
                    </p>
                    {l s='Zip Code' mod='correosoficial'}:
                    <p class="cp">
                        {if isset($officeOrCityPaqParams[0].cp)}{$officeOrCityPaqParams[0].cp}{/if}
                    </p>
                    {l s='Phone' mod='correosoficial'}:
                    <p class="phone">
                        {if isset($officeOrCityPaqParams[0].telefono)}{$officeOrCityPaqParams[0].telefono}{/if}
                    </p>
                </div>
            </div>

        </div>
        
        {if $show_maps }
            <div class="map-section">
                <div id="GoogleMapCorreos_{$params.id_carrier|intval}" class="map"></div>
            </div>
        {/if}

    </div>
</div>

<script>
    var openingInfo = "{l s='Opening hours' mod='correosoficial'}";
    var opening24hInfo = "{l s='Open 24 hours' mod='correosoficial'}";
    var pudoCEXNotFound = "{l s='Can not connect with the PudoCEX service' mod='correosoficial'}";
    var pudoCEXPostCodeNotFound = "{l s='Can not find PudoCEXs for postal code' mod='correosoficial'} ";
    var collectionPointNotFound = "{l s='Collection point not found' mod='correosoficial'}";
    var pickupPointSameProvince = "{l s='You must choose a pickup point in the same province as the shipping address' mod='correosoficial'} ";
    var ajaxError = "{l s='Han error has ocurred calling the PudoCEX locator service' mod='correosoficial'}";
    var searchForPudoCEX =
    "{l s='Please search and select a terminal before completing the order' mod='correosoficial'}";
    var defined_google_api_key = '{$defined_google_api_key}';
    var show_maps = '{$show_maps}';
</script>