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

use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialPrefilter;

if (!defined('WPINC')) {
	die;
}

class AdminCorreosOficialNotificationsController {

	private $smarty;

	public function __construct( $smarty ) {

		if (isset($_POST['gdpr_nonce'])) {
			$gdprNonce = sanitize_text_field( $_POST['gdpr_nonce'] );
			if (wp_verify_nonce($gdprNonce, 'gdpr_nonce')) {
				$vars = $_POST;
			}
		}

		if (isset($vars['notificationId']) && !empty($vars['notificationId'])) {
			( new Analitica() )->checkNotifications((int) $vars['notificationId']);
			// Invalida la caché del contador para que el banner se actualice en la siguiente carga
			delete_option('correos_notification_count');
		}

		$this->smarty = $smarty;
		include WP_PLUGIN_DIR . '/correosoficial/langs/utilitysLang.php';

		$this->renderView();
	}

	private function renderView() {
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
		$template = 'notifications.tpl';
		if ($gdpr) {
			$template = 'correosGdpr.tpl';
			$this->smarty->assign('gdpr_nonce', wp_create_nonce( 'gdpr_nonce' ));
		}

		wp_localize_script( 'co_notifications-js', 'notificationsVars', array(
			'correos_inView_check' => __( 'Mark as ready and discart', 'correosoficial' ),
			'gdpr_nonce' => wp_create_nonce( 'gdpr_nonce' ),
		) );

		$notifications = $analitica->getNotifications();
		if (!is_array($notifications['output'])) {
			$notifications['output'] = false;
		}
		
		$this->smarty->assign(array(
			'notifications' => $notifications['output'],
			'noNotifications' => __( 'You don\'t have any notification', 'correosoficial' ),
		));

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);
		$this->smarty->display($template);
	}
}
