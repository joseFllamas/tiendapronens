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
<div id="correos_oficial">

    <div id="co_home" class="container">
        <div class="row">
            <div class="col-md-12 mb-2 form-col-center">
                <h2 id="hello">
                    {l s='Hello, are you a Correos customer?' mod='correosoficial'}
                    <a href="{$dispatcher}" class="yellowButton">{l s='YES, CONFIGURE MY MODULE' mod='correosoficial'}</a>
                </h2>
            </div>
        </div>
        <div class="row left-right-container">
            {* Columna izquierda *}
            <div class="col-md-6 form-col-left">
                <h2>
                    {l s='The best transport solutions for your customers online' mod='correosoficial'}                    
                </h2>
                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/paq24.png" alt="paq24" width="50" />

                            <span>{l s='DELIVERY IN CORREOS OFFICE' mod='correosoficial'}</span>
                        </h3>
                        <p>
                            {l s='You have more than 2,300 collection points for your shipments with our network of offices' mod='correosoficial'}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/Entrega_Flexible.png"
                                alt="Entrega_Flexible" width="50" />
                            <span>{l s='SAVE THEM WAITING WITH FLEXIBLE DELIVERY' mod='correosoficial'}</span>
                        </h3>
                        <p>{l s='Select the Correos or Correos Express products that best suit you' mod='correosoficial'}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/localizacion.png" alt="localizacion"
                                width="50" />
                            <span>{l s='MINIMIZES INCIDENTS' mod='correosoficial'}</span>
                        </h3>
                        <p>
                            {l s='Increase your sales and count on the confidence of the Correos Group for your shipments' mod='correosoficial'}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/check.png" alt="check" width="50" />
                            <span>{l s='ELIMINATES UNCERTAINTY' mod='correosoficial'}</span>
                        </h3>
                        <p>
                            {l s='Know the status of your shipments at all times and offer transparency to your customer' mod='correosoficial'}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/sms.png" alt="sms" width="50" />
                            <span>{l s='GIVE THEM PEACE OF MIND' mod='correosoficial'}</span>
                        </h3>
                        <p>{l s='Your customer will also have the possibility to track his order live' mod='correosoficial'}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <h3>
                            <img src="{$co_base_dir}views/commons/img/calendario.png" alt="calendario"
                                width="50" />
                            <span>{l s='PROMPT DELIVERY' mod='correosoficial'}</span>
                        </h3>
                        <p>{l s='Choose the deadline that best suits your type of shipments' mod='correosoficial'}.</p>
                    </div>
                </div>
            </div>
            {* Formulario de nuevo cliente *}
{*             <div class="col-md-6 form-col-right">
                <div id="form_register">
                    <h2>{l s='I AM NEW, CALL ME' mod='correosoficial'}</h2>
                    <hr />
                    <h4>
                        {l s='Leave us your details, and we contact you soon' mod='correosoficial'}
                    </h4>
                    <div class="form-inputs">
                        <form class="form-group" id="LeadForm" name="LeadForm">
                            <fieldset>
                                <div>
                                    <label for="input_company"></label>
                                    <input type="text" class="form-control" id="input_company" name="input_company"
                                        placeholder="{l s='Company name' mod='correosoficial'} *" required />
                                </div>
                                <div>
                                    <label for="input_cif"></label>
                                    <input type="text" class="form-control" id="input_cif" name="input_cif"
                                        placeholder="{l s='ID number' mod='correosoficial'} *" required />
                                </div>
                                <div>
                                    <label for="ContactName"></label>
                                    <input type="text" class="form-control" id="input_contact_name"
                                        name="input_contact_name"
                                        placeholder="{l s='Contact name' mod='correosoficial'} *" required />
                                </div>
                                <div>
                                    <label for="input_mobile_phone"></label>
                                    <input type="tel" class="form-control" id="input_mobile_phone"
                                        name="input_mobile_phone"
                                        placeholder="{l s='Mobile Phone' mod='correosoficial'} *" required />
                                </div>
                                <div>
                                    <label for="input_phone"></label>
                                    <input type="tel" class="form-control" id="input_phone" name="input_phone"
                                        placeholder="{l s='Phone' mod='correosoficial'}" required />
                                </div>
                                <div>
                                    <label for="input_email"></label>
                                    <input type="email" class="form-control" id="input_email" name="input_email"
                                        placeholder="{l s='Email' mod='correosoficial'} *" required />
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <label for="product_category"></label>
                                        <select id="product_category" name="product_category" class="form-control"
                                            required>
                                            <option value="">
                                                {l s='Select products category' mod='correosoficial'} *
                                            </option>
                                            <option
                                                value="{l s='More than 20 shippings/month' mod='correosoficial'}">
                                                {l s='More than 20 shippings/month' mod='correosoficial'}
                                            </option>
                                            <option
                                                value="{l s='Less than 20 shippings/month' mod='correosoficial'}">
                                                {l s='Less than 20 shippings/month' mod='correosoficial'}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <p id="emailHelp" class="form-text co_small">
                                        {l s='The fields with * are required.' mod='correosoficial'}
                                    </p>
                                </div>
                                <div>
                                    <p id="check_policy_container">
                                        <input type="checkbox" class="form-check-input" id="check_policy" name="check_policy" required />
                                        {l s='I have read the ' mod='correosoficial'}
                                        <a id="privacy_policy" href="#">{l s=' privacy policy' mod='correosoficial'}</a>
                                    </p>
                                </div>
                                <br />
                                <div>
                                    <input type="submit" id="CustomerSendRequestSubmitButton"
                                        name="CustomerSendRequestSubmitButton" class="btn btn-primary"
                                        value="{l s='Send' mod='correosoficial'}" />
                                </div>
                                <div id="form_incompleted">
                                    <h4>
                                        <strong>{l s='Please, check errors before send the form.' mod='correosoficial'}</strong>
                                    </h4>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </div>
                <div id="form_registered">
                    <h1>{l s='Thank you. We contact you soon' mod='correosoficial'}</h1>
                </div>
            </div> *}
            <div class="clearfix"></div>
        </div>
    </div>
</div>
<script>
    var wrongDniCif = "{l s='Incorrect DNI/CIF number, please correct it before continuing' mod='correosoficial'}";
    var requiredCustomMessage = "{l s='Required field' mod='correosoficial'}";
    var minLengthMessage = "{l s='Please enter at least' mod='correosoficial'}";
    var maxLengthMessage = "{l s='Please enter no more than' mod='correosoficial'}";
    var characters = "{l s='characters' mod='correosoficial'}";
    var invalidEmail = "{l s='Please enter a valid email address' mod='correosoficial'}";
    var homeTechnicalError="{l s='Error submitting the form. Please try again later. If the error persists, please contact Correos Technical Support.' mod='correosoficial'}";
</script>