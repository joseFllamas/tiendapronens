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

use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Models\CorreosOficialSender;

if (!defined('WC_VERSION')) {
	die;
}


class AdminCorreosOficialSendersProcessController {


	public function __construct() {
		$action = sanitize_text_field(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

		$sender_from_time = CorreosOficialNormalization::normalizeData('sender_from_time');
		$sender_to_time = CorreosOficialNormalization::normalizeData('sender_to_time');

		$correos_code = CorreosOficialNormalization::normalizeData('correos_code');
		$cex_code = CorreosOficialNormalization::normalizeData('cex_code');

		switch ($action) {
			case 'CorreosSendersInsertForm':
				$fields = array(
				'sender_name' => CorreosOficialNormalization::normalizeData('sender_name'),
				'sender_address' => CorreosOficialNormalization::normalizeData('sender_address'),
				'sender_cp' => CorreosOficialNormalization::normalizeData('sender_cp'),
				'sender_nif_cif' => CorreosOficialNormalization::normalizeData('sender_nif_cif'),
				'sender_city' => CorreosOficialNormalization::normalizeData('sender_city'),
				'sender_contact' => CorreosOficialNormalization::normalizeData('sender_contact'),
				'sender_phone' => CorreosOficialNormalization::normalizeData('sender_phone'),
				'sender_from_time'     => $sender_from_time != '' ? $sender_from_time : '00:00',
				'sender_to_time'       => $sender_to_time != '' ? $sender_to_time : '00:00',
				'sender_iso_code_pais' => CorreosOficialNormalization::normalizeData('sender_iso_code_pais'),
				'sender_email' => CorreosOficialNormalization::normalizeData('sender_email', 'email'),
				'sender_default' => '0',
				'correos_code'         => $correos_code != '' ? $correos_code : 0,
				'cex_code'             => $cex_code != '' ? $cex_code : 0,
				);

							CorreosOficialSender::insert( $fields );						break;			case 'CorreosSendersUpdateForm':
				$fields = array(
				'id' => CorreosOficialNormalization::normalizeData('sender_id'),
				'sender_name' => CorreosOficialNormalization::normalizeData('sender_name'),
				'sender_address' => CorreosOficialNormalization::normalizeData('sender_address'),
				'sender_cp' => CorreosOficialNormalization::normalizeData('sender_cp'),
				'sender_nif_cif' => CorreosOficialNormalization::normalizeData('sender_nif_cif'),
				'sender_city' => CorreosOficialNormalization::normalizeData('sender_city'),
				'sender_contact' => CorreosOficialNormalization::normalizeData('sender_contact'),
				'sender_phone' => CorreosOficialNormalization::normalizeData('sender_phone'),
				'sender_from_time'     => $sender_from_time != '' ? $sender_from_time : '00:00',
				'sender_to_time'       => $sender_to_time != '' ? $sender_to_time : '00:00',
				'sender_iso_code_pais' => CorreosOficialNormalization::normalizeData('sender_iso_code_pais'),
				'sender_email' => CorreosOficialNormalization::normalizeData('sender_email', 'email'),
				'correos_code'         => $correos_code != '' ? $correos_code : 0,
				'cex_code'             => $cex_code != '' ? $cex_code : 0,
				);

							CorreosOficialSender::update_sender( $fields );
						break;
			case 'CorreosSenderSaveDefaultForm':
				$sender_default_id = CorreosOficialNormalization::normalizeData('sender_default_id');
					CorreosOficialSender::set_default( $sender_default_id );
					break;

				case 'CorreosSendersDeleteForm':
					$sender_id = CorreosOficialNormalization::normalizeData('sender_id');
					CorreosOficialSender::delete_sender( $sender_id );
				break;
			default:
				die( 'ERROR 11010: Action no válido' );
		}

		// Actualizamos analitica
		( new Analitica() )->configurationCall('undefined');
	}
}
