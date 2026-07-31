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
<div id="MarketplaceConfigurationBlock" class="accordion-body">
    <form id="MarketplaceConfigurationDataForm" name="MarketplaceConfigurationDataForm" method="POST" enctype="multipart/form-data">
        <fieldset>
            <div class="row">

                {* ── WooCommerce not active warning ── *}
                {if !$MarketplaceWooCommerceActive}
                <div class="col-sm-12 mb-3">
                    <div class="alert alert-warning">
                        {l s='WooCommerce is required to activate the Marketplace integration. Please install and activate WooCommerce first.' mod='correosoficial'}
                    </div>
                </div>
                {/if}

                {* ── Enable checkbox ── *}
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-checkbox-custom d-flex align-items-center justify-content-center">
                                    <input class="form-check-input mt-0" type="checkbox" name="ActivateMarketplace"
                                        id="ActivateMarketplace" {if $ActivateMarketplace == 'on'}checked="checked"{/if}
                                        {if !$MarketplaceWooCommerceActive}disabled="disabled"{/if}>
                                </div>
                                <span class="input-group-text input-group-text-color w-50">
                                    {l s='Enable Marketplace Sales' mod='correosoficial'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {* ── Extra configuration block (shown when Marketplace is active) ── *}
                <div class="col-sm-12" id="MarketplaceConfigurationDataBlock" {if $ActivateMarketplace != 'on'}style="display: none;"{/if}>

                    {* ── API credentials info (shown once the key has been generated) ── *}
                    <div id="MarketplaceApiInfoBlock" {if !$MarketplaceConsumerKey}style="display: none;"{/if}>
                        <hr>

                        {* Consumer Key *}
                        <div class="row mb-3 align-items-center">
                            <label class="col-sm-3 col-form-label fw-semibold">
                                {l s='Consumer Key' mod='correosoficial'}
                            </label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control font-monospace" id="MarketplaceConsumerKeyInput"
                                    value="{$MarketplaceConsumerKey|escape:'html'}" readonly>
                            </div>
                            <div class="col-sm-2">
                                <button type="button" class="btn btn-secondary w-100"
                                    data-co-copy="MarketplaceConsumerKeyInput">
                                    {l s='Copy' mod='correosoficial'}
                                </button>
                            </div>
                        </div>

                        {* Consumer Secret *}
                        <div class="row mb-3 align-items-center">
                            <label class="col-sm-3 col-form-label fw-semibold">
                                {l s='Consumer Secret' mod='correosoficial'}
                            </label>
                            <div class="col-sm-7">
                                <input type="password" class="form-control font-monospace" id="MarketplaceConsumerSecretInput"
                                    value="{$MarketplaceConsumerSecret|escape:'html'}" readonly>
                            </div>
                            <div class="col-sm-2 d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary flex-fill"
                                    id="MarketplaceToggleSecret" title="{l s='Show / Hide' mod='correosoficial'}">
                                    <span class="co-eye-icon">&#128065;</span>
                                </button>
                                <button type="button" class="btn btn-secondary flex-fill"
                                    data-co-copy="MarketplaceConsumerSecretInput">
                                    {l s='Copy' mod='correosoficial'}
                                </button>
                            </div>
                        </div>

                        {* Base URL *}
                        <div class="row mb-3 align-items-center">
                            <label class="col-sm-3 col-form-label fw-semibold">
                                {l s='Base URL' mod='correosoficial'}
                            </label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control font-monospace" id="MarketplaceApiBaseUrlInput"
                                    value="{$MarketplaceApiBaseUrl|escape:'html'}" readonly>
                            </div>
                            <div class="col-sm-2">
                                <button type="button" class="btn btn-secondary w-100"
                                    data-co-copy="MarketplaceApiBaseUrlInput">
                                    {l s='Copy' mod='correosoficial'}
                                </button>
                            </div>
                        </div>

                        {* Exposed endpoints table *}
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-semibold">
                                {l s='Exposed Endpoints' mod='correosoficial'}
                            </label>
                            <div class="col-sm-9">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{l s='Resource' mod='correosoficial'}</th>
                                            <th>{l s='Endpoint' mod='correosoficial'}</th>
                                            <th>{l s='Methods' mod='correosoficial'}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $MarketplaceResources as $res}
                                        <tr>
                                            <td>{$res.name|escape:'html'}</td>
                                            <td><code>{$res.endpoint|escape:'html'}</code></td>
                                            <td>{$res.methods|escape:'html'}</td>
                                        </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>{* /MarketplaceApiInfoBlock *}

                    {* ── Pending placeholder (shown before the first save) ── *}
                    <div id="MarketplaceApiPendingBlock" {if $MarketplaceConsumerKey}style="display: none;"{/if}>
                        <hr>
                        <div class="alert alert-info">
                            {l s='Marketplace API key not yet generated. Save the configuration to create it.' mod='correosoficial'}
                        </div>
                    </div>

                </div>{* /MarketplaceConfigurationDataBlock *}

                <div class="col-sm-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="co_primary_button" name="MarketplaceConfigurationSaveButton"
                            id="MarketplaceConfigurationSaveButton" type="submit"
                            {if !$MarketplaceWooCommerceActive}disabled="disabled"{/if}>
                            <span id="ProcessingMarketplaceConfigButton" class="hidden-block">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span role="status" aria-hidden="true">{l s='Processing' mod='correosoficial'}</span>
                            </span>
                            <span class="label-message" id="MsgMarketplaceConfigButton"
                                role="status">{l s='SAVE MARKETPLACE DATA' mod='correosoficial'}</span>
                        </button>
                    </div>
                </div>

            </div>
        </fieldset>
    </form>
</div>

<script>
    var marketplaceConfigurationSaved = "{$Marketplace_data_successfully_saved}";
    var marketplaceAccordionLockedTitle = "{$This_section_is_managed_by_Marketplace}";
    var marketplaceDeactivateWarning = "{$If_you_save_the_current_API_access_will_be_revoked}";
</script>
