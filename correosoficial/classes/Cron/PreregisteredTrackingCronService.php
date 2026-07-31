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
use CorreosOficial\Classes\Apis\CorreosOficialCEXRest;
use CorreosOficial\Models\CorreosOficialConfig;

/**
 * Orchestrates shipment-tracking for all pending pre-registered Correos and CEX orders.
 *
 * Pre-registered orders have an entry in correos_oficial_orders + correos_oficial_saved_orders.
 * This service replaces the legacy vendor CronCorreosOficial class.
 *
 * Both Correos P3 (OAuth2) and legacy Basic Auth credential sets are supported:
 *   - P3:     CorreosClientID + CorreosSecretID → getOrderStatusP3()
 *   - Legacy: CorreosUser + CorreosPassword + CorreosContract + CorreosCustomer → getOrderStatus()
 *
 * Design mirrors MarketplaceTrackingCronService:
 *  - Uses TrackingOrderRepository for data access.
 *  - Uses StatusMapper (static PHP array) — zero DB queries for code mapping.
 *  - WC order status change via wc_get_order()->update_status() when enabled.
 *  - Throttle 600–900 ms between API calls.
 *
 * Log files written (same paths as legacy cron, same format as MarketplaceTrackingCronService):
 *   log/log_cron_register.txt      — execution summary per run
 *
 * run() returns a stats array:
 *   [
 *     'processed' => N,   // orders for which the API was called
 *     'updated'   => M,   // orders whose tracking status actually changed
 *     'errors'    => E,   // unexpected exceptions during processing
 *   ]
 */
class PreregisteredTrackingCronService
{
    /** @var CorreosOficialRest */
    private $correosRest;

    /** @var CorreosOficialCEXRest */
    private $cexRest;

    /** @var TrackingOrderRepository */
    private $repository;

    /** @var object Settings reader exposing readSettings(string $key): ?object {value} */
    private $dao;

    /** @var string */
    private $logFile;

