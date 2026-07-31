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
<link rel="stylesheet" type="text/css" href="{$co_base_dir}views/commons/css/detailOrder.css" />

<div class="card card-custom detail-order-container">
  <div class="card-header">
    <h3 class="card-header-title">
      <img src="{$co_base_dir}views/commons/img/logo.jpg" alt="Correos" width="100" />
      {l s='Correos Oficial' mod='correosoficial'}
    </h3>
  </div>

  <div class="card-body">
    <div class="card" id="card-rte">

      <div class="card-header card-header-blue">
        <h3 class="card-header-title">{l s='Correos Oficial Tracking Order' mod='correosoficial'}</h3>
      </div>

      <div class="do-click">
        <p>{l s='Do click in the link below to tracking your order' mod='correosoficial'}</p>

        <p>
          <a target="__blank"
            href="https://www.correos.es/es/es/herramientas/localizador/envios/detalle?tracking-number={$shipping_number}">{l s='Tracking your order' mod='correosoficial'}</a>
        </p>
      </div>
    </div>
  </div>
</div>