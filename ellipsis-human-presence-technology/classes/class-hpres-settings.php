<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}


/**
 * Class HumanPresenceSettings
 */
class HumanPresenceSettings {

	public function __construct( $is_partner = false ) {
		$this->_hooks( $is_partner );
	}

	public function _hooks( $is_partner = false ) {
		add_action( 'wp_ajax_hpres_form_enabled_change', array( __CLASS__, 'form_enabled_change' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_forms_scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'process_save_settings' ) );
		if ( ! $is_partner ) {
			// Temporarily disable the premium license check. This only really checks for expired licenses
			// since license activations are handled in the license key saving function
			// add_action( 'admin_init', array( __CLASS__, 'premium_license_check' ) );
		}
		add_action( 'admin_init', array( __CLASS__, 'display_community_upgrade_banner' ) );
		add_action( 'hp_upgrade', array( __CLASS__, 'reenable_community_upgrade_banner') );
		add_action( 'admin_init', array( __CLASS__, 'download_debug_report' ) );
	}

	public static function options_page( $hook ) {

		// Handle invalid permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		// Run auto protect before showing settings.
		// If auto protect is disabled, this will continue silently without protecting
		HumanPresenceAutoProtect::init();

		$options = human_presence()->get_options();
		// Render options page UI
		require_once( human_presence()->plugin_dir . '/templates/options-page-wrapper.php' );

	}

	public static function process_save_settings() {

		if ( ! isset( $_GET['page'] ) || ( isset( $_GET['page'] ) && 'wp-human-presence' !== sanitize_text_field( $_GET['page'] ) ) ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		// Handle invalid permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( !empty( $_GET['hide_community_nag'] ) ) {
			self::hide_community_upgrade_banner();
		}

		if ( ! empty( $_POST ) && check_admin_referer( isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'humanpresence_account', 'humanpresence_nonce' ) ) {

			// Process license activation form
			if ( isset( $_GET['action'] ) && 'humanpresence_license_activation' == sanitize_text_field( $_GET['action'] ) ) {
				$hidden_field = isset( $_POST['hpres_license_activation_change_submitted'] ) ? sanitize_text_field( $_POST['hpres_license_activation_change_submitted'] ) : '';
				if ( 'Y' == $hidden_field ) {
					// Validate the license key submitted
					$license_key_validation_failed = isset( $_POST['license-key'] ) ? self::license_key_validate( sanitize_text_field( $_POST['license-key'] ) ) : true;
					if ( ! $license_key_validation_failed ) {
						// Save license key change
						self::handle_license_key_changes( sanitize_text_field( $_POST['license-key'] ) );
					}
				}
			}

			// Process community upgrade banner form
			if ( isset( $_GET['action'] ) && 'human_presence_community_join' == sanitize_text_field( $_GET['action'] ) ) {
				$error = false;
				$options = [
					'body'		=> [
						'email' => sanitize_text_field( $_REQUEST['hp_community_upgrade_email'] ),
					],
					'headers'	=> [],

				];
				$response = wp_remote_post( 'https://www.humanpresence.io/?action=wp_hp_community_upgrade_join', $options );
				if( !is_wp_error( $response ) ) {
					$status = json_decode( $response['body'] );
					if( $status->status == 'success' ) {
						$error = false;
					} else {
						$error = true;
					}
				} else {
					$error = true;
				}
				if( $error ) {
					HumanPresenceUtils::show_wp_admin_notice( 'Hmmm. Something went wrong when we tried to send you your upgrade discount code. Please try again.', 'notice-success is-dismissible', true );
				} else {
					self::hide_community_upgrade_banner( true );
					HumanPresenceUtils::show_wp_admin_notice( 'Awesome! Check your email for your upgrade discount code.', 'notice-success is-dismissible', true );
				}
			}

			if ( ! human_presence()->is_convesio() ) {

				// On disconnect...
				if ( isset( $_GET['action'] ) && 'humanpresence_logout' == sanitize_text_field( $_GET['action'] ) ) {
					self::logout();
					wp_redirect( add_query_arg( 'action', false, human_presence()->admin_url ) );
					die();
				}

			}

			/* Settings form submission */
			if ( isset( $_GET['action'] ) && 'humanpresence_change_settings' == sanitize_text_field( $_GET['action'] ) ) {
				$hidden_field = isset( $_POST['hpres_settings_change_submitted'] ) ? sanitize_text_field( $_POST['hpres_settings_change_submitted'] ) : false;
				if ( 'Y' == $hidden_field ) {
					// Validate autoprotect status submitted
					$autoprotect_change            = isset( $_POST['autoprotect'] ) ? sanitize_text_field( $_POST['autoprotect'] ) : 0;
					$debug_change            	   = isset( $_POST['debug'] ) ? sanitize_text_field( $_POST['debug'] ) == 1 : 0;
					$autoprotect_validation_failed = self::autoprotect_validate( $autoprotect_change );
					// Validate the minimum confidence submitted
					$min_confidence_validation_failed = isset( $_POST['minimal_confidence'] ) ? self::minimal_confidence_validate( sanitize_text_field( $_POST['minimal_confidence'] ) ) : '';
					if ( ! $min_confidence_validation_failed && ! $autoprotect_validation_failed ) {
						// Save minimal confidence change
						self::handle_settings_changes( $_POST );
					}
				}
			}

		}

	}

