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
<!-- INICIO BLOQUE REMITENTE -->
<div class="card" id="card-rte">

    <div class="card-header card-header-blue">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person custom-icon icon-margin-top" viewBox="0 0 16 16">
        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
        </svg>
        <h3 class="card-header-title">{l s='Sender Data' mod='correosoficial'}</h3>
    </div>

    <div class="card-body" id="container_sender">
        <div class="row">
            <div class="col-sm-6">
                {if empty($default_sender)}
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>{l s='Default sender' mod='correosoficial'}:</strong> {l s='You have not configured any default sender' mod='correosoficial'}
                        <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                {/if}
                {if !empty($senders)}
                <div class="input-group input-group-custom">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text">{l s='Sender' mod='correosoficial'}</span>
                    </div>
                    <select class="form-select" id="senderSelect" name="senderSelect" {if $order_done}disabled{/if}>
                    {foreach from=$senders item=sender}
                        {if $sender.id == $default_sender.id}
                            <option value="{$sender.id}" selected>{$sender.sender_name}</option>
                        {else}
                            <option value="{$sender.id}">{$sender.sender_name}</option>
                        {/if}
                    {/foreach}   
                    </select>
                    <div class="co_primary_link">
                        <a href="{$co_url_settings}#sender-anchor" target="blank">{l s='Edit Sender' mod='correosoficial'}</a>
                    </div>
                </div>
                
                <input type="hidden" id="sender_name" name="sender_name" class="form-control" value="{$default_sender.sender_name|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_contact" name="sender_contact" class="form-control" value="{$default_sender.sender_contact|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_address" name="sender_address" class="form-control" value="{$default_sender.sender_address|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_city" name="sender_city" class="form-control" value="{$default_sender.sender_city|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_country" name="sender_country" class="form-control"  value="{$default_sender.sender_iso_code_pais|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_phone" name="sender_phone" class="form-control"  value="{$default_sender.sender_phone|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_email" name="sender_email" class="form-control" value="{$default_sender.sender_email|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_nif_cif" name="sender_nif_cif" class="form-control" value="{$default_sender.sender_nif_cif|escape:'html':'UTF-8'}">
                <input type="hidden" id="sender_cp" name="sender_cp" class="form-control" value="{$default_sender.sender_cp|escape:'html':'UTF-8'}">
                <input type="hidden" id="correos_code" name="correos_code" class="form-control" value="{$default_sender.correos_code|escape:'html':'UTF-8'}">
                <input type="hidden" id="cex_code" name="cex_code" class="form-control" value="{$default_sender.cex_code|escape:'html':'UTF-8'}">  
            {/if}
            </div>

        </div>
    </div>
</div>
<!-- FIN BLOQUE REMITENTE -->

<script>
    var co_url_settings = "{$co_url_settings}";
</script>
