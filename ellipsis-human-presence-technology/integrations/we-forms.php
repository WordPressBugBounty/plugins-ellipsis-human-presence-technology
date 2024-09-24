<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceWEFormsIntegration extends HumanPresenceIntegration {
	public static $form_prefix = 'wef';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 60, 1 );
		add_filter( 'weforms_before_entry_submission', array( __CLASS__, 'validate_ajax_forms' ), 10, 4 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 60, 1 );
	}

	/*
	 * Get WeForms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( method_exists( 'WeForms_Form_Manager', 'all' ) ) {
			$additional_forms = array();
			$form_list        = ( new WeForms_Form_Manager() )->all();
			if ( isset( $form_list['forms'] ) ) {
				foreach ( $form_list['forms'] as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form->get_id() );
					// Get current form entries
					$entries = $form->entries()->all();
					// Get last entry date
					$entry_ids = array();
					foreach ( $entries as $entry ) {
						$entry_ids[] = $entry->id;
					}
					$last_entry_id = ! empty( $entry_ids ) ? max( $entry_ids ) : 0;
					$last_entry    = array_key_exists( $last_entry_id, $entries ) ? $entries[ $last_entry_id ]->created : null;
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => ( $form->get_name() ),
						'type'        => 'weForms',
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
	 * Validate WeForms Ajax Submission
	 *
	 * @param Array $entry_fields - Array of submitted data fields
	 * @param Obj $form - Form Obj
	 * @param Array $form_settings - Form Settings
	 * @param Array $form_fields - Form Fields
	 *
	 * @return Array - $entry_fields
	 */
	public static function validate_ajax_forms( $entry_fields, $form, $form_settings, $form_fields ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $form->get_id() ) );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];

			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					wp_send_json( [
						'success' => false,
						'error'   => HumanPresenceForms::fail_form_error_message()
					] );
				}
			}
		}

		return $entry_fields;
	}

	/*
	 * Protect Existing WeForms
	 */
	public static function enable_forms( $unattended ) {
		if ( method_exists( 'WeForms_Form_Manager', 'all' ) ) {
			$form_list = ( new WeForms_Form_Manager() )->all();
			if ( isset( $form_list['forms'] ) ) {
				foreach ( $form_list['forms'] as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form->get_id() );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}	
		}
	}

	/**
     * Update Human Presence form enabled status from weForms form field save
     *
     * @return void
     */
    public static function update_enabled_status( $form_id, $form_fields, $form_settings ) {
        if ( class_exists( 'HumanPresenceForms' ) ) {        	
            $status = isset( $form_settings['humanpresence_enabled'] ) ? (int)$form_settings['humanpresence_enabled'] : 0;
            $options = human_presence()->get_options();
            // Disable auto-protect if user wants to disable a form
            if ( 1 === $options['wp_hp_autoprotect'] && !$status ) {
                $options['wp_hp_autoprotect'] = 0;
                human_presence()->update_options( $options );
            }

            HumanPresenceForms::enable_forms( self::get_form_id( $form_id ), $status, true );
        }
    }

    /**
     * Update Human Presence form enabled status from weForms form field save
     *
     * @return void
     */
    public static function filter_settings_enabled_status( $settings, $form_id ) {
    	$options = human_presence()->get_options();
    	if ( isset( $options['hp_forms'] ) ) {
			$settings['humanpresence_enabled'] = !!$options['hp_forms'][self::get_form_id( $form_id )]['form_enabled'];
		}

        return $settings;
    }

    public static function filter_fields_enabled_status( $fields, $form_id ) {
    	$options = human_presence()->get_options();
    	$enabled = false;
		if ( isset( $options['hp_forms'] ) ) {
			$enabled = !!$options['hp_forms'][self::get_form_id( $form_id )]['form_enabled'];
		}
		if( $enabled ) {
			$hp_exists = false;
			foreach ( $fields as $key => $field ) {
				if ( $field['template'] === 'humanpresence' ) {
					$hp_exists = true;
				}
			}
			if ( !$hp_exists ) {
				if ( class_exists( 'WeForms_Form_Field_HumanPresence' ) ) {
					$fields[] = (new WeForms_Form_Field_HumanPresence())->get_field_props();
				} else {
					$fields[] = [
						'template'              => 'humanpresence',
			            'label'                 => '',
			            'is_meta'               => 'yes',
			            'id'                    => 0,
			            'is_new'                => true,
			            'wpuf_cond'             => null,
					];
				}
				
			}
		} else {
			foreach ( $fields as $key => $field ) {
				if ( $field['template'] === 'humanpresence' ) {
					unset( $fields[$key] );
				}
			}
		}
    		
        return $fields;
    }

    /*
	 * Usage self::log('error', 'Check out this sweet error!');
	 *
	*/
	public static function log($action, $message="", $logfile=false) {
	    $logfile = ($logfile) ? $logfile : WP_CONTENT_DIR.'/logs/'.date("Y-m-d").'.log';
	    $new = file_exists($logfile) ? false : true;
	    if($handle = fopen($logfile, 'a')) { // append
	        $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
	        $content = "{$timestamp} | {$action}: {$message}\n";
	        fwrite($handle, $content);
	        fclose($handle);
	        if($new) { chmod($logfile, 0755); }
	    } else {
	        return false;
	    }
	}

    /**
     * Form and form processing for global humanpresence settings in weForms General settings tabs
     *
     * @return void
     */
    public static function humanpresence_general_settings() {
    	$options = human_presence()->get_options();
		$premium = human_presence()->is_premium();
		$message = '';
		$message_class = 'updated';
		if ( $premium ) {
			if ( isset( $_POST['minimal_confidence'] ) && $_POST['minimal_confidence'] !== $options['wp_hp_min_confidence'] ) {
				$validation_failed = HumanPresenceSettings::minimal_confidence_validate( $_POST['minimal_confidence'] );
				if ( !$validation_failed ) {
					HumanPresenceSettings::handle_settings_changes( $_POST );
					$options = human_presence()->get_options();
					$message = 'Settings saved successfully.';
				} else {
					$message = 'Please enter a valid number.';
					$message_class = 'error';
				}
			}
			?>
<form method="POST" action="" class="wp-human-presence">
<table class="wp-list-table widefat fixed striped">
	<tbody>
		<tr>
			<td class="dl">
				<p>
					Adjust the Minimal Confidence Threshold
					<span class="hp-pop">
						<i class="fa fa-info-circle fa-fw"></i>
						<span class="popover above">A number between 0 and 100 that represents the minimum percentage of confidence acceptable before failing the form validation.</span>
					</span>
				</p>
			</td>
			<td class="pull-right">
				<label for="minimal_confidence"></label>
				<input class="minimal-confidence" name="minimal_confidence" type="number" value="<?php echo isset( $options['wp_hp_min_confidence'] ) ? esc_attr( $options['wp_hp_min_confidence'] ) : 70; ?>" />
			</td>
		</tr>
	</tbody>
</table>
<?php if ( $message ):?>
<div class="wrap">
<div class="<?php echo $message_class;?>"><p><?php echo $message;?></p></div>
</div>
<?php endif;?>
<p class="action-row">
	<input onclick="this.submit()" class="button-primary" name="hpres_settings_save" type="submit" value="Save changes">
</p>
</form>
<?php
		}

    }

}

add_action( 'plugins_loaded', function() { new HumanPresenceWEFormsIntegration(); });

add_action( 'weforms_after_save_form', [ 'HumanPresenceWEFormsIntegration', 'update_enabled_status' ], 10, 3 );
add_filter( 'weforms-get-form-settings', [ 'HumanPresenceWEFormsIntegration', 'filter_settings_enabled_status' ], 999, 2 );
add_filter( 'weforms-get-form-fields', [ 'HumanPresenceWEFormsIntegration', 'filter_fields_enabled_status' ], 999, 2 );
add_action( 'weforms_humanpresence_global_settings_form', [ 'HumanPresenceWEFormsIntegration', 'humanpresence_general_settings' ], 10 );
