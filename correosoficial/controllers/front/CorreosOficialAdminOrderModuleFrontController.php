<?php
use CorreosOficial\Classes\CorreosOficialNeedCustoms;
use CorreosOficial\Classes\CorreosOficialUtils;
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

use CorreosOficial\Classes\CorreosOficialApiRouter;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\CorreosOficialReturnsMail;
use CorreosOficial\Classes\CorreosOficialSenders;
use CorreosOficial\Models\CorreosOficialConfig;

if (! defined('WC_VERSION')) {
	die;
}

require_once __DIR__ . '/../../classes/CorreosOficialOrder.php';
require_once __DIR__ . '/../../classes/CorreosOficialOrders.php';
require_once __DIR__ . '/../../classes/CorreosOficialReturnsMail.php';
require_once __DIR__ . '/../../classes/CorreosOficialCheckout.php';

require_once __DIR__ . '/../../vendor/pdfmerger.php';

class CorreosOficialAdminOrderModuleFrontController {


	public $context;
	public $db;
	public $horaActual;
	public $statusProcessActive;

	public function __construct() {
		$this->horaActual   = gmdate('Y-m-d H:i:s', time());

		$this->initContent();
	}

	public function initContent() {

		$row = CorreosOficialConfig::get_config_status('ShowShippingStatusProcess');
		$this->statusProcessActive = $row ? $row->value : '';

		$action = sanitize_text_field(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

		switch ($action) {
			case 'RequireCustom':
				$cp_source                = CorreosOficialNormalization::normalizeData('cp_source');
				$cp_dest                  = CorreosOficialNormalization::normalizeData('cp_dest');
				$country_source           = CorreosOficialNormalization::normalizeData('country_source');
				$country_dest             = CorreosOficialNormalization::normalizeData('country_dest');
				$result['require_custom'] = CorreosOficialNeedCustoms::isCustomsRequired($cp_source, $cp_dest, $country_source, $country_dest);
				die(json_encode($result));
				break;
			case 'getSenderById':
				$sender_id = CorreosOficialNormalization::normalizeData('sender_id');
				// $sender = CorreosOficialSendersDao::getSenderById($sender_id);
				$sender = CorreosOficialSenders::getSenderById($sender_id);
				die(json_encode($sender));
				break;
			case 'getOrderStatus':
				$this->getOrderStatus();
				break;
			case 'getMarketplaceOrderStatus':
				$this->getMarketplaceOrderStatus();
				break;
			case 'printLabel':
				$this->getEtiquetasByExpNumber();
				break;
			case 'printLabelReturn':
				$this->getEtiquetasByExpNumber();
				break;
			case 'getCustomsDoc':
				$this->getDocAduanera();
				break;
			case 'deleteFiles':
				CorreosOficialUtils::deleteFiles();
				break;
			case 'generateOrder':
				$this->generateOrder();
				break;
			case 'cancelOrder':
				$this->cancelOrder('order');
				break;
			case 'cancelReturn':
				$this->cancelOrder('return');
				break;
			case 'generatePickup':
				$this->generatePickup();
				break;
			case 'cancelPickup':
				$this->cancelarRecogida();
				break;
			case 'generateReturn':
				$this->generateReturn();
				break;
			case 'sendEmail':
				$this->getEtiquetasByExpNumber();
				break;
		}
	}

	public function generateReturn() {
		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //
		$info_bulto_raw = isset($_REQUEST['info_bulto']) ? wp_unslash($_REQUEST['info_bulto']) : '[]'; // phpcs:ignore

		$payload = array(
			'order_id'            => CorreosOficialNormalization::normalizeData('order_id'),
			'order_reference'     => CorreosOficialNormalization::normalizeData('order_reference'),
			'product_id'          => CorreosOficialNormalization::normalizeData('id_product'),
			'sender_id'           => CorreosOficialNormalization::normalizeData('id_sender'),
			'company'             => CorreosOficialNormalization::normalizeData('company'),
			'delivery_mode'       => CorreosOficialNormalization::normalizeData('delivery_mode', 'no_uppercase'),
			'needPickup'          => CorreosOficialNormalization::normalizeData('needPickup'),
			'needPrintLablPickup' => CorreosOficialNormalization::normalizeData('needPrintLablPickup'),
			'packetSize'          => CorreosOficialNormalization::normalizeData('packetSize'),
			'order_form'          => CorreosOficialNormalization::normalizeData('order_form'),
			'info_bulto'          => json_decode($info_bulto_raw, true),
		);


		$generateReturnResponse = $APIRouter->generateReturn($payload);
		wp_send_json($generateReturnResponse, 200);
	}

	public function generateOrder() {

		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //
		$info_bulto_raw = isset($_REQUEST['info_bultos']) ? wp_unslash($_REQUEST['info_bultos']) : '[]'; // phpcs:ignore

		$payload = array(
			'order_id'            => CorreosOficialNormalization::normalizeData('order_id'),
			'product_id'          => CorreosOficialNormalization::normalizeData('id_product'),
			'sender_id'           => CorreosOficialNormalization::normalizeData('id_sender'),
			'company'             => CorreosOficialNormalization::normalizeData('company'),
			'delivery_mode'       => CorreosOficialNormalization::normalizeData('delivery_mode', 'no_uppercase'),
			'needPickup'          => CorreosOficialNormalization::normalizeData('needPickup'),
			'pickupDateRegister'  => CorreosOficialNormalization::normalizeData('pickupDateRegister'),
			'pickupFromRegister'  => CorreosOficialNormalization::normalizeData('pickupFromRegister'),
			'pickupToRegister'    => CorreosOficialNormalization::normalizeData('pickupToRegister'),
			'needPrintLablPickup' => CorreosOficialNormalization::normalizeData('needPrintLablPickup'),
			'packetSize'          => CorreosOficialNormalization::normalizeData('packetSize'),
			'order_form'          => CorreosOficialNormalization::normalizeData('order_form'),
			'info_bulto'          => json_decode($info_bulto_raw, true),
			'source_channel'      => 'WOO',
			'added_values'        => array(
				'cash_on_delivery'       => CorreosOficialNormalization::normalizeData('added_values_cash_on_delivery'),
				'insurance'              => CorreosOficialNormalization::normalizeData('added_values_insurance'),
				'partial_delivery'       => CorreosOficialNormalization::normalizeData('added_values_partial_delivery'),
				'delivery_saturday'      => CorreosOficialNormalization::normalizeData('added_values_delivery_saturday'),
				'cash_on_delivery_iban'  => CorreosOficialNormalization::normalizeData('added_values_cash_on_delivery_iban'),
				'cash_on_delivery_value' => CorreosOficialNormalization::normalizeData('added_values_cash_on_delivery_value'),
				'insurance_value'        => CorreosOficialNormalization::normalizeData('added_values_insurance_value'),
			),
			'request_data'   => CorreosOficialNormalization::normalizeData('request_data'),
			'reference_code' => CorreosOficialNormalization::normalizeData('reference_code'),
		);

		// CALL PRE-REGISTRO
		$responsePreRegistro = $APIRouter->registrarEnvio($payload);

		// RETURN
		wp_send_json($responsePreRegistro, 200);
	}

	public function cancelOrder( $type ) {
		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //
		$payload = array(
			'order_id'             => CorreosOficialNormalization::normalizeData('order_id'),
			'carrier_id'           => CorreosOficialNormalization::normalizeData('id_carrier'),
			'sender_id'            => CorreosOficialNormalization::normalizeData('sender_id'),
			'company'              => CorreosOficialNormalization::normalizeData('company'),
			'expedition_number'    => CorreosOficialNormalization::normalizeData('expedition_number'),
			'pickup_number_return' => CorreosOficialNormalization::normalizeData('pickup_number_return'),
			'lang'                 => CorreosOficialNormalization::normalizeData('lang'),
			'type'                 => $type,
		);

		// CALL CANCELAR ENVIO
		$responseCancelarEnvio = $APIRouter->cancelarEnvio($payload);

		// RETURN
		wp_send_json($responseCancelarEnvio, 200);
	}

	public function generatePickup() {
		$APIRouter = new CorreosOficialApiRouter();
		
		// La recogida solo se puede realizar con Correos
		if (CorreosOficialNormalization::normalizeData('company') != 'Correos') {
			return;
		}

		$payload = array(
			'order_id'            => CorreosOficialNormalization::normalizeData('order_id'),
			'product_id'          => CorreosOficialNormalization::normalizeData('id_product'),
			'sender_id'           => CorreosOficialNormalization::normalizeData('id_sender'),
			'company'             => CorreosOficialNormalization::normalizeData('company'),
			'bultos'              => CorreosOficialNormalization::normalizeData('bultos'),
			'pickupDateRegister'  => CorreosOficialNormalization::normalizeData('pickup_date'),
			'pickupFromRegister'  => CorreosOficialNormalization::normalizeData('sender_from_time'),
			'pickupToRegister'    => CorreosOficialNormalization::normalizeData('sender_to_time'),
			'needPrintLablPickup' => CorreosOficialNormalization::normalizeData('print_label') == 0 ? 'N' : 'S',
			'packetSize'          => CorreosOficialNormalization::normalizeData('package_type'),
			'producto'            => CorreosOficialNormalization::normalizeData('producto'),
			'mode_pickup'         => CorreosOficialNormalization::normalizeData('mode_pickup'),
			'order_form'          => array(
				'input_tamanio_paquete' => CorreosOficialNormalization::normalizeData('package_type'),
				'order_reference'       => CorreosOficialNormalization::normalizeData('order_reference'),
				'sender_address'        => CorreosOficialNormalization::normalizeData('sender_address'),
				'sender_city'           => CorreosOficialNormalization::normalizeData('sender_city'),
				'sender_cp'             => CorreosOficialNormalization::normalizeData('sender_cp'),
				'sender_name'           => CorreosOficialNormalization::normalizeData('sender_name'),
				'sender_contact'        => CorreosOficialNormalization::normalizeData('sender_contact'),
				'sender_phone'          => CorreosOficialNormalization::normalizeData('sender_phone'),
				'sender_email'          => CorreosOficialNormalization::normalizeData('sender_email', 'email'),
				'sender_nif_cif'        => CorreosOficialNormalization::normalizeData('sender_nif_cif'),
				'sender_country'        => CorreosOficialNormalization::normalizeData('sender_country'),
			),
		);

		// CALL GENERAR RECOGIDA
		$responseRegistrarRecogida = $APIRouter->registrarRecogida($payload);

		// RETURN
		wp_send_json($responseRegistrarRecogida, 200);
	}

	public function getCN23ToEmail( $order_id, $iso_code ) {
		/* Se consigue ruta del CN23 */
		$json   = file_get_contents(plugins_url('correosoficial') . '/dispatcher.php?controller=CorreosOficialAdminOrderModuleFrontController&ajax=true&action=getCustomsDoc&exp_number=' . $order_id . '&type=return&customer_country=' . $iso_code . '&optionButton=ImprimirCN23Button2');
		$result = json_decode($json);

        if (empty($result->errors)) { // phpcs:ignore
			$filename = $result->files[0];
			$path     = WP_CONTENT_DIR . '/plugins/correosoficial/pdftmp/' . $filename;

			/**
			 *
			 * Lectura del fichero de CN23 de devolución
			 */
			$handle   = fopen($path, 'rb');
			$contents = fread($handle, filesize($path));
			fclose($handle);

			// CN23 codificado en base64
			return base64_encode($contents);
		}
		return null;
	}

	public function cancelarRecogida() {
		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //
		$payload = array(
			'order_id'             => CorreosOficialNormalization::normalizeData('order_id'),
			'sender_id'            => CorreosOficialNormalization::normalizeData('sender_id'),
			'pickup_number'        => CorreosOficialNormalization::normalizeData('pickup_number'),
			'pickup_number_return' => CorreosOficialNormalization::normalizeData('pickup_number_return'),
			'mode_pickup'          => CorreosOficialNormalization::normalizeData('mode_pickup'),
			'company'              => CorreosOficialNormalization::normalizeData('company'),
			'lang'                 => CorreosOficialNormalization::normalizeData('lang'),
		);

		$responseCancelPickup = $APIRouter->cancelarRecogida($payload);

		wp_send_json($responseCancelPickup, 200);
	}

	//Obtiene etiquetas
	public function getEtiquetasByExpNumber() {
		$APIRouter = new CorreosOficialApiRouter();

		
		// codigo de cada bulto (1 - 10), solo para el envio de email.
		$returns_code = array();

		for ($i = 1; $i < 11; $i++) {
			$returns_code[] = CorreosOficialNormalization::normalizeData('return_code_' . $i);
		}

		// PAYLOAD ------------------------------------------------------------------------------------------- //

		$payload = array(
			'order_id'               => CorreosOficialNormalization::normalizeData('order_id'),
			'company'                => CorreosOficialNormalization::normalizeData('company'),
			'exp_number'             => CorreosOficialNormalization::normalizeData('exp_number'),
			'product_id'             => CorreosOficialNormalization::normalizeData('product_id'),
			'sender_id'              => CorreosOficialNormalization::normalizeData('sender_id'),
			'label_type'             => CorreosOficialNormalization::normalizeData('selectedTipoEtiquetaReimpresion'),
			'label_format'           => CorreosOficialNormalization::normalizeData('selectedFormatEtiquetaReimpresion'),
			'label_position'         => CorreosOficialNormalization::normalizeData('selectedPosicionEtiquetaReimpresion'),
			'delivery_mode'          => CorreosOficialNormalization::normalizeData('delivery_mode'),
			'send_email'             => CorreosOficialNormalization::normalizeData('send_email'),
			'order_form'             => CorreosOficialNormalization::normalizeData('order_form'),
			'returns_code'           => $returns_code,
		);

		$responseImpresionEtiqueta = $APIRouter->impresionEtiqueta($payload);
		
		// SOLO ENVIO EMAIL
		if ($payload['send_email']) {
	
			$email = new CorreosOficialReturnsMail();

			$result_email = $email->sendEmail($responseImpresionEtiqueta);
			
			if ($result_email) {
				$responseImpresionEtiqueta['mensajeRetorno'] = __('An email was sended to the customer with details of the return', 'correosoficial');
				$responseImpresionEtiqueta['codigoRetorno']  = 0;
			} else {
				$responseImpresionEtiqueta['codigoRetorno'] = 1;
				$responseImpresionEtiqueta['mensajeRetorno'] = $result_email . '. ' . __('Can not send returns email to your customer. Please, print the label and CN23 documents and send an email to your customer', 'correosoficial');
			}
		}

		wp_send_json($responseImpresionEtiqueta, 200);
	}

	public function mergeArraysIntoOne( $shipping_numbers ) {
		$clean_shipping_numbers = array();
		foreach ($shipping_numbers as $shipping_number) {
			$clean_shipping_numbers[] = $shipping_number['shipping_number'];
		}

		return $clean_shipping_numbers;
	}

	public function getDocAduanera() {
		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //

		$payload = array(
			'order_id' 	       => CorreosOficialNormalization::normalizeData('order_id'),
			'sender_id'        => CorreosOficialNormalization::normalizeData('sender_id'),
			'adressed_name'    => CorreosOficialNormalization::normalizeData('adressed_name'),
			'customer_country' => CorreosOficialNormalization::normalizeData('customer_country'),
			'customer_iso'     => CorreosOficialNormalization::normalizeData('customer_iso'),
			'company'          => CorreosOficialNormalization::normalizeData('company'),
			'exp_number'       => CorreosOficialNormalization::normalizeData('exp_number'),
			'postal_code'      => CorreosOficialNormalization::normalizeData('postal_code'),
			'print_option'     => CorreosOficialNormalization::normalizeData('optionButton'),
			'type'             => CorreosOficialNormalization::normalizeData('type', 'no_uppercase')
		);

		$responseGetDocAduanera = $APIRouter->getDocAduanera($payload);

		wp_send_json($responseGetDocAduanera, 200);
	}

	public function getOrderStatus() {
		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //
		$payload = array(
			'order_id'   => CorreosOficialNormalization::normalizeData('order_id'),
			'sender_id'  => CorreosOficialNormalization::normalizeData('sender_id'),
			'company'    => CorreosOficialNormalization::normalizeData('company'),
		);

		$responseGetOrderStatus = $APIRouter->getOrderStatus($payload);

		
		wp_send_json($responseGetOrderStatus, 200);
	}

	/**
	 * Devuelve el histórico de seguimiento para un número de expedición de Marketplace.
	 *
	 * A diferencia de getOrderStatus(), aquí el shipping_number llega directamente
	 * desde el JS (se leyó del order meta _correosoficial_marketplace_tracking_number)
	 * y no hay que buscarlo en la tabla wp_correosoficial_orders.
	 *
	 * El resultado tiene el mismo formato { events: [...] } que getOrderStatus()
	 * para que el DataTable del template lo consuma sin cambios.
	 */
	public function getMarketplaceOrderStatus() {
		$shipping_number = CorreosOficialNormalization::normalizeData('shipping_number');
		$sender_id       = CorreosOficialNormalization::normalizeData('sender_id');
		$id_order        = (int) CorreosOficialNormalization::normalizeData('id_order');

		// Resolve product name + company from the WC order's shipping method instance.
		$cod_producto = 'Correos';
		if ($id_order > 0) {
			$wc_order = wc_get_order($id_order);
			if ($wc_order) {
				foreach ($wc_order->get_shipping_methods() as $shipping_item) {
					$instance_id = (int) $shipping_item->get_instance_id();
					if ($instance_id > 0) {
						$product = \CorreosOficial\Models\CorreosOficialProduct::get_carrier_params(array('id_reference' => $instance_id));
						if ($product) {
							$name    = isset($product['name'])    ? $product['name']    : '';
							$company = isset($product['company']) ? $product['company'] : '';
							if ($name !== '') {
								$cod_producto = $company !== '' ? $name . ' (' . $company . ')' : $name;
							}
						}
						break;
					}
				}
			}
		}

		// Respuesta vacía por defecto
		$default_event = array(
			'codEnvio'        => '',
			'codProducto'     => '',
			'desTextoResumen' => 'En espera de datos',
			'fecEvento'       => '',
			'horEvento'       => '',
			'unidad'          => '',
		);
		$result = array( 'events' => array( $default_event ) );

		if (empty($shipping_number)) {
			wp_send_json($result, 200);
			return;
		}

		$APIRouter = new CorreosOficialApiRouter();

		$payload = array(
			'shipping_number' => $shipping_number,
			'sender_id'       => $sender_id,
			'company'         => 'Correos', // Marketplace siempre usa Correos
		);

		$outputApi = $APIRouter->checkOutputApi($payload);

		if (!$outputApi || $outputApi !== API_P3) {
			wp_send_json($result, 200);
			return;
		}

		$correosRest   = new \CorreosOficial\Classes\Apis\CorreosOficialRest();
		$package_status = $correosRest->getOrderStatusP3($payload);

		if ($package_status && is_array($package_status) && isset($package_status[0]['events']) && !empty($package_status[0]['events'])) {
			$last_status = array();
			$i = 0;
			foreach ($package_status[0]['events'] as $event) {
				if (!isset($event['summaryText']) || $event['summaryText'] === null) {
					continue;
				}
				$last_status[$i++] = array(
					'codEnvio'        => isset($package_status[0]['code']) ? $package_status[0]['code'] : $shipping_number,
					'codProducto'     => $cod_producto,
					'desTextoResumen' => $event['summaryText'],
					'fecEvento'       => isset($event['eventDate'])  ? $event['eventDate']  : '',
					'horEvento'       => isset($event['eventHours']) ? $event['eventHours'] : '',
					'unidad'          => isset($event['unit'])       ? $event['unit']       : '',
				);
			}
			if (!empty($last_status)) {
				$result = array( 'events' => $last_status );
			}
		}

		wp_send_json($result, 200);
	}

	public static function getCustomsDesc( $bultos ) {

		$customs_desc_array          = array();
		$returned_customs_desc_array = array();
		$units                       = array( ' €', ' Kg', ' Unid.' );

		if (isset($_POST['_nonce'])) {
			$nonce = sanitize_text_field($_POST['_nonce']);
			if (! wp_verify_nonce(wp_unslash($nonce), 'correosoficial_nonce')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_send_json_error('bad_nonce');
				wp_die();
			}
		}

		for ($i = 1; $i <= $bultos; $i++) {
			$n = 0;

			if (! isset($_POST['dispatcher']['order_form'][$i])) {
				return;
			}
			$formData = array_map('sanitize_text_field', (array) $_POST['dispatcher']['order_form'][$i]);

			foreach ($formData as $customs_desc) {

				$customs_desc                   = str_replace($units, '', $customs_desc);
				$customs_desc                   = rtrim($customs_desc, ' • ');
				$customs_desc_array[$i][$n + 1] = $customs_desc;
				$n++;
			}

			foreach ($customs_desc_array as $customs_desc) {
				$h = 0;

				foreach ($customs_desc as $cd) {

					// Informamos solo las descripciones necesarias.
					if ($h < count($customs_desc_array[$i])) {

						$elements = explode(' • ', $cd);

						$len_ntarifario = strlen($elements[0]);

						if ($len_ntarifario == 6 || $len_ntarifario == 8 || $len_ntarifario == 10) {
							$returned_customs_desc_array[$i][$h]['numero_tarifario']     = $elements[0];
							$returned_customs_desc_array[$i][$h]['descripcion_aduanera'] = $elements[1];
						} else {
							$returned_customs_desc_array[$i][$h]['numero_tarifario']     = '';
							$returned_customs_desc_array[$i][$h]['descripcion_aduanera'] = $elements[0];
						}

						$returned_customs_desc_array[$i][$h]['valor_neto'] = $elements[2] * 100;
						$returned_customs_desc_array[$i][$h]['weight']     = $elements[3] * 1000;
						$returned_customs_desc_array[$i][$h]['unidades']   = $elements[4];
						$h++;
					}
				}
			}
		}

		return $returned_customs_desc_array;
	}

	public function getStatus( $search ) {
		if ($this->statusProcessActive == 'on') {
			$row = CorreosOficialConfig::get_config_status($search);
			return $row ? $row->value : false;
		}
		return false;
	}
}
