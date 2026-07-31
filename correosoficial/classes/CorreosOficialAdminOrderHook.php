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

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialReturn;
use CorreosOficial\Classes\Apis\CorreosOficialRest;
use CorreosOficial\Classes\Apis\CorreosOficialSoap;
use CorreosOficial\Models\CorreosOficialSender;
use CorreosOficial\Classes\Apis\CorreosOficialCEXRest;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialCustomDescription;
use CorreosOficial\Models\CorreosOficialOrder;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialRequests;
use CorreosOficial\Models\CorreosOficialSGAOrdersLog;
use CorreosOficial\Models\CorreosOficialSgaOrdersStatus;
use CorreosOficial\Classes\CorreosOficialCountriesWC;
use CorreosOficial\Classes\CorreosOficialSenders;
use CorreosOficial\Classes\CorreosOficialReturnsShippingMethods;
use CorreosOficial\Classes\CorreosOficialOrders;
use CorreosOficial\Classes\CorreosOficialCarrier;
use CorreosOficial\Classes\CorreosOficialOrdersWC;
use CorreosOficial\Classes\CorreosOficialCustomerWC;
use CorreosOficial\Classes\CorreosOficialAddressWC;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialMarketplace;
use CorreosOficial\Classes\CorreosOficialNeedCustoms;
use CorreosOficial\Classes\CorreosOficialPrefilter;

class CorreosOficialAdminOrderHook {


	private $smarty;
	private $plugin_dir;

	public function __construct( $smarty, $plugin_dir ) {
		
		$this->plugin_dir = $plugin_dir;
		$this->smarty = $smarty;

		if ($this->isSGAOrder()) {
			$this->correosecomsgaOrderTracking();
		} elseif ($this->isMarketplaceOrder()) {
			$this->marketplaceOrderTracking();
		} else {
			$this->hookDisplayAdminOrder();
		}
	}

	/**
	 * Check if HPOS enabled.
	 */
	public function is_wc_order_hpos_enabled() {
		return function_exists( 'wc_get_container' ) ?
				wc_get_container()
				->get( CustomOrdersTableController::class )
				->custom_orders_table_usage_is_enabled()
			: false;
	}

