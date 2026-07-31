<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Classes\Apis\CorreosOficialCEXRest;
use CorreosOficial\Classes\Apis\CorreosOficialRest;
use CorreosOficial\Classes\Apis\CorreosOficialSGARest;
use CorreosOficial\Classes\Apis\CorreosOficialSoap;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialOrder;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialReturn;
use CorreosOficial\Models\CorreosOficialSavedOrder;
use CorreosOficial\Models\CorreosOficialSavedOrderDataStore;
use CorreosOficial\Models\CorreosOficialSavedReturn;
use CorreosOficial\Models\CorreosOficialSavedReturnDataStore;
use CorreosOficial\Models\CorreosOficialSender;
use CorreosOficial\Models\CorreosOficialRequests;
use CorreosOficial\Models\CorreosOficialSgaOrdersStatus;
use CorreosOficialOrders;
use CorreosOficial\PDFMerger;
use Product;

require_once __DIR__ . '/CorreosOficialOrders.php';

define('API_P3', 'P3');
define('API_LEGACY', 'LEGACY');
define('API_CEX', 'CEX');

class CorreosOficialApiRouter {

	protected $outputApi;
	protected $accountNotFound;

	public function __construct() {

		$this->outputApi = API_P3;

		$this->accountNotFound = array(
			'codigoRetorno' => 1,
			'mensajeRetorno' => __('The sender does not have a valid account for this product')
		);
	}

	/**
	 * Obtenemos API de salida según contrato
	 */
	public function checkOutputApi( &$payload ) {

		$co_sender = null;

		// SGA + Pickup bypass: cuando SGA está activo y el modo de entrega es
		// oficina/citypaq/pudocex (PaqPunto), las credenciales se obtienen
		// directamente de CorreosOficialCode sin necesidad de remitente.
		$sga_active = (new CorreosOficialConfig('ActivateSGA'))->get_value() === 'on';
		if ($sga_active) {
			$pickup_types = ['office', 'citypaq', 'pudocex'];
			$is_pickup_carrier = false;

			// Comprobar delivery_mode
			if (isset($payload['delivery_mode'])) {
				$is_pickup_carrier = in_array(strtolower(trim($payload['delivery_mode'])), $pickup_types, true);
			}

			// Comprobar selector_type (checkout context)
			if (!$is_pickup_carrier && isset($payload['selector_type'])) {
				$is_pickup_carrier = in_array(strtolower(trim($payload['selector_type'])), $pickup_types, true);
			}

			// Comprobar product_type del producto
			if (!$is_pickup_carrier && isset($payload['product_id'])) {
				$co_product_check = new CorreosOficialProduct($payload['product_id']);
				$is_pickup_carrier = in_array($co_product_check->get_product_type(), $pickup_types, true);
			}

			if ($is_pickup_carrier) {
				// Determinar la compañía desde el payload o el producto
				$company = isset($payload['company']) ? $payload['company'] : 'Correos';
				if (isset($payload['product_id'])) {
					$co_product_sga = new CorreosOficialProduct($payload['product_id']);
					$company = $co_product_sga->get_company();
					$payload['company'] = $company;
				}

				// Obtener credenciales directamente de CorreosOficialCode
				$direct_code = CorreosOficialCarrier::getClientCodeByCompany($company);

				if ($direct_code) {
					$payload['client'] = $direct_code;

					switch ($company) {
						case 'CEX':
							$this->outputApi = API_CEX;
							break;
						case 'Correos':
						default:
							if (isset($direct_code['CorreosClientID']) && $direct_code['CorreosClientID'] != 'n/a') {
								$this->outputApi = API_P3;
							} else {
								$this->outputApi = API_LEGACY;
							}
							break;
					}

					return $this->outputApi;
				}
			}
		}

		// Si viene desde el checkout utilizamos el primer remitente que permita realizar la busqueda por oficina/citypq/paq punto (CEX/CORREOS)
		if (isset($payload['checkout']) && $payload['checkout']) {
			$sender = new CorreosOficialSender();
			$co_sender = $sender->get_first_sender_by_company($payload['company']);
		}

		// Si no viene el id del remitente, obtenemos el remitente por defecto
		if (empty($co_sender) && isset($payload['sender_id']) && !empty($payload['sender_id'])) {
			$co_sender = new CorreosOficialSender($payload['sender_id']);
		} elseif ( empty($co_sender) ) {
			$co_sender = new CorreosOficialSender('default');
		}
		
		// Sobreescribimos el company según el id del producto que viene en el payload y no es return
        if ( isset($payload['product_id']) && isset($payload['delivery_mode']) && strtolower($payload['delivery_mode']) != 'return') {
            $co_product = new CorreosOficialProduct($payload['product_id']);
            $payload['company'] = $co_product->get_company();
        }
		
		switch ($payload['company']) {
			case 'Correos':
				$correos_code = $co_sender->get_correos_code();
				if ($correos_code) {
					// Añadimos al payload contratos correos
					$payload['client'] = $correos_code;

					if (isset($correos_code['CorreosClientID']) && $correos_code['CorreosClientID'] != 'n/a') {
						$this->outputApi = API_P3;
					} else {
						$this->outputApi = API_LEGACY;
					}
				} elseif ($sga_active) {
					// Fallback SGA: obtener credenciales directamente de la tabla de códigos
					$direct_code = CorreosOficialCarrier::getClientCodeByCompany('Correos');
					if ($direct_code) {
						$payload['client'] = $direct_code;
						if (isset($direct_code['CorreosClientID']) && $direct_code['CorreosClientID'] != 'n/a') {
							$this->outputApi = API_P3;
						} else {
							$this->outputApi = API_LEGACY;
						}
					} else {
						return false;
					}
				} else {
					return false;
				}
				break;

			case 'CEX':
				$cex_code = $co_sender->get_cex_code();
				if ($cex_code) {
					// Añadimos al payload contratos cex
					$payload['client'] = $cex_code;

					$this->outputApi = API_CEX;
				} elseif ($sga_active) {
					// Fallback SGA: obtener credenciales directamente de la tabla de códigos
					$direct_code = CorreosOficialCarrier::getClientCodeByCompany('CEX');
					if ($direct_code) {
						$payload['client'] = $direct_code;
						$this->outputApi = API_CEX;
					} else {
						return false;
					}
				} else {
					return false;
				}
				break;
		}

		return $this->outputApi;
	}

	/* *********************************************************************************************************
	* REGISTRAR ENVÍO
	********************************************************************************************************* */

