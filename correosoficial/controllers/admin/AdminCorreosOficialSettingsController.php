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

namespace CorreosOficial\Controllers\Admin;

if (!defined('WPINC')) {
	die;
}



use CorreosOficial\Classes\CorreosOficialZonesWC;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialCarrier;
use CorreosOficial\Classes\CorreosOficialMarketplace;
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialCustomDescription;
use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Classes\CorreosOficialPrefilter;

class AdminCorreosOficialSettingsController {

	private $smarty;

	public function __construct( $smarty ) {
		$this->smarty = $smarty;

		include_once WP_PLUGIN_DIR . '/correosoficial/langs/settingsLang.php';

		if (isset($_POST['_nonce'])) {
			$nonce = sanitize_text_field($_POST['_nonce']);
			if (!wp_verify_nonce(wp_unslash($nonce), 'correosoficial_nonce')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_send_json_error( 'bad_nonce' );
				wp_die();
			}
		}

		if (isset($_POST['dispatcher']['action']) && $_POST['dispatcher']['action'] == 'getDataTable') {
			$this->getDataTableSenders();
		}

		$this->smarty->assign('co_base_dir', site_url());
		$this->smarty->assign('Processing', 'Procesando');
		$this->renderView();
	}

	private function renderView() {

		wp_enqueue_script('customer_data', plugins_url('correosoficial/js/customer-data.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('senders', plugins_url('correosoficial/js/senders.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('user_configuration', plugins_url('correosoficial/js/user-configuration.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('zones_carrier', plugins_url('correosoficial/js/zones-carriers.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('products', plugins_url('correosoficial/js/products.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('customs_processing', plugins_url('correosoficial/views/js/commons/customs-processing.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('sga_configuration', plugins_url('correosoficial/js/sga-configuration.js'), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_script('marketplace_configuration', plugins_url('correosoficial/js/marketplace-configuration.js'), array(), CORREOS_OFICIAL_VERSION, 'all');

		wp_enqueue_script(
			'co_ajax', plugins_url('correosoficial/views/js/commons/ajax.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		wp_enqueue_script(
			'co_ajax_wc', plugins_url('correosoficial/js/ajax_wc_settings.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		wp_enqueue_script(
			'co_common_settings',
			plugins_url('correosoficial/views/js/commons/common-settings.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		// Pasamos variables js al frontal
		wp_localize_script(
			'senders', 'varsAjax', array(
			'nonce' => wp_create_nonce('correosoficial_nonce'),
			'ajaxUrl' => admin_url('admin-ajax.php'),
			)
		);

		global $co_module_url;

		$this->smarty->assign('UploadLogoLabels');
		// Rellenamos checkbox y selectores de forma global en Ajustes.
		$this->fillSettingsCheckBoxAndSelectores();

		// Rellenar selectores de contrato en formulario remitente
		$this->fillSenderFormContractSelector();

		$this->getProducts();
		$this->getZonesAndCarriers();
		
		$DefaultLabel = (new CorreosOficialConfig('DefaultLabel') )->get_value();
		$payment_method_selected = (new CorreosOficialConfig('CashOnDeliveryMethod') )->get_value();
		$customs_desc_array =  (new CorreosOficialCustomDescription() )->get_all_customs_desc();
		$customs_tariff_array = (new CorreosOficialConfig('Tariff') )->get_value();
		$customs_desc_selected = (new CorreosOficialConfig('DefaultCustomsDescription') )->get_value();
		$ShippCustomsReference = (new CorreosOficialConfig('ShippCustomsReference') )->get_value();
        $CountryOriginByDefault = (new CorreosOficialConfig('CountryOriginByDefault') )->get_value();

		$select_label_options = array( '0' => __('Adhesive', 'correosoficial'), /* '1' => __('Half sheet', 'correosoficial'),  */'2' => __('Thermic', 'correosoficial') );
		$CorreosModify = (new CorreosOficialConfig('CorreosModify') )->get_value();
		$select_correosmodify_options = array( '1' => __('Sí', 'correosoficial'), '0' => __('No', 'correosoficial') );
		$select_payment_method = array();
		
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		$select_payment_method = array( '0' => __('None', 'correosoficial') );

		foreach ($gateways as $gateway) {
			$select_payment_method[$gateway->id] = $gateway->title;
		}

		// Obtenemos status de los pedidos
		$select_shipment_status_options = array();
		
		// Obtener los estados de pedido de WooCommerce
		$wc_order_statuses = wc_get_order_statuses();

		// Convertir el array asociativo en el formato que deseas
		$records = array();
		foreach ($wc_order_statuses as $key => $value) {
			$records[] = array( 'id_order_state' => $key, 'name' => $value );
		}

		$ShipmentPreregistered = CorreosOficialConfig::get_config_status('ShipmentPreregistered');
		$ShipmentDelivered = CorreosOficialConfig::get_config_status('ShipmentDelivered');
		$ShipmentInProgress = CorreosOficialConfig::get_config_status('ShipmentInProgress');
		$ShipmentCanceled = CorreosOficialConfig::get_config_status('ShipmentCanceled');
		$ShipmentReturned = CorreosOficialConfig::get_config_status('ShipmentReturned');

		$i = 0;
		foreach ($records as $record) {
			$select_shipment_status_options[$i]['id_order_state'] = $record['id_order_state'];
			$select_shipment_status_options[$i]['name'] = $record['name'];
			$i++;
		}

		// Mapeo Amazon Channable
		if (is_null(CorreosOficialConfig::get_config_status('AutomaticProductAssignmentText'))) {
			CorreosOficialConfig::save( 'AutomaticProductAssignmentText', '' );
		}
		if (is_null(CorreosOficialConfig::get_config_status('AutomaticProductAssignmentProduct'))) {
			CorreosOficialConfig::save( 'AutomaticProductAssignmentProduct', '' );
		}
		$AutomaticProductAssignmentText         = CorreosOficialConfig::get_config_status('AutomaticProductAssignmentText');
		$AutomaticProductAssignmentProduct      = CorreosOficialConfig::get_config_status('AutomaticProductAssignmentProduct');

		global $wpdb;
		$activeProducts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}correos_oficial_products WHERE active=1", OBJECT);

		$sga_module = ( new CorreosOficialConfig('ActivateSGA') )->get_value();

		$this->smarty->assign('sga_module', $sga_module);
		$this->smarty->assign('AutomaticProductAssignmentText', $AutomaticProductAssignmentText);
		$this->smarty->assign('AutomaticProductAssignmentProduct', $AutomaticProductAssignmentProduct);    
		$this->smarty->assign('active_products', $activeProducts);

		$this->smarty->assign('ShipmentPreregistered', $ShipmentPreregistered);
		$this->smarty->assign('ShipmentDelivered', $ShipmentDelivered);
		$this->smarty->assign('ShipmentInProgress', $ShipmentInProgress);
		$this->smarty->assign('ShipmentCanceled', $ShipmentCanceled);
		$this->smarty->assign('ShipmentReturned', $ShipmentReturned);

		$this->smarty->assign('select_shipment_status_options', $select_shipment_status_options);
		$this->smarty->assign('select_label_options', $select_label_options);
		$this->smarty->assign('DefaultLabel', $DefaultLabel);
		$this->smarty->assign('select_correosmodify_options', $select_correosmodify_options);
		$this->smarty->assign('CorreosModify', $CorreosModify);
		$this->smarty->assign('select_payment_method', $select_payment_method);
		$this->smarty->assign('payment_method_selected', $payment_method_selected);
		$this->smarty->assign('customs_desc_array', $customs_desc_array);
		$this->smarty->assign('customs_tariff_array', $customs_tariff_array);
		$this->smarty->assign('customs_desc_selected', $customs_desc_selected);
		$this->smarty->assign('ShippCustomsReference', $ShippCustomsReference);
		$this->smarty->assign("CountryOriginByDefault", $CountryOriginByDefault);

		$TariffRadio = (new CorreosOficialConfig('TariffRadio') )->get_value();
		$ProductRadio = (new CorreosOficialConfig('ProductRadio') )->get_value();
		
		$config_default_aduanera = 0; // Por defecto tab descripción
		if ($TariffRadio == 'on') {
			$config_default_aduanera = 1;
		} else if ($ProductRadio == 'on') {
			$config_default_aduanera = 2;
		}
		$this->smarty->assign('config_default_aduanera', $config_default_aduanera);
		
		// Obtener atributos de productos de WooCommerce para el tab de producto
		$product_attributes_array = $this->getProductAttributes();
		$this->smarty->assign('product_attributes_array', $product_attributes_array);
		
		// Obtener valores de mapeo de atributos para producto
		$MappedHsFeature = (new CorreosOficialConfig('MappedHsFeature'))->get_value();
		$MappedOriginFeature = (new CorreosOficialConfig('MappedOriginFeature'))->get_value();
		$UseModuleFeatures = (new CorreosOficialConfig('UseModuleFeatures'))->get_value();
		
		$this->smarty->assign('MappedHsFeature', $MappedHsFeature);
		$this->smarty->assign('MappedOriginFeature', $MappedOriginFeature);
		$this->smarty->assign('UseModuleFeatures', $UseModuleFeatures == 'on' ? 'checked' : '');

		// Marketplace
		$activate_marketplace = ( new CorreosOficialConfig('ActivateMarketplace') )->get_value();
		$this->smarty->assign('ActivateMarketplace', $activate_marketplace);
		$this->smarty->assign('MarketplaceConsumerKey', CorreosOficialMarketplace::getConsumerKey());
		$this->smarty->assign('MarketplaceConsumerSecret', CorreosOficialMarketplace::getConsumerSecret());
		$this->smarty->assign('MarketplaceApiBaseUrl', CorreosOficialMarketplace::getApiBaseUrl());
		$this->smarty->assign('MarketplaceWooCommerceActive', CorreosOficialMarketplace::isWooCommerceActive());
		$marketplaceResources = [];
		foreach (CorreosOficialMarketplace::MARKETPLACE_RESOURCES as $resource => $methods) {
			$marketplaceResources[] = [
				'name'     => $resource,
				'endpoint' => rtrim(CorreosOficialMarketplace::getApiBaseUrl(), '/') . '/' . $resource,
				'methods'  => implode(', ', $methods),
			];
		}
		$this->smarty->assign('MarketplaceResources', $marketplaceResources);

		// SGA 

		$this->smarty->assign('SGAAOwner', (new CorreosOficialConfig('SGAAOwner') )->get_value());
		$this->smarty->assign('SGACustomer', (new CorreosOficialConfig('SGACustomer') )->get_value());
		$this->smarty->assign('SGAStore', (new CorreosOficialConfig('SGAStore') )->get_value());
		$this->smarty->assign('SGAProcessStatus', (new CorreosOficialConfig('SGAProcessStatus') )->get_value());

		// Asignación de estados SGA con fallback a los definidos en CORREOS_OFICIAL_SGA_ORDER_STATES
		$sgastates_map = array_column(CORREOS_OFICIAL_SGA_ORDER_STATES, 'value', 'alias');

		$selected_status_PE = ( new CorreosOficialConfig('SGAOrderStatusTrackingPE') )->get_value();
		$selected_status_EX = ( new CorreosOficialConfig('SGAOrderStatusTrackingEX') )->get_value();
		$selected_status_Error = ( new CorreosOficialConfig('SGAOrderStatusTrackingError') )->get_value();

		// Fallbacks: PE -> PREPARE, EX -> OK, Error -> KO
		if ( empty( $selected_status_PE ) ) {
			$selected_status_PE = $sgastates_map['SGA_STATUS_OK'] ?? '';
		}
		if ( empty( $selected_status_EX ) ) {
			$selected_status_EX = 'wc-completed';
		}
		if ( empty( $selected_status_Error ) ) {
			$selected_status_Error = $sgastates_map['SGA_STATUS_KO'] ?? '';
		}

		$this->smarty->assign('selected_status_PE', $selected_status_PE );
		$this->smarty->assign('selected_status_EX', $selected_status_EX );
		$this->smarty->assign('selected_status_Error', $selected_status_Error );
        $this->smarty->assign('order_status_tracking_cron_interval_value', (new CorreosOficialConfig('SGAOrderStatusTrackingCronInterval'))->get_value() );

		foreach (CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_status) {
			$wc_order_statuses[ $sga_status['value'] ] = $sga_status['name'];
		}

		$this->smarty->assign('sga_statuses', $wc_order_statuses);

		// Ruta para recuperar el logo de las etiquetas
		$this->smarty->assign('co_base_dir', $co_module_url);

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);

		$analitica = new Analitica();

		// Comprobamos si han pasado las 12 h para actualizar
		$lastComprove = $analitica->lastHour();
		$now = gmdate('Y-m-d H:i:s');

		if (!empty($lastComprove) && strtotime($now) > strtotime($lastComprove . '+ 12 hours')) {
			$analitica->moduleRecord(); 
			$analitica->externalModulesRecord();
			$analitica->configurationCall('undefined');
			$analitica->updateTime();
		}

		$vars = array();

		if (isset($_POST['gdpr_nonce'])) {
			$gdprNonce = sanitize_text_field( $_POST['gdpr_nonce'] );
			if (wp_verify_nonce($gdprNonce, 'gdpr_nonce')) {
				$vars = $_POST;
			}
		}
		
		$gdpr = $analitica->gdpr($vars);

		//plantilla
		$template = 'settings.tpl';
		if ($gdpr) {
			$template = 'correosGdpr.tpl';
			$this->smarty->assign('gdpr_nonce', wp_create_nonce( 'gdpr_nonce' ));
		}
		$this->smarty->fetch(__DIR__ . '/../../views/templates/admin/' . $template);
		$this->smarty->display($template);
	}

	/**
	 * Rellenamos checkbox y selectores de forma global en Ajustes.
	 *
	 * @param Oject $dao. El dao.
	 */
	public function fillSettingsCheckBoxAndSelectores() {
		$records = CorreosOficialConfig::get_all();

		$language_id = null; // Valor por defecto: si no hay config guardada para FormSwitchLanguage

		/**
		 * Autorreleno de Selectores y checkbox
		 */
		foreach ($records as $record) {

			$this->smarty->assign($record->name, $record->value);

			if ($record->name == 'BankAccNumberAndIBAN') {
				if (!empty($record->value)) {
					$BankAccNumberAndIBAN = CorreosOficialCrypto::decrypt($record->value);
					if ($BankAccNumberAndIBAN === false) {
						$BankAccNumberAndIBAN = '';
					}

					//Se sustituyen los primeros caracteres por asteriscos y se dejan sólo los últimos cuatro números
					$BankIni = substr($BankAccNumberAndIBAN, 0, -4);
					$BankFin = substr($BankAccNumberAndIBAN, -4);
					$BankAccNumberAndIBAN = str_repeat('*', strlen($BankIni)) . $BankFin;

					$this->smarty->assign($record->name, $BankAccNumberAndIBAN);
				} else {
					$this->smarty->assign($record->name, $record->value);
				}
			}

			// Si no ha seleccionado ningún idioma del selector toma el idioma del contexto
			if ($record->name == 'FormSwitchLanguage') {
				if (!empty($record->value)) {
					$language_id = $record->value;
				} else {
					$languages[] = CorreosOficialUtils::getActiveLanguages();
					$language_id = $languages[0][0]['id_lang'];
				}
			}

			if ($record->name == 'TranslatableInput') {
				$this->smarty->assign('TranslatableInputH', CorreosOficialUtils::restoreBadCharacters($record->value));
				$string_translated = CorreosOficialUtils::translateStringsFromDB($record->value, $language_id);
				$this->smarty->assign($record->name, $string_translated);
			}

			if ($record->type == 'checkbox' && ( $record->value == 'true' || $record->value == 'on' )) {
				$this->smarty->assign($record->name, 'checked');
			}

			$getUserLogo = CorreosOficialConfig::get_config_status('UploadLogoLabels');

			if ($record->name == 'UploadLogoLabels' && ( $getUserLogo == '' || $getUserLogo == 'default.jpg' )) {
				if ($record->value == '') {
					$this->smarty->assign('baseLabel', 'default.jpg');
				} else {
					$result = $record->value;
					if (strstr($result, 'ERROR:  12010')) {
						$result = __('ERROR 12010: Allowed formats: png, jpg, jpeg', 'correosoficial');
						$this->smarty->assign('ErrorLogoLabels', $result);
					} else {
						$this->smarty->assign('UploadLogoLabelsName', CorreosOficialNormalization::filterFiles($result));
						$this->smarty->assign('UploadLogoLabels', $result);
					}

				}
			}

			if ($record->name == 'CronInterval') {
				$this->smarty->assign('CronInterval', $record->value);
			}

			$this->smarty->assign('showNIF', 'true');

		}

		$active_languages = CorreosOficialUtils::getActiveLanguages();
		CorreosOficialUtils::fillLanguagesSelector($active_languages, $this->smarty, $language_id);
	}

	/**
	 * Para conseguir el datatable de Senders
	 */
	public function getDataTableSenders() {

		global $wpdb;

		// Este código se tiene que mover a readSenders en el senders dao

		$records = $wpdb->get_results($wpdb->prepare("SELECT a.*, b.CorreosContract, b.CorreosCustomer, b.CorreosClientID, c.CEXCustomer
		FROM {$wpdb->prefix}correos_oficial_senders a
		LEFT JOIN {$wpdb->prefix}correos_oficial_codes b ON a.correos_code = b.id
		LEFT JOIN {$wpdb->prefix}correos_oficial_codes c ON a.cex_code = c.id"));
		
		die(json_encode($records));
	}

	/**
	 * Rellenar selectores de contrato en formulario remitente
	 */
	public function fillSenderFormContractSelector() {
		global $wpdb;

		$optionsCountsCorreos = $wpdb->get_results(
			"SELECT `id`, `CorreosContract`, `CorreosCustomer`, `CorreosClientID` FROM {$wpdb->prefix}correos_oficial_codes WHERE company='Correos'",
			ARRAY_A
		);

		$optionsCountsCex = $wpdb->get_results(
			"SELECT `id`, `CEXCustomer` FROM {$wpdb->prefix}correos_oficial_codes WHERE company='CEX'",
			ARRAY_A
		);

		$this->smarty->assign('optionsCorreos', $optionsCountsCorreos);
		$this->smarty->assign('optionsCex', $optionsCountsCex);
	}

	public function getProducts() {
		global $wpdb;

		// Se precargan los productos pero se controlan a nivel de ajax en el frontal
		$products_column1 = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}correos_oficial_products WHERE company='CEX'", OBJECT);
		$products_column2 = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}correos_oficial_products WHERE company='CORREOS'", OBJECT);

		$products_column2 = array_filter($products_column2, function($product) {
			return $product->id != 26;
		});

		$cex = true;
		$correos = true;

		$this->smarty->assign('exist_products', true);
		$this->smarty->assign('cex', $cex);
		$this->smarty->assign('correos', $correos);

		$this->smarty->assign('products_column1', $products_column1);
		$this->smarty->assign('products_column2', $products_column2);
	}

	/**
	 * Obtiene el nombre el método de envío traducido, si no lo encuentra
	 * transforma el $method_rate_id para que pueda ser traducible.
	 * La tabla consultada es wp_options de WP.
	 *
	 * @param  string $method_id
	 * @param  int    $instance_id
	 * @return string titulo del nombre de envio
	 */
	public function getShippingNameById( $method_rate_id, $instance_id ) {
		if (!empty($method_rate_id)) {
			$method_key_id = str_replace(':', '_', $method_rate_id);
			$option_name = 'woocommerce_' . $method_key_id . '_' . $instance_id . '_settings';

			// Si existe la opción
			if (get_option($option_name, false)) {
				$title = isset(get_option($option_name)['title']) ? get_option($option_name)['title'] : '';
			}
			if (!empty($title)) {
				return $title;
			} else { // Transforma ej. flat_rate a Flat Rate para poder ser traducido
				$carrier_name = str_replace('_', ' ', $method_rate_id);
				$carrier_name = ucfirst($carrier_name);
				return $carrier_name;
			}
		}
	}

	// Obtenemos la relación de zonas y carriers y cada producto seleccionado para cada carrier
	public function getZonesAndCarriers() {
		global $wpdb;
		$zonesandcarriers = array();

		$this->smarty->assign('zonesandcarriers', $zonesandcarriers);

		$zones_and_carriers = new CorreosOficialZonesWC();

		$wc_zones = $zones_and_carriers->getZones('woocommerce_shipping_zones', true);

		if (empty($wc_zones)) {
			return;
		}

		foreach ($wc_zones as $wc_zone) {
			$zones[] = array(
				'id_zone' => $wc_zone['zone_id'],
				'name' => $wc_zone['zone_name'],
				'active' => 1,
			);
		}

		foreach ($zones as $zone) {
			$carriers = array();

			$wc_carriers = $zones_and_carriers->getCarriersByZone($zone['id_zone'], 'woocommerce_shipping_zone_methods');

			foreach ($wc_carriers as $wc_carrier) {

				/* Mediante el method_id y el instance_id obtenemos el nombre del método de envío y
							lo transformamos a una palabra traducible por el gestor de idiomas de WP. */
				$carrier_name = self::getShippingNameById($wc_carrier['method_id'], $wc_carrier['instance_id']);

				if ($wc_carrier['method_id'] != 'local_pickup' && !preg_match('/request_shipping_quote(_\d+)?/', $wc_carrier['method_id'])) {
					$carriers[] = array(
						'id_carrier' => $wc_carrier['instance_id'],
						'name' => __($carrier_name, 'woocommerce'),
						'active' => $wc_carrier['is_enabled'],
					);
				}
			}

			$carriers_products = array();

			$no_display_zones_without_products = false;
			$products = array();

			foreach ($carriers as $carrier) {
				$product_selected = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id_product FROM {$wpdb->prefix}correos_oficial_carriers_products WHERE id_carrier = %s AND id_zone = %d",
						$carrier['id_carrier'],
						$zone['id_zone']
					),
					ARRAY_A
				);

				if (!empty($product_selected)) {
					$carriers_products[] = array(
						'id_carrier' => $carrier['id_carrier'],
						'name' => $carrier['name'],
						'active' => $carrier['active'],
						'product_selected' => $product_selected[0]['id_product'],
					);
				} else {
					$carriers_products[] = array(
						'id_carrier' => $carrier['id_carrier'],
						'name' => $carrier['name'],
						'active' => $carrier['active'],
						'product_selected' => '0',
					);
				}
				$products = $this->getActiveProductsForSelect($zone['id_zone']);

				if (!$products) {
					$no_display_zones_without_products = true;
				}
			}

			// Si la zona no tiene productos asociados en WC->Ajustes->Envío no la mostramos
			if ($no_display_zones_without_products) {
				continue;
			}

			$zonesandcarriers[] = array(
				'id_zone' => $zone['id_zone'],
				'zonename' => $zone['name'],
				'carriers' => $carriers_products,
				'products' => $products,
			);
			$this->smarty->assign('zonesandcarriers', $zonesandcarriers);
		}
	}

	public function getActiveProductsForSelect( $id_zone ) {

		$products = CorreosOficialCarrier::getCarriersByCompany('both', $id_zone);
		
		$products2 = array();
		$correos_oficial_products_counter = 0;

		/*
		 * Ordenamos el array por la clave 'id_product'
		 */
		usort($products, array( $this, 'sortByKey' ));

		$before = 0;
		foreach ($products as $product) {
$product_dao = new CorreosOficialProduct();

			/* Si es un producto nativo si no es de Correos */
			if ($product['id_product'] == null) {
				continue;
			} else {
				$correos_oficial_products_counter++;
			}

			/*
			 * Eliminamos repetición
			 */
			if ($before == $product['id_product']) {
				continue;
			}

			$before = $product['id_product'];

			$product_dao->id = $product['id_product'];
			$product_dao->name = $product['name'];
			$product_dao->product_type = $product['product_type'];
			$products2[] = $product_dao;
		}

		if ($correos_oficial_products_counter == 0) {
			$products2 = array();
			$this->smarty->assign('select_active_products_html', $products2);
		} else {
			$this->smarty->assign('select_active_products_html', $products2);
		}

		return $products2;
	}

	/**
	 * Se ordena por clave 'id_product'
	 */
	private function sortByKey( $a, $b ) {
		if ($a['id_product'] === $b['id_product']) {
			return 0;
		}
		return ( $a['id_product'] < $b['id_product'] ) ? -1 : 1;
	}

	/**
	 * Obtiene los atributos de producto
	 * @return array Array de atributos con id => nombre
	 */
	private function getProductAttributes() {
		$attributes = array();
		
		// Obtener atributos globales
		$wc_attributes = wc_get_attribute_taxonomies();
		
		if (!empty($wc_attributes)) {
			foreach ($wc_attributes as $attribute) {
				$attributes['pa_' . $attribute->attribute_name] = $attribute->attribute_label;
			}
		}
		
		return $attributes;
	}
}
