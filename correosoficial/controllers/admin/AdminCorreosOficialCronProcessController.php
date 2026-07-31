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
use CorreosOficial\Classes\CorreosOficialLog;
use CorreosOficial\Classes\Cron\PreregisteredTrackingCronService;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialMarketplace;
use CorreosOficial\Classes\Cron\MarketplaceTrackingCronService;

if (!defined('WPINC')) {
	die;
}


class AdminCorreosOficialCronProcessController {



	public function __construct() {
		$operation = CorreosOficialNormalization::normalizeData('operation');

		if ($operation === 'CronForm') {
			$this->updateCronSettings();
		}
	}

	/**
	 * Se guardan los ajustes del cron.
	 */
	public function updateCronSettings() {

		// Obtenemos campos de los formularios
		$ActivateOrderStatusChangeAfterSave = CorreosOficialNormalization::normalizeData('ActivateOrderStatusChangeAfterSave');
		$StatusSelector = CorreosOficialNormalization::normalizeData('StatusSelector');
		$ActivateAutomaticTracking = CorreosOficialNormalization::normalizeData('ActivateAutomaticTracking');
		$CurrentState = CorreosOficialNormalization::normalizeData('CurrentState');
		$DeliveredState = CorreosOficialNormalization::normalizeData('DeliveredState');
		$CancelledStateValue = CorreosOficialNormalization::normalizeData('CancelledStateValue');
		$ReturnedState = CorreosOficialNormalization::normalizeData('ReturnedState');
		$CronInterval = CorreosOficialNormalization::normalizeData('CronInterval');

		// Los metemos en un array
		$fields = array(
			'ActivateOrderStatusChangeAfterSave' => $ActivateOrderStatusChangeAfterSave,
			'StatusSelector' => $StatusSelector,
			'ActivateAutomaticTracking' => $ActivateAutomaticTracking,
			'CurrentState' => $CurrentState,
			'DeliveredState' => $DeliveredState,
			'CancelledStateValue' => $CancelledStateValue,
			'ReturnedState' => $ReturnedState,
			'CronInterval' => $CronInterval,
		);

		// Guardar configuración del cron
		foreach ( $fields as $key => $value ) {
			CorreosOficialConfig::save( $key, $value );
		}
		die;
	}

	public static function updateCronInterval( $schedules ) {
		global $wpdb;
		
		// Comprobar si la tabla existe antes de intentar leer de ella
		$table_name = $wpdb->prefix . 'correos_oficial_configuration';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
		
		// Default values (de install.php)
		$cron_interval = 4;
		$sga_cron_interval = 4;
		
		if ($table_exists) {
			// Intentar obtener valores de la base de datos
			$db_cron_interval = CorreosOficialConfig::getConfigValue('CronInterval');
			if (!empty($db_cron_interval)) {
				$cron_interval = $db_cron_interval;
			}
			
			try {
				$config = new CorreosOficialConfig('SGAOrderStatusTrackingCronInterval');
				$db_sga_interval = $config->get_value();
				if (!empty($db_sga_interval)) {
					$sga_cron_interval = $db_sga_interval;
				}
			} catch (\Exception $e) {
				// Usar valor por defecto si ocurre un error
			}
		}
		
		$schedules['correosoficial_cron'] = array(
			'interval' => 3600 * $cron_interval,
			'display'  => __('Cada ' . $cron_interval . ' Horas'),
		);

		if(!empty($sga_cron_interval)) {
			$schedules['correosoficial_sga_cron'] = array(
				'interval' => 3600 * $sga_cron_interval,
				'display'  => __('Cada ' . $sga_cron_interval . ' Horas'),
			);
		}
		
		return $schedules;
	}

	/**
	 * Función de Cron desde el controlador. Ejecuta el Cron de Ajustes.
	 */
	public static function cronExecute() {
		$lockKey = 'correosoficial_tracking_cron_lock';
		$canUseTransients = function_exists('get_transient') && function_exists('set_transient') && function_exists('delete_transient');

		// Evita ejecuciones concurrentes que duplican bloques de log en la misma ventana de tiempo.
		if ($canUseTransients && get_transient($lockKey)) {
			return;
		}

		if ($canUseTransients) {
			set_transient($lockKey, 1, 10 * MINUTE_IN_SECONDS);
		}

		$ini_time = CorreosOficialLog::logDate();

		try {
			$activate_tracking_cron = (new CorreosOficialConfig('ActivateAutomaticTracking') )->get_value();

			// Pre-registered orders (Correos + CEX) — handled by the new WC-specific service.
			if ($activate_tracking_cron == 'on') {
				$preregisteredCron = new PreregisteredTrackingCronService();
				$preregisteredCron->run();
			}

			// Marketplace orders — handled by the new WC-specific service.
			if (CorreosOficialMarketplace::isMarketplaceEnabled()) {
				$marketplaceCron = new MarketplaceTrackingCronService();
				$marketplaceCron->run();
			}
		} catch (\Exception $e) {
			$cron_error_log = __DIR__ . '/../../log/cron_error_log.txt';

			file_put_contents($cron_error_log, '[' . $ini_time . '] ', FILE_APPEND);
			file_put_contents($cron_error_log, $e->getMessage(), FILE_APPEND);

			$end_time = CorreosOficialLog::logDate();

			file_put_contents($cron_error_log, ' [' . $end_time . ']' . PHP_EOL . PHP_EOL, FILE_APPEND);
			error_log('Excepción capturada 15500: ' . $e->getMessage() . "\n");
			return;
		} finally {
			if ($canUseTransients) {
				delete_transient($lockKey);
			}
		}
	}
}
