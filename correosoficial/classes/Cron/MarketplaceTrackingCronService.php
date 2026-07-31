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

use CorreosOficial\Classes\Apis\CorreosOficialRest;
use CorreosOficial\Models\CorreosOficialConfig;

/**
 * Orchestrates shipment-tracking for all pending Marketplace orders.
 *
 * Marketplace orders are NOT pre-registered, so they have no entry in
 * correos_oficial_orders. They always use the Correos P3 API (OAuth2).
 *
 * This service is intentionally separate from PreregisteredTrackingCronService,
 * which handles pre-registered orders (Correos + CEX). Both services are
 * invoked by AdminCorreosOficialCronProcessController::cronExecute().
 *
 * Design:
 *  - Zero inheritance from vendor DAO classes.
 *  - Uses TrackingOrderRepository for data access.
 *  - Uses StatusMapper (static PHP array) — zero DB queries for code mapping.
 *  - Uses CorreosRest::getOrderStatusP3() directly (P3 always for Marketplace).
 *  - WC order status change via wc_get_order()->update_status() when enabled.
 *  - Throttle 600–900 ms between API calls (same as PS service).
 *
 * Log files written (same paths/format as legacy cron):
 *   log/log_cron_register.txt      — execution summary per run
 *
 * run() returns a stats array:
 *   [
 *     'processed' => N,   // orders for which the API was called
 *     'updated'   => M,   // orders whose tracking status actually changed
 *     'errors'    => E,   // unexpected exceptions during processing
 *   ]
 */
class MarketplaceTrackingCronService
{
    /** @var CorreosOficialRest */
    private $correosRest;

    /** @var TrackingOrderRepository */
    private $repository;

    /** @var object Settings reader exposing readSettings(string $key): ?object {value} */
    private $dao;

    /** @var string  Absolute path to log/log_cron_register.txt */
    private $logFile;

