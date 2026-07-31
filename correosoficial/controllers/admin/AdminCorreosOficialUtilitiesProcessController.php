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

if (!defined('WC_VERSION')) {
	die;
}

require_once __DIR__ . '/../../vendor/pdfmerger.php';

use CorreosOficial\Classes\CorreosOficialNeedCustoms;
use CorreosOficial\Classes\CorreosOficialCarrier;
use CorreosOficial\Classes\CorreosOficialOrder;
use CorreosOficial\Classes\CorreosOficialOrders;
use CorreosOficial\Classes\CorreosOficialOrdersWC;
use WC_Order;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Services\CorreosOficialDataTableService;
use CorreosOficial\Classes\CorreosOficialApiRouter;
use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialSender;
use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Classes\CorreosOficialSenders;
use Exception;

class AdminCorreosOficialUtilitiesProcessController {

	public $module;
	protected $context;

	private $default_sender;

	public function __construct() {

		global $co_debugCorreosOficial;

		$this->default_sender = CorreosOficialSender::get_default_sender();

		$controllerAction = sanitize_text_field(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

		switch ($controllerAction) {

			// Acciones y llamadas a WS
			case 'registerOrders':
				$this->registerOrders();
				break;
			case 'registerSingleOrder':
				$this->registerSingleOrder();
				break;
			case 'printLabelsGenerated':
				$this->getEtiquetasByShippingNumber();
				break;
			case 'generatePickups':
				$this->generatePickups();
				break;
			case 'getCustomsDoc':
				$this->getDocAduanera();
				break;
			// Búsquedas
			case 'searchOrdersRegistration':
				$date_from = CorreosOficialNormalization::normalizeData('FromDateOrdersReg');
				$date_to = CorreosOficialNormalization::normalizeData('ToDateOrdersReg');
				$this->getDataTableSearch($date_from, $date_to);
				break;
			case 'searchLabels':
				$date_from = CorreosOficialNormalization::normalizeData('FromDateLabels');
				$date_to = CorreosOficialNormalization::normalizeData('ToDateLabels');
				$this->getShippingsPreregistered($date_from, $date_to);
				break;
			case 'searchOrdersSummary':
				$date_from = CorreosOficialNormalization::normalizeData('FromDateSummary');
				$date_to = CorreosOficialNormalization::normalizeData('ToDateSummary');
				$this->getShippingsPreregistered($date_from, $date_to);
				break;
			case 'searchPickups':
				$date_from = CorreosOficialNormalization::normalizeData('FromDatePickups');
				$date_to = CorreosOficialNormalization::normalizeData('ToDatePickups');
				$this->getShippingsPreregistered($date_from, $date_to);
				break;
			case 'searchCustomsDoc':
				$date_from = CorreosOficialNormalization::normalizeData('FromDateCustomsDoc');
				$date_to = CorreosOficialNormalization::normalizeData('ToDateCustomsDoc');
				$this->getShippingsCustomsDoc($date_from, $date_to);
				break;
			// Funciones Auxiliares
			case 'getActiveProducts':
				$this->getActiveProductsForDataTableOrders();
				break;
			case 'deleteFiles':
				CorreosOficialUtils::deleteFiles();
				break;
			case 'generatePDFManifest':
				$orders = CorreosOficialNormalization::normalizeData('selectedData');
				$this->generatePDFManifest($orders);
				break;
				
		}
	}

	// PREREGISTRO MASIVO DE PEDIDOS
	public function registerOrders() {
		
		$orders              = CorreosOficialNormalization::normalizeData('selectedData');

		$pickup_data = CorreosOficialNormalization::normalizeData('selectedGrabarRecogida');
		$pickup = isset($pickup_data) ? $pickup_data : 'N';
		$pickupDateRegister  = CorreosOficialNormalization::normalizeData('PickupDateRegister');
		$pickupFromRegister  = CorreosOficialNormalization::normalizeData('PickupFromRegister');
		$pickupToRegister    = CorreosOficialNormalization::normalizeData('PickupToRegister');
		$needPrintLablPickup = CorreosOficialNormalization::normalizeData('selectedImprimirEtiqueta') == 0 ? 'N' : 'S';
		$packetSize          = CorreosOficialNormalization::normalizeData('selectedTamanioPaquete');

		// Iteramos pedidos
		foreach ($orders as $order) {

			$APIRouter = new CorreosOficialApiRouter();

			// Sender
			$sender_id = empty($order['saved_sender']) ? $order['sender_default'] : $order['saved_sender'];
			$co_sender   = new CorreosOficialSender($sender_id);

			// Product
			$product_id = empty($order['mod_product']) ? $order['id_product'] : $order['mod_product'];
			$co_product  = new CorreosOficialProduct($product_id);

			// WC Order
			$wc_order    = wc_get_order($order['id_order']);

			// Office or CityPaq or PudoCEX
			$cod_office = '';
			if ($co_product->get_product_type() == 'office') {
				$cod_office = $order['office'];
			}
			$cod_homepaq = '';
			if ($co_product->get_product_type() == 'citypaq') {
				$cod_homepaq = $order['office'];
			}

			$cod_pudocex = '';
			if ($co_product->get_product_type() == 'pudocex') {
				$cod_pudocex = $order['office'];
			}


			// Added Values
			$added_values = array();

			// Cash on delivery - usa el método configurado en CashOnDeliveryMethod (con fallback a 'cod')
			$cash_on_delivery_method = ( new CorreosOficialConfig('CashOnDeliveryMethod') )->get_value();
			if ( empty( $cash_on_delivery_method ) ) {
				$cash_on_delivery_method = 'cod';
			}
			if ($wc_order->get_payment_method() == $cash_on_delivery_method) {
				$bank_acc_number = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
				$added_values['cash_on_delivery'] = 'true';
				$added_values['cash_on_delivery_value'] = number_format( (float) $wc_order->get_total(), 2, '.', '');
				$decryptedIban = CorreosOficialCrypto::decrypt($bank_acc_number);
				$added_values['cash_on_delivery_iban'] = $decryptedIban !== false ? $decryptedIban : '';
			}

			// Requiere Doc Aduanera
			$require_customs_doc = CorreosOficialNeedCustoms::isCustomsRequired(
				$co_sender->get_sender_cp(),
				$wc_order->get_shipping_postcode(),
				$co_sender->get_sender_iso_code_pais(),
				$wc_order->get_shipping_country()
			);

			// Información bultos
            $current_index  = 1;
            $items    = $wc_order->get_items();
            $total_articles = count($items);
            $total_weight   = 0;
            $total_units    = 0;

			// Verificar si el producto está en la lista de productos con dimensiones por defecto
			$dimensions_by_default = ( new CorreosOficialConfig('ActivateDimensionsByDefault') )->get_value();
			
			// Detección de producto especial
            $is_special_product =
                !empty($co_product)
                && (
                    in_array($co_product->get_product_type(), ['citypaq', 'pudocex'], true)
                    || $co_product->get_codigoProducto() === 'S0179'
                );
			
			// Calculamos pesos
			$orderWeight = 0;
			// --- Pasada 1: detectar padres Composite que declaran su propio peso.
			// Sus hijos (wooco_parent_id === ID del producto padre) deben saltarse
			// para no duplicar el peso del paquete.
			$composite_parent_product_ids = [];
			foreach ( $items as $pre_item ) {
				if ( ! $pre_item->is_type( 'line_item' ) ) {
					continue;
				}
				$pre_product = $pre_item->get_product();
				if ( ! $pre_product ) {
					continue;
				}
				if ( CorreosOficialHelpers::isCompositeParentItem( $pre_item, $pre_product )
					&& CorreosOficialHelpers::getProductMetaWeight( $pre_product ) > 0 ) {
					$composite_parent_product_ids[ (int) $pre_product->get_id() ] = true;
				}
			}

			// --- Pasada 2: acumular peso y unidades.
			foreach ( $items as $item ) {
				if ( ! $item->is_type( 'line_item' ) ) {
					continue;
				}

				$product = $item->get_product();

				$is_composite_parent = CorreosOficialHelpers::isCompositeParentItem($item, $product);
				$is_bundle_parent    = CorreosOficialHelpers::isBundleParentItem($item, $product);
				$parent_meta_weight  = $is_composite_parent ? CorreosOficialHelpers::getProductMetaWeight($product) : 0.0;

				// Caso A: PADRE de Composite con _weight > 0 → usa su peso,
				// los hijos se saltarán en esta misma pasada.
				if ($is_composite_parent && $parent_meta_weight > 0) {
					$quantity      = $item->get_quantity();
					$total_weight += $parent_meta_weight * $quantity;
					$total_units  += $quantity;
					continue;
				}

				// Caso B: PADRE de Composite sin peso, o PADRE de Bundle →
				// se salta y los hijos aportan su peso (comportamiento histórico).
				if ($is_composite_parent || $is_bundle_parent) {
					continue;
				}

				// Hijo de un Composite cuyo padre YA aportó su peso → skip.
				$wooco_parent_id = $item->get_meta('wooco_parent_id');
				if ($wooco_parent_id === '' || $wooco_parent_id === null) {
					$wooco_parent_id = $item->get_meta('_wooco_parent_id');
				}
				if ($wooco_parent_id && ! empty($composite_parent_product_ids[ (int) $wooco_parent_id ])) {
					continue;
				}

				if ( $product ) {
					$weight       = CorreosOficialHelpers::getProductMetaWeight($product);
					$quantity     = $item->get_quantity();
					$total_weight += $weight * $quantity;
					$total_units  += $quantity;
				}
			}

			$single_unit_single_product = ($total_articles === 1 && $total_units === 1);

			// Peso por defecto en caso de que el producto no tenga uno (excepto envíos con aduana).
            if( $total_weight == 0 && ( new CorreosOficialConfig('ActivateWeightByDefault') )->get_value() == 'on' && !$require_customs_doc ){
                $total_weight = floatval(str_replace(',', '.', ( new CorreosOficialConfig('WeightByDefault') )->get_value()));
            }

			// Utilidades necesita tratar el peso por si viene en una medida diferente a KG.
			$total_weight = CorreosOficialHelpers::weightToKg($total_weight);

			// Caso: un único producto con una unidad y dimensiones válidas
            if ($single_unit_single_product  && $co_product->get_product_type() == 'pudocex') {
				$articles = [];
				$units    = 0;

				foreach ($items as $item) {
					if (! $item instanceof WC_Order_Item_Product) {
						continue;
					}

					$product = $item->get_product();
					if (! $product || ! $product->has_dimensions()) {
						continue;
					}

					$articles[] = [
						'depth'  => (float) $product->get_length(),
						'width'  => (float) $product->get_width(),
						'height' => (float) $product->get_height(),
					];

					$units += (int) $item->get_quantity();
				}

				// Caso: un único artículo con dimensiones válidas
				if (
					count($articles) === 1 &&
					$units === 1 &&
					$articles[0]['depth'] > 0 &&
					$articles[0]['width'] > 0 &&
					$articles[0]['height'] > 0
				) {
                    $info_bulto[$current_index] = [
                        'reference'       => $order['reference'],
                        'weight'          => ((float) $total_weight / (float) $order['bultos']),
                        'height'          => (int) $articles[0]['depth'],
                        'width'           => (int) $articles[0]['width'],
                        'large'           => (int) $articles[0]['height'],
                        'observations'    => ''
                    ];

					$current_index++;
				}
			// Caso: dimensiones por defecto activadas para productos especiales
			} elseif (!empty($co_product) && $is_special_product && $dimensions_by_default) {

				$info_bulto[$current_index] = [
                    'reference'       => $order['reference'],
					'weight'          => ((float) $total_weight / (float) $order['bultos']),
					'height'          => (int) (new CorreosOficialConfig('DimensionsByDefaultHeight'))->get_value(),
					'width'           => (int) (new CorreosOficialConfig('DimensionsByDefaultWidth'))->get_value(),
					'large'           => (int) (new CorreosOficialConfig('DimensionsByDefaultLarge'))->get_value(),
					'observations'    => ''
				];
				
				$current_index++;
			} else {
				$info_bulto[$current_index] = [
                    'reference'       => $order['reference'],
                    'weight'          => ((float) $total_weight / (float) $order['bultos']),
                    'height'          => '',
                    'width'           => '',
                    'large'           => '',
                    'observations'    => ''
                ];

                $current_index++;
			}
			// Replicar el bulto para todos los bultos adicionales (todos iguales)
			if (isset($info_bulto[1]) && (int) $order['bultos'] > 1) {
				for ($i = 2; $i <= (int) $order['bultos']; $i++) {
					$info_bulto[$i] = $info_bulto[1];
				}
			}

			// Referencia Expedición aduanera
			$custom_ref_exp = ( new CorreosOficialConfig('ShippCustomsReference') )->get_value();

			// Composición del Payload
			$payload[] = array(
				'order_id'              => $order['id_order'],
				'product_id'            => $order['id_product'],
				'sender_id'             => empty($order['saved_sender']) ? $order['sender_default'] : $order['saved_sender'],
				'company'               => $order['carrier_type'],
				'delivery_mode'         => $co_product->get_product_type(),
				'needPickup'            => $pickup,
				'pickupDateRegister'    => $pickupDateRegister,
				'pickupFromRegister'    => $pickupFromRegister,
				'pickupToRegister'      => $pickupToRegister,
				'needPrintLablPickup'   => $needPrintLablPickup,
				'packetSize'          => $packetSize,
				'order_form'            => array(
					'sender_name'     => $co_sender->get_sender_name(),
					'sender_contact'  => $co_sender->get_sender_contact(),
					'sender_address'  => $co_sender->get_sender_address(),
					'sender_city'     => $co_sender->get_sender_city(),
					'sender_country'  => $co_sender->get_sender_iso_code_pais(),
					'sender_phone'    => $co_sender->get_sender_phone(),
					'sender_email'    => $co_sender->get_sender_email(),
					'sender_nif_cif'  => $co_sender->get_sender_nif_cif(),
					'sender_cp'       => $co_sender->get_sender_cp(),
					'customer_cp'        => $wc_order->get_shipping_postcode(),
					'customer_firstname' => $wc_order->get_shipping_first_name(),
					'customer_lastname'  => $wc_order->get_shipping_last_name(),
					'customer_company'   => '',
					'customer_contact'   => '',
					'customer_address'   => $wc_order->get_shipping_address_1() . ' ' . $wc_order->get_shipping_address_2(),
					'customer_city'      => $wc_order->get_shipping_city(),
					'customer_phone'     => $wc_order->get_billing_phone(),
					'customer_email'     => $wc_order->get_billing_email(),
					'customer_dni'       => CorreosOficialOrder::getRealDnI($order['id_order']),
					'customer_country'   => $wc_order->get_shipping_country(),
					'order_reference'    => $order['reference'],
					'cod_homepaq'        => $cod_homepaq,
					'cod_office'         => $cod_office,
					'cod_pudocex'        => $cod_pudocex,
					'correos-num-parcels' => $order['bultos'],
					'AT_code'             => $order['AT_code'],
					'all_packages_equal'  => '1',
					'require_customs_doc' => $require_customs_doc,
					'custom_ref_exp'      => isset($custom_ref_exp) ? $custom_ref_exp : '',
					'input_select_carrier'=> $co_product->get_codigoProducto(),
                    'total_weight'        => $total_weight,
                    'partial_delivery'    => 'false',
					'contrareembolsoCheckbox' => !empty($added_values['cash_on_delivery']) ? 1 : 0,
					'cash_on_delivery_value'  => !empty($added_values['cash_on_delivery_value']) ? $added_values['cash_on_delivery_value'] : '',
				),
				'source_channel'      => 'WOO',
				'info_bulto'          => $info_bulto,
				'added_values'        => $added_values,
			);

		}

		// CALL PRE-REGISTRO
		$responsePreRegistro = $APIRouter->registrarEnvio($payload, 'utilities');

		// RETURN
		wp_send_json($responsePreRegistro, 200);
	}

	// PREREGISTRO DE UN SOLO PEDIDO (para procesamiento secuencial)
	public function registerSingleOrder() {
		try {
			$order               = CorreosOficialNormalization::normalizeData('orderData');
			
			$pickup_data         = CorreosOficialNormalization::normalizeData('selectedGrabarRecogida');
			$pickup = isset($pickup_data) ? $pickup_data : 'N';
			$pickupDateRegister  = CorreosOficialNormalization::normalizeData('PickupDateRegister');
			$pickupFromRegister  = CorreosOficialNormalization::normalizeData('PickupFromRegister');
			$pickupToRegister    = CorreosOficialNormalization::normalizeData('PickupToRegister');
			$needPrintLablPickup = CorreosOficialNormalization::normalizeData('selectedImprimirEtiqueta') == 0 ? 'N' : 'S';
			$packetSize          = CorreosOficialNormalization::normalizeData('selectedTamanioPaquete');

			$APIRouter = new CorreosOficialApiRouter();

			// Sender
			$sender_id = empty($order['saved_sender']) ? (isset($order['sender_default']) ? $order['sender_default'] : null) : $order['saved_sender'];
			
			if (!$sender_id) {
				throw new Exception(__('Order #' . $order['id_order'] . ': No sender has been configured', 'correosoficial'));
			}
			$co_sender = new CorreosOficialSender($sender_id);

			// Product
			$product_id = empty($order['mod_product']) ? (isset($order['id_product']) ? $order['id_product'] : null) : $order['mod_product'];
			
			if (!$product_id) {
				throw new Exception(__('Order #' . $order['id_order'] . ': No shipping product has been selected', 'correosoficial'));
			}
			$co_product = new CorreosOficialProduct($product_id);

			// WC Order
			$wc_order = wc_get_order($order['id_order']);

			// Office or CityPaq or PudoCEX
			$cod_office = '';
			if ($co_product->get_product_type() == 'office') {
				$cod_office = isset($order['office']) ? $order['office'] : '';
			}
			$cod_homepaq = '';
			if ($co_product->get_product_type() == 'citypaq') {
				$cod_homepaq = isset($order['office']) ? $order['office'] : '';
			}

			$cod_pudocex = '';
			if ($co_product->get_product_type() == 'pudocex') {
				$cod_pudocex = isset($order['office']) ? $order['office'] : '';
			}

			// Added Values
			$added_values = array();

			// Cash on delivery - usa el método configurado en CashOnDeliveryMethod (con fallback a 'cod')
			$cash_on_delivery_method = ( new CorreosOficialConfig('CashOnDeliveryMethod') )->get_value();
			if ( empty( $cash_on_delivery_method ) ) {
				$cash_on_delivery_method = 'cod';
			}
			if ($wc_order->get_payment_method() == $cash_on_delivery_method) {
				$bank_acc_number = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
				$added_values['cash_on_delivery'] = 'true';
				$added_values['cash_on_delivery_value'] = number_format( (float) $wc_order->get_total(), 2, '.', '');
				$decryptedIban = CorreosOficialCrypto::decrypt($bank_acc_number);
				$added_values['cash_on_delivery_iban'] = $decryptedIban !== false ? $decryptedIban : '';
			}

			// Requiere Doc Aduanera
			$require_customs_doc = CorreosOficialNeedCustoms::isCustomsRequired(
				$co_sender->get_sender_cp(),
				$wc_order->get_shipping_postcode(),
				$co_sender->get_sender_iso_code_pais(),
				$wc_order->get_shipping_country()
			);

			// Información bultos
			$current_index  = 1;
			$items    = $wc_order->get_items();
			$total_articles = count($items);
			$total_weight   = 0;
			$total_units    = 0;

			// Verificar si el producto está en la lista de productos con dimensiones por defecto
			$dimensions_by_default = ( new CorreosOficialConfig('ActivateDimensionsByDefault') )->get_value();
			
			// Detección de producto especial
			$is_special_product =
				!empty($co_product)
				&& (
					in_array($co_product->get_product_type(), ['citypaq', 'pudocex'], true)
					|| $co_product->get_codigoProducto() === 'S0179'
				);
			
			// Calculamos pesos
			foreach ( $items as $item ) {
				if ( ! $item->is_type( 'line_item' ) ) {
					continue;
				}

				$product = $item->get_product();

				if ( $product ) {
					$weight = floatval( $product->get_weight() );
					$quantity = $item->get_quantity();
					$total_weight += $weight * $quantity;
					$total_units += $quantity;
				}
			}

			$single_unit_single_product = ($total_articles === 1 && $total_units === 1);

			// Peso por defecto en caso de que el producto no tenga uno (excepto envíos con aduana).
			if( $total_weight == 0 && ( new CorreosOficialConfig('ActivateWeightByDefault') )->get_value() == 'on' && !$require_customs_doc ){
				$total_weight = ( new CorreosOficialConfig('WeightByDefault') )->get_value();
			}

			// Utilidades necesita tratar el peso por si viene en una medida diferente a KG.
			$total_weight = CorreosOficialHelpers::weightToKg($total_weight);

			// Caso: un único producto con una unidad y dimensiones válidas
			if ($single_unit_single_product  && $co_product->get_product_type() == 'pudocex') {
				$articles = [];
				$units    = 0;

				foreach ($items as $item) {
					if (! $item instanceof WC_Order_Item_Product) {
						continue;
					}

					$product = $item->get_product();
					if (! $product || ! $product->has_dimensions()) {
						continue;
					}

					$articles[] = [
						'depth'  => (float) $product->get_length(),
						'width'  => (float) $product->get_width(),
						'height' => (float) $product->get_height(),
					];

					$units += (int) $item->get_quantity();
				}

				if (
					count($articles) === 1 &&
					$units === 1 &&
					$articles[0]['depth'] > 0 &&
					$articles[0]['width'] > 0 &&
					$articles[0]['height'] > 0
				) {
					$info_bulto[$current_index] = [
						'reference'       => $order['reference'],
						'weight'          => ((float) $total_weight / (float) $order['bultos']),
						'height'          => (int) $articles[0]['depth'],
						'width'           => (int) $articles[0]['width'],
						'large'           => (int) $articles[0]['height'],
						'observations'    => ''
					];

					$current_index++;
				}
			// Caso: dimensiones por defecto activadas para productos especiales
			} elseif (!empty($co_product) && $is_special_product && $dimensions_by_default) {

				$info_bulto[$current_index] = [
					'reference'       => $order['reference'],
					'weight'          => ((float) $total_weight / (float) $order['bultos']),
					'height'          => (int) (new CorreosOficialConfig('DimensionsByDefaultHeight'))->get_value(),
					'width'           => (int) (new CorreosOficialConfig('DimensionsByDefaultWidth'))->get_value(),
					'large'           => (int) (new CorreosOficialConfig('DimensionsByDefaultLarge'))->get_value(),
					'observations'    => ''
				];
				
				$current_index++;
			} else {
				$info_bulto[$current_index] = [
					'reference'       => $order['reference'],
					'weight'          => ((float) $total_weight / (float) $order['bultos']),
					'height'          => '',
					'width'           => '',
					'large'           => '',
					'observations'    => ''
				];

				$current_index++;
			}

			// Replicar el bulto para todos los bultos adicionales (todos iguales)
			if (isset($info_bulto[1]) && (int) $order['bultos'] > 1) {
				for ($i = 2; $i <= (int) $order['bultos']; $i++) {
					$info_bulto[$i] = $info_bulto[1];
				}
			}

			// Referencia Expedición aduanera
			$custom_ref_exp = ( new CorreosOficialConfig('ShippCustomsReference') )->get_value();

			// Composición del Payload
			$payload[] = array(
				'order_id'              => $order['id_order'],
				'product_id'            => $order['id_product'],
				'sender_id'             => empty($order['saved_sender']) ? $order['sender_default'] : $order['saved_sender'],
				'company'               => $order['carrier_type'],
				'delivery_mode'         => $co_product->get_product_type(),
				'needPickup'            => $pickup,
				'pickupDateRegister'    => $pickupDateRegister,
				'pickupFromRegister'    => $pickupFromRegister,
				'pickupToRegister'      => $pickupToRegister,
				'needPrintLablPickup'   => $needPrintLablPickup,
				'packetSize'            => $packetSize,
				'order_form'            => array(
					'sender_name'     => $co_sender->get_sender_name(),
					'sender_contact'  => $co_sender->get_sender_contact(),
					'sender_address'  => $co_sender->get_sender_address(),
					'sender_city'     => $co_sender->get_sender_city(),
					'sender_country'  => $co_sender->get_sender_iso_code_pais(),
					'sender_phone'    => $co_sender->get_sender_phone(),
					'sender_email'    => $co_sender->get_sender_email(),
					'sender_nif_cif'  => $co_sender->get_sender_nif_cif(),
					'sender_cp'       => $co_sender->get_sender_cp(),
					'customer_cp'        => $wc_order->get_shipping_postcode(),
					'customer_firstname' => $wc_order->get_shipping_first_name(),
					'customer_lastname'  => $wc_order->get_shipping_last_name(),
					'customer_company'   => '',
					'customer_contact'   => '',
					'customer_address'   => $wc_order->get_shipping_address_1() . ' ' . $wc_order->get_shipping_address_2(),
					'customer_city'      => $wc_order->get_shipping_city(),
					'customer_phone'     => $wc_order->get_billing_phone(),
					'customer_email'     => $wc_order->get_billing_email(),
					'customer_dni'       => CorreosOficialOrder::getRealDnI($order['id_order']),
					'customer_country'   => $wc_order->get_shipping_country(),
					'order_reference'    => $order['reference'],
					'cod_homepaq'        => $cod_homepaq,
					'cod_office'         => $cod_office,
					'cod_pudocex'        => $cod_pudocex,
					'correos-num-parcels' => $order['bultos'],
					'AT_code'             => $order['AT_code'],
					'all_packages_equal'  => '1',
					'require_customs_doc' => $require_customs_doc,
					'custom_ref_exp'      => isset($custom_ref_exp) ? $custom_ref_exp : '',
					'input_select_carrier'=> $co_product->get_codigoProducto(),
					'total_weight'        => $total_weight,
					'partial_delivery'    => 'false',
					'contrareembolsoCheckbox' => !empty($added_values['cash_on_delivery']) ? 1 : 0,
					'cash_on_delivery_value'  => !empty($added_values['cash_on_delivery_value']) ? $added_values['cash_on_delivery_value'] : '',
				),
				'source_channel'      => 'WOO',
				'info_bulto'          => $info_bulto,
				'added_values'        => $added_values,
			);

			// CALL PRE-REGISTRO
			$responsePreRegistro = $APIRouter->registrarEnvio($payload, 'utilities');

			// RETURN
			wp_send_json($responsePreRegistro, 200);
			
		} catch (Exception $e) {
			// Log error for debugging
			$originalMessage = $e->getMessage();
			$errorMessage = $originalMessage;
			
			// Detectar errores de conexión
			if (stripos($errorMessage, 'Connection') !== false || 
				stripos($errorMessage, 'timeout') !== false || 
				stripos($errorMessage, 'Could not connect') !== false ||
				stripos($errorMessage, '503') !== false ||
				stripos($errorMessage, 'Service Unavailable') !== false) {
				$errorMessage = __('Correos service is temporarily unavailable. Please try again later.', 'correosoficial');
			}
			// Detectar errores de autenticación
			else if (stripos($errorMessage, '401') !== false || 
					 stripos($errorMessage, '403') !== false ||
					 stripos($errorMessage, 'Unauthorized') !== false ||
					 stripos($errorMessage, 'Forbidden') !== false) {
				$errorMessage = __('Authentication error with Correos API. Please verify the configured credentials.', 'correosoficial');
			}
			// Filtrar mensajes técnicos de PHP
			else if (strpos($errorMessage, 'Warning:') !== false || strpos($errorMessage, 'Notice:') !== false) {
				$errorMessage = __('Order configuration error. Please verify that all required fields are complete.', 'correosoficial');
			}
			
			// Return error response
			wp_send_json([
				[
					'id_order' => isset($order['id_order']) ? $order['id_order'] : 'unknown',
					'mensajeRetorno' => $errorMessage,
					'codigoRetorno' => -1
				]
			], 500);
		}
	}

	public function changeOrderStatus( $id_order ) {
		if ((new CorreosOficialConfig('ShowShippingStatusProcess'))->get_value() == 'on') {
			$config_status_value = (new CorreosOficialConfig('ShipmentPreregistered'))->get_value();
			CorreosOficialUtils::changeOrderStatus($id_order, $config_status_value);
		}
	}

	// GENERACIÓN ORDENES DE RECOGIDA
	public function generatePickups() {
		$orders              = CorreosOficialNormalization::normalizeData('selectedDataPickups');

		$pickup_data = CorreosOficialNormalization::normalizeData('selectedGrabarRecogida');
		$pickup = isset($pickup_data) ? $pickup_data : 'N';
		$pickupDateRegister  = CorreosOficialNormalization::normalizeData('PickupDate');
		$pickupFromRegister  = CorreosOficialNormalization::normalizeData('PickupFrom');
		$pickupToRegister    = CorreosOficialNormalization::normalizeData('PickupTo');
		$needPrintLablPickup = CorreosOficialNormalization::normalizeData('selectedImprimirEtiqueta') == 0 ? 'N' : 'S';
		$packetSize          = CorreosOficialNormalization::normalizeData('TamLabelPickups');
		
		$page = CorreosOficialNormalization::normalizeData('page', 'no_uppercase');

		if (isset($page) && $page == 'pickup') {
			$needPrintLablPickup = CorreosOficialNormalization::normalizeData('PrintLabelPickups');
		}

		foreach ($orders as $order) {
			$APIRouter = new CorreosOficialApiRouter();

			// Sender
			$sender_id = isset($order['id_sender']) ? $order['id_sender'] : $order['saved_sender'];

			// Product
			if (isset($order['mod_product'])) {
				$product_id = $order['mod_product'];
			} else {
				$product_id = isset($order['id_product']) ? $order['id_product'] : '';
			}

			if ($product_id) {
				$co_product  = new CorreosOficialProduct($product_id);
			}

			$payload[] = array(
				'order_id'  => $order['id_order'],
				'sender_id' => isset($sender_id) ? $sender_id : '',
				'company'   => $order['company'],
				'bultos'    => $order['bultos'],
				'pickupDateRegister'  => $pickupDateRegister,
				'pickupFromRegister'  => $pickupFromRegister,
				'pickupToRegister'    => $pickupToRegister,
				'needPrintLablPickup' => $needPrintLablPickup,
				'packetSize'          => $packetSize ? $packetSize : $order['package_size'],
				'producto'            => isset($co_product) ? $co_product->get_codigoProducto() : '',
				'pickup'              => $pickup,
			);  
		}

		// CALL GENERAR RECOGIDA
		$responseRegistrarRecogida = $APIRouter->registrarRecogida($payload, 'utilities');

		// RETURN
		wp_send_json($responseRegistrarRecogida, 200);
	}

	//Genera HTML con Option para Columna ModProdcut de Datatable Orders
	public function getActiveProductsForDataTableOrders() {
		$products = CorreosOficialProduct::get_active_products(' WHERE cop.active=1 ORDER BY id ASC');

		$select_active_products_html = '<option selected="" disabled="" value="0">' . __('Select a product', 'correosoficial') . '</option>';
		foreach ($products as $product => $field) {
			$select_active_products_html .= '<option value="' . $field->id . '" data-max-packages="' . $field->max_packages . '"
                data-product-code="' . $field->codigoProducto . '" data-company="' . $field->company . '"
                data-product-type="' . $field->product_type . '">' . $field->name . '</option>';
		}
		die(json_encode($select_active_products_html));
	}

	public function getDataTableSearch( $date_from, $date_to ) {
		$orders = CorreosOficialDataTableService::get_orders_for_mass_management($date_from, $date_to);
		$orders = $this->addExtraPackagesInfo($orders);
		die(json_encode($orders));
	}

	// Rellena la tabla de Reimpresion de Etiquetas con pedidos ya prerregistrados
	public function getShippingsPreregistered( $date_from, $date_to ) {
		$orders = CorreosOficialDataTableService::get_shippings($date_from, $date_to);
		$orders = $this->addExtraPackagesInfo($orders);
		die(json_encode($orders));
	}

	//Llama la función del dao para rellenar la tabla de Generación de documentación aduanera
	public function getShippingsCustomsDoc( $date_from, $date_to ) {
		$orders = CorreosOficialDataTableService::get_shippings_customs_doc($date_from, $date_to);
		$orders = $this->addExtraPackagesInfo($orders);
		die(json_encode($orders));
	}

	//Obtiene etiquetas
	public function getEtiquetasByShippingNumber() {

		$orders     = CorreosOficialNormalization::normalizeData('selectedDataReimpresion');
		$label_type = CorreosOficialNormalization::normalizeData('selectedTipoEtiquetaReimpresion');
		$label_position = CorreosOficialNormalization::normalizeData('selectedPosicionEtiquetaReimpresion');
		$label_format = CorreosOficialNormalization::normalizeData('selectedFormatEtiquetaReimpresion');

		foreach ($orders as $order) {
			$APIRouter = new CorreosOficialApiRouter();

			// Sender
			$sender_id = isset($order['id_sender']) ? $order['id_sender'] : $order['saved_sender'];

			// Product
			if (isset($order['mod_product'])) {
				$product_id = $order['mod_product'];
			} else {
				$product_id = isset($order['id_product']) ? $order['id_product'] : '';
			}

			if ($product_id) {
				$co_product  = new CorreosOficialProduct($product_id);
			}

			$payload[] = array(
				'order_id'       => $order['id_order'],
				'product_id'     => $product_id,
				'sender_id'      => $sender_id,
				'company'        => isset($order['carrier_type']) ? $order['carrier_type'] : $order['company'],
				'product'        => isset($co_product) ? $co_product->get_data() : '',
				'label_type'     => $label_type,
				'label_position' => $label_position,
				'label_format'   => $label_format,
				'reference'      => $order['reference'],
				'exp_number'     => isset($order['shipping_number']) ? $order['shipping_number'] : $order['first_shipping_number'],
			);
		}

		// CALL REIMPRESION ETIQUETA
		$responseImpresionEtiqueta = $APIRouter->impresionEtiqueta($payload, 'utilities');

		wp_send_json($responseImpresionEtiqueta, 200);
	}
	
	public function getDocAduanera() {

		$orders = CorreosOficialNormalization::normalizeData('selectedDataDocAduanera');
		$optionButton = sanitize_text_field(isset($_REQUEST['optionButton']) ? $_REQUEST['optionButton'] : '');
	
		foreach ($orders as $order) {
			$APIRouter = new CorreosOficialApiRouter();

			$payload[] = array(
				'order_id'      => $order['id_order'],
				'adressed_name' => $order['customer_name'],
				'customer_iso'  => $order['customer_country'],
				'company'       => $order['company'],
				'print_option'  => strtoupper($optionButton),
				'reference'     => $order['reference'],
				'sender_id'     => $order['saved_sender']
			);
		}

		$responseGetDocAduanera = $APIRouter->getDocAduanera($payload, 'utilities');
		wp_send_json($responseGetDocAduanera, 200);
	}

	private function addExtraPackagesInfo( $expeditions ) {
		$i = 0;
		foreach ($expeditions as $expedition => $row) {
			if ($row['shipping_number'] != '') {
				if ($row['bultos'] > 1) {
					$expeditions[$i]['first_shipping_number'] = $expeditions[$i]['first_shipping_number'] . ' (' . $row['bultos'] . ')';
				}
			}
			$i++;
		}
		return $expeditions;
	}

	private function getDimensionsByType( $available_carrier_d, $activateDimensionsByDefault ) {
		return array(
			'large' => ( $available_carrier_d == 1 && $activateDimensionsByDefault == true ) ?
			(int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultLarge') : 0,
			'height' => ( $available_carrier_d == 1 && $activateDimensionsByDefault == true ) ?
			(int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultHeight') : 0,
			'width' => ( $available_carrier_d == 1 && $activateDimensionsByDefault == true ) ?
			(int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultWidth') : 0,
		);
	}

	
	public function generatePDFManifest( $orders ) {
		// formateamos las líneas de los pedidos dividiéndolas por compañía y por sender
		// para que haya una página en el PDF con cada información
		$client_pages = array();
		// Esta variable es para comprobar que hay más de un sender, de tal modo ponemos el nombre de la tienda en lugar del sender
		$name_store = 0;
		foreach ($orders as $order) {
			$correos_order = CorreosOficialOrders::getCorreosOrder($order['id_order']);
			$correos_packages = CorreosOficialOrders::getCorreosPackages($order['id_order'], null);
			$order_wc = new WC_Order((int) $order['id_order']);
			$address_formatted = $order_wc->get_shipping_address_1() . ' ' . $order_wc->get_shipping_address_2() . ' ' . $order_wc->get_shipping_city() . ' - ' . $order_wc->get_shipping_postcode() . ' ' . $order_wc->get_shipping_country();
			$sender = CorreosOficialSenders::getSenderById($correos_order['id_sender']);
			$product = CorreosOficialCarrier::getCarrierByProductId($correos_order['id_product']);

			// Creamos una key con company para agruparlos
			$key = $order['company'];
			$sender_customer_code = ( $key == 'Correos' ) ? $sender['correos_code'] : $sender['cex_code'];
			$id_sender = $correos_order['id_sender'];

			if (!isset($client_pages[$key])) {
				
				$client_pages[$key] = array(
					'name' => $sender['sender_name'],
					'client_code' => $sender_customer_code,
					'id_sender' => $correos_order['id_sender'],
					'company' => $order['company'],
					'orders' => array(),
				);

			// Comprobamos que haya más de un sender
			} elseif ($id_sender !== $client_pages[$key]['id_sender']) {
				$name_store = 1;
			}

			$client_pages[$key]['orders'][$order['id_order']] = $order;

			//Añadimos los bultos
			$client_pages[$key]['orders'][$order['id_order']]['bultos'] = $correos_order['bultos'];

			// Agrupamos los paquetes del pedido para tener el los total KGs y los Shipping numbers agrupados
			$client_pages[$key]['orders'][$order['id_order']]['total_weight'] = 0;
			$shipping_numbers = array();
			foreach ($correos_packages as $package) {
				$shipping_numbers[] = $package['shipping_number'];
				$client_pages[$key]['orders'][$order['id_order']]['total_weight'] += $package['weight'];
			}

			// shipping numbers largos de saved orders separados por comas
			$client_pages[$key]['orders'][$order['id_order']]['shipping_numbers'] = implode(',', $shipping_numbers);

			// shipping number corto, en saved_orders es el exp_number
			$client_pages[$key]['orders'][$order['id_order']]['exp_number'] = $correos_order['shipping_number'];

			// generamos la dirección añadiendo el CP + el iso code del país
			$client_pages[$key]['orders'][$order['id_order']]['address'] = $address_formatted;

			// Añadimos el total de reembolso y el total del seguro en caso de tener
			$client_pages[$key]['orders'][$order['id_order']]['insurance_value'] = ( $correos_order['added_values_insurance_value'] ) ? $correos_order['added_values_insurance_value'] : '0';
			$client_pages[$key]['orders'][$order['id_order']]['cash_on_delivery_value'] = ( $correos_order['added_values_cash_on_delivery_value'] ) ? $correos_order['added_values_cash_on_delivery_value'] : '0';

			// Creamos la información para el total por productos dentro de cada agrupación
			if (!isset($client_pages[$key]['total_products'][$correos_order['id_product']])) {

				//totales por producto
				$client_pages[$key]['total_products'][$correos_order['id_product']] = array(
					'product' => $product['name'],
					'total_bultos' => 0,
					'total_weight' => 0,
					'total_insurance' => 0,
					'total_cash_on_delivery_value' => 0,
				);
			}

			//totales por página
			if (!isset($client_pages[$key]['total_page'])) {
				$client_pages[$key]['total_page'] = array(
					'total_bultos_page' => 0,
					'total_weight_page' => 0,
					'total_insurance_page' => 0,
					'total_cash_on_delivery_value_page' => 0,
				);
			}
			// Añadimos los totales a cada producto
			$client_pages[$key]['total_products'][$correos_order['id_product']]['total_bultos'] += (int) $correos_order['bultos'];
			$client_pages[$key]['total_products'][$correos_order['id_product']]['total_weight'] += floatval($client_pages[$key]['orders'][$order['id_order']]['total_weight']);
			$client_pages[$key]['total_products'][$correos_order['id_product']]['total_insurance'] += floatval($correos_order['added_values_insurance_value']);
			$client_pages[$key]['total_products'][$correos_order['id_product']]['total_cash_on_delivery_value'] += floatval($correos_order['added_values_cash_on_delivery_value']);

			// Añadimos los totales a la página
			$client_pages[$key]['total_page']['total_bultos_page'] += (int) $correos_order['bultos'];
			$client_pages[$key]['total_page']['total_weight_page'] += floatval($client_pages[$key]['orders'][$order['id_order']]['total_weight']);
			$client_pages[$key]['total_page']['total_insurance_page'] += floatval($correos_order['added_values_insurance_value']);
			$client_pages[$key]['total_page']['total_cash_on_delivery_value_page'] += floatval($correos_order['added_values_cash_on_delivery_value']);
		}

		// cambiamos el nombre al nombre de la tienda en caso de que haya más de 1 sender
		if ($name_store == 1) {
			foreach ($client_pages as $key => $page) {
				$client_pages[$key]['name'] = get_option('blogname');
				$client_pages[$key]['client_code'] = '';
			}
		}

		//$pdf = new PDFMerger();
		$labelType = CorreosOficialNormalization::normalizeData('selectedTipoEtiquetaReimpresion');
		$labelFormat = CorreosOficialNormalization::normalizeData('selectedFormatEtiquetaReimpresion');
		$pdf = new \CorreosOficial\PDFMerger($labelType, $labelFormat);
		$tempFolder = dirname(MODULE_CORREOS_OFICIAL_PATH) . '/pdftmp';
		$pdfOutputFile = $tempFolder . '/' . uniqid('manifest_') . '.pdf';

		$pdf_generated = $pdf->createManifest($pdfOutputFile, $client_pages);

		// si se ha generado correctamente el pdf actualizamos la manifest_date para cada pedido seleccionado
		if ($pdf_generated instanceof \PDF_MC_Table) {
			foreach ($orders as $order) {
				CorreosOficialOrders::updateOrderManifestDate($order['id_order']);
			}
		}

		$pdf_generated->Output('F', $pdfOutputFile);

		die(json_encode($pdfOutputFile));
	}
}
