<?php
namespace CorreosOficial\Controllers\Admin;

use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialSGA;
use CorreosOficial\Models\CorreosOficialConfig;

class AdminCorreosOficialSGAProcessController {

    public function __construct() {

        $action = isset($_REQUEST['action']) ? CorreosOficialUtils::sanitize($_REQUEST['action']) : '';

        switch($action) {
            case 'saveSGAConfig':
                $this->saveSGAConfig();
                break;
            case 'getOrderLogs':
                $this->getOrderLogs();
                break;
            default: return false;
        }
    }

    /**
     * Guarda la configuración de logística en base de datos.
     *
     * Valida la conexión con SGA antes de guardar usando cualquier cuenta Correos
     * disponible, SIN necesitar un remitente
     * - true  → credenciales SGA correctas, se guarda.
     * - false → credenciales SGA incorrectas, se devuelve error.
     * - null  → no hay cuenta Correos aún, se guarda igualmente (validación aplazada).
     */
    public function saveSGAConfig() {
        try {
            // Sanear datos recibidos
            $form_data = isset($_POST) ? CorreosOficialUtils::sanitize($_POST) : [];

            // Validar conexión SGA solo si el módulo está siendo activado.
            if (!empty($form_data['ActivateSGA'])) {
                $payload = [
                    'company'     => 'Correos',
                    'ownerid'     => $form_data['SGAOwner']    ?? '',
                    'clientid'    => $form_data['SGACustomer'] ?? '',
                    'warehouse'   => $form_data['SGAStore']    ?? '',
                    'product_sku' => 'fakeRef12345',
                    'id_order'    => 0,
                ];

                $validation = CorreosOficialSGA::validateConnection($payload);

                if ($validation === false) {
                    wp_send_json_error([
                        'message' => __(
                            'Error connecting to SGA. Please check the Owner, Customer and Store codes.',
                            'correosoficial'
                        )
                    ]);
                    return;
                }
                // $validation === null → sin cuenta Correos, se guarda sin validar.
            }

            foreach ($form_data as $name => $value) {
                // Guardar cada campo
                $saved = CorreosOficialConfig::save($name, $value);
                if (!$saved) {
                    wp_send_json_error([
                        'message' => "Error al guardar el campo {$name}",
                    ]);
                    return; // Detener procesamiento si falla
                }
            }

            // Programar o eliminar tareas cron según si el checkbox está activo
			$this->activateSGACronTasks();

            // Mandamos a CTRLVERS
            (new Analitica())->configurationCall(false);

            // Si todo se guardó correctamente
            wp_send_json_success([
                'success' => true,
                'message' => 'Datos procesados correctamente',
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Error al procesar los datos: ' . $e->getMessage(),
            ]);
        }
    }

    public function getOrderLogs() {
        $id_order = CorreosOficialNormalization::normalizeData('id_order');
        CorreosOficialSGA::getOrderLogs($id_order);
    }

    public function activateSGACronTasks() {

        // Comprueba checks de ajustes
        $cron_stock_active = ( new CorreosOficialConfig('SGAUpdateStock') )->get_value();
        $cron_status_active = ( new CorreosOficialConfig('SGAOrderStatusTracking') )->get_value();

        // Comprueba si hay tareas programadas
        $timestamp_stock_cron  = wp_next_scheduled('correosoficial_sga_stock_cron_event');
        $timestamp_status_cron = wp_next_scheduled('correosoficial_sga_status_cron_event');

        // Intervalo de la tarea de estado de pedido
        if ($cron_stock_active == 'on' && !wp_next_scheduled('correosoficial_sga_stock_cron_event')) {
            wp_schedule_event(
                time(),
                'twicedaily',
                'correosoficial_sga_stock_cron_event'
            );
        } else {
            if ($cron_stock_active != 'on' && $timestamp_stock_cron) {
                wp_unschedule_event($timestamp_stock_cron, 'correosoficial_sga_stock_cron_event');
            }
        }

        if ($cron_status_active == 'on' && !wp_next_scheduled('correosoficial_sga_status_cron_event')) {
            wp_schedule_event(
                time(),
                'hourly',
                'correosoficial_sga_status_cron_event'
            );
        } else {
             if ($cron_status_active != 'on' && $timestamp_status_cron) {
                wp_unschedule_event($timestamp_status_cron, 'correosoficial_sga_status_cron_event');
            }
        }

	}
}
