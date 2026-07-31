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
//Para debug completo del var_dump
ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);


add_action('admin_enqueue_scripts', 'co_bootstrap_css');
add_action('admin_enqueue_scripts', 'co_load_general_styles');
add_action('admin_enqueue_scripts', 'co_scripts_js');

function co_load_general_styles() {
	// Clonado de reglas de métodos de envío en Woocommerce.
	if (isset($_GET['page']) && $_GET['page'] == 'wc-settings') {
		wp_enqueue_style('shipping_method', plugins_url('override/css/shipping_methods.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
	}
}

// Incluir Bootstrap CSS
function co_bootstrap_css() {
	if (isset($_GET['page'])) {

		$page = sanitize_text_field($_GET['page']);

		if ($page == 'home' || $page == 'settings' || $page == 'utilities' || $page == 'correosoficial' || $page == 'notifications') {
			wp_enqueue_style('co_bootstrap_min', plugins_url('views/commons/css/bootstrap.min.css', __FILE__), array(), '5.0.2', 'all');
			wp_enqueue_style('co_back', plugins_url('views/commons/css/back.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			wp_enqueue_style('co_override_back', plugins_url('/override/css/back.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			wp_enqueue_style('co_tab', plugins_url('views/commons/css/tab.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			//wp_enqueue_style('co_override_tab', plugins_url('/override/css/tab.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		}
	}
}

function co_scripts_js() {

	// Clonado de reglas de métodos de envío en Woocommerce.
	if (isset($_GET['page']) && $_GET['page'] == 'wc-settings') {
		// Localize the script with new data
		$translation_array = array(
			'mustSaveBefore' => __('Before adding shipping methods, please add regions to the zone and save the changes', 'correosoficial'),
		);
		wp_enqueue_script('co_clone-shipping-rule', plugins_url('/js/clone-shipping-rule.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, false);
		wp_enqueue_script('co_validate_shipping_methods', plugins_url('/js/validate-shipping-methods.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, false);

		wp_localize_script('co_validate_shipping_methods', 'validateShippingMethod', $translation_array);
	}
}