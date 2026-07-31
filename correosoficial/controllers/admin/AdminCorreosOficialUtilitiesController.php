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

use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialSender;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialPrefilter;

if (!defined('WPINC')) {
	die;
}

class AdminCorreosOficialUtilitiesController {

	private $smarty;

	public function __construct( $smarty ) {

		$this->smarty = $smarty;
		include WP_PLUGIN_DIR . '/correosoficial/langs/utilitysLang.php';

		$this->renderView();
	}

	private function renderView() {
		$senders_hours = '';
		$default_sender = '';

		wp_enqueue_script(
			'co_ajax', plugins_url('correosoficial/views/js/commons/ajax.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);
		
		wp_enqueue_script(
			'utilities', plugins_url('correosoficial/js/utilities.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		// Pasamos variables js al frontal
		wp_localize_script(
			'utilities', 'varsAjax', array(
			'nonce' => wp_create_nonce('correosoficial_nonce'),
			'ajaxUrl' => admin_url('admin-ajax.php'),
			)
		);

		$DefaultLabel = CorreosOficialConfig::getConfigValue('DefaultLabel');

		// Comprobamos que el las dimensiones por defecto están activas
		$activateDimensionsByDefault = CorreosOficialConfig::checkDimensionsByDefaultActivated();

		$this->smarty->assign(array(
			'activateDimensionsByDefault' => $activateDimensionsByDefault,
			'dimensionsByDefaultHeight' => (int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultHeight'),
			'dimensionsByDefaultLarge' => (int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultLarge'),
			'dimensionsByDefaultWidth' => (int) CorreosOficialConfig::getConfigValue('DimensionsByDefaultWidth'),
		));

		$this->smarty->assign(
			'select_label_options', array(
			LABEL_TYPE_THERMAL => __('Thermic', 'correosoficial'),
			LABEL_TYPE_ADHESIVE => __('Adhesive', 'correosoficial'),
			/* LABEL_TYPE_HALF     => __('Half sheet', 'correosoficial'), */
			)
		);
		$this->smarty->assign('DefaultLabel', $DefaultLabel);

		$this->smarty->assign(
			'select_label_options_format', array(
			LABEL_FORMAT_STANDAR => __('Standar', 'correosoficial'),
			LABEL_FORMAT_3A4 => __('3/3A (Only CEX)', 'correosoficial'),
			/* LABEL_FORMAT_4A4     => __('4/3A (Only CEX)', 'correosoficial') */
			)
		);

		$sender_hours = CorreosOficialSender::get_default_time();

		$sender_hours['sender_from_time'] = isset($sender_hours['sender_from_time']) ? $sender_hours['sender_from_time'] : '';
		$sender_hours['sender_to_time'] = isset($sender_hours['sender_to_time']) ? $sender_hours['sender_to_time'] : '';

		if (isset($senders_hours)) {
			$this->smarty->assign('pickup_from', $sender_hours['sender_from_time']);
			$this->smarty->assign('pickup_to', $sender_hours['sender_to_time']);
		}

		$default_sender = CorreosOficialSender::get_default_sender();

		if (isset($default_sender)) {
			$this->smarty->assign('default_sender', $default_sender);
		}

		
		$senders = CorreosOficialSender::get_senders();
		$select_senders_options = array();
		foreach ($senders as $sender) {
			$select_senders_options[] = array( 'id' => $sender['id'], 'name' => $sender['sender_name'] );
		}
		$this->smarty->assign('select_senders_options', $select_senders_options);

		// Evitamos el warning en la plantilla al no existir el $order_token en WC
		$this->smarty->assign('order_token', '');

		$analitica = new Analitica();

		// Comprobamos si han pasado las 12 h para actualizar
		$lastComprove = $analitica->lastHour();
		$now = gmdate('Y-m-d H:i:s');

		if (!empty($lastComprove) && strtotime($now) > strtotime($lastComprove . '+ 12 hours')) {
			$analitica->moduleRecord();
			$analitica->externalModulesRecord();
			$analitica->configurationCall('undefined');
			$analitica->updateTime();
		}

		$vars = array();

		if (isset($_POST['gdpr_nonce'])) {
			$gdprNonce = sanitize_text_field( $_POST['gdpr_nonce'] );
			if (wp_verify_nonce($gdprNonce, 'gdpr_nonce')) {
				$vars = $_POST;
			}
		}

		$gdpr = $analitica->gdpr($vars);
		
		$template = 'utilities.tpl';
		if ($gdpr) {
			$template = 'correosGdpr.tpl';
			$this->smarty->assign('gdpr_nonce', wp_create_nonce( 'gdpr_nonce' ));
		}

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);
		$this->smarty->display($template);
	}
}
