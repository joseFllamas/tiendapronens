<?php
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

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}



global $wpdb;

// Bootstrap the PSR-4 autoloader so namespaced classes are available
// even when this file is executed after the plugin has been deactivated.
require_once __DIR__ . '/vendor/autoload.php';

use CorreosOficial\Classes\Analitica;

// Enviamos unistall a control de versiones.
( new Analitica() )->uninstallCall();

// Eliminamos tablas
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_install");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_configuration");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_senders");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_codes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_codes_actives");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_orders");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_saved_orders");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_returns");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_saved_returns");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_pickups_returns");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_products");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_carriers_products");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_customs_description");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_requests");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_shipping_method_rules");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_postcodes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_ws_status");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_sga_orders_log");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}correos_oficial_sga_orders_status");

// eliminar flag primera activacion sga.
delete_option('correosoficial_sga_status_created');

// eliminar lock del Upgrade Manager y versión almacenada.
delete_option('correosoficial_install_lock_version');
delete_option('correosoficial_version');

/**
 * Se eliminan las opciones de la base de datos de wp_options
 */
$wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'CORREOS_OFICIAL%'");

/**
 * Se elimina fichero de bloqueo antiguo
 */
foreach (glob(sys_get_temp_dir() . '/' . $wpdb->dbname . '/correosoficial*.lock') as $old) {
	unlink($old);
}