	public static function enqueue_forms_scripts() {
		// Register the ajax script to enable forms checkbox functionality
		wp_register_script( 'hpres_ajax', human_presence()->plugin_url . 'js/ajax-settings.js', array( 'jquery' ), human_presence()->version );
		// Localize the script for DOM with new data
		$options    = human_presence()->get_options();
		$ajaxConfig = array(
			'url'              => admin_url( 'admin-ajax.php' ),
			'isPremium'        => isset( $options['wp_hp_premium_license'] ),
			'protectedFormsCt' => isset( $options['protected_forms_ct'] ) ? $options['protected_forms_ct'] : 0
		);
		wp_localize_script( 'hpres_ajax', 'ajaxConfig', $ajaxConfig );
		// Enqueue script with localized data.
		wp_enqueue_script( 'hpres_ajax' );
	}

	public static function logout() {
		$options = human_presence()->get_default_options();
		human_presence()->update_options( $options );
	}

	public static function premium_license_check() {
		$license         = get_transient( 'human_presence_license' );
		$options         = human_presence()->get_options();
		$hp_mismatch_msg = 'License Mismatch! Your Human Presence license key doesn\'t match your current domain. This is most likely due to a change in the domain URL. Please deactivate the license and then reactivate it. <a class="button-primary danger" style="margin-left: 10px;" href="' . admin_url('admin.php?page=wp-human-presence') . '#hp-license-modal-open">Reactivate License</a>';

		$license_error_msg = 'You have an invalid or expired license key for Human Presence. Please go to the <a href="' . admin_url('admin.php?page=wp-human-presence') . '#hp-license-modal-open">Licenses Page</a> to correct this issue.';
		// If the license is not set, run the check and set the license again.
		if ( false === $license || !empty( $_REQUEST['hp_force_license_check'] ) ) {
			// Check for premium license
			$license_key = $options['wp_hp_premium_license_key'];
			if ( ! $license_key && empty( $_REQUEST['hp_force_license_check'] ) ) {
				$hp_license_check = [
					'success' => false,
					'license' => 'invalid',
				];
			} else {
				$license_ids = array(
					'premium' => '1950',
					'enterprise' => '52390',
					'custom' => '51915',
				);
				$activation_success = false;
				foreach ( $license_ids as $license_id ) {
					$hp_license_check = self::remote_license_check( $license_key, $license_id );

					if ( $hp_license_check['success'] && 'valid' == $hp_license_check['license'] ) {
						$activation_success = true;
					}

					if ( $activation_success ) {
						break;
					}

				}
				
			}

			$options['wp_hp_premium_license_last_check'] = time();
			
			if ( ( ! $hp_license_check['success'] || 'valid' != $hp_license_check['license'] ) && $license_key ) {
				// Handle license check error
				HumanPresenceUtils::show_wp_admin_notice( self::get_displayable_license_message( $hp_license_check['license'] ), 'notice-error license-mismatch', true );
				// Show upgrade options & mismatch message upon finding an invalid license
				$options['wp_hp_premium_license_error']    = 1;
				$options['wp_hp_premium_license']          = '';
				$options['wp_hp_premium_license_msg']	   = $hp_license_check['license'];
			} else {
				// If we have an active license, never do this check more than once per hour.
				set_transient( 'human_presence_license', $hp_license_check, HOUR_IN_SECONDS );
			}

			human_presence()->update_options( $options );

		} else {
			// Check for past license mismatch
			if ( array_key_exists( 'wp_hp_premium_license_error', $options ) && 1 === $options['wp_hp_premium_license_error'] ) {
				$hp_license_check = [
					'success' => false,
					'license' => 'invalid',
				];
				HumanPresenceUtils::show_wp_admin_notice( self::get_displayable_license_message( $hp_license_check['license'] ), 'notice-error license-mismatch is-dismissible', true );
			}
		}
	}

