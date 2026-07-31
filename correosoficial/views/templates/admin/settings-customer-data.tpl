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
<div id="customer-data" class="accordion-body">
    <div class="row justify-content-md-center">
        <div class="col-sm-12 table-users">
            <div class="card">
                <div class="card-header">{l s='ACTIVE CUSTOMERS' mod='correosoficial'}</div>
                <div class="card-body card-body-custom">
                    <table id="CustomerDataDataTable" class="table">
                        <thead>
                            <tr>
                                <th>{l s='id' mod='correosoficial'}</th>
                                <th>{l s='Status' mod='correosoficial'}</th>
                                <th>{l s='Customer Code' mod='correosoficial'}</th>
                                <th>{l s='Company' mod='correosoficial'}</th>
                                <th>{l s='Edit' mod='correosoficial'}</th>
                                <th>{l s='Delete' mod='correosoficial'}</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="add-new-contract-container">
                        <button id="add-new-contract" type="button" class="btn btn-secondary"><span class="add-new-contract-plus">+ </span>{l s='New contract' mod='correosoficial'}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="cocexUserLoggin" class="row justify-content-md-center hidden-block">
        <div id="Correos" class="col-sm-6 col-correos">
            <img src="{$co_base_dir}views/commons/img/logos/correos_ind_blue.png" class="correos-logo"
                alt="Logo Correos" />
            <div class="connected" id="connected-correos">
                <span id="CorreosConnected">{l s='Connected' mod='correosoficial'}</span>
            </div>
            <form id="CorreosCustomerDataForm" name="CorreosCustomerDataForm" method="POST">
                <fieldset>
                    <input id="CorreosCompany" name="CorreosCompany" value="Correos" type="hidden"
                        class="form-control" />
                    <input id="idCorreos" name="idCorreos" value="" type="hidden" class="form-control" />
                    <input name="operation" value="CorreosCustomerDataForm" type="hidden" class="form-control" />
                    <div class="row justify-content-md-center">
                        <div class="col-sm-10">
                            <div class="row justify-content-end pb-2">
                                <div class="col-auto">
                                    <a href="https://identidad.correos.es" target="_blank" class="btn btn-sm co_primary_button">
                                        {l s='Register in Correos ID' mod='correosoficial'}
                                    </a>
                                </div>
                            <div class="col-auto">
                                <a href="https://www.correos.es/content/dam/recursos-comunes/m%C3%B3dulos-ecommerce/Instrucciones_Credenciales_Ecommerce_Correos.pdf"
                                target="_blank" class="btn btn-sm co_primary_button">
                                    {l s='INSTRUCTIONS' mod='correosoficial'}
                                </a>
                            </div>
                        </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Client ID' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Código alfanumérico generado en Correos ID">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosClientID" name="CorreosClientID" value="" type="text"
                                    class="form-control" aria-label="Correos Client ID"
                                    aria-describedby="CorreosClientID" placeholder="00AA00AA-00AA-00AA-00AA-00AA00AA00AA00AA" />
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Secret ID' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Código alfanumérico generado en Correos ID">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosSecretID" name="CorreosSecretID" type="password"
                                        class="form-control" aria-label="Correos Secret ID"
                                        placeholder="****************" />
                                <div class="input-group-addon input-group-text-custom toggle-password" style="cursor:pointer;">
                                    <span class="input-group-text input-group-text-color">
                                        <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-width="2"
                                                d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12s-3.75 7.5-10.5 7.5S1.5 12 1.5 12z" />
                                            <circle cx="12" cy="12" r="3" stroke-width="2" />
                                        </svg>

                                        <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                                            <path stroke-width="2"
                                                d="M3 3l18 18M10.58 10.58A3 3 0 0113.41 13.4M6.11 6.11C3.88 7.94 2.25 10.22 1.5 12c.75 1.78 2.38 4.06 4.61 5.89C8.34 19.71 10.6 21 12 21c1.02 0 2.82-.44 4.93-1.82M17.89 17.89C20.12 16.06 21.75 13.78 22.5 12c-.75-1.78-2.38-4.06-4.61-5.89C15.66 4.29 13.4 3 12 3c-1.02 0-2.82.44-4.93 1.82" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Contract number' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Es un número de 8 dígitos">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosContract" name="CorreosContract" value="" type="text"
                                    class="form-control" aria-label="Número de Contrato"
                                    aria-describedby="CorreosContract" placeholder="8 números, ej.: 87654321" />
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Customer number' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Numero compuesto por un total de 10 dígitos, comenzando por 99">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosCustomer" name="CorreosCustomer" value="" type="text"
                                    class="form-control" aria-label="Número de Cliente"
                                    aria-describedby="CorreosCustomer" placeholder="10 dígitos, ej.: 9912345678"
                                    required />
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Correos Key' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Serie de 4 caracteres alfanuméricos">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosKey" name="CorreosKey" value="" type="text" class="form-control"
                                    aria-label="Código etiquetador" aria-describedby="CorreosKey"
                                    placeholder="4 dígitos, ej.: 1A4H" required />
                            </div>
                            <div class="input-group mb-4 hidden-block">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Systems Customer Account' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Recibido en correo electrónico de correos@correos.com, asunto: Alta Cuenta Sistema (PETGSVS…)">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosUser" name="CorreosUser" value="" type="text" class="form-control"
                                    aria-label="Cuenta de cliente de Sistemas" aria-describedby="CorreosUser"
                                    placeholder="13 dígitos, comienza por W, ej.: W876543211A4H"/>
                            </div>
                            <div class="input-group mb-4 hidden-block">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Account Password' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Recibido en correo electrónico de correos@correos.com, asunto: Alta Cuenta Sistema (PETGSVS…)">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosPassword" name="CorreosPassword" value="" type="password"
                                    class="form-control" aria-label="Contaseña Cuenta"
                                    aria-describedby="CorreosPassword" placeholder="8 dígitos, ej.: Qs7(Gn8%"
                                    />
                            </div>
                            <div class="input-group mb-4 hidden-block">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='My Office User' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Email con el que esté registrado en la Oficina Virtual de Correos">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CorreosOv2Code" name="CorreosOv2Code" value="" type="email"
                                    class="form-control" aria-label="Usuario Mi Oficina"
                                    aria-describedby="CorreosOv2Code"
                                    placeholder="email con el que se registró en Mi Oficina"/>
                            </div>

                            <div class="d-flex justify-content-md-end gap-2 mb-4">
                                <input class="btn btn-primary" name="CorreosCustomerDataSaveButton"
                                    id="CorreosCustomerDataSaveButton" type="submit"
                                    value="{l s='Add' mod='correosoficial'}" />
                                <button class="btn btn-danger" name="CorreosCustomerDataCancelButton"
                                    id="CorreosCustomerDataCancelButton" type="button">
                                    {l s='CANCEL' mod='correosoficial'}
                                </button>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>

        <div id="CEX" class="col-sm-6 col-cex">
            <img src="{$co_base_dir}views/commons/img/logos/cex_ind_yellow.png" class="correos-express-logo"
                alt="Logo Correos Express" />
            <div class="connected" id="connected-cex">
                <span id="CEXConnected">{l s='Connected' mod='correosoficial'}</span>
            </div>
            <form id="CEXCustomerDataForm" name="CEXCustomerDataForm" method="POST">
                <fieldset>
                    <input id="CEXCompany" name="CEXCompany" value="CEX" type="hidden" class="form-control" />
                    <input id="idCEX" name="idCEX" value="" type="hidden" class="form-control" />
                    <input name="operation" value="CEXCustomerDataForm" type="hidden" class="form-control" />
                    <div class="row justify-content-md-center">
                        <div class="col-sm-10">
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Customer Code' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Datos facilitado por Correos Express. Presente en su contrato">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CEXCustomer" name="CEXCustomer" value="" type="text" class="form-control"
                                    aria-label="Código cliente" aria-describedby="CEXCustomer" required />
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='User' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Dato facilitado por Correos Express">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CEXUser" name="CEXUser" value="" type="text" class="form-control"
                                    aria-label="Usuario" aria-describedby="CEXUser" required />
                            </div>
                            <div class="input-group mb-4">
                                <div class="input-group-addon input-group-text-custom">
                                    <span class="input-group-text input-group-text-color">
                                        {l s='Password' mod='correosoficial'}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#9b9fa3"
                                            class="bi bi-info-circle-fill tt_settings" viewBox="0 0 16 16"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Dato facilitado por Correos Express">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <input id="CEXPassword" name="CEXPassword" value="" type="password" class="form-control"
                                    aria-label="Password" aria-describedby="CEXPassword" required />
                            </div>

                            <div class="d-flex justify-content-md-end gap-2 mb-4">
                                <input class="btn btn-primary" name="CEXCustomerDataSaveButton"
                                    id="CEXCustomerDataSaveButton" type="submit"
                                    value="{l s='Add' mod='correosoficial'}" />
                                <button class="btn btn-danger" name="CEXCustomerDataCancelButton"
                                    id="CEXCustomerDataCancelButton" type="button">
                                    {l s='CANCEL' mod='correosoficial'}
                                </button>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
