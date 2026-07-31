<?php

namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialSender extends WC_Data {

	protected $id;
	protected $data = array(
		'sender_name'          => '',
		'sender_address'       => '',
		'sender_cp'            => '',
		'sender_nif_cif'       => '',
		'sender_city'          => '',
		'sender_contact'       => '',
		'sender_phone'         => '',
		'sender_from_time'     => '',
		'sender_to_time'       => '',
		'sender_iso_code_pais' => '',
		'sender_email'         => '',
		'sender_default'       => 0,
		'correos_code'         => '',
		'cex_code'             => '',
	);

	protected $object_type = 'correos_oficial_sender';

	public function __construct( $id = 0 ) {

		parent::__construct($id);
		$this->data_store = new CorreosOficialSenderDataStore();

		if (is_numeric($id) && $id > 0) {
			$this->set_id($id);
			$this->read();
		} else if ($id === 'default') {
			$this->load_as_default();
		}
	}

	public function get_sender_name() {
 return $this->get_prop('sender_name'); }
	public function set_sender_name( $value ) {
 $this->set_prop('sender_name', $value); }

	public function get_sender_address() {
 return $this->get_prop('sender_address'); }
	public function set_sender_address( $value ) {
 $this->set_prop('sender_address', $value); }

	public function get_sender_cp() {
 return $this->get_prop('sender_cp'); }
	public function set_sender_cp( $value ) {
 $this->set_prop('sender_cp', $value); }

	public function get_sender_nif_cif() {
 return $this->get_prop('sender_nif_cif'); }
	public function set_sender_nif_cif( $value ) {
 $this->set_prop('sender_nif_cif', $value); }

	public function get_sender_city() {
 return $this->get_prop('sender_city'); }
	public function set_sender_city( $value ) {
 $this->set_prop('sender_city', $value); }

	public function get_sender_contact() {
 return $this->get_prop('sender_contact'); }
	public function set_sender_contact( $value ) {
 $this->set_prop('sender_contact', $value); }

	public function get_sender_phone() {
 return $this->get_prop('sender_phone'); }
	public function set_sender_phone( $value ) {
 $this->set_prop('sender_phone', $value); }

	public function get_sender_from_time() {
 return $this->get_prop('sender_from_time'); }
	public function set_sender_from_time( $value ) {
 $this->set_prop('sender_from_time', $value); }

	public function get_sender_to_time() {
 return $this->get_prop('sender_to_time'); }
	public function set_sender_to_time( $value ) {
 $this->set_prop('sender_to_time', $value); }

	public function get_sender_iso_code_pais() {
 return $this->get_prop('sender_iso_code_pais'); }
	public function set_sender_iso_code_pais( $value ) {
 $this->set_prop('sender_iso_code_pais', $value); }

	public function get_sender_email() {
 return $this->get_prop('sender_email'); }
	public function set_sender_email( $value ) {
 $this->set_prop('sender_email', $value); }

	public function get_correos_code() {
 return $this->get_prop('correos_code'); }
	public function set_correos_code( $value ) {
 $this->set_prop('correos_code', $value); }

	public function get_cex_code() {
 return $this->get_prop('cex_code'); }
	public function set_cex_code( $value ) {
 $this->set_prop('cex_code', $value); }

	public function read() {
 $this->data_store->read($this); }
	public function load_as_default() {
 $this->data_store->get_default_sender($this); }
	public function get_first_sender_by_company( $company ) {
		return $this->data_store->get_first_sender_by_company($this, $company);
	}
	// public function save() { $this->data_store->update($this); }
	// public function create() { $this->data_store->create($this); }

	// ─── Static query methods ────────────────────────────────────────────────

	/**
	 * Returns the default sender row as an array, or a specific sender by ID.
	 *
	 * @param  int|false $id_sender
	 * @return array|null
	 */
	public static function get_default_sender( $id_sender = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		if ( $id_sender ) {
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id_sender ),
				ARRAY_A
			) ?: null;
		}
		return $wpdb->get_row( "SELECT * FROM {$table} WHERE sender_default = 1", ARRAY_A ) ?: null;
	}

	/**
	 * Returns all senders, optionally filtered by company code presence.
	 *
	 * @param  string|false $company 'correos'|'cex'|false
	 * @return array|null
	 */
	public static function get_senders( $company = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		if ( ! $company ) {
			return $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ) ?: null;
		}
		$col = esc_sql( strtolower( (string) $company ) ) . '_code';
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$col} <> 0 ORDER BY sender_default DESC",
			ARRAY_A
		) ?: null;
	}

	/**
	 * Returns all senders with correos_code/cex_code resolved to customer names.
	 *
	 * @return array|null
	 */
	public static function get_senders_with_codes(): ?array {
		global $wpdb;
		$senders = self::get_senders();
		if ( ! $senders ) {
			return null;
		}
		$codes_table = $wpdb->prefix . 'correos_oficial_codes';
		foreach ( $senders as &$sender ) {
			if ( ! empty( $sender['correos_code'] ) && (int) $sender['correos_code'] !== 0 ) {
				$row = $wpdb->get_row(
					$wpdb->prepare( "SELECT CorreosCustomer FROM {$codes_table} WHERE id = %d", $sender['correos_code'] ),
					ARRAY_A
				);
				$sender['correos_code'] = $row ? $row['CorreosCustomer'] : $sender['correos_code'];
			}
			if ( ! empty( $sender['cex_code'] ) && (int) $sender['cex_code'] !== 0 ) {
				$row = $wpdb->get_row(
					$wpdb->prepare( "SELECT CEXCustomer FROM {$codes_table} WHERE id = %d", $sender['cex_code'] ),
					ARRAY_A
				);
				$sender['cex_code'] = $row ? $row['CEXCustomer'] : $sender['cex_code'];
			}
		}
		unset( $sender );
		return $senders;
	}

	/**
	 * Returns the code row for a given sender ID and company ('correos'|'cex').
	 *
	 * @param  int    $sender_id
	 * @param  string $company
	 * @return array|null
	 */
	public static function get_code_by_sender_and_company( $sender_id, string $company ): ?array {
		if ( $company !== 'correos' && $company !== 'cex' ) {
			return null;
		}
		global $wpdb;
		$senders_table = $wpdb->prefix . 'correos_oficial_senders';
		$codes_table   = $wpdb->prefix . 'correos_oficial_codes';
		$sender = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$senders_table} WHERE id = %d", (int) $sender_id ),
			ARRAY_A
		);
		if ( ! $sender ) {
			return null;
		}
		$code_id = (int) $sender[ $company . '_code' ];
		if ( $code_id === 0 ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$codes_table} WHERE id = %d", $code_id ),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Returns sender_from_time and sender_to_time from the default sender.
	 *
	 * @return array|null
	 */
	public static function get_default_time(): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		return $wpdb->get_row(
			"SELECT sender_from_time, sender_to_time FROM {$table} WHERE sender_default = 1",
			ARRAY_A
		) ?: null;
	}

	// ─── Write methods ───────────────────────────────────────────────────────

	/**
	 * Inserts a new sender. Marks it as default if it is the only one.
	 *
	 * @param array $fields
	 */
	public static function insert( array $fields ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		$wpdb->insert( $table, $fields );
		$last_id = (int) $wpdb->insert_id;
		$count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count === 1 ) {
			self::set_default( $last_id );
		}
	}

	/**
	 * Updates an existing sender. $fields must contain 'id'.
	 *
	 * @param array $fields
	 */
	public static function update_sender( array $fields ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		$id    = (int) $fields['id'];
		unset( $fields['id'] );
		$wpdb->update( $table, $fields, array( 'id' => $id ), null, array( '%d' ) );
	}

	/**
	 * Marks a sender as the default (resets all others first).
	 *
	 * @param int|string $id
	 */
	public static function set_default( $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		$wpdb->query( "UPDATE {$table} SET sender_default = '0'" );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET sender_default = '1' WHERE id = %d", (int) $id ) );
	}

	/**
	 * Deletes a sender by ID.
	 *
	 * @param int|string $id
	 */
	public static function delete_sender( $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_senders';
		$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}
}
