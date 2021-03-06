<?php
/**
 * Profile Page Shortcode.
 *
 * @author  ThimPress
 * @category Shortcodes
 * @package  Learnpress/Shortcodes
 * @version  3.0.0
 * @extends  LP_Abstract_Shortcode
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Shortcode_Profile' ) ) {

	/**
	 * Class LP_Shortcode_Profile
	 */
	class LP_Shortcode_Profile extends LP_Abstract_Shortcode {
		/**
		 * LP_Shortcode_Profile constructor.
		 *
		 * @param mixed $atts
		 */
		public function __construct( $atts = '' ) {
			parent::__construct( $atts );
		}

		/**
		 * Shortcode content.
		 *
		 * @return string
		 */
		public function output() {
			global $wp_query, $wp;
			if ( isset( $wp_query->query['user'] ) ) {
				$user = get_user_by( apply_filters( 'learn_press_get_user_requested_by', 'login' ), urldecode( $wp_query->query['user'] ) );
			} else {
				$user = get_user_by( 'id', get_current_user_id() );
			}

			if ( $user ) {
				$user = learn_press_get_user( $user->ID );
			}

			ob_start();
			learn_press_print_messages();
//		if ( ! $user || $user->is_guest() ) {
//			if ( empty( $wp_query->query['user'] ) ) {
//				if ( ! is_user_logged_in() ) {
//					if ( LP()->settings->get( 'enable_login_profile' ) ) {
//						echo do_shortcode( '[learn_press_login_form]' );
//					} else {
//						learn_press_display_message( __( 'Please login to see your profile content!', 'learnpress' ), 'error' );
//					}
//				}
//			}
//		} else {
			//if ( $user ) {
			global $profile;
			$profile = LP_Profile::instance( $user ? $user->get_id() : 0 );
			learn_press_get_template( 'profile/profile.php', array( 'profile' => $profile ) );
			//}
			$output = ob_get_clean();

			return $output;
		}
	}
}