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
use CorreosOficial\Classes\CorreosOficialApiRouter;
use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialProduct;

if (!defined('WC_VERSION')) {
	die;
}


class CorreosOficialCheckoutModuleFrontController {

	public $cart;

	public function __construct( $action ) {
		$this->initContent($action);
	}

	public function initContent( $action ) {

		switch ($action) {

			case 'SearchCityPaqByPostalCode':
				$this->SearchCityPaqByPostalCode();
				break;
			case 'SearchOfficeByPostalCode':
				$this->SearchOfficeByPostalCode();
				break;
			case 'SearchPudoCEXByPostalCode':
				$this->SearchPudoCEXByPostalCode();
			// Los insert Citypaq y Office no aplican(se hace con el action woocommerce_checkout_order_created)
			case 'insertCityPaq':
			case 'insertOffice':
				break;
			default:
				throw new LogicException('Error 21000: No se ha indicado un "action" para el formulario.');
		}
	}

	public function SearchOfficeByPostalCode() {

		$shortcode = CorreosOficialNormalization::normalizeData('shortcode');
		$order_detail_page = CorreosOficialNormalization::normalizeData('order_detail_page');

        if ($order_detail_page) {
            $checkout = false;
        } else {
            $checkout = true;
        }

		if ($shortcode) {
			$id_carrier = CorreosOficialNormalization::normalizeData('id_carrier');

			if ($id_carrier) {
				$product = (new CorreosOficialProduct())->get_by_carrier($id_carrier);
				if ($product && $product->get_product_type() === 'office') {
					$payload = array(
						'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
						'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
						'company'   => $product->get_company(),
						'sender_id' => 'default',
						'selector_type' => 'office',
						'checkout'      => $checkout
					);
				}
			}
		} else {
			$payload = array(
				'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
				'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
				'company'   => CorreosOficialNormalization::normalizeData('company'),
				'sender_id' => CorreosOficialNormalization::normalizeData('sender_id'),
				'selector_type' => 'office',
				'checkout'      => $checkout
			);
		}
		
		$APIRouter = new CorreosOficialApiRouter();
		$searchOfficeResponse = $APIRouter->getPickupLocations($payload);
		wp_send_json($searchOfficeResponse, 200);
	}

	public function SearchCityPaqByPostalCode() {

		$shortcode = CorreosOficialNormalization::normalizeData('shortcode');
		$order_detail_page = CorreosOficialNormalization::normalizeData('order_detail_page');

        if ($order_detail_page) {
            $checkout = false;
        } else {
            $checkout = true;
        }

		if ($shortcode) {
			$id_carrier = CorreosOficialNormalization::normalizeData('id_carrier');

			if ($id_carrier) {
				$product = (new CorreosOficialProduct())->get_by_carrier($id_carrier);
				if ($product && $product->get_product_type() === 'citypaq') {
					$payload = array(
						'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
						'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
						'company'   => $product->get_company(),
						'sender_id' => 'default',
						'selector_type' => 'citypaq',
						'checkout'  => $checkout
					);
				}
			}
		} else {
			$payload = array(
				'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
				'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
				'company'   =>  CorreosOficialNormalization::normalizeData('company'),
				'sender_id' => CorreosOficialNormalization::normalizeData('sender_id'),
				'selector_type' => 'citypaq',
				'checkout' => $checkout
			);
		}

		$APIRouter = new CorreosOficialApiRouter();
		$searchOfficeResponse = $APIRouter->getPickupLocations($payload);
		wp_send_json($searchOfficeResponse, 200);
	}

