<?php

namespace CorreosOficial\Models;

use Exception;
use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialSenderDataStore extends WC_Data_Store_WP {

	protected $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'correos_oficial_senders';
	}

	// public function create(&$sender) {
	//     global $wpdb;
	//     $data = [
	//         'name'        => $sender->get_name(),
	//         'address'     => $sender->get_address(),
	//         'cp'          => $sender->get_cp(),
	//         'nif_cif'     => $sender->get_nif_cif(),
	//         'city'        => $sender->get_city(),
	//         'contact'     => $sender->get_contact(),
	//         'phone'       => $sender->get_phone(),
	//         'from_time'   => $sender->get_from_time(),
	//         'to_time'     => $sender->get_to_time(),
	//         'iso_code_pais' => $sender->get_iso_code_pais(),
	//         'email'       => $sender->get_email(),
	//         'is_default'  => $sender->get_is_default(),
	//         'created_at'  => current_time('mysql'),
	//     ];
	//     $wpdb->insert($this->table_name, $data);
	//     $sender->set_id($wpdb->insert_id);
	// }

	public function read( &$sender ) {
		global $wpdb;
		$data = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . esc_sql($this->table_name) . ' WHERE id = %d', $sender->get_id()), ARRAY_A);
		
		if ($data['correos_code']) {
			$data['correos_code'] = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . esc_sql($wpdb->prefix) . 'correos_oficial_codes WHERE id = %d', $data['correos_code']), ARRAY_A);
		}
		if ($data['cex_code']) {
			$data['cex_code'] = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . esc_sql($wpdb->prefix) . 'correos_oficial_codes WHERE id = %d', $data['cex_code']), ARRAY_A);
		}
		
		if ($data) {
			$sender->set_props($data);
			$sender->set_object_read(true);
		}
	}

	public function get_default_sender( &$sender ) {
		global $wpdb;
	
		// Obtener el ID del sender por defecto
		$sender_id = $wpdb->get_var('SELECT id FROM ' . esc_sql($this->table_name) . ' WHERE sender_default = 1 LIMIT 1');
	
		if (!$sender_id) {
			return null; // No hay sender por defecto
		}
	
		// Obtener los datos del sender por defecto
		$data = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . esc_sql($this->table_name) . ' WHERE id = %d', $sender_id), ARRAY_A);
	
		if ($data) {
			if ($data['correos_code']) {
				$data['correos_code'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_codes WHERE id = %d", $data['correos_code']), ARRAY_A);
			}
			if ($data['cex_code']) {
				$data['cex_code'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_codes WHERE id = %d", $data['cex_code']), ARRAY_A);
			}
		}

		if ($data) {
			$sender->set_props($data);
			$sender->set_object_read(true);
		}
	}

	public function get_first_sender_by_company(&$sender, $company) {
		global $wpdb;

		$company = strtolower(trim($company));

		if ($company == 'correos') {
			$where = 'correos_code > 0';
		} elseif ($company == 'cex') {
			$where = 'cex_code > 0';
		} else {
			return null; // Empresa no válida
		}

		$sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY id ASC LIMIT 1";

		$data = $wpdb->get_row($sql, ARRAY_A);

		if (!$data) {
			return null; // No se encontró remitente
		}

		// Cargar detalles de códigos si existen
		if ($data) {
			if ($company == 'correos' && $data['correos_code']) {
				$data['correos_code'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_codes WHERE id = %d", $data['correos_code']), ARRAY_A);
			}
			if ($company == 'cex' && $data['cex_code']) {
				$data['cex_code'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_codes WHERE id = %d", $data['cex_code']), ARRAY_A);
			}
		}

		if ($data) {
			$sender->set_props($data);
			$sender->set_object_read(true);
		}

		return $sender;
	}



	// public function update(&$sender) {
	//     global $wpdb;
	//     $data = [
	//         'name'        => $sender->get_name(),
	//         'address'     => $sender->get_address(),
	//         'cp'          => $sender->get_cp(),
	//         'nif_cif'     => $sender->get_nif_cif(),
	//         'city'        => $sender->get_city(),
	//         'contact'     => $sender->get_contact(),
	//         'phone'       => $sender->get_phone(),
	//         'from_time'   => $sender->get_from_time(),
	//         'to_time'     => $sender->get_to_time(),
	//         'iso_code_pais' => $sender->get_iso_code_pais(),
	//         'email'       => $sender->get_email(),
	//         'is_default'  => $sender->get_is_default(),
	//     ];
	//     $wpdb->update($this->table_name, $data, ['id' => $sender->get_id()]);
	// }

	// public function delete(&$sender, $force_delete = false) {
	//     global $wpdb;
	//     $wpdb->delete($this->table_name, ['id' => $sender->get_id()]);
	// }

	// public function get_all($args = []) {
	//     global $wpdb;
	//     $query = "SELECT * FROM $this->table_name";
	//     if (!empty($args['include'])) {
	//         $placeholders = implode(',', array_fill(0, count($args['include']), '%d'));
	//         $query .= $wpdb->prepare(" WHERE id IN ($placeholders)", $args['include']);
	//     }
	//     return $wpdb->get_results($query);
	// }

	// function set_default($sender_id) {
	//     global $wpdb;
	
	//     // Validar que el ID sea un número válido
	//     $sender_id = intval($sender_id);
	//     if ($sender_id <= 0) {
	//         return false;
	//     }
	
	//     // Establecer todos los remitentes como no predeterminados (is_default = 0)
	//     $wpdb->query("UPDATE $this->table_name SET is_default = 0");
	
	//     // Marcar el remitente especificado como predeterminado (is_default = 1)
	//     $updated = $wpdb->update(
	//         $this->table_name,
	//         ['is_default' => 1],
	//         ['id' => $sender_id],
	//         ['%d'],
	//         ['%d']
	//     );
	
	//     return $updated !== false;
	// }
}