	public function registrarEnvio( $payload, $origin = 'order' ) {
		// Indexar el payload si viene de la página de pedido
		$payloads = $origin == 'order' ? array( $payload ) : $payload;

		// Resultados procesados
		$results = array();

		// Iterar payloads de pedidos
		foreach ($payloads as $payload) {

			// Decodificar request_data si viene como string JSON
			if (isset($payload['request_data']) && is_string($payload['request_data']) && !empty($payload['request_data'])) {
				$payload['request_data'] = json_decode($payload['request_data'], true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					$payload['request_data'] = null;
				}
			}

			// Verificar que existe un remitente antes de intentar la llamada API.
			// Si no hay remitente configurado se devuelve un error claro en lugar de fallar silenciosamente.
			$_check_sender_id = ( isset($payload['sender_id']) && !empty($payload['sender_id']) ) ? $payload['sender_id'] : 'default';
			$_check_sender    = new CorreosOficialSender($_check_sender_id);
			if ( ! $_check_sender->get_id() ) {
				wp_send_json(
					array(
						'codigoRetorno'  => 1,
						'mensajeRetorno' => __( 'No sender configured. You must configure a sender in Settings > Senders before generating shipping labels.', 'correosoficial' ),
					),
					400
				);
			}

			// Comprobar Api de salida
			if ($this->checkOutputApi($payload)) {
				// Instanciar los modelos necesarios
				$co_sender = new CorreosOficialSender($payload['sender_id']);
				$co_product = new CorreosOficialProduct($payload['product_id']);
				$wc_order = wc_get_order( $payload['order_id'] );

				// Comprobamos si tenemos nota del cliente
				if($observations = $wc_order->get_customer_note()){
					$payload['order_form']['observations'] = $observations;
				} else {
					$payload['order_form']['observations'] = '';
				}

				// Agregar, transformar o reindexar campos
				$payload['id_order'] = $wc_order->get_id();
				$payload['product'] = $co_product->get_data();
				$payload['company'] = $co_product->get_company();
				$payload['bultos'] = $payload['order_form']['correos-num-parcels'];
				$payload['require_customs_doc'] = CorreosOficialNeedCustoms::isCustomsRequired(
					$co_sender->get_sender_cp(),
					$payload['order_form']['customer_cp'],
					$co_sender->get_sender_iso_code_pais(),
					$payload['order_form']['customer_country']
				);

				// Obtener todas las descripciones aduaneras de los bultos e indexarlas en payload
				$this->setCustomsDescArray($payload, $origin, $wc_order);

				/* *********************************************************************************************************
				* REGLAS DE NEGOCIO COMUNES
				********************************************************************************************************* */
				// Reglas de Pesos
				if ($payload["order_form"]["correos-num-parcels"] > 1 && $payload["order_form"]["all_packages_equal"] == "0"){
					$payload["order_form"]["total_weight"] = 0;
				}

				if ($payload['order_form']['total_weight']) {
					$payload['order_form']['total_weight'] = $payload['order_form']['total_weight'];
				}

				// Customer Real DNI/NIF
				if (empty($payload['order_form']['customer_dni'])) {
					$NifFieldRadio = ( new CorreosOficialConfig('NifFieldRadio') )->get_value();
					$NifFieldValue = $NifFieldRadio == 'PERSONALIZED' && $NifFieldRadio ? ( new CorreosOficialConfig('NifFieldPersonalizedValue') )->get_value() : 'NIF';
					$payload['order_form']['customer_dni'] = get_post_meta($payload['id_order'], $NifFieldValue, true);
				}

				// Datos de cliente para realizar una recogida del envio
				$payload['pickup_address_data'] = array(
					'province'        => str_split( $co_sender->get_sender_cp(), 2 )[0],
					'contactName'     => $co_sender->get_sender_name(),
					'lastNameContact' => !empty($co_sender->get_sender_contact()) ? $co_sender->get_sender_contact() : '-',
				);

				// Comprobar si el numero del pedido es mayor al máximo permitido en la tabla orders - INT
				$max_int = 4294967295;

				if ($payload['id_order'] > $max_int) {
					wp_send_json(
						array(
							'codigoRetorno' => 500,
							'mensajeRetorno' => __('The value of id_order exceeds the maximum allowed for the column id_order in correos_oficial_orders.')
						),
						500
					);
				}

				// Enrutador de llamadas y resultado
				switch ($this->outputApi) {
					case API_P3:
						$result = ( new CorreosOficialRest() )->registrarEnvio($payload, $origin);
						break;
					case API_LEGACY:
						$result = ( new CorreosOficialSoap() )->registrarEnvio($payload, $origin);
						break;
					case API_CEX:
						$result = ( new CorreosOficialCEXRest() )->registrarEnvio($payload);
						break;
					default:
						return false;
				}

				/* *********************************************************************************************************
				* RESULTADO KO
				********************************************************************************************************* */

				if($origin != 'order' && isset($result[0]) && ($result[0]['codigoRetorno'] > 0 || $result[0]['codigoRetorno'] < 0)) {
                    foreach($result as $subresult){
                        $results[] = $subresult;
                    }
                    continue;
                }else{
                    $this->normalizedErrorReturn($result);
                }

				/* *********************************************************************************************************
				* RESULTADO OK
				********************************************************************************************************* */

				// Crear order en CorreosOficial
				$co_order = new CorreosOficialOrder();
				$co_order->set_id_order($wc_order->get_id());
				$co_order->set_id_sender($co_sender->get_id());
				$co_order->set_reference($payload['order_form']['order_reference']);
				$co_order->set_shipping_number($result['exp_number']);
				$co_order->set_carrier_type($co_product->get_company());
				$co_order->set_date_add(gmdate('Y-m-d H:i:s'));
				$co_order->set_id_product($co_product->get_id());
				$co_order->set_id_carrier(0);
				$co_order->set_bultos($payload['bultos']);
				$co_order->set_last_status('Prerregistrado');
				$co_order->set_status('Grabado');
				$co_order->set_updated_at(gmdate('Y-m-d H:i:s'));
				$co_order->set_require_customs_doc($payload['require_customs_doc']);
				$co_order->set_AT_code($payload['order_form']['AT_code']);

				if (!empty ($payload['added_values']) ) {
					$co_order->set_added_values_cash_on_delivery(isset($payload['added_values']['cash_on_delivery']) && $payload['added_values']['cash_on_delivery'] == 'true' ? 1 : 0);
					$co_order->set_added_values_insurance(isset($payload['added_values']['insurance']) && $payload['added_values']['insurance'] == 'true' ? 1 : 0);
					$co_order->set_added_values_partial_delivery(isset($payload['added_values']['partial_delivery']) && $payload['added_values']['partial_delivery'] == 'true' ? 1 : 0);
					$co_order->set_added_values_delivery_saturday(isset($payload['added_values']['delivery_saturday']) && $payload['added_values']['delivery_saturday'] == 'true' ? 1 : 0);
					$co_order->set_added_values_cash_on_delivery_iban(isset($payload['added_values']['cash_on_delivery']) && $payload['added_values']['cash_on_delivery'] == 'true' ? $payload['added_values']['cash_on_delivery_iban'] : null);
					$co_order->set_added_values_cash_on_delivery_value(isset($payload['added_values']['cash_on_delivery']) && $payload['added_values']['cash_on_delivery'] == 'true' ? $payload['added_values']['cash_on_delivery_value'] : null);
					$co_order->set_added_values_insurance_value(isset($payload['added_values']['insurance']) && $payload['added_values']['insurance'] == 'true' ? $payload['added_values']['insurance_value'] : null);
				} else {
					$co_order->set_added_values_cash_on_delivery(0);
					$co_order->set_added_values_insurance(0);
					$co_order->set_added_values_partial_delivery(0);
					$co_order->set_added_values_delivery_saturday(0);
				}
				
				$co_order->create();

				// Crear bultos
				$bultos_reg = array();
				foreach ($result['bultos'] as $bulto) {
					$infoBulto = $payload['info_bulto'][$bulto['numBulto']];
					$co_saved_order = new CorreosOficialSavedOrder();
					$co_saved_order->set_id_order($payload['order_id']);
					$co_saved_order->set_shipping_number($bulto['shipping_number']);
					$co_saved_order->set_exp_number($result['exp_number']);
					$co_saved_order->set_height($infoBulto['height']);
					$co_saved_order->set_width($infoBulto['width']);
					$co_saved_order->set_large($infoBulto['large']);
					$co_saved_order->set_weight(!empty($infoBulto['weight']) ? $infoBulto['weight'] : ( new CorreosOficialConfig('WeightByDefault') )->get_value());
					$co_saved_order->set_reference($infoBulto['reference']);
					$co_saved_order->set_observations($infoBulto['observations']);
					$co_saved_order->create();

					// Info de bultos para el front
					$bultos_reg[] = array(
						'package_number' => $bulto['numBulto'],
						'shipping_number' => $bulto['shipping_number'],
					);
				}

				// Recogidas
				if (isset($result['pickup']['codRecogida'])) {

					if ($payload['company'] == 'Correos') {
						$packetSize = intval($payload['packetSize']);
						$needPrintLablPickup = $payload['needPrintLablPickup'];
					} else {
						$packetSize = 0;
						$needPrintLablPickup = 'N';
					}

					$co_order->set_pickup_number(mb_convert_encoding($result['pickup']['codRecogida'], 'UTF-8'));
					$co_order->set_pickup_date(isset($result['pickup']['dataRegister']) ? $result['pickup']['dataRegister'] : $payload['pickupDateRegister']);
					$co_order->set_pickup_from_hour(isset($result['pickup']['fromRegister']) ? $result['pickup']['fromRegister'] : $payload['pickupFromRegister']);
					$co_order->set_pickup_to_hour(isset($result['pickup']['toRegister']) ? $result['pickup']['toRegister'] : $payload['pickupToRegister']);
					$co_order->set_package_size($packetSize);
					$co_order->set_print_label($needPrintLablPickup);
					$co_order->set_pickup_status('Grabado');
					$co_order->set_pickup(1);
					$co_order->save();
				}

				if ( !empty($payload['delivery_mode']) ) { 
					$delivery_mode = trim($payload['delivery_mode']);
				} else {
					$delivery_mode = '';
				}

				// Detectar si el pedido originalmente era con punto de entrega
				$original_was_pickup_point = false;
				if ($orderRequest = (new CorreosOficialRequests())->getRequestByCartId($co_order->get_id_order())) {
					$original_was_pickup_point = true;
				}

				// Actualizar Datos Oficina/CityPaq/PudoCEX
				$address_updated = false;
				if ( !empty($payload['request_data']) && $payload['request_data'] && $payload['reference_code'] && ( $delivery_mode == 'office' || $delivery_mode == 'citypaq' || $delivery_mode == 'pudocex' ) ) {
						$cart_hash = (new CorreosOficialRequests())->getCartHashFromWooTable($payload['order_id']);

					if ($orderRequest = (new CorreosOficialRequests())->getRequestByCartId($co_order->get_id_order())) {
						$co_request = new CorreosOficialRequests($orderRequest['id_order']);
						$co_request->set_data(json_encode($payload['request_data']));
						$co_request->set_reference_code($payload['reference_code']);
						$co_request->save();
					} else {
						$cart_hash = (new CorreosOficialRequests())->getCartHashFromWooTable($payload['order_id']);
						$co_request = new CorreosOficialRequests();
						$co_request->set_id_order($co_order->get_id_order());
						$co_request->set_id_cart($cart_hash);
						$co_request->set_data(json_encode($payload['request_data']));
						$co_request->set_reference_code($payload['reference_code']);
						$co_request->set_date(gmdate('Y-m-d H:i:s'));
						$co_request->create();
					}

					// Guardar la dirección de envío actual antes de modificarla (para poder restaurarla al cancelar)
					if (!$wc_order->get_meta('_correosoficial_original_shipping_saved', true)) {
						$wc_order->update_meta_data('_correosoficial_original_shipping_first_name', $wc_order->get_shipping_first_name());
						$wc_order->update_meta_data('_correosoficial_original_shipping_last_name', $wc_order->get_shipping_last_name());
						$wc_order->update_meta_data('_correosoficial_original_shipping_company', $wc_order->get_shipping_company());
						$wc_order->update_meta_data('_correosoficial_original_shipping_address_1', $wc_order->get_shipping_address_1());
						$wc_order->update_meta_data('_correosoficial_original_shipping_address_2', $wc_order->get_shipping_address_2());
						$wc_order->update_meta_data('_correosoficial_original_shipping_city', $wc_order->get_shipping_city());
						$wc_order->update_meta_data('_correosoficial_original_shipping_state', $wc_order->get_shipping_state());
						$wc_order->update_meta_data('_correosoficial_original_shipping_postcode', $wc_order->get_shipping_postcode());
						$wc_order->update_meta_data('_correosoficial_original_shipping_country', $wc_order->get_shipping_country());
						$wc_order->update_meta_data('_correosoficial_original_shipping_phone', $wc_order->get_shipping_phone());
						// Marcar que ya se guardó la dirección original para no sobrescribirla en siguientes pre-registros
						$wc_order->update_meta_data('_correosoficial_original_shipping_saved', '1');
						// Guardar también si la dirección original era de un punto de entrega (para saber qué restaurar al cancelar)
						$wc_order->update_meta_data('_correosoficial_original_was_pickup', $delivery_mode);
				}

				// Actualizar dirección de envío con datos del punto de entrega
				$request_data = $payload['request_data'];
					$pickup_country = 'ES'; // Por defecto España

					if ($delivery_mode == 'office') {
						// Datos de oficina - las claves correctas son: unitName, address, municipalityName, postalCode
						$pickup_name = isset($request_data['unitName']) ? $request_data['unitName'] : '';
						$pickup_address = isset($request_data['address']) ? $request_data['address'] : '';
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : '';
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : '';
					} elseif ($delivery_mode == 'citypaq') {
						// Datos de CityPaq
						$pickup_name = isset($request_data['terminalName']) ? $request_data['terminalName'] : '';
						if (empty($pickup_name) && isset($request_data['unitName'])) {
							$pickup_name = $request_data['unitName'];
						}
						$pickup_address = isset($request_data['location']) ? $request_data['location'] : 
						                  (isset($request_data['address']) ? $request_data['address'] : '');
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : 
						               (isset($request_data['locality']) ? $request_data['locality'] : '');
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : '';
					} elseif ($delivery_mode == 'pudocex') {
						// Datos de PudoCEX - mismo formato que oficina
						$pickup_name = isset($request_data['unitName']) ? $request_data['unitName'] : 
						               (isset($request_data['name']) ? $request_data['name'] : '');
						$pickup_address = isset($request_data['address']) ? $request_data['address'] : '';
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : 
						               (isset($request_data['city']) ? $request_data['city'] : '');
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : 
						                   (isset($request_data['zipCode']) ? $request_data['zipCode'] : '');
					}

					// Solo actualizar si tenemos datos válidos
					if (!empty($pickup_address) || !empty($pickup_city) || !empty($pickup_postcode)) {
						$wc_order->set_shipping_company($pickup_name);
						$wc_order->set_shipping_address_1($pickup_address);
						$wc_order->set_shipping_address_2('');
						$wc_order->set_shipping_city($pickup_city);
						$wc_order->set_shipping_postcode($pickup_postcode);
						$wc_order->set_shipping_country($pickup_country);
						// Mantener nombre del cliente
						if (empty($wc_order->get_shipping_first_name())) {
							$wc_order->set_shipping_first_name($wc_order->get_billing_first_name());
							$wc_order->set_shipping_last_name($wc_order->get_billing_last_name());
						}
						$address_updated = true;
					}
				}

				// Si el pedido era con punto de entrega y ahora es a domicilio, actualizar dirección de envío
				if ($original_was_pickup_point && $delivery_mode != 'office' && $delivery_mode != 'citypaq' && $delivery_mode != 'pudocex') {
					// Guardar la dirección actual (del punto de entrega) antes de modificarla
					if (!$wc_order->get_meta('_correosoficial_original_shipping_saved', true)) {
						$wc_order->update_meta_data('_correosoficial_original_shipping_first_name', $wc_order->get_shipping_first_name());
						$wc_order->update_meta_data('_correosoficial_original_shipping_last_name', $wc_order->get_shipping_last_name());
						$wc_order->update_meta_data('_correosoficial_original_shipping_company', $wc_order->get_shipping_company());
						$wc_order->update_meta_data('_correosoficial_original_shipping_address_1', $wc_order->get_shipping_address_1());
						$wc_order->update_meta_data('_correosoficial_original_shipping_address_2', $wc_order->get_shipping_address_2());
						$wc_order->update_meta_data('_correosoficial_original_shipping_city', $wc_order->get_shipping_city());
						$wc_order->update_meta_data('_correosoficial_original_shipping_state', $wc_order->get_shipping_state());
						$wc_order->update_meta_data('_correosoficial_original_shipping_postcode', $wc_order->get_shipping_postcode());
						$wc_order->update_meta_data('_correosoficial_original_shipping_country', $wc_order->get_shipping_country());
						$wc_order->update_meta_data('_correosoficial_original_shipping_phone', $wc_order->get_shipping_phone());
						// Marcar que era un pickup point originalmente (usamos el tipo específico si está disponible)
						$original_type = $original_was_pickup_point; // Puede ser office, citypaq, pudocex
						$wc_order->update_meta_data('_correosoficial_original_was_pickup', $original_type);
						$wc_order->update_meta_data('_correosoficial_original_shipping_saved', '1');
					}
					
					// Copiar dirección de facturación a dirección de envío
					$wc_order->set_shipping_first_name($wc_order->get_billing_first_name());
					$wc_order->set_shipping_last_name($wc_order->get_billing_last_name());
					$wc_order->set_shipping_company($wc_order->get_billing_company());
					$wc_order->set_shipping_address_1($wc_order->get_billing_address_1());
					$wc_order->set_shipping_address_2($wc_order->get_billing_address_2());
					$wc_order->set_shipping_city($wc_order->get_billing_city());
					$wc_order->set_shipping_state($wc_order->get_billing_state());
					$wc_order->set_shipping_postcode($wc_order->get_billing_postcode());
					$wc_order->set_shipping_country($wc_order->get_billing_country());
					$wc_order->set_shipping_phone($wc_order->get_billing_phone());
					$address_updated = true;
				}

				$current_status = $this->getStatus('ShipmentPreregistered');

				if ($current_status !== false) {
					$wc_order->set_status($current_status);
				}
				
				// Guardar siempre si se actualizó la dirección o el estado
				if ($address_updated || $current_status !== false) {
					$wc_order->save();
				}

				// Finalizar y return
				$results[] = array(
					'codigoRetorno' => $result['codigoRetorno'],
					'mensajeRetorno' => $result['mensajeRetorno'],
					'codigoRetornoPick' => isset($result['pickup']['codigoRetorno']) ? $result['pickup']['codigoRetorno'] : '',
					'mensajeRetornoPick' => isset($result['pickup']['mensajeRetorno']) ? $result['pickup']['mensajeRetorno'] : '',
					'bultos' => $bultos_reg,
					'exp_number' => $co_saved_order->get_exp_number(),
					'cod_pickup' => $co_order->get_pickup_number(),
					'date_pickup' => $co_order->get_pickup_date(),
					'from_pickup' => $co_order->get_pickup_from_hour(),
					'to_pickup' => $co_order->get_pickup_to_hour(),
					'changeStatus' => $current_status,
					'orderId' => $co_order->get_id_order(),
					'reference' => $payload['order_form']['order_reference'],
					'order_data' => $co_order->get_data(),
				);

			} else {
				$this->accountNotFound['orderId'] = 'ERROR:';
				$results[] = $this->accountNotFound;
			}
		}

		// Devolver según origen de la petición
		return $origin == 'order' ? $results[0] : $results;
	}

