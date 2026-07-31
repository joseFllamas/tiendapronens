<?php
namespace CorreosOficial\Classes;

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
 * Provee métodos para el manejo de errores
 * 
 * @author:  Grupo Correos A649437
 * @uses:    provide methods to manage errors
 * @version: 1
 */
class CorreosOficialErrorManager {


	/**
	 * Errores de usuarios: deben ser traducibles
	 */
	public $UserErrorLoginError;
	public $userErrorAnErrorHasOCurred = '';
	public $couldNotConnectToHost;

	/**
	 * Errores técnicos. Directamente en español.
	 */
	public $preregisterError;

	public function __construct() {

		/* Errores de usuario */
		$this->UserErrorLoginError = __('Please, check your credentials in module Correos Oficial, in Settings Customer Data.', 'correosoficial');

		/* Errores técnicos */
		$this->preregisterError = 'Debe indicarse una operación (PreRegistro/MultiBulto)';

		$this->couldNotConnectToHost = __('The waiting time has expired', 'correosoficial');
	}

	public function display_timeout_error( $line ) {
		echo nl2br('<br/>Ha ocurrido un error temporal. Puede que el servicio de Correos no esté disponible en estos momentos. ');
		echo 'Inténtelo de nuevo más tarde. ';
		echo 'Error en línea: ' . esc_html($line) . ' en Fichero: ' . __FILE__;
	}

	public static function checkStateConnection( $state ) {
		$error = new self();

		switch ($state) {
			/**
				 * Errores de usuarios: deben ser traducibles
				 */
			case '0':
				return $error->couldNotConnectToHost;
				break;
			case 'Unauthorized':
			case 'Authorization Required':
			case '401':
				return $error->UserErrorLoginError;
				break;
			case 'Could not connect to host':
				return $error->couldNotConnectToHost;
				break;
			/**
				 * Errores técnicos. Directamente en español.
				 */
			case 'Not Found':
			case '404':
				return 'Servicio no encontrado. Se ha conectado correctamente al host.';
				break;
			default:
				return 'Error no conocido. Código HTTP del HOST: ' . $state;
		}
	}
}
