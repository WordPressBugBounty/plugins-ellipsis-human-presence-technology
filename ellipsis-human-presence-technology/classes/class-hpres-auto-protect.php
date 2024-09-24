<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceAutoProtect {

	/**
	 * If auto-protection is on, get and protect all forms with HP
	 *
	 * @param Boolean $unattended - If unattended, don't output HTML on enabling forms
	 *
	 * @return Void
	 */
	public static function init( $unattended = true ) {
		$options = human_presence()->get_options();
		// Don't auto-protect if user is not premium
		if ( 1 === $options['wp_hp_autoprotect'] && human_presence()->is_premium() ) {

			do_action( 'humanpresence_before_autoprotect_forms', $unattended );
			do_action( 'humanpresence_autoprotect_forms', $unattended );
			do_action( 'humanpresence_after_autoprotect_forms', $unattended );

		}
	}

}
