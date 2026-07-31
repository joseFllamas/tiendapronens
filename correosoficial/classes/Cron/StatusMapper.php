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

/**
 * Maps raw tracking event codes (from Correos P3 API and CEX API) to semantic
 * status labels used throughout the cron service.
 *
 * This replaces the legacy runtime SQL lookup against correos_oficial_ws_status,
 * converting all static entries into a zero-query constant PHP array.
 *
 * Semantic statuses:
 *   'preregistered' → ShipmentPreregistered config key
 *   'in_progress'   → ShipmentInProgress    config key
 *   'delivered'     → ShipmentDelivered     config key
 *   'canceled'      → ShipmentCanceled      config key
 *   'returned'      → ShipmentReturned      config key
 *
 * Terminal statuses: delivered, canceled, returned → order is NOT processed again.
 *
 * Sources: correosoficial/sql/install.php, correos_oficial_ws_status table (all entries).
 * CEX numeric codes: codigoEstado field from estadoEnvios (seguimientoEnvio endpoint).
 * Correos P3 codes:  eventCode field from events array (search-continuous-trace endpoint).
 */
class StatusMapper
{
    // ── Semantic status constants ─────────────────────────────────────────────

    const STATUS_PREREGISTERED = 'preregistered';
    const STATUS_IN_PROGRESS   = 'in_progress';
    const STATUS_DELIVERED     = 'delivered';
    const STATUS_CANCELED      = 'canceled';
    const STATUS_RETURNED      = 'returned';

    // ── config key → correos_oficial_configuration value name ────────────────

    const CONFIG_KEY_MAP = [
        self::STATUS_PREREGISTERED => 'ShipmentPreregistered',
        self::STATUS_IN_PROGRESS   => 'ShipmentInProgress',
        self::STATUS_DELIVERED     => 'ShipmentDelivered',
        self::STATUS_CANCELED      => 'ShipmentCanceled',
        self::STATUS_RETURNED      => 'ShipmentReturned',
    ];

    // ── Terminal statuses (stop tracking after reaching one of these) ─────────

