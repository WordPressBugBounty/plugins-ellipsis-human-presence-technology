<?php

/*
 *	Plugin Name: Human Presence
 *	Plugin URI: https://www.humanpresence.io/anti-spam-wordpress-plugin
 *	Description: Human Presence is a fraud prevention and form protection service that uses multiple overlapping strategies to fight form spam.
 *	Version: 3.4.51
 *	Author: Human Presence Technology
 *	Author URI: https://www.humanpresence.io
 *  Developer: John Schulz
 *  Developer URI: https://ellipsistech.io
 *  Text Domain: wp-human-presence
 *	License: GPL2
 *
*/

if ( ! class_exists( 'HumanPresence' ) ) {

	final class HumanPresence {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 3.2.0
		 */
		private static $instance;

		public $version;
		public $text_domain;
		public $menu_slug;
		public $file;
		public $basename;
		public $plugin_dir;
		public $plugin_url;
		public $admin_url;
		public $settings;
		public $options;
		public $partner;
		public $default_api_key;

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 3.2.0
		 *
		 * @param $partner (string)
		 *
		 * @return self
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof HumanPresence ) ) {
				self::$instance = new self();
				self::$instance->init();
			}

			return self::$instance;
		}

		/**
		 * Class constructor.
		 *
		 * @since 3.2.0
		 *
		 * @return void
		 */
		private function __construct() {
		}

		public function is_partner() {
			return (bool) self::$instance->partner;
		}

		public function is_convesio() {
			return 'convesio' === self::$instance->partner;
		}

		/**
		 * Get debug status
		 *
		 * @since 3.4.32
		 *
		 * @return bool
		 */
		public function is_debug() {
			$options = self::$instance->get_options();

			return HUMAN_PRESENCE_DEBUG || ( 
				! empty( $options['wp_hp_debug'] ) &&
			  	1 === $options['wp_hp_debug']
			);
		}

		/**
		 * Get premium status
		 *
		 * @since 3.4.0
		 *
		 * @return bool
		 */
		public function is_premium() {
			$options = self::$instance->get_options();

			return self::$instance->is_partner() ||
				( 
					! empty( $options['wp_hp_premium_license_key'] ) &&
					! empty( $options['wp_hp_premium_license'] ) &&
				  	1 === $options['wp_hp_premium_license']
				);
		}

		/**
		 * Get community status
		 *
		 * @since 3.4.27
		 *
		 * @return bool
		 */
		public function is_community() {
			return !self::$instance->is_premium() && !self::$instance->is_partner();
		}

		private function connect_convesio() {
			$options = self::get_options();
			if ( ! isset( $options['convesio_setup_complete'] ) || 1 != $options['convesio_setup_complete'] ) {
				$options                 = self::$instance->get_partner_settings( 'convesio' );
				$options['last_updated'] = time();
				// Enable premium license
				$options['wp_hp_premium_license'] = 1;
				// Enable auto-protection by default
				$options['wp_hp_autoprotect']       = 1;
				$options['convesio_setup_complete'] = 1;
				self::update_options( $options );
			}
		}

		private function get_partner_settings( $partner ) {
			// Check Human Presence via an API request.
			$url  = 'https://humanpresence.io/';
			$url  .= '?' . $partner . '=j29fdCI2ErmMF2kCGHv04fs1hm7kN6pr';
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_CUSTOMREQUEST  => 'GET'
			) );
			$response    = curl_exec( $curl );
			$status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			curl_close( $curl );

			// If we got an OK response from the API...
			if ( ! empty( $status_code ) && 200 == $status_code ) {
				// Return the data array.
				return json_decode( $response, true );
			} else {
				// Otherwise return an empty array.
				return array();
			}
		}

		public function init() {
			self::$instance->_setup_globals();
			self::$instance->_activation_hooks();
			self::$instance->_connect_partner();
			self::$instance->_include_files();
			self::$instance->_settings();
			self::$instance->_hooks();
			if ( is_admin() ) {
				self::$instance->schedule_events();
			}
			if ( self::$instance->is_partner() ) {
				// Attempt to auto-protect by default, unattended
				add_action( 'wp_loaded', array( 'HumanPresenceAutoProtect', 'init' ) );
			}
			self::$instance->upgrade();
		}

		private function _connect_partner() {
			if ( self::$instance->is_convesio() ) {
				self::$instance->connect_convesio();
			}
		}

		private function _setup_globals() {

			defined( 'HUMAN_PRESENCE_PARTNER' ) or define( 'HUMAN_PRESENCE_PARTNER', apply_filters( 'human_presence_partner', '' ) );
			defined( 'HUMAN_PRESENCE_DEBUG' ) || define( 'HUMAN_PRESENCE_DEBUG', apply_filters( 'human_presence_debug', false ) );
			defined( 'HUMAN_PRESENCE_LOG_LIMIT' ) || define( 'HUMAN_PRESENCE_LOG_LIMIT' , apply_filters( 'human_presence_log_limit', 10 ) );

			self::$instance->partner = HUMAN_PRESENCE_PARTNER;

			self::$instance->version     = '3.4.51';
			self::$instance->text_domain = 'wp-human-presence';
			self::$instance->menu_slug   = 'wp-human-presence';

			self::$instance->default_api_key = '68ab8080-85dd-4670-8123-893028b5d7d4';

			// paths
			self::$instance->file       = apply_filters( 'human_presence_plugin_file', __FILE__ );
			self::$instance->basename   = apply_filters( 'human_presence_plugin_basename', plugin_basename( self::$instance->file ) );
			self::$instance->plugin_dir = apply_filters( 'human_presence_plugin_dir_path', plugin_dir_path( self::$instance->file ) );
			self::$instance->plugin_url = apply_filters( 'human_presence_plugin_dir_url', plugin_dir_url( self::$instance->file ) );
			self::$instance->admin_url  = apply_filters( 'human_presence_plugin_admin_url', admin_url( 'admin.php?page=' . self::$instance->menu_slug ) );

		}

		/**
		 * Include files required by the plugin.
		 *
		 * @return void
		 */
		private function _include_files() {

			// Includes
			require_once( self::$instance->plugin_dir . 'classes/class-hpres-utils.php' );
			require_once( self::$instance->plugin_dir . 'classes/class-hpres-protected-forms-list-table.php' );
			require_once( self::$instance->plugin_dir . 'classes/class-hpres-settings.php' );
			require_once( self::$instance->plugin_dir . 'classes/class-hpres-auto-protect.php' );
			require_once( self::$instance->plugin_dir . 'classes/class-hpres-forms.php' );

			// Integrations
			require_once( self::$instance->plugin_dir . 'classes/class-integration.php' );
			require_once( self::$instance->plugin_dir . 'integrations/wordpress-comments.php' );
			include_once( self::$instance->plugin_dir . 'integrations/ninja-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/formidable-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/gravity-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/contact-form-7-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/ws-form.php' );
			include_once( self::$instance->plugin_dir . 'integrations/we-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/quform-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/fluent-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/wp-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/elementor-forms.php' );
			include_once( self::$instance->plugin_dir . 'integrations/happy-forms.php' );

		}

		public function _settings() {

			self::$instance->settings = new HumanPresenceSettings( self::$instance->is_partner() );

		}

		/**
		 * Run activation, deactivation, and uninstall action hooks.
		 *
		 * @since 3.3.3
		 *
		 * @return void
		 */
		private function _activation_hooks() {

			register_activation_hook( apply_filters( 'human_presence_activation_hook_file', self::$instance->file ), array(
				__CLASS__,
				'activate'
			) );
			register_deactivation_hook( apply_filters( 'human_presence_activation_hook_file', self::$instance->file ), array(
				__CLASS__,
				'deactivate'
			) );
			register_uninstall_hook( apply_filters( 'human_presence_activation_hook_file', self::$instance->file ), array(
				__CLASS__,
				'uninstall'
			) );

		}

		/**
		 * Run action and filter hooks.
		 *
		 * @since 3.2.0
		 *
		 * @return void
		 */
		public function _hooks() {

			add_action( 'admin_menu', array( self::$instance, 'menu' ) );
			add_filter( 'plugin_action_links_' . self::$instance->basename, array(
				self::$instance,
				'plugin_add_settings_link'
			) );

			// load scripts
			add_action( 'admin_enqueue_scripts', array( self::$instance, 'load_scripts' ) );

			add_filter( 'cron_schedules', array( self::$instance, 'isa_add_cron_recurrence_interval' ) );

			// Auto-protect all newly created forms
			add_action( 'human_presence_autoprotect_scan', array( 'HumanPresenceAutoProtect', 'init' ) );

			if ( self::$instance->get_api_key() ) {
				add_action( 'wp_enqueue_scripts', array( self::$instance, 'load_frontend_scripts' ) );
				add_filter('script_loader_tag', array( self::$instance, 'add_async_attribute' ), 10, 2);
				add_filter('script_loader_tag', array( self::$instance, 'add_defer_attribute' ), 10, 2);
			}

		}

		public function menu() {

			add_menu_page(
				'Human Presence Form Protection',
				'Human Presence',
				'manage_options',
				self::$instance->menu_slug,
				array( 'HumanPresenceSettings', 'options_page' )
			);

		}

		/**
		 * Render the HP 'Settings' link on the plugins page
		 *
		 * @param array $links - Array of anchor tags
		 *
		 * @return array $links
		 */
		public function plugin_add_settings_link( $links ) {

			$settings_link = '<a href="admin.php?page=wp-human-presence">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;

		}

		/**
		 * Loads admin scripts.
		 *
		 * @since 3.2.0
		 *
		 * @return void
		 */
		public function load_scripts( $hook ) {

			wp_enqueue_style( 'styles', self::$instance->plugin_url . 'css/wp-human-presence.css', array(), self::$instance->version );

			if ( 'toplevel_page_wp-human-presence' != $hook ) {
				return;
			}

		}

		public function load_frontend_scripts() {

			$script_url = 'https://script.metricode.com/wotjs/ellipsis.js';
			$script_url .= '?api_key=' . self::$instance->get_api_key();
			if ( self::$instance->is_convesio() ) {
				$script_url .= '&cid=convesio';
			}

			wp_enqueue_script( 'hpjs', $script_url, array(), self::$instance->version, true );

		}

		//function to add async attribute
		public function add_async_attribute( $tag, $handle ) {

			$scripts_to_async = [ 'hpjs', ];
			//check if this script is in the array	
			if ( in_array( $handle, $scripts_to_async ) ){
				//return with async
				return str_replace( ' src', ' async="async" src', $tag );
			} else {
				//return without async
				return $tag;
			}

		}

		//function to add defer attribute
		public function add_defer_attribute( $tag, $handle ) {

			$scripts_to_defer = [ 'hpjs', ];
			//check if this script is in the array
			if ( in_array( $handle, $scripts_to_defer ) ){
				//return with defer
				return str_replace( ' src', ' defer="defer" src', $tag );		
			} else {
				//return without async
				return $tag;
			}

		}

		public static function get_default_options() {
			return array(
				'hp_db_version'					   => '',
				'wp_hp_username'                   => '',
				'wp_hp_email'                      => '',
				'wp_hp_api_key'                    => '',
				'wp_hp_autoprotect'                => 0,
				'wp_hp_debug'	                   => 0,
				'wp_hp_min_confidence'             => 70,
				'wp_hp_premium_license'            => 0,
				'wp_hp_premium_license_key'        => '',
				'wp_hp_premium_license_error'	   => 0,
				'wp_hp_premium_license_msg' 	   => 'invalid',
				'wp_hp_premium_license_last_check' => 0,
				'hp_forms'                         => array(),
				'protected_forms_ct'               => 0,
				'last_updated'                     => time(),
				'convesio_setup_complete'          => 0,
				'hide_community_nag'			   => 0,
			);
		}

		public static function get_options() {
			$options = get_option( 'wp-human-presence', array() );

			return array_merge( self::get_default_options(), $options );
		}

		public static function update_options( $options ) {
			update_option( 'wp-human-presence', $options );

			return $options;
		}

		public static function is_account_connected() {
			$options = self::get_options();
			if ( !empty( $options['wp_hp_api_key'] ) ) {
				return true;
			}

			return false;
		}

		public static function get_api_key() {
			$options = self::get_options();
			if ( !empty( $options['wp_hp_api_key'] ) ) {
				return $options['wp_hp_api_key'];
			} else {
				return self::$instance->default_api_key;
			}
		}

		/**
		 * Returns the current user's Human Presence session ID.
		 *
		 * @return String
		 */
		public static function get_session_id() {
			return ! empty( $_COOKIE['ellipsis_sessionid'] ) ? sanitize_text_field( $_COOKIE['ellipsis_sessionid'] ) : '';
		}

		/**
		 * Returns the base URL to use for checking Human Presence.
		 *
		 * @return String
		 */
		public static function check_session_url() {
			return 'https://api.humanpresence.io/v2/checkhumanpresence/' . self::get_session_id() . '?apikey=' . self::$instance->get_api_key();
		}

		/**
		 * Performs a Human Presence check for the current user's session.
		 *
		 * @return Array
		 *   An empty array in the event of an invalid request or the API response from
		 *   Human Presence including the keys:
		 *   - signal: a string representing the type of session, one of HUMAN, BOT, or
		 *     BAD_SESSION (in the event the session has not interacted with the site
		 *     enough for Human Presence to determine the type of session)
		 *   - confidence: a numeric value ranging from 0 to 100 denoting the percentage
		 *     confidence Human Presence has in its signal designation
		 */
		public static function check_session() {
			// Check Human Presence via an API request.
			$url  = self::check_session_url();
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_CUSTOMREQUEST  => 'GET'
			) );
			$response    = curl_exec( $curl );
			$status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			curl_close( $curl );

			// If we got an OK response from the API...
			if ( ! empty( $status_code ) && 200 == $status_code ) {
				// Return the data array.
				return json_decode( $response, true );
			} else {
				// Otherwise return an empty array.
				return array();
			}
		}


		/**
		 * Add custom scheduler interval
		 *
		 * @param array $schedules
		 *
		 * @return array $schedules
		 */
		public static function isa_add_cron_recurrence_interval( $schedules ) {
			$schedules['human_presence_every_fifteen_minutes'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 Minutes', 'wp-human-presence' )
			);

			return $schedules;
		}

		/**
		 * Process activation events
		 *
		 * @param String $type - ['in' || 'un'] Activation event type
		 *
		 * @return Void
		 */
		private static function activation_event( $type ) {
			$domain  = parse_url( get_site_url() )['host'];
			$partner = apply_filters( 'human_presence_partner', '' );
			$channel = 'convesio' === $partner ? 'Convesio' : 'Wordpress';
			$apiBase = 'https://a.humanpresence.app';
			if ( 'in' === $type ) {
				$url = $apiBase . '/in?h=' . $domain . '&c=' . $channel;
			} elseif ( 'un' === $type ) {
				$url = $apiBase . '/un?h=' . $domain . '&c=' . $channel . '&a=' . self::get_api_key();
			}
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST  => 'POST'
			) );
			$response = curl_exec( $curl );
			curl_close( $curl );
		}

		/**
		 * Set defaults on activation.
		 */
		public static function activate() {
			// Process activation event
			self::activation_event( 'in' );

			// Create options
			$default_options = self::get_default_options();
			$options         = self::get_options();
			$options         = array_merge( $default_options, $options );
			self::update_options( $options );

		}

		/**
		 * Clear events on deactivation.
		 */
		public static function deactivate() {
			wp_clear_scheduled_hook( 'human_presence_autoprotect_scan' );
			// Process activation event
			self::activation_event( 'un' );
		}

		/**
		 * Delete the options on uninstall.
		 */
		public static function uninstall() {

			delete_option( 'wp-human-presence' );

		}

		/**
		 * Upgrade events.
		 *
		 * @since 3.4.6
		 *
		 * @return void
		 */
		public static function upgrade() {
			$options = self::$instance->get_options();

			// Check current version of plugin stored in db. 
			// If not equal to plugin version, run upgrade hook and update version in db
			if( $options['hp_db_version'] !== self::$instance->version ) {
				add_action( 'wp_loaded', function() use ($options) { do_action('hp_upgrade', $options); } );
				$options['hp_db_version'] = self::$instance->version;
				self::$instance->update_options( $options );
			}

			// Run specific version update routines
			if ( ! get_option( 'wp-human-presence-upgrade-3-4-6' ) ) {
				if ( ( isset( $options['wp_hp_min_confidence'] ) && $options['wp_hp_min_confidence'] > 70 ) || ! isset( $options['wp_hp_min_confidence'] ) ) {
					$options['wp_hp_min_confidence'] = 70;
					self::$instance->update_options( $options );
				}
				update_option( 'wp-human-presence-upgrade-3-4-6', 1 );
			}
		}

		public static function schedule_events() {
			// Scan for unprotected forms every 15 minutes
			if ( ! wp_next_scheduled( 'human_presence_autoprotect_scan' ) ) {
				wp_clear_scheduled_hook( 'hp_autoprotect_scan' ); // Clear deprecated name space event
				wp_clear_scheduled_hook( 'hpres_autoprotect_scan' ); // Clear deprecated name space event
				wp_schedule_event( time(), 'human_presence_every_fifteen_minutes', 'human_presence_autoprotect_scan' );
			}
		}

	}

}


/**
 * Loads a single instance of HumanPresence
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $human_presence = human_presence(); ?>
 *
 * @since 3.2.0
 *
 * @see HumanPresence::get_instance()
 *
 * @return object Returns an instance of the HumanPresence class
 */
if ( ! function_exists( 'human_presence' ) ) {
	function human_presence() {
		return HumanPresence::get_instance();
	}

	$human_presence = human_presence();
}
