<?php
use CorreosOficial\Classes\CorreosOficialBridgeWCLanguage;
use CorreosOficial\Classes\CorreosOficialNeedCustoms;
use CorreosOficial\Classes\CorreosOficialUtils;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
use CorreosOficial\Classes\CorreosOficialApiRouter;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialSender;

/**
 * Shipping Workshop Extend Store API.
 */
class CorreosOficial_Wc_Extend_Store_Endpoint {

	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendRestApi
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'correosoficial';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public static function init() {
		self::$extend = Automattic\WooCommerce\StoreApi\StoreApi::container()->get(
			Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class
		);
		self::extendStore();
	}

	/**
	 * Registers the actual data into each endpoint.
	 */
	public static function extendStore()
    {
        if (is_callable([self::$extend, 'register_endpoint_data'])) {
            self::$extend->register_endpoint_data(
                [
                    'endpoint'        => CheckoutSchema::IDENTIFIER,
                    'namespace'       => self::IDENTIFIER,
                    'schema_callback' => ['CorreosOficial_Wc_Extend_Store_Endpoint', 'extendCheckoutSchema'],
                    'schema_type'     => ARRAY_A,
                ]
            );
            self::$extend->register_endpoint_data(
                [
                    'endpoint'        => CartSchema::IDENTIFIER,
                    'namespace'       => self::IDENTIFIER,
                    'schema_callback' => function () {
                        return [
                            'pickup_locations' => [
                                'description' => __('A list of Correos Pick UP Locations.', 'correosoficial'),
                                'type'        => 'array',
                            ],
                            'products'         => [
                                'description' => __('A Correos products list data.', 'correosoficial'),
                                'type'        => 'array',
                            ],
                            'config'           => [
                                'description' => __('Checkout User Config.', 'correosoficial'),
                                'type'        => 'array',
                            ],
                            'cart_items'       => [
                                'description' => __('Dimensions and weights of the products in the cart.', 'correosoficial'),
                                'type'        => 'array',
                            ],
                        ];
                    },
                    'data_callback'   => function () {
						$locations             = array();
						$customs_config        = array();
						$cart_items = array();
						// Cart Items Dimensions
						$cart = WC()->cart;
						if ($cart && $cart->get_cart_contents()) {
							foreach ($cart->get_cart_contents() as $key => $item) {
								$article = $item['data'];
								if ($article instanceof WC_Product) {
									$cart_items[] = array(
										'key'     => $key,
										'name'    => $article->get_name(),
										'length'  => $article->get_length(),
										'width'   => $article->get_width(),
										'height'  => $article->get_height(),
										'weight'  => $article->get_weight(),
										'qty'     => $item['quantity'],
									);
								}
							}
						}
                        // Obtenemos Payload (Igual existe otra manera)
                        $requestPayload = file_get_contents('php://input');
                        $payload        = json_decode($requestPayload, true);
						$disable_cod    = false;
						
                        if ($payload) {
                            $data = isset($payload['requests'][0]['data']) ? $payload['requests'][0]['data'] : '';
                            // Comprobamos si es nuestro namespace y la acción a realizar
                            if (isset($data['namespace']) && $data['namespace'] == self::IDENTIFIER) {
// Detectar si se trata de un pudocex o citypaq
							$selector_type = isset($data['data']['selector_type']) ? strtolower($data['data']['selector_type']) : '';

                                if (isset($data['data']['action']) && $data['data']['action'] === 'search_postal_code') {
									// pudocex y citypaq: siempre ocultar COD
									if ($selector_type === 'pudocex' || $selector_type === 'citypaq') {
										$disable_cod = true;
									}

									// office: PCE activo si sendDelivery=E (default en CorreosOficialRest es 'E')
									if ($selector_type === 'office') {
									}

                                    $locations = self::getPickupLocations($data['data']);
                                    // Asegurar que locations siempre sea un array
                                    if ($locations === false || !is_array($locations) || $locations === null) {
                                        $locations = array();
                                        error_log('[CorreosOficial] getPickupLocations devolvió un valor no válido');
                                    }

									if ($selector_type === 'office') {
										$first_location = !empty($locations[0]) && is_array($locations[0]) ? $locations[0] : array();
										$pce_active = !empty($first_location['use_PCE']) || (isset($first_location['data']['use_PCE']) && $first_location['data']['use_PCE']);
										if ($pce_active) {
											$disable_cod = true;
										}
										if (WC()->session) {
											WC()->session->set('correosoficial_office_pce_active', $pce_active);
										}
									}
                                }
                                if ($data['data']['action'] === 'check_customs') {
                                    $customs_config = self::getCustomsConfig($data['data']['postcode'], $data['data']['country']);
                                }
                            }
                        }

						// Verificar el método de envío seleccionado desde la sesión
						if (!$disable_cod && WC()->session) {
							$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
							if (!empty($chosen_shipping_methods)) {
								$selected_method = $chosen_shipping_methods[0];
								$correos_products = CorreosOficialCarrier::getCarriersProductsList();
								
								// Extraer el carrier_id del formato request_shipping_quote_X:Y donde Y es el carrier_id
								if (preg_match('/:(\d+)$/', $selected_method, $matches)) {
									$selected_carrier_id = $matches[1];
									
									foreach ($correos_products as $product) {
										$carrier_id = isset($product['id_carrier']) ? $product['id_carrier'] : '';
										$product_type = isset($product['product_type']) ? strtolower($product['product_type']) : '';
										
										if ($carrier_id == $selected_carrier_id) {
											if ($product_type === 'pudocex' || $product_type === 'citypaq') {
												$disable_cod = true;
											} elseif ($product_type === 'office') {
												// Solo ocultar COD si la última búsqueda de oficina tenía PCE activo
												$pce_from_session = WC()->session->get('correosoficial_office_pce_active', false);
												if ($pce_from_session) {
													$disable_cod = true;
												}
											}
											break;
										}
									}
								}
							}
						}

                        return [
                            'pickup_locations' => $locations,
                            'products'         => CorreosOficialCarrier::getCarriersProductsList(),
                            'config'           => [
                                'googleApiKey' => CorreosOficialConfig::get_config_status('GoogleMapsApi'),
                                'customs'      => $customs_config,
								'nif'          => CorreosOficialConfig::getConfigValue('ActivateNifFieldCheckout'),
								'nif_required' => CorreosOficialConfig::getConfigValue('NifFieldRadio'),
								'cod_method_id' => CorreosOficialConfig::getConfiguredCodMethod(),
								'cod_method_aliases' => CorreosOficialConfig::getConfiguredCodMethodAliases(),
								'plugin_path'  => plugin_dir_url(__FILE__),
                            ],
							'cart_items' => $cart_items,
							'hide_delivery_payment' => $disable_cod,
                        ];
                    },
                    'schema_type'     => ARRAY_A,
                ]
            );
            // Se ejecuta siempre indistintamente del endpoint
            self::$extend->register_update_callback(
                [
                    'namespace' => self::IDENTIFIER,
                    'callback'  => function ($data) {},
                ]
            );
        }
    }

