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




use CorreosOficial\Classes\CorreosOficialCarrier;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialProduct;
use LogicException;

class AdminCorreosOficialProductsProcessController {

	private $dao;

	private $info1;
	private $info2;
	private $info3;
	private $info4;

	public function __construct() {
		$this->info1 = __('Products created as carriers in your shop', 'correosoficial');
		$this->info3 = __(
			'Products created as carriers in your shop.
        You have selected products that were already in your store.
        Have been activated', 'correosoficial'
		);
		$this->info2 = __(
			'You have selected products that were already in your store.
                              Have been activated', 'correosoficial'
		);
		$this->info4 = __('There aren\'t active products as carriers in your shop.', 'correosoficial');



		$action = sanitize_text_field(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
		
		switch ($action) {
			case 'CorreosProductsForm':
				$this->updateProducts();
				break;
			case 'getActiveProducts':
				$this->getActiveProducts();
				break;
			default:
				throw new LogicException('ERROR CORREOS OFICIAL 14508: No se ha indicado un "action" para el formulario.');
		}
	}

	public function updateProducts() {
		$products = CorreosOficialNormalization::normalizeData('products');
		$return = array();

		// Resetea la tabla productos poniendo a 0 el active
		CorreosOficialProduct::reset_products();

		if ($products) {

			// Reseteamos el estado a activo (SE SUPONE QUE PONE ACTIVO A 0, comprobar si hace los mismo que resetProducts()
			CorreosOficialCarrier::resetCarriers();

			$existing_products = 0;
			$added_product = 0;

			foreach ($products as $key => $value) {
				CorreosOficialProduct::activate_product($key);

				$product = CorreosOficialProduct::get_product($key);
				$carrier = new CorreosOficialCarrier();

				$carrier_id = $carrier->carrierExists($product[0]->name);

				$createMethods[] = $carrier_id;

				if (!$carrier_id) {
					$added_product++;
				} else {
					$existing_products++;
				}

				$carrier->addCarrier($product[0]);
			}

			// Products created as carriers in your shop');
			if ($existing_products == 0) {
				$return['info'] = 'INFO 14503';
				$return['desc'] = $this->info1;
			} elseif ($existing_products > 0 && $added_product == 0) {
				// You have selected products that were already in your store.
				// Have been activated
				$return['info'] = 'INFO 14506';
				$return['desc'] = $this->info2;
			} else {
				// Products created as carriers in your shop.
				// You have selected products that were already in your store.
				// Have been activated
				$return['info'] = 'INFO 14504';
				$return['desc'] = $this->info3;
			}

			die(json_encode($return));
		} else { // There aren\'t active products as carriers in your shop.
			CorreosOficialCarrier::resetCarriers();
			$return['info'] = 'INFO 14505';
			$return['desc'] = $this->info4;
			die(json_encode($return));
		}
	}

	public function getActiveProducts() {
		$products = CorreosOficialProduct::get_active_products(' WHERE coc.active = 1 and cop.active = 1');
		die(json_encode($products));
	}
}