	/**
	 * Get human readable message string for license status
	 *
	 * @since 3.4.27
	 *
	 * @return string
	 */
	public static function get_displayable_license_message( $license_msg ) {
		$msg = '';
		switch ( $license_msg ) {
			case 'item_name_mismatch':
			case 'key_mismatch':
				$msg = 'Your Human Presence license is not valid for the pro version.';
				break;
			case 'disabled':
				$msg = 'Your Human Presence license has been revoked.';
				break;
			case 'expired':
				$msg = 'Your Human Presence license has expired.';
				break;
			case 'invalid_item_id':
				$msg = 'You have an invalid or expired license key for Human Presence.';
				break;
			case 'site_inactive':
				$msg = 'License Mismatch! Your Human Presence license key doesn\'t match your current domain. This is most likely due to a change in the domain URL.';
				break;
			default:
				$msg = 'You have an invalid or expired license key for Human Presence.';
		}

		$msg .= ' Please go to the <a href="' . admin_url('admin.php?page=wp-human-presence') . '#hp-license-modal-open">Licenses Page</a> to correct this issue.';
		return $msg;
	}

	/**
	 * Validate the license key value requested
	 *
	 * @param string $license_key - Acceptable license key value requested, 32-character alpha numeric string
	 *
	 * @return Boolean - Whether or not the license key validation failed
	 */
	public static function license_key_validate( $license_key ) {
		$validation_failed = false;
		if ( '' == $license_key ) {
			// Handle empty field
			HumanPresenceUtils::show_wp_admin_notice( 'License key cannot be empty.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( preg_match( '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $license_key ) ) {
			// Handle special characters
			HumanPresenceUtils::show_wp_admin_notice( 'License key should be an alpha numeric string.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( 32 != strlen( $license_key ) ) {
			// Handle invalid length
			HumanPresenceUtils::show_wp_admin_notice( 'License key should be 32 characters.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		}

		return $validation_failed;
	}


	/**
	 * Check license key on remote server for given license_id
	 *
	 * @since 3.4.27
	 * 
	 * @param string $license_key - Acceptable license key value requested, 32-character alpha numeric string
	 * @param int $license_id - Acceptable license id for product in EDD
	 *
	 * @return array
	 */
	public static function remote_license_check( $license_key, $license_id ) {
		$check_license_url = 'https://www.humanpresence.io?edd_action=check_license&item_id=' . $license_id . '&license=' . urlencode( $license_key ) . '&url=' . get_site_url();
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $check_license_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'GET'
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
		$hp_license_check = json_decode( $response, true );
		return $hp_license_check;

	}

	/**
	 * Activate license key on remote server for given license_id
	 *
	 * @since 3.4.27
	 * 
	 * @param string $license_key - Acceptable license key value requested, 32-character alpha numeric string
	 * @param int $license_id - Acceptable license id for product in EDD
	 *
	 * @return array
	 */
	public static function remote_license_activation( $license_key, $license_id ) {
		$activate_license_url = 'https://www.humanpresence.io?edd_action=activate_license&item_id=' . $license_id . '&license=' . urlencode( $license_key ) . '&url=' . get_site_url();
		$curl                 = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $activate_license_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'GET'
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
		$hp_license_activation = json_decode( $response, true );
		return $hp_license_activation;
	}

	/**
	 * Save HP configuration changes
	 *
	 * @return Void
	 */
	public static function handle_license_key_changes( $license_key ) {
		// Attempt to activate the license key on the requesting site
		$license_ids = array(
			'premium' => '1950',
			'enterprise' => '52390',
			'custom' => '51915',
		);
		$activation_success = false;
		foreach ( $license_ids as $license_id ) {
			
			$hp_license_activation = self::remote_license_activation( $license_key, $license_id );

			// Handle connectivity error
			if ( null === $hp_license_activation ) {
				HumanPresenceUtils::show_wp_admin_notice( 'Uh oh, please check your internet connection and try again', 'notice-error is-dismissible', true );
				return;
			}

			if ( array_key_exists( 'success', $hp_license_activation ) && $hp_license_activation['success'] ) {
				// Store premium license key in wp_options
				$options                                   = human_presence()->get_options();
				$options['wp_hp_premium_license']          = 1;
				$options['wp_hp_premium_license_key']      = $license_key;
				$options['wp_hp_premium_license_error']    = 0;
				$options['wp_hp_premium_license_msg']	   = 'valid';
				$options['wp_hp_email'] 				   = $hp_license_activation['customer_email'];
				$options['wp_hp_api_key'] 				   = $hp_license_activation['hp_api_key'];
				human_presence()->update_options( $options );
				$activation_success = true;
			} else {
				$activation_success = false;
			}
			if ( $activation_success ) {
				break;
			}

		}

		if( $activation_success ) {
			// Alert the user on success
			HumanPresenceUtils::show_wp_admin_notice( 'Human Presence premium license key activated successfully.', 'notice-success is-dismissible', true );

		} else {
			// Handle license activation error
			if ( !empty( $hp_license_activation ) && !empty( $hp_license_activation['error'] ) ) {
				switch ( $hp_license_activation['error'] ) {
					case 'missing':
						$error_message = "License doesn't exist.";
						break;
					case 'disabled':
						$error_message = "License key revoked";
						break;
					case 'no_activations_left':
						$error_message = "No activations left.";
						break;
					case 'expired':
						$error_message = "License has expired.";
						break;
					case 'key_mismatch':
						$error_message = "License is not valid for this product.";
						break;
					default:
						$error_message = 'The license key provided is invalid.';
				}
				HumanPresenceUtils::show_wp_admin_notice( 'There was a problem activating your Human Presence premium license key for this website. Error: ' . $error_message, 'notice-error is-dismissible', true );

			} else {
				HumanPresenceUtils::show_wp_admin_notice( 'There was a problem activating your Human Presence premium license key for this website. The license key provided is invalid.', 'notice-error is-dismissible', true );
			}
		}
	}

	/**
	 * WordPress HP Ajax Callback
	 *
	 * @return JSON response
	 */
	public static function form_enabled_change() {
		if ( isset( $_POST['humanpresence_ajax_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['humanpresence_ajax_nonce'] ), 'humanpresence_form_enabled_change' ) ) {
			// Implement ajax function here
			$fid             = isset( $_POST['fid'] ) ? sanitize_text_field( $_POST['fid'] ) : '';
			$hp_form_enabled = isset( $_POST['formEnabled'] ) ? sanitize_text_field( $_POST['formEnabled'] ) : '';
			if ( is_string( $fid ) && is_string( $hp_form_enabled ) ) {
				// Enable the form to be protected
				HumanPresenceForms::enable_forms( $fid, $hp_form_enabled );
				wp_die();
			}
		} else {
			wp_die( 'Something went wrong. Try again later.' );
		}
	}

	/**
	 * Render HP Enable Forms Checkbox
	 *
	 * @return String, (HTML)
	 */
	public static function render_hp_fm_enabled_cb( $fid ) {
		$options         = human_presence()->get_options();
		$hp_form_enabled = array_key_exists( $fid, $options['hp_forms'] ) ? $options['hp_forms'][ $fid ]['form_enabled'] : 0;
		$checked         = ( ( null == $hp_form_enabled ) || ( 0 == $hp_form_enabled ) ) ? '' : 'checked';
		$disabled        = isset( $options['wp_hp_autoprotect'] ) && 1 === $options['wp_hp_autoprotect'] ? 'disabled' : '';

		return '<div class="check-toggle-container"><label class="check-toggle-label"></label><div class="check-toggle"><input class="hp-fm-enable-cb" id="hp-fm-enable-' . $fid . '" type="checkbox"' . $checked . ' ' . $disabled . '/><label for="hp-fm-enable-' . $fid . '"></label><span></span></div></div>';
	}

	/**
	 * Validate the autoprotect status value requested
	 *
	 * @param string $autoprotect - Acceptable autoprotect status requested, number 0 or 1
	 *
	 * @return Boolean - Whether or not the autoprotect status validation failed
	 */
	public static function autoprotect_validate( $autoprotect ) {
		$options           = human_presence()->get_options();
		$validation_failed = false;
		if ( ! human_presence()->is_premium() && 1 == $autoprotect ) {
			// Handle non-premium user request
			HumanPresenceUtils::show_wp_admin_notice( 'Automatic protection of all forms requires a premium license.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( ! is_numeric( $autoprotect ) ) {
			// Handle non-number
			HumanPresenceUtils::show_wp_admin_notice( 'Auto-protect status should be either 0 or 1.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( $autoprotect > 1 ) {
			// Handle invalid number
			HumanPresenceUtils::show_wp_admin_notice( 'Auto-protect status should not be higher than 1.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		}

		return $validation_failed;
	}

	/**
	 * Validate the minimal confidence value requested
	 *
	 * @param string $minimal_confidence - Acceptable minimum confidence level requested, number between 0 - 100
	 *
	 * @return Boolean - Whether or not the minimal confidence validation failed
	 */
	public static function minimal_confidence_validate( $minimal_confidence ) {
		$validation_failed = false;
		if ( '' == $minimal_confidence ) {
			// Handle empty field
			HumanPresenceUtils::show_wp_admin_notice( 'Minimal confidence value required.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( ! is_numeric( $minimal_confidence ) ) {
			// Handle non-number
			HumanPresenceUtils::show_wp_admin_notice( 'Minimal value should be a number.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		} elseif ( $minimal_confidence < 0 || $minimal_confidence > 100 ) {
			// Handle invalid number
			HumanPresenceUtils::show_wp_admin_notice( 'Minimal value should be at least 0 and at most 100.', 'notice-error is-dismissible', true );
			$validation_failed = true;
		}

		return $validation_failed;
	}

	/**
	 * Save HP configuration changes
	 *
	 * @param array $posted_data - An array of $_POST data
	 *
	 * @return Void
	 */
	public static function handle_settings_changes( $posted_data ) {

		// Store form enabled setting in wp_options
		$options                         = human_presence()->get_options();
		$options['wp_hp_autoprotect']    = isset( $posted_data['autoprotect'] ) ? absint( sanitize_text_field( $posted_data['autoprotect'] ) ) : 0;
		$options['wp_hp_debug']    		 = isset( $posted_data['debug'] ) ? absint( sanitize_text_field( $posted_data['debug'] ) ) === 1 : 0;
		$options['wp_hp_min_confidence'] = isset( $posted_data['minimal_confidence'] ) ? absint( sanitize_text_field( $posted_data['minimal_confidence'] ) ) : $options['wp_hp_min_confidence'];
		human_presence()->update_options( $options );
		// If auto-protection is on, get and protect all forms
		HumanPresenceAutoProtect::init();
		// Alert the user on success
		HumanPresenceUtils::show_wp_admin_notice( 'Human Presence settings changes saved successfully.', 'notice-success is-dismissible', true );

	}

	/**
	 * Conditionally show Community Upgrade Banner
	 *
	 * @since 3.4.27
	 * 
	 * @return Void
	 */
	public static function display_community_upgrade_banner() {
		if( human_presence()->is_community() ) {
			$options = human_presence()->get_options();
			if( $options['hide_community_nag'] == 0 && !wp_doing_ajax() ) {
				HumanPresenceUtils::show_wp_admin_notice( '<img class="hp-community-upgrade-banner-logo" src="' . esc_url( human_presence()->plugin_url . 'images/hp-shield.svg' ) . '" alt="Human Presence Logo"/><div class="hp-community-upgrade-banner-content-wrap"><h2>Get 20% Off Your First Year of Human Presence Pro!</h2><p>Protect unlimited forms on your WordPress website at a discount. You can even apply it to our agency packages!</p><form action="' . esc_url( add_query_arg( 'action', 'human_presence_community_join', human_presence()->admin_url ) ) . '" method="post" name="human_presence_community_upgrade">' . wp_nonce_field('human_presence_community_join', 'humanpresence_nonce') . '<input type="email" name="hp_community_upgrade_email" placeholder="Enter your email address" /><input type="submit" class="hp-community-upgrade-banner-submit" value="Get Started" /></form><a class="dismiss" href="' . admin_url('admin.php?page=wp-human-presence&hide_community_nag=1') . '">Dismiss</a></div><div class="clearfix"></div>', 'notice-warning hp-community-upgrade-banner', true, false );
			}
		}
	}

	public static function hide_community_upgrade_banner( $forever = false ) {
		$options = human_presence()->get_options();
		$options['hide_community_nag'] = $forever ? -1 : 1;
		human_presence()->update_options( $options );
	}

	public static function reenable_community_upgrade_banner() {
		$options = human_presence()->get_options();
		if( $options['hide_community_nag'] == 1 ) {
			$options['hide_community_nag'] = 0;
			human_presence()->update_options( $options );
		}
	}

	public static function download_debug_report() {
		if( !empty( $_GET['hp_debug_download'] ) && current_user_can('manage_options') ) {
			$options = human_presence()->get_options();
			ob_start();
			ob_end_clean();
			header('Content-type: text/plain');
   			header('Content-Disposition: attachment; filename="debug-report.txt"');
			echo convert_uuencode( print_r( $options, true ) );
			exit;
		}
	}

}