    /**
     * All dependencies are injectable for unit testing.
     *
     * @param CorreosOficialRest|null      $correosRest  Correos P3 REST client (WC-specific, classes/Apis).
     * @param TrackingOrderRepository|null $repository   Order repository.
     * @param object|null                  $dao          Settings reader (must expose readSettings(string $key): ?object).
     */
    public function __construct(
        ?CorreosOficialRest       $correosRest = null,
        ?TrackingOrderRepository  $repository  = null,
        $dao = null
    ) {
        $this->correosRest = $correosRest ?? new CorreosOficialRest();
        $this->repository  = $repository  ?? new TrackingOrderRepository();
        $this->dao         = $dao         ?? new class {
            public function readSettings(string $name)
            {
                return CorreosOficialConfig::get_config_status($name);
            }
        };

        $logDir               = dirname(__FILE__, 3) . '/log/';
        $this->logFile        = $logDir . 'log_cron_register.txt';
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Fetches the current P3 tracking status for all pending Marketplace orders
     * and updates the WC order meta and (optionally) the WC order status.
     *
     * @return array{processed: int, updated: int, errors: int}
     */
    public function run(): array
    {
        $stats     = ['processed' => 0, 'updated' => 0, 'errors' => 0];
        $startTime = new \DateTime();
        $startStr  = $startTime->format('d-m-Y H:i:s');

        $this->writeTxt($this->logFile, "CorreosOficial [MARKETPLACE]: LOG del CRON\r\nComenzamos ejecucion Cron -> {$startStr}" . PHP_EOL, LOCK_EX);

        // Flag principal: ShowShippingStatusProcess (formulario actual).
        // ActivateOrderStatusChange queda como fallback de retro-compatibilidad.
        $changeStatus = $this->readSettingBool('ShowShippingStatusProcess')
            || $this->readSettingBool('ActivateOrderStatusChange');

        $orders = $this->repository->findPendingMarketplaceOrders();

        $this->writeTxt($this->logFile, sprintf(
            "[MARKETPLACE] Pedidos pendientes encontrados: %d" . PHP_EOL,
            count($orders)
        ), FILE_APPEND);

        foreach ($orders as $row) {
            $this->processRow($row, $changeStatus, $stats);
        }

        $endTime = new \DateTime();
        $elapsed = $startTime->diff($endTime);
        $this->writeTxt(
            $this->logFile,
            sprintf(
                "Finalizando cron Marketplace: processed=%d updated=%d errors=%d tiempo=%s" . PHP_EOL,
                $stats['processed'],
                $stats['updated'],
                $stats['errors'],
                $elapsed->format('%h horas %i minutos %s segundos')
            ),
            FILE_APPEND
        );

        return $stats;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Processes a single Marketplace order end-to-end:
     * 1. Call P3 API for event code.
     * 2. Map to semantic status using StatusMapper.
     * 3. Skip if status has not changed.
     * 4. Persist the new status label in WC order meta.
     * 5. Optionally update WC order status.
     * 6. Throttle.
     *
     * @param array<string, mixed>                         $row
     * @param bool                                         $changeStatus
     * @param array{processed:int,updated:int,errors:int} &$stats
     */
    private function processRow(array $row, bool $changeStatus, array &$stats): void
    {
        $idOrder        = (int)    $row['id_order'];
        $trackingNumber = (string) $row['tracking_number'];
        $currentLabel   = (string) ($row['marketplace_tracking_status'] ?? '');

        $stats['processed']++;

        try {
            $eventResult = $this->fetchP3EventCode($trackingNumber, $row);

            if ($eventResult === null) {
                $this->writeTxt($this->logFile, sprintf(
                    "[MARKETPLACE] id_order=%d tracking=%s => sin_eventos (estado_actual: '%s')" . PHP_EOL,
                    $idOrder, $trackingNumber, $currentLabel
                ), FILE_APPEND);
                return;
            }

            $eventCode      = $eventResult['code'];
            $eventDate      = $eventResult['date'];
            $semanticStatus = StatusMapper::getSemanticStatus($eventCode);
            $newLabel       = StatusMapper::getStatusLabel($semanticStatus);

            if ($newLabel === $currentLabel) {
                $this->writeTxt($this->logFile, sprintf(
                    "[MARKETPLACE] id_order=%d tracking=%s => eventCode=%s estado_sin_cambio: '%s'" . PHP_EOL,
                    $idOrder, $trackingNumber, $eventCode, $currentLabel
                ), FILE_APPEND);
                return;
            }

            $stats['updated']++;

            // Always persist the semantic label (and event date) to the WC order meta
            // so we can detect terminal orders on the next cron run without hitting the API again.
            $this->repository->updateMarketplaceTrackingStatus($idOrder, $newLabel, $eventDate);

            $this->writeTxt($this->logFile, sprintf(
                "[MARKETPLACE] id_order=%d tracking=%s => eventCode=%s '%s' -> '%s'%s" . PHP_EOL,
                $idOrder,
                $trackingNumber,
                $eventCode,
                $currentLabel,
                $newLabel,
                $changeStatus ? ' [WC_ESTADO_CAMBIADO]' : ' [cambio_WC_desactivado]'
            ), FILE_APPEND);

            if ($changeStatus) {
                $this->changeWcOrderStatus($idOrder, $semanticStatus);
            }
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->writeTxt($this->logFile, sprintf(
                "[ERROR][MARKETPLACE] id_order=%d tracking=%s: %s" . PHP_EOL,
                $idOrder,
                $trackingNumber,
                $e->getMessage()
            ), FILE_APPEND);
        } finally {
            usleep(CronConfig::THROTTLE_MIN_US + mt_rand(0, CronConfig::THROTTLE_JITTER_US));
        }
    }

    /**
     * Calls CorreosOficialRest::getOrderStatusP3($payload) and extracts the last
     * eventCode. Uses the WC-specific class (classes/Apis/CorreosOficialRest),
     * NOT the vendor \CorreosRest.
     *
     * P3 response structure (success):
     *   $result[0]['events'][N]['eventCode']  — array of events, chronological
     *
     * P3 response structure (API-level error or connection failure):
     *   false  |  $result['codigoRetorno'] / $result['mensajeRetorno']
     *
     * Returns null on connection failure (false), HTTP 500, API error, or zero events.
     *
     * @param string               $trackingNumber
     * @param array<string, mixed> $row             Must contain CorreosClientID, CorreosSecretID.
     * @return string|int|null
     */
    private function fetchP3EventCode(string $trackingNumber, array $row)
    {
        $payload = [
            'shipping_number' => $trackingNumber,
            'client'          => [
                'CorreosClientID' => (string) $row['CorreosClientID'],
                'CorreosSecretID' => (string) $row['CorreosSecretID'],
            ],
        ];

        $result = $this->correosRest->getOrderStatusP3($payload);

        $logEntry = (is_array($result) && isset($result[0]['events']))
            ? sprintf('[OK] lastEventCode=%s', end($result[0]['events'])['eventCode'] ?? 'n/a')
            : sprintf('[ERROR] response=%s', is_array($result)
                ? sprintf('codigoRetorno=%s', $result['codigoRetorno'] ?? '?')
                : var_export($result, true));

        $this->writeTxt($this->logFile, sprintf(
            "[MARKETPLACE P3] tracking=%s\n%s" . PHP_EOL,
            $trackingNumber,
            $logEntry
        ), FILE_APPEND);

        if (!is_array($result) || !isset($result[0]['events']) || empty($result[0]['events'])) {
            return null;
        }

        $lastEvent = end($result[0]['events']);
        $code = $lastEvent['eventCode'] ?? null;
        if ($code === null) {
            return null;
        }
        // Convert eventDate 'dd/mm/yyyy' to 'yyyy-mm-dd' for the HTML date input.
        $rawDate = $lastEvent['eventDate'] ?? '';
        $date    = '';
        if ($rawDate !== '' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $rawDate, $m)) {
            $date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return ['code' => $code, 'date' => $date];
    }

    /**
     * Updates the WC order status to the slug stored in correos_oficial_configuration
     * for the given semantic status (e.g. ShipmentDelivered → 'wc-correosoficial-delivered').
     *
     * @param int    $orderId
     * @param string $semanticStatus  One of StatusMapper::STATUS_* constants.
     */
    private function changeWcOrderStatus(int $orderId, string $semanticStatus): void
    {
        $configKey = StatusMapper::getConfigKey($semanticStatus);
        if ($configKey === null) {
            return;
        }

        $settingRow = $this->dao->readSettings($configKey);
        $wcStatus   = $settingRow ? (string) $settingRow->value : '';

        if (empty($wcStatus)) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->writeTxt($this->logFile, sprintf(
                "[ERROR][MARKETPLACE] changeWcOrderStatus: order %d not found" . PHP_EOL,
                $orderId
            ), FILE_APPEND);
            return;
        }

        $order->update_status($wcStatus);
    }

    /**
     * Reads a setting from correos_oficial_configuration and returns true
     * when the stored value is 'on', false otherwise.
     *
     * @param string $key  Configuration name (e.g. 'ActivateOrderStatusChange').
     * @return bool
     */
    private function readSettingBool(string $key): bool
    {
        $row = $this->dao->readSettings($key);
        if (!$row) {
            return false;
        }

        $value = strtolower(trim((string) $row->value));
        return in_array($value, ['on', '1', 'true', 'yes', 'si', 'sí'], true);
    }

    /**
     * Writes $content to a txt log file.
     * Fails silently — logging must never break the cron run.
     *
     * @param string $path
     * @param string $content
     * @param int    $flags   FILE_APPEND or LOCK_EX
     */
    private function writeTxt(string $path, string $content, int $flags): void
    {
        @file_put_contents($path, $content, $flags);
    }
}