    /**
     * All dependencies are injectable for unit testing.
     *
     * @param CorreosOficialRest|null      $correosRest
     * @param CorreosOficialCEXRest|null   $cexRest
     * @param TrackingOrderRepository|null $repository
     * @param object|null                  $dao  Settings reader (must expose readSettings(string $key): ?object).
     */
    public function __construct(
        ?CorreosOficialRest       $correosRest = null,
        ?CorreosOficialCEXRest    $cexRest     = null,
        ?TrackingOrderRepository  $repository  = null,
        $dao = null
    ) {
        $this->correosRest = $correosRest ?? new CorreosOficialRest();
        $this->cexRest     = $cexRest     ?? new CorreosOficialCEXRest();
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
     * Fetches the current tracking status for all pending pre-registered orders
     * (Correos + CEX) and updates the WC order meta and (optionally) the WC
     * order status.
     *
     * @return array{processed: int, updated: int, errors: int}
     */
    public function run(): array
    {
        $stats     = ['processed' => 0, 'updated' => 0, 'errors' => 0];
        $startTime = new \DateTime();
        $startStr  = $startTime->format('d-m-Y H:i:s');

        // Initialise log files (same format as legacy cron, overwrite on each run)
        $this->writeTxt($this->logFile, "[CRON][INICIO] {$startStr} | activo=si" . PHP_EOL, LOCK_EX);

        // Flag principal en el formulario actual: ShowShippingStatusProcess.
        // ActivateOrderStatusChange queda como compatibilidad con instalaciones antiguas
        // (en el formulario nuevo no se persiste, por lo que sin este OR el cron nunca
        // cambiaría el estado WC aunque el cliente tenga el mapeo configurado).
        $changeStatus = $this->readSettingBool('ShowShippingStatusProcess')
            || $this->readSettingBool('ActivateOrderStatusChange');

        $isAutomaticTrackingEnabled = $this->readSettingBool('ActivateAutomaticTracking');
        $isStoreProgressEnabled     = $this->readSettingBool('ShowShippingStatusProcess');

        $this->writeTxt($this->logFile, sprintf(
            "[CRON][CHECKS] activar_seguimiento_automatico=%s mostrar_progreso_estado_envio_tienda=%s" . PHP_EOL,
            $isAutomaticTrackingEnabled ? 'si' : 'no',
            $isStoreProgressEnabled ? 'si' : 'no'
        ), FILE_APPEND);

        foreach (['Correos', 'CEX'] as $carrierType) {
            $orders = $this->repository->findPendingPreregisteredOrders($carrierType);

            $this->writeTxt($this->logFile, sprintf(
                "[CRON][PENDIENTES][%s] total=%d" . PHP_EOL,
                $carrierType,
                count($orders)
            ), FILE_APPEND);

            foreach ($orders as $row) {
                $this->processRow($row, $carrierType, $changeStatus, $stats);
            }
        }

        $endTime = new \DateTime();
        $elapsed = $startTime->diff($endTime);
        $this->writeTxt($this->logFile, sprintf(
            "[CRON][FIN] procesados=%d actualizados=%d errores=%d duracion=%s" . PHP_EOL,
            $stats['processed'],
            $stats['updated'],
            $stats['errors'],
            $elapsed->format('%h h %i m %s s')
        ), FILE_APPEND);

        return $stats;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Processes a single pre-registered order end-to-end:
     * 1. Call API (P3 or Legacy for Correos, CEX endpoint for CEX).
     * 2. Map to semantic status using StatusMapper.
     * 3. Skip if status has not changed.
     * 4. Persist new status in correos_oficial_orders.
     * 5. Optionally update WC order status.
     * 6. Throttle.
     *
     * @param array<string, mixed>                         $row
     * @param string                                       $carrierType  'Correos' or 'CEX'
     * @param bool                                         $changeStatus
     * @param array{processed:int,updated:int,errors:int} &$stats
     */
    private function processRow(array $row, string $carrierType, bool $changeStatus, array &$stats): void
    {
        $idOrder        = (int)    $row['id_order'];
        $trackingNumber = (string) $row['tracking_number'];
        $currentLabel   = (string) ($row['status'] ?? '');

        $stats['processed']++;

        try {
            $eventCode = ($carrierType === 'CEX')
                ? $this->fetchCEXEventCode($trackingNumber, $row)
                : $this->fetchCorreosEventCode($trackingNumber, $row);

            if ($eventCode === null) {
                $this->writeTxt($this->logFile, sprintf(
                    "[SEGUIMIENTO][%s] pedido=%d tracking=%s sin_evento estado='%s'" . PHP_EOL,
                    $carrierType,
                    $idOrder,
                    $trackingNumber,
                    $currentLabel
                ), FILE_APPEND);
                return;
            }

            $semanticStatus = StatusMapper::getSemanticStatus((string) $eventCode);
            $newLabel       = StatusMapper::getStatusLabel($semanticStatus);

            if ($newLabel === $currentLabel) {
                // El estado de tracking no cambia, pero WC puede estar desfasado (p. ej. on-hold).
                $wcResult = $changeStatus
                    ? $this->changeWcOrderStatus($idOrder, $semanticStatus)
                    : 'wc=off';

                $this->writeTxt($this->logFile, sprintf(
                    "[SEGUIMIENTO][%s] pedido=%d tracking=%s evento=%s estado='%s' %s" . PHP_EOL,
                    $carrierType,
                    $idOrder,
                    $trackingNumber,
                    $eventCode,
                    $currentLabel,
                    $wcResult
                ), FILE_APPEND);
                return;
            }

            $stats['updated']++;

            $this->repository->updatePreregisteredStatus($idOrder, (string) $eventCode, $newLabel);

            $wcResult = $changeStatus
                ? $this->changeWcOrderStatus($idOrder, $semanticStatus)
                : 'wc=off';

            $this->writeTxt($this->logFile, sprintf(
                "[SEGUIMIENTO][%s] pedido=%d tracking=%s evento=%s '%s'->'%s' %s" . PHP_EOL,
                $carrierType,
                $idOrder,
                $trackingNumber,
                $eventCode,
                $currentLabel,
                $newLabel,
                $wcResult
            ), FILE_APPEND);
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->writeTxt($this->logFile, sprintf(
                "[ERROR][%s] pedido=%d tracking=%s: %s" . PHP_EOL,
                $carrierType,
                $idOrder,
                $trackingNumber,
                $e->getMessage()
            ), FILE_APPEND);
        } finally {
            usleep(CronConfig::THROTTLE_MIN_US + mt_rand(0, CronConfig::THROTTLE_JITTER_US));
        }
    }

    /**
     * Fetches the last event code for a Correos shipment.
     * Detects P3 (OAuth2) vs legacy (Basic Auth) by checking if CorreosClientID is set.
     *
     * P3 response:     $result[0]['events'][N]['eventCode']
     * Legacy response: array of stdObjects with ->eventos[N]->codEvento
     *
     * @param string               $trackingNumber
     * @param array<string, mixed> $row
     * @return string|int|null
     */
    private function fetchCorreosEventCode(string $trackingNumber, array $row)
    {
        $isP3 = !empty($row['CorreosClientID']) && $row['CorreosClientID'] !== 'n/a';

        if ($isP3) {
            $payload = [
                'shipping_number' => $trackingNumber,
                'client'          => [
                    'CorreosClientID' => (string) $row['CorreosClientID'],
                    'CorreosSecretID' => (string) $row['CorreosSecretID'],
                ],
            ];

            $result = $this->correosRest->getOrderStatusP3($payload);

            $logEntry = (is_array($result) && isset($result[0]['events']))
                ? sprintf('[OK] ultimo_codigo_evento=%s', end($result[0]['events'])['eventCode'] ?? 'n/a')
                : sprintf('[ERROR] respuesta=%s', is_array($result)
                    ? sprintf('codigoRetorno=%s', $result['codigoRetorno'] ?? '?')
                    : var_export($result, true));

            $this->writeTxt($this->logFile, sprintf(
                "[Correos P3] tracking=%s\n%s" . PHP_EOL,
                $trackingNumber,
                $logEntry
            ), FILE_APPEND);

            if (!is_array($result) || !isset($result[0]['events']) || empty($result[0]['events'])) {
                return null;
            }

            $lastEvent = end($result[0]['events']);
            return $lastEvent['eventCode'] ?? null;
        }

        // Legacy Basic Auth path
        $payload = [
            'shipping_number' => $trackingNumber,
            'client'          => [
                'CorreosUser'     => (string) $row['CorreosUser'],
                'CorreosPassword' => (string) $row['CorreosPassword'],
                'CorreosContract' => (string) $row['CorreosContract'],
                'CorreosCustomer' => (string) $row['CorreosCustomer'],
            ],
        ];

        $result = $this->correosRest->getOrderStatus($payload, true);

        $this->writeTxt($this->logFile, sprintf(
            "[Correos Legacy] tracking=%s\nrespuesta: %s" . PHP_EOL,
            $trackingNumber,
            print_r($result, true)
        ), FILE_APPEND);

        if (!is_array($result) || empty($result)) {
            return null;
        }

        $lastObj  = end($result);
        $eventos  = $lastObj->eventos ?? [];
        if (empty($eventos)) {
            return null;
        }

        $lastEvento = end($eventos);
        return $lastEvento->codEvento ?? null;
    }

    /**
     * Fetches the last codigoEstado for a CEX shipment.
     *
     * CEX response:
     *   $result['codigoRetorno']                    int   200 = OK
     *   $result['mensajeRetorno']->estadoEnvios[N]->codEstado  int
     *
     * @param string               $trackingNumber
     * @param array<string, mixed> $row
     * @return string|int|null
     */
    private function fetchCEXEventCode(string $trackingNumber, array $row)
    {
        $payload = [
            'shipping_number' => $trackingNumber,
            'client'          => [
                'CEXCustomer' => (string) $row['CEXCustomer'],
                'CEXUser'     => (string) $row['CEXUser'],
                'CEXPassword' => (string) $row['CEXPassword'],
            ],
        ];

        $result = $this->cexRest->getOrderStatus($payload);

        $mensajeObj   = $result['mensajeRetorno'] ?? null;
        $estadoEnvios = is_object($mensajeObj) ? ($mensajeObj->estadoEnvios ?? []) : [];
        // su argumento por referencia y un cast (array) produce un valor
        // temporal no referenciable (fatal en PHP 8.1+).
        if (is_object($estadoEnvios)) {
            $estadoEnvios = [$estadoEnvios];
        } elseif (!is_array($estadoEnvios)) {
            $estadoEnvios = [];
        }

        $estadosLog = '';
        foreach ($estadoEnvios as $estado) {
            $estadosLog .= sprintf(
                'codEstado=%s descEstado=%s fecha=%s hora=%s; ',
                $estado->codEstado ?? '',
                $estado->descEstado ?? '',
                $estado->fechaEstado ?? '',
                $estado->horaEstado ?? ''
            );
        }

        $this->writeTxt($this->logFile, sprintf(
            "[CEX] tracking=%s\nESTADOS: %s" . PHP_EOL,
            $trackingNumber,
            rtrim($estadosLog, '; ') ?: ($result === false ? 'error curl (respuesta false)' : 'sin estados')
        ), FILE_APPEND);

        $httpCode = (int) ($result['codigoRetorno'] ?? 0);
        if ($httpCode !== 200 || empty($estadoEnvios)) {
            return null;
        }

        $lastState = end($estadoEnvios);
        return $lastState->codigoEstado ?? null;
    }

    /**
     * Intenta actualizar el estado WC del pedido y devuelve un string compacto
     * con el resultado para incluirlo en la línea [SEGUIMIENTO].
     * Solo escribe al log de errores en casos de aviso o excepción.
     *
     * @param int    $orderId
     * @param string $semanticStatus  One of StatusMapper::STATUS_* constants.
     * @return string  p.ej. "wc='on-hold'->'processing'", "wc=bloqueado:terminal('cancelled')", "wc=sin_cambio"
     */
    private function changeWcOrderStatus(int $orderId, string $semanticStatus): string
    {
        $configKey = StatusMapper::getConfigKey($semanticStatus);
        if ($configKey === null) {
            $this->writeTxt($this->logFile, sprintf(
                "[WARN][PREREGISTERED] changeWcOrderStatus: sin configKey para semanticStatus=%s (pedido=%d)" . PHP_EOL,
                $semanticStatus,
                $orderId
            ), FILE_APPEND);
            return 'wc=sin_config';
        }

        $settingRow = $this->dao->readSettings($configKey);
        $wcStatus   = $settingRow ? (string) $settingRow->value : '';
        $wcStatus   = $this->normalizeWcStatus($wcStatus);

        if (empty($wcStatus)) {
            $this->writeTxt($this->logFile, sprintf(
                "[WARN][PREREGISTERED] changeWcOrderStatus: setting '%s' vacío en correos_oficial_configuration (orderId=%d, semanticStatus=%s)" . PHP_EOL,
                $configKey,
                $orderId,
                $semanticStatus
            ), FILE_APPEND);
            return 'wc=setting_vacio';
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->writeTxt($this->logFile, sprintf(
                "[ERROR][PREREGISTERED] changeWcOrderStatus: pedido %d no encontrado" . PHP_EOL,
                $orderId
            ), FILE_APPEND);
            return 'wc=pedido_no_encontrado';
        }

        $previousStatus = $order->get_status();

        // No sobrescribir estados terminales ni el mismo estado.
        if (in_array($previousStatus, ['completed', 'cancelled'], true) || $previousStatus === $wcStatus) {
            return sprintf("wc='%s'", $previousStatus);
        }

        try {
            $order->update_status($wcStatus, '[CorreosOficial Cron] ' . $semanticStatus);
            $newStatus = $order->get_status();
            return sprintf("wc='%s'->'%s'", $previousStatus, $newStatus);
        } catch (\Throwable $e) {
            $this->writeTxt($this->logFile, sprintf(
                "[ERROR][PREREGISTERED] changeWcOrderStatus: pedido=%d update_status('%s') excepcion: %s" . PHP_EOL,
                $orderId,
                $wcStatus,
                $e->getMessage()
            ), FILE_APPEND);
            return sprintf("wc=error('%s')", $wcStatus);
        }
    }

    /**
     * Normaliza estados de WooCommerce almacenados en configuración.
     * Admite variantes como "wc-processing" y devuelve "processing".
     *
     * @param string $status
     * @return string
     */
    private function normalizeWcStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        return $status;
    }

    /**
     * @param string $key  Configuration name.
     * @return bool
     */
    private function readSettingBool(string $key): bool
    {
        $row = $this->dao->readSettings($key);
        if (!$row) {
            return false;
        }

        $value = strtolower(trim((string) $row->value));

        // Aceptamos varias representaciones "true" porque el formulario admin
        // y migraciones antiguas pueden haber persistido el flag de formas distintas.
        return in_array($value, ['on', '1', 'true', 'yes', 'si', 'sí'], true);
    }

    /**
     * Writes content to a log file. Fails silently.
     *
     * @param string $path
     * @param string $content
     * @param int    $flags   FILE_APPEND or LOCK_EX
     */
    private function writeTxt(string $path, string $content, int $flags): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, $content, $flags);
    }
}
