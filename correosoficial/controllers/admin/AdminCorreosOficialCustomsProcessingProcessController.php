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

use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Models\CorreosOficialConfig;

if (!defined('WC_VERSION')) {
	die;
}



class AdminCorreosOficialCustomsProcessingProcessController {


	public function __construct() {
		$this->getCustomFormData();
	}

	public function getCustomFormData() {
		// Obtenemos campos de los formularios
		$DefaultCustomsDescription = CorreosOficialNormalization::normalizeData('DefaultCustomsDescription');
		$TranslatableInput = CorreosOficialNormalization::normalizeData('TranslatableInput');
		$FormSwitchLanguage = CorreosOficialNormalization::normalizeData('FormSwitchLanguage');
		$Tariff = CorreosOficialNormalization::normalizeData('Tariff');
		$TariffDescription = CorreosOficialNormalization::normalizeData('TariffDescription');
		$MessageToWarnBuyer = CorreosOficialNormalization::normalizeData('MessageToWarnBuyer');
		$CustomsDesriptionAndTariff = CorreosOficialNormalization::normalizeData('CustomsDesriptionAndTariff');
		$CustomsDesriptionAndTariffSelected = CorreosOficialNormalization::normalizeData('CustomsDesriptionAndTariffSelected');
		$ShippCustomsReference = CorreosOficialNormalization::normalizeData('ShippCustomsReference');
        $CountryOriginByDefault = CorreosOficialNormalization::normalizeData('CountryOriginByDefault');
		$MappedHsFeature = CorreosOficialNormalization::normalizeData('MappedHsFeature', 'no_uppercase');
		$MappedOriginFeature = CorreosOficialNormalization::normalizeData('MappedOriginFeature', 'no_uppercase');
		$UseModuleFeatures = CorreosOficialNormalization::normalizeData('UseModuleFeatures');

		// Asegurarse de que CustomsDesriptionAndTariff es un array con al menos un valor
		if (!isset($CustomsDesriptionAndTariff[0]) && $CustomsDesriptionAndTariffSelected !== '') {
			$CustomsDesriptionAndTariff = array($CustomsDesriptionAndTariffSelected);
		}

		// Si UseModuleFeatures está marcado, crear atributos y asignarlos
		if ($UseModuleFeatures == 'on') {
			$this->createWooCommerceAttributes();
			// Asignar los atributos del módulo
			$MappedHsFeature = 'pa_hs_code';
			$MappedOriginFeature = 'pa_country_of_origin';
		}

		// Traducción de los campos.
		$string_from_db = CorreosOficialConfig::getConfigValue( 'TranslatableInput' );
		$TranslatableInput = CorreosOficialUtils::translateStringsToDB(
			$string_from_db,
			$FormSwitchLanguage,
			$TranslatableInput
		);

		// Los metemos en un array
		$fields = array(
			'DefaultCustomsDescription' => $DefaultCustomsDescription,
			'TranslatableInput' => $TranslatableInput,
			'FormSwitchLanguage' => $FormSwitchLanguage,
			'Tariff' => $Tariff,
			'TariffDescription' => $TariffDescription,
			'MessageToWarnBuyer' => $MessageToWarnBuyer,
			'ShippCustomsReference' => $ShippCustomsReference,
            'CountryOriginByDefault' => $CountryOriginByDefault,
			'MappedHsFeature' => $MappedHsFeature,
			'MappedOriginFeature' => $MappedOriginFeature,
			'UseModuleFeatures' => $UseModuleFeatures
		);

		// Solo actualizar los radios si CustomsDesriptionAndTariff tiene un valor válido
		if (isset($CustomsDesriptionAndTariff[0])) {
			if ($CustomsDesriptionAndTariff[0] == 0) {
				$fields['DescriptionRadio'] = 'on';
				$fields['TariffRadio'] = '';
				$fields['ProductRadio'] = '';
			} else if ($CustomsDesriptionAndTariff[0] == 1) {
				$fields['TariffRadio'] = 'on';
				$fields['DescriptionRadio'] = '';
				$fields['ProductRadio'] = '';
			} else if ($CustomsDesriptionAndTariff[0] == 2) {
				$fields['ProductRadio'] = 'on';
				$fields['DescriptionRadio'] = '';
				$fields['TariffRadio'] = '';
			}
		}

		// Guardar configuración de aduanas
		foreach ( $fields as $key => $value ) {
			CorreosOficialConfig::save( $key, $value );
		}
	}

	/**
	 * Crea los atributos código HS y país de origen
	 */
	private function createWooCommerceAttributes() {
		global $wpdb;
		
		// Tabla de atributos de WooCommerce
		$attribute_table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
		
		// Verificar si el atributo HS Code ya existe
		$hs_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$attribute_table} WHERE attribute_name = %s",
				'hs_code'
			)
		);
		
		if (!$hs_exists) {
			// Insertar atributo HS Code
			$wpdb->insert(
				$attribute_table,
				array(
					'attribute_name'    => 'hs_code',
					'attribute_label'   => 'HS Code',
					'attribute_type'    => 'text',
					'attribute_orderby' => 'menu_order',
					'attribute_public'  => 0
				),
				array('%s', '%s', '%s', '%s', '%d')
			);
			
			// Registrar la taxonomía
			register_taxonomy('pa_hs_code', array('product'), array(
				'labels' => array(
					'name' => 'HS Code',
				),
				'public' => true,
				'show_ui' => true,
				'show_in_quick_edit' => false,
				'show_admin_column' => false,
				'query_var' => true,
				'rewrite' => false,
			));
		}
		
		// Verificar si el atributo Country of Origin ya existe
		$origin_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$attribute_table} WHERE attribute_name = %s",
				'country_of_origin'
			)
		);
		
		if (!$origin_exists) {
			// Insertar atributo Country of Origin
			$wpdb->insert(
				$attribute_table,
				array(
					'attribute_name'    => 'country_of_origin',
					'attribute_label'   => 'Country of Origin',
					'attribute_type'    => 'text',
					'attribute_orderby' => 'menu_order',
					'attribute_public'  => 0
				),
				array('%s', '%s', '%s', '%s', '%d')
			);
			
			// Registrar la taxonomía
			register_taxonomy('pa_country_of_origin', array('product'), array(
				'labels' => array(
					'name' => 'Country of Origin',
				),
				'public' => true,
				'show_ui' => true,
				'show_in_quick_edit' => false,
				'show_admin_column' => false,
				'query_var' => true,
				'rewrite' => false,
			));
		}
		
		// Limpiar el cache de taxonomías de WooCommerce
		delete_transient('wc_attribute_taxonomies');
		wp_cache_flush();
	}

}