</div>
</div>

<script>
    var wantDeleteCustomer = "{l s='Do you want to delete this customer?' mod='correosoficial'}";
    var customerHaveSender = "{l s='Action not allowed. The account you want to delete has an associated sender' mod='correosoficial'}";
    var noCustomersActive = "{l s='There is no customers actives.' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
    var confirmationTitle = "{l s='Confirmation?' mod='correosoficial'}";

    var contractNumberMsj = "{l s='The contract number must have 8 numbers, for example 12345678' mod='correosoficial'}";
    var customerNumberMsj = "{l s='The Customer Number must have 8 numbers, e.g. 87654321' mod='correosoficial'}";
    var labelingCodeMsj = "{l s='The labelling code must be 4 characters, letters and/or numbers, e.g. 1A4H' mod='correosoficial'}";
    var systemsAccountMsj = "{l s='The Client Account Systems must have format w87654321 or W876543211A4H' mod='correosoficial'}";
    var systemsPasswordMsj = "{l s='Account Password must be 8 alphanumeric characters, e.g.: Qs7(Gn8%)' mod='correosoficial'}";
    var invalidEmailMsj = "{l s='The My Office User must be an email address' mod='correosoficial'}";

    var title200 = "{l s='Your credentials are correct.' mod='correosoficial'}";
    var description200 = "{l s='Please, save your username and password in a safe way.' mod='correosoficial'}";
    var title401 = "{l s='Failed to validate credentials.' mod='correosoficial'}";
    var description401 = "{l s='The username and password are not correct.' mod='correosoficial'}";
    var title404 = "{l s='Failed to validate credentials.' mod='correosoficial'}";
    var description404 = "{l s='Service Temporaly unavailable. Please, try again later.' mod='correosoficial'}";
    var title999 = "{l s='Failed to validate credentials.' mod='correosoficial'}";
    var description999 = "{l s='Service Temporaly unavailable. Please, try again later.' mod='correosoficial'}";
    var customer_technical_error =
        'Error al enviar el formulario de alta de cliente.\r\n\
    Revise su configuración. En caso de persistir el error\r\n\
    por favor, póngase en contacto con el Soporte Técnico de Correos';

    var statusConnected = "{l s='Connected' mod='correosoficial'}";
    var statusNotConnected = "{l s='No connected' mod='correosoficial'}";
    var soapFeatureInstallErrorMessage = "{l s='ERROR 12050: To use webservice credentials, you must have the SOAP feature installed. Please contact your hosting for more information' mod='correosoficial'}";
</script>
