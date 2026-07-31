/**
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
 */
// WooCommerce no necesita el token.  Asignamos el token a vacío.
const token = '';
const platform = 'wc';
// Conseguimos la url base
const getUrl = window.location;

let co_url_base_ = getUrl.protocol + '//' + getUrl.host + '/';
let path = '';

const pathname = window.location.pathname.split('/')[1];

const co_url_base = co_url_base_ + path;
const co_path_to_module = woocommerceVars.pluginsUrl + '/correosoficial';
const co_url_base_wpadmin = woocommerceVars.adminUrl;

// Despachador
const dispatcher_url = woocommerceVars.pluginsUrl + '/correosoficial/dispatcher.php';
const url_prefix_back = dispatcher_url;

// General
const AdminCorreosOficialActiveCustomers = dispatcher_url + '?controller=AdminCorreosOficialActiveCustomers';

// Inicio
const AdminHomeSendMail = dispatcher_url + '?controller=AdminHomeSendMail';

// Datos de cliente
const AdminCorreosOficialCustomerDataProcess = dispatcher_url + '?controller=AdminCorreosOficialCustomerDataProcess';

// DataTable de cliente
const getDataTableCustomerList = dispatcher_url + '?controller=AdminCorreosOficialCustomerDataProcess&action=getDataTableCustomerList';

const AdminCorreosOficialExecuteCron = dispatcher_url + '?controller=AdminCorreosOficialCronProcessController&operation=EXECUTECRON';

// Servicios de Correos
const AdminCorreosSoapRequestURL = dispatcher_url + '?controller=AdminCorreosSOAPRequest';

// Servicios de CEX
const AdminCEXRestRequestURL = dispatcher_url + '?controller=AdminCEXRestRequest';

// Ajustes
const AdminCorreosOficialSettingsGetDataTable = dispatcher_url + '?controller=AdminCorreosOficialSettings&action=getDataTable';

// Ajustes->Remitentes
const AdminCorreosOficialSendersProcess = dispatcher_url + '?controller=AdminCorreosOficialSendersProcess';

// Ajustes->Configuracion de usuario
const AdminCorreosOficialUserConfigurationProcess = dispatcher_url + '?controller=AdminCorreosOficialUserConfigurationProcess';

// Ajustes->Productos
const AdminCorreosOficialProductsProcess = dispatcher_url + '?controller=AdminCorreosOficialProductsProcess';

// Ajustes->Zonas y transportistas
const AdminCorreosOficialZonesCarriersProcess = dispatcher_url + '?controller=AdminCorreosOficialZonesCarriersProcess';

// Ajustes->Tramitación Aduanera
const AdminCorreosOficialCustomsProcessingProcess = dispatcher_url + '?controller=AdminCorreosOficialCustomsProcessingProcess';

// Checkout
const FrontCheckoutAdminURL = dispatcher_url + '?controller=CorreosOficialCheckoutModuleFrontController&';
const FrontCheckoutURL = FrontCheckoutAdminURL;

// AdminOrder
const AdminOrderURL = dispatcher_url + '?controller=CorreosOficialAdminOrderModuleFrontController&';
