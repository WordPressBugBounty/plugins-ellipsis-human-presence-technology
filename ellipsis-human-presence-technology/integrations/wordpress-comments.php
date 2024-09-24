<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceWPCommentsIntegration extends HumanPresenceIntegration {
	public static $form_prefix = 'wp';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 10, 1 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'validate_forms' ), 10, 2 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 10, 1 );
	}

	/**
	 * Get WP comments
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		$comments_list = get_comments();
		// Assign unique form id
		$fid = self::get_form_id( 'comments' );
		// Get last comment date
		$last_comment = isset( $comments_list[0] ) ? $comments_list[0]->comment_date : null;
		// Push to forms list
		$forms_list[] = array(
			'id'          => $fid,
			'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
			'name'        => 'Comments / Reviews',
			'type'        => 'WP Comments',
			'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, count( $comments_list ) ),
			'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $last_comment )
		);

		return $forms_list;
	}

	/**
	 * Validate comments
	 *
	 * @param String $id - Comment ID
	 * @param Object $comment - Comment object
	 *
	 * @return Void
	 */
	public static function validate_forms( $id, $comment ) {
		if ( 'order_note' == $comment->comment_type || is_user_logged_in()) {
			return;
		}
		// If HP protection enabled, validate new comments
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( 'comments' );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				$return_url = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( $_SERVER['HTTP_REFERER'] ) : '';
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Do not store the comment in the db
					wp_delete_comment( $id, true );
				}
			}
		}
	}

	/*
	 * This is for the autoprotect process and should enable all forms for validation.
	 * This should be added to the humanpresence_autoprotect_forms action.
	 * This should implement the HumanPresenceForms::enable_forms() static method to enable each form
	 */
	public static function enable_forms( $unattended ) {
		HumanPresenceForms::enable_forms( self::get_form_id( 'comments' ), 1, $unattended );
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceWPCommentsIntegration(); });
