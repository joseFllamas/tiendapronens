<?php

namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialProduct extends WC_Data {
	protected $id;
	protected $data = array(
		'code'            => '',
		'mode'            => '',
		'name'            => '',
		'active'          => 0,
		'delay'           => '',
		'company'         => '',
		'url'             => '',
		'codigoProducto'  => '',
		'id_carrier'      => 0,
		'product_type'    => '',
		'max_packages'    => 0,
		'max_weight'      => 0,
	);

	protected $object_type = 'correos_oficial_product';

	public function __construct( $id = 0 ) {
		parent::__construct($id);
		$this->data_store = new CorreosOficialProductDataStore();

		if ($id > 0) {
			$this->id = $id;
			$this->read();
		}
	}

	// Acceso directo a propiedades protegidas desde el exterior (controladores y templates)
	public function __get( $name ) {
		if ( $name === 'id' ) {
			return $this->id;
		}
		return isset( $this->data[ $name ] ) ? $this->data[ $name ] : null;
	}

	public function __set( $name, $value ) {
		if ( $name === 'id' ) {
			$this->id = $value;
		} elseif ( array_key_exists( $name, $this->data ) ) {
			$this->data[ $name ] = $value;
		}
	}

	public function __isset( $name ) {
		if ( $name === 'id' ) {
			return isset( $this->id );
		}
		return isset( $this->data[ $name ] );
	}

	// Getter y Setter dinámicos
	public function __call( $method, $arguments ) {
		if (strpos($method, 'get_') === 0) {
			$prop = substr($method, 4);
			return isset($this->data[$prop]) ? $this->data[$prop] : null;
		}

		if (strpos($method, 'set_') === 0) {
			$prop = substr($method, 4);
			if (array_key_exists($prop, $this->data)) {
				$this->data[$prop] = $arguments[0];
			}
		}
	}

	public function read() {
 		$this->data_store->read($this); 
	}
	
	public function get_by_carrier( $carrier_id ) {
	    $data_store = new CorreosOficialProductDataStore();
	    $product_data = $data_store->get_product_by_carrier( $carrier_id );
		
	    if ( $product_data && isset( $product_data['id'] ) ) {
	        $this->id = $product_data['id'];
	        $this->data = array_merge($this->data, $product_data);
	        $this->read();
	        return $this;
	    }
	    return null;
	}

	public static function get_all_products() {
		$data_store = new CorreosOficialProductDataStore();

		return $data_store->get_all_products();
	}

        /**
         * Returns all rows in correos_oficial_products with the given id (as array of objects).
         *
         * @param int $id Product id.
         * @return array|null Array of stdClass rows, or null if not found.
         */
        public static function get_product( int $id ): ?array {
                global $wpdb;
                $table   = $wpdb->prefix . 'correos_oficial_products';
                $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
                return !empty( $results ) ? $results : null;
        }

        /**
         * Returns active products (with optional WHERE clause) as array of objects.
         *
         * @param string $where Additional SQL clause (e.g. ' WHERE cop.active=1').
         * @return array|null Array of stdClass rows, or null if empty.
         */
        public static function get_active_products( string $where = '' ): ?array {
                global $wpdb;
                $p       = $wpdb->prefix;
                $results = $wpdb->get_results(
                        "SELECT cop.id, cop.name, cop.active, cop.delay, cop.company, cop.url, cop.codigoProducto, cop.id_carrier, cop.product_type, cop.max_packages " .
                        "FROM {$p}correos_oficial_products as cop " .
                        "LEFT JOIN {$p}correos_oficial_codes_actives as coc ON cop.company=coc.company{$where}"
                );
                return !empty( $results ) ? $results : null;
        }

        /**
         * Sets active=0 on all products.
         */
        public static function reset_products(): void {
                global $wpdb;
                $wpdb->query( "UPDATE {$wpdb->prefix}correos_oficial_products SET active = 0" );
        }

        /**
         * Sets active=1 on the product with the given id.
         *
         * @param int $id Product id.
         */
        public static function activate_product( int $id ): void {
                global $wpdb;
                $wpdb->update(
                        $wpdb->prefix . 'correos_oficial_products',
                        array( 'active' => 1 ),
                        array( 'id' => $id ),
                        null,
                        '%d'
                );
        }

        /**
         * Returns carrier+product params for a given carrier (JOIN result as assoc array).
         *
         * @param array $carrier Carrier data with 'id_reference' key.
         * @return array|null First matching row as associative array, or null.
         */
        public static function get_carrier_params( array $carrier ): ?array {
                global $wpdb;
                $p       = $wpdb->prefix;
                $results = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT * FROM {$p}correos_oficial_products cop " .
                                "JOIN {$p}correos_oficial_carriers_products cocp ON cop.id = cocp.id_product " .
                                "WHERE cocp.id_carrier = %d",
                                $carrier['id_reference']
                        ),
                        ARRAY_A
                );
                return !empty( $results ) ? $results[0] : null;
        }
}