<?php
/*
Plugin Name: LearnPress
Plugin URI: http://thimpress.com/learnpress
Description: LearnPress is a WordPress complete solution for creating a Learning Management System (LMS). It can help you to create courses, lessons and quizzes.
Author: ThimPress
Version: 3.0.0
Author URI: http://thimpress.com
Requires at least: 3.8
Tested up to: 4.7

Text Domain: learnpress
Domain Path: /languages/
*/

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

// show every possible error
error_reporting( - 1 );

if ( ! defined( 'LP_PLUGIN_FILE' ) ) {
	define( 'LP_PLUGIN_FILE', __FILE__ );
	require_once dirname( __FILE__ ) . '/inc/lp-constants.php';
}

if ( ! class_exists( 'LearnPress' ) ) {

	/**
	 * Class LearnPress
	 *
	 * Version 3.0.0
	 */
	class LearnPress {

		/**
		 * Current version of the plugin
		 *
		 * @var string
		 */
		public $version = LEARNPRESS_VERSION;

		/**
		 * The single instance of the class
		 *
		 * @var LearnPress object
		 */
		private static $_instance = null;

		/**
		 * Store the session class
		 *
		 * @var LP_Session_Handler
		 */
		public $session = null;

		/**
		 * @var LP_Profile
		 */
		public $profile = null;

		/**
		 * @var LP_Cart object
		 */
		public $cart = false;

		/**
		 * @var LP_Settings
		 */
		public $settings = null;

		/**
		 * @var null
		 */
		public $schedule = null;

		/**
		 * @var array
		 */
		public $query_vars = array();

		/**
		 * Table prefixes
		 *
		 * @var array
		 */
		protected $_table_prefixes = array();

		/**
		 * @var null
		 */
		public $query = null;

		/**
		 * @var array
		 */
		public $global = array();

		/**
		 * LearnPress constructor.
		 */
		public function __construct() {
			// Prevent duplicate unwanted hooks
			if ( self::$_instance ) {
				return;
			}
			self::$_instance = $this;

			// define table prefixes
			$this->define_tables();
			// include files
			$this->includes();
			// hooks
			$this->init_hooks();
		}

		/**
		 * Defines table names.
		 */
		public function define_tables() {
			global $wpdb;
			$tables = array(
				'sessions',
				'sections',
				'section_items',
				'user_items',
				'user_itemmeta',
				'order_items',
				'order_itemmeta',
				'quiz_questions',
				'question_answers',
				'question_answermeta',
				'review_logs'
			);
			foreach ( $tables as $short_name ) {
				$table_name                                    = $wpdb->prefix . LP_TABLE_PREFIX . $short_name;
				$this->_table_prefixes[ 'tbl_' . $short_name ] = $table_name;

				$backward_key          = 'learnpress_' . $short_name;
				$wpdb->{$backward_key} = $table_name;
			}
		}

		/**
		 * Includes needed files.
		 */
		public function includes() {
			require_once 'inc/class-lp-factory.php';
			require_once 'inc/class-lp-datetime.php';
			require_once 'inc/class-lp-hard-cache.php';
			require_once 'inc/interfaces/interface-curd.php';
			require_once 'inc/abstracts/abstract-object-data.php';
			require_once 'inc/abstracts/abstract-post-data.php';
			require_once 'inc/abstracts/abstract-assets.php';
			require_once 'inc/class-lp-query-course.php';
			require_once 'inc/abstracts/abstract-addon.php';

			// Background processes
			require_once 'inc/background-process/class-lp-background-emailer.php';
			require_once 'inc/background-process/class-lp-background-schedule-items.php';
			require_once 'inc/background-process/class-lp-background-clear-temp-users.php';

			// curds
			require_once 'inc/curds/class-lp-helper-curd.php';
			require_once 'inc/curds/class-lp-course-curd.php';
			require_once 'inc/curds/class-lp-section-curd.php';
			require_once 'inc/curds/class-lp-lesson-curd.php';
			require_once 'inc/curds/class-lp-quiz-curd.php';
			require_once 'inc/curds/class-lp-question-curd.php';
			require_once 'inc/curds/class-lp-order-curd.php';
			require_once 'inc/curds/class-lp-user-curd.php';

			require_once 'inc/class-lp-debug.php';
			require_once 'inc/class-lp-global.php';
			require_once 'inc/admin/meta-box/class-lp-meta-box-helper.php';
			require_once 'inc/course/class-lp-course-item.php';
			require_once 'inc/course/class-lp-course-section.php';
			require_once 'inc/user-item/class-lp-user-item.php';
			require_once 'inc/user-item/class-lp-user-item-course.php';
			require_once 'inc/lp-deprecated.php';
			require_once 'inc/class-lp-cache.php';
			require_once 'inc/lp-core-functions.php';
			require_once 'inc/class-lp-autoloader.php';
			require_once 'inc/class-lp-install.php';
			require_once 'inc/lp-webhooks.php';
			require_once 'inc/class-lp-request-handler.php';
			require_once( 'inc/abstract-settings.php' );

			if ( is_admin() ) {
				require_once 'inc/admin/meta-box/class-lp-meta-box-helper.php';
				require_once 'inc/admin/class-lp-admin-notice.php';
				require_once 'inc/admin/class-lp-admin.php';
				require_once( 'inc/admin/settings/abstract-settings-page.php' );
			}
			if ( ! is_admin() ) {
				require_once 'inc/class-lp-assets.php';
			}
			require_once 'inc/question/class-lp-question.php';

			// Register custom-post-type and taxonomies
			require_once 'inc/custom-post-types/abstract.php';
			require_once 'inc/custom-post-types/course.php';
			require_once 'inc/custom-post-types/lesson.php';
			require_once 'inc/custom-post-types/quiz.php';
			require_once 'inc/custom-post-types/question.php';
			require_once 'inc/custom-post-types/order.php';

			if ( defined( 'LP_USE_ATTRIBUTES' ) && LP_USE_ATTRIBUTES ) {
				require_once 'inc/attributes/lp-attributes-functions.php';
			}

			require_once 'inc/course/lp-course-functions.php';
			require_once 'inc/course/abstract-course.php';
			require_once 'inc/course/class-lp-course.php';
			require_once 'inc/quiz/lp-quiz-functions.php';
			require_once 'inc/quiz/class-lp-quiz-factory.php';
			require_once 'inc/quiz/class-lp-quiz.php';
			require_once 'inc/lesson/lp-lesson-functions.php';
			require_once 'inc/order/lp-order-functions.php';
			require_once 'inc/order/class-lp-order.php';

			// user API
			require_once 'inc/user/lp-user-functions.php';
			require_once 'inc/user/class-lp-user-factory.php';
			require_once 'inc/user/abstract-lp-user.php';
			require_once 'inc/user/class-lp-user.php';
			require_once 'inc/user/class-lp-profile.php';
			require_once 'inc/user-item/class-lp-user-item.php';
			require_once 'inc/user-item/class-lp-user-item-course.php';
			require_once 'inc/user-item/class-lp-user-item-quiz.php';
			require_once 'inc/class-lp-session-handler.php';

			if ( is_admin() ) {
				require_once 'inc/admin/pointers/pointers.php';
			} else {
				require_once 'inc/class-lp-shortcodes.php';
			}

			// include template functions
			require_once( 'inc/lp-template-functions.php' );
			require_once( 'inc/lp-template-hooks.php' );
			require_once 'inc/cart/class-lp-cart.php';
			require_once 'inc/cart/lp-cart-functions.php';
			require_once 'inc/gateways/class-lp-gateway-abstract.php';
			require_once 'inc/gateways/class-lp-gateways.php';
			require_once 'inc/admin/class-lp-admin-ajax.php';
			if ( ! is_admin() ) {
				require_once 'inc/class-lp-ajax.php';
			}
			require_once 'inc/class-lp-multi-language.php';
			require_once 'inc/class-lp-page-controller.php';


			require_once 'inc/class-lp-schedules.php';

			// widgets
			LP_Widget::register( array( 'featured-courses', 'popular-courses', 'recent-courses' ) );

			$GLOBALS['lp_query'] = $this->query = new LP_Query();
		}

		/**
		 * Initial common hooks
		 */
		public function init_hooks() {
			$plugin_basename = $this->plugin_basename();

			add_action( 'activate_' . $plugin_basename, array( $this, 'on_activate' ) );
			add_action( 'deactivate_' . $plugin_basename, array( $this, 'on_deactivate' ) );
			add_action( 'activate_' . $plugin_basename, array( 'LP_Install', 'install' ) );

			add_action( 'wp_loaded', array( $this, 'wp_loaded' ), 20 );

			add_action( 'after_setup_theme', array( $this, 'setup_theme' ) );
			add_action( 'load-post.php', array( $this, 'load_meta_box' ), - 10 );
			add_action( 'load-post-new.php', array( $this, 'load_meta_box' ), - 10 );
			add_action( 'plugins_loaded', array( $this, 'plugin_loaded' ), 0 );
		}

		/**
		 * Get base name of plugin from file.
		 *
		 * @return string
		 */
		private function plugin_basename() {
			return learn_press_plugin_basename( __FILE__ );
		}

		/**
		 * Magic function to get Learnpress data.
		 *
		 * @param $key
		 *
		 * @return bool|LP_Checkout|LP_Course|LP_Emails|LP_User|LP_User_Guest|mixed
		 */
		public function __get( $key ) {
			_deprecated_argument( $key, '3.0.0' );

			$return = false;
			switch ( $key ) {
				case 'user':
					$return = learn_press_get_current_user();
					break;
				case 'email':
					$return = LP_Emails::instance();
					break;
				case 'checkout':
					$return = LP_Checkout::instance();
					break;
				case 'course':
					if ( empty( $this->_course ) ) {
						if ( learn_press_is_course() ) {
							$this->_course = learn_press_setup_object_data( get_the_ID() );
						}
					}
					$return = $this->_course;
					break;
				case 'quiz':
					if ( empty( $this->_quiz ) ) {
						if ( learn_press_is_quiz() ) {
							$this->_quiz = learn_press_setup_object_data( get_the_ID() );
						}
					}
					$return = $this->_quiz;
					break;
				default:
					if ( strpos( $key, 'tbl_' ) === 0 ) {
						$return = $this->_table_prefixes[ $key ];
					}
			}

			return $return;
		}

		/**
		 * Trigger this function while activating Learnpress.
		 *
		 * @since 3.0.0
		 *
		 * @hook learn_press_activate
		 */
		public function on_activate() {
			do_action( 'learn-press/activate', $this );
		}

		/**
		 * Trigger this function while deactivating Learnpress.
		 *
		 * $since 3.0.0
		 *
		 * @hook learn_press_deactivate
		 */
		public function on_deactivate() {
			do_action( 'learn-press/deactivate', $this );
		}

		/**
		 * Trigger WP loaded actions.
		 *
		 * @since 3.0.0
		 */
		public function wp_loaded() {
			if ( $this->is_request( 'frontend' ) ) {
				$this->gateways = LP_Gateways::instance()->get_available_payment_gateways();
			}
		}

		/**
		 * Setup courses thumbnail.
		 *
		 * @since 3.0.0
		 */
		public function setup_theme() {
			if ( ! current_theme_supports( 'post-thumbnails' ) ) {
				add_theme_support( 'post-thumbnails' );
			}
			add_post_type_support( 'lp_course', 'thumbnail' );

			// if enabled generate course thumbnail on General Settings add new image sizes
			$enabled_course_thum = LP()->settings->get( 'generate_course_thumbnail', 'yes' );
			if ( $enabled_course_thum !== 'yes' ) {
				return;
			}
			$sizes = apply_filters( 'learn_press_image_sizes', array( 'single_course', 'course_thumbnail' ) );

			foreach ( $sizes as $image_size ) {
				$size           = LP()->settings->get( $image_size . '_image_size', array() );
				$size['width']  = isset( $size['width'] ) ? $size['width'] : '300';
				$size['height'] = isset( $size['height'] ) ? $size['height'] : '300';
				$size['crop']   = isset( $size['crop'] ) ? $size['crop'] : 0;

				add_image_size( $image_size, $size['width'], $size['height'], $size['crop'] );
			}
		}

		/**
		 * Load metabox library.
		 *
		 * @since 3.0.0
		 */
		public function load_meta_box() {
			require_once 'inc/libraries/meta-box/meta-box.php';
		}

		/**
		 * Trigger Learnpress loaded actions.
		 *
		 * @since 3.0.0
		 */
		public function plugin_loaded() {
			$this->init();
			// let third parties know that we're ready
			do_action( 'learn_press_ready' );
			do_action( 'learn_press_loaded', $this );
			do_action( 'learn-press/ready' );
		}

		/**
		 * Init LearnPress when WP initialises
		 */
		public function init() {

			$this->view_log();

			$this->get_session();

			$this->settings = $this->settings();

			if ( $this->is_request( 'frontend' ) ) {
				$this->get_cart();
			}

			// init email notification hooks
			LP_Emails::init_email_notifications();
		}

		/**
		 * View log.
		 *
		 * @since 3.0.0
		 */
		public function view_log(){
			if ( ! empty( $_REQUEST['view-log'] ) ) {
				$log = $_REQUEST['view-log'];
				echo '<pre>';
				if ( is_multisite() ) {
					$log = "{$log}-" . get_current_blog_id();
				}
				echo $log = learn_press_get_log_file_path( $log );
				@readfile( $log );
				echo '<pre>';
				die();
			}
		}

		/**
		 * Get session object instance.
		 *
		 * @return mixed
		 */
		public function get_session() {
			if ( ! $this->session ) {
				$session_class = apply_filters( 'learn_press_session_class', 'LP_Session_Handler' );
				if ( class_exists( $session_class ) ) {
					$this->session = is_callable( array(
						$session_class,
						'instance'
					) ) ? call_user_func( array( $session_class, 'instance' ) ) : new $session_class();
				}
			}

			return $this->session;
		}

		/**
		 * Get settings object instance.
		 *
		 * @return bool|LP_Settings
		 */
		public function settings() {
			return LP_Settings::instance();
		}

		/**
		 * Get cart object instance for online learning market.
		 *
		 * @return LP_Cart
		 */
		public function get_cart() {
			if ( ! $this->cart ) {
				$cart_class = apply_filters( 'learn-press/cart-class', 'LP_Cart' );
				if ( is_object( $cart_class ) ) {
					$this->cart = $cart_class;
				} else {
					if ( class_exists( $cart_class ) ) {
						$this->cart = is_callable( array(
							$cart_class,
							'instance'
						) ) ? call_user_func( array( $cart_class, 'instance' ) ) : new $cart_class();
					}
				}
			}

			return $this->cart;
		}

		/**
		 * Check type of request.
		 *
		 * @param string $type ajax, frontend or admin
		 *
		 * @return bool
		 */
		public function is_request( $type ) {
			switch ( $type ) {
				case 'admin' :
					return is_admin();
				case 'ajax' :
					return defined( 'LP_DOING_AJAX' );
				case 'cron' :
					return defined( 'DOING_CRON' );
				case 'frontend' :
					return ( ! is_admin() || defined( 'LP_DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
				default:
					return strtolower( $_SERVER['REQUEST_METHOD'] ) == $type;
			}
		}

		/**
		 * Get the plugin url.
		 *
		 * @param string $sub_dir
		 *
		 * @return string
		 */
		public function plugin_url( $sub_dir = '' ) {
			return LP_PLUGIN_URL . ( $sub_dir ? "{$sub_dir}" : '' );
		}

		/**
		 * Get the plugin path.
		 *
		 * @param string $sub_dir
		 *
		 * @return string
		 */
		public function plugin_path( $sub_dir = '' ) {
			return LP_PLUGIN_PATH . ( $sub_dir ? "{$sub_dir}" : '' );
		}

		/**
		 * Get checkout object instance
		 *
		 * @return LP_Checkout
		 */
		public function checkout() {
			return LP_Checkout::instance();
		}

		/**
		 * Short way to return js file is located in LearnPress directory.
		 *
		 * @param string
		 *
		 * @return string
		 */
		public function js( $file ) {
			$min = '';
			if ( LP()->settings->get( 'debug' ) !== 'yes' ) {
				$min = '.min';
			}
			if ( ! preg_match( '/.js$/', $file ) ) {
				$file .= '.js';
			}
			if ( $min ) {
				$file = preg_replace( '/.js$/', $min . '.js', $file );
			}

			return $this->plugin_url( "assets/js/{$file}" );
		}

		/**
		 * Short way to return css file is located in LearnPress directory.
		 *
		 * @param string
		 *
		 * @return string
		 */
		public function css( $file ) {
			$min = '';
			if ( LP()->settings->get( 'debug' ) !== 'yes' ) {
				$min = '.min';
			}
			if ( ! preg_match( '/.css/', $file ) ) {
				$file .= '.css';
			}
			if ( $min ) {
				$file = preg_replace( '/.css/', $min . '.css', $file );
			}

			return $this->plugin_url( "assets/css/{$file}" );
		}

		/**
		 * Short way to return image file is located in LearnPress directory.
		 *
		 * @param string
		 *
		 * @return string
		 */
		public function image( $file ) {

			if ( ! preg_match( '/.(jpg|png)$/', $file ) ) {
				$file .= '.jpg';
			}

			return $this->plugin_url( "assets/images/{$file}" );
		}

		/**
		 * Main plugin instance.
		 *
		 * @return LearnPress
		 */
		public static function instance() {
			if ( ! self::$_instance ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}

}

/**
 * Short way to load main instance of plugin
 *
 * @return LearnPress
 * @since  1.0
 * @author thimpress
 */
function LP() {
	return LearnPress::instance();
}

/**
 * Load the main instance of plugin after all plugins have been loaded
 *
 * @author      ThimPress
 * @package     LearnPress/Functions
 * @since       1.0
 */
function load_learn_press() {
	_deprecated_function( __FUNCTION__, '1.1', 'LP' );

	return LP();
}

/**
 * Done! entry point of the plugin
 * Create new instance of LearnPress and put it to global
 */
$GLOBALS['LearnPress'] = LP();
