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

<div class="correos-content-gdpr">
    <h4 class="correos-title-gdpr">
        {l s='To use our module you must accept our conditions.' mod='correosoficial'}
    </h4>
    <form id="comment_form" name="comment_form" class="needs-validation"></form>
    <form id="correos-form-gdpr" method="post" class="correos-form-gdpr">
        <input type="hidden" name="gdpr_nonce" id="gdpr_nonce" value="{$gdpr_nonce}">
        <div class="col-6 col-xs-6 px-4 correos-checks-gdpr">
            <div class="input-group correos-check-gdpr">
                <input type="checkbox" id="correos-gdpr-check" name="correos-gdpr-check" class="correos-input-gdpr" required>
                <label for="correos-gdpr-check" class="correos-text-gdpr">
                    {l s='I have read and accept the ' mod='correosoficial'} 
                    <a href="{$co_base_dir}views/gdpr/condiciones_servicio.pdf" target="_blank">
                        {l s='terms and conditions' mod='correosoficial'}
                    </a>
                </label>
            </div>
            <div class="input-group correos-check-gdpr">
                <input type="checkbox" id="correos-dataProtect-check" name="correos-dataProtect-check" class="correos-input-gdpr" required>
                <label for="correos-betatester-check" class="correos-text-gdpr">
                {l s='I have read and accept the ' mod='correosoficial'}
                    <a href="{$co_base_dir}views/gdpr/proteccion_datos.pdf" target="_blank">
                        {l s='data protection policy.' mod='correosoficial'}
                    </a>
                </label>
            </div>
        </div>
        <div class="col-6 col-xs-6 correos-checks-button-gdpr">
            <button type="submit" class="btn btn-lg correos-button-gdpr">
                {l s='I ACCEPT' mod='correosoficial'}
            </button>
        </div>
    </form>
</div>