	public function hookDisplayAdminOrder() {
		global $co_module_url_wc;
		global $post;
		global $woocommerce;

		$client_data = array();
		$carriers = array();

		$array_packages_order = array();
		$array_packages_return = array();

		$saved_return_pickup = array();

		$pickup_return_data_response = array();
		$pickup_return_cancelable = '';

		$return_status = '';

		$cod_office = '';
		$cod_homepaq = '';

		// Init Modal para remitentes
		$showSenderModal = false;
		$errorSenderName = '';
		$errorCompanyName = '';

		// Multicliente (Se tendría que implementar un método que devuelva contratos activos)
		$active_client = 'both'; // Forzado

		$is_international = '';
		$require_customs_doc = '';

		$order_returnable = '';
		$is_code_at = false;

		$shipping_method_data = array();
		$id_zone = null;

		// Order
		if ($this->is_wc_order_hpos_enabled()) {
			$id_order = CorreosOficialNormalization::normalizeData('id');
		} else {
			$id_order = $post->ID;
		}

		$order = new WC_Order($id_order);
		$order_reference = str_replace('wc_order_', '', $order->get_order_key());

		$total_value_products = $order->get_subtotal();

		$order_number = $order->get_order_number();

		$correos_order = CorreosOficialOrders::getCorreosOrder($order->get_id());
		$correos_return = CorreosOficialOrders::getCorreosReturn($order->get_id());
		$correos_pickup_return = CorreosOficialOrders::getCorreosPickupReturn($order->get_id());

		// Comprobamos Cash on delivery - usa el método configurado en CashOnDeliveryMethod (con fallback a 'cod')
		$cash_on_delivery_method = ( new CorreosOficialConfig('CashOnDeliveryMethod') )->get_value();
		if ( empty( $cash_on_delivery_method ) ) {
			$cash_on_delivery_method = 'cod';
		}
		if ($order->get_payment_method() == $cash_on_delivery_method) {
			$cash_on_delivery = true;
		} else {
			$cash_on_delivery = false;
		}
		$cash_on_delivery_value = number_format($order->get_total(), 2);

		$NifFieldRadio = CorreosOficialConfig::getConfigValue('NifFieldRadio');

		if ($NifFieldRadio && $NifFieldRadio == 'PERSONALIZED') {
			$NifFieldValue = CorreosOficialConfig::getConfigValue('NifFieldPersonalizedValue');
		} else {
			$NifFieldValue = 'NIF';
		}

		$customer = new CorreosOficialCustomerWC($order);
		$address = new CorreosOficialAddressWC($order, $NifFieldValue);
		$countries = CorreosOficialCountriesWC::getCountries();

		$shipping_methods = $order->get_shipping_methods();

		foreach ($shipping_methods as $shipping_method) {
			$shipping_method_data = $shipping_method->get_data();
		}

		if (count($shipping_method_data) > 1) {
			// $order->id_carrier = $shipping_method_data['instance_id'];
			$order_id_carrier = $shipping_method_data['instance_id'];
		} else { // Transportista aún no seleccionado (ejemplo un pedido hecho desde Woocommerce->Pedidos)
			// $order->id_carrier = '';
			$order_id_carrier = '';
		}
		
		$id_zone = CorreosOficialCarrier::getCarrierZone(
			isset($shipping_method_data['instance_id']) ? $shipping_method_data['instance_id'] : '');

		//$carrier_order = $this->getCarrierOrder($id_zone, $order_id_carrier, $correos_order);

		// Borrar multicliente
		// Seleccionamos carriers según usuario (Correos, Cex, All)
		// if ($active_client != 'none') {
		//  $carriers = CorreosOficialCarrier::getCarriersByCompanyInOrder($active_client, $id_zone);
		// }

		$carriers = CorreosOficialCarrier::getCarriersByCompanyInOrder($active_client, $id_zone);

		// Remitente por defecto
		$default_sender = CorreosOficialSender::get_default_sender();
		
		// Sobreescribimos con correos_code y cex_code
		if ($default_sender) {
			$default_sender = CorreosOficialSenders::getSenderById($default_sender['id']);
		}

		// Si el pedido está preregistrado obtenemos información guardada
		if ($correos_order) {
			$default_sender = CorreosOficialSender::get_default_sender($correos_order['id_sender']);
			$carrier_order = CorreosOficialCarrier::getSavedOrderProduct($id_order);
		}

		// Contrato según remitente por defecto y producto
		if (isset($carrier_order) && $default_sender) {
			$client_data = CorreosOficialSender::get_code_by_sender_and_company($default_sender['id'], strtolower($carrier_order['company']));
		} else {
			$carrier_order = $this->getCarrierOrder($id_zone, $order_id_carrier, $correos_order);
		}

		// Senders
		$senders = CorreosOficialSender::get_senders();

		// Asegurar que client_data se calcule si carrier_order tiene company y hay remitente
		if ((empty($client_data) || !isset($client_data['customer_code'])) && isset($carrier_order['company']) && !empty($carrier_order['company']) && $default_sender) {
			$client_data = CorreosOficialSender::get_code_by_sender_and_company($default_sender['id'], strtolower($carrier_order['company']));
		}

		// Client code actual si existe relación
		$client_code = isset($client_data['customer_code']) ? $client_data['customer_code'] : '';

		// Alerta Modal sobre Remitentes
		if (empty($senders) || empty($default_sender)) {
			$showSenderModal = true;
		} else {
			
			// Si está preregistrado
			if ($correos_order) {
				$order_company = $correos_order['carrier_type'];
			} else {
				$order_company = CorreosOficialCarrier::getCompanyByOrder($id_order, $id_zone);
			}
			
			if (
				( $order_company == 'Correos' && !$default_sender['correos_code'] ) ||
				( $order_company == 'CEX' && !$default_sender['cex_code'] )
			) {
				$errorSenderName = $default_sender['sender_name'];
				$errorCompanyName = $order_company;
				$showSenderModal = true;
			}
		}

		$delivered = false;

		// Comprobamos si está preregistrado y si tiene recogida grabada
		if (empty($correos_order)) {

			$order_done = false;
			$cancelable = true;
			$pickup = 0;
			$pickup_cancelable = false;
			$pickup_data_response = self::getPickUpDataResponse('Estado 1');

			// Comprobación de dimensiones activadas manualmente
			$dimensions_by_default = (new CorreosOficialConfig('ActivateDimensionsByDefault'))->get_value() == 'on';

			// Condición de productos especiales
			$is_special_product = !empty($carrier_order)
				&& (
					in_array($carrier_order['product_type'], ['citypaq', 'pudocex'], true)
					|| $carrier_order['codigoProducto'] === 'S0179'
				);

			$array_packages_order = [];

			$items = $order->get_items() ?? [];
			$total_articles = count($items);

			$total_units = 0;

			foreach ($items as $item) {
				$qty = (int) $item->get_quantity();
				$total_units += $qty;
			}

			$single_unit_single_product = ($total_articles === 1 && $total_units === 1);

			if ($single_unit_single_product && $carrier_order['product_type'] == 'pudocex') {

				$articles = [];
				$units    = 0;

				foreach ($order->get_items() as $item) {
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
					$array_packages_order[] = [
						'height' => (int) $articles[0]['depth'],
						'width'  => (int) $articles[0]['width'],
						'large'  => (int) $articles[0]['height'],
						'shipping_number' => ''
					];
				}

			} elseif (!empty($carrier_order) && $is_special_product && $dimensions_by_default) {

				// Dimensiones por defecto activadas
				$array_packages_order[] = [
					'height' => (int) (new CorreosOficialConfig('DimensionsByDefaultHeight'))->get_value(),
					'width'  => (int) (new CorreosOficialConfig('DimensionsByDefaultWidth'))->get_value(),
					'large'  => (int) (new CorreosOficialConfig('DimensionsByDefaultLarge'))->get_value(),
					'shipping_number' => ''
				];

			} else {

				// Sin datos
				$array_packages_order[] = [
					'height' => '',
					'width'  => '',
					'large'  => '',
					'shipping_number' => ''
				];
			}

		} elseif ($correos_order['shipping_number'] != '') {

			$order_done = true;

			// Comprobamos bultos para traer información de cada bulto
			$array_packages_order = CorreosOficialOrders::getCorreosPackages($order->get_id(), $correos_order['shipping_number']);
			$company = sanitize_text_field(isset($correos_order['company']) ? $correos_order['company'] : '');

			if ($correos_order['pickup'] == 1) {
				$pickup = 1;
				if ($company == 'Correos') {
				
					if ($client_data['CorreosClientID'] != 'n/a') {
						$pickup_status = ( new CorreosOficialRest() )->seguimientoRecogida( $client_data, $correos_order['pickup_number']);

						if ($pickup_status['codigoRetorno'] == 1) {
							$pickup_data_response = self::getPickUpDataResponse($pickup_status['mensajeRetorno'], '5', 'Sin datos');
						} 

						list($pickup_from_hour, $pickup_to_hour) = explode(' ', $pickup_status['mensajeRetorno']['clientObservations']);

						$pickup_data_response= array(
							'codEstado' => $pickup_status['mensajeRetorno']['state'],
							'status' => $pickup_status['mensajeRetorno']['state'],
							'pickup_reference' => $correos_order['reference'],
							'pickup_date' => $pickup_status['mensajeRetorno']['requestDate'],
							'pickup_from_hour' => $pickup_from_hour,
							'pickup_to_hour' => $pickup_to_hour,
							'pickup_address' => $pickup_status['mensajeRetorno']['address'],
							'pickup_city' => $pickup_status['mensajeRetorno']['locality'],
							'pickup_cp' => $pickup_status['mensajeRetorno']['postalCode'],
						);

					} else {

						$payload = array(
							'CodigoSRE' => $correos_order['pickup_number'],
							'client'    => $client_data,
							'ModoOperacion' => '1', // Info + Todos los estados
						);

						$pickup_status = (new CorreosOficialSoap())->consultaSRE($payload);

						if ($pickup_status) {

							$pickup_traze = $pickup_status['data']->TrazasSolicitudRecogidaEsporadica->TrazaSolicitudRecogidaEsporadica;

							if ($pickup_traze == null) {
								$pickup_data_response = self::getPickUpDataResponse($pickup_status['mensajeRetorno'], '5', 'Sin datos');
							} else {
								
								if ($pickup_traze instanceof stdClass) {
									// Caso único lo metemos en array
									$pickup_traze = [$pickup_traze];
								}

								$pickup_last_status = end($pickup_traze);

								$pickup_data_response = array(
									'codEstado' => $pickup_last_status->codEstado,
									'status' => $pickup_last_status->desTextoResumen,
									'pickup_reference' => $pickup_status['data']->CodigoSolicitudRecogidaEsporadica->ReferenciaRecogida,
									'pickup_date' => str_replace('00:00:00.0', '', $pickup_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->FecRecogida),
									'pickup_from_hour' =>gmdate('H:i', strtotime($correos_order['pickup_from_hour'])),
									'pickup_to_hour' =>gmdate('H:i', strtotime($correos_order['pickup_to_hour'])),
									'pickup_address' => $pickup_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->NomNombreViaRec,
									'pickup_city' => $pickup_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->NomLocalidadRec,
									'pickup_cp' => $pickup_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->CodigoPostalRecogida,
								);
							}
						} else {
							$pickup_data_response = self::getPickUpDataResponse('En espera de datos', '3', 'Sin datos');
						}
					}
				} elseif ($company == 'CEX') {

					$payload = array(
						'pickup_number' => $correos_order['pickup_number'],
						'client' => $client_data,
						'fecRecogida' => '',
						'idioma' => 'ES',
					);

					$pickup_status = (new CorreosOficialCEXRest())->consultarRecogida($payload);

					if ($pickup_status['codigoRetorno'] != 0) {
						$pickup_last_status = self::getPickUpDataResponse($pickup_status['mensajeRetorno'], '5', 'Sin datos');
					} else {
						$pickup_data_cex = $pickup_status['mensajeRetorno'];

						$pickup_data_response = array(
							'status' => $pickup_data_cex['situaciones'][0]['descSituacion'],
							'pickup_reference' => $pickup_data_cex['referencia'],
							'pickup_date' => $pickup_data_cex['fecRecogida'],
							'pickup_from_hour' =>gmdate('H:i', strtotime($correos_order['pickup_from_hour'])),
							'pickup_to_hour' =>gmdate('H:i', strtotime($correos_order['pickup_to_hour'])),
							'pickup_address' => $pickup_data_cex['domRecogida'],
							'pickup_city' => $pickup_data_cex['pobRecogida'],
							'pickup_cp' => $pickup_data_cex['codPosRecogida'],
						);
					}
				}

				// Comprobamos estado de la recogida
				$pickup_cancelable = true;
				if ( ( $pickup_data_response['status'] != 'RECOGIDA REGISTRADA' && $pickup_data_response['status'] != 'PDTE ASIGNAR' ) || ( isset($pickup_data_response['status']['errorCode']) && $pickup_data_response['status']['errorCode'] == '1' ) ) {
					$pickup_cancelable = false;
				}

				// LA RESPUESTA DE CEX NO TIENE codEstado
				// if ($pickup_data_response['codEstado'] != 'SR-001'  // Recogida solicitada Correos
				//  && $pickup_data_response['codEstado'] != 'SR-003'  // Alta Unidad de recogida Correos
				//  && $pickup_data_response['status'] != 'RECOGIDA REGISTRADA' 
				//  && $pickup_data_response['status'] != 'PDTE ASIGNAR'
				// ) {
				//  $pickup_cancelable = false;
				// }

				if ($pickup_data_response['status'] == 'ANULADA') {
					$pickup = 0;
					$pickup_cancelable = false;
				}
			} else {
				$pickup = 0;
				$pickup_cancelable = false;
				$pickup_data_response = self::getPickUpDataResponse('Estado 2');
			}

			$last_status[] = array(
				'codEnvio' => '',
				'codProducto' => '',
				'desTextoResumen' => 'En espera de datos',
				'fecEvento' => '',
				'horEvento' => '',
				'unidad' => '',
			);

			foreach ($array_packages_order as $bulto) {
				if ($correos_order['carrier_type'] == 'Correos') {
					$payload = array(
						'shipping_number' => $bulto['shipping_number'],
						'sender_id'       => $default_sender['id'],
						'client'          => $client_data,
					);
					$package_status = (new CorreosOficialRest())->getOrderStatus($payload);
					if (isset($package_status[0]->eventos)) {
						$i = 0;
						foreach ($package_status[0]->eventos as $evento) {
							if ($evento->desTextoResumen == null) {
								continue;
							}
							$last_status[$i] = array(
								'codEnvio' => $package_status[0]->codEnvio,
								'desTextoResumen' => $evento->desTextoResumen,
								'fecEvento' => $evento->fecEvento,
								'unidad' => '',
							);
							$i++;
						}
					}
				} elseif ($correos_order['carrier_type'] == 'CEX') {
					$payload = array(
						'shipping_number' => $bulto['shipping_number'],
						'client'          => $client_data,
					);
					$cex_response = (new CorreosOficialCEXRest())->getOrderStatus($payload);
					$package_status = isset($cex_response['mensajeRetorno']) ? $cex_response['mensajeRetorno'] : null;
					if ($package_status && isset($package_status->bultoSeguimiento[0])) {
						$last_status[0] = array(
							'codEnvio' => $package_status->bultoSeguimiento[0]->codUnico,
							'codProducto' => isset($package_status->producto) ? $package_status->producto : '',
							'desTextoResumen' => $package_status->bultoSeguimiento[0]->descEstado,
							'fecEvento' => $package_status->bultoSeguimiento[0]->fechaEstado,
							'unidad' => '',
						);
					}
				}
			}

			// De inicio ningún en ningún estado se podrá cancelar, hasta comprobar exclusiones.
			$cancelable = false;
			foreach ($last_status as $status_bulto) {

				$statusBultoResumen = $status_bulto['desTextoResumen'];

				// Exclusiones de estados en los que se puede cancelar
				if (
					$statusBultoResumen == 'En espera de datos' ||
					$statusBultoResumen == 'Prerregistrado' ||
					$statusBultoResumen == 'Admisión anulada' ||
					$statusBultoResumen == 'SIN RECEPCION'
				) {
					$cancelable = true;
				}

				// Si está entregado no se podrá cancelar (ya que no está excluido) y marcamos flag delivered
				if (
					$statusBultoResumen == 'Entregado' ||
					$statusBultoResumen == 'ENTREGADO'
				) {
					$delivered = true;
				}
			}

		} else {
			$order_done = false;
			$cancelable = true;
			$pickup = 0;
		}

		// DEVOLUCIONES
		if (empty($correos_return)) {
			$exist_return = false;
			$return_cancelable = true;
			$pickup_return = 0;
			$return_pickup_number = '';
		} else {
			$exist_return = true;

			$saved_return = new CorreosOficialReturn($order->get_id());
			$saved_return_pickup = $saved_return->get_pickup();
			$return_pickup_number = $saved_return->get_pickup_number();
			$carrier_type = $saved_return->get_carrier_type();

			$carrier_type = strtolower($carrier_type);
			$client_data = CorreosOficialSender::get_code_by_sender_and_company($default_sender['id'], $carrier_type);

			if ($correos_return['pickup_number'] != null) {
				$pickup_return = 1;
				if ($correos_return['carrier_type'] == 'Correos') {
					if ($client_data['CorreosClientID'] != 'n/a') {

						$pickup_status = ( new CorreosOficialRest() )->seguimientoRecogida( $client_data, $return_pickup_number);

						if ($pickup_status['codigoRetorno'] == 1) {
							$pickup_return_data_response = self::getPickUpDataResponse($pickup_status['mensajeRetorno'], '5', 'Sin datos');
						}

						list($pickup_from_hour, $pickup_to_hour) = explode(' ', $pickup_status['mensajeRetorno']['clientObservations']);

						$pickup_return_data_response = array(
							'codEstado' => $pickup_status['mensajeRetorno']['state'],
							'status' => $pickup_status['mensajeRetorno']['state'],
							'pickup_reference' => $saved_return->get_reference(),
							'pickup_date' => $pickup_status['mensajeRetorno']['requestDate'],
							'pickup_from_hour' => $pickup_from_hour,
							'pickup_to_hour' => $pickup_to_hour,
							'pickup_address' => $pickup_status['mensajeRetorno']['address'],
							'pickup_city' => $pickup_status['mensajeRetorno']['locality'],
							'pickup_cp' => $pickup_status['mensajeRetorno']['postalCode'],
						);

					} else {

						$payload = array(
							'CodigoSRE' => $correos_return['pickup_number'],
							'client'    => $client_data,
							'ModoOperacion' => '1', // Info + Todos los estados
						);

						$pickup_return_status = (new CorreosOficialSoap())->consultaSRE($payload);
						
						if ($pickup_return_status) {

							$pickup_traze = $pickup_return_status['data']->TrazasSolicitudRecogidaEsporadica;

							if ($pickup_traze == null) {
								$pickup_return_data_response = self::getPickUpDataResponse('Sin trazabilidad', '4', 'En espera de datos');
							} else {
								$pickup_last_status = end($pickup_traze);

								$pickup_return_data_response = array(
									'codEstado' => $pickup_last_status->codEstado,
									'status' => $pickup_last_status->desTextoResumen,
									'pickup_reference' => $pickup_return_status['data']->CodigoSolicitudRecogidaEsporadica->ReferenciaRecogida,
									'pickup_from_hour' => gmdate('H:i', strtotime($correos_pickup_return['pickup_from_hour'])),
									'pickup_to_hour'   => gmdate('H:i', strtotime($correos_pickup_return['pickup_to_hour'])),
									'pickup_date'      => str_replace('00:00:00.0', '', $pickup_return_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->FecRecogida),
									'pickup_address'   => $pickup_return_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->NomNombreViaRec,
									'pickup_city'      => $pickup_return_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->NomLocalidadRec,
									'pickup_cp'        => $pickup_return_status['data']->DatosSolicitudRecogidaEsporadica->Recogida->CodigoPostalRecogida,
								);
							}

						} else {
							$pickup_return_data_response = self::getPickUpDataResponse('En espera de datos', '3', 'Sin datos');
						}
					}

				} elseif ($correos_return['carrier_type'] == 'CEX') {

					$payload = array(
						'pickup_number' => $correos_return['pickup_number'],
						'client' => $client_data,
						'fecRecogida' => '',
						'idioma' => 'ES',
					);

					$pickup_status = (new CorreosOficialCEXRest())->consultarRecogida($payload);

					if ($pickup_status['codigoRetorno'] != 0) {
						$pickup_last_status = self::getPickUpDataResponse($pickup_status['mensajeRetorno'], '5', 'Sin datos');
					} else {
						$pickup_data_cex = $pickup_status['mensajeRetorno'];

						$pickup_return_data_response = array(
							'status' => $pickup_data_cex['situaciones'][0]['descSituacion'],
							'pickup_reference' => $pickup_data_cex['referencia'],
							'pickup_date' => $pickup_data_cex['fecRecogida'],
							'pickup_from_hour' =>gmdate('H:i', strtotime($correos_pickup_return['pickup_from_hour'])),
							'pickup_to_hour' =>gmdate('H:i', strtotime($correos_pickup_return['pickup_to_hour'])),
							'pickup_address' => $pickup_data_cex['domRecogida'],
							'pickup_city' => $pickup_data_cex['pobRecogida'],
							'pickup_cp' => $pickup_data_cex['codPosRecogida'],
						);
					}
				}

				// Comprobamos estado de la recogida
				$pickup_return_cancelable = true;
				if ($pickup_return_data_response['status'] != 'RECOGIDA REGISTRADA' 
					&& $pickup_return_data_response['status'] != 'PDTE ASIGNAR'
				) {
					$pickup_return_cancelable = false;
				}
				if ($pickup_return_data_response['status'] == 'ANULADA') {
					$pickup_return = 1;
					$pickup_return_cancelable = false;
				}
			} else {
				$pickup_return = 0;
				$pickup_return_cancelable = false;
				$pickup_return_data_response = self::getPickUpDataResponse('Estado 3');
			}

			$array_packages_return = CorreosOficialOrders::getCorreosPackagesReturn($order->get_id());
			foreach ($array_packages_return as $bulto => $field) {
				
				$default_status_return = array(
					'codEnvio' => "",
					'codProducto' => "",
					'desTextoResumen' => "En espera de datos",
					'fecEvento' => "",
					'horEvento' => "",
					'unidad' => ""
				);

				if ($correos_return['carrier_type'] == 'Correos') {
					if ($client_data['CorreosClientID'] != 'n/a') {

						$payload = [
                            'shipping_number' => $field['shipping_number'],
                            'client' => $client_data
                        ];

                        $package_status_return = ( new CorreosOficialRest() )->getOrderStatusP3($payload);

                        if (isset($package_status_return[0]['events']) && count($package_status_return[0]['events'])) {
                            
                            foreach ($package_status_return[0]['events'] as $event) {
                                $last_status_return[] = array(
                                    'codEnvio' => $package_status_return[0]['code'],
                                    'codProducto' => isset($package_status_return[0]['codProducto']) ? $package_status_return[0]['codProducto'] : '',
                                    'desTextoResumen' => $event['summaryText'],
                                    'fecEvento' => $event['eventDate'],
                                    'unidad' => $event['unit']
                                );
                            }

                        } else {
                            $last_status_return[] = $default_status_return;
                        }
					}

					$payload = array (
						'order_id' => $field['id_order'],
						'sender_id' => $field['id_sender'],
						'shiping_number' => $field['shipping_number'],
						'client' => $client_data
					);

					$package_status_return = (new CorreosOficialRest())->getOrderStatus($payload);
					
					if (isset($package_status_return[0]->eventos)) {
						foreach ($package_status_return[0]->eventos as $evento => $field2) {
							$last_status_return[] = array(
								'codEnvio' => $package_status_return[0]->codEnvio,
								'codProducto' => isset($package_status_return[0]->codProducto) ? $package_status_return[0]->codProducto : '',
								'desTextoResumen' => $field2->desTextoResumen,
								'fecEvento' => $field2->fecEvento,
								'unidad' => isset($field2->unidad) ? $field2->unidad : '',
							);
						}
					}
				} else {
					$payload = array(
						'shipping_number' => $field['shipping_number'],
						'client'          => $client_data,
					);
					$cex_response = (new CorreosOficialCEXRest())->getOrderStatus($payload);
					$package_status_return = isset($cex_response['mensajeRetorno']) ? $cex_response['mensajeRetorno'] : null;

					if ($package_status_return && isset($package_status_return->bultoSeguimiento[0])) {
						$last_status_return[] = array(
							'codEnvio' => $package_status_return->bultoSeguimiento[0]->codUnico,
							'codProducto' => isset($package_status_return->producto) ? $package_status_return->producto : '',
							'desTextoResumen' => $package_status_return->bultoSeguimiento[0]->descEstado,
							'fecEvento' => $package_status_return->bultoSeguimiento[0]->fechaEstado,
							'unidad' => '',
						);
					}
				}
			}

			$return_cancelable = true;
			$return_status = __('No information', 'correosoficial');

			if (isset($last_status_return) && is_array($last_status_return)) {
				foreach ($last_status_return as $status_bulto => $field3) {
					if ($field3['desTextoResumen'] != 'Prerregistrado' && $field3['desTextoResumen'] != 'Admisión anulada' && $field3['desTextoResumen'] != 'SIN RECEPCION' && $field3['desTextoResumen'] != 'En espera de datos') {
						$return_cancelable = false;
					}
					$return_status = $field3['desTextoResumen'];
				}
			} else {
				$return_cancelable = false;
				$return_status = __('No information', 'correosoficial');
			}
		}

		// Bultos a devolver: por defecto 1, si es CEX usar los bultos del pedido
		$bultos_return = 1;

		if (isset($order_company) && $order_company == 'CEX') {
			$correos_order_bultos = isset($correos_order['bultos']) ? $correos_order['bultos'] : 1;
			$bultos_return = $correos_order_bultos;
		}

		// Inicializar variables de códigos de punto de entrega
		$cod_office = '';
		$cod_homepaq = '';
		$cod_pudocex = '';

		$customer_postal_code = $address->postcode;
		$customer_country = $order->get_shipping_country();

		$co_request = (new CorreosOficialRequests($order->get_id()));
		
		//Comprobamos datos del checkout
		if (isset($co_request) && !empty($co_request->get_id_cart())) {
			$request_data = $co_request->getRequestData($co_request->get_id_order(), $carrier_order['product_type']);
			
			if (!empty($request_data)) {
				// Si el pedido está pre-registrado, usar la dirección de envío actual en lugar de la tabla requests
				if ($correos_order) {
					$address_paq = array(
						"dir_paq" => $address->address1,
						"loc_paq" => $address->city,
						"cp_paq" => $address->postcode
					);
				} else {
					// Si no está pre-registrado, usar datos de la tabla requests
					$address_paq = array(
						"dir_paq" => isset($request_data['address']) ? $request_data['address'] : '',
						"loc_paq" => isset($request_data['city']) ? $request_data['city'] : '',
						"cp_paq" => isset($request_data['zipcode']) ? $request_data['zipcode'] : ''
					);
				}

				$request_type = isset($request_data['type']) ? $request_data['type'] : '';
				switch ($request_type) {
					case 'citypaq':
						$cod_office  = '';
						$cod_homepaq = isset($request_data['reference']) ? $request_data['reference'] : '';
						$cod_pudocex = '';
						break;
					case 'office':
						$cod_office  = isset($request_data['reference']) ? $request_data['reference'] : '';
						$cod_homepaq = '';
						$cod_pudocex = '';
						break;
					case 'pudocex':
						$cod_office  = '';
						$cod_homepaq = '';
						$cod_pudocex = isset($request_data['reference']) ? $request_data['reference'] : '';
						break;
				}
		} else {
			$address_paq = array("dir_paq" => "", "loc_paq" => "", "cp_paq" => "");
			$cod_office  = '';
			$cod_homepaq = '';
			$cod_pudocex = '';
		}
	} else {
		$address_paq = array("dir_paq" => "", "loc_paq" => "", "cp_paq" => "");
	}
	
	$return_products_filtered = [];
	$order_returnable = false;
	$available_return_methods = [];
	
	if (CorreosOficialReturnsShippingMethods::isReturnsSupportedCountry($customer_country)) {
		$order_returnable = true;
		$available_return_methods = CorreosOficialReturnsShippingMethods::getAvailableReturnMethods($customer_country);
			
			// Filtrar productos según el país
			foreach ($available_return_methods as $method) {
				switch ($method['operator']) {
					case 'correos_national':
						$return_products_filtered[] = [
							'code' => 'S0148',
							'name' => 'Paq Retorno - Correos',
							'company' => 'Correos',
							'allow_pickup' => true
						];
						break;
					case 'correos_international':
						$return_products_filtered[] = [
							'code' => 'S0159',
							'name' => 'Paq Retorno Internacional - Correos',
							'company' => 'Correos',
							'allow_pickup' => false
						];
						break;
					case 'cex':
						$return_products_filtered[] = [
							'code' => '63',
							'name' => 'Paq24 - Correos Express',
							'company' => 'CEX',
							'allow_pickup' => true
						];
						break;
				}
			}
		}

		$height_by_default = '';
		$large_by_default = '';
		$width_by_default = '';
		$bank_acc_number = '';
		
		// Obtenemos configuración por defecto
		$bultos_config = ( new CorreosOficialConfig('DefaultPackages') )->get_value();
		
		if ($order_done) {
			$bultos = $correos_order['bultos'];
		} else {
			$bultos = $bultos_config;
		}

		//IBAN
		$bank_acc_number = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
		try {
			$bank_acc_number = CorreosOficialCrypto::decrypt($bank_acc_number);
		} catch ( \RuntimeException $e ) {
			$bank_acc_number = '';
		}
		
		$BankIni = substr($bank_acc_number, 0, -4);
		$BankFin = substr($bank_acc_number, -4);
		$bank_acc_number = str_repeat('*', strlen($BankIni)) . $BankFin;

		$DefaultLabel = ( new CorreosOficialConfig('DefaultLabel') )->get_value();
		$ActivateWeightByDefault = ( new CorreosOficialConfig('ActivateWeightByDefault') )->get_value() == 'on' ? true : false;

		if ($ActivateWeightByDefault) {
			$weight_by_default = ( new CorreosOficialConfig('WeightByDefault') )->get_value();
		}

		// Aduanas
		$sender_postal_code = $default_sender['sender_cp'];
		$sender_country = $default_sender['sender_iso_code_pais'];

		$customs_desc_array = (new CorreosOficialCustomDescription())->get_all_customs_desc();
		$customs_desc_selected = CorreosOficialConfig::getConfigValue('DefaultCustomsDescription');
		$customs_tariff_selected = CorreosOficialConfig::getConfigValue('Tariff');
		$customs_tariff_description = CorreosOficialConfig::getConfigValue('TariffDescription');
		$customs_reference = CorreosOficialConfig::getConfigValue('ShippCustomsReference');
		$CountryOriginByDefault = CorreosOficialConfig::getConfigValue('CountryOriginByDefault');
		$customs_tariff_radio = CorreosOficialConfig::getConfigValue('TariffRadio');

		$return_require_customs_doc = CorreosOficialNeedCustoms::isCustomsRequired($sender_postal_code, $customer_postal_code, $sender_country, $customer_country, true);
        $require_customs_doc = CorreosOficialNeedCustoms::isCustomsRequired($sender_postal_code, $customer_postal_code, $sender_country, $customer_country);

		/* Obtenemos dimensiones por defecto si existen */
		$height_by_default = ( new CorreosOficialConfig('DimensionsByDefaultHeight') )->get_value();
		$large_by_default = ( new CorreosOficialConfig('DimensionsByDefaultLarge') )->get_value();
		$width_by_default = ( new CorreosOficialConfig('DimensionsByDefaultWidth') )->get_value();

		$google_maps_api = ( new CorreosOficialConfig('GoogleMapsApi') )->get_value();

		$label_observations = ( new CorreosOficialConfig('LabelObservations') )->get_value();
		if ($label_observations == 'on') {
			$customer_message = substr($order->get_customer_note(), 0, 80);
		} else {
			$customer_message = '';
		}

		$tariff_radio = ( new CorreosOficialConfig('TariffRadio') )->get_value();
		$product_radio = ( new CorreosOficialConfig('ProductRadio') )->get_value();

		if ($tariff_radio == 'on') {
			$config_default_aduanera = 1;
		} else if ($product_radio == 'on') {
			// Activar tab de código HS pero sin precargar datos por defecto
			$config_default_aduanera = 1;
		} else {
			$config_default_aduanera = 0;
		}

		$ship_reference = $order->get_id();

		// Obtenemos unidades y peso
		$orderUnits  = 0;
		$orderWeight = 0;

		$wc_weight_unit = get_option('woocommerce_weight_unit');

		// Primera pasada: localizar items padre (packs/composites/bundles) para
		// poder descartar a sus hijos aunque el plugin no añada metas estándar
		// en los items hijos. Soportamos varios plugins habituales:
		//   - WPC Product Bundles / Composite Products: meta de item '_wooco_ids'
		//   - WooCommerce Composite Products:           meta de item '_composite_children'
		//   - WooCommerce Product Bundles:              meta de item '_bundled_items'
		//   - Fallback por tipo de producto:            composite/bundle/woosb/yith_bundle
		$parent_item_ids    = array(); // item_ids de items padre
		$child_product_ids  = array(); // product_ids de posibles hijos (referenciados por el padre)
		foreach ($order->get_items() as $item_id_p => $item_p) {
			if (! $item_p instanceof WC_Order_Item_Product) {
				continue;
			}
			$product_p = $item_p->get_product();
			$product_type_p = $product_p ? $product_p->get_type() : '';

			$meta_wooco     = $item_p->get_meta('_wooco_ids');
			if (empty($meta_wooco)) {
				$meta_wooco = $item_p->get_meta('wooco_ids');
			}
			$meta_composite = $item_p->get_meta('_composite_children');
			if (empty($meta_composite)) {
				$meta_composite = $item_p->get_meta('composite_children');
			}
			$meta_bundle    = $item_p->get_meta('_bundled_items');
			if (empty($meta_bundle)) {
				$meta_bundle = $item_p->get_meta('bundled_items');
			}

			$is_parent_by_meta =
				! empty($meta_wooco) ||
				! empty($meta_composite) ||
				! empty($meta_bundle);

			$is_parent_by_type = in_array(
				$product_type_p,
				array( 'composite', 'bundle', 'woosb', 'yith_bundle', 'mix-and-match' ),
				true
			);

			if ($is_parent_by_meta || $is_parent_by_type) {
				$parent_item_ids[ (int) $item_id_p ] = true;

				// Extraer ids de hijos desde las metas del padre (si existen)
				$ids_from_meta = array();
				foreach (array( $meta_wooco, $meta_composite, $meta_bundle ) as $meta_val) {
					if (empty($meta_val)) {
						continue;
					}
					if (is_array($meta_val)) {
						foreach ($meta_val as $k => $v) {
							// Los valores pueden ser arrays anidados (composite/bundle)
							if (is_array($v) && isset($v['product_id'])) {
								$ids_from_meta[] = (int) $v['product_id'];
							} elseif (is_numeric($k)) {
								$ids_from_meta[] = (int) $k;
							} elseif (is_numeric($v)) {
								$ids_from_meta[] = (int) $v;
							}
						}
					} elseif (is_string($meta_val)) {
						// WPC guarda '_wooco_ids' como "id/qty/price|id/qty/price|..."
						foreach (preg_split('/[|,]/', $meta_val) as $chunk) {
							$first = strtok($chunk, '/');
							if ($first !== false && is_numeric($first)) {
								$ids_from_meta[] = (int) $first;
							}
						}
					}
				}
				foreach (array_filter($ids_from_meta) as $cid) {
					$child_product_ids[ $cid ] = true;
				}
			}
		}

		if (!empty($parent_item_ids) || !empty($child_product_ids)) {
			error_log(sprintf(
				'[CorreosOficial][WeightCalc] order_id=%d detected parents_item_ids=[%s] child_product_ids=[%s]',
				$order->get_id(),
				implode(',', array_keys($parent_item_ids)),
				implode(',', array_keys($child_product_ids))
			));
		}

		foreach ($order->get_items() as $item_id => $item) {
			// Asegurarse de que solo trabajamos con elementos de línea de producto
			if (! $item instanceof WC_Order_Item_Product) {
				continue;
			}

			$product  = $item->get_product();

			// Volcar metas del item para poder identificar el vínculo padre/hijo
			// que use el plugin de composites si la detección estándar falla.
			$meta_keys = array();
			foreach ($item->get_meta_data() as $meta) {
				$md = $meta->get_data();
				if (isset($md['key'])) {
					$meta_keys[] = $md['key'];
				}
			}

			// Saltar items HIJO de composites/bundles: su peso ya está incluido
			// en el del producto padre (pack/composite) que viaja como un solo bulto.
			//
			// (a) Metadatos estándar que añaden los plugins más comunes al hijo:
			//   - WPC Product Bundles / Composite Products: _wooco_parent_id, _wooco_parent_key
			//   - WooCommerce Composite Products:           _composite_parent, _composite_item
			//   - WooCommerce Product Bundles:              _bundled_by, _bundled_item_id
			// (b) Fallback: si el product_id del item aparece listado como hijo
			//     en la meta del padre detectada en la primera pasada.
			$is_wooco_child     = (bool) (
				$item->get_meta('_wooco_parent_id') || $item->get_meta('_wooco_parent_key') ||
				$item->get_meta('wooco_parent_id')  || $item->get_meta('wooco_parent_key')  ||
				$item->get_meta('wooco_component')
			);
			$is_composite_child = (bool) (
				$item->get_meta('_composite_parent') || $item->get_meta('_composite_item') ||
				$item->get_meta('composite_parent')  || $item->get_meta('composite_item')
			);
			$is_bundle_child    = (bool) (
				$item->get_meta('_bundled_by') || $item->get_meta('_bundled_item_id') ||
				$item->get_meta('bundled_by')  || $item->get_meta('bundled_item_id')
			);
			$is_child_by_parent_list = $product && isset($child_product_ids[ (int) $product->get_id() ])
				&& ! isset($parent_item_ids[ (int) $item_id ]);

			if ($is_wooco_child || $is_composite_child || $is_bundle_child || $is_child_by_parent_list) {
				continue;
			}

			$quantity = (int) $item->get_quantity();

			// Acumular siempre las unidades (aunque el producto no tenga peso definido)
			$orderUnits += $quantity;

			// Leer peso directamente desde _weight en post_meta para evitar
			// que plugins composite (WPC, SomewhereWarm) modifiquen el valor
			// a través del filtro woocommerce_product_get_weight.
			$weight = 0;
			$raw_weight = '';
			$weight_source = 'none';
			if ($product) {
				$raw_weight = get_post_meta($product->get_id(), '_weight', true);
				$weight_source = 'product:' . $product->get_id();
				// Si es variación sin peso propio, heredar del producto padre
				if (($raw_weight === '' || $raw_weight === false) && $product->is_type('variation')) {
					$raw_weight = get_post_meta($product->get_parent_id(), '_weight', true);
					$weight_source = 'parent:' . $product->get_parent_id();
				}
				$weight = ($raw_weight !== '' && $raw_weight !== false) ? (float) $raw_weight : 0;
			}

			$weight_raw_unit = $weight;

			if ($weight > 0) {
				// Conversión de unidades de peso a kilogramos
				switch ($wc_weight_unit) {
					case 'g':
						$weight = $weight * 0.001;
						break;
					case 'lbs':
						$weight = $weight * 0.45359237;
						break;
					case 'oz':
						$weight = $weight * 0.0283495;
						break;
				}

				$orderWeight += $weight * $quantity;
			}
		}

		$orderWeight_before_default = $orderWeight;
		$orderWeight = round($orderWeight, 2);

		// Si no hay peso calculado y el peso por defecto está activo
		$applied_default_weight = false;
		if ($orderWeight == 0 && $ActivateWeightByDefault && !$require_customs_doc) {
			$orderWeight = $weight_by_default;
			$applied_default_weight = true;
		}

		// added_values: ocultamos el número de IBAN excepto últimos 4 dígitos
		if (isset($correos_order['added_values_cash_on_delivery_iban'])) {
			$BankIni = substr($correos_order['added_values_cash_on_delivery_iban'], 0, -4);
			$BankFin = substr($correos_order['added_values_cash_on_delivery_iban'], -4);
			$correos_order['added_values_cash_on_delivery_iban'] = str_repeat('*', strlen($BankIni)) . $BankFin;
		}

		// Calculamos valor
		if ($bultos > 1) {
			$orderTotalValue = '';
		} else {
			$orderTotalValue = $order->get_subtotal();
		}

		// Url acceso a settings desde pedido
		$shop_admin_url = admin_url();
		$slug = 'admin.php?page=settings';
		$co_url_settings = $shop_admin_url . $slug;

		// Si no están definidas las definimoas a blanco
		$correos_order['shipping_number'] = isset($correos_order['shipping_number']) ? $correos_order['shipping_number'] : '';
		$correos_order['pickup_number'] = isset($correos_order['pickup_number']) ? $correos_order['pickup_number'] : '';
		$correos_order['AT_code'] = isset($correos_order['AT_code']) ? $correos_order['AT_code'] : '';

		$correos_return = !$correos_return ? array( 'shipping_number' => '' ) : $correos_return;

		$carrier_type = isset($correos_return['carrier_type']) ? $correos_return['carrier_type'] : 'Correos';

		$address_paq = isset($address_paq) ? $address_paq : array();
		$address_paq['dir_paq'] = isset($address_paq['dir_paq']) ? $address_paq['dir_paq'] : '';
		$address_paq['loc_paq'] = isset($address_paq['loc_paq']) ? $address_paq['loc_paq'] : '';
		$address_paq['cp_paq'] = isset($address_paq['cp_paq']) ? $address_paq['cp_paq'] : '';

		$pickup_return_data_response['status'] = isset($pickup_return_data_response['status']) ? $pickup_return_data_response['status'] : '';
		$pickup_return_data_response['pickup_date'] = isset($pickup_return_data_response['pickup_date']) ? $pickup_return_data_response['pickup_date'] : '';
		$pickup_return_data_response['pickup_address'] = isset($pickup_return_data_response['pickup_address']) ? $pickup_return_data_response['pickup_address'] : '';
		$pickup_return_data_response['pickup_city'] = isset($pickup_return_data_response['pickup_city']) ? $pickup_return_data_response['pickup_city'] : '';
		$pickup_return_data_response['pickup_cp'] = isset($pickup_return_data_response['pickup_cp']) ? $pickup_return_data_response['pickup_cp'] : '';

		$pickup_to = isset($pickup_to) ? $pickup_to : '';
		$pickup_from = isset($pickup_from) ? $pickup_from : '';

		// Asignamos datos a la plantilla
		$this->smarty->assign('show_sender_modal', $showSenderModal);
		$this->smarty->assign('error_sender_name', $errorSenderName);
		$this->smarty->assign('error_company_name', $errorCompanyName);

		$this->smarty->assign('active_client', $active_client);
		$this->smarty->assign('order', $order);
		$this->smarty->assign('order_number', $order_number);
		$this->smarty->assign('order_reference', $order_reference);
		$this->smarty->assign('order_id', $order->get_id());
		$this->smarty->assign('orderTotalValue', $orderTotalValue);
		$this->smarty->assign('order_done', $order_done);
		$this->smarty->assign('exist_return', $exist_return);
		$this->smarty->assign('correos_order', $correos_order);
		$this->smarty->assign('correos_return', $correos_return);
		$this->smarty->assign('pickup_return_number', $return_pickup_number);

		$this->smarty->assign('carrier_type', $carrier_type);

		$this->smarty->assign('array_packages_order', $array_packages_order);
		$this->smarty->assign('array_packages_return', $array_packages_return);

		$this->smarty->assign('cash_on_delivery', $cash_on_delivery);
		$this->smarty->assign('cash_on_delivery_value', $cash_on_delivery_value);

		$this->smarty->assign('customer_message', $customer_message);
		
		if ( isset($correos_order['shipping_number']) && $correos_order['shipping_number'] == '' ) {
			$carriers = array_filter($carriers, function($carrier) {
				return $carrier['id'] != 26;
			});
		}

		$this->smarty->assign('carriers', $carriers);
		$this->smarty->assign('id_zone', $id_zone);

		$this->smarty->assign('carrier_order', $carrier_order);

		$this->smarty->assign('client_code', $client_code);

		if ($default_sender && !empty($default_sender['sender_to_time'])) {
			$default_sender['sender_to_time_timestamp'] = strtotime($default_sender['sender_to_time']);
		}
		
		$this->smarty->assign('default_sender', $default_sender);
		$this->smarty->assign('senders', $senders);

		$this->smarty->assign('customer', $customer);
		$this->smarty->assign('address', $address);
		$this->smarty->assign('countries', $countries);

		$this->smarty->assign('pickup', $pickup);

		$this->smarty->assign('pickup_data_response', $pickup_data_response);
		$this->smarty->assign('pickup_cancelable', $pickup_cancelable);

		$this->smarty->assign('order_returnable', $order_returnable);

		$this->smarty->assign('pickup_return', $pickup_return);
		$this->smarty->assign('saved_return_pickup', $saved_return_pickup);
		$this->smarty->assign('pickup_return_data_response', $pickup_return_data_response);
		$this->smarty->assign('pickup_return_cancelable', $pickup_return_cancelable);

		$this->smarty->assign('cancelable', $cancelable);
		$this->smarty->assign('delivered', $delivered);

		$this->smarty->assign('return_cancelable', $return_cancelable);

		$this->smarty->assign('return_status', $return_status);

		// Asignar datos de devoluciones filtrados por país
		$this->smarty->assign('order_returnable', $order_returnable);
		$this->smarty->assign('available_return_methods', $available_return_methods);
		$this->smarty->assign('return_products_filtered', $return_products_filtered);
		$this->smarty->assign('customer_country', $customer_country);
		$this->smarty->assign('returns_supported_country', CorreosOficialReturnsShippingMethods::isReturnsSupportedCountry($customer_country));

		$this->smarty->assign(
			'select_label_options', array(
			LABEL_TYPE_THERMAL => __('Thermic', 'correosoficial'),
			LABEL_TYPE_ADHESIVE => __('Adhesive', 'correosoficial'),
			/* LABEL_TYPE_HALF     => __('Half sheet', 'correosoficial'), */
			)
		);

		$this->smarty->assign('DefaultLabel', $DefaultLabel);
		$company = sanitize_text_field(isset($carrier_order['company']) ? $carrier_order['company'] : '');

		$this->smarty->assign(
			'select_label_options_format', array(
			LABEL_FORMAT_STANDAR => __('Standar', 'correosoficial'),
			LABEL_FORMAT_3A4 => __('3/3A (Only CEX)', 'correosoficial'),
			/* LABEL_FORMAT_4A4     => __('4/3A (Only CEX)', 'correosoficial') */
			)
		);

		$this->smarty->assign('DefaultLabel', $DefaultLabel);
		$this->smarty->assign('bank_acc_number', $bank_acc_number);

		$this->smarty->assign('bultos', $bultos);
		$this->smarty->assign('bultos_return', $bultos_return);
		$this->smarty->assign('orderWeight', $orderWeight);

		// comprobamos si el carier está dentro de los disponibles para las dimiensiones por defecto
		$carriers_default_dimensions = array( 'S0179', 'S0176', 'S0178', '18' );

		$codigoProducto = ( !isset($carrier_order) ) ? $correos_order['codigoProducto'] : $carrier_order['codigoProducto'];
		$available_carrier_d = ( $codigoProducto !== null && in_array($codigoProducto, $carriers_default_dimensions ) ) ? 1 :0;

		$this->smarty->assign('available_carrier_default_dimensions', $available_carrier_d);
		$this->smarty->assign('height_by_default', $height_by_default);
		$this->smarty->assign('large_by_default', $large_by_default);
		$this->smarty->assign('width_by_default', $width_by_default);

		$this->smarty->assign('orderUnits', $orderUnits);
		$this->smarty->assign('ship_reference', $ship_reference);
		$this->smarty->assign('google_maps_api', $google_maps_api);

		$this->smarty->assign('require_customs_doc', $require_customs_doc);
		$this->smarty->assign("return_require_customs_doc", $return_require_customs_doc);
		$this->smarty->assign('is_international', $is_international);
		$this->smarty->assign('config_default_aduanera', $config_default_aduanera);

		$this->smarty->assign('customs_desc_array', $customs_desc_array);
		$this->smarty->assign('customs_desc_selected', $customs_desc_selected);
		// Si se usa el HS code del producto, no precargar los valores por defecto del arancelario
		if ($product_radio == 'on') {
			$customs_tariff_selected = '';
			$customs_tariff_description = '';
		}

		$this->smarty->assign('customs_tariff_selected', $customs_tariff_selected);
		$this->smarty->assign('customs_tariff_description', $customs_tariff_description);
		$this->smarty->assign('customs_reference', $customs_reference);
		$this->smarty->assign("CountryOriginByDefault", $CountryOriginByDefault);

	 	$this->smarty->assign("customer_country", $customer_country);
        $this->smarty->assign("cart_total", $total_value_products);

		$this->smarty->assign('address_paq', $address_paq);
		$this->smarty->assign('cod_office', $cod_office);
		$this->smarty->assign('cod_homepaq', $cod_homepaq);
		$this->smarty->assign("cod_pudocex", $cod_pudocex);
		
		// Assign pickup point data for JavaScript
		$this->smarty->assign('reference_code', (isset($request_data) && isset($request_data['reference'])) ? $request_data['reference'] : '');
		$this->smarty->assign('request_data', (isset($request_data) && isset($request_data['data'])) ? json_encode($request_data['data']) : '');

		// Construir los datos de aduana de productos solo si el mapeo o la casilla de funcionalidades del módulo está activada.
		$products_customs_data = array();
		$mappedHsFeature = ( new CorreosOficialConfig('MappedHsFeature') )->get_value();
		$mappedOriginFeature = ( new CorreosOficialConfig('MappedOriginFeature') )->get_value();
		$useModuleFeatures = ( new CorreosOficialConfig('UseModuleFeatures') )->get_value();

		// Generar siempre los datos de aduanas de productos.
		// El modo activo (descripción, HS por defecto, HS del producto) determina
		// qué valores se usan para hs_code y country_origin dentro del bucle.
		if (isset($order) && $order instanceof WC_Order) {
			foreach ($order->get_items() as $item) {
				if (! $item instanceof WC_Order_Item_Product) {
						continue;
					}
					$product = $item->get_product();
					if (! $product) {
						continue;
					}

					// Saltar items PADRE de composites/bundles en aduanas
					// (no filtrar por tipo de producto: un hijo puede ser wooco/composite)
					if ($item->get_meta('_wooco_ids') || $item->get_meta('_composite_children') || $item->get_meta('_bundled_items')) {
						continue;
					}

					$quantity = (int) $item->get_quantity();
					// Precio total de la línea (precio × cantidad)
					$price = (float) $item->get_total();
					if ($price <= 0) {
						$price = (float) $product->get_price() * $quantity;
					}
					// Peso total en Kg (peso unitario × cantidad)
					// Leer peso desde _weight en post_meta (evita filtros de plugins composite)
					$weight = 0.0;
					$raw_w = get_post_meta($product->get_id(), '_weight', true);
					if (($raw_w === '' || $raw_w === false) && $product->is_type('variation')) {
						$raw_w = get_post_meta($product->get_parent_id(), '_weight', true);
					}
					if ($raw_w !== '' && $raw_w !== false) {
						$weight = (float) $raw_w;

						switch (get_option('woocommerce_weight_unit')) {
							case 'g':
								$weight = $weight * 0.001;
								break;
							case 'lbs':
								$weight = $weight * 0.45359237;
								break;
							case 'oz':
								$weight = $weight * 0.0283495;
								break;
						}
						// Peso total = peso unitario × cantidad
						$weight = $weight * $quantity;
					}
					// Código HS y país de origen
					$hs_code = '';
					$country_origin = '';

					if ($tariff_radio == 'on') {
						// Modo "Código HS por defecto": usar siempre los valores de configuración
						$hs_code = $customs_tariff_selected;
						$country_origin = isset($CountryOriginByDefault) ? $CountryOriginByDefault : '';
					} else if ($product_radio != 'on') {
						// Modo "Descripción aduanera por defecto": usar el value de la descripción aduanera seleccionada
						$hs_code = $customs_desc_selected;
						$country_origin = isset($CountryOriginByDefault) ? $CountryOriginByDefault : '';
					} else {
						// Modo producto o mapeo: obtener de atributos del producto
						$attributes_to_check = $product->get_attributes();
						if ($product->is_type('variation')) {
							$parent_product = wc_get_product($product->get_parent_id());
							if ($parent_product) {
								$attributes_to_check = array_merge($attributes_to_check, $parent_product->get_attributes());
							}
						}
						
						foreach ($attributes_to_check as $attr_name => $attr_value) {
							$attr_name_lower = strtolower($attr_name);
							$attr_value_clean = '';
							
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
							
							if (empty($country_origin) && (strpos($attr_name_lower, 'country') !== false || strpos($attr_name_lower, 'origin') !== false || strpos($attr_name_lower, 'pais') !== false)) {
								$country_origin = $attr_value_clean;
							}
						}

						if (empty($country_origin)) {
							$country_origin = isset($CountryOriginByDefault) ? $CountryOriginByDefault : '';
						}
					}

					$products_customs_data[] = array(
						'hs_code' => $hs_code ? $hs_code : '-',
						'product_name' => $product->get_name(),
						'price' => round((float) $price, 2),
						'weight' => round((float) $weight, 2),
						'quantity' => $quantity,
						'country_origin' => $country_origin,
					);
				}
			}
			
		$this->smarty->assign('products_customs_data', $products_customs_data);

		// Exponer flags de mapeo/configuración a la plantilla para que la UI de pedidos admin pueda reaccionar
		$this->smarty->assign('MappedHsFeature', isset($mappedHsFeature) ? $mappedHsFeature : '');
		$this->smarty->assign('MappedOriginFeature', isset($mappedOriginFeature) ? $mappedOriginFeature : '');
		$this->smarty->assign('UseModuleFeatures', isset($useModuleFeatures) ? $useModuleFeatures : '');

		$this->smarty->assign('is_code_at', $is_code_at);

		$this->smarty->assign('co_base_dir', $co_module_url_wc);
		$this->smarty->assign('co_url_settings', $co_url_settings);
		
		// copy data button (office/citypaq)
		$contentCopied = __('Content copied to clipboard', 'correosoficial');
		$co_titleAddress = __('"Address: "', 'correosoficial');
		$co_titleCity = __('"City: "', 'correosoficial');
		$co_titleCp = __('"CP: "', 'correosoficial');

		$this->smarty->assign('contentCopied', $contentCopied);
		$this->smarty->assign('co_titleAddress', $co_titleAddress);
		$this->smarty->assign('co_titleCity', $co_titleCity);
		$this->smarty->assign('co_titleCp', $co_titleCp);
	
	
		$atention = __('"Atention"', 'correosoficial');
		$messageWrongLabelFormat = __('"The selected format is only available for CEX "', 'correosoficial');
		$cancelOrderStr = __('"Cancel Order"', 'correosoficial');
		$cancelStr = __('"Cancel"', 'correosoficial');

		$this->smarty->assign('atention', $atention);
		$this->smarty->assign('cancelOrderStr', $cancelOrderStr);
		$this->smarty->assign('cancelStr', $cancelStr);
		$this->smarty->assign('messageWrongLabelFormat', $messageWrongLabelFormat); 
		$this->smarty->assign('sga_module', false);
		$this->smarty->assign('sga_id_order', '');
		
		$analitica = new Analitica();

		$vars = array();

		if (isset($_POST['gdpr_nonce'])) {
			$gdprNonce = sanitize_text_field( $_POST['gdpr_nonce'] );
			if (wp_verify_nonce($gdprNonce, 'gdpr_nonce')) {
				$vars = $_POST;
			}
		}

		$gdpr = $analitica->gdpr($vars);
		
		// Asignar variables de fecha y hora para la plantilla
		$this->smarty->assign('current_date', date('Y-m-d'));
		$this->smarty->assign('tomorrow_date', date('Y-m-d', strtotime('+ 1 day')));
		$this->smarty->assign('current_time_timestamp', strtotime(date('H:i:s')));
		
		$template = 'hook/admin-order.tpl';
		if ($gdpr) {
			$template = 'admin/correosGdpr.tpl';
			$this->smarty->assign('gdpr_nonce', wp_create_nonce( 'gdpr_nonce' ));
		}

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);
		$this->smarty->display($this->plugin_dir . 'views/templates/' . $template);
	}

	private function isOrderReturnable( $carrier_order, $customer_country ) {
		// Comprobación envío admite devolución
		$order_returnable = false;

		// Para cualquier transportista ajeno a Correos/CEX
		if (empty($carrier_order['company'])) {
			return true;
		} elseif ($carrier_order['company'] == 'CEX') {
			// CEX admite ES/AD
			if ($customer_country == 'ES' || $customer_country == 'PT') {
				$order_returnable = true;
			}
		} elseif ($carrier_order['company'] == 'Correos') {
			// Correos admite ES/PT
			if ($customer_country == 'ES' || $customer_country == 'AD') {
				$order_returnable = true;
			}
		}

		return $order_returnable;
	}

	private function isATCode( $carrier_order, $address, $default_sender ) {
		// CódigoAT -> Exclusivo CEX
		if ($default_sender && $carrier_order['company'] == 'CEX'
		&& $address->id_country == 'PT' && $default_sender['sender_iso_code_pais'] == 'PT') {
			return true;
		}
	
		return false;
	}
	

	private function getCarrierOrder( $id_zone, $order_id_carrier, $correos_order ) {
		$carrier = array(
			'id_carrier' => null,
			'codigoProducto' => null,
			'product_type' => null,
			'company' => null,
		);
		if ($id_zone >= 0) {
			$id_carrier_product = CorreosOficialCarrier::getCarriersProducts($order_id_carrier, $id_zone);

			// Si ha cambiado de zona (CP y Provincia) y ha sido preregistrado
			if (empty($id_carrier_product) && isset($correos_order['id_product'])) {
				$id_carrier_product[0]['id_product'] = $correos_order['id_product'];
			}

			// Si es un tranporsita externo
			if (empty($id_carrier_product)) {
				return $carrier;
			} else {
				$carrier_order = CorreosOficialCarrier::getCarrierByProductId($id_carrier_product[0]['id_product'], $id_zone);
			}
		} elseif (empty($correos_order)) {
				$carrier_order = CorreosOficialCarrier::getCarrier($order_id_carrier);
		} elseif ($correos_order['shipping_number'] != '') {
				$carrier_order = CorreosOficialCarrier::getCarrier($correos_order['id_carrier']);
		} else {
			$carrier_order = CorreosOficialCarrier::getCarrier($order_id_carrier);
		}

		return $carrier_order;
	}

	/**
	 * Devuelve el peso total del carrito
	 *
	 * @param  array $items Array con los elementos del carrito
	 * @return float Devuelve el peso del carrito en Kg.
	 */
	private static function getTotalWeightCart( $items ) {

		$order_weight = 0;
		$totalWeight = 0;

		/* Calculamos peso */
		foreach ($items as $item) {

			if ($item['product_id'] > 0) {
				$product = $item->get_product();

				// Saltar items PADRE de composites/bundles
				// (no filtrar por tipo de producto: un hijo puede ser wooco/composite)
				if ($item->get_meta('_wooco_ids') || $item->get_meta('_composite_children') || $item->get_meta('_bundled_items')) {
					continue;
				}

				if (!$product->is_virtual()) {
					// Leer peso desde _weight en post_meta (evita filtros de plugins composite)
					$raw = get_post_meta($product->get_id(), '_weight', true);
					if (($raw === '' || $raw === false) && $product->is_type('variation')) {
						$raw = get_post_meta($product->get_parent_id(), '_weight', true);
					}
					$weight = ($raw !== '' && $raw !== false) ? (float) $raw : 0;

					$order_weight += $weight * $item['qty'];
				}
			}
		}

		$totalWeight = $totalWeight + $order_weight;

		return $totalWeight;
	}

	private static function getPickUpDataResponse( $status, $cod_status = '', $pickup_date = '' ) {
		return array(
			'codEstado' => $cod_status,
			'status' => $status,
			'pickup_reference' => '',
			'pickup_date' => $pickup_date,
			'pickup_from_hour' => '',
			'pickup_to_hour' => '',
			'pickup_address' => '',
			'pickup_city' => '',
			'pickup_cp' => '',
		);
	}

	private static function returnPickupLastStatus( $array_status ) {
		$last_status = '';
		$count = 1;

		if (is_array($array_status) || is_object($array_status)) {

			$count = count($array_status->ns3TrazaSolicitudRecogidaEsporadica);

			if ($count == 1) {
				$last_status = end($array_status); //Nos quedamos con estado único
			} else {
				$last_status = $array_status->ns3TrazaSolicitudRecogidaEsporadica[$count - 1]; //Nos quedamos con el último estado
			}
		}
		return $last_status;
	}

	/**
	 * Returns true when the global Marketplace mode is enabled.
	 * In that case every order in WC admin shows the Marketplace tracking block
	 * (same behaviour as PrestaShop: when Marketplace is active, all orders
	 * are handled by the Marketplace channel).
	 */
	private function isMarketplaceOrder() {
		return CorreosOficialMarketplace::isMarketplaceEnabled();
	}

	/**
	 * Renders the Marketplace order tracking block.
	 *
	 * Shows only the shipment-status DataTable (no pre-registration, no returns).
	 * The tracking number is read from WC order meta set by the Marketplace API
	 * (CorreosOficialMarketplace::META_KEY_TRACKING_NUMBER). If the meta is
	 * still empty, the template shows a waiting placeholder instead.
	 *
	 * HPOS-aware: reads order ID from $_GET['id'] when HPOS is enabled,
	 * otherwise from the global $post.
	 */
	private function marketplaceOrderTracking() {
		global $post;

		if ($this->is_wc_order_hpos_enabled()) {
			$id_order = CorreosOficialNormalization::normalizeData('id');
		} else {
			$id_order = $post->ID;
		}

		$wc_order        = wc_get_order($id_order);
		$tracking_number = $wc_order ? (string) $wc_order->get_meta(CorreosOficialMarketplace::META_KEY_TRACKING_NUMBER) : '';

		$this->smarty->assign('marketplace_id_order',        (int) $id_order);
		$this->smarty->assign('marketplace_tracking_number', $tracking_number);
		$this->smarty->assign('co_base_dir',                 plugin_dir_url(WP_PLUGIN_DIR . '/correosoficial/correosoficial.php'));

		$template_path = $this->plugin_dir . 'views/templates/hook/adminOrderMarketplaceTracking.tpl';

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);
		$this->smarty->display($template_path);
	}

	private function isSGAOrder() {
		global $post;
		
       	if ($this->is_wc_order_hpos_enabled()) {
			$id_order = CorreosOficialNormalization::normalizeData('id');
		} else {
			$id_order = $post->ID;
		}
		
        // Comprobamos si SGA está activo.
        $SGAIsActive = ( new CorreosOficialConfig('ActivateSGA') )->get_value();

        if ($SGAIsActive  != 'on') {
            return false;
        }

        $process_status = ( new CorreosOficialConfig('SGAProcessStatus') )->get_value();
        $process_status = str_starts_with($process_status, 'wc-') ? substr($process_status, 3) : $process_status;
        
        $wc_order = wc_get_order($id_order);
        
        if (!$wc_order) {
            return false;
        }
        
        // pedido de logistica
        $sga_order_log = new CorreosOficialSGAOrdersLog($id_order);
        $sga_has_logs = $sga_order_log->getLogsByIdOrder();
        
        $correos_exists = CorreosOficialOrder::exists($id_order);

		// Comprobamos si es un pedido SGA y NO exista en la tabla de Correos (para distinguir pedidos SGA de pedidos normales)
        if (!empty($sga_has_logs) && !$correos_exists ) {
			return true;
		} else {
			return false;
		}
	}

	private function correosecomsgaOrderTracking() {
		global $post;
		
		// Order
		if ($this->is_wc_order_hpos_enabled()) {
			$id_order = CorreosOficialNormalization::normalizeData('id');
		} else {
			$id_order = $post->ID;
		}

		$wc_order = new WC_Order($id_order);
		
		$shipping_methods = $wc_order->get_shipping_methods();

		$carrier_id = null;
		foreach ( $shipping_methods as $method ) {
			$carrier_id  = $method->get_instance_id();
		}

		$company = 'Correos';
		if ($carrier_id) {
			$co_product = (new CorreosOficialProduct())->get_by_carrier($carrier_id);
			if ($co_product) {
				$company = $co_product->get_company();
			}
		}

		// Fallback para pedidos migrados: obtener company desde la tabla de estados SGA
		if ($company === 'Correos' && $carrier_id === null) {
			$sga_status = new CorreosOficialSgaOrdersStatus($id_order);
			$transport = $sga_status->get_company_transport();
			if ($transport == 22) {
				$company = 'CEX';
			}
		}

		$this->smarty->assign('sga_id_order', $wc_order->get_id());
		$this->smarty->assign('sga_module', true);
		$this->smarty->assign('sga_order_company', $company);
		
		$template_path = $this->plugin_dir . 'views/templates/hook/adminOrderTracking.tpl';

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);

		$this->smarty->display($template_path);
	}
}
