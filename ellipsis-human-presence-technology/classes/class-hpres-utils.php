<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

if ( ! class_exists( 'HumanPresenceUtils' ) ) {

	class HumanPresenceUtils {

		/**
		 * Convert a date/time string to time ago formatted string.
		 *
		 * @param String $datetime - Date string
		 * @param Boolean $full - Whether or not to show the full breakdown all the way to seconds ago
		 * (x months, x weeks, x days, x hours, x minutes, x seconds ago)
		 *
		 * @return String
		 */
		public static function time_ago( $datetime, $full = false ) {
			$now     = new DateTime();
			$ago     = new DateTime( $datetime );
			$diff    = $now->diff( $ago );
			$diff->w = floor( $diff->d / 7 );
			$diff->d -= $diff->w * 7;
			$string  = array(
				'y' => 'year',
				'm' => 'month',
				'w' => 'week',
				'd' => 'day',
				'h' => 'hour',
				'i' => 'minute',
				's' => 'second',
			);
			foreach ( $string as $k => &$v ) {
				if ( $diff->$k ) {
					$v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
				} else {
					unset( $string[ $k ] );
				}
			}
			if ( ! $full ) {
				$string = array_slice( $string, 0, 1 );
			}

			return $string ? implode( ', ', $string ) . ' ago' : 'just now';
		}

		/**
		 * Trigger an admin notice
		 *
		 * @param string $message - Message text (HTML)
		 * @param string $classes - CSS classes, separate by spaces
		 * @param boolean $positionTop - Position the notice at the page top
		 *
		 * @return Void
		 */
		public static function show_wp_admin_notice( $message = '', $classes = 'notice-success', $positionTop = false, $custom_html = false) {
			if ( $positionTop ) {
				// Move up to the page top
				if( $custom_html ) {
					$output = sprintf( '<div class="notice %2$s">%1$s</div>', $message, esc_attr( $classes ) );
				} else {
					$output = sprintf( '<div class="notice %2$s"><p>%1$s</p></div>', $message, esc_attr( $classes ) );
				}
			} else {
				// Render custom notice local to the area the function was invoked
				if( $custom_html ) {
					$output = sprintf( '<div class="notice %2$s below-h2">%1$s</div>', $message, esc_attr( $classes ) );
				} else {
					$output = sprintf( '<div class="notice %2$s below-h2"><p>%1$s</p></div>', $message, esc_attr( $classes ) );
				}
			}
			add_action( 'admin_notices', function () use ( $output ) {
				echo $output;
			} );
		}

		/**
		 * Determine if plugin is installed
		 *
		 * @param String $slug - Plugin slug
		 *
		 * @return Boolean
		 */
		public static function is_plugin_installed( $slug ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();

			if ( ! empty( $all_plugins[ $slug ] ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Perform initial cleanup if non-bundled HP plugin versions are installed on Convesio and activate
		 */
		public static function clean() {
			if ( self::is_plugin_installed( 'wp-human-presence/wp-human-presence.php' ) ) {
				$non_bundled_hp_dirname = 'wp-human-presence';
				$non_bundled_hp         = $non_bundled_hp_dirname . '/wp-human-presence.php';
				self::deactivate_non_bundled_human_presence( $non_bundled_hp );
			} elseif ( self::is_plugin_installed( 'ellipsis-human-presence-technology/wp-human-presence.php' ) ) {
				$non_bundled_hp_dirname = 'ellipsis-human-presence-technology';
				$non_bundled_hp         = $non_bundled_hp_dirname . '/wp-human-presence.php';
				self::deactivate_non_bundled_human_presence( $non_bundled_hp );
			}
		}

		/**
		 * Detect and deactivate non-bundled HP plugin versions
		 *
		 */
		public static function deactivate_non_bundled_human_presence( $non_bundled_hp ) {
			if ( is_plugin_active( $non_bundled_hp ) ) {
				deactivate_plugins( $non_bundled_hp );
				add_action( 'admin_notices', array( 'HumanPresenceUtils', 'active_human_presence_found_message' ) );
			}
		}

		public static function active_human_presence_found_message() {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'Another active version of the Human Presence plugin was already found. You don\'t need two, so we\'ve deactivated one of them for you.', 'wp-human-presence' ); ?></p>
			</div>
			<?php
		}

		public static function startsWith( $haystack, $needle ) {
			return 0 === substr_compare( $haystack, $needle, 0, strlen( $needle ) );
		}

		public static function endsWith( $haystack, $needle ) {
			return 0 === substr_compare( $haystack, $needle, - strlen( $needle ) );
		}

		public static function generate_public_key() {
			$data = random_bytes(16);
		    assert(strlen($data) == 16);

		    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}

	}

}
