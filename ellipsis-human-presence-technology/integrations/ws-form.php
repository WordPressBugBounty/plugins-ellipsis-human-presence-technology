<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceWSFormIntegration extends HumanPresenceIntegration {
	public static $form_prefix = 'wsf';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 50, 1 );
		add_filter( 'wsf_action_humanpresence_check', array( __CLASS__, 'validate_forms' ), 10, 1 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 50, 1 );
	}

	/*
	 * Get WS Form forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( class_exists( 'WS_Form_Form' ) ) {
			$additional_forms = array();
			$ws_form_form     = new WS_Form_Form();
			$wsform_form_list = $ws_form_form->db_read_all( '', 'NOT status="trash"', 'label', '', '', false );
      
			if ( is_array( $wsform_form_list ) ) {
				foreach ( $wsform_form_list as $form ) {
					$form_id = is_array( $form ) && isset( $form['id'] ) ? $form['id'] : $form->id;
					$fid     = self::get_form_id( $form_id );
					// Get current form entries count
					$entries_count = $form['count_submit'];
					// Get last entry date
					$ws_form_submit        = new WS_Form_Submit();
					$last_entry            = $ws_form_submit->db_read_all( '', sprintf( 'form_id=%u', $form_id ), '', 'date_added DESC', '1', '', false, false, true );

					$last_entry_date_added = ( is_array( $last_entry ) && ( count( $last_entry ) > 0 ) ) ? $last_entry[0]->date_added : null;
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => $form['label'],
						'type'        => 'WS Form',
						'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, $entries_count ),
						'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $last_entry_date_added )
					);
				}
			}
			self::prune_disabled_forms( $additional_forms );
			$forms_list = array_merge( $forms_list, $additional_forms );
		}

		return $forms_list;
	}

	/**
	 * Validate WS Form
	 *
	 * @param Int $form_id - WS Form form ID
	 *
	 * @return Array - validation_failed, confidence
	 */
	public static function validate_forms( $form_id ) {
		$return_array = array( 'validation_failed' => false, 'confidence' => false );
		$fid          = self::get_form_id( $form_id );
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		if ( is_array( $options ) && isset( $options['hp_forms'] ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				// Fetch the HP check response.
				$response = human_presence()->check_session();
				// If response is not sure the user is human or the confidence is below the desired threshold,
				// fail the detection check.
				$validation_failed                 = ( ! empty( $response ) && 'HUMAN' !== $response['signal'] || $response['confidence'] < $options['wp_hp_min_confidence'] );
				$return_array['validation_failed'] = $validation_failed;
				$return_array['confidence']        = $response['confidence'];
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
				}
			}
		}

		return $return_array;
	}

	/*
	 * Protect Existing WS Form forms
	 */
	public static function enable_forms( $unattended ) {
		if ( class_exists( 'WS_Form_Form' ) ) {
			$ws_form_form     = new WS_Form_Form();
			$wsform_form_list = $ws_form_form->db_read_all( '', 'NOT status="trash"', '', 'label', '', '', false );
			if ( is_array( $wsform_form_list ) ) {
				foreach ( $wsform_form_list as $form ) {
					$form_id = is_array( $form ) && isset( $form['id'] ) ? $form['id'] : $form->id;
					$fid     = self::get_form_id( $form_id );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceWSFormIntegration(); });