	/* *********************************************************************************************************
	* CANCELAR ENVÍO
	********************************************************************************************************* */
	public function cancelarEnvio( $payload ) {

		if ($this->checkOutputApi($payload)) {
			// Añadimos info del pedido al payload
			if ($payload['type'] == 'return') {
				$co_return   = new CorreosOficialReturn($payload['order_id']);

				$payload['product_id'] = $co_return->get_id_product();
				$payload['pickup_number'] = $co_return->get_pickup_number();
			} else {
				$co_order   = new CorreosOficialOrder($payload['order_id']);

				$payload['product_id'] = $co_order->get_id_product();
				$payload['pickup_number'] = $co_order->get_pickup_number();
			}

			// Resultado de las llamadas
			$result = array();

			switch ($this->outputApi) {
				case API_P3:
					// CANCELA ENVIO POR BULTO
					if ($payload['type'] == 'return') {
						foreach ($co_return->get_bultos() as $bulto) {
							$payload['bulto'] = $bulto;
							$result[] = ( new CorreosOficialRest() )->cancelarEnvio($payload);
						}
					} else {
						foreach ($co_order->get_bultos() as $bulto) {
							$payload['bulto'] = $bulto;
							$result[] = ( new CorreosOficialRest() )->cancelarEnvio($payload);
						}
					}
					break;
				case API_LEGACY:
					// CANCELA ENVIO POR BULTO

					if ($payload['type'] == 'return') {
						foreach ($co_return->get_bultos() as $bulto) {
							$payload['bulto'] = $bulto;
							$result[] = ( new CorreosOficialSoap() )->cancelarEnvio($payload);
						}
					} else {
						foreach ($co_order->get_bultos() as $bulto) {
							$payload['bulto'] = $bulto;
							$result[] = ( new CorreosOficialSoap() )->cancelarEnvio($payload);
						}
					}
					break;
				case API_CEX:
					// CANCELA ENVIO COMPLETO
					$result = ( new CorreosOficialCEXRest() )->cancelarEnvio($payload);

					break;

				default:
					return false;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */

			$this->normalizedErrorReturn($result);

			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */

			// Borramos la información de los bultos y el pedido
			if ($payload['type'] == 'return') {
				if (( new CorreosOficialSavedReturnDataStore() )->delete($payload['order_id'])) {
					( new CorreosOficialReturn($payload['order_id']) )->delete();
					$wc_order = wc_get_order($co_return->get_id_order());
				}
			} elseif (( new CorreosOficialSavedOrderDataStore() )->delete($payload['order_id'])) {
				( new CorreosOficialOrder($payload['order_id']) )->delete();
				$wc_order = wc_get_order($co_order->get_id_order());
			}

			// Determinar si el producto era de punto de entrega antes de borrar
			$pickupPointTypes = array('citypaq', 'office', 'pudocex');
			$isPickupPoint = false;
			if (isset($co_order)) {
				$co_product = new CorreosOficialProduct($co_order->get_id_product());
				$isPickupPoint = in_array($co_product->get_product_type(), $pickupPointTypes, true);
			}

			// RESTAURAR DIRECCIÓN DE ENVÍO AL CANCELAR PRE-REGISTRO
			if (isset($wc_order) && $wc_order) {
				$this->restoreShippingAddressOnCancel($wc_order);
				$wc_order->save();
			}
			
			$current_status = $this->getStatus('ShipmentCanceled');

			if ($current_status !== false) {
				$wc_order->set_status($current_status);
				$wc_order->save();
			}

			// Mensaje según tipo de producto
			if ($isPickupPoint) {
				$cancel_message = __('Delivery address reverted to original pickup point address, reload the page to see the changes', 'correosoficial');
			} else {
				$cancel_message = __('Your shipping request has been canceled', 'correosoficial');
			}
			
			// FINALIZAR Y RETURN
			return array(
				'codigoRetorno'      => 0,
				'mensajeRetorno'     => $cancel_message,
				'changeStatus'       => $this->getStatus('ShipmentCanceled'),
				'isPickupPoint'      => $isPickupPoint,
			);
		} else {
			return $this->accountNotFound;
		}
	}

	/**
	 * Restaura la dirección de envío original al cancelar un pre-registro
	 * 
	 * Lógica de restauración:
	 * - Si originalmente era pickup point: restaurar desde tabla requests (que se mantiene al cancelar)
	 * - Si originalmente NO era pickup point: restaurar desde meta_data original (dirección de facturación o shipping original)
	 * 
	 * @param WC_Order $wc_order
	 * @return void
	 */
	private function restoreShippingAddressOnCancel($wc_order) {
		if (!$wc_order) {
			return;
		}

		// Verificar si tenemos dirección original guardada
		$original_saved = $wc_order->get_meta('_correosoficial_original_shipping_saved', true);
		if (!$original_saved) {
			// No hay dirección original guardada, no hacer nada
			return;
		}

		// Obtener el tipo de dirección original (office, citypaq, pudocex, o vacío si era domicilio)
		$original_was_pickup = $wc_order->get_meta('_correosoficial_original_was_pickup', true);

		// ESCENARIO 1 y 2: Si originalmente era un punto de entrega
		if (!empty($original_was_pickup) && in_array($original_was_pickup, array('office', 'citypaq', 'pudocex'))) {
			// Restaurar desde la tabla requests (que NO se borra al cancelar)
			$orderRequest = (new CorreosOficialRequests())->getRequestByCartId($wc_order->get_id());
			
			if ($orderRequest && !empty($orderRequest['data'])) {
				// Tenemos datos del punto de entrega original en requests
				$request_data = json_decode($orderRequest['data'], true);
				
				if (!empty($request_data)) {
					$pickup_name = '';
					$pickup_address = '';
					$pickup_city = '';
					$pickup_postcode = '';
					$pickup_country = 'ES';

					if ($original_was_pickup == 'office') {
						$pickup_name = isset($request_data['unitName']) ? $request_data['unitName'] : '';
						$pickup_address = isset($request_data['address']) ? $request_data['address'] : '';
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : '';
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : '';
					} elseif ($original_was_pickup == 'citypaq') {
						$pickup_name = isset($request_data['terminalName']) ? $request_data['terminalName'] : 
						               (isset($request_data['unitName']) ? $request_data['unitName'] : '');
						$pickup_address = isset($request_data['location']) ? $request_data['location'] : 
						                  (isset($request_data['address']) ? $request_data['address'] : '');
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : 
						               (isset($request_data['locality']) ? $request_data['locality'] : '');
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : '';
					} elseif ($original_was_pickup == 'pudocex') {
						$pickup_name = isset($request_data['unitName']) ? $request_data['unitName'] : 
						               (isset($request_data['name']) ? $request_data['name'] : '');
						$pickup_address = isset($request_data['address']) ? $request_data['address'] : '';
						$pickup_city = isset($request_data['municipalityName']) ? $request_data['municipalityName'] : 
						               (isset($request_data['city']) ? $request_data['city'] : '');
						$pickup_postcode = isset($request_data['postalCode']) ? $request_data['postalCode'] : 
						                   (isset($request_data['zipCode']) ? $request_data['zipCode'] : '');
					}

					// Restaurar con datos del punto de entrega original
					if (!empty($pickup_address) || !empty($pickup_city) || !empty($pickup_postcode)) {
						$wc_order->set_shipping_company($pickup_name);
						$wc_order->set_shipping_address_1($pickup_address);
						$wc_order->set_shipping_address_2('');
						$wc_order->set_shipping_city($pickup_city);
						$wc_order->set_shipping_postcode($pickup_postcode);
						$wc_order->set_shipping_country($pickup_country);
						// Mantener nombre del cliente
						if (empty($wc_order->get_shipping_first_name())) {
							$wc_order->set_shipping_first_name($wc_order->get_billing_first_name());
							$wc_order->set_shipping_last_name($wc_order->get_billing_last_name());
						}
						// Limpiar meta_data después de restaurar
						$wc_order->delete_meta_data('_correosoficial_original_shipping_saved');
						$wc_order->delete_meta_data('_correosoficial_original_was_pickup');
						return;
					}
				}
			}
			
		} else {
			// ESCENARIO 3 y 4: Originalmente NO era punto de entrega, restaurar desde meta_data
			// (que contendrá la dirección de facturación o la dirección original del pedido)
			$this->restoreFromMetaData($wc_order);
			return;
		}

		// Si llegamos aquí, no pudimos restaurar - limpiar meta_data de todos modos
		$wc_order->delete_meta_data('_correosoficial_original_shipping_saved');
		$wc_order->delete_meta_data('_correosoficial_original_was_pickup');
	}

	/**
	 * Restaura la dirección de envío desde los meta_data guardados
	 * 
	 * @param WC_Order $wc_order
	 * @return void
	 */
	private function restoreFromMetaData($wc_order) {
		$wc_order->set_shipping_first_name($wc_order->get_meta('_correosoficial_original_shipping_first_name', true));
		$wc_order->set_shipping_last_name($wc_order->get_meta('_correosoficial_original_shipping_last_name', true));
		$wc_order->set_shipping_company($wc_order->get_meta('_correosoficial_original_shipping_company', true));
		$wc_order->set_shipping_address_1($wc_order->get_meta('_correosoficial_original_shipping_address_1', true));
		$wc_order->set_shipping_address_2($wc_order->get_meta('_correosoficial_original_shipping_address_2', true));
		$wc_order->set_shipping_city($wc_order->get_meta('_correosoficial_original_shipping_city', true));
		$wc_order->set_shipping_state($wc_order->get_meta('_correosoficial_original_shipping_state', true));
		$wc_order->set_shipping_postcode($wc_order->get_meta('_correosoficial_original_shipping_postcode', true));
		$wc_order->set_shipping_country($wc_order->get_meta('_correosoficial_original_shipping_country', true));
		$wc_order->set_shipping_phone($wc_order->get_meta('_correosoficial_original_shipping_phone', true));

		// Limpiar meta_data de dirección original
		$wc_order->delete_meta_data('_correosoficial_original_shipping_first_name');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_last_name');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_company');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_address_1');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_address_2');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_city');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_state');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_postcode');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_country');
		$wc_order->delete_meta_data('_correosoficial_original_shipping_phone');
	}

	/* *********************************************************************************************************
	* REGISTRAR RECOGIDA
	********************************************************************************************************* */
	public function registrarRecogida( $payload, $origin = 'order' ) {

		// Indexamos el payload si viene de la página de pedido
		$payloads = $origin == 'order' ? array( $payload ) : $payload;

		foreach ($payloads as $payload) {
			
			if ($this->checkOutputApi($payload)) {
				$co_return = '';
				$co_order  = '';
				$wc_order = wc_get_order($payload['order_id']);
				
				// Para impresión de etiquetas
				if (isset($payload['mode_pickup']) && $payload['mode_pickup'] == 'RETURN') {
					$co_product  = new CorreosOficialProduct($payload['product_id']);
					$co_return = new CorreosOficialReturn($payload['order_id']);

					foreach ($co_return->get_bultos() as $bulto) {
						$payload['shipping_numbers'][] = $bulto->get_shipping_number();
					}

					$payload['pickup_address_data'] = array(
						'province'        => str_split( $wc_order->get_billing_postcode(), 2 )[0],
						'contactName'     => $wc_order->get_billing_first_name(),
						'lastNameContact' => $wc_order->get_billing_last_name(),
					);
				} else {
					$co_order = new CorreosOficialOrder($payload['order_id']);

					$payload['reference'] = $co_order->get_reference();

					foreach ($co_order->get_bultos() as $bulto) {
						$payload['shipping_numbers'][] = $bulto->get_shipping_number();
					}

					if ($payload['sender_id']) {
						$sender = new CorreosOficialSender($co_order->get_id_sender());
						$payload['sender_id'] = $sender->get_id();
						$payload['order_form'] = array(
							'sender_address' => $sender->get_sender_address(),
							'sender_city'    => $sender->get_sender_city(),
							'sender_cp'      => $sender->get_sender_cp(),
							'sender_name'    => $sender->get_sender_name(),
							'sender_contact' => $sender->get_sender_contact(),
							'sender_phone'   => $sender->get_sender_phone(),
							'sender_email'   => $sender->get_sender_email(),
							'sender_nif_cif' => $sender->get_sender_nif_cif(),
						);
					}
					
					// Product 
					if ( isset($payload['product_id']) && !is_null($payload['product_id']) ) {
						$co_product  = new CorreosOficialProduct($payload['product_id']);
					} else {
						$co_product  = new CorreosOficialProduct($co_order->get_id_product());
					}

					// Se usa para recogidas
					$payload['pickup_address_data'] = array(
						'province'        => str_split( $payload['order_form']['sender_cp'], 2 )[0],
						'contactName'     => $payload['order_form']['sender_name'],
						'lastNameContact' => !empty($payload['order_form']['sender_contact']) ? $payload['order_form']['sender_contact'] : '-',
					);
				}

				$payload['product'] = $co_product->get_data();

				// Resultado de las llamadas
				$result = array();

				switch ($this->outputApi) {
					case API_P3:
						// REGISTRO DE RECOGIDA
						$result = ( new CorreosOficialRest() )->registrarRecogida($payload);

						break;

					case API_LEGACY:
						// REGISTRO DE RECOGIDA
						$result = ( new CorreosOficialSoap() )->registrarRecogida($payload);

						break;

					case API_CEX:
						// Para CEX la recogida solo se registra durante el pre-registro
						break;

					default:
						return false;
				}

				/* *********************************************************************************************************
				* RESULTADO KO
				********************************************************************************************************* */

				// Consideramos éxito exclusivamente cuando codigoRetorno == 0.
				$batchError = $origin != 'order' && isset($result[0]['codigoRetorno']) && intval($result[0]['codigoRetorno']) !== 0;
				$singleError = isset($result['codigoRetorno']) && intval($result['codigoRetorno']) !== 0;

				if ($batchError || $singleError) {
					// En batch acumulamos subresultados de error y continuamos con siguiente payload
					if ($origin != 'order' && is_array($result)) {
						foreach ($result as $subresult) {
							$results[] = $subresult;
						}
						continue;
					}

					// En petición individual devolvemos error estandarizado (termina la ejecución)
					$this->normalizedErrorReturn($result);
				}

				/* *********************************************************************************************************
				* RESULTADO OK
				********************************************************************************************************* */

				// RECOGIDAS ------------------------------------------------------------------------------------------ //
				$pickup_number = '';

				if ($payload['company'] == 'Correos') {
					$packetSize = intval($payload['packetSize']);
					$needPrintLablPickup = $payload['needPrintLablPickup'];
				} else {
					$packetSize = 0;
					$needPrintLablPickup = 'N';
				}

				if (isset($payload['mode_pickup']) && $payload['mode_pickup'] == 'RETURN') {
					if (isset($result['codRecogida'])) {
						$co_return->set_pickup_number(mb_convert_encoding($result['codRecogida'], 'UTF-8'));
						$co_return->set_pickup_date(isset($result['pickup']['dataRegister']) ? $result['pickup']['dataRegister'] : $payload['pickupDateRegister']);
						$co_return->set_pickup_from_hour(isset($result['pickup']['fromRegister']) ? $result['pickup']['fromRegister'] : $payload['pickupFromRegister']);
						$co_return->set_pickup_to_hour(isset($result['pickup']['toRegister']) ? $result['pickup']['toRegister'] : $payload['pickupToRegister']);
						$co_return->set_package_size($packetSize);
						$co_return->set_print_label($needPrintLablPickup);
						$co_return->set_pickup_status('Grabado');
						$co_return->set_pickup(1);
						$co_return->save();

						$pickup_number = $co_return->get_pickup_number();
						$pickup_date = $co_return->get_pickup_date();
						$pickup_from_hour = $co_return->get_pickup_from_hour();
						$pickup_to_hour = $co_return->get_pickup_to_hour();
					}
				} elseif (isset($result['codRecogida'])) {
						$co_order->set_pickup_number(mb_convert_encoding($result['codRecogida'], 'UTF-8'));
						$co_order->set_pickup_date(isset($result['dataRegister']) ? $result['dataRegister'] : $payload['pickupDateRegister']);
						$co_order->set_pickup_from_hour(isset($result['fromRegister']) ? $result['fromRegister'] : $payload['pickupFromRegister']);
						$co_order->set_pickup_to_hour(isset($result['toRegister']) ? $result['toRegister'] : $payload['pickupToRegister']);
						$co_order->set_package_size($packetSize);
						$co_order->set_print_label($needPrintLablPickup);
						$co_order->set_pickup_status('Grabado');
						$co_order->set_pickup(1);
						$co_order->save();

						$pickup_number = $co_order->get_pickup_number();
						$pickup_date = $co_order->get_pickup_date();
						$pickup_from_hour = $co_order->get_pickup_from_hour();
						$pickup_to_hour = $co_order->get_pickup_to_hour();
				}

				$results[] = array(
					'codigoRetorno'      => $result['codigoRetorno'],
					'mensajeRetorno'     => $result['mensajeRetorno'],
					'cod_pickup'         => $pickup_number,
					'date_pickup'        => $pickup_date,
					'from_pickup'        => $pickup_from_hour,
					'to_pickup'          => $pickup_to_hour,
				);
			} else {
				$results[] = $this->accountNotFound;
			}
		}

		// FINALIZAR Y RETURN
		return $origin == 'order' ? $results[0] : $results;
	}

	public function cancelarRecogida( $payload ) {

		if ($this->checkOutputApi($payload)) {
			// Instanciamos los modelos necesarios
			if ($payload['mode_pickup'] == 'RETURN') {
				$co_return = new CorreosOficialReturn($payload['order_id']);
				$payload['pickup_number_return'] = $co_return->get_pickup_number();
				$payload['order_reference'] = $co_return->get_reference();
			} else {
				$co_order = new CorreosOficialOrder($payload['order_id']);
				$payload['pickup_number'] = $co_order->get_pickup_number();
				$payload['order_reference'] = $co_order->get_reference();
			}

			switch ($this->outputApi) {
				case API_P3:
						$result = ( new CorreosOficialRest() )->cancelarRecogida($payload);
					break;
				case API_LEGACY:
						$result = ( new CorreosOficialSoap() )->cancelarRecogida($payload);
					break;
				case API_CEX:
					break;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */

			$this->normalizedErrorReturn($result);

			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */   
			if ($payload['mode_pickup'] == 'RETURN') {
				$co_return->set_pickup(0);
				$co_return->set_pickup_number('');
				$co_return->set_print_label('N');
				$co_return->set_pickup_status('Anulado');
				$co_return->save();
			} else {
				$co_order->set_pickup(0);
				$co_order->set_pickup_number('');
				$co_order->set_print_label('N');
				$co_order->set_pickup_status('Anulado');
				$co_order->save();
			}
	

			return $result;
		} else {
			return $this->accountNotFound;
		}
	}

	/* *********************************************************************************************************
	* REGISTRAR DEVOLUCION
	********************************************************************************************************* */
	public function generateReturn( $payload ) {
		if ($this->checkOutputApi($payload)) {
			// Instanciamos los modelos necesarios
			$co_sender   = new CorreosOficialSender($payload['sender_id']);
			$co_product  = new CorreosOficialProduct($payload['product_id']);
			$wc_order    = wc_get_order($payload['order_id']);

			$payload['bultos'] = $payload['order_form']['correos-num-parcels-return'];
			$payload['sender'] = $co_sender;
			$payload['product'] = $co_product;

			$payload['require_customs_doc'] = CorreosOficialNeedCustoms::isCustomsRequired(
				$co_sender->get_sender_cp(),
				$payload['order_form']['customer_cp'],
				$co_sender->get_sender_iso_code_pais(),
				$payload['order_form']['customer_country'],
				true
			);

			// Obtenemos todas las descripciones aduaneras de los bultos y las indexamos en payload
			$this->setCustomsDescArray($payload, 'order', $wc_order);

			/* *********************************************************************************************************
			* REGLAS DE NEGOCIO COMUNES
			********************************************************************************************************* */
			$activateDefault = (new CorreosOficialConfig('ActivateDimensionsByDefault'))->get_value() === 'on';
			$defaultHeight   = (new CorreosOficialConfig('DimensionsByDefaultHeight'))->get_value();
			$defaultWidth    = (new CorreosOficialConfig('DimensionsByDefaultWidth'))->get_value();
			$defaultLarge    = (new CorreosOficialConfig('DimensionsByDefaultLarge'))->get_value();

			$payload['info_bulto'] = [];

			for ($i = 1; $i <= $payload['bultos']; $i++) {
				$bulto = [
					'weight'    => $payload['order_form']["packageWeightReturn_$i"] ?? '',
					'height'    => $payload['order_form']["packageHeightReturn_$i"] ?? '',
					'width'     => $payload['order_form']["packageWidthReturn_$i"] ?? '',
					'large'     => $payload['order_form']["packageLargeReturn_$i"] ?? '',
					'reference' => $payload['order_reference']
				];

				if (
					$activateDefault &&
					empty($bulto['height']) &&
					empty($bulto['width']) &&
					empty($bulto['large'])
				){
					$bulto['height'] = $defaultHeight;
					$bulto['width']  = $defaultWidth;
					$bulto['large']  = $defaultLarge;
				}

				$payload['info_bulto'][$i] = $bulto;
			}

			switch ($this->outputApi) {
				case API_P3:
					$result = ( new CorreosOficialRest() )->generateReturn($payload);
					break;
				case API_LEGACY:
					$result = ( new CorreosOficialSoap() )->generateReturn($payload);
					break;
				case API_CEX:
					$result = ( new CorreosOficialCEXRest() )->generateReturn($payload);
					break;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */

			$this->normalizedErrorReturn($result);

			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */    

			// CREAR SAVED_ORDER EN CORREOSOFICIAL
			$co_return = new CorreosOficialReturn();

			$co_return->set_id_order($wc_order->get_id());
			$co_return->set_id_sender($co_sender->get_id());
			$co_return->set_reference($payload['order_form']['order_reference']);
			$co_return->set_shipping_number($result['exp_number']); // OJO!! se guarda el número de expedición bug heredado
			$co_return->set_carrier_type($payload['company']);
			$co_return->set_date_add(gmdate('Y-m-d H:i:s'));
			$co_return->set_id_product(0);
			$co_return->set_id_carrier(0);
			$co_return->set_bultos($payload['bultos']);
			$co_return->set_AT_code('');
			$co_return->set_last_status('Prerregistrado');
			$co_return->set_status('Grabado');
			$co_return->set_updated_at(gmdate('Y-m-d H:i:s'));
			$co_return->set_pickup(0);
			$co_return->set_pickup_status('None');
			$co_return->set_require_custom($payload['require_customs_doc']);
			$co_return->create();

			// CREAR BULTO
			$co_saved_return = new CorreosOficialSavedReturn();

			if (count($result['bultos']) > 1) {
				foreach ($result['bultos'] as $bulto) {
					$co_saved_return->set_id_order($wc_order->get_id());
					$co_saved_return->set_shipping_number($bulto['shipping_number']);
					$co_saved_return->set_exp_number($result['exp_number']);
					$co_saved_return->create();
				}
			} else {
				$co_saved_return->set_id_order($wc_order->get_id());
				$co_saved_return->set_shipping_number($result['bultos'][0]['shipping_number']);
				$co_saved_return->set_exp_number($result['exp_number']);
				$co_saved_return->create();
			}

			// Info de bultos para el front
			$bultos_reg[] = array(
				'package_number'  => $result['bultos'][0]['numBulto'],
				'shipping_number' => $result['bultos'][0]['shipping_number'],
			);

			if (isset($result['pickup']['codRecogida'])) {

				if ($payload['company'] == 'Correos') {
					$packetSize = intval($payload['packetSize']);
					$needPrintLablPickup = $payload['needPrintLablPickup'];
				} else {
					$packetSize = 0;
					$needPrintLablPickup = 'N';
				}

				$co_return->set_pickup_number(mb_convert_encoding($result['pickup']['codRecogida'], 'UTF-8'));
				$co_return->set_pickup_date(isset($result['pickup']['dataRegister']) ? $result['pickup']['dataRegister'] : $payload['order_form']['PickupDateRegister']);
				$co_return->set_pickup_from_hour(isset($result['pickup']['fromRegister']) ? $result['pickup']['fromRegister'] : $payload['order_form']['PickupFromRegister']);
				$co_return->set_pickup_to_hour(isset($result['pickup']['toRegister']) ? $result['pickup']['toRegister'] : $payload['order_form']['PickupToRegister']);
				$co_return->set_package_size($packetSize);
				$co_return->set_print_label($needPrintLablPickup);
				$co_return->set_pickup_status('Grabado');
				$co_return->set_pickup(1);
				$co_return->save();
			}

			$current_status = $this->getStatus('ShipmentReturned');

			if ($current_status !== false) {
				$wc_order->set_status($current_status);
				$wc_order->save();
			}

			// FINALIZAR Y RETURN
			$result[] = array(
				'codigoRetorno'      => $result['codigoRetorno'], // 0 Pre-Registro Devolucion OK, 1 Contiene algún Problema o Alerta
				'mensajeRetorno'     => $result['mensajeRetorno'],
				'codigoRetornoPick'  => isset($result['pickup']['codigoRetorno']) ? $result['pickup']['codigoRetorno'] : '', // 0 Recogida OK, 1 Contiene algún Problema o Alerta, vacío si no hay recogida
				'mensajeRetornoPick' => isset($result['pickup']['mensajeRetorno']) ? $result['pickup']['mensajeRetorno'] : '',
				'bultos'             => $bultos_reg,
				'exp_number'         => $co_saved_return->get_exp_number(),
				'expedition_number'  => $co_saved_return->get_exp_number(),
				'cod_pickup'         => $co_return->get_pickup_number(),
				'date_pickup'        => $co_return->get_pickup_date(),
				'from_pickup'        => $co_return->get_pickup_from_hour(),
				'to_pickup'          => $co_return->get_pickup_to_hour(),
				'changeStatus'       => $current_status,
				'pickup_return'      => 1,
			);
			return $result;
		} else {
			return $this->accountNotFound;
		}
	}

	/* *********************************************************************************************************
	* IMPRESIÓN ETIQUETAS
	********************************************************************************************************* */
	public function impresionEtiqueta( $payload, $origin = 'order' ) {
		// Indexamos el payload si viene de la página de pedido
		$payloads = $origin == 'order' ? array( $payload ) : $payload;

		// Array de etiquetas temporales
		$temp_labels = array();

		foreach ($payloads as $payload) {

			if ($this->checkOutputApi($payload)) {
				// Instanciamos los modelos necesarios
				if (isset($payload['delivery_mode']) && $payload['delivery_mode'] == 'RETURN') {
					$co_order = new CorreosOficialReturn($payload['order_id']);
					$co_sender = new CorreosOficialSender($payload['sender_id']);

					$payload['label_position'] = !empty($payload['label_position']) ? $payload['label_position'] : 2;
					$payload['exp_number'] = $co_order->get_shipping_number();
					$order_form = $payload['order_form'];
				} else {
					$co_order    = new CorreosOficialOrder($payload['order_id']);

					if ($payload['product_id'] == '') {
						$product = new CorreosOficialProduct($co_order->get_id_product());
						$payload['company'] = $product->get_company();
						$payload['product'] = $product->get_data();
					}
				}

				if ($payload['sender_id'] == '') {
					$payload['sender_id'] = $co_order->get_id_sender();
				}

				// Iniciamos Variables
				$pdf = new PDFMerger($payload['label_type'], $payload['label_format']);
				$temp_folder = dirname(MODULE_CORREOS_OFICIAL_PATH) . '/pdftmp';
				$pdf_output_file = $temp_folder . '/' . uniqid('labels_') . '.pdf';

				// Solo CEX, comprueba si hay un logo custom para la etiqueta
				$useUserLogo = ( new CorreosOficialConfig('ChangeLogoOnLabel') )->get_value();
				$getUserLogo = ( new CorreosOficialConfig('UploadLogoLabels') )->get_value();
				
				if ($useUserLogo == 'on') {
					$imagedata = file_get_contents($getUserLogo);
					$payload['label_custom_logo'] = base64_encode($imagedata);
				}

				// Resultado de las llamadas
				$result = array();

				switch ($this->outputApi) {
					case API_P3:
						foreach ($co_order->get_bultos() as $bulto) {
							$payload['shipments'][] = $bulto->get_shipping_number();
						}

						$result[] = ( new CorreosOficialRest() )->imprimirEtiqueta($payload);
						
						if (
								$payload['label_format'] == '0' &&
								isset($result[0]['labels']) && 
								is_array($result[0]['labels']) && 
								count($result[0]['labels']) > 0 &&
								count($co_order->get_bultos()) > 1
							) {
							// Generamos un pdf temporal con las etiquetas obtenidas
							$temp_path = $temp_folder . '/split_pages_' . $payload['exp_number'] . '.pdf';
							file_put_contents($temp_path, $result[0]['labels'][0]);

							$result[0]['labels'] = $pdf->splitPDFPages($temp_path);
							$result[0]['labels'] = array_map('base64_decode', $result[0]['labels']);
							unlink($temp_path);
						}
						
						break;
					case API_LEGACY:
						// OBTENER ETIQUETA DE PS2C
						foreach ($co_order->get_bultos() as $bulto) {
							$payload['bulto'] = $bulto;
							$result_label = ( new CorreosOficialSoap() )->imprimirEtiqueta($payload);
							$result[0]['codigoRetorno'] = $result_label['codigoRetorno'];
							$result[0]['mensajeRetorno'] = $result_label['mensajeRetorno'];
							$result[0]['labels'][] = $result_label['label'];

							// Si falla en alguna etiqueta, fallan todas
							if ($result_label['codigoRetorno'] > 0) {
								$result = array( $result_label );
								break;
							}
						}

						break;
					case API_CEX:
						$result = ( new CorreosOficialCEXRest() )->imprimirEtiqueta($payload);

						if (
							$payload['label_format'] == LABEL_FORMAT_3A4 &&
							count($result[0]['labels'])
						) {
							// Generamos un pdf temporal con las etiquetas obtenidas
							$temp_path = $temp_folder . '/split_labels_' . $payload['exp_number'] . '.pdf';
							file_put_contents($temp_path, $result[0]['labels'][0]);

							// Cortamos en pdf individuales
							$labels = $pdf->splitByFormat($temp_path, count($co_order->get_bultos()), LABEL_FORMAT_3A4, 3);

							// Añadimos las etiquetas al resultado
							$result[0]['labels'] = array_map('base64_decode', $labels);

							// Eliminamos el archivo temporal
							unlink($temp_path);
						}

						break;
					default:
						return false;
				}

				/* *********************************************************************************************************
				* RESULTADO KO
				********************************************************************************************************* */
				if($origin != 'order' && isset($result[0]) && $result[0]['codigoRetorno'] > 0) {
					foreach($result as $subresult){
						$results[] = $subresult;
					}
                	continue;
           		} else {
                	$this->normalizedErrorReturn($result);
            	}

				/* *********************************************************************************************************
				* RESULTADO OK
				********************************************************************************************************* */
				
				// Envío de email
				if (isset($payload['send_email']) && $payload['send_email']) {

					$labels = isset($result[0]['labels']) && is_array($result[0]['labels']) ? $result[0]['labels'] : [];

					if (empty($labels)) {
						$this->normalizedErrorReturn([[
							'codigoRetorno'  => 1,
							'mensajeRetorno' => __('No labels were returned by the carrier API.', 'correosoficial'),
						]]);
						return;
					}

					$counter = 0;
					foreach ($labels as $label_data) {
						$temp_path_pdf = $temp_folder . '/E_' . $payload['exp_number'] . '_' . $counter . '.pdf';
						file_put_contents($temp_path_pdf, $label_data);
						$pdf->addPDF($temp_path_pdf, 'all');
						$counter++;
					}

					switch ($payload['label_type']) {
						case LABEL_TYPE_ADHESIVE:
							$pdf->merge(
								'file',
								$pdf_output_file,
								$payload['label_type'],
								$payload['label_position']
							);
							break;
						default: // LABEL_TYPE_THERMAL
							$pdf->mergeTopages(
								'file',
								$pdf_output_file
							);
							break;
					}

					$label = base64_encode(file_get_contents($pdf_output_file));

					$payload['require_customs_doc'] = CorreosOficialNeedCustoms::isCustomsRequired(
						$co_sender->get_sender_cp(),
						$order_form['customer_cp'],
						$co_sender->get_sender_iso_code_pais(),
						$order_form['customer_country']
					);

					if ($payload['require_customs_doc']) {
						$payload['print_option'] = 'IMPRIMIRCN23BUTTON';
						$payload['cn23'] = $this->getDocAduanera($payload);
					}
					
					// Eliminar archivos temporales en la carpeta de pdftmp
					$files = glob(dirname(__DIR__) . '/pdftmp/' . '*.pdf');

					foreach ($files as $file) {
						if (is_file($file)) {
							unlink($file);
						}
					}

					$return_code_cex = '';
					if ( isset($payload['returns_code']) && is_array($payload['returns_code']) ) {
						foreach ( $payload['returns_code'] as $return_code ) {
							if ( ! empty($return_code) ) {
								$return_code_cex = $return_code;
								break;
							}
						}
					}

					if ( $return_code_cex === '' ) {
						$return_packages = $co_order->get_bultos();
						if ( ! empty($return_packages) && is_array($return_packages) ) {
							$first_package = $return_packages[0];
							$package_code = $first_package->get_shipping_number();
							if ( ! empty($package_code) ) {
								$return_code_cex = $package_code;
							}
						}
					}

					if ( $return_code_cex === '' ) {
						$return_code_cex = $co_order->get_shipping_number();
					}
				
					return array(
						'customer_email'     => $order_form['customer_email'],
						'sender_email'       => false,
						'label'              => $label,
						'cn23'               => isset($payload['cn23']) ? $payload['cn23'] : false,
						'company'            => $payload['company'],
						'shipping_number'    => $return_code_cex,
						'return_code_cex'    => $return_code_cex,
						'pickup_date'        => isset($order_form['return_pickup_date']) ? $order_form['return_pickup_date'] : '',
						'sender_from_time'   => isset($order_form['return_sender_from_time']) ? $order_form['return_sender_from_time'] : '',
						'order_id'           => $payload['order_id'],
						'shop_name'          => get_bloginfo('name'),
						'require_custom_doc' => $payload['require_customs_doc'],
						'sender_country'     => $order_form['customer_country'],   
					);
				}

				// Las etiquetas obtenidas del ws pasan como pdf a la carpeta pdftmp del modulo
				$counter = 0;
				foreach ($result[0]['labels'] as $label_data) {
					$temp_path_pdf = $temp_folder . '/E_' . $payload['exp_number'] . '_' . $counter . '.pdf';
					file_put_contents($temp_path_pdf, $label_data);
					$temp_labels[] = $temp_path_pdf;
					$counter++;
				}

			}
		}

		// Mergeamos todos los pdf de etiquetas en uno solo
		if (count($temp_labels) >= 1) {

			$pdfResult = new PDFMerger($payloads[0]['label_type'], $payloads[0]['label_format']);
			foreach ($temp_labels as $file) {
				$pdfResult->addPDF($file, 'all');
			}

			switch ($payloads[0]['label_type']) {
				case LABEL_TYPE_ADHESIVE:	
					$pdfResult->merge(
						'file',
						$pdf_output_file,
						$payloads[0]['label_type'],
						$payloads[0]['label_position']
					);
					break;
				default: //LABEL_TYPE_THERMAL
					$pdfResult->mergeTopages(
						'file',
						$pdf_output_file
					);
					break;
			}
		}

        $results[] = array(
            'codigoRetorno'   => 0,
            'mensajeRetorno'  => '',
            'filePath'        => [$pdf_output_file],
        );

		return $origin != 'order' ? $results : $results[0];

	}

	/* *********************************************************************************************************
	 * GET DOC ADUANERA
	 ********************************************************************************************************* */
	public function getDocAduanera( $payload, $origin = 'order' ) {
		
		// Indexamos el payload si viene de la página de pedido
		$payloads = $origin == 'order' ? array( $payload ) : $payload;

		foreach ($payloads as $payload) {

			// iniciamos variables
			$pdf = new PDFMerger(2, 0);
			$temp_folder = dirname(MODULE_CORREOS_OFICIAL_PATH) . '/pdftmp';
			$document_type = '';

			// Comprobamos Api de salida
			if ($this->checkOutputApi($payload)) {
				// Instanciamos los modelos necesarios
				if ((
					isset($payload['type']) && $payload['type'] == 'return') || (
					isset($payload['delivery_mode']) && $payload['delivery_mode'] == 'RETURN'
				)) {
					$co_return = new CorreosOficialReturn($payload['order_id']);
				} else {
					$co_order = new CorreosOficialOrder($payload['order_id']);
				}
				
				if ($payload['sender_id'] != '') {
					$co_sender   = new CorreosOficialSender($payload['sender_id']);
				} else {
					$is_return = (isset($payload['type']) && $payload['type'] == 'return') ||
					             (isset($payload['delivery_mode']) && $payload['delivery_mode'] == 'RETURN');
					$co_sender   = $is_return
						? new CorreosOficialSender($co_return->get_id_sender())
						: new CorreosOficialSender($co_order->get_id_sender());
				}
				
				if ($origin == 'utilities' && $payload['customer_iso']) {
					$payload['customer_country'] = $this->getCountryName($payload['customer_iso']);
				}
				
				// Resultado de las llamadas
				$result = array();

				switch ($payload['print_option']) {
					case 'IMPRIMIRCN23BUTTON':
					case 'IMPRIMIRCN23BUTTON-RETURN':
						$document_type = 'CN23';
						$payload['documentation_type'] = 2;
						break;

					case 'IMPRIMIRDUABUTTON':
						$document_type = 'DCAF';
						$payload['documentation_type'] = 5;
						break;

					case 'IMPRIMIRDDPBUTTON':
						$document_type = 'DDP';
						$payload['documentation_type'] = 6;
						break;
				}

				switch ($this->outputApi) {
					case API_P3:
						// Initialize shipments to avoid undefined variable if get_bultos() returns empty
						$payload['shipments'] = [];
						
						if ( (isset($payload['type']) && $payload['type'] == 'return') ||
						 (isset($payload['delivery_mode']) && $payload['delivery_mode'] == 'RETURN') ) {
							foreach ($co_return->get_bultos() as $bulto) {
								$payload['shipments'][] = $bulto->get_shipping_number();
							}

							$payload['shipment_numbers'] = count($co_return->get_bultos());
							$payload['sender_name']      = $co_sender->get_sender_name();
							$payload['sender_city']      = $co_sender->get_sender_city();
							$payload['sender_nif_cif']   = $co_sender->get_sender_nif_cif();
						} else {
							foreach ($co_order->get_bultos() as $bulto) {
								$payload['shipments'][] = $bulto->get_shipping_number();
							}

							$payload['shipment_numbers'] = count($co_order->get_bultos());
							$payload['sender_name']      = $co_sender->get_sender_name();
							$payload['sender_city']      = $co_sender->get_sender_city();
							$payload['sender_nif_cif']   = $co_sender->get_sender_nif_cif();
						}

						$result[] = ( new CorreosOficialRest() )->getDocAduanera($payload);

						break;
					case API_LEGACY:
						$payload['sender_name'] = $co_sender->get_sender_name();

						if ((
							isset($payload['type']) && $payload['type'] == 'return') || (
							isset($payload['delivery_mode']) && $payload['delivery_mode'] == 'RETURN'
						)) {
							$payload['total_buks'] = count($co_return->get_bultos());

							foreach ($co_return->get_bultos() as $bulto) {
								$payload['shipping_number'] = $bulto->get_shipping_number();
								$result_label = ( new CorreosOficialSoap() )->getDocAduanera($payload);
								$result[0]['codigoRetorno'] = $result_label['codigoRetorno'];
								$result[0]['mensajeRetorno'] = $result_label['mensajeRetorno'];
								$result[0]['labels'][] = $result_label['label'];

								// Si falla en alguna etiqueta, fallan todas
								if ($result_label['codigoRetorno'] > 0) {
									$result = array( $result_label );
									break;
								}
							}
						} else {
							$payload['total_buks'] = count($co_order->get_bultos());

							foreach ($co_order->get_bultos() as $bulto) {
								$payload['shipping_number'] = $bulto->get_shipping_number();
								$result_label = ( new CorreosOficialSoap() )->getDocAduanera($payload);
								$result[0]['codigoRetorno'] = $result_label['codigoRetorno'];
								$result[0]['mensajeRetorno'] = $result_label['mensajeRetorno'];
								$result[0]['labels'][] = $result_label['label'];

								// Si falla en alguna etiqueta, fallan todas
								if ($result_label['codigoRetorno'] > 0) {
									$result = array( $result_label );
									break;
								}
							}
						}
						
						break;
				}

				/* *********************************************************************************************************
				* RESULTADO KO
				********************************************************************************************************* */

				if ($origin != 'order' && $result[0]['codigoRetorno']) {
					foreach($result as $subresult) {
						$results[] = $subresult;
					}
					continue;
				} else {
					$this->normalizedErrorReturn($result);
				}
 
				/* *********************************************************************************************************
				* RESULTADO OK
				********************************************************************************************************* */
				if (isset($payload['send_email']) && $payload['send_email']) {
					$labels = array();
					foreach ($result[0]['labels'] as $label_data) {
						$labels[] = base64_encode($label_data);
					}

					return $labels;
				}
				
				$counter = 0;

				foreach ($result[0]['labels'] as $label_data) {
					if (!isset($payload['exp_number']) || empty($payload['exp_number'])) {
						$payload['exp_number'] = isset($co_return) ? $co_return->get_shipping_number() : (isset($co_order) ? $co_order->get_shipping_number() : '');
					}
					$temp_path_pdf = $temp_folder . '/' . $document_type . '_' . $payload['exp_number'] . '_' . $counter . '.pdf';
					file_put_contents($temp_path_pdf, $label_data);
					$pdf->addPDF($temp_path_pdf, 'all');
					$counter++;
				}

				$pdf_output_file = $temp_folder . '/' . $document_type . '_' . uniqid() . '.pdf';

				$pdf->mergeTopages(
					'file',
					$pdf_output_file
				);

				if ($origin == 'utilities') {
					$files[] = $pdf_output_file;
				}
			}
		}
		
        $results[] = array(
            'codigoRetorno'   => 0,
            'mensajeRetorno'  => '',
            'filePath'        => empty($files) ? $pdf_output_file : $files,
        );
 
        if($origin != 'order') {
            return $results;
        }
 
        return $results[0];
	}

	/* *********************************************************************************************************
	 * GET ORDER STATUS
	 ********************************************************************************************************* */
	public function getOrderStatus( $payload ) {
		// Comprobamos la API de salida
		if ($this->checkOutputApi($payload)) {
			// Inicializamos variables necesarias
			$co_order    = new CorreosOficialOrder($payload['order_id']);
			$co_product  = new CorreosOficialProduct($co_order->get_id_product());

			// Estado por defecto "en espera de datos"
			$last_status = array(
				array(
					'codEnvio'        => '',
					'codProducto'     => '',
					'desTextoResumen' => 'En espera de datos',
					'fecEvento'       => '',
					'horEvento'       => '',
					'unidad'          => '',
				),
			);

			$result = array();
			$result['codigoRetorno'] = 0;
			$result['mensajeRetorno'] = '';

			switch ($this->outputApi) {
				case API_P3:
					$sga_active = (new CorreosOficialConfig('ActivateSGA') )->get_value();
					if ($sga_active  == 'on' && !CorreosOficialOrder::exists($payload['order_id']) ) {
						$sga_order = new CorreosOficialSgaOrdersStatus($payload['order_id']);
						$payload['shipping_number'] = $sga_order->get_shipping_number();

						// Validar que el shipping_number no esté vacío antes de continuar
						if (empty(trim($payload['shipping_number']))) {
							$result['events'] = $last_status;
							return $result;
						}

						$wc_order = wc_get_order($payload['order_id']);

						if ( ! $wc_order ) {
							$result['events'] = $last_status;
							return $result;
						}
						
						$shipping_methods = $wc_order->get_shipping_methods();

						if ( empty( $shipping_methods ) ) {
							$result['events'] = $last_status;
							return $result;
						} else {
							foreach ($shipping_methods as $method) {
								$instance_id = $method->get_instance_id();
							}

							$co_product = (new CorreosOficialProduct())->get_by_carrier($instance_id);
						}


						$package_status = ( new CorreosOficialRest() )->getOrderStatusP3($payload);

						if ($package_status && (count(array_filter($package_status, 'is_array')) > 0) && $package_status[0]['events']) {
							$i = 0;

							foreach ($package_status[0]['events'] as $event) {
								if ($event['summaryText'] === null) {
									continue;
								}
								$last_status[$i] = array(
									'codEnvio'        => $package_status[0]['code'],
									'codProducto'     => $co_product->get_name(),
									'desTextoResumen' => $event['summaryText'],
									'fecEvento'       => $event['eventDate'],
									'horEvento'       => $event['eventHours'],
									'unidad'          => isset($event['unit']) ? $event['unit'] : '',
								);
								$i++;
							}
						}
					} else {
						foreach ($co_order->get_bultos() as $bulto) {
							// FALTA ENDPOINT DEJAR PARA EL FINAL
							$payload['shipping_number'] = $bulto->get_shipping_number();

							// Validar que el shipping_number no esté vacío antes de hacer la llamada
							if (empty(trim($payload['shipping_number']))) {
								continue;
							}

							$package_status = ( new CorreosOficialRest() )->getOrderStatusP3($payload);
							if ($package_status && (count(array_filter($package_status, 'is_array')) > 0) && $package_status[0]['events']) {
								$i = 0;

								foreach ($package_status[0]['events'] as $event) {
									if ($event['summaryText'] === null) {
										continue;
									}

									$last_status[$i] = array(
										'codEnvio'        => $package_status[0]['code'],
										'codProducto'     => $co_product->get_name(),
										'desTextoResumen' => $event['summaryText'],
										'fecEvento'       => $event['eventDate'],
										'horEvento'       => $event['eventHours'],
										'unidad'          => isset($event['unit']) ? $event['unit'] : '',
									);
									$i++;
								}
							}
						}
					}

					if ($last_status[0]['codEnvio'] != '') {
						$result['events'] = $last_status;
					} else {
						$result['events'] = $package_status;
					}

					break;
				case API_LEGACY:
					foreach ($co_order->get_bultos() as $bulto) {
						$payload['shipping_number'] = $bulto->get_shipping_number();
						$package_status = ( new CorreosOficialRest() )->getOrderStatus($payload);
						if (!empty($package_status[0]->eventos)) {
							$i = 0;
							foreach ($package_status[0]->eventos as $evento) {
								if ($evento->desTextoResumen === null) {
									continue;
								}
								$last_status[$i] = array(
									'codEnvio'        => $package_status[0]->codEnvio,
									'codProducto'     => $co_product->get_name(),
									'desTextoResumen' => $evento->desTextoResumen,
									'fecEvento'       => $evento->fecEvento,
									'horEvento'       => $evento->horEvento,
									'unidad'          => isset($evento->unidad) ? $evento->unidad : '',
								);
								$i++;
							}
						}
					}
					
					if ($last_status[0]['codEnvio'] != '') {
						$result['events'] = $last_status;
					} else {
						$result['events'] = $package_status;
					}

					break;
				case API_CEX:
					$payload['shipping_number'] = $co_order->get_shipping_number();
					$api_result = ( new CorreosOficialCEXRest() )->getOrderStatus($payload);
					$i = 0;
					$cex_count = 0;
					if ($api_result) {
						foreach ($api_result['mensajeRetorno']->estadoEnvios as $estado) {
							// Se asegura que la hora tenga 6 dígitos y se formatea a HH:MM:SS
							$horaCompleta   = str_pad($estado->horaEstado, 6, '0', STR_PAD_LEFT);
							$formatted_hour = substr($horaCompleta, 0, 2) . ':' . substr($horaCompleta, 2, 2) . ':' . substr($horaCompleta, 4, 2);

							if (preg_match('/^\d{8}$/', $estado->fechaEstado)) {
								$formatted_date = substr($estado->fechaEstado, 0, 2) . '/' . substr($estado->fechaEstado, 2, 2) . '/' . substr($estado->fechaEstado, 4, 4);
							} else {
								$formatted_date = '';
							}

							$last_status[$i] = array(
								'codEnvio'        => isset($api_result['mensajeRetorno']->bultoSeguimiento[$cex_count]->codUnico) ? $api_result['mensajeRetorno']->bultoSeguimiento[$cex_count]->codUnico : '',
								'codProducto'     => $co_product->get_name(),
								'desTextoResumen' => $estado->descEstado,
								'fecEvento'       => $formatted_date,
								'horEvento'       => $formatted_hour,
								'unidad'          => '',
							);
							$i++;
							$cex_count++;
						}
					}

					if ($last_status[0]['codEnvio'] != '') {
						$result['events'] = $last_status;
					} else {
						$result['events'] = $api_result;
					}

					break;
				default:
					$result = false;
					break;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */
			$this->normalizedErrorReturn($result);

			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */
			if (
				$payload['company'] !== 'CEX' &&
				(
					(isset($result['events'][0]->error->codError) && $result['events'][0]->error->codError == 3) || 
					(is_array($result['events']) && isset($result['events']['codigoRetorno']) && $result['events']['codigoRetorno'] == 3)
				)
			) {
				$codEnvio = isset($result['events'][0]->codEnvio) ? $result['events'][0]->codEnvio : '';
				$result['events'] = [[
					'codEnvio'        => $codEnvio,
					'codProducto'     => '',
					'desTextoResumen' => 'Sin trazabilidad',
					'fecEvento'       => '',
					'horEvento'       => '',
					'unidad'          => '',
				]];
			}

			return $result;
		} else {
			return $this->accountNotFound;
		}
	}


	/* *********************************************************************************************************
	 * GET PICKUP LOCATIONS
	 ********************************************************************************************************* */

	public function getPickupLocations( $payload ) {

		$this->outputApi = $this->checkOutputApi($payload);

		if (isset($payload['order_id'])){
			$orderRequest = (new CorreosOficialRequests($payload['order_id']));
		}

		switch ($this->outputApi) {
			case API_P3:
				$result = ( new CorreosOficialRest() )->getPickupLocations($payload);
				break;
			case API_LEGACY:
				$result = ( new CorreosOficialSoap() )->getPickupLocations($payload);
				break;
			case API_CEX:
				$result = ( new CorreosOficialCEXRest() )->getPickupLocations($payload);
				break;
			default:
				return false;
		}

		/* *********************************************************************************************************
		* RESULTADO KO
		********************************************************************************************************* */
		
		// Si es desde checkout, no llamar a normalizedErrorReturn que termina la ejecución
		$isCheckoutContext = isset($payload['checkout']) && $payload['checkout'] === true;
		
		$defaultReturn = [[
			'codigoRetorno'  => 1,
			'mensajeRetorno' => __('There was an error in the request', 'correosoficial'),
		]];

		$isError =
			empty($result) ||
			is_object($result) ||
			(isset($result[0]['codigoRetorno']) && $result[0]['codigoRetorno'] > 0) ||
			(isset($result['codigoRetorno']) && $result['codigoRetorno'] > 0) ||
			(isset($result[0]['codigoRetorno']) && $result[0]['codigoRetorno'] == -1) ||
			(isset($result['locations']['error'])) || // Detectar error dentro de locations
			(isset($result['locations']['code']) && !isset($result['locations'][0])); // Detectar respuesta de error HTTP de la API

		if ($isError) {
			if ($isCheckoutContext) {
				// En contexto de checkout, devolver array vacío para evitar terminar la ejecución
				$errorMsg = 'getPickupLocations - Error en checkout: ';
				if (isset($result['locations']['error'])) {
					$errorMsg .= 'API Error: ' . $result['locations']['error'];
				} else {
					$errorMsg .= print_r($result, true);
				}
				error_log($errorMsg);
				return array();
			} else {
				// En otros contextos, usar el comportamiento normal
				$this->normalizedErrorReturn($result);
			}
		}

		/* *********************************************************************************************************
		* RESULTADO OK
		********************************************************************************************************* */
		
		// Verificar que result contiene locations válidos
		if (!isset($result['locations']) || !is_array($result['locations'])) {
			if ($isCheckoutContext) {
				error_log('getPickupLocations - Resultado no contiene locations válidos');
				return array();
			}
		}
		
		$normalizeLocations = ( new CorreosOficialRequests() )->normalizeLocations(
			$result['locations']
		);
		$use_pce = !empty($result['use_PCE']);

		// Añade al location flag por si está guardado en requests
		foreach ($normalizeLocations as &$location) {
			if ($use_pce) {
				$location['use_PCE'] = true;
				if (!isset($location['data']) || !is_array($location['data'])) {
					$location['data'] = array();
				}
				$location['data']['use_PCE'] = true;
			}
			if(isset($orderRequest) && $location['reference'] == $orderRequest->get_reference_code()){
				$location['cart'] = true;
			}else{
				$location['cart'] = false;
			}
		}

		return $normalizeLocations;
	}

	/* *********************************************************************************************************
	 * SGA Send order to Warehouse
	 ********************************************************************************************************* */
	
	public function sendOutgoingOrders( $payload ) {
		// Comprobar Api de salida
		if ($this->checkOutputApi($payload)) {
			
			switch ($this->outputApi) {
				// Salimos por "P3" Tradeinout / Api de experiencia
				case API_P3:
					$result = ( new CorreosOficialSGARest() )->sendOutgoingOrder($payload);
					break;
				case API_LEGACY:
				case API_CEX:
				default;
					// Si no hay api de salida, devolvemos error (Posiblemente no tenemos credenciales válidas)
					$message = __('The request could not be sent to the SGA. Please check your customer account details.');
					$this->showSGAMessage($message, 'error');
					break;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */
			$this->normalizedErrorReturn($result, 'sga');
			
			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */
			return $result;

		}
	}

	/* *********************************************************************************************************
	 * SGA Request producto stock by sku
	 ********************************************************************************************************* */
	
	public function getProductStockBySKU($payload, $cron_action = false) {
		// Comprobar Api de salida
		if ($this->checkOutputApi($payload)) {
			switch ($this->outputApi) {
				// Salimos por "P3" Tradeinout / Api de experiencia
				case API_P3:
					$result = ( new CorreosOficialSGARest() )->getProductStockBySKU($payload, $cron_action);
					break;
				case API_LEGACY:
				case API_CEX:
				default;
					$result = null;
					break;
			}

			/* *********************************************************************************************************
			* RESULTADO KO
			********************************************************************************************************* */
			$this->normalizedErrorReturn($result, 'sga');

			/* *********************************************************************************************************
			* RESULTADO OK
			********************************************************************************************************* */
			return $result;
		}
	}

	/* *********************************************************************************************************
	 * SGA Find Outgoing Orders Situation
	 ********************************************************************************************************* */

	public function findOutgoingOrdersSituation($payload) {
		// Comprobar Api de salida
		if($this->checkOutputApi($payload)){

			switch ($this->outputApi) {
				// Salimos por "P3" Tradeinout / Api de experiencia
				case API_P3:
					$result = ( new CorreosOficialSGARest() )->findOutgoingOrdersSituation($payload);
					break;
				case API_LEGACY:
				case API_CEX:
				default;
					$result = null;
					break;
			}

		}

		/* *********************************************************************************************************
		* RESULTADO KO
		********************************************************************************************************* */
		$this->normalizedErrorReturn($result, 'sga');

		/* *********************************************************************************************************
		* RESULTADO OK
		********************************************************************************************************* */
		return $result;
	}

	/* *********************************************************************************************************
	 * SGA cancelOutgoingOrder
	 ********************************************************************************************************* */
	public function cancelOutgoingOrder($payload) {
		// Comprobar Api de salida
		if ($this->checkOutputApi($payload)) {
			switch ($this->outputApi) {
				// Salimos por "P3" Tradeinout / Api de experiencia
				case API_P3:
					$result = ( new CorreosOficialSGARest() )->cancelOutgoingOrder($payload);
					break;
				case API_LEGACY:
				case API_CEX:
				default;
					$result = null;
					break;
			}
		}

		/* *********************************************************************************************************
		* RESULTADO KO
		********************************************************************************************************* */
		$this->normalizedErrorReturn($result, 'sga');

		/* *********************************************************************************************************
		* RESULTADO OK
		********************************************************************************************************* */
		return $result;
	}

	/* *********************************************************************************************************
	* UTILS
	********************************************************************************************************* */

	function getValue($item, ...$keys) {
		foreach ($keys as $key) {
			if (isset($item[$key])) {
				return $item[$key];
			}
		}
		return null;
	}

	function isArrayMulti(array $arr): bool {
		foreach ($arr as $elemento) {
			if (is_array($elemento)) {
				return true;
			}
		}
		return false;
	}

	public function normalizedErrorReturn($result, $context = '')
		{
			$defaultReturn = [[
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('There was an error in the request', 'correosoficial'),
			]];

		// Las respuestas REST directas de SGA llevan formato ['output', 'status'] sin codigoRetorno.
		// Si el HTTP status es 200 o 201 se considera respuesta válida y se procesa aguas abajo.
		if (is_array($result) && isset($result['output'], $result['status']) &&
			in_array((int) $result['status'], [200, 201])) {
			return;
		}

		// Detectar el codigoRetorno sea cual sea la estructura (indexada o plana).
		// Se usa array_key_exists (no isset) para capturar también codigoRetorno = null.
		// La comparación estricta !== 0 detecta positivos, negativos y null.
		$codigoRetorno = null;
		if (is_array($result) && isset($result[0]) && is_array($result[0]) && array_key_exists('codigoRetorno', $result[0])) {
			$codigoRetorno = $result[0]['codigoRetorno'];
		} elseif (is_array($result) && array_key_exists('codigoRetorno', $result)) {
			$codigoRetorno = $result['codigoRetorno'];
		}

		$isError =
			empty($result) ||
			is_object($result) ||
			$codigoRetorno === null ||
			$codigoRetorno !== 0;

			if ($isError) {
				$payload = empty($result) ? $defaultReturn : $result;

				// 🔸 Si el contexto es SGA, muestra mensaje visual en WooCommerce
				if (strtolower($context) === 'sga') {
					$msg = isset($payload[0]['mensajeRetorno']) 
						? $payload[0]['mensajeRetorno'] 
						: __('SGA service error occurred.', 'correosoficial');
					$this->showSGAMessage($msg, 'error');
				}

				// 🔸 En cualquier otro contexto, envía respuesta JSON estándar
				wp_send_json($result, 500);
			}
		}


	public function showSGAMessage($message, $type = 'success') {
		// Solo permitimos 'success' o 'error'
		$type = in_array($type, ['success', 'error']) ? $type : 'success';

		$query_arg = ($type === 'success') ? 'correosecomsga_message' : 'correosecomsga_error';

		$redirect_url = add_query_arg(
			$query_arg,
			urlencode($message),
			admin_url('edit.php?post_type=shop_order')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	public function setCustomsDescArray( &$payload, $origin = 'order', $wc_order = null ) {
		// Obtenemos todas las descripciones aduaneras de los bultos y las indexamos en payload como 'customs_desc_array'
		// es una solución de interación, explode y parseo, igual es mejorable
		$customs_desc_array = array_filter($payload['order_form'], function ( $key ) {
			return strpos($key, 'customs_desc') === 0;
		}, ARRAY_FILTER_USE_KEY);

		// Filter out entries where the value was wiped by sanitization (e.g. bullet • character stripped by regex)
		$customs_desc_array = array_filter($customs_desc_array, function ( $value ) {
			return !empty($value);
		});

		$needs_customs = !empty($payload['require_customs_doc']) || !empty($payload['order_form']['require_customs_doc']);

		if ($needs_customs && empty($customs_desc_array)) {

			// Obtener productos del pedido
			$products_customs = array();

			foreach ( $wc_order->get_items() as $item ) {
				$product = $item->get_product();
				$product_id = $product->get_id();
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();
				$hs_code = '';
				$product_iso = '';
				
				$attributes_to_check = $product->get_attributes();
				
				// Si es una variación, también obtener atributos del padre
				if ($product->is_type('variation')) {
					$parent_product = wc_get_product($product->get_parent_id());
					if ($parent_product) {
						$attributes_to_check = array_merge($attributes_to_check, $parent_product->get_attributes());
					}
				}
				
				foreach ($attributes_to_check as $attr_name => $attr_value) {
					$attr_name_lower = strtolower($attr_name);
					$attr_value_clean = '';
					
					// Si es un objeto WC_Product_Attribute
					if (is_object($attr_value) && method_exists($attr_value, 'get_options')) {
						$options = $attr_value->get_options();
						$is_taxonomy = method_exists($attr_value, 'is_taxonomy') && $attr_value->is_taxonomy();
						
						if ($is_taxonomy && !empty($options)) {
							$taxonomy_name = $attr_value->get_name();
							$term_names = [];
							$option_ids = is_array($options) ? $options : [$options];
							
							foreach ($option_ids as $term_id) {
								$term = get_term($term_id, $taxonomy_name);
								if ($term && !is_wp_error($term)) {
									$term_names[] = $term->name;
								}
							}
							$attr_value_clean = implode(', ', $term_names);
						} else {
							// Es un atributo personalizado
							$attr_value_clean = is_array($options) ? implode(', ', $options) : strval($options);
						}
					} elseif (is_array($attr_value)) {
						$attr_value_clean = implode(', ', array_map('strval', array_filter($attr_value)));
					} else {
						$attr_value_clean = strval($attr_value);
					}
					
					$attr_value_clean = trim($attr_value_clean);
					
					if (empty($hs_code) && (strpos($attr_name_lower, 'hs') !== false || strpos($attr_name_lower, 'hs_code') !== false)) {
						$hs_code = $attr_value_clean;
					}
					
					if (empty($product_iso) && (strpos($attr_name_lower, 'country') !== false || strpos($attr_name_lower, 'origin') !== false || strpos($attr_name_lower, 'pais') !== false)) {
						$product_iso = $attr_value_clean;
					}
				}

				// Calcular valores
				$valor_neto = $item->get_total();
				$product_weight = $product->get_weight() ? floatval($product->get_weight()) : 0;

				$products_customs[] = array(
					'product_id' => $product_id,
					'product_name' => $product_name,
					'quantity' => $quantity,
					'valor_neto' => $valor_neto,
					'weight' => $product_weight * $quantity * 1000, // Convertir a gramos
					'hs_code' => $hs_code ? $hs_code : '',
					'origin_country' => $product_iso,
				);
			}

			// Configuración por defecto
			$customs_tariff_radio_config_value = ( new CorreosOficialConfig('TariffRadio') )->get_value();
			$customs_desc_config_value = ( new CorreosOficialConfig('DefaultCustomsDescription') )->get_value();
			$customs_ntarif_config_value = ( new CorreosOficialConfig('Tariff') )->get_value();
			$customs_ntarif_desc_config_value = ( new CorreosOficialConfig('TariffDescription') )->get_value();
			$customs_origin_country_config_value = ( new CorreosOficialConfig('CountryOriginByDefault') )->get_value();
			$use_module_features = ( new CorreosOficialConfig('UseModuleFeatures') )->get_value();
			$mapped_hs_feature = ( new CorreosOficialConfig('MappedHsFeature') )->get_value();
			$mapped_origin_feature = ( new CorreosOficialConfig('MappedOriginFeature') )->get_value();
			
			// Determinar si se deben usar datos del producto
			$use_product_data = ($use_module_features == 'on') || !empty($mapped_hs_feature) || !empty($mapped_origin_feature);

			// Para cada bulto, adjuntar un array de descripciones aduaneras de productos
			foreach ($payload['info_bulto'] as $key => $bultos) {
				$index = 0;
				foreach ($products_customs as $prod) {
					$desc_data_array = array(
						'valor_neto' => $prod['valor_neto'],
						'weight' => $prod['weight'],
						'unidades' => $prod['quantity'],
					);

					// Solo usar datos del producto si UseModuleFeatures está activado O hay mapeo configurado
					if ($use_product_data && !empty($prod['hs_code'])) {
						$len_ntarifario = strlen($prod['hs_code']);
						if ($len_ntarifario == 6 || $len_ntarifario == 8 || $len_ntarifario == 10) {
							$desc_data_array['numero_tarifario'] = $prod['hs_code'];
							$desc_data_array['descripcion_aduanera'] = $prod['product_name'];
							$desc_data_array['origin_country'] = !empty($prod['origin_country']) ? $prod['origin_country'] : '';
						}
					}
					// Si no se deben usar datos del producto, usar configuración por defecto
					else if ($customs_tariff_radio_config_value == 'on') {
						// Usar configuración por defecto del módulo
						$len_ntarifario = strlen($customs_ntarif_config_value);
						if ($len_ntarifario == 6 || $len_ntarifario == 8 || $len_ntarifario == 10) {
							$desc_data_array['numero_tarifario'] = $customs_ntarif_config_value;
							$desc_data_array['descripcion_aduanera'] = $customs_ntarif_desc_config_value;
							$desc_data_array['origin_country'] = !empty($customs_origin_country_config_value) ? $customs_origin_country_config_value : '';
						} else {
							// Si no hay código tarifario válido, añadir campos vacíos para que la API indique el error
							$desc_data_array['numero_tarifario'] = '';
							$desc_data_array['descripcion_aduanera'] = !empty($customs_ntarif_desc_config_value) ? $customs_ntarif_desc_config_value : $prod['product_name'];
							$desc_data_array['origin_country'] = !empty($customs_origin_country_config_value) ? $customs_origin_country_config_value : '';
						}
					} else {
						// Sin código tarifario
						$desc_data_array['numero_tarifario'] = '';
						$desc_data_array['descripcion_aduanera'] = $customs_desc_config_value;
						$desc_data_array['origin_country'] = !empty($customs_origin_country_config_value) ? $customs_origin_country_config_value : '';
					}

					$payload['customs_desc_array'][$key][$index] = array_map('trim', array_map('strval', $desc_data_array));
					$index++;
				}
			}
		} elseif ($needs_customs) {
			foreach ($customs_desc_array as $key => $desc) {
				$desc_data      = str_replace(array( '€', 'KG', 'UNID.' ), '', $desc);
				$desc_data      = explode(' • ', $desc_data);

				$weight_raw = $desc_data[3];

				if (fmod(floatval($weight_raw), 1.0) !== 0.0) {
					// Es decimal
					$weight_grams = floatval($weight_raw) * 1000;
				} else {
					// Es entero
					$weight_grams = intval($weight_raw) * 1000;
				}

				$desc_data_array = array(
					'valor_neto'           => $desc_data[2],
					'weight'               => $weight_grams,
					'unidades'             => $desc_data[4],
					'origin_country'       => isset($desc_data[5]) ? $desc_data[5] : '',
				);

				$len_ntarifario = strlen($desc_data[0]);
				if ($len_ntarifario == 6 || $len_ntarifario == 8 || $len_ntarifario == 10) {
					$desc_data_array['numero_tarifario']         = $desc_data[0];
					$desc_data_array['descripcion_aduanera']     = $desc_data[1];
				} else {
					$desc_data_array['numero_tarifario']         = '';
					$desc_data_array['descripcion_aduanera']     = $desc_data[0];
				}
				
				$customs_desc = explode('_', $key);
				$payload['customs_desc_array'][$customs_desc[2]][$customs_desc[3]] = array_map('trim', $desc_data_array);
			}
		}
	}


	public function getCountryName( $iso_code ) {
		// Asegúrate de que el código ISO esté en el formato correcto
		$countries = WC()->countries->get_countries();
		return isset($countries[$iso_code]) ? $countries[$iso_code] : null;
	}

	// Lo dejo aquí temporalmente
	public function getStatus( $search ) {
		if (( new CorreosOficialConfig('ShowShippingStatusProcess') )->get_value() == 'on') {
			return ( new CorreosOficialConfig($search) )->get_value();
		}
		return false;
	}

	// metodo provisional - solo se usa para localizador oficinas-citypaq.
	public function getCorreosUser() {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'correos_oficial_codes WHERE CorreosUser NOT LIKE %s', 'n/a'),
			ARRAY_A
		);
	}
}
