<?php

namespace CorreosOficial\Upgrade;

defined('ABSPATH') || exit;

class CorreosOficialUpgrade {

    private static $tables = array(
        'correos_oficial_configuration',
        'correos_oficial_senders',
        'correos_oficial_codes',
        'correos_oficial_codes_actives',
        'correos_oficial_orders',
        'correos_oficial_saved_orders',
        'correos_oficial_returns',
        'correos_oficial_saved_returns',
        'correos_oficial_pickups_returns',
        'correos_oficial_products',
        'correos_oficial_carriers_products',
        'correos_oficial_customs_description',
        'correos_oficial_requests',
        'correos_oficial_shipping_method_rules',
        'correos_oficial_sga_orders_log',
        'correos_oficial_sga_orders_status',
    );

    // ─────────────────────────────────────────────────────────────────────────
    // Rutas de archivos
    // ─────────────────────────────────────────────────────────────────────────

    private static function get_backup_file_path() {
        return plugin_dir_path(__FILE__) . 'plugin_backup_tables.sql';
    }

    private static function get_upgrade_file_path() {
        return plugin_dir_path(__FILE__) . 'upgrade.sql';
    }

    private static function get_execution_log_path() {
        return plugin_dir_path(__FILE__) . 'upgrade_execution.log';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Carga de SQL con inyección de prefijo real
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Carga un archivo SQL y reemplaza el placeholder {PREFIX} con el prefijo
     * real de la BD del cliente ($wpdb->prefix).
     * El install.sql distribuido con el plugin usa {PREFIX} en lugar de wp_.
     *
     * @param  string $file Ruta absoluta al archivo SQL
     * @return string       Contenido SQL con el prefijo correcto inyectado
     */
    private static function read_sql_with_prefix( $file ) {
        global $wpdb;
        $content = file_get_contents( $file );
        return str_replace( '{PREFIX}', $wpdb->prefix, $content );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Backup de estructura (desde la BD real del cliente)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtiene el CREATE TABLE de una tabla específica directamente desde la BD.
     *
     * @param  string      $table_name Nombre sin prefijo
     * @return string|false
     */
    public static function get_table_create_sql( $table_name ) {
        global $wpdb;
        $full = $wpdb->prefix . $table_name;
        // Comprobar existencia antes de SHOW CREATE TABLE para evitar errores MySQL en el log.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) !== $full ) {
            return false;
        }
        $result = $wpdb->get_row( "SHOW CREATE TABLE `{$full}`", ARRAY_N );
        return $result ? $result[1] : false;
    }

    /**
     * Exporta la estructura real de todas las tablas del plugin a un archivo SQL.
     *
     * @return bool
     */
    public static function export_tables_structure() {
        $sql  = "-- Backup de estructura desde BD del cliente\n";
        $sql .= "-- Generado: " . gmdate( 'Y-m-d H:i:s' ) . "\n";
        $sql .= "-- IMPORTANTE: Refleja la estructura REAL de la BD del cliente\n\n";

        $backed_up = 0;
        foreach ( self::$tables as $table ) {
            $create = self::get_table_create_sql( $table );
            if ( $create ) {
                $sql .= "-- Tabla: {$table}\n{$create};\n\n";
                $backed_up++;
            } else {
                $sql .= "-- Tabla: {$table} (NO EXISTE en la BD actual)\n\n";
            }
        }

        $sql .= "-- Total de tablas respaldadas: {$backed_up} de " . count( self::$tables ) . "\n";

        $result = file_put_contents( self::get_backup_file_path(), $sql );
        if ( $result !== false ) {
            error_log( "CorreosOficial Backup: {$backed_up} tablas exportadas desde la BD del cliente." );
        } else {
            error_log( 'CorreosOficial Backup: ERROR al escribir el archivo de backup.' );
        }
        return $result !== false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detección instalación nueva vs actualización
    // ─────────────────────────────────────────────────────────────────────────

    private static function is_new_installation() {
        global $wpdb;
        $table = $wpdb->prefix . 'correos_oficial_configuration';
        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
        if ( $wpdb->get_var( $query ) !== $table ) {
            return true; // tabla no existe → instalación nueva
        }
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        return ( (int) $count === 0 ); // tabla vacía → instalación nueva
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instalación nueva
    // ─────────────────────────────────────────────────────────────────────────

    private static function execute_install_file( $install_file ) {
        if ( ! file_exists( $install_file ) ) {
            return array(
                'success'      => false,
                'message'      => 'El archivo install.sql no existe',
                'is_new_install' => true,
            );
        }

        // Inyectar el prefijo real antes de parsear
        $content    = self::read_sql_with_prefix( $install_file );
        $statements = self::parse_sql_statements( $content );

        $executed = 0;
        $failed   = 0;
        $errors   = array();
        $log      = array();

        $log[] = '=== Instalación Nueva: ' . gmdate( 'Y-m-d H:i:s' ) . ' ===';
        $log[] = 'Archivo: install.sql';
        $log[] = 'Total de statements: ' . count( $statements );
        $log[] = '';

        global $wpdb;
        foreach ( $statements as $index => $statement ) {
            $statement = trim( $statement );
            if ( empty( $statement ) ) {
                continue;
            }

            $log[] = '[Statement #' . ( $index + 1 ) . ']';
            $log[] = substr( $statement, 0, 150 ) . '...';

            $result = $wpdb->query( $statement );
            if ( $result === false ) {
                $failed++;
                $error = $wpdb->last_error ?: 'Error desconocido';
                $errors[] = array( 'statement' => $statement, 'error' => $error, 'index' => $index );
                $log[] = '✗ ERROR: ' . $error;
            } else {
                $executed++;
                $log[] = '✓ OK (Filas afectadas: ' . $wpdb->rows_affected . ')';
            }
            $log[] = '';
        }

        $log[] = '=== Resumen ===';
        $log[] = "Ejecutados correctamente: {$executed}";
        $log[] = "Fallidos: {$failed}";
        $log[] = 'Fin de instalación: ' . gmdate( 'Y-m-d H:i:s' );

        if ( $failed === 0 ) {
            $log[] = '';
            $log[] = '=== Generando Backup Post-Instalación ===';
            $backup_ok = self::export_tables_structure();
            $log[] = $backup_ok
                ? '✓ Backup generado: ' . basename( self::get_backup_file_path() )
                : '✗ Error al generar backup post-instalación';
        }

        $log_file = plugin_dir_path( __FILE__ ) . 'install_execution.log';
        file_put_contents( $log_file, implode( "\n", $log ) . "\n\n" );

        return array(
            'success'        => ( $failed === 0 ),
            'executed'       => $executed,
            'failed'         => $failed,
            'errors'         => $errors,
            'message'        => $failed === 0
                ? "Instalación completada exitosamente. {$executed} statements ejecutados."
                : "Instalación completada con {$failed} errores. {$executed} statements ejecutados correctamente.",
            'log_file'       => $log_file,
            'is_new_install' => true,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Punto de entrada principal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ejecuta instalación o actualización según corresponda.
     *
     * FLUJO:
     * - Instalación nueva  → ejecuta install.sql directamente.
     * - Actualización       → backup BD real → compara vs install.sql →
     *                         genera upgrade.sql → ejecuta con rollback automático.
     *
     * @return array Resultado con 'success', 'message', 'is_new_install', etc.
     */
    public static function run_upgrade() {
        $install_file = plugin_dir_path( __FILE__ ) . 'install.sql';

        if ( ! file_exists( $install_file ) ) {
            error_log( 'CorreosOficial Upgrade: No se encuentra install.sql' );
            return array(
                'success'      => false,
                'message'      => 'No se encuentra el archivo install.sql',
                'is_new_install' => false,
            );
        }

        if ( self::is_new_installation() ) {
            error_log( 'CorreosOficial Upgrade: Instalación nueva detectada, ejecutando install.sql' );
            $result = self::execute_install_file( $install_file );
            if ( $result['success'] ) {
                error_log( 'CorreosOficial Upgrade: Instalación completada exitosamente' );
            } else {
                error_log( 'CorreosOficial Upgrade: Error en instalación — ' . $result['message'] );
            }
            return $result;
        }

        // ── ACTUALIZACIÓN ────────────────────────────────────────────────────
        error_log( 'CorreosOficial Upgrade: Actualización detectada, comparando estructuras' );

        if ( ! self::export_tables_structure() ) {
            error_log( 'CorreosOficial Upgrade: Error al generar backup' );
            return array(
                'success'      => false,
                'message'      => 'Error al generar backup de la estructura actual',
                'is_new_install' => false,
            );
        }

        $backup_file  = self::get_backup_file_path();
        $differences  = self::compare_sql_files( $backup_file, $install_file );
        $upgrade_sql  = self::generate_upgrade_statements( $differences );
        $upgrade_file = self::get_upgrade_file_path();
        file_put_contents( $upgrade_file, $upgrade_sql );

        error_log( 'CorreosOficial Upgrade: upgrade.sql generado, ejecutando con rollback automático' );

        $result = self::execute_upgrade();

        if ( $result['success'] ) {
            error_log( 'CorreosOficial Upgrade: Actualización completada exitosamente' );
        } else {
            error_log( 'CorreosOficial Upgrade: Error en actualización — ' . $result['message'] );
            if ( ! empty( $result['rollback_executed'] ) ) {
                error_log( 'CorreosOficial Upgrade: Rollback automático ejecutado — '
                    . $result['rollback_result']['reverted'] . ' cambios revertidos' );
            }
            if ( file_exists( $upgrade_file ) ) {
                @unlink( $upgrade_file );
                error_log( 'CorreosOficial Upgrade: upgrade.sql temporal eliminado (upgrade falló)' );
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Comparación de esquemas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compara la estructura actual de la BD (backup) con la objetivo (install.sql).
     *
     * @param  string $current_sql Ruta del backup generado por SHOW CREATE TABLE
     * @param  string $target_sql  Ruta del install.sql
     * @return array               Diferencias encontradas
     */
    private static function compare_sql_files( $current_sql, $target_sql ) {
        $current_structure = self::parse_sql_structure( $current_sql );
        // Para install.sql inyectamos el prefijo antes de parsear
        $target_structure  = self::parse_sql_structure( $target_sql, true );

        $differences = array(
            'missing_tables' => array(),
            'table_changes'  => array(),
            'missing_data'   => array(),
        );

        foreach ( self::$tables as $table ) {
            $current_table = $current_structure['tables'][ $table ] ?? null;
            $target_table  = $target_structure['tables'][ $table ] ?? null;

            if ( ! $current_table && $target_table ) {
                $differences['missing_tables'][ $table ] = $target_table;
            } elseif ( $current_table && $target_table ) {
                $table_diff = self::compare_table_structure( $table, $current_table, $target_table );
                if ( ! empty( $table_diff ) ) {
                    $differences['table_changes'][ $table ] = $table_diff;
                }
            }
        }

        // Comparar datos de INSERT para tablas de configuración/catálogo
        $data_tables = array(
            'correos_oficial_configuration',
            'correos_oficial_products',
            'correos_oficial_codes_actives',
            'correos_oficial_customs_description',
        );

        foreach ( $data_tables as $table ) {
            if ( isset( $target_structure['inserts'][ $table ] ) ) {
                $table_exists    = isset( $current_structure['tables'][ $table ] );
                $has_new_columns = isset( $differences['table_changes'][ $table ]['missing_columns'] );

                if ( $table_exists && $has_new_columns ) {
                    $differences['missing_data'][ $table ] = array(
                        'inserts'     => $target_structure['inserts'][ $table ],
                        'needs_updates' => true,
                        'new_columns' => $differences['table_changes'][ $table ]['missing_columns'],
                    );
                } else {
                    $differences['missing_data'][ $table ] = array(
                        'inserts'     => $target_structure['inserts'][ $table ],
                        'needs_updates' => false,
                    );
                }
            }
        }

        return $differences;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parser de SQL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parsea un archivo SQL y extrae su estructura (tablas + inserts).
     *
     * @param  string $sql_file           Ruta del archivo
     * @param  bool   $inject_prefix      Si true, reemplaza {PREFIX} con $wpdb->prefix antes de parsear
     * @return array                      Estructura parseada
     */
    private static function parse_sql_structure( $sql_file, $inject_prefix = false ) {
        global $wpdb;

        $content = $inject_prefix
            ? self::read_sql_with_prefix( $sql_file )
            : file_get_contents( $sql_file );

        $structure = array(
            'tables'  => array(),
            'inserts' => array(),
        );

        // Extraer CREATE TABLE — el prefijo real de la BD está en $wpdb->prefix
        preg_match_all( '/CREATE TABLE `?(\w+)`?\s*\((.*?)\)\s*ENGINE/si', $content, $tables, PREG_SET_ORDER );

        foreach ( $tables as $match ) {
            // Eliminar el prefijo real para obtener el nombre corto de la tabla
            $table_name = str_replace( $wpdb->prefix, '', $match[1] );
            $table_def  = $match[2];

            $structure['tables'][ $table_name ] = array(
                'name'       => $table_name,
                'definition' => $table_def,
                'columns'    => self::parse_columns( $table_def ),
                'keys'       => self::parse_keys( $table_def ),
            );
        }

        // Extraer INSERT statements
        preg_match_all( '/INSERT INTO `?(\w+)`?.*?VALUES\s*(.*?);/si', $content, $inserts, PREG_SET_ORDER );

        foreach ( $inserts as $match ) {
            $table_name = str_replace( $wpdb->prefix, '', $match[1] );
            if ( ! isset( $structure['inserts'][ $table_name ] ) ) {
                $structure['inserts'][ $table_name ] = array();
            }
            // Usar INSERT IGNORE para evitar duplicados en actualizaciones
            $stmt = str_replace( 'INSERT INTO', 'INSERT IGNORE INTO', $match[0] );
            $structure['inserts'][ $table_name ][] = $stmt;
        }

        return $structure;
    }

    private static function parse_columns( $table_def ) {
        $columns = array();
        foreach ( explode( "\n", $table_def ) as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT)/i', $line ) ) {
                continue;
            }
            if ( preg_match( '/^`?(\w+)`?\s+(.+?)(?:,\s*)?$/i', $line, $m ) ) {
                $col_name = $m[1];
                $columns[ $col_name ] = array(
                    'name'       => $col_name,
                    'definition' => rtrim( $m[2], ',' ),
                );
            }
        }
        return $columns;
    }

    private static function parse_keys( $table_def ) {
        $keys = array();
        foreach ( explode( "\n", $table_def ) as $line ) {
            $line = trim( $line );
            if ( preg_match( '/(PRIMARY KEY|UNIQUE KEY|KEY)\s*`?(\w*)`?\s*\((.*?)\)/i', $line, $m ) ) {
                $key_name = $m[2] ?: 'PRIMARY';
                $keys[ $key_name ] = array(
                    'type'       => $m[1],
                    'name'       => $key_name,
                    'columns'    => $m[3],
                    'definition' => trim( $line, ',' ),
                );
            }
        }
        return $keys;
    }

    private static function compare_table_structure( $table_name, $current, $target ) {
        $diff = array();

        $missing_columns  = array();
        $modified_columns = array();

        foreach ( $target['columns'] as $col_name => $col_info ) {
            if ( ! isset( $current['columns'][ $col_name ] ) ) {
                $missing_columns[] = $col_info;
            } else {
                $current_def = self::normalize_column_def( $current['columns'][ $col_name ]['definition'] );
                $target_def  = self::normalize_column_def( $col_info['definition'] );
                if ( $current_def !== $target_def ) {
                    $modified_columns[] = array(
                        'name'        => $col_name,
                        'current'     => $current_def,
                        'target'      => $target_def,
                        'target_full' => $col_info['definition'],
                    );
                }
            }
        }

        if ( ! empty( $missing_columns ) ) {
            $diff['missing_columns'] = $missing_columns;
        }
        if ( ! empty( $modified_columns ) ) {
            $diff['modified_columns'] = $modified_columns;
        }

        $missing_keys = array();
        foreach ( $target['keys'] as $key_name => $key_info ) {
            if ( ! isset( $current['keys'][ $key_name ] ) ) {
                $missing_keys[] = $key_info;
            }
        }
        if ( ! empty( $missing_keys ) ) {
            $diff['missing_keys'] = $missing_keys;
        }

        return $diff;
    }

    private static function normalize_column_def( $definition ) {
        $def = strtolower( trim( $definition ) );
        $def = preg_replace( '/\bint\(\d+\)/', 'int', $def );
        $def = preg_replace( '/\bcharacter set\s+\S+/', '', $def );
        $def = preg_replace( '/\bcollate\s+\S+/', '', $def );
        $def = preg_replace( '/\s+/', ' ', $def );
        $def = str_replace( 'default null', '', $def );
        return trim( $def );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generación de SQL de upgrade
    // ─────────────────────────────────────────────────────────────────────────

    private static function generate_upgrade_statements( $differences ) {
        global $wpdb;

        $sql  = "-- Script de actualización generado automáticamente\n";
        $sql .= '-- Fecha: ' . gmdate( 'Y-m-d H:i:s' ) . "\n\n";

        if ( ! empty( $differences['missing_tables'] ) ) {
            $sql .= "-- =============================================\n";
            $sql .= "-- TABLAS FALTANTES\n";
            $sql .= "-- =============================================\n\n";
            foreach ( $differences['missing_tables'] as $table_name => $table_info ) {
                $full = $wpdb->prefix . $table_name;
                $sql .= "-- Crear tabla: {$table_name}\n";
                $sql .= "CREATE TABLE IF NOT EXISTS `{$full}` (\n{$table_info['definition']}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8;\n\n";
            }
        }

        if ( ! empty( $differences['table_changes'] ) ) {
            $sql .= "-- =============================================\n";
            $sql .= "-- CAMBIOS EN TABLAS EXISTENTES\n";
            $sql .= "-- =============================================\n\n";
            foreach ( $differences['table_changes'] as $table_name => $changes ) {
                $full = $wpdb->prefix . $table_name;
                $sql .= "-- Tabla: {$table_name}\n";

                foreach ( $changes['missing_columns'] ?? array() as $column ) {
                    $sql .= "ALTER TABLE `{$full}` ADD COLUMN `{$column['name']}` {$column['definition']};\n";
                }
                foreach ( $changes['modified_columns'] ?? array() as $column ) {
                    $sql .= "ALTER TABLE `{$full}` MODIFY COLUMN `{$column['name']}` {$column['target_full']};\n";
                }
                foreach ( $changes['missing_keys'] ?? array() as $key ) {
                    if ( stripos( $key['type'], 'PRIMARY' ) !== false ) {
                        $sql .= "ALTER TABLE `{$full}` ADD PRIMARY KEY ({$key['columns']});\n";
                    } else {
                        $sql .= "ALTER TABLE `{$full}` ADD {$key['definition']};\n";
                    }
                }
                $sql .= "\n";
            }
        }

        if ( ! empty( $differences['missing_data'] ) ) {
            $sql .= "-- =============================================\n";
            $sql .= "-- DATOS DE CONFIGURACIÓN/CATÁLOGO\n";
            $sql .= "-- =============================================\n";

            foreach ( $differences['missing_data'] as $table_name => $data_info ) {
                $full           = $wpdb->prefix . $table_name;
                $is_new_format  = isset( $data_info['inserts'], $data_info['needs_updates'] );

                if ( $is_new_format && $data_info['needs_updates'] ) {
                    $sql .= "-- Tabla: {$table_name}\n-- UPDATEs para poblar columnas nuevas en filas existentes\n\n";
                    foreach ( self::generate_updates_for_new_columns( $full, $data_info['inserts'], $data_info['new_columns'] ) as $upd ) {
                        $sql .= $upd . "\n";
                    }
                    $sql .= "\n-- INSERTs para filas nuevas\n";
                    foreach ( $data_info['inserts'] as $stmt ) {
                        $sql .= $stmt . "\n";
                    }
                    $sql .= "\n";
                } else {
                    $sql .= "-- Tabla: {$table_name}\n";
                    $inserts = $is_new_format ? $data_info['inserts'] : $data_info;
                    foreach ( $inserts as $stmt ) {
                        $sql .= $stmt . "\n";
                    }
                    $sql .= "\n";
                }
            }
        }

        return $sql;
    }

    private static function generate_updates_for_new_columns( $table_name, $insert_statements, $new_columns ) {
        $update_statements = array();
        $new_column_names  = array_column( $new_columns, 'name' );

        foreach ( $insert_statements as $insert_stmt ) {
            if ( ! preg_match( '/INSERT\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s*\(([^)]+)\)\s*VALUES\s*(.+);/si', $insert_stmt, $matches ) ) {
                continue;
            }

            $columns     = array_map( 'trim', explode( ',', $matches[1] ) );
            $new_col_pos = array();
            foreach ( $columns as $index => $col_name ) {
                $col_name = trim( $col_name, '` ' );
                if ( in_array( $col_name, $new_column_names, true ) ) {
                    $new_col_pos[ $col_name ] = $index;
                }
            }
            if ( empty( $new_col_pos ) ) {
                continue;
            }

            $pk_column = trim( $columns[0], '` ' );
            foreach ( self::parse_values_rows( $matches[2] ) as $row ) {
                $values = self::parse_row_values( $row );
                if ( empty( $values ) || ! isset( $values[0] ) ) {
                    continue;
                }
                $pk_value  = $values[0];
                $set_parts = array();
                foreach ( $new_col_pos as $col_name => $col_index ) {
                    if ( isset( $values[ $col_index ] ) ) {
                        $value = $values[ $col_index ];
                        $set_parts[] = strtoupper( trim( $value ) ) === 'NULL'
                            ? "`{$col_name}` = NULL"
                            : "`{$col_name}` = {$value}";
                    }
                }
                if ( ! empty( $set_parts ) ) {
                    $update_statements[] = "UPDATE `{$table_name}` SET " . implode( ', ', $set_parts ) . " WHERE `{$pk_column}` = {$pk_value};";
                }
            }
        }

        return $update_statements;
    }

    private static function parse_values_rows( $values_str ) {
        $rows        = array();
        $current_row = '';
        $depth       = 0;
        $in_string   = false;
        $string_char = '';
        $length      = strlen( $values_str );

        for ( $i = 0; $i < $length; $i++ ) {
            $char      = $values_str[ $i ];
            $prev_char = $i > 0 ? $values_str[ $i - 1 ] : '';

            if ( ( $char === '"' || $char === "'" ) && $prev_char !== '\\' ) {
                if ( ! $in_string ) {
                    $in_string   = true;
                    $string_char = $char;
                } elseif ( $char === $string_char ) {
                    $in_string = false;
                }
            }

            if ( ! $in_string ) {
                if ( $char === '(' ) {
                    $depth++;
                    if ( $depth === 1 ) {
                        $current_row = '';
                        continue;
                    }
                } elseif ( $char === ')' ) {
                    $depth--;
                    if ( $depth === 0 ) {
                        $rows[]      = $current_row;
                        $current_row = '';
                        continue;
                    }
                }
            }

            if ( $depth > 0 ) {
                $current_row .= $char;
            }
        }

        return $rows;
    }

    private static function parse_row_values( $row_str ) {
        $values        = array();
        $current_value = '';
        $in_string     = false;
        $string_char   = '';
        $length        = strlen( $row_str );

        for ( $i = 0; $i < $length; $i++ ) {
            $char      = $row_str[ $i ];
            $prev_char = $i > 0 ? $row_str[ $i - 1 ] : '';

            if ( ( $char === '"' || $char === "'" ) && $prev_char !== '\\' ) {
                if ( ! $in_string ) {
                    $in_string     = true;
                    $string_char   = $char;
                    $current_value .= $char;
                } elseif ( $char === $string_char ) {
                    $in_string     = false;
                    $current_value .= $char;
                }
                continue;
            }

            if ( $char === ',' && ! $in_string ) {
                $values[]      = trim( $current_value );
                $current_value = '';
            } else {
                $current_value .= $char;
            }
        }

        if ( $current_value !== '' ) {
            $values[] = trim( $current_value );
        }

        return $values;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parser de statements SQL
    // ─────────────────────────────────────────────────────────────────────────

    private static function parse_sql_statements( $sql_content ) {
        // Eliminar comentarios /* ... */
        $sql_content = preg_replace( '/\/\*.*?\*\//s', '', $sql_content );

        // Eliminar comentarios -- ... sin estar dentro de strings
        $lines = array();
        foreach ( explode( "\n", $sql_content ) as $line ) {
            $pos = strpos( $line, '--' );
            if ( $pos !== false ) {
                $before         = substr( $line, 0, $pos );
                $single_quotes  = substr_count( $before, "'" ) - substr_count( $before, "\\'" );
                $double_quotes  = substr_count( $before, '"' ) - substr_count( $before, '\\"' );
                if ( $single_quotes % 2 === 0 && $double_quotes % 2 === 0 ) {
                    $line = substr( $line, 0, $pos );
                }
            }
            $lines[] = $line;
        }
        $sql_content = implode( "\n", $lines );

        // Dividir por ; respetando strings
        $statements        = array();
        $current_statement = '';
        $in_string         = false;
        $string_char       = '';
        $length            = strlen( $sql_content );

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $sql_content[ $i ];
            if ( ( $char === '"' || $char === "'" ) && ( $i === 0 || $sql_content[ $i - 1 ] !== '\\' ) ) {
                if ( ! $in_string ) {
                    $in_string   = true;
                    $string_char = $char;
                } elseif ( $char === $string_char ) {
                    $in_string = false;
                }
            }
            if ( $char === ';' && ! $in_string ) {
                $statements[]      = trim( $current_statement );
                $current_statement = '';
            } else {
                $current_statement .= $char;
            }
        }

        if ( ! empty( trim( $current_statement ) ) ) {
            $statements[] = trim( $current_statement );
        }

        return array_filter( $statements );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ejecución de upgrade con rollback automático
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ejecuta upgrade.sql con rollback automático statement a statement.
     * Si cualquier statement falla, revierte todos los cambios previos de esa sesión.
     *
     * @return array Resultado de la ejecución
     */
    public static function execute_upgrade() {
        global $wpdb;

        $upgrade_file = self::get_upgrade_file_path();
        $backup_file  = self::get_backup_file_path();

        if ( ! file_exists( $backup_file ) ) {
            return array( 'success' => false, 'message' => 'No existe archivo de backup. Ejecute primero export_tables_structure()' );
        }
        if ( ! file_exists( $upgrade_file ) ) {
            return array( 'success' => false, 'message' => 'No existe upgrade.sql. Ejecute primero run_upgrade()' );
        }

        $backup_structure = self::parse_sql_structure( $backup_file );
        $statements       = self::parse_sql_statements( file_get_contents( $upgrade_file ) );

        $executed       = 0;
        $failed         = 0;
        $errors         = array();
        $rollback_stack = array();
        $execution_log  = array();

        $execution_log[] = '=== Inicio de ejecución con rollback automático: ' . gmdate( 'Y-m-d H:i:s' ) . ' ===';
        $execution_log[] = 'Total de statements: ' . count( $statements );
        $execution_log[] = '';

        foreach ( $statements as $index => $statement ) {
            $statement = trim( $statement );
            if ( empty( $statement ) ) {
                continue;
            }

            $execution_log[] = '[Statement #' . ( $index + 1 ) . ']';
            $execution_log[] = substr( $statement, 0, 150 ) . '...';

            $rollback_statement = self::generate_rollback_statement( $statement, $backup_structure );
            if ( $rollback_statement ) {
                $execution_log[] = '  Rollback preparado: ' . substr( $rollback_statement, 0, 100 ) . '...';
            }

            $result = $wpdb->query( $statement );

            if ( $result === false ) {
                $failed++;
                $error   = $wpdb->last_error ?: 'Error desconocido';
                $errors[] = array( 'statement' => $statement, 'error' => $error, 'index' => $index );
                $execution_log[] = "✗ ERROR: {$error}";
                $execution_log[] = '';
                $execution_log[] = '⚠️  INICIANDO ROLLBACK AUTOMÁTICO...';

                $rollback_result = self::execute_rollback_stack( $rollback_stack, $execution_log );

                $execution_log[] = '=== Resumen ===';
                $execution_log[] = "Ejecutados correctamente: {$executed}";
                $execution_log[] = 'Fallido en statement #' . ( $index + 1 );
                $execution_log[] = "Rollback ejecutado: {$rollback_result['reverted']} cambios revertidos";
                $execution_log[] = 'Fin: ' . gmdate( 'Y-m-d H:i:s' );

                file_put_contents( self::get_execution_log_path(), implode( "\n", $execution_log ) . "\n\n" );

                return array(
                    'success'          => false,
                    'executed'         => $executed,
                    'failed'           => 1,
                    'errors'           => $errors,
                    'rollback_executed' => true,
                    'rollback_result'  => $rollback_result,
                    'message'          => "Error en statement #" . ( $index + 1 ) . ". Rollback automático ejecutado. {$rollback_result['reverted']} cambios revertidos.",
                    'log_file'         => self::get_execution_log_path(),
                );
            }

            $executed++;
            if ( $rollback_statement ) {
                $rollback_stack[] = array(
                    'statement' => $rollback_statement,
                    'original'  => $statement,
                    'index'     => $index + 1,
                );
            }
            $execution_log[] = '✓ OK (Filas afectadas: ' . $wpdb->rows_affected . ')';
            $execution_log[] = '';
        }

        $execution_log[] = '=== Resumen ===';
        $execution_log[] = '✓ Todos los statements ejecutados correctamente';
        $execution_log[] = "Total ejecutados: {$executed}";
        $execution_log[] = 'Fin: ' . gmdate( 'Y-m-d H:i:s' );

        file_put_contents( self::get_execution_log_path(), implode( "\n", $execution_log ) . "\n\n" );

        return array(
            'success'          => true,
            'executed'         => $executed,
            'failed'           => 0,
            'errors'           => array(),
            'rollback_executed' => false,
            'message'          => "Upgrade ejecutado correctamente. {$executed} statements ejecutados.",
            'log_file'         => self::get_execution_log_path(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rollback manual
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Revierte manualmente el último upgrade exitoso.
     * Solo revierte cambios estructurales (columnas, índices, tablas nuevas).
     * NO elimina datos de las tablas.
     *
     * @return array Resultado del rollback
     */
    public static function rollback() {
        global $wpdb;

        $upgrade_file = self::get_upgrade_file_path();
        $backup_file  = self::get_backup_file_path();

        if ( ! file_exists( $upgrade_file ) ) {
            return array( 'success' => false, 'message' => 'No existe upgrade.sql. No se ha ejecutado ningún upgrade.' );
        }
        if ( ! file_exists( $backup_file ) ) {
            return array( 'success' => false, 'message' => 'No existe archivo de backup. No se puede determinar el estado anterior.' );
        }

        $log   = array();
        $log[] = '=== ROLLBACK MANUAL ===';
        $log[] = 'Fecha: ' . gmdate( 'Y-m-d H:i:s' );
        $log[] = '⚠️  Solo se revierten cambios estructurales, NO se eliminan datos';
        $log[] = '';

        $backup_structure    = self::parse_sql_structure( $backup_file );
        $statements          = self::parse_sql_statements( file_get_contents( $upgrade_file ) );
        $rollback_statements = array();

        foreach ( $statements as $index => $statement ) {
            $statement = trim( $statement );
            if ( empty( $statement ) ) {
                continue;
            }
            $rollback_statement = self::generate_rollback_statement( $statement, $backup_structure );
            if ( $rollback_statement ) {
                $rollback_statements[] = array(
                    'original' => $statement,
                    'rollback' => $rollback_statement,
                    'index'    => $index + 1,
                );
            }
        }

        if ( empty( $rollback_statements ) ) {
            $rollback_log = plugin_dir_path( __FILE__ ) . 'rollback_execution.log';
            $log[] = 'No hay statements que revertir.';
            file_put_contents( $rollback_log, implode( "\n", $log ) . "\n" );
            return array( 'success' => true, 'message' => 'No hay cambios que revertir.', 'reverted' => 0, 'log_file' => $rollback_log );
        }

        $log[]   = 'Total de statements a revertir: ' . count( $rollback_statements );
        $log[]   = '';

        $rollback_statements = array_reverse( $rollback_statements );

        $reverted = 0;
        $failed   = 0;
        $errors   = array();

        foreach ( $rollback_statements as $item ) {
            $log[] = "[Revirtiendo statement #{$item['index']}]";
            $log[] = 'Original: ' . substr( $item['original'], 0, 100 ) . '...';
            $log[] = 'Rollback: ' . substr( $item['rollback'], 0, 100 ) . '...';

            $result = $wpdb->query( $item['rollback'] );
            if ( $result === false ) {
                $failed++;
                $error   = $wpdb->last_error ?: 'Error desconocido';
                $errors[] = array( 'statement' => $item['rollback'], 'error' => $error );
                $log[] = "✗ ERROR: {$error}";
            } else {
                $reverted++;
                $log[] = '✓ OK (Filas afectadas: ' . $wpdb->rows_affected . ')';
            }
            $log[] = '';
        }

        $log[] = '=== Resumen ===';
        $log[] = "Revertidos correctamente: {$reverted}";
        $log[] = "Fallidos: {$failed}";
        $log[] = 'Fin de rollback: ' . gmdate( 'Y-m-d H:i:s' );

        $rollback_log = plugin_dir_path( __FILE__ ) . 'rollback_execution.log';
        file_put_contents( $rollback_log, implode( "\n", $log ) . "\n" );

        if ( $failed === 0 && file_exists( $upgrade_file ) ) {
            @unlink( $upgrade_file );
        }

        return array(
            'success'  => ( $failed === 0 ),
            'message'  => $failed === 0
                ? "Rollback completado exitosamente. {$reverted} cambios revertidos sin pérdida de datos."
                : "Rollback completado con {$failed} errores. {$reverted} cambios revertidos correctamente.",
            'reverted' => $reverted,
            'failed'   => $failed,
            'errors'   => $errors,
            'log_file' => $rollback_log,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generación de statements de rollback
    // ─────────────────────────────────────────────────────────────────────────

    private static function generate_rollback_statement( $statement, $backup_structure ) {
        global $wpdb;
        $statement = trim( $statement );

        // ALTER TABLE ... ADD COLUMN → DROP COLUMN
        if ( preg_match( '/ALTER\s+TABLE\s+`?([^`\s]+)`?\s+ADD\s+COLUMN\s+`?([^`\s]+)`?/i', $statement, $m ) ) {
            return "ALTER TABLE `{$m[1]}` DROP COLUMN `{$m[2]}`";
        }

        // ALTER TABLE ... MODIFY COLUMN → restaurar definición del backup
        if ( preg_match( '/ALTER\s+TABLE\s+`?([^`\s]+)`?\s+MODIFY\s+COLUMN\s+`?([^`\s]+)`?/i', $statement, $m ) ) {
            $table  = str_replace( $wpdb->prefix, '', $m[1] );
            $column = $m[2];
            if ( isset( $backup_structure['tables'][ $table ]['columns'][ $column ] ) ) {
                $original_def = $backup_structure['tables'][ $table ]['columns'][ $column ]['definition'];
                return "ALTER TABLE `{$m[1]}` MODIFY COLUMN `{$column}` {$original_def}";
            }
            return false;
        }

        // ALTER TABLE ... ADD PRIMARY KEY → DROP PRIMARY KEY
        if ( preg_match( '/ALTER\s+TABLE\s+`?([^`\s]+)`?\s+ADD\s+PRIMARY\s+KEY/i', $statement, $m ) ) {
            return "ALTER TABLE `{$m[1]}` DROP PRIMARY KEY";
        }

        // ALTER TABLE ... ADD KEY/INDEX → DROP KEY
        if ( preg_match( '/ALTER\s+TABLE\s+`?([^`\s]+)`?\s+ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([^`\s]+)`?/i', $statement, $m ) ) {
            return "ALTER TABLE `{$m[1]}` DROP KEY `{$m[2]}`";
        }

        // CREATE TABLE → DROP TABLE IF EXISTS (solo si la tabla no existía antes)
        if ( preg_match( '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([^`\s]+)`?/i', $statement, $m ) ) {
            $table_short = str_replace( $wpdb->prefix, '', $m[1] );
            if ( ! isset( $backup_structure['tables'][ $table_short ] ) ) {
                return "DROP TABLE IF EXISTS `{$m[1]}`";
            }
            return false;
        }

        // INSERT IGNORE no necesita rollback
        if ( preg_match( '/INSERT\s+IGNORE/i', $statement ) ) {
            return false;
        }

        return false;
    }

    private static function execute_rollback_stack( array $rollback_stack, array &$execution_log ) {
        global $wpdb;

        $reverted        = 0;
        $rollback_errors = array();

        foreach ( array_reverse( $rollback_stack ) as $item ) {
            $statement = $item['statement'];
            $execution_log[] = "  Revirtiendo statement #{$item['index']}...";
            $execution_log[] = '    ' . substr( $statement, 0, 100 ) . '...';

            $result = $wpdb->query( $statement );
            if ( $result === false ) {
                $error           = $wpdb->last_error ?: 'Error desconocido';
                $rollback_errors[] = array( 'statement' => $statement, 'error' => $error );
                $execution_log[] = "    ✗ Error en rollback: {$error}";
            } else {
                $reverted++;
                $execution_log[] = '    ✓ Revertido correctamente';
            }
        }

        return array(
            'success'  => empty( $rollback_errors ),
            'reverted' => $reverted,
            'errors'   => $rollback_errors,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades de log
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtiene el contenido del log de ejecución.
     *
     * @param  string       $log_type 'upgrade' o 'rollback'
     * @return string|false
     */
    public static function get_execution_log( $log_type = 'upgrade' ) {
        $log_file = $log_type === 'rollback'
            ? plugin_dir_path( __FILE__ ) . 'rollback_execution.log'
            : self::get_execution_log_path();
        return file_exists( $log_file ) ? file_get_contents( $log_file ) : false;
    }

    /**
     * Ejecuta un archivo SQL línea por línea (sin rollback).
     * Para uso interno o casos especiales. En actualizaciones normales usa run_upgrade().
     *
     * @param  string $sql_file      Ruta del archivo SQL
     * @param  bool   $log_execution Si debe registrar la ejecución
     * @return array
     */
    public static function execute_sql_file( $sql_file, $log_execution = true ) {
        global $wpdb;

        if ( ! file_exists( $sql_file ) ) {
            return array( 'success' => false, 'executed' => 0, 'failed' => 0, 'errors' => array( 'El archivo SQL no existe: ' . $sql_file ) );
        }

        $statements    = self::parse_sql_statements( file_get_contents( $sql_file ) );
        $executed      = 0;
        $failed        = 0;
        $errors        = array();
        $execution_log = array();

        if ( $log_execution ) {
            $execution_log[] = '=== Inicio de ejecución: ' . gmdate( 'Y-m-d H:i:s' ) . ' ===';
            $execution_log[] = 'Archivo: ' . basename( $sql_file );
            $execution_log[] = 'Total de statements: ' . count( $statements );
            $execution_log[] = '';
        }

        foreach ( $statements as $index => $statement ) {
            $statement = trim( $statement );
            if ( empty( $statement ) ) {
                continue;
            }

            $result = $wpdb->query( $statement );
            if ( $result === false ) {
                $failed++;
                $error   = $wpdb->last_error ?: 'Error desconocido';
                $errors[] = array( 'statement' => $statement, 'error' => $error, 'index' => $index );
                if ( $log_execution ) {
                    $execution_log[] = '✗ ERROR: ' . $error;
                }
            } else {
                $executed++;
                if ( $log_execution ) {
                    $execution_log[] = '✓ OK (Filas afectadas: ' . $wpdb->rows_affected . ')';
                }
            }
            if ( $log_execution ) {
                $execution_log[] = '';
            }
        }

        if ( $log_execution ) {
            $execution_log[] = '=== Resumen ===';
            $execution_log[] = "Ejecutados correctamente: {$executed}";
            $execution_log[] = "Fallidos: {$failed}";
            $execution_log[] = 'Fin: ' . gmdate( 'Y-m-d H:i:s' );
            file_put_contents( self::get_execution_log_path(), implode( "\n", $execution_log ) . "\n\n" );
        }

        return array(
            'success'  => ( $failed === 0 ),
            'executed' => $executed,
            'failed'   => $failed,
            'errors'   => $errors,
        );
    }
}
