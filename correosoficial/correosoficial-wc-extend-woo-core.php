<?php
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialRequests;

/**
 * Shipping Workshop Extend WC Core.
 */
class CorreosOficial_Wc_Extend_Woo_Core {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	private $name = 'correosoficial';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public function init() {
		$this->save_pickup_location();
		$this->handle_shipping_method_change_in_admin();
		// $this->show_shipping_instructions_in_order();
		// $this->show_pickup_location_confirmation();
		// $this->show_shipping_instructions_in_order_email();
	}


	/**
	 * Register shipping workshop schema into the Checkout endpoint.
	 *
	 * @return array Registered schema.
	 */
	public function extend_checkout_schema() {

		return array(
			'selectedPickupLocationOption' => array(
				'description' => 'Pickup location selected by the user',
				'type' => 'object',
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
				'arg_options' => array(
					'validate_callback' => function ( $value ) {
						return true;
					},
				),
			),
			'nifCode' => array(
				'description' => 'Cutomer nif code',
				'type' => 'string',
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
				'arg_options' => array(
					'validate_callback' => function ( $value ) {
						return true;
					},
				),
			),
		);
	}

	/**
	 * Saves the shipping instructions to the order's metadata.
	 *
	 * @return void
	 */
	private function save_pickup_location() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			function ( \WC_Order $order, \WP_REST_Request $request ) {
				$this->validate_cod_for_pce_pickup_location($order, $request);

				$id_order = $order->get_id();
				$id_cart = $order->get_cart_hash();

				$nifCode = isset($request['extensions'][$this->name]['nifCode']) ? $request['extensions'][$this->name]['nifCode'] : '';
				$selectedReference = isset($request['extensions'][$this->name]['selectedPickupLocationOption']['reference']) ? $request['extensions'][$this->name]['selectedPickupLocationOption']['reference'] : '';

				if ($order->save()) {

					if ($nifCode) {
						$order->update_meta_data('NIF', $nifCode);
						update_post_meta($id_order, 'NIF', $nifCode);
					}

					$shipping_methods = $order->get_shipping_methods();
					$instance_id = 0;

					foreach ( $shipping_methods as $method ) {
						$instance_id = $method->get_instance_id();
					}

					$co_product = $instance_id ? (new CorreosOficialProduct())->get_by_carrier($instance_id) : null;

					if ($selectedReference && $co_product && (
						$co_product->get_product_type() == 'office' || 
						$co_product->get_product_type() == 'citypaq' || 
						$co_product->get_product_type() == 'pudocex' )
					) {
						$selectedReferenceData = json_encode($request['extensions'][$this->name]['selectedPickupLocationOption']['data']);
						CorreosOficialRequests::insert_reference_code_with_order_id($id_cart, $selectedReference, $selectedReferenceData, $id_order);
						
						// Guardar temporalmente los datos del punto de recogida para actualizarlos después de la validación
						$order->update_meta_data('_correosoficial_pending_pickup_location', $selectedReferenceData);
						$order->update_meta_data('_correosoficial_pending_pickup_reference', $selectedReference);
						$order->save();
					}
				}
			},
			10,
			2
		);
		
		// Hook que se ejecuta DESPUÉS de la validación, para actualizar la dirección de envío
		add_action(
			'woocommerce_store_api_checkout_order_processed',
			function ( \WC_Order $order ) {
				$pending_location_data = $order->get_meta('_correosoficial_pending_pickup_location', true);
				$pending_reference = $order->get_meta('_correosoficial_pending_pickup_reference', true);
				
				if ($pending_location_data && $pending_reference) {
					$location_data = json_decode($pending_location_data, true);
					if ($location_data) {
						$pickup_location = array(
							'reference' => $pending_reference,
							'data' => $location_data
						);
						$this->update_order_shipping_address_with_pickup_location($order, $pickup_location);
					}
					
					// Limpiar los datos temporales
					$order->delete_meta_data('_correosoficial_pending_pickup_location');
					$order->delete_meta_data('_correosoficial_pending_pickup_reference');
					$order->save();
					
					// Limpiar localStorage del navegador para que no se reutilice el punto en otro pedido
					add_action('wp_footer', function() {
						?>
						<script type="text/javascript">
						(function() {
							try {
								localStorage.removeItem('correosoficial_pickup_location');
								console.log('CorreosOficial: localStorage limpiado después de completar pedido');
							} catch (error) {
								console.error('Error limpiando localStorage:', error);
							}
						})();
						</script>
						<?php
					});
				}
			},
			10,
			1
		);
	}

	/**
	 * Bloquea COD en checkout de bloques para puntos oficina con PCE/PCI y Citypaq.
	 *
	 * @param \WC_Order $order Pedido en actualización.
	 * @param \WP_REST_Request $request Request del Store API.
	 * @return void
	 * @throws \WC_REST_Exception Si se intenta usar COD en un punto PCE/PCI o Citypaq.
	 */
	private function validate_cod_for_pce_pickup_location( \WC_Order $order, \WP_REST_Request $request ) {
		$request_params = $request->get_params();
		$extensions = isset($request['extensions'][$this->name]) ? $request['extensions'][$this->name] : array();
		$selected_pickup = isset($extensions['selectedPickupLocationOption']) ? $extensions['selectedPickupLocationOption'] : array();
		$selected_data = isset($selected_pickup['data']) && is_array($selected_pickup['data']) ? $selected_pickup['data'] : array();
		$session_office_pce_active = ( function_exists('WC') && WC()->session )
			? WC()->session->get('correosoficial_office_pce_active', false)
			: false;

		$type_code = isset($selected_data['typeCode']) ? $selected_data['typeCode'] : ( isset($selected_data['type_code']) ? $selected_data['type_code'] : '' );
		$use_pce = isset($selected_data['use_PCE']) ? $selected_data['use_PCE'] : ( isset($selected_data['use_pce']) ? $selected_data['use_pce'] : false );

		$is_type_pce = in_array(strtoupper((string) $type_code), array('PCE', 'PCI'), true);
		$is_use_pce = in_array(strtolower((string) $use_pce), array('1', 'true', 'yes', 'on'), true) || $use_pce === true || $use_pce === 1 || $session_office_pce_active === true;
		$is_citypaq_pickup = (
			isset($selected_data['terminalId']) ||
			isset($selected_data['cod_homepaq']) ||
			in_array(strtoupper((string) $type_code), array('CITYPAQ', 'CITYPAQ_PREMIUM', 'HOMEPAQ'), true)
		);

		$is_office_carrier = false;
		$is_citypaq_carrier = false;
		$shipping_methods = $order->get_shipping_methods();
		foreach ($shipping_methods as $method) {
			$instance_id = $method->get_instance_id();
			if (!$instance_id) {
				continue;
			}
			$co_product = (new CorreosOficialProduct())->get_by_carrier($instance_id);
			if ($co_product && method_exists($co_product, 'get_product_type')) {
				$product_type = (string) $co_product->get_product_type();
				if ($product_type === 'office') {
					$is_office_carrier = true;
				}
				if ($product_type === 'citypaq') {
					$is_citypaq_carrier = true;
				}
			}
		}

		if (!$is_type_pce && !$is_use_pce && !$is_citypaq_pickup && !$is_office_carrier && !$is_citypaq_carrier) {
			return;
		}

		// Si el punto seleccionado ya viene marcado como PCE/PCI o Citypaq, bloqueamos COD siempre,
		// incluso cuando Woo no permite mapear correctamente el instance_id al product_type.
		if (!$is_type_pce && !$is_use_pce && !$is_citypaq_pickup && !$is_citypaq_carrier) {
			return;
		}

		$payment_method = isset($request['payment_method']) ? sanitize_text_field($request['payment_method']) : '';
		if (empty($payment_method) && isset($request_params['payment_method'])) {
			$payment_method = sanitize_text_field((string) $request_params['payment_method']);
		}
		if (empty($payment_method) && isset($request_params['payment_data']) && is_array($request_params['payment_data']) && isset($request_params['payment_data']['payment_method'])) {
			$payment_method = sanitize_text_field((string) $request_params['payment_data']['payment_method']);
		}
		if (empty($payment_method)) {
			$payment_method = (string) $order->get_payment_method();
		}

		$cod_candidates = CorreosOficialConfig::getConfiguredCodMethodAliases();
		$is_cod_payment = in_array($payment_method, $cod_candidates, true);

		if ($is_cod_payment) {
			throw new \WC_REST_Exception(
				'correosoficial_cod_not_allowed_for_pce',
				__('Cash on delivery is not available for the selected delivery point. Please choose another payment method.', 'correosoficial'),
				400
			);
		}
	}

	/**
	 * Actualiza la dirección de envío del pedido con los datos del punto de recogida
	 *
	 * @param \WC_Order $order El pedido a actualizar
	 * @param array $pickup_location Datos del punto de recogida seleccionado
	 * @return void
	 */
	private function update_order_shipping_address_with_pickup_location( $order, $pickup_location ) {
		// El objeto pickup_location tiene esta estructura:
		// - reference: código del punto de recogida
		// - data: objeto con todos los campos del API
		// Aunque existe normalización en el backend, el objeto que llega aquí
		// solo contiene 'reference' y 'data', por lo que debemos leer desde 'data'
		
		if ( ! isset( $pickup_location['data'] ) ) {
			return;
		}

		// Solo guardar dirección original si no existe ya (evitar sobrescribir)
		if ( ! $order->get_meta( '_correosoficial_original_shipping_address_1', true ) ) {
			$order->update_meta_data( '_correosoficial_original_shipping_address_1', $order->get_shipping_address_1() );
			$order->update_meta_data( '_correosoficial_original_shipping_address_2', $order->get_shipping_address_2() );
			$order->update_meta_data( '_correosoficial_original_shipping_city', $order->get_shipping_city() );
			$order->update_meta_data( '_correosoficial_original_shipping_postcode', $order->get_shipping_postcode() );
			$order->update_meta_data( '_correosoficial_original_shipping_state', $order->get_shipping_state() );
			$order->update_meta_data( '_correosoficial_original_shipping_country', $order->get_shipping_country() );
			$order->update_meta_data( '_correosoficial_original_shipping_company', $order->get_shipping_company() );
			
			// Marcar que este pedido tiene un punto de recogida
			$order->update_meta_data( '_correosoficial_is_pickup_location', 'yes' );
		}
		
		$location_data = $pickup_location['data'];
		
		// Usar la misma lógica que el método normalize() de CorreosOficialRequestsDataStore
		// para soportar múltiples APIs y tipos de puntos:
		// - Correos CityPaq: alias, municipality/desc_localidad, postalCode/cod_postal
		// - Correos Office: unitName/nombre, municipalityName/descLocalidad, postalCode/cp, address/direccion
		// - CEX Office: nombreOficina, poblacionOficina, codigoPostalOficina, direccionOficina
		// - CEX PudoCEX: nombrePtoConv, ciudadPtoConv, codigoPostalPtoConv, direccionPtoConv
		
		$helpers = new \CorreosOficial\Classes\CorreosOficialHelpers();
		
		// Nombre del punto: CityPaq usa 'alias', Office usa 'unitName'/'nombre', CEX Oficinas usa 'nombreOficina', CEX PudoCEX usa 'nombrePtoConv'
		$pickup_name = $helpers::getOneValue( $location_data, 'alias', 'unitName', 'nombre', 'nombreOficina', 'nombrePtoConv' );
		if ( empty( $pickup_name ) ) {
			$pickup_name = '';
		}
		
		$pickup_reference = isset( $pickup_location['reference'] ) ? $pickup_location['reference'] : '';
		
		// Dirección: para CityPaq se construye desde múltiples campos
		// Para Office/CEX viene en un solo campo
		$pickup_address = '';
		
		// Detectar si es CityPaq (tiene terminalId o cod_homepaq)
		$is_citypaq = isset( $location_data['terminalId'] ) || isset( $location_data['cod_homepaq'] );
		
		if ( $is_citypaq ) {
			// Construir dirección de CityPaq igual que en normalize()
			if ( isset( $location_data['location'] ) || isset( $location_data['roadType'] ) || isset( $location_data['addressName'] ) ) {
				// Formato API P3
				$address_parts = array();
				
				if ( ! empty( $location_data['roadType'] ) ) {
					$address_parts[] = $location_data['roadType'];
				}
				if ( ! empty( $location_data['addressName'] ) ) {
					$address_parts[] = $location_data['addressName'];
				}
				if ( ! empty( $location_data['addressNumber'] ) ) {
					$address_parts[] = $location_data['addressNumber'];
				}
				
				$pickup_address = implode( ' ', $address_parts );
				
				// Añadir detalles adicionales si existen
				$additional_parts = array();
				if ( ! empty( $location_data['portalNumber'] ) ) {
					$additional_parts[] = 'Portal ' . $location_data['portalNumber'];
				}
				if ( ! empty( $location_data['blockNumber'] ) ) {
					$additional_parts[] = 'Bloque ' . $location_data['blockNumber'];
				}
				if ( ! empty( $location_data['stairNumber'] ) ) {
					$additional_parts[] = 'Escalera ' . $location_data['stairNumber'];
				}
				
				if ( ! empty( $additional_parts ) ) {
					$pickup_address .= ', ' . implode( ', ', $additional_parts );
				}
			} else {
				// Formato API Legacy
				$des_via = isset( $location_data['des_via'] ) ? $location_data['des_via'] : '';
				$direccion = isset( $location_data['direccion'] ) ? $location_data['direccion'] : '';
				$numero = isset( $location_data['numero'] ) ? $location_data['numero'] : '';
				$pickup_address = trim( "$des_via $direccion, $numero" );
			}
		} else {
			// Para Office y CEX: buscar en campo directo
			// CEX Oficinas usa 'direccionOficina', CEX PudoCEX usa 'direccionPtoConv'
			$pickup_address = $helpers::getOneValue( $location_data, 'address', 'direccion', 'direccionOficina', 'direccionPtoConv' );
		}
		
		if ( empty( $pickup_address ) ) {
			$pickup_address = '';
		}
		
		// Ciudad: CityPaq usa 'municipality', Office usa 'municipalityName', CEX Oficinas usa 'poblacionOficina', CEX PudoCEX usa 'ciudadPtoConv'
		// IMPORTANTE: poner los campos de CityPaq PRIMERO para que se encuentren antes
		$pickup_city = $helpers::getOneValue( $location_data, 'municipality', 'desc_localidad', 'municipalityName', 'descLocalidad', 'poblacionOficina', 'ciudadPtoConv' );
		if ( empty( $pickup_city ) ) {
			$pickup_city = $order->get_shipping_city();
		}
		
		// Código postal: todos usan 'postalCode' pero con variantes
		// CEX Oficinas usa 'codigoPostalOficina', CEX PudoCEX usa 'codigoPostalPtoConv'
		$pickup_postcode = $helpers::getOneValue( $location_data, 'postalCode', 'cod_postal', 'cp', 'codigoPostalOficina', 'codigoPostalPtoConv' );
		if ( empty( $pickup_postcode ) ) {
			$pickup_postcode = $order->get_shipping_postcode();
		}
		
		// IMPORTANTE: Mantener el nombre del cliente (first_name y last_name) original
		// Solo actualizamos la dirección física del punto de entrega
		
		// Actualizar dirección de envío con los datos del punto de recogida
		// El nombre del cliente (shipping_first_name y shipping_last_name) se mantiene sin cambios
		$order->set_shipping_company( $pickup_name ); // Nombre del punto en empresa
		$order->set_shipping_address_1( $pickup_address ); // Dirección del punto
		$order->set_shipping_city( $pickup_city );
		$order->set_shipping_postcode( $pickup_postcode );
		
		// Mantener el país y estado del envío original
		// $order->set_shipping_country() y $order->set_shipping_state() no se modifican
		// Los campos first_name y last_name tampoco se modifican
		
		$order->save();
	}

	/**
	 * Hook para manejar cambios en el método de envío desde el admin
	 */
	private function handle_shipping_method_change_in_admin() {
		add_action(
			'woocommerce_process_shop_order_meta',
			function ( $order_id, $post ) {
				$order = wc_get_order( $order_id );
				
				if ( ! $order ) {
					return;
				}
				
				// Obtener el método de envío actual
				$shipping_methods = $order->get_shipping_methods();
				$current_instance_id = null;
				$current_product_type = null;
				
				foreach ( $shipping_methods as $method ) {
					$current_instance_id = $method->get_instance_id();
					break;
				}
				
				if ( ! $current_instance_id ) {
					return;
				}
				
				// Verificar si el método actual es un punto de recogida
				$co_product = (new CorreosOficialProduct())->get_by_carrier( $current_instance_id );
				
				if ( $co_product ) {
					$current_product_type = $co_product->get_product_type();
				}
				
				$is_pickup_location = $co_product && in_array( 
					$current_product_type, 
					array( 'office', 'citypaq', 'pudocex' ) 
				);
				
				// Verificar si tenemos dirección original guardada
				$has_original_address = $order->get_meta( '_correosoficial_original_shipping_address_1', true );
				
				// Caso 1: Cambio de punto de recogida a método normal -> Restaurar dirección original
				if ( ! $is_pickup_location && $has_original_address ) {
					$this->restore_original_shipping_address( $order );
				}
			},
			45,
			2
		);
	}

	/**
	 * Restaura la dirección de envío original del cliente
	 *
	 * @param \WC_Order $order El pedido
	 * @return void
	 */
	private function restore_original_shipping_address( $order ) {
		$original_address_1 = $order->get_meta( '_correosoficial_original_shipping_address_1', true );
		$original_address_2 = $order->get_meta( '_correosoficial_original_shipping_address_2', true );
		$original_city = $order->get_meta( '_correosoficial_original_shipping_city', true );
		$original_postcode = $order->get_meta( '_correosoficial_original_shipping_postcode', true );
		$original_state = $order->get_meta( '_correosoficial_original_shipping_state', true );
		$original_country = $order->get_meta( '_correosoficial_original_shipping_country', true );
		$original_company = $order->get_meta( '_correosoficial_original_shipping_company', true );
		
		if ( $original_address_1 ) {
			$order->set_shipping_address_1( $original_address_1 );
			$order->set_shipping_address_2( $original_address_2 );
			$order->set_shipping_city( $original_city );
			$order->set_shipping_postcode( $original_postcode );
			if ( $original_company !== false ) {
				$order->set_shipping_company( $original_company );
			}
			if ( $original_state ) {
				$order->set_shipping_state( $original_state );
			}
			if ( $original_country ) {
				$order->set_shipping_country( $original_country );
			}
			
			// Limpiar los metas de dirección original ya que hemos restaurado
			$order->delete_meta_data( '_correosoficial_original_shipping_address_1' );
			$order->delete_meta_data( '_correosoficial_original_shipping_address_2' );
			$order->delete_meta_data( '_correosoficial_original_shipping_city' );
			$order->delete_meta_data( '_correosoficial_original_shipping_postcode' );
			$order->delete_meta_data( '_correosoficial_original_shipping_state' );
			$order->delete_meta_data( '_correosoficial_original_shipping_country' );
			$order->delete_meta_data( '_correosoficial_original_shipping_company' );
			$order->delete_meta_data( '_correosoficial_is_pickup_location' );
			
			$order->save();
		}
	}

	// /**
	//  * Adds the address on the order confirmation page.
	//  */
	// private function show_pickup_location_confirmation() {

	//  add_action(
	//      'woocommerce_thankyou',
	//      function( int $order_id ) {
	//          $order = wc_get_order( $order_id );
	//          // $shipping_workshop_alternate_shipping_instruction            = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction' );
	//          // $shipping_workshop_alternate_shipping_instruction_other_text = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction_other_text' );

	//          // if ( '' !== $shipping_workshop_alternate_shipping_instruction ) {
	//          //  echo '<h2>' . esc_html__( 'Shipping Instructions', 'shipping-workshop' ) . '</h2>';
	//          //  echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction ) . '</p>';

	//          //  if ( '' !== $shipping_workshop_alternate_shipping_instruction_other_text ) {
	//          //      echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction_other_text ) . '</p>';
	//          //  }
	//          // }

	//      }
	//  );
	// }

	// /**
	//  * Adds the address in the order page in WordPress admin.
	//  */
	// private function show_shipping_instructions_in_order() {
	//  add_action(
	//      'woocommerce_admin_order_data_after_shipping_address',
	//      function( \WC_Order $order ) {
	//          $alternate_shipping_instruction            = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction' );
	//          $alternate_shipping_instruction_other_text = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction_other_text' );

	//          echo '<div>';
	//          echo '<strong>' . esc_html__( 'Shipping Instructions', 'shipping-workshop' ) . '</strong>';
	//          /** 📝 Output the alternate shipping instructions here! */
	//          printf( '<p>%s</p>', esc_html( $alternate_shipping_instruction ) );
	//          if ( 'other' === $alternate_shipping_instruction ) {
	//              printf( '<p>%s</p>', esc_html( $alternate_shipping_instruction_other_text ) );
	//          }
	//          echo '</div>';
	//      }
	//  );
	// }

	// /**
	//  * Adds the address on the order confirmation page.
	//  */
	// private function show_shipping_instructions_in_order_confirmation() {
	//  add_action(
	//      'woocommerce_thankyou',
	//      function( int $order_id ) {
	//          $order = wc_get_order( $order_id );
	//          $shipping_workshop_alternate_shipping_instruction            = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction' );
	//          $shipping_workshop_alternate_shipping_instruction_other_text = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction_other_text' );

	//          if ( '' !== $shipping_workshop_alternate_shipping_instruction ) {
	//              echo '<h2>' . esc_html__( 'Shipping Instructions', 'shipping-workshop' ) . '</h2>';
	//              echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction ) . '</p>';

	//              if ( '' !== $shipping_workshop_alternate_shipping_instruction_other_text ) {
	//                  echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction_other_text ) . '</p>';
	//              }
	//          }
	//      }
	//  );
	// }

	// /**
	//  * Adds the address on the order confirmation email.
	//  */
	// private function show_shipping_instructions_in_order_email() {
	//  add_action(
	//      'woocommerce_email_after_order_table',
	//      function( $order, $sent_to_admin, $plain_text, $email ) {
	//          $shipping_workshop_alternate_shipping_instruction            = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction' );
	//          $shipping_workshop_alternate_shipping_instruction_other_text = $order->get_meta( 'shipping_workshop_alternate_shipping_instruction_other_text' );

	//          if ( '' !== $shipping_workshop_alternate_shipping_instruction ) {
	//              echo '<h2>' . esc_html__( 'Shipping Instructions', 'shipping-workshop' ) . '</h2>';
	//              echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction ) . '</p>';

	//              if ( '' !== $shipping_workshop_alternate_shipping_instruction_other_text ) {
	//                  echo '<p>' . esc_html( $shipping_workshop_alternate_shipping_instruction_other_text ) . '</p>';
	//              }
	//          }
	//      },
	//      10,
	//      4
	//  );
	// }
}
