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
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Classes\CorreosOficialMarketplace;

if (!defined('WPINC')) {
    die;
}

class AdminCorreosOficialMarketplaceProcessController
{
    public function __construct()
    {
        $action = isset($_REQUEST['action']) ? CorreosOficialUtils::sanitize($_REQUEST['action']) : '';

        switch ($action) {
            case 'saveMarketplaceConfig':
                $this->saveMarketplaceConfig();
                break;
            default:
                return false;
        }
    }

    /**
     * Guarda la configuración de Marketplace en base de datos.
     *
     * Al activar:  verifica que WooCommerce esté activo, crea la clave de
     *              WooCommerce REST API y persiste el estado de pedido.
     * Al desactivar: elimina la clave de API para que el tercero no pueda
     *                autenticarse.
     */
    public function saveMarketplaceConfig(): void
    {
        try {
            $activate = isset($_POST['ActivateMarketplace'])
                ? CorreosOficialUtils::sanitize($_POST['ActivateMarketplace'])
                : '';

            $isActive = ($activate === 'on');

            if ($isActive) {
                // WooCommerce es imprescindible para exponer la REST API
                if (!CorreosOficialMarketplace::isWooCommerceActive()) {
                    wp_send_json_error([
                        'success' => false,
                        'message' => __('WooCommerce is required to activate the Marketplace integration. Please install and activate WooCommerce first.', 'correosoficial'),
                    ]);
                    return;
                }

                $saved = CorreosOficialMarketplace::enableMarketplace();
                if (!$saved) {
                    wp_send_json_error([
                        'success' => false,
                        'message' => __('Error saving Marketplace configuration.', 'correosoficial'),
                    ]);
                    return;
                }

                if (!CorreosOficialMarketplace::createOrActivateApiKey()) {
                    wp_send_json_error([
                        'success' => false,
                        'message' => __('Error creating Marketplace API key.', 'correosoficial'),
                    ]);
                    return;
                }

                CorreosOficialMarketplace::createOrFindOrderStatus();

            } else {
                $saved = CorreosOficialMarketplace::disableMarketplace();
                if (!$saved) {
                    wp_send_json_error([
                        'success' => false,
                        'message' => __('Error saving Marketplace configuration.', 'correosoficial'),
                    ]);
                    return;
                }

                CorreosOficialMarketplace::deleteApiKey();
            }

            // Mandamos a CTRLVERS
            $analitica = new Analitica();
            $analitica->moduleRecord();
            $analitica->configurationCall(false);

            wp_send_json_success([
                'success'           => true,
                'marketplaceActive' => $isActive,
                'consumerKey'       => CorreosOficialMarketplace::getConsumerKey(),
                'consumerSecret'    => CorreosOficialMarketplace::getConsumerSecret(),
                'apiBaseUrl'        => CorreosOficialMarketplace::getApiBaseUrl(),
                'message'           => __('Marketplace data successfully saved.', 'correosoficial'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'success' => false,
                'message' => 'Error al procesar los datos: ' . $e->getMessage(),
            ]);
        }
    }
}
