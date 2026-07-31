<?php

namespace CorreosOficial\Models;

use Exception;
use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialCodeDataStore extends WC_Data_Store_WP {

	private $table;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_codes';
	}

	public function create( &$code ) {
		$data = $code->get_data();
		unset($data['meta_data']);
		$this->wpdb->insert(
			$this->table,
			$data,
			array_fill(0, count($data), '%s')
		);
		$code->set_id($data['id']);
	}

	public function read( &$code ) {
		$id = $code->get_id();
		if (! $id) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE id = %d', $id), ARRAY_A); // phpcs:ignore


		if ($data) {
			$code->set_props($data);
		}
	}

	public function update( &$code ) {
		$id = $code->get_id();
		if (! $id) {
			$this->create($code);
		}

		$data = $code->get_data();
		unset($data['meta_data']);
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			array_fill(0, count($data), '%s'),
			array( '%d' )
		);
	}

	public function delete( &$code, $force_delete = false ) {
		$id = $code->get_id();
		if (! $id) {
			return;
		}
		return $this->wpdb->delete($this->table, array( 'id' => $id ), array( '%d' ));
	}

	public function get_code_by_customer(&$code, $customer_code) {
		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE customer_code = %s', $customer_code), ARRAY_A); // phpcs:ignore

		if ($data) {
			$code->set_props($data);
		}
	}
}
