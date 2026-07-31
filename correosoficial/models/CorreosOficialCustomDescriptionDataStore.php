<?php

namespace CorreosOficial\Models;

use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialCustomDescriptionDataStore extends WC_Data_Store_WP {

    private $table;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_customs_description';
	}

    public function read( &$custom ) {
		$id = $custom->get_id();
		if (! $id) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE id = %d', $id), ARRAY_A); // phpcs:ignore


		if ($data) {
			$custom->set_props($data);
		}
	}

    public function get_all_customs_desc() {
		static $cache = null; // cache interno

		if ($cache !== null) {
			return $cache;
		}
		
		$data = $this->wpdb->get_results(
			"SELECT code, description FROM {$this->table}"
		);
		
		$array_desc = [];

		if ($data) {
			foreach ($data as $desc) {
				$array_desc[$desc->code] = $desc->description;
			}
		}

		$cache = $array_desc; // Guardar en cache para futuras llamadas
		return $cache;
	}

}