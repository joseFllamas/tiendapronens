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

use CorreosOficial\Classes\CorreosOficialPrefilter;

if (!defined('WPINC')) {
	die;
}


class AdminCorreosOficialHomeController {


	private $smarty;

	public function __construct( $smarty ) {
		$this->smarty = $smarty;

		include WP_PLUGIN_DIR . '/correosoficial/langs/homeLang.php';

		wp_enqueue_style('home', plugins_url('views/commons/css/home.css', __FILE__), false, '1.0.0.0');
		$this->renderView();
	}

	private function renderView() {
		wp_enqueue_script(
			'home_js', plugins_url('correosoficial/views/commons/home.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		$this->smarty->assign('dispatcher', 'admin.php?page=ajustes');

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);

		$this->smarty->display('home.tpl');
	}
}
