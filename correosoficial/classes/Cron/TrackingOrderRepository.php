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

declare(strict_types=1);

namespace CorreosOficial\Classes\Cron;

use CorreosOficial\Classes\CorreosOficialMarketplace;

/**
 * Repository for the tracking cron: retrieves and updates orders using wpdb
 * and WooCommerce order APIs.
 *
 * Pre-registered orders  → plugin tables correos_oficial_orders + correos_oficial_saved_orders,
 *                          queried directly via $wpdb (same tables as PS, different prefix helper).
 *
 * Marketplace orders     → WC orders carrying the _correosoficial_marketplace_tracking_number
 *                          order meta, retrieved via wc_get_orders() for HPOS compatibility.
 *
 * Orders are excluded when:
 *   - Pre-registered: status is in TERMINAL_STATUS_LABELS, shipping_number is empty,
 *                     or date_add is older than LOOKBACK_MONTHS months.
 *   - Marketplace:    _correosoficial_marketplace_tracking_status is in TERMINAL_STATUS_LABELS,
 *                     tracking number meta is empty, or order date older than LOOKBACK_MONTHS.
 */
class TrackingOrderRepository
{
    /** @var \wpdb */
    private $wpdb;

    /**
     * @param \wpdb|null $wpdb  Inject for testing; falls back to the global $wpdb.
     */
    public function __construct($wpdb = null)
    {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }
    }

    // ── Pre-registered orders ─────────────────────────────────────────────────

    /**
     * Returns all trackable pending pre-registered orders for a given carrier type,
     * one row per saved bulto (package), with Correos / CEX credentials embedded.
     *
     * Credentials come from the first valid correos_oficial_codes row for the
     * carrier type. If no valid code row exists the query returns zero rows.
     *
     * @param string $carrierType  'Correos' or 'CEX'
     * @return array<int, array<string, mixed>>  Indexed array of associative rows:
     *   - id_order        int
     *   - carrier_type    string
     *   - last_status     string  Last raw event code stored
     *   - status          string  Semantic label stored in the column
     *   - exp_number      string  Expedition number (correos_oficial_orders.shipping_number)
     *   - tracking_number string  Actual package (bulto) tracking number
     *   - CorreosClientID, CorreosSecretID, CorreosUser, CorreosPassword,
     *     CorreosContract, CorreosCustomer, CorreosKey, CEXCustomer, CEXUser, CEXPassword
     */
    public function findPendingPreregisteredOrders(string $carrierType): array
    {
        $prefix = $this->wpdb->prefix;

        $terminalList = implode(',', array_map(
            fn(string $s) => "'" . esc_sql($s) . "'",
            CronConfig::TERMINAL_STATUS_LABELS
        ));

        $codeWhere = ($carrierType === 'CEX') ? "company = 'CEX'" : "company = 'Correos'";

        $sql = $this->wpdb->prepare(
            "SELECT
                coo.id_order,
                coo.carrier_type,
                coo.last_status,
                coo.status,
                coo.shipping_number  AS exp_number,
                coso.shipping_number AS tracking_number,
                cc.CorreosClientID,
                cc.CorreosSecretID,
                cc.CorreosUser,
                cc.CorreosPassword,
                cc.CorreosContract,
                cc.CorreosCustomer,
                cc.CorreosKey,
                cc.CEXCustomer,
                cc.CEXUser,
                cc.CEXPassword
            FROM `{$prefix}correos_oficial_orders` coo
            INNER JOIN `{$prefix}correos_oficial_saved_orders` coso
                ON coso.exp_number = coo.shipping_number
            CROSS JOIN (
                SELECT
                    CorreosClientID, CorreosSecretID,
                    CorreosUser, CorreosPassword, CorreosContract, CorreosCustomer, CorreosKey,
                    CEXCustomer, CEXUser, CEXPassword
                FROM `{$prefix}correos_oficial_codes`
                WHERE {$codeWhere}
                ORDER BY id ASC
                LIMIT 1
            ) cc
            WHERE coo.carrier_type = %s
              AND (coo.status IS NULL OR coo.status NOT IN ({$terminalList}))
              AND TIMESTAMPDIFF(MONTH, coo.date_add, NOW()) < %d
              AND coo.shipping_number != ''
              AND coso.shipping_number != ''
            ORDER BY coo.id_order ASC",
            $carrierType,
            CronConfig::LOOKBACK_MONTHS
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if ($rows === null) {
            error_log(
                '[CorreosOficial][TrackingOrderRepository] findPendingPreregisteredOrders query failed: '
                    . $this->wpdb->last_error
            );
            return [];
        }

        return $rows ?: [];
    }

    /**
     * Persists a new last_status / status label on correos_oficial_orders.
     *
     * @param int    $idOrder     WooCommerce order ID (same value stored in the plugin table).
     * @param string $lastStatus  Raw event code from the API.
     * @param string $statusLabel Human-readable label (e.g. 'Entregado').
     * @return bool               True on success.
     */
    public function updatePreregisteredStatus(int $idOrder, string $lastStatus, string $statusLabel): bool
    {
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'correos_oficial_orders',
            [
                'last_status' => $lastStatus,
                'status'      => $statusLabel,
                'updated_at'  => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
            ['id_order' => $idOrder],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log(sprintf(
                '[CorreosOficial][TrackingOrderRepository] updatePreregisteredStatus failed for id_order=%d: %s',
                $idOrder,
                $this->wpdb->last_error
            ));
        }

        return $result !== false;
    }

    // ── Marketplace orders ────────────────────────────────────────────────────

    /**
     * Returns all trackable pending marketplace orders, with credentials embedded.
     *
     * Marketplace orders are NOT pre-registered, so they have no entry in
     * correos_oficial_orders. Their tracking number lives in the WC order meta
     * _correosoficial_marketplace_tracking_number.
     *
     * Terminal filter: orders whose _correosoficial_marketplace_tracking_status
     * meta is in TERMINAL_STATUS_LABELS are excluded, avoiding re-querying
     * finished shipments on every cron run.
     *
     * Credentials: first valid Correos company row from correos_oficial_codes.
     * If no row exists, the method returns an empty array.
     *
     * @return array<int, array<string, mixed>>  Indexed array of associative rows:
     *   - id_order                       int
     *   - tracking_number                string
     *   - marketplace_tracking_status    string  Current semantic label (may be '')
     *   - CorreosClientID, CorreosSecretID, CorreosUser, CorreosPassword,
     *     CorreosContract, CorreosCustomer, CorreosKey
     */
    public function findPendingMarketplaceOrders(): array
    {
        $credentials = $this->getCorreosCredentials();
        if ($credentials === null) {
            return [];
        }

        $lookbackDate = ( new \DateTime() )
            ->modify('-' . CronConfig::LOOKBACK_MONTHS . ' months')
            ->format('Y-m-d H:i:s');

        $orders = wc_get_orders([
            'limit'      => -1,
            'date_after' => $lookbackDate,
            'meta_query' => [
                [
                    'key'     => CorreosOficialMarketplace::META_KEY_TRACKING_NUMBER,
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
        ]);

        $rows = [];

        foreach ($orders as $order) {
            $statusLabel = (string) $order->get_meta('_correosoficial_marketplace_tracking_status');

            // Skip terminal orders — no further tracking needed.
            if (in_array($statusLabel, CronConfig::TERMINAL_STATUS_LABELS, true)) {
                continue;
            }

            $rows[] = array_merge(
                [
                    'id_order'                    => $order->get_id(),
                    'tracking_number'             => (string) $order->get_meta(CorreosOficialMarketplace::META_KEY_TRACKING_NUMBER),
                    'marketplace_tracking_status' => $statusLabel,
                ],
                $credentials
            );
        }

        return $rows;
    }

    /**
     * Persists the semantic tracking status label in the WC order meta
     * _correosoficial_marketplace_tracking_status.
     *
     * @param int    $orderId      WooCommerce order ID.
     * @param string $statusLabel  Human-readable label (e.g. 'Entregado').
     */
    public function updateMarketplaceTrackingStatus(int $orderId, string $statusLabel, string $eventDate = ''): void
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            error_log(sprintf(
                '[CorreosOficial][TrackingOrderRepository] updateMarketplaceTrackingStatus: order %d not found',
                $orderId
            ));
            return;
        }

        $order->update_meta_data('_correosoficial_marketplace_tracking_status', $statusLabel);
        if ($eventDate !== '') {
            $order->update_meta_data('_correosoficial_marketplace_tracking_date', $eventDate);
        }
        $order->save();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns credential columns from the first valid Correos company row in
     * correos_oficial_codes, or null if no row exists.
     *
     * @return array<string, string>|null
     */
    private function getCorreosCredentials(): ?array
    {
        $prefix = $this->wpdb->prefix;

        $row = $this->wpdb->get_row(
            "SELECT
                CorreosClientID, CorreosSecretID,
                CorreosUser, CorreosPassword,
                CorreosContract, CorreosCustomer, CorreosKey
            FROM `{$prefix}correos_oficial_codes`
            WHERE company = 'Correos'
            ORDER BY id ASC
            LIMIT 1",
            ARRAY_A
        );

        return $row ?: null;
    }
}
