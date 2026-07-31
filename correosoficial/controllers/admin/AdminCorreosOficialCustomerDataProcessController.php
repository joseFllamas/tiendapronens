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

use CorreosOficial\Classes\Apis\CorreosOficialCEXRest;
use CorreosOficial\Classes\Apis\CorreosOficialRest;
use CorreosOficial\Classes\Apis\CorreosOficialSoap;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialCode;
use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Classes\Analitica;

if (!defined('WC_VERSION')) {
	die;
}



class AdminCorreosOficialCustomerDataProcessController {

	public function __construct() {
		$action = sanitize_text_field(isset($_REQUEST['action']) ? $_REQUEST['action'] : null);
		$operation = sanitize_text_field(isset($_REQUEST['operation']) ? $_REQUEST['operation'] : null);

		if ($operation === 'CorreosCustomerDataForm') {
			$this->getCorreosData();
		} elseif ($operation === 'CEXCustomerDataForm') {
			$this->getCEXData();
		} elseif ($action === 'DeleteCustomerCode') {
			$CorreosOficialCustomerCode = CorreosOficialNormalization::normalizeData('CorreosOficialCustomerCode');
			CorreosOficialCode::delete_code($CorreosOficialCustomerCode);
			die();
		} elseif ($action === 'getDataTableCustomerList') {
			$code = new CorreosOficialCode();
			wp_send_json($code::getAllCodes());
		} elseif ($action === 'getCustomerCode') {
			$this->getCode();
		} elseif ($action === 'getCustomerCodes') {
			$this->getCodes();
		} elseif ($action === 'checkAccount') {
			$this->checkAccount();
		} else {
			throw new LogicException('ERROR CORREOS OFICIAL 10508: No se ha indicado un "action" para el formulario.');
		}
	}

	public function getCorreosData() {
		// Obtenemos campos de los formularios
		$idCorreos = CorreosOficialNormalization::normalizeData('idCorreos');
		$CorreosClientID = CorreosOficialNormalization::normalizeData('CorreosClientID', 'nospaces');
		$CorreosSecretID = CorreosOficialNormalization::normalizeData('CorreosSecretID', 'nospaces');
		$CorreosContract = CorreosOficialNormalization::normalizeData('CorreosContract');
		$CorreosCustomer = CorreosOficialNormalization::normalizeData('CorreosCustomer');
		$CorreosKey = CorreosOficialNormalization::normalizeData('CorreosKey');
		$CorreosUser = CorreosOficialNormalization::normalizeData('CorreosUser', 'user');
		$CorreosPassword = CorreosOficialNormalization::normalizeData('CorreosPassword', 'password');
		$CorreosOv2Code = CorreosOficialNormalization::normalizeData('CorreosOv2Code', 'email');

		if ($CorreosCustomer && strlen($CorreosCustomer) === 8) {
    		$CorreosCustomer = '99' . $CorreosCustomer;
		}

		$parsed_password = $CorreosPassword ? CorreosOficialCrypto::encrypt($CorreosPassword) : 'n/a';
		$parsed_secretID  = $CorreosSecretID ? CorreosOficialCrypto::encrypt($CorreosSecretID) : 'n/a';

		$user_data = array(
			'customer_code'   => $CorreosCustomer,
			'company'         => 'Correos',
			'CorreosContract' => $CorreosContract ? $CorreosContract : 'n/a',
			'CorreosCustomer' => $CorreosCustomer ? $CorreosCustomer : 'n/a',
			'CorreosKey'      => $CorreosKey ? $CorreosKey : 'n/a',
			'CorreosUser'     => $CorreosUser ? $CorreosUser : 'n/a',
			'CorreosPassword' => $parsed_password,
			'CorreosOv2Code'  => $CorreosOv2Code ? $CorreosOv2Code : 'n/a',
			'CorreosClientID' => $CorreosClientID ? $CorreosClientID : 'n/a',
			'CorreosSecretID' => $parsed_secretID
		);

		// $Company = CorreosOficialNormalization::normalizeData('CorreosCompany');

		$correos_code = new CorreosOficialCode($idCorreos);
		$saved_correos_code = null;
		$valid_user   = $correos_code->validateUser($user_data);

		( new Analitica() )->configurationCall('undefined');

		if ($valid_user) {
			$correos_code->set_CorreosClientID($user_data['CorreosClientID']);
			$correos_code->set_CorreosSecretID($user_data['CorreosSecretID']);
			$correos_code->set_CorreosContract($user_data['CorreosContract']);
			$correos_code->set_CorreosCustomer($user_data['CorreosCustomer']);
			$correos_code->set_CorreosKey($user_data['CorreosKey']);
			$correos_code->set_CorreosUser($user_data['CorreosUser']);
			$correos_code->set_CorreosPassword($user_data['CorreosPassword']);
			$correos_code->set_CorreosOv2Code($user_data['CorreosOv2Code']);
			$correos_code->set_company('Correos');
			$correos_code->set_customer_code($user_data['customer_code']);
			$correos_code->set_CEXCustomer('n/a');
			$correos_code->set_CEXUser('n/a');
			$correos_code->set_CEXPassword('n/a');
			$correos_code->save();

			$saved_correos_code = $correos_code->getCodeByCustomer($user_data['customer_code']);
		} else {
			$mensajeRetorno = array(
				'codigoRetorno' => '10504',
				'mensajeRetorno' => __('User authentication failed. Please check your Client ID and Secret and try again.', 'correosoficial'),
				'status_code' => '443',
			);

			wp_send_json($mensajeRetorno);
		}


		$account_id = $saved_correos_code->get_id();
		wp_send_json($account_id);
	}

