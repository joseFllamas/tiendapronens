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
use CorreosOficial\Classes\CorreosOficialOrders;
use CorreosOficial\Classes\CorreosOficialMarketplace;
use CorreosOficial\Models\CorreosOficialProduct;

if (!defined('WPINC')) {
	die;
}

class CorreosTrackings {

	public function correos_email_templates( $template, $template_name, $template_path ) {
		global $woocommerce;
		$new_template = plugin_dir_path(__FILE__) . 'wc-email-templates/' . $template_name;
	
		if (!file_exists($new_template)) {
			return $template;
		}
	
		return $new_template;
	}
	
	public function add_metabox_in_orders() {

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		add_meta_box(
			'correosoficial_shipment',
			__('Shipment Tracking', 'correosoficial'),
			array( $this, 'correos_metabox_orders' ),
			$screen,
			'side',
			'high'
		);
	}
	
	public function correos_metabox_orders( $post ) {

		// Check HPOS
		$order = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? $post
		: wc_get_order($post->ID);

		$allowed_html = array(
			'div' => array(
				'class' => true,
				'style' => true,
			),
			'label' => array(
				'for' => true,
			),
			'select' => array(
				'id' => true,
				'name' => true,
				'style' => true,
			),
			'option' => array(
				'value' => true,
				'selected' => true,
			),
			'input' => array(
				'type' => true,
				'id' => true,
				'class' => true,
				'name' => true,
				'value' => true,
			),
			'button' => array(
				'type' => true,
				'class' => true,
				'style' => true,
			),
		);

		echo wp_kses($this->getHtml($order->get_id()), $allowed_html);
	}
	
	public function getHtml( $id_order ) {
		$correosOrder = CorreosOficialOrders::getCorreosOrder((int) $id_order);
		$provider = '';
		$tracking_number = '';
		$tracking_link = '';
		$tracking_date = '';
		$selectedCorreos = '';
		$selectedCEX = '';
	
		if (!empty($correosOrder)) {
			$provider = $correosOrder['carrier_type'];
			$tracking_date = gmdate('Y-m-d', strtotime($correosOrder['date_add']));
			if ($provider === 'CEX') {
				$tracking_number = $correosOrder['shipping_number'];
				$tracking_link = str_replace('=@', '=' . $correosOrder['shipping_number'], $correosOrder['url']);
				$selectedCEX = 'selected';
			} else {
				$shippingNumber = CorreosOficialOrders::getCorreosPackages((int) $id_order);
				$tracking_number = $shippingNumber[0]['shipping_number'];
				$tracking_link = str_replace('=@', '=' . $shippingNumber[0]['shipping_number'], $correosOrder['url']);
				$selectedCorreos = 'selected';
			}
		} else {
			// Pedido Marketplace: tracking guardado en WC order meta, no en la tabla wp_correos_oficial_orders.
			$wc_order = wc_get_order((int) $id_order);
			if ($wc_order) {
				$marketplace_tracking = (string) $wc_order->get_meta(CorreosOficialMarketplace::META_KEY_TRACKING_NUMBER);
				if ($marketplace_tracking !== '') {
					$tracking_number = $marketplace_tracking;
					$selectedCorreos  = 'selected';
					// Fecha guardada por el CRON en la primera actualización de estado.
					$saved_date = (string) $wc_order->get_meta('_correosoficial_marketplace_tracking_date');
					if ($saved_date !== '') {
						$tracking_date = $saved_date;
					}
					// URL del producto del módulo: instance_id del método de envío → url de la tabla.
					foreach ($wc_order->get_shipping_methods() as $shipping_item) {
						$instance_id = (int) $shipping_item->get_instance_id();
						if ($instance_id > 0) {
							$product = CorreosOficialProduct::get_carrier_params(array('id_reference' => $instance_id));
							if ($product && !empty($product['url'])) {
								$tracking_link = str_replace('=@', '=' . $marketplace_tracking, $product['url']);
							}
							break;
						}
					}
				}
			}
		}
		$nonce = wp_create_nonce( 'correos-metabox-tracking');
	
		return '
			<input type="hidden" name="correos_tracking_nonce" value="' . $nonce . '">
            <div class="form-group">
                <label for="correos_provider">' . __( 'Provider' , 'correosoficial' ) . '</label>
                <select id="correos_provider" name="correos_provider" style="width:100%;">
                    <option value="">' . __( 'Select an option' , 'correosoficial' ) . '</option>
                    <option value="Correos" ' . $selectedCorreos . '>Correos</option>
                    <option value="CEX" ' . $selectedCEX . '>Correos Express</option>
                </select>
            </div>
            <div class="form-group">
                <label for="correos_tracking_number">' . __( 'Tracking number' , 'correosoficial' ) . '</label>
                <input type="text" id="correos_tracking_number" class="form-control" name="correos_tracking_number" value="' . $tracking_number . '" >
            </div>
            <div class="form-group">
                <label for="correos_tracking_link">' . __( 'Tracking link' , 'correosoficial' ) . '</label>
                <input type="text" id="correos_tracking_link" class="form-control" name="correos_tracking_link" value="' . $tracking_link . '" >
            </div>
            <div class="form-group">
                <label for="correos_tracking_date">' . __( 'Tracking date' , 'correosoficial' ) . '</label>
                <input type="date" id="correos_tracking_date" class="form-control" name="correos_tracking_date" value="' . $tracking_date . '" >
            </div>
            <div class="form-group" style="padding:.5rem 0; text-align:right;">
                <button type="submit" class="button save_order button-primary" style>'
					. __( 'Save tracking', 'correosoficial' ) .
				'</button>
            </div>
        
        ';
	}
	
	public function save_correos_metabox( $post_id ) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		$vars = array();
		if (isset($_POST['correos_tracking_nonce'])) {
			$nonceTracking = sanitize_text_field( $_POST['correos_tracking_nonce'] );
			if (wp_verify_nonce($nonceTracking, 'correos-metabox-tracking')) {
				$vars = $_POST;
			}
		}

		if (isset($vars['correos_provider'])) {
			$provider = $vars['correos_provider'];
			$track_number = $vars['correos_tracking_number'];
			$track_date = $vars['correos_tracking_date'];
			$this->updateValues($provider, $track_number, $track_date, $post_id);
		}
	}
	
	public function updateValues( $provider, $track_number, $track_date, $id_order ) {
		global $wpdb;
		$basicTable = $wpdb->prefix . 'correos_oficial_orders';
		$savedTable = $wpdb->prefix . 'correos_oficial_saved_orders';
		$updateOrders = array();
		$updateSaved = array();
		$condition = array(
			'id_order' => (int) $id_order,
		);
	
		$updateOrders['carrier_type'] = $provider;
		$updateOrders['date_add'] = $track_date;
	
		if ($provider === 'CEX') {
			$updateOrders['shipping_number'] = $track_number;
			$updateSaved['exp_number'] = $track_number;
		} else {
			$updateSaved['shipping_number'] = $track_number;
		}
		$wpdb->update($basicTable, $updateOrders, $condition);
		$wpdb->update($savedTable, $updateSaved, $condition);
	}
}

$class = new CorreosTrackings();

add_filter('woocommerce_locate_template', array( $class, 'correos_email_templates' ), 10, 3);

add_action('add_meta_boxes', array( $class, 'add_metabox_in_orders' ));

add_action('save_post_shop_order', array( $class, 'save_correos_metabox' ));

add_action('woocommerce_process_shop_order_meta', array( $class, 'save_correos_metabox' ));