	public function SearchPudoCEXByPostalCode() {

		$shortcode = CorreosOficialNormalization::normalizeData('shortcode');
		$order_detail_page = CorreosOficialNormalization::normalizeData('order_detail_page');

        if ($order_detail_page) {
            $checkout = false;
        } else {
            $checkout = true;
        }

		if ($shortcode) {
			$id_carrier = CorreosOficialNormalization::normalizeData('id_carrier');

			$cart = WC()->cart;
			$country = WC()->customer ? WC()->customer->get_shipping_country() : '';
			$cart_total = 0;
			$total_weight = 0;
			$length = 0;
			$width  = 0;
			$height = 0;

			if ($id_carrier) {
				$product = (new CorreosOficialProduct())->get_by_carrier($id_carrier);
				if ($product && $product->get_product_type() === 'pudocex') {

					if ( $cart ) {
						foreach ( $cart->get_cart() as $cart_item ) {
							$article = $cart_item['data'];
							if ( $article instanceof WC_Product ) {
								$length = $article->get_length();
								$width  = $article->get_width();
								$height = $article->get_height();
								break; // Solo el primer producto
							}
						}
					}

					if ( $cart ) {
						$cart_total = $cart->get_subtotal();

						$cart_items = $cart->get_cart();

						// --- Pasada 1: detectar padres Composite con peso propio.
						// Sus hijos (wooco_parent_key === cart_item_key del padre) se saltan.
						$composite_parent_cart_keys = [];
						foreach ( $cart_items as $cart_item_key => $cart_item ) {
							$pre_article = $cart_item['data'] ?? null;
							if ( ! ( $pre_article instanceof WC_Product ) ) {
								continue;
							}
							if ( CorreosOficialHelpers::isCompositeParentItem( $cart_item, $pre_article )
								&& CorreosOficialHelpers::getProductMetaWeight( $pre_article ) > 0 ) {
								$composite_parent_cart_keys[ (string) $cart_item_key ] = true;
							}
						}

						// --- Pasada 2: acumular peso.
						foreach ( $cart_items as $cart_item_key => $cart_item ) {
							$article = $cart_item['data'] ?? null;
							if ( ! ( $article instanceof WC_Product ) ) {
								continue;
							}

							$is_composite_parent = CorreosOficialHelpers::isCompositeParentItem( $cart_item, $article );
							$is_bundle_parent    = CorreosOficialHelpers::isBundleParentItem( $cart_item, $article );
							$parent_meta_weight  = $is_composite_parent ? CorreosOficialHelpers::getProductMetaWeight( $article ) : 0.0;

							// Caso A: PADRE Composite con _weight > 0 → usa su peso.
							if ( $is_composite_parent && $parent_meta_weight > 0 ) {
								$total_weight += $parent_meta_weight * intval( $cart_item['quantity'] );
								continue;
							}

							// Caso B: PADRE Composite sin peso, o PADRE Bundle → skip.
							if ( $is_composite_parent || $is_bundle_parent ) {
								continue;
							}

							// Hijo de un Composite cuyo padre YA aportó su peso → skip.
							$wooco_parent_key = $cart_item['wooco_parent_key'] ?? '';
							if ( $wooco_parent_key !== '' && ! empty( $composite_parent_cart_keys[ (string) $wooco_parent_key ] ) ) {
								continue;
							}

							$w = CorreosOficialHelpers::getProductMetaWeight( $article );
							$total_weight += $w * intval( $cart_item['quantity'] );
						}
					}

					$payload = array(
						'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
						'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
						'cart_total' => $cart_total,
						'total_weight' => $total_weight,
						'country' => $country,
						'company'   => $product->get_company(),
						'sender_id' => 'default',
						'insurance_value' => '0',
						'selector_type' => 'pudocex',
						'checkout'      => $checkout,
						'info_bulto' => array(
						1 => array(
							'large'  => strVal($length),
							'width'  => strVal($width),
							'height' => strVal($height)
							)
						)
					);
				}
			}
		} else {

			$length = CorreosOficialNormalization::normalizeData('length');
			$width  = CorreosOficialNormalization::normalizeData('width');
			$height = CorreosOficialNormalization::normalizeData('height');

			$payload = array(
				'order_id'  => CorreosOficialNormalization::normalizeData('order_id'),
				'postcode'  => CorreosOficialNormalization::normalizeData('postcode'),
				'cart_total' => CorreosOficialNormalization::normalizeData('cart_total'),
				'total_weight' => CorreosOficialNormalization::normalizeData('total_weight'),
				'country' => CorreosOficialNormalization::normalizeData('country'),
				'company'   => CorreosOficialNormalization::normalizeData('company'),
				'sender_id' => CorreosOficialNormalization::normalizeData('sender_id'),
				'insurance_value' => '0',
				'selector_type' => 'pudocex',
				'checkout' => $checkout,
				'info_bulto' => array(
				1 => array(
					'large'  => strVal($length),
					'width'  => strVal($width),
					'height' => strVal($height)
					)
				)
			);
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
		}

		$payload['total_weight'] = round($payload['total_weight'], 2);

		if ($payload['total_weight'] == '0' && (new CorreosOficialConfig('ActivateWeightByDefault'))->get_value() == 'on') {
			$payload['total_weight'] = (new CorreosOficialConfig('WeightByDefault'))->get_value();
		}


		$APIRouter = new CorreosOficialApiRouter();
		$searchPudoResponse = $APIRouter->getPickupLocations($payload);
		wp_send_json($searchPudoResponse, 200);
	}
}
