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

namespace CorreosOficial\Classes;

use Smarty\Smarty;

if (!defined('WPINC')) {
	exit;
}

/**
 * WooCommerce-specific Smarty helper.
 *
 * Replaces the cross-platform vendor/ecommerce_common_lib/CorreosOficialSmarty.php
 * which had PrestaShop/WordPress branching via DetectPlatform. Here we are always
 * on WordPress, so the platform detection and its require_once are dropped entirely.
 * Autoloaded via PSR-4: CorreosOficial\Classes\ → ./classes
 */
class CorreosOficialSmarty
{
	/**
	 * Creates and returns a configured Smarty instance.
	 *
	 * compile_dir is set to the plugin's views/templates_c directory, which is
	 * the same path the vendor class resolved to when running on WordPress.
	 */
	public static function loadSmartyInstance(): Smarty
	{
		$smarty = new Smarty();
		$smarty->setCompileDir(WP_PLUGIN_DIR . '/correosoficial/views/templates_c');
		$smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'intval', 'intval');

		return $smarty;
	}
}