    const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_CANCELED,
        self::STATUS_RETURNED,
    ];

    /**
     * Event codes that map to a non-default (non-in_progress) semantic status.
     * Only exceptions are listed; anything not found defaults to 'in_progress'.
     *
     * Numeric string keys = CEX codigoEstado / Legacy Correos numeric codes.
     * Alphanumeric string keys = Correos P3 eventCode values.
     *
     * @var array<string, string>
     */
    private const EVENT_MAP = [

        // ── PRERREGISTRADO ────────────────────────────────────────────────────
        '1'          => self::STATUS_PREREGISTERED, // CEX: Sin recepción / Correos: preregistrado
        'A020000R'   => self::STATUS_PREREGISTERED,
        'A090000V'   => self::STATUS_PREREGISTERED,
        'A150000R'   => self::STATUS_PREREGISTERED,
        'O040000R'   => self::STATUS_PREREGISTERED,
        'SR01000V'   => self::STATUS_PREREGISTERED,
        'SR05000V'   => self::STATUS_PREREGISTERED,
        'SR070B0R'   => self::STATUS_PREREGISTERED,
        'SR070C0R'   => self::STATUS_PREREGISTERED,
        'SR070D0R'   => self::STATUS_PREREGISTERED,
        'SR070G0R'   => self::STATUS_PREREGISTERED,
        'SR070I0R'   => self::STATUS_PREREGISTERED,
        'SR070J0R'   => self::STATUS_PREREGISTERED,
        'SR070S0R'   => self::STATUS_PREREGISTERED,
        'SR070U0R'   => self::STATUS_PREREGISTERED,
        'SR070Y0R'   => self::STATUS_PREREGISTERED,
        'SR080A0R'   => self::STATUS_PREREGISTERED,
        'SR080E0R'   => self::STATUS_PREREGISTERED,
        'SR080F0R'   => self::STATUS_PREREGISTERED,
        'SR080M0R'   => self::STATUS_PREREGISTERED,
        'SR080N0R'   => self::STATUS_PREREGISTERED,
        'SR080O0R'   => self::STATUS_PREREGISTERED,
        'SR080R0R'   => self::STATUS_PREREGISTERED,
        'SR080W0R'   => self::STATUS_PREREGISTERED,
        'SR09000R'   => self::STATUS_PREREGISTERED,
        'X010000V'   => self::STATUS_PREREGISTERED,
        'X240000V'   => self::STATUS_PREREGISTERED,
        'Z010PF0R'   => self::STATUS_PREREGISTERED,
        'Z130000R'   => self::STATUS_PREREGISTERED,

        // ── ENVÍO ENTREGADO ───────────────────────────────────────────────────
        '12'         => self::STATUS_DELIVERED, // CEX + Correos legacy
        'A170000V'   => self::STATUS_DELIVERED,
        'E010670V'   => self::STATUS_DELIVERED,
        'H010710V'   => self::STATUS_DELIVERED,
        'H300030V'   => self::STATUS_DELIVERED,
        'H300040V'   => self::STATUS_DELIVERED,
        'H300050V'   => self::STATUS_DELIVERED,
        'H300070V'   => self::STATUS_DELIVERED,
        'I010000V'   => self::STATUS_DELIVERED,
        'I010110V'   => self::STATUS_DELIVERED,
        'I010120V'   => self::STATUS_DELIVERED,
        'I010130V'   => self::STATUS_DELIVERED,
        'I010400V'   => self::STATUS_DELIVERED,
        'I0104E0V'   => self::STATUS_DELIVERED,
        'I011010V'   => self::STATUS_DELIVERED,
        'I011020V'   => self::STATUS_DELIVERED,
        'I011030V'   => self::STATUS_DELIVERED,
        'I01E030V'   => self::STATUS_DELIVERED,
        'I01E070V'   => self::STATUS_DELIVERED,
        'I01E090V'   => self::STATUS_DELIVERED,
        'I01E410V'   => self::STATUS_DELIVERED,
        'I01H010V'   => self::STATUS_DELIVERED,
        'I01H030V'   => self::STATUS_DELIVERED,
        'I01H040V'   => self::STATUS_DELIVERED,
        'I01H050V'   => self::STATUS_DELIVERED,
        'I01H060V'   => self::STATUS_DELIVERED,
        'I01H210V'   => self::STATUS_DELIVERED,
        'I01H220V'   => self::STATUS_DELIVERED,
        'I01H230V'   => self::STATUS_DELIVERED,
        'I240300V'   => self::STATUS_DELIVERED,
        'IPUDO02V'   => self::STATUS_DELIVERED, // Entregado en PUDO/Oficina/Citypaq
        'R010750V'   => self::STATUS_DELIVERED,
        'X090030R'   => self::STATUS_DELIVERED,
        'X120000V'   => self::STATUS_DELIVERED,
        'X290030R'   => self::STATUS_DELIVERED,
        'X340000V'   => self::STATUS_DELIVERED,
        'X350000V'   => self::STATUS_DELIVERED,
        'X380000V'   => self::STATUS_DELIVERED,

        // ── ENVÍO ANULADO ─────────────────────────────────────────────────────
        '13'         => self::STATUS_CANCELED, // CEX + Correos legacy
        '14'         => self::STATUS_CANCELED,
        '15'         => self::STATUS_CANCELED,
        '16'         => self::STATUS_CANCELED,
        '19'         => self::STATUS_CANCELED,
        '31'         => self::STATUS_CANCELED,
        'A040000R'   => self::STATUS_CANCELED,
        'A060000R'   => self::STATUS_CANCELED,
        'E070000R'   => self::STATUS_CANCELED,
        'E130000R'   => self::STATUS_CANCELED,
        'E140000R'   => self::STATUS_CANCELED,
        'E150000R'   => self::STATUS_CANCELED,
        'E230000R'   => self::STATUS_CANCELED,
        'E23E500R'   => self::STATUS_CANCELED,
        'E310000R'   => self::STATUS_CANCELED,
        'H01I270R'   => self::STATUS_CANCELED,
        'H01K100V'   => self::STATUS_CANCELED,
        'H01K110V'   => self::STATUS_CANCELED,
        'H01K130V'   => self::STATUS_CANCELED,
        'H01K180V'   => self::STATUS_CANCELED,
        'H01K200V'   => self::STATUS_CANCELED,
        'H01K220V'   => self::STATUS_CANCELED,
        'H01K230V'   => self::STATUS_CANCELED,
        'H01K240V'   => self::STATUS_CANCELED,
        'H01K280V'   => self::STATUS_CANCELED,
        'L010000R'   => self::STATUS_CANCELED,
        'L040000R'   => self::STATUS_CANCELED,
        'L04G000V'   => self::STATUS_CANCELED,
        'O020BS0R'   => self::STATUS_CANCELED,
        'O080000V'   => self::STATUS_CANCELED,
        'VA06GO0R'   => self::STATUS_CANCELED,
        'VA06GO0V'   => self::STATUS_CANCELED,
        'X160000V'   => self::STATUS_CANCELED,
        'X190000V'   => self::STATUS_CANCELED,

        // ── ENVÍO DEVUELTO ────────────────────────────────────────────────────
        '17'         => self::STATUS_RETURNED, // CEX + Correos legacy
        'ADV0000V'   => self::STATUS_RETURNED,
        'B250000R'   => self::STATUS_RETURNED,
        'DX92002R'   => self::STATUS_RETURNED,
        'DX92007V'   => self::STATUS_RETURNED,
        'DX92132R'   => self::STATUS_RETURNED,
        'DX92151R'   => self::STATUS_RETURNED,
        'DX92153R'   => self::STATUS_RETURNED,
        'DX92156R'   => self::STATUS_RETURNED,
        'E23E220R'   => self::STATUS_RETURNED,
        'E240000R'   => self::STATUS_RETURNED,
        'H01E100R'   => self::STATUS_RETURNED,
        'H01E110R'   => self::STATUS_RETURNED,
        'H01E130R'   => self::STATUS_RETURNED,
        'H01E180R'   => self::STATUS_RETURNED,
        'H01E200R'   => self::STATUS_RETURNED,
        'H01E220R'   => self::STATUS_RETURNED,
        'H01E230R'   => self::STATUS_RETURNED,
        'H01E240R'   => self::STATUS_RETURNED,
        'H01E280R'   => self::STATUS_RETURNED,
        'H01R220R'   => self::STATUS_RETURNED,
        'H01R240R'   => self::STATUS_RETURNED,
        'I020000V'   => self::STATUS_RETURNED,
        'L01R380V'   => self::STATUS_RETURNED,
        'L020000V'   => self::STATUS_RETURNED,
        'L030000R'   => self::STATUS_RETURNED,
        'L030000V'   => self::STATUS_RETURNED,
        'L031060R'   => self::STATUS_RETURNED,
        'L031070R'   => self::STATUS_RETURNED,
        'L03D010R'   => self::STATUS_RETURNED,
        'L03D100R'   => self::STATUS_RETURNED,
        'L03D130R'   => self::STATUS_RETURNED,
        'L03D230R'   => self::STATUS_RETURNED,
        'L03D240R'   => self::STATUS_RETURNED,
        'L03D270V'   => self::STATUS_RETURNED,
        'L03D300R'   => self::STATUS_RETURNED,
        'L03D320R'   => self::STATUS_RETURNED,
        'L03D360R'   => self::STATUS_RETURNED,
        'L03D370R'   => self::STATUS_RETURNED,
        'L03DR40V'   => self::STATUS_RETURNED,
        'L03I270R'   => self::STATUS_RETURNED,
        'L060000R'   => self::STATUS_RETURNED,
        'L060100R'   => self::STATUS_RETURNED,
        'L060110R'   => self::STATUS_RETURNED,
        'L060120R'   => self::STATUS_RETURNED,
        'L060130R'   => self::STATUS_RETURNED,
        'L060140R'   => self::STATUS_RETURNED,
        'L060150R'   => self::STATUS_RETURNED,
        'L060160R'   => self::STATUS_RETURNED,
        'L060170R'   => self::STATUS_RETURNED,
        'L060180R'   => self::STATUS_RETURNED,
        'L060190R'   => self::STATUS_RETURNED,
        'L060200R'   => self::STATUS_RETURNED,
        'L060210R'   => self::STATUS_RETURNED,
        'L060220R'   => self::STATUS_RETURNED,
        'L060230R'   => self::STATUS_RETURNED,
        'L060240R'   => self::STATUS_RETURNED,
        'L060250R'   => self::STATUS_RETURNED,
        'L060260R'   => self::STATUS_RETURNED,
        'L060270R'   => self::STATUS_RETURNED,
        'L060280R'   => self::STATUS_RETURNED,
        'L060290R'   => self::STATUS_RETURNED,
        'L060990R'   => self::STATUS_RETURNED,
        'O130000V'   => self::STATUS_RETURNED,
        'O140000V'   => self::STATUS_RETURNED,
        'X090010R'   => self::STATUS_RETURNED,
        'X090020R'   => self::STATUS_RETURNED,
        'X090070R'   => self::STATUS_RETURNED,
        'X090080R'   => self::STATUS_RETURNED,
        'X090110R'   => self::STATUS_RETURNED,
        'X090120R'   => self::STATUS_RETURNED,
        'X090140R'   => self::STATUS_RETURNED,
        'X090150R'   => self::STATUS_RETURNED,
        'X090350R'   => self::STATUS_RETURNED,
        'X090410R'   => self::STATUS_RETURNED,
        'X170000V'   => self::STATUS_RETURNED,
        'X290010R'   => self::STATUS_RETURNED,
        'X290020R'   => self::STATUS_RETURNED,
        'X290070R'   => self::STATUS_RETURNED,
        'X290080R'   => self::STATUS_RETURNED,
        'X290110R'   => self::STATUS_RETURNED,
        'X290120R'   => self::STATUS_RETURNED,
        'X290140R'   => self::STATUS_RETURNED,
        'X290150R'   => self::STATUS_RETURNED,
        'X290350R'   => self::STATUS_RETURNED,
        'X290410R'   => self::STATUS_RETURNED,
        'X320000V'   => self::STATUS_RETURNED,
        'Z06E010R'   => self::STATUS_RETURNED,
        'Z190000R'   => self::STATUS_RETURNED,
        'Z190DC0R'   => self::STATUS_RETURNED,
        'Z190DD0R'   => self::STATUS_RETURNED,
        'Z190DF0R'   => self::STATUS_RETURNED,
        'Z190DP0R'   => self::STATUS_RETURNED,
        'Z190DR0R'   => self::STATUS_RETURNED,
    ];

    /**
     * Resolves a raw API event code to a semantic status.
     *
     * @param string|int $eventCode  Raw code from Correos P3 (eventCode) or CEX (codigoEstado).
     * @return string                One of the STATUS_* constants.
     */
    public static function getSemanticStatus($eventCode): string
    {
        return self::EVENT_MAP[(string) $eventCode] ?? self::STATUS_IN_PROGRESS;
    }

    /**
     * Returns the correos_oficial_configuration key for a given semantic status.
     *
     * @param string $semanticStatus
     * @return string|null  Config name, or null if the status has no WC state mapping.
     */
    public static function getConfigKey(string $semanticStatus): ?string
    {
        return self::CONFIG_KEY_MAP[$semanticStatus] ?? null;
    }

    /**
     * Returns true when no further tracking is needed for this shipment.
     *
     * @param string $semanticStatus
     * @return bool
     */
    public static function isTerminal(string $semanticStatus): bool
    {
        return in_array($semanticStatus, self::TERMINAL_STATUSES, true);
    }

    /**
     * Human-readable label stored in correos_oficial_orders.status column
     * and in the _correosoficial_marketplace_tracking_status order meta.
     *
     * @param string $semanticStatus
     * @return string
     */
    public static function getStatusLabel(string $semanticStatus): string
    {
        $labels = [
            self::STATUS_PREREGISTERED => CronConfig::LABEL_PREREGISTERED,
            self::STATUS_IN_PROGRESS   => CronConfig::LABEL_IN_PROGRESS,
            self::STATUS_DELIVERED     => CronConfig::LABEL_DELIVERED,
            self::STATUS_CANCELED      => CronConfig::LABEL_CANCELED,
            self::STATUS_RETURNED      => CronConfig::LABEL_RETURNED,
        ];

        return $labels[$semanticStatus] ?? CronConfig::LABEL_IN_PROGRESS;
    }
}
