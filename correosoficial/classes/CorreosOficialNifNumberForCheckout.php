<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Models\CorreosOficialConfig;

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

/**
 * Clase para tratamiento del campo dni en el checkout
 */
class CorreosOficialNifNumberForCheckout {


	/**
	 * Función para añadir el campo dni al Checkout de Woocommerce
	 *
	 * @param  string $checkout objeto de tipo WC_Checkout
	 * @return void
	 */
	public static function addNifFieldToCheckout( $checkout ) {
		woocommerce_form_field(
			'nif', array(
			'type' => 'text',
			'class' => array( 'my-field-class form-row-wide' ),
			'label' => __('VAT Number', 'correosoficial'),
			'required' => ( CorreosOficialConfig::getConfigValue('NifFieldRadio') === 'OBLIGATORY' ) ? true : false,
			'placeholder' => __('Your VAT Number', 'correosoficial'),
			), $checkout->get_value('nif')
		);
	}

	/**
	 * Función para mostrar el valor del nuevo campo NIF en la página de edición del pedido
	 *
	 * @param  string $checkout objeto de tipo Order
	 * @return void
	 */
	public static function showPersonalisedFieldAdminOrder( $order ) {
		if (get_post_meta($order->get_id(), 'NIF', true) != null) {
			$allowed_html = array( 'strong'=>array(), 'p'=>array() );
			echo wp_kses('<p><strong>' . __('NIF') . ':</strong> ' . get_post_meta($order->get_id(), 'NIF', true) . '</p>', $allowed_html);
		}
	}

	public static function updateOrderInfoWithNewField( $order_id ) {

		if ( isset($_POST['woocommerce-process-checkout-nonce']) &&
			wp_verify_nonce(sanitize_text_field($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout')
		) {

			$nif = sanitize_text_field(isset($_POST['nif']) ? $_POST['nif'] : '');

			if ($nif != '') {
				update_post_meta($order_id, 'NIF', $nif);
			}
		}
	}
}

// Global alias for backward compatibility with legacy hook registrations.
if ( ! class_exists( 'CorreosOficialNifNumberForCheckout', false ) ) {
	class_alias( \CorreosOficial\Classes\CorreosOficialNifNumberForCheckout::class, 'CorreosOficialNifNumberForCheckout' );
}
