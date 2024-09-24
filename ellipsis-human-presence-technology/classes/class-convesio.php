<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceConvesio {

	public $convesio_plugin_dirname;

	/**
	 * Perform initial cleanup if non-bundled HP plugin versions are installed on Convesio and activate
	 *
	 * @param String $convesio_plugin_dirname - Convesio plugin directory
	 */
	public function __construct( $convesio_plugin_dirname ) {

		require_once( dirname( __FILE__ ) . '/class-hpres-utils.php' );
		$this->convesio_plugin_dirname = $convesio_plugin_dirname;
		add_filter( 'human_presence_activation_hook_file', array( $this, 'plugin_file' ) );
		add_filter( 'human_presence_partner', array( $this, 'partner_name' ) );
	}

	public function plugin_file( $file_path ) {
		return $this->convesio_plugin_dirname;
	}

	public function partner_name( $partner_name ) {
		return 'convesio';
	}

	/**
	 * Perform initial cleanup if non-bundled HP plugin versions are installed on Convesio and activate
	 */
	public function clean() {
		HumanPresenceUtils::clean();
	}

}
