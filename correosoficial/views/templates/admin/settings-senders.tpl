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
<div class="accordion-body">
    <fieldset>
        <form id="CorreosSendersForm" name="CorreosSendersForm" class="needs-validation" novalidate>
        <div class="col-sm-6">
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Sender name' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_name" name="sender_name" class="form-control" placeholder="" required>
                <input type="hidden" id="sender_id" name="sender_id" class="form-control" placeholder="sender_id">
            </div>
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Contact person' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_contact" name="sender_contact" class="form-control" placeholder="">
            </div>

            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Address' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_address" name="sender_address" class="form-control" placeholder="" required>
            </div>
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='City' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_city" name="sender_city" class="form-control" placeholder="" required>
            </div>
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Zip code' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_cp" name="sender_cp" class="form-control" placeholder="" required>
            </div>
        </div>
        <div class="col-sm-6">
        
            <div class="col-sm-4 p-0">
                <div class="input-group mb-4">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Country' mod='correosoficial'}</span>
                    </div>
                    
                    <select class="co_dropdown" id="sender_iso_code_pais" name="sender_iso_code_pais" required>
                        <option selected disabled value=""></option>
                        <option value="ES">{l s='Spain' mod='correosoficial'}</option>
                        <option value="PT">{l s='Portugal' mod='correosoficial'}</option>
                        <option value="AD">{l s='Andorra' mod='correosoficial'}</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-8 p-0">
                <div class="input-group mb-4">
                    <div class="input-group-addon input-group-text-custom">
                        <span class="input-group-text input-group-text-color">{l s='Phone number' mod='correosoficial'}</span>
                    </div>
                    <input type="tel" id="sender_phone" name="sender_phone" class="form-control" placeholder="">
                </div>
            </div>

            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Email' mod='correosoficial'}</span>
                </div>
                <input type="email" id="sender_email" name="sender_email" class="form-control" placeholder="">
            </div>
            <div class="input-group mb-4">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='NIF/CIF' mod='correosoficial'}</span>
                </div>
                <input type="text" id="sender_nif_cif" name="sender_nif_cif" class="form-control" placeholder="" required>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="input-group mb-4" id="">
                        <div class="input-group-addon input-group-text-custom">
                            <span class="input-group-text input-group-text-color">
                                {l s='From hour' mod='correosoficial'}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Escoja el inicio de la franja horaria para las recogidas">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            </span>
                        </div>
                        <input type="time" id="sender_from_time" name="sender_from_time" class="form-control">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="input-group mb-4" id="">
                        <div class="input-group-addon input-group-text-custom">
                            <span class="input-group-text input-group-text-color">
                                {l s='To hour' mod='correosoficial'}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3" class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16" data-bs-toggle="tooltip" data-bs-placement="top" title="Escoja el final de la franja horaria para las recogidas">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            </span>
                        </div>
                        <input type="time" id="sender_to_time" name="sender_to_time" class="form-control">
                    </div>
                </div>
            </div>
            <div class="input-group mb-4 correosCountSelect">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='Correos Account' mod='correosoficial'}</span>
                </div>                    
                <select class="co_dropdown" id="correos_code" name="correos_code">
                    {foreach from=$optionsCorreos item='optionCorreos'}
                        <option value="{$optionCorreos.id}">
                            {if $optionCorreos.CorreosClientID != "n/a"}
                                {$optionCorreos.CorreosCustomer}
                            {else}
                                {$optionCorreos.CorreosContract}/{$optionCorreos.CorreosCustomer}
                            {/if}
                        </option>
                    {/foreach}
                    <option value="">{l s='At the moment I am not going to use an Correos account.' mod='correosoficial'}</option>
                </select>
            </div>           
            <div class="input-group mb-4 correosCountSelect">
                <div class="input-group-addon input-group-text-custom">
                    <span class="input-group-text input-group-text-color">{l s='CEX Account' mod='correosoficial'}</span>
                </div>                    
                <select class="co_dropdown" id="cex_code" name="cex_code">
                    {foreach from=$optionsCex item='optionCex'}
                        <option value="{$optionCex.id}">{$optionCex.CEXCustomer}</option>
                    {/foreach}
                    <option value="">{l s='At the moment I am not going to use an CEX account.' mod='correosoficial'}</option>
                </select>
            </div>
        </div>

        <div class="col-sm-12 card-margin">
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <input id="SendersSaveButton" class="btn btn-primary me-md-2" type="submit" value="{l s='ADD' mod='correosoficial'}">
                <input id="SendersEditButton" class="btn btn-primary me-md-2" type="button" value="{l s='SAVE' mod='correosoficial'}" disabled>
                <input id="SendersCleanButton" class="btn btn-danger me-md-1" type="reset" value="{l s='CANCEL' mod='correosoficial'}">
            </div>
        </div>

        <div class="card card-custom card-margin">
            <div class="card-header">
                {l s=' SENDER LIST' mod='correosoficial'}
            </div>
            <div class="card-body card-body-custom">
                <table id="SendersDataTable" class="table table-striped" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{l s='Name' mod='correosoficial'}</th>
                            <th>{l s='Correos Account' mod='correosoficial'}</th>
                            <th>{l s='CEX Account' mod='correosoficial'}</th> 
                            <th>{l s='Address' mod='correosoficial'}</th>                            
                            <th>{l s='ZP' mod='correosoficial'}</th>
                            <th>{l s='NIF' mod='correosoficial'}</th>
                            <th>{l s='City' mod='correosoficial'}</th>
                            <th>{l s='Contact' mod='correosoficial'}</th>
                            <th>{l s='Phone' mod='correosoficial'}</th>
                            <th>{l s='From' mod='correosoficial'}</th>
                            <th>{l s='To' mod='correosoficial'}</th>
                            <th>{l s='Country' mod='correosoficial'}</th>
                            <th>{l s='Email' mod='correosoficial'}</th>
                            <th>{l s='Default sender' mod='correosoficial'}</th>
                            <th>{l s='Edit' mod='correosoficial'}</th> 
                            <th>{l s='Delete' mod='correosoficial'}</th>
                        </tr>
                    </thead>
                    
                </table>
            </div>
        </div>
        </form>        
    </fieldset>
</div>
<script>
    var senderDefaultSaved = "{l s='Sender successfully saved' mod='correosoficial'}";
    var wrongDniCif = "{l s='Incorrect DNI/CIF number, please correct it before continuing' mod='correosoficial'}";
    var invalidEmail = "{l s='Please enter a valid email address' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
    var selectAContract = "{l s='You must select at least one Correos Account or Cex Account' mod='correosoficial'}";
</script>
