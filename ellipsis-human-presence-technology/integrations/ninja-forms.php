<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

if ( class_exists( 'Ninja_Forms' ) && ( version_compare( get_option( 'ninja_forms_version', '0.0.0' ), '3', '<' ) || get_option( 'ninja_forms_load_deprecated', false ) ) ) {

	function humanpresence_ninja_forms_version_fail() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'Ninja Forms version 3 or greater is required. If you have version 3, check to ensure that all of your forms have been converted to version 3.', 'wp-human-presence' ); ?></p>
		</div>
		<?php
	}

	add_action( 'admin_notices', 'humanpresence_ninja_forms_version_fail' );

} else {

	class HumanPresenceNinjaFormsIntegration extends HumanPresenceIntegration {
		public static $form_prefix = 'nf';

		/*
		 * This is where you will add filters and actions
		 */
		public function __construct() {
			add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 50, 1 );
			add_filter( 'ninja_forms_submit_data', array( __CLASS__, 'validate_forms' ), 10, 1 );
			add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 50, 1 );

			/**
			 * Auto-protect New Ninja Forms
			 */
			add_action( 'ninja_forms_save_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
		}

		/*
		 * Get Ninja Forms
		 * This should return the forms list
		 *
		 * @return Array $forms_list
		 */
		public static function get_forms( $forms_list ) {
			if ( class_exists( 'Ninja_Forms' ) ) {
				$additional_forms = array();
				try {
					$ninja_form_list  = Ninja_Forms()->form()->get_forms();
				} catch (\Exception $e) {
					HumanPresenceUtils::show_wp_admin_notice( 'Ninja Forms is missing a required function. Check to ensure you have the latest version. <a href="https://www.humanpresence.io/support/">Contact us</a> if the issue persists.' );
					return $forms_list;
				} catch (\Throwable $e) {
					HumanPresenceUtils::show_wp_admin_notice( 'Ninja Forms is missing a required function. Check to ensure you have the latest version. <a href="https://www.humanpresence.io/support/">Contact us</a> if the issue persists.' );
					return $forms_list;
				}

				if ( is_array( $ninja_form_list ) ) {
					foreach ( $ninja_form_list as $form ) {
						$fid = self::get_form_id( $form->get_id() );
						// Get current form entries count
						$entries = Ninja_Forms()->form( $form->get_id() )->get_subs();
						// Get last entry date
						$last_entry_id = empty( $entries ) ? null : array_values( $entries )[0]->get_id(); // Entries are reverse ordered, newest listed first
						$last_entry    = ( 0 == $last_entry_id ) ? null : $entries[ $last_entry_id ]->get_sub_date( 'Y-m-d h:m:s' );
						// Push to forms list
						$additional_forms[] = array(
							'id'          => $fid,
							'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
							'name'        => $form->get_setting( 'title' ),
							'type'        => 'Ninja Forms',
							'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, count( $entries ) ),
							'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $last_entry )
						);
					}
				}
				self::prune_disabled_forms( $additional_forms );
				$forms_list = array_merge( $forms_list, $additional_forms );
			}

			return $forms_list;
		}

		/**
		 * Validate Ninja forms
		 *
		 * @param Array $form_data - Array of data from the Ninja Form submitted
		 *
		 * @return Array - $form_data
		 */
		public static function validate_forms( $form_data ) {
			// If HP protection enabled, validate the form
			$options       = human_presence()->get_options();
			$ninja_form_id = $form_data['id'];
			$fid           = self::get_form_id( $ninja_form_id );
			if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
				$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
				if ( $hp_form_enabled ) {
					// If the detection check fails, prevent the form submission and display an error message.
					$validation_failed = HumanPresenceForms::fail_form_validation();
					if ( $validation_failed ) {
						// Record suspicious activity
						HumanPresenceForms::handle_suspicious_form_activity( $fid );
						// Get error message
						$fields                                          = Ninja_Forms()->form( $ninja_form_id )->get_fields();
						$last_field_id                                   = end( $fields )->get_id();
						$form_data['errors']['fields'][ $last_field_id ] = HumanPresenceForms::fail_form_error_message();
					}
				}
			}

			return $form_data;
		}

		/*
		 * Protect Existing Ninja forms
		 */
		public static function enable_forms( $unattended ) {
			if ( class_exists( 'Ninja_Forms' ) ) {
				try {
					$ninja_form_list  = Ninja_Forms()->form()->get_forms();
				} catch (\Exception $e) {
					HumanPresenceUtils::show_wp_admin_notice( 'Ninja Forms is missing a required function. Check to ensure you have the latest version. <a href="https://www.humanpresence.io/support/">Contact us</a> if the issue persists.' );
					return;
				} catch (\Throwable $e) {
					HumanPresenceUtils::show_wp_admin_notice( 'Ninja Forms is missing a required function. Check to ensure you have the latest version. <a href="https://www.humanpresence.io/support/">Contact us</a> if the issue persists.' );
					return;
				}

				if ( is_array( $ninja_form_list ) ) {
					foreach ( $ninja_form_list as $form ) {
						$fid = self::get_form_id( $form->get_id() );
						HumanPresenceForms::enable_forms( $fid, 1, $unattended );
					}
				}
			}
		}
	}

	add_action( 'plugins_loaded', function() { new HumanPresenceNinjaFormsIntegration(); });

}
