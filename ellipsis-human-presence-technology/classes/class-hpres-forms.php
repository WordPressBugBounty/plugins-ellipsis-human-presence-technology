<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceForms {

	/**
	 * If auto-protection is on, get and protect all forms with HP
	 *
	 * @return Void
	 */
	public static function init() {

	}

	/**
	 * Save configuration to enable/disable forms to be protected with HP
	 *
	 * @param String $fid - Form ID to enable/disable
	 * @param Integer $enabled - Boolean Int (1 === enabled)
	 * @param Boolean $unattended - If unattended, don't output HTML on enabling forms
	 *
	 * @return Void
	 */
	public static function enable_forms( $fid, $enabled, $unattended = false ) {
		$options                     = human_presence()->get_options();
		$hp_forms                    = isset( $options['hp_forms'] ) ? $options['hp_forms'] : array();
		$hp_comm_forms_limit_reached = isset( $options['protected_forms_ct'] ) ? $options['protected_forms_ct'] > 0 : false;

		if ( ( isset( $options['wp_hp_premium_license'] ) && 1 == $options['wp_hp_premium_license'] ) || ! $enabled || ( $enabled && ! $hp_comm_forms_limit_reached ) ) {
			// Handle unprotect request or premium license request to protect more than 1 form
			if ( isset( $hp_forms[ $fid ] ) ) {
				// Form object already exists
				$hp_forms[ $fid ]['form_enabled'] = $enabled;
			} else {
				// Create a new form object for this form
				$hp_forms[ $fid ] = array(
					'form_enabled' => $enabled
				);
			}

			// Store form enabled setting in wp_options
			$options['hp_forms'] = $hp_forms;
			human_presence()->update_options( $options );
			// Update the protected forms count
			self::get_protected_forms_ct();
			if ( ! $unattended ) {
				// return success message
				// disable success message for now as it's not used
				// echo json_encode( array( 'success' => true, 'form_id' => esc_html( $fid ) ) );
			}
		}
	}

	/**
	 * Get a count of all protected forms
	 *
	 * @return Integer
	 */
	public static function get_protected_forms_ct() {
		$hp_forms_idx = 1;
		$options      = human_presence()->get_options();
		if ( isset( $options['hp_forms'] ) && is_array( $options['hp_forms'] ) ) {
			$protected_forms_ct = 0;
			$hp_forms_len       = count( $options['hp_forms'] );
			foreach ( $options['hp_forms'] as $hp_form ) {
				if ( $hp_form['form_enabled'] ) {
					// Increment the count for protected forms
					$protected_forms_ct ++;
				}
				if ( $hp_forms_idx === $hp_forms_len ) {
					// Store the count
					$options['protected_forms_ct'] = $protected_forms_ct;
					human_presence()->update_options( $options );
				}
				$hp_forms_idx ++;
			}
		}

		return $hp_forms_idx;
	}

	/**
	 * Get form submissions count
	 *
	 * @param String $fid - hp_forms id
	 * @param Integer $human_ct - Number of valid form entries
	 * @param Boolean $no_ct_tracked - When a form builder doesn't track submissions
	 *
	 * @return String (HTML)
	 */
	public static function get_form_submissions_ct( $fid, $human_ct, $no_ct_tracked = false ) {
		$options = get_option( 'wp-human-presence' );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) ) {
			$recorded_human_ct   = $no_ct_tracked ? 'N/A' : $human_ct;
			$recorded_suspicious = 0;
			if ( isset( $options['hp_forms'][ $fid ]['suspicious_ct'] ) ) {
				$recorded_suspicious = ( null == $options['hp_forms'][ $fid ]['suspicious_ct'] ) ? 0 : $options['hp_forms'][ $fid ]['suspicious_ct'];
			}
			$recorded_total = $no_ct_tracked ? 'N/A' : ( $human_ct + $recorded_suspicious );

			return '<div class="label-row text-center row"><div class="col-lg-4"><span class="label label-success"><span class="fa fa-check"></span> <span>Human</span> <span class="badge">' . $recorded_human_ct . '</span></span></div><div class="col-lg-4"><span class="label label-danger"><span class="fa fa-exclamation-circle"></span> <span>Suspicious</span> <span class="badge">' . $recorded_suspicious . '</span></span></div><div class="col-lg-4"><span class="label label-default"><span class="fa fa-balance-scale"></span> <span>Total</span> <span class="badge">' . $recorded_total . '</span></span></div></div>';
		}
	}

	/**
	 * Get latest form activity
	 *
	 * @param String $fid - hp_forms id
	 * @param String $last_entry - Date/Time string of the last human/valid form entry
	 *
	 * @return String
	 */
	public static function get_latest_form_activity( $fid, $last_entry ) {
		$options = get_option( 'wp-human-presence' );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) ) {
			$last_suspicious_activity = isset( $options['hp_forms'][ $fid ]['last_suspicious_activity'] ) ? $options['hp_forms'][ $fid ]['last_suspicious_activity'] : null;
			if ( null != $last_suspicious_activity ) {
				if ( $last_suspicious_activity > $last_entry ) {
					// If suspicious activity is the latest
					return HumanPresenceUtils::time_ago( $last_suspicious_activity );
				} else {
					// Otherwise show the latest human activity
					return HumanPresenceUtils::time_ago( $last_entry );
				}
			} else {
				// Otherwise show the latest human activity if there is any
				return ( null != $last_entry ) ? HumanPresenceUtils::time_ago( $last_entry ) : 'never';
			}
		}
	}

	/**
	 * Build the table data for the forms.
	 *
	 * @return array
	 */
	public static function forms_list() {

		$forms_list = apply_filters( 'humanpresence_forms_list', array() );

		$options = human_presence()->get_options();

		// Get a count of all initially protected forms
		if ( isset( $options['hp_forms'] ) && ! array_key_exists( 'protected_forms_ct', $options ) ) {
			self::get_protected_forms_ct();
		}

		return $forms_list;
	}

	/**
	 * Makes form validation fail if Human Presence is not 100% confident.
	 *
	 * @return Boolean
	 */
	public static function fail_form_validation() {
		$options = human_presence()->get_options();
		// Fetch the HP check response.
		$response = human_presence()->check_session();
		// If response is not sure the user is human or the confidence is below the desired threshold, fail the detection check.
		$failed = false;
		$latest_submissions = !empty( $options['latest_submissions'] ) ? $options['latest_submissions'] : [];
		if ( empty( $response ) || ! isset( $response['signal'] ) || ! isset( $response['confidence'] ) ) {
			$failed = true;
		} else {
			if ( 'HUMAN' !== $response['signal'] ) {
				$failed = true;
			}
			if ( $response['confidence'] < $options['wp_hp_min_confidence'] ) {
				$failed = true;
			}
		}
		if( ( $options['wp_hp_debug'] ) ){
			if( count($latest_submissions) >= HUMAN_PRESENCE_LOG_LIMIT ) {
				array_pop($latest_submissions);
			}
			array_unshift($latest_submissions, array(
				'request'  => $_POST,
				'response' => $response,
				'session_id' => @$_COOKIE['ellipsis_sessionid'],
				'failed_validation' => $failed ? 'Yes' : 'No',
			));
			$options['latest_submissions'] = $latest_submissions;
			human_presence()->update_options( $options );
		}
		return $failed;
	}

	/**
	 * Record suspicious form activity
	 *
	 * @param String $fid - hp_forms id
	 *
	 * @return Void
	 */
	public static function handle_suspicious_form_activity( $fid ) {
		$options = human_presence()->get_options();
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) ) {
			// Increment suspicious activity
			$options['hp_forms'][ $fid ]['suspicious_ct'] = ( null == $options['hp_forms'][ $fid ]['suspicious_ct'] ) ? 1 : ++ $options['hp_forms'][ $fid ]['suspicious_ct'];
			// Save the timestamp
			$options['hp_forms'][ $fid ]['last_suspicious_activity'] = gmdate( 'Y-m-d H:i:s' );
			human_presence()->update_options( $options );
		}
	}

	/**
	 * Returns the failed form validation error message.
	 *
	 * @return String
	 */
	public static function fail_form_error_message() {
		return apply_filters( 'humanpresence_fail_validation_message', 'Sorry, we could not process your submission at this time. Please try again later.' );
	}

	/**
	 * Show admin notice for forms in protected forms table
	 *
	 * @param String $fid - Form ID to enable/disable
	 * @param Integer $enabled - Boolean Int (1 === enabled)
	 * @param String $alert_type - success/warning class for enable/disable form notices
	 *
	 * @return Void
	 */
	public static function show_protected_forms_admin_notice( $fid, $enabled, $alert_type ) {
		$options                     = human_presence()->get_options();
		$current_action              = $enabled ? 'enable' : 'disable';
		$hp_comm_forms_limit_reached = isset( $options['protected_forms_ct'] ) ? $options['protected_forms_ct'] > 0 : false;
		if ( ( isset( $options['wp_hp_premium_license'] ) && 1 == $options['wp_hp_premium_license'] ) || ! $enabled || ( $enabled && ! $hp_comm_forms_limit_reached ) ) {
			// Handle unprotect request or premium license request to protect more than 1 form
			HumanPresenceUtils::show_wp_admin_notice( $current_action . 'd form #' . $fid, 'notice-' . $alert_type . ' is-dismissible' );
		} else {
			// Handle community plan forms limit reached
			HumanPresenceUtils::show_wp_admin_notice( 'Community version limit reached. Protecting more than one form requires a premium license.', 'notice-error is-dismissible' );
		}
	}

}
