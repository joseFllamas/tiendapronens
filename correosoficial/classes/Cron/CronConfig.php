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
 * Compile-time configuration for the tracking cron subsystem.
 *
 * All tunable knobs for the cron live here so that there is a single place
 * to adjust behaviour without hunting through service / repository code.
 *
 * DB-backed configuration (values editable from the back-office) still lives
 * in wp_correos_oficial_configuration and is read via CorreosOficialDao.
 */
class CronConfig
{
    // ── Order look-back window ────────────────────────────────────────────────

    /**
     * Maximum age (in months) of an order still considered trackable.
     * Orders older than this are skipped to avoid querying stale shipments.
     */
    const LOOKBACK_MONTHS = 4;

    // ── Status labels stored in correos_oficial_orders.status ────────────────

    /** Human-readable labels written to the `status` column. */
    const LABEL_DELIVERED     = 'Entregado';
    const LABEL_CANCELED      = 'Cancelado';
    const LABEL_RETURNED      = 'Devuelto';
    const LABEL_IN_PROGRESS   = 'En curso';
    const LABEL_PREREGISTERED = 'Grabado';

    /** Labels that mark an order as terminal (no further tracking needed). */
    const TERMINAL_STATUS_LABELS = [
        self::LABEL_DELIVERED,
        self::LABEL_CANCELED,
        self::LABEL_RETURNED,
    ];

    // ── API throttling ────────────────────────────────────────────────────────

    /**
     * Minimum delay in microseconds between consecutive API calls.
     * Prevents burst detection by CloudFront / Correos rate-limiting.
     */
    const THROTTLE_MIN_US = 600000;   // 600 ms

    /**
     * Maximum additional random jitter in microseconds added on top of
     * THROTTLE_MIN_US. A random value in [0, THROTTLE_JITTER_US] is added
     * each time so request intervals are not perfectly uniform.
     */
    const THROTTLE_JITTER_US = 300000; // up to 300 ms extra  →  600–900 ms total
}