	public function getCEXData() {
		$idCEX = CorreosOficialNormalization::normalizeData('idCEX');
		$CEXCustomer = CorreosOficialNormalization::normalizeData('CEXCustomer');
		$CEXUser = CorreosOficialNormalization::normalizeData('CEXUser', 'user');
		$CEXPassword = CorreosOficialNormalization::normalizeData('CEXPassword', 'password');

		$parsed_CEXPassword = $CEXPassword ? CorreosOficialCrypto::encrypt($CEXPassword) : 'n/a';

		$user_data = array(
			'customer_code'   => $CEXCustomer ? $CEXCustomer : 'n/a',
			'company'         => 'CEX',
			'CEXCustomer'     => $CEXCustomer ? $CEXCustomer : 'n/a',
			'CEXUser'         => $CEXUser ? $CEXUser : 'n/a',
			'CEXPassword'     => $parsed_CEXPassword
		);
		
		$cex_code   = new CorreosOficialCode($idCEX);
		$saved_cex_code = null;
		$valid_user = $cex_code->validateUser($user_data);

		( new Analitica() )->configurationCall('undefined');

		if ($valid_user) {
			$cex_code->set_CorreosClientID('n/a');
			$cex_code->set_CorreosSecretID('n/a');
			$cex_code->set_CorreosContract('n/a');
			$cex_code->set_CorreosCustomer('n/a');
			$cex_code->set_CorreosKey('n/a');
			$cex_code->set_CorreosUser('n/a');
			$cex_code->set_CorreosPassword('n/a');
			$cex_code->set_CorreosOv2Code('n/a');
			$cex_code->set_CEXCustomer($user_data['CEXCustomer']);
			$cex_code->set_CEXUser($user_data['CEXUser']);
			$cex_code->set_CEXPassword($user_data['CEXPassword']);
			$cex_code->set_company('CEX');
			$cex_code->set_customer_code($user_data['customer_code']);
			$cex_code->save();

			$saved_cex_code = $cex_code->getCodeByCustomer($user_data['customer_code']);
		} else {
			$mensajeRetorno = array(
				'codigoRetorno' => '10504',
				'mensajeRetorno' => __('User authentication failed. Please check your username and password and try again.', 'correosoficial'),
				'status_code' => '443',
			);

			wp_send_json($mensajeRetorno);
		}

		$account_id = $saved_cex_code->get_id();
		wp_send_json($account_id);
	}

	// Obtenemos información de un contrato (AJAX)
	public function getCode() {
		$id = CorreosOficialNormalization::normalizeData('id');
		//$code = $this->dao->readRecord('correos_oficial_codes', 'WHERE id=' . $id . ' LIMIT 1');

		$code = ( new CorreosOficialCode($id) )->get_data();
		$code = array_map(fn( $valor ) => $valor === 'n/a' ? '' : $valor, $code);

		if ($code['CorreosPassword'] != 'n/a') {
			$decryptedPassword = CorreosOficialCrypto::decrypt($code['CorreosPassword']);
			$code['CorreosPassword'] = $decryptedPassword !== false ? $decryptedPassword : '';
		}

 		// si tenemos resultados obtenemos el primer registro
		if ($code) {
			die(json_encode($code));
		} else {
			die(json_encode(array()));
		}
	}

	// Obtenemos los contratos (AJAX)
	public function getCodes() {

		$optionsCountsCorreos = CorreosOficialCode::get_by_company('Correos', '`id`, `CorreosContract`, `CorreosCustomer`');

		$optionsCountsCex = CorreosOficialCode::get_by_company('CEX', '`id`, `CEXCustomer`');

		// Componemos array de contratos
		$contracts = array(
			'correos' => $optionsCountsCorreos ?? [],
			'cex' => $optionsCountsCex ?? [],
		);

		die(json_encode($contracts));
	}

	public function badResponse( $result ) {
		if (!$result) {
			$mensajeRetorno = array(
				'codigoRetorno' => '10502',
				'mensajeRetorno' => __('The customer code you are triying to save, is already in use', 'correosoficial'),
				'status_code' => '409',
			);
	
			echo json_encode($mensajeRetorno);
			die();
		}
	}

	public function checkAccount() {
		$idCorreos = (int) CorreosOficialNormalization::normalizeData('codes_id');

		// normalizar respuesta
		$respond = function ($success) {
			die(json_encode([
				'success'    => $success,
				'error_code' => $success ? '200' : '401',
			]));
		};

		if ($idCorreos) {
			$co_code = new CorreosOficialCode($idCorreos);
			$code_data = $co_code->get_data();

			// REST Correos
			if ($code_data["CorreosClientID"] !== 'n/a' && $code_data["CorreosSecretID"] !== 'n/a') {
				$respond((bool)$co_code->validateUser($code_data));
			}

			// SOAP Correos
			if ($code_data["CorreosUser"] !== 'n/a' && !empty($code_data["CorreosPassword"])) {
				$respond((bool)$co_code->validateUser($code_data));
			}

			// REST CEX
			if ($code_data["CEXCustomer"] !== 'n/a' && $code_data["CEXPassword"] !== 'n/a') {
				$respond((bool)$co_code->validateUser($code_data));
			}
		}
		
		$respond(false);
	}
}
