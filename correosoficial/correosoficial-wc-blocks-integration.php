<?php
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class CorreosOficial_Wc_Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'correosoficial';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		require_once __DIR__ . '/correosoficial-wc-extend-store-endpoint.php';
		$this->register_correosoficial_wc_block_frontend_scripts();
		$this->register_correosoficial_wc_block_editor_scripts();
		$this->register_correosoficial_wc_block_editor_styles();

		$this->register_correosoficial_wc_block_nif_frontend_scripts();
		$this->register_correosoficial_wc_block_nif_editor_scripts();

		$this->register_main_integration();
	}

	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	private function register_main_integration() {
		$script_path = '/build/index.js';
		$style_path  = '/build/style-index.css';

		$script_url = plugins_url( $script_path, __FILE__ );
		$style_url  = plugins_url( $style_path, __FILE__ );

		$script_asset_path = __DIR__ . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
			);

		wp_enqueue_style(
			'correosoficial-wc-blocks-integration',
			$style_url,
			array(),
			$this->get_file_version( $style_path )
		);

		wp_register_script(
			'correosoficial-wc-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations(
			'correosoficial-wc-blocks-integration',
			'correosoficial',
			__DIR__ . '/languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array(
			'correosoficial-wc-blocks-integration',
			'correosoficial-wc-block-frontend',
			'correosoficial-wc-block-nif-frontend',
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 
			'correosoficial-wc-blocks-integration',
			'correosoficial-wc-block-editor',
			'correosoficial-wc-block-nif-editor',
		);
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$data = array(
			'correosoficial-wc-active'    => true,

			/**
			 * 💰Extra credit: Add a key/value pair to the data array that will be available to the block on the client side.
			 * It should be called defaultLabelText and the value should be the default text to display above the label
			 * in the editor.
			 *
			 * To test if this is working, go to the editor, remove and re-add the Checkout block and see the label text
			 * above the alternative shipping options select box.
			 */
			'defaultLabelText' => __( 'What should we do if you are not at home?', 'correosoficial' ),
		);

		return $data;
	}

	// Block de checkout para transportistas

	public function register_correosoficial_wc_block_editor_styles() {
		$style_path = '/build/style-correosoficial-wc-block.css';

		$style_url = plugins_url( $style_path, __FILE__ );
		wp_enqueue_style(
			'correosoficial-wc-block',
			$style_url,
			array(),
			$this->get_file_version( $style_path )
		);
	}

	public function register_correosoficial_wc_block_editor_scripts() {
		$script_path       = '/build/correosoficial-wc-block.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/correosoficial-wc-block.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'correosoficial-wc-block-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'correosoficial-wc-block-editor',
			'correosoficial',
			__DIR__ . '/languages'
		);
	}

	public function register_correosoficial_wc_block_frontend_scripts() {
		$script_path       = '/build/correosoficial-wc-block-frontend.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/correosoficial-wc-block-frontend.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'correosoficial-wc-block-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations(
			'correosoficial-wc-block-frontend',
			'correosoficial',
			__DIR__ . '/languages'
		);
	}

	// Registros para bloque NIF
	public function register_correosoficial_wc_block_nif_editor_scripts() {
		$script_path       = '/build/correosoficial-wc-block-nif.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/correosoficial-wc-block-nif.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'correosoficial-wc-block-nif-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'correosoficial-wc-block-nif-editor',
			'correosoficial',
			__DIR__ . '/languages'
		);
	}

	public function register_correosoficial_wc_block_nif_frontend_scripts() {
		$script_path       = '/build/correosoficial-wc-block-nif-frontend.js';
		$script_url        = plugins_url( $script_path, __FILE__ );
		$script_asset_path = __DIR__ . '/build/correosoficial-wc-block-nif-frontend.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'correosoficial-wc-block-nif-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations(
			'correosoficial-wc-block-nif-frontend',
			'correosoficial',
			__DIR__ . '/languages'
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
		return CORREOS_OFICIAL_VERSION;
	}
}