	private static function getCustomsConfig( $customer_postalcode, $customer_country ) {

		$customs_advice = CorreosOficialConfig::get_config_status('MessageToWarnBuyer');
		$customs_advice_value = is_object($customs_advice) && isset($customs_advice->value) ? $customs_advice->value : $customs_advice;
		
		$customsMessage = CorreosOficialConfig::get_config_status('TranslatableInput');
		$customsMessage_value = is_object($customsMessage) && isset($customsMessage->value) ? $customsMessage->value : $customsMessage;
		
		$iso_code = get_locale();
		$id_lang = CorreosOficialBridgeWCLanguage::getIdLanguageByIsoCode($iso_code);
		$string_translated = CorreosOficialUtils::translateStringsFromDB($customsMessage_value, $id_lang);

		// Default sender
		$default_sender = CorreosOficialSender::get_default_sender();

		$need_customs = CorreosOficialNeedCustoms::isCustomsRequired($default_sender['sender_cp'], $customer_postalcode, $default_sender['sender_iso_code_pais'], $customer_country);

		return array(
			'customs_advice' => ( $customs_advice_value == 'on' ) ? true : false,
			'string_translated' => $string_translated,
			'require_customs_doc' => $need_customs,
		);
	}

	/**
	 * Get Pickup Locations from Correos.
	 *
	 * @param string $value
	 * @param string $selector_type
	 * @return array
	 */
	private static function getPickupLocations( $payload ) {

		$APIRouter = new CorreosOficialApiRouter();

		// PAYLOAD ------------------------------------------------------------------------------------------- //

		// Obtener dimensiones del primer item, con valores por defecto si están vacíos
		$width  = !empty($payload['cart_items'][0]['width']) ? $payload['cart_items'][0]['width'] : 0;
		$length = !empty($payload['cart_items'][0]['length']) ? $payload['cart_items'][0]['length'] : 0;
		$height = !empty($payload['cart_items'][0]['height']) ? $payload['cart_items'][0]['height'] : 0;

		$ActivateDimensionsByDefault = ( new CorreosOficialConfig('ActivateDimensionsByDefault') )->get_value();

		if ( $ActivateDimensionsByDefault == 'on' ) {
			$length = (int) ( new CorreosOficialConfig('DimensionsByDefaultLarge') )->get_value();
			$width  = (int) ( new CorreosOficialConfig('DimensionsByDefaultWidth') )->get_value();
			$height = (int) ( new CorreosOficialConfig('DimensionsByDefaultHeight') )->get_value();
		}

		switch (get_option('woocommerce_weight_unit')) {
			case 'g': 
				$payload['total_weight'] = $payload['total_weight'] * 0.001;
				break;
			case 'lbs':
				$payload['total_weight'] = $payload['total_weight'] * 0.45359237;
				break;
			case 'oz':
				$payload['total_weight'] = $payload['total_weight'] * 0.0283495;
				break;
			case 'kg':
				$payload['total_weight'] = $payload['total_weight'] * 0.001;
				break;
		}

		$payload['total_weight'] = round($payload['total_weight'], 2);

		if ($payload['total_weight'] == 0 && (new CorreosOficialConfig('ActivateWeightByDefault'))->get_value() == 'on') {
			$payload['total_weight'] = floatval(str_replace(',', '.', (new CorreosOficialConfig('WeightByDefault'))->get_value()));
		}

		$payload['insurance_value'] = 0;

		$payload['info_bulto'] = array(
			1 => array(
				'large'  => strVal($length),
				'width'  => strVal($width),
				'height' => strVal($height)
			)
		);
		
		$payload['checkout'] = true;

		$responseGetPickupLocations = $APIRouter->getPickupLocations($payload);

		return $responseGetPickupLocations;
	}

	/**
	 * Register shipping workshop schema into the Checkout endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extendCheckoutSchema() {
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
}
