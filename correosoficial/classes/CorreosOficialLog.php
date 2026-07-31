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

namespace CorreosOficial\Classes;

if (!defined('WPINC')) {
	exit;
}

/**
 * WP-specific log utility.
 *
 * Replaces the vendor ecommerce_common_lib/Commons/CorreosOficialLog class for
 * all plugin-owned code.
 */
class CorreosOficialLog
{
    /**
     * Returns the current datetime with microseconds.
     *
     * @return string  e.g. "02-03-2026 15:18:41:123456"
     */
    public static function logDate(): string
    {
        $now = \DateTime::createFromFormat('U.u', (string) microtime(true));
        return $now->format('d-m-Y H:i:s:u');
    }

    /**
     * Returns the size of a log file in KB.
     * Creates the file if it does not exist yet (avoids filesize() warnings).
     *
     * @param string $file  Absolute path to the log file.
     * @return int          File size in KB.
     */
    public static function getSizeErrorLog(string $file): int
    {
        if (!file_exists($file)) {
            @file_put_contents($file, '');
        }
        $size = filesize($file);
        return $size !== false ? (int) ($size / 1000) : 0;
    }

    /**
     * Rotates a log file by copying it to a backup and deleting the original.
     *
     * @param string $file  Absolute path to the log file.
     */
    public static function rotateErrorLog(string $file): void
    {
        $backup = str_replace('.txt', '-lastbackup.txt', $file);
        if (!copy($file, $backup)) {
            error_log('CorreosOficial: No se pudo copiar el fichero de error para rotar: ' . $file);
        }
        if (!unlink($file)) {
            error_log('CorreosOficial: No se pudo eliminar fichero de error para rotar: ' . $file);
        }
    }
}
