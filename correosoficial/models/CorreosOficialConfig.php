<?php
namespace CorreosOficial\Models;

if (! defined('ABSPATH')) {
	exit;
}

// NOTA esta clase no es WC_Data es una seudo implementación ya que el modelo no dispone de id

class CorreosOficialConfig {

	/** @var bool|null Caché de existencia de tabla; null = no comprobado aún. */
	private static $table_exists_cache = null;

	/** @var array|null Caché de get_all_config(). */
	private static $all_config_cache = null;

	/** @var array|null Caché de get_all(). */
	private static $all_records_cache = null;

	private $table;
	private $wpdb;

	/**
	 * Comprueba si la tabla de configuración existe. El resultado se cachea en memoria
	 * para que sólo se ejecute un SHOW TABLES por carga de página.
	 */
	private static function config_table_exists(): bool {
		if ( self::$table_exists_cache === null ) {
			global $wpdb;
			$table                 = $wpdb->prefix . 'correos_oficial_configuration';
			self::$table_exists_cache = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}
		return self::$table_exists_cache;
	}

	/**
	 * Resetea las cachés en memoria. Útil en tests para garantizar
	 * que cada test evalúa la condición desde cero.
	 */
	public static function reset_cache(): void {
		self::$table_exists_cache = null;
		self::$all_config_cache   = null;
		self::$all_records_cache  = null;
	}

	/**
	 * Propiedades del objeto.
	 */
	protected $props = array(
		'name'  => '',
		'value' => '',
		'type'  => '',
	);

	protected $object_type = 'correos_oficial_configuration';

	/**
	 * Constructor.
	 */
	public function __construct( $name ) {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_configuration';
		
		$this->read($name);
	}

	/**
	 * Métodos getter y setter.
	 */
	public function get_name() {
		return $this->props['name'];
	}
	public function set_name( $value ) {
		$this->props['name'] = $value;
	}

	public function get_value() {
		return $this->props['value'];
	}

	public function set_value( $value ) {
		$this->props['value'] = $value;
	}

	public function get_type() {
		return $this->props['type'];
	}

	public function set_type( $value ) {
		$this->props['type'] = $value;
	}

	public function read( $name ) {
		if (! $name) {
			return;
		}

		if ( ! self::config_table_exists() ) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE name = %s', $name), ARRAY_A); // phpcs:ignore

		if ($data) {
			$this->set_name($data['name']);
			$this->set_value($data['value']);
			$this->set_type($data['type']);
		}
	}

	// Métodos con lógica relaciodada con configuración
	public static function getLabelAlternativeText() {
		$customerConfig = new self('CustomerAlternativeText');
		$CustomerAlternativeText = $customerConfig->get_value();
	
		if ($CustomerAlternativeText === 'on') {
			$labelConfig = new self('LabelAlternativeText');
			return $labelConfig->get_value();
		}
	
		return false;
	}

	public static function getWeightByDefault() { 
		$defaultWeightConfig = new self('WeightByDefault');

		return $defaultWeightConfig->get_value();
	}

	// metodo para obtener todos los campos de una manera determinada /rellenar checkboxes y otros contenidos
	public static function get_all() {
		if (self::$all_records_cache !== null) {
			return self::$all_records_cache;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_configuration';

		if ( ! self::config_table_exists() ) {
			self::$all_records_cache = [];
			return self::$all_records_cache;
		}

		$results = $wpdb->get_results("SELECT * FROM {$table}", OBJECT);
		self::$all_records_cache = $results;

		return self::$all_records_cache;
	}

	// metodo para obtener todos los campos para luego ser buscados de manera individual
	public static function get_all_config() {
		if (self::$all_config_cache !== null) {
			return self::$all_config_cache;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_configuration';

		if ( ! self::config_table_exists() ) {
			self::$all_config_cache = [];
			return self::$all_config_cache;
		}

		$results = $wpdb->get_results("SELECT name, value FROM {$table}", OBJECT_K);

		self::$all_config_cache = $results;
		return self::$all_config_cache;
	}

	public static function get_config_status($config_status) {
		$all = self::get_all_config();
		return $all[$config_status] ?? null;
	}

	public static function save($name, $value, $type = 'text') {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_configuration';

		// Verificar si el campo existe
		$exists = $wpdb->get_var(
			$wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE name = %s", $name)
		);

		if ($exists) {
			// Actualizar solo el valor
			$updated = $wpdb->update(
				$table,
				['value' => sanitize_text_field($value)],
				['name'  => $name],
				['%s'],
				['%s']
			);

			if ($updated === false) {
				return false;
			}
		} else {
			// Insertar nuevo registro si no existe
			$inserted = $wpdb->insert(
				$table,
				[
					'name'  => sanitize_text_field($name),
					'value' => sanitize_text_field($value),
					'type'  => $type,
				],
				['%s', '%s', '%s']
			);

			if ($inserted === false) {
				return false;
			}
		}

		self::reset_cache();

		return true;
	}

	public static function set_value_by_name($name, $value) {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_configuration';
		
		$wpdb->update(
			$table,
			['value' => sanitize_text_field($value)],
			['name'  => $name],
			['%s'],
			['%s']
		);

		self::reset_cache();
	}

        /**
         * Drop-in replacement for CorreosOficialConfig::getConfigValue().
         * Returns the string value for a given config key, or null if not found.
         */
        public static function getConfigValue( string $key ): ?string {
                $all = self::get_all_config();
                return isset( $all[ $key ] ) ? $all[ $key ]->value : null;
        }

		/**
		 * Returns the configured WooCommerce gateway id used as COD in the plugin.
		 * Values like '', null or '0' mean "none configured".
		 */
		public static function getConfiguredCodMethod(): string {
			$value = self::getConfigValue( 'CashOnDeliveryMethod' );

			if ( $value === null || $value === '' || $value === '0' ) {
				$instance_value = ( new self( 'CashOnDeliveryMethod' ) )->get_value();
				$value = $instance_value;
			}

			if ( $value === null || $value === '' || $value === '0' ) {
				return '';
			}

			return sanitize_text_field( (string) $value );
		}

		public static function getConfiguredCodMethodAliases(): array {
			$configured_method = self::getConfiguredCodMethod();

			if ( $configured_method === '' ) {
				return array( 'cod' );
			}

			$aliases = array( $configured_method, 'cod' );

			if ( $configured_method === 'cheque' ) {
				$aliases[] = 'checkpayment';
				$aliases[] = 'check_payment';
			}

			if ( $configured_method === 'checkpayment' || $configured_method === 'check_payment' ) {
				$aliases[] = 'cheque';
				$aliases[] = 'checkpayment';
				$aliases[] = 'check_payment';
			}

			return array_values( array_unique( array_filter( $aliases ) ) );
		}

        /**
         * Drop-in replacement for CorreosOficialConfig::checkDimensionsByDefaultActivated().
         */
        public static function checkDimensionsByDefaultActivated(): bool {
                return ( self::getConfigValue( 'ActivateDimensionsByDefault' ) === 'on' ||
                        ( (int) self::getConfigValue( 'DimensionsByDefaultHeight' ) > 0 &&
                          (int) self::getConfigValue( 'DimensionsByDefaultLarge' )  > 0 &&
                          (int) self::getConfigValue( 'DimensionsByDefaultWidth' )  > 0 ) );
        }

}
