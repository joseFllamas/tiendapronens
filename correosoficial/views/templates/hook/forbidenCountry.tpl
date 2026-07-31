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
<div class="correos_oficial">
    {if $params.carrier_type eq 'office'}
        {include file="{$co_base_dir}views/templates/hook/helper/CarrierExtraContent_office.tpl"
        params=$params}
    {/if}

    {if $params.carrier_type eq 'citypaq'}
        {include file="{$co_base_dir}views/templates/hook/helper/CarrierExtraContent_citypaq.tpl"
        params=$params}
    {/if}

    {if $params.carrier_type eq 'international'}
        {include file="{$co_base_dir}views/templates/hook/helper/CarrierExtraContent_international.tpl"
        params=$params}
    {/if}

    {if $params.carrier_type eq 'homedelivery'}
        {include file="{$co_base_dir}views/templates/hook/helper/CarrierExtraContent_homepaq.tpl"
        params=$params}
    {/if}

    {if $params.carrier_type eq 'pudocex'}
        {include file="{$co_base_dir}views/templates/hook/helper/CarrierExtraContent_pudo.tpl"
        params=$params}
    {/if}
</div>