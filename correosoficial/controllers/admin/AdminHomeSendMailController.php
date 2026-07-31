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
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Classes\CorreosOficialSendMail;

if (!defined('WC_VERSION')) {
	die;
}



class AdminHomeSendMailController {

	public function __construct() {

		$host = $_SERVER['HTTP_HOST']
			?? $_SERVER['SERVER_NAME']
			?? wp_parse_url(home_url(), PHP_URL_HOST);

		$co_signup_customers_from = 'moduloecommercecorreosoficial@' . $host;
		// Destinatarios Alta de nuevo cliente
		$co_signup_customers_cc = array('alvaro.vergara@correos.com',
			'rosario.encinas@correos.com',
			'david.lorencio@correos.com');

		$this->bootstrap = true;
		$this->display = 'view';

		$inputCompany = CorreosOficialNormalization::normalizeData('input_company');
		$inputCif = CorreosOficialNormalization::normalizeData('input_cif');
		$inputContactName = CorreosOficialNormalization::normalizeData('input_contact_name');
		$inputPhoneMobile = CorreosOficialNormalization::normalizeData('input_mobile_phone');
		$inputPhone = CorreosOficialNormalization::normalizeData('input_phone');
		$inputEmail = CorreosOficialNormalization::normalizeData('input_email');
		$productCategory = CorreosOficialNormalization::normalizeData('product_category');

		$platform_and_version = PLATFORM_AND_VERSION;

		$body_to_Customer =
		__('Dear Customer, we have receive your request from module CorreosOficial for ', 'correosoficial') . __PLATFORM__ . ".\r\n\r\n" .
		__('We will contact you as soon as possible.', 'correosoficial') . "\r\n\r\n" .
			"$inputCompany\r\n" .
			"$inputCif\r\n" .
			"$inputContactName\r\n" .
			"$inputPhoneMobile\r\n" .
			"$inputPhone\r\n" .
			"$inputEmail\r\n" .
			"$productCategory\r\n\r\n" .
			"--\r\n\r\nMódulo E-COMMERCE CorreosOficial\r\n\r\n";

		$body_to_CorreosGroup =
			'Se ha recibido una solicitud desde ' . $platform_and_version . ": \r\n\r\n" .
			"Compañía: $inputCompany\r\n" .
			"CIF: $inputCif\r\n" .
			"Persona de contacto: $inputContactName\r\n" .
			"Teléfono Móvil: $inputPhoneMobile\r\n" .
			"Teléfono fijo: $inputPhone\r\n" .
			"Email: $inputEmail\r\n" .
			"Categoría de producto: $productCategory\r\n\r\n" .
			"--\r\n\r\nMódulo E-COMMERCE CorreosOficial\r\n\r\n";

		// Email al cliente
		$result1 = $this->SendEMail(
			$inputEmail, __('Sign up in CorreosOficial: You will receive an answer soon', 'correosoficial'),
			$body_to_Customer, $co_signup_customers_from
		);

		// Email a Grupo Correos
		$result2 = $this->SendEMail(
			$co_signup_customers_cc, __('New lead from CorreosOficial E-COMMERCE: ', 'correosoficial') . $platform_and_version,
			$body_to_CorreosGroup, $co_signup_customers_from, $co_signup_customers_cc
		);
		$result = array( $result1, $result2 );
		CorreosOficialUtils::varDump('ENVIO DE CORREO', $result);
		die(esc_html($result1));
	}

	public function SendEMail( $email, $subject, $message, $from, $cc = null ) {
		$mail = new CorreosOficialSendMail($email, $subject, $message, $from, $cc);
		return $mail->sendEmail();
	}
}
