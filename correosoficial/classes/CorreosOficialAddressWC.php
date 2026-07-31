<?php
namespace CorreosOficial\Classes;

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


/**
 * Clase referente a la las direcciones de Woocommerce.
 */
class CorreosOficialAddressWC {

	public $firstname;
	public $lastname;
	public $company;
	public $address1;
	public $address2;
	public $city;
	public $postcode;
	public $phone;
	public $email;
	public $dni;
	public $id_country;

	public function __construct( $order, $NifFieldValue = null ) {
		$this->firstname = $order->get_shipping_first_name();
		$this->lastname = $order->get_shipping_last_name();
		$this->company = $order->get_shipping_company();
		$this->address1 = $order->get_shipping_address_1();
		$this->address2 = $order->get_shipping_address_2();
		$this->city = $order->get_shipping_city();
		$this->postcode = $order->get_shipping_postcode();

		// Soporte para versiones inferiores a Woocommerce 5.6.0
		if (version_compare(WC_VERSION, '5.6.0', '<')) {
			$this->phone = $order->get_billing_phone();
		} else {
			$this->phone = $order->get_shipping_phone() == '' ? $order->get_billing_phone() : $order->get_shipping_phone();
		}

		$this->email = $order->get_billing_email();
		$this->dni = CorreosOficialUtils::nifIsAnString(get_post_meta($order->get_id(), $NifFieldValue, true));

		$this->id_country = $order->get_shipping_country();
	}

	public function getDni() {
		return $this->dni;
	}
}
