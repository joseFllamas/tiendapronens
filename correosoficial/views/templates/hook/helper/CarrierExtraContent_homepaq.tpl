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
{if $aviso_aduanas_interiores eq 'on' && $require_customs_doc eq true}
    <div class="container extra-container">
        <div class="customs-advice-doc">
            <h3>{l s='Customs Notice' mod='correosoficial'}</h3>
            {$string_translated}
        </div>
    </div>
{/if}
