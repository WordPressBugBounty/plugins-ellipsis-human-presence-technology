<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceIntegration {

	public static $form_prefix = '';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {

	}

	public static function get_form_id( $id ) {
		return static::$form_prefix . '-' . $id;
	}

	/*
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {

	}

	/*
	 * This is for the autoprotect process and should enable all forms for validation.
	 * This should be added to the humanpresence_autoprotect_forms action.
	 * This should implement the HumanPresenceForms::enable_forms() static method to enable each form
	 */
	public static function enable_forms( $unattended ) {

	}

	public static function prune_disabled_forms( array $forms_list ) {
		$options = human_presence()->get_options();
		$changed = false;
		if ( ! isset( $options['hp_forms'] ) || empty( $options['hp_forms'] ) ) {
			return;
		}

		// Loop through all the forms registered with HP to make sure they still exist in the form list provided by the plugin
		$forms = array_map( function ( $f ) {
			return $f['id'];
		}, $forms_list );
		foreach ( $options['hp_forms'] as $fid => $hp_form ) {
			// The $forms_list only contains forms from 1 integration at a time, so to be sure that we don't remove
			// forms that aren't in this integration, ensure they have the same prefix as the current integration
			// before checking if they're in the hp_forms list.
			if ( HumanPresenceUtils::startsWith( $fid, static::$form_prefix ) && ! in_array( $fid, $forms ) ) {
				$changed = true;
				unset( $options['hp_forms'][ $fid ] );
			}
		}

		// Now save the new filtered set of options
		if ( $changed ) {
			human_presence()->update_options( $options );
		}
	}
}
