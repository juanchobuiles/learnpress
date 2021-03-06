<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'LP_Profile' ) ) {
	/**
	 * Class LP_Profile
	 *
	 * Main class to controls the profile of a user
	 */
	class LP_Profile {
		/**
		 * The instances of all users has initialed a profile
		 *
		 * @var array
		 */
		protected static $_instances = array();

		/**
		 * @var LP_User
		 */
		protected $_user = false;

		/**
		 * @var string
		 */
		protected $_role = '';

		/**
		 * @var bool
		 */
		protected static $_hook_added = false;

		/**
		 * @var array
		 */
		protected $_publicity = array();

		/**
		 * @var array
		 */
		protected $_default_actions = array();

		/**
		 * @var LP_User_CURD
		 */
		protected $_curd = null;

		/**
		 *  Constructor
		 *
		 * @param        $user
		 * @param string $role
		 */
		protected function __construct( $user, $role = '' ) {

			$this->_curd = new LP_User_CURD();

			$this->_user = $user;
			$this->get_user();

			if ( ! $role ) {
				$this->_role = $this->get_role();
			}

			$this->maybe_logout_redirect();

			$this->_default_actions = apply_filters(
				'learn-press/profile-default-actions',
				array(
					'basic-information' => __( 'Account information updated successfully.', 'learnpress' ),
					'avatar'            => __( 'Account avatar updated successfully.', 'learnpress' ),
					'password'          => __( 'Password updated successfully.', 'learnpress' )
				)
			);

			if ( ! self::$_hook_added ) {
				self::$_hook_added = true;
				add_action( 'learn-press/profile-content', array( $this, 'output' ), 10, 3 );
				add_action( 'learn-press/before-profile-content', array( $this, 'output_section' ), 10, 3 );
				add_action( 'learn-press/profile-section-content', array( $this, 'output_section_content' ), 10, 3 );

				/*
				 * Register actions with request handler class to process
				 * requesting from user profile.
				 */
				foreach ( $this->_default_actions as $action => $message ) {
					/**
					 * @see LP_Profile::save()
					 */
					LP_Request_Handler::register( 'save-profile-' . $action, array( $this, 'save' ) );
				}

				add_filter( 'learn-press/profile/class', array( $this, 'profile_class' ) );
			}
		}

		/**
		 * Maybe logout wp if there is a logout sign
		 */
		public function maybe_logout_redirect() {
			if ( ( 'true' !== LP_Request::get_string( 'lp-logout' ) ) || ! wp_verify_nonce( LP_Request::get_string( 'nonce' ), 'lp-logout' ) ) {
				return;
			}

			wp_logout();
			if ( ! $redirect = LP_Request::get_redirect() ) {
				$redirect = learn_press_get_current_url();
			}
			wp_redirect( $redirect );
			exit();
		}

		public function profile_class( $classes ) {
			if ( 'yes' === LP()->settings()->get( 'enable_login_profile' ) && 'yes' === LP()->settings()->get( 'enable_register_profile' ) ) {
				$classes[] = 'enable-login-register';
			}

			return $classes;
		}

		public function output( $tab, $args, $user ) {
			if ( ( $location = learn_press_locate_template( 'profile/tabs/' . $tab . '.php' ) ) && file_exists( $location ) ) {
				include $location;
			}
		}

		public function output_section( $tab_key, $tab_data, $user ) {
			learn_press_get_template( 'profile/tabs/sections.php', compact( 'tab_key', 'tab_data', 'user' ) );
		}

		public function output_section_content( $section, $args, $user ) {
			global $wp;
			$current = $this->get_current_section();
			if ( $current === $section ) {
				if ( ( $location = learn_press_locate_template( 'profile/tabs/' . $this->get_current_tab() . '/' . $section . '.php' ) ) && file_exists( $location ) ) {
					include $location;
				} else {
					do_action( 'learn-press/' );

					echo $location;
				}
			}
		}

		/**
		 * Get role of current user.
		 *
		 * @return string
		 */
		protected function get_role() {
			if ( $user = learn_press_get_current_user( false ) ) {
				if ( ! $user->is_guest() ) {
					if ( $this->_user->is_admin() ) {
						return 'admin';
					}

					if ( $this->_user->is_instructor() ) {
						return 'instructor';
					}

					return 'user';
				}
			}

			return '';
		}

		/**
		 * Get the user of a profile instance.
		 *
		 * @return bool|LP_User|mixed
		 */
		public function get_user() {
			if ( ! $this->_user instanceof LP_Abstract_User ) {
				if ( is_numeric( $this->_user ) ) {
					$this->_user = learn_press_get_user( $this->_user );
				} elseif ( empty( $this->_user ) ) {
					$this->_user = learn_press_get_current_user( true );
				}

				$settings         = LP()->settings;
				$this->_publicity = array(
					'view-tab-courses'           => $settings->get( 'profile_publicity.courses' ) === 'yes',
					'view-tab-basic-information' => $settings->get( 'profile_publicity.basic-information' ) === 'yes',
				);
			}

			return $this->_user;
		}

		public function is_current_user() {
			return $this->get_user()->is( 'current' );
		}

		/**
		 * Wrap function for $user->get_data()
		 *
		 * @param string $field
		 *
		 * @return mixed
		 */
		public function get_user_data( $field ) {
			return 'id' === strtolower( $field ) ? $this->_user->get_id() : $this->_user->get_data( $field );
		}

		public function tab_dashboard() {
			learn_press_get_template( 'profile/dashboard.php', array( 'user' => $this->_user ) );
		}

		public function get_login_url( $redirect = false ) {
			return learn_press_get_login_url( $redirect !== false ? $redirect : $this->get_current_url() );
		}

		/**
		 * Get default tabs for profile.
		 *
		 * @return mixed
		 */
		public function get_tabs() {
			static $tabs = false;

			if ( $tabs === false ) {
				$settings = LP()->settings;
				$defaults = array(
					''              => array(
						'title'    => __( 'Dashboard', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-dashboard', '' ),
						'callback' => array( $this, 'tab_dashboard' ),
						'priority' => 10
					),
					'courses'       => array(
						'title'    => __( 'Courses', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-courses', 'courses' ),
						'callback' => array( $this, 'tab_courses' ),
						'priority' => 15,
						'sections' => array(
							'own'       => array(
								'title'    => __( 'Own', 'learnpress' ),
								'slug'     => $settings->get( 'profile_endpoints.own-courses', 'own' ),
								'callback' => array( $this, 'tab_order_details' ),
								'priority' => 10
							),
							'purchased' => array(
								'title'    => __( 'Purchased', 'learnpress' ),
								'slug'     => $settings->get( 'profile_endpoints.purchased-courses', 'purchased' ),
								'callback' => array( $this, 'tab_order_details' ),
								'priority' => 15
							)
						)
					),
					'quizzes'       => array(
						'title'    => __( 'Quizzes', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-quizzes', 'quizzes' ),
						'callback' => array( $this, 'tab_quizzes' ),
						'priority' => 20
					),
					'orders'        => array(
						'title'    => __( 'Orders', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-orders', 'orders' ),
						'callback' => array( $this, 'tab_orders' ),
						'priority' => 25
					),
					'order-details' => array(
						'title'    => __( 'Order details', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-order-details', 'order-details' ),
						'hidden'   => true,
						'callback' => array( $this, 'tab_order_details' ),
						'priority' => 30
					),
					'settings'      => array(
						'title'    => __( 'Settings', 'learnpress' ),
						'slug'     => $settings->get( 'profile_endpoints.profile-settings', 'settings' ),
						'callback' => array( $this, 'tab_settings' ),
						'sections' => array(
							'basic-information' => array(
								'title'    => __( 'General', 'learnpress' ),
								'slug'     => $settings->get( 'profile_endpoints.settings-basic-information', 'basic-information' ),
								'callback' => array( $this, 'tab_order_details' ),
								'priority' => 10
							),
							'avatar'            => array(
								'title'    => __( 'Avatar', 'learnpress' ),
								'callback' => array( $this, 'tab_order_details' ),
								'slug'     => $settings->get( 'profile_endpoints.settings-avatar', 'avatar' ),
								'priority' => 20
							),
							'change-password'   => array(
								'title'    => __( 'Password', 'learnpress' ),
								'slug'     => $settings->get( 'profile_endpoints.settings-change-password', 'change-password' ),
								'hidden'   => true,
								'callback' => array( $this, 'tab_order_details' ),
								'priority' => 30
							)
						),
						'priority' => 35
					)
				);

				$tabs = apply_filters( 'learn-press/profile-tabs', $defaults );

				foreach ( $tabs as $slug => $data ) {
					if ( ! array_key_exists( 'slug', $data ) ) {
						$tabs[ $slug ]['slug'] = $slug;
					}

					if ( ! array_key_exists( 'priority', $data ) ) {
						$tabs[ $slug ]['priority'] = 10;
					}

					if ( empty( $data['sections'] ) ) {
						continue;
					}
					foreach ( $data['sections'] as $section_slug => $section_data ) {
						if ( ! array_key_exists( 'slug', $section_data ) ) {
							$tabs[ $slug ]['sections'][ $section_slug ]['slug'] = $section_slug;
						}

						if ( ! array_key_exists( 'priority', $section_data ) ) {
							$tabs[ $slug ]['sections'][ $section_slug ]['priority'] = 10;
						}
					}
					//$tabs[ $slug ]['sections']
					uasort( $tabs[ $slug ]['sections'], array( $this, '_sort_tabs' ) );
				}

				uasort( $tabs, array( $this, '_sort_tabs' ) );

				$key = md5( serialize( $tabs ) );
				if ( $key !== get_option( '_lp_tabs_data' ) ) {
					flush_rewrite_rules();
					update_option( '_lp_tabs_data', $key, false );
				}
			}

			return $tabs;
		}

		protected function _sort_tabs( $a, $b ) {
			return $a['priority'] > $b['priority'];
		}

		/**
		 * Get current tab slug in query string.
		 *
		 * @param string $default Optional.
		 * @param bool   $key     Optional. True if return the key instead of value.
		 *
		 * @return string
		 */
		public function get_current_tab( $default = '', $key = true ) {
			global $wp;
			$current = $default;
			if ( ! empty( $_REQUEST['view'] ) ) {
				$current = $_REQUEST['view'];
			} else if ( ! empty( $wp->query_vars['view'] ) ) {
				$current = $wp->query_vars['view'];
			} else {
				if ( $tab = $this->get_tab_at() ) {
					$current = $tab['slug'];
				}
			}
			if ( $key ) {
				$current_display = $current;
				$current         = false;
				foreach ( $this->get_tabs() as $_slug => $data ) {
					if ( array_key_exists( 'slug', $data ) && ( $data['slug'] === $current_display ) ) {
						$current = $_slug;
						break;
					}
				}
			}

			return $current;
		}

		/**
		 * Get current section in query string.
		 *
		 * @param string $default
		 * @param bool   $key
		 * @param string $tab
		 *
		 * @return bool|int|mixed|string
		 */
		public function get_current_section( $default = '', $key = true, $tab = '' ) {
			global $wp;
			$current = $default;
			if ( ! empty( $_REQUEST['section'] ) ) {
				$current = $_REQUEST['section'];
			} else if ( ! empty( $wp->query_vars['section'] ) ) {
				$current = $wp->query_vars['section'];
			} else {
				if ( false === $tab ) {
					$current_tab = $this->get_current_tab();
				} else {
					$current_tab = $tab;
				}
				if ( $tab = $this->get_tab_at( $current_tab ) ) {
					if ( ! empty( $tab['sections'] ) ) {
						$section = reset( $tab['sections'] );
						if ( array_key_exists( 'slug', $section ) ) {
							$current = $section['slug'];
						} else {
							$sections = array_keys( $tab['sections'] );
							$current  = reset( $sections );
						}
					}
				}
			}

			// If find the key instead of value from settings
			if ( $key ) {
				$current_display = $current;
				$current         = false;
				foreach ( $this->get_tabs() as $_slug => $data ) {
					if ( empty( $data['sections'] ) ) {
						continue;
					}
					foreach ( $data['sections'] as $_slug => $data ) {
						if ( array_key_exists( 'slug', $data ) && ( $data['slug'] === $current_display ) ) {
							$current = $_slug;
							break 2;
						}
					}
				}
			}

			return $current;
		}

		/**
		 * Get tab data at a position.
		 *
		 * @param int $position Optional. Indexed number or slug.
		 *
		 * @return mixed
		 */
		public function get_tab_at( $position = 0 ) {
			if ( $tabs = $this->get_tabs() ) {
				if ( is_numeric( $position ) ) {
					$tabs = array_values( $tabs );
					if ( ! empty( $tabs[ $position ] ) ) {
						return $tabs[ $position ];
					}
				} else {
					if ( ! empty( $tabs[ $position ] ) ) {
						return $tabs[ $position ];
					}
				}
			}

			return false;
		}

		/**
		 * Get permalink of a tab and section if passed.
		 *
		 * @param bool $tab
		 * @param bool $with_section
		 *
		 * @return mixed|string
		 */
		public function get_tab_link( $tab = false, $with_section = false ) {

			$user = $this->get_user();

			if ( ! $user ) {
				return '';
			}
			$args = array(
				'user' => $user->get_data( 'user_login' )
			);
			if ( isset( $args['user'] ) ) {
				if ( false === $tab ) {
					$tab = $this->get_current_tab( null, false );
				}

				$tab_data = $this->get_tab_at( $tab );
				$tab      = $this->get_slug( $tab_data, $tab );

				if ( $tab ) {
					$args['tab'] = $tab;
				} else {
					unset( $args['user'] );
				}

				if ( $with_section && ! empty( $tab_data['sections'] ) ) {
					if ( $with_section === true ) {
						$section_keys  = array_keys( $tab_data['sections'] );
						$first_section = reset( $section_keys );
						$with_section  = $this->get_slug( $tab_data['sections'][ $first_section ], $first_section );
					}
					$args['section'] = $with_section;
				}
			}
			$args         = array_map( '_learn_press_urlencode', $args );
			$profile_link = trailingslashit( learn_press_get_page_link( 'profile' ) );
			if ( $profile_link ) {
				if ( get_option( 'permalink_structure' ) ) {
					$url = trailingslashit( $profile_link . join( "/", array_values( $args ) ) );
				} else {
					$url = add_query_arg( $args, $profile_link );
				}
			} else {
				$url = get_author_posts_url( $user->get_id() );
			}

			/**
			 * @deprecated
			 */
			$url = apply_filters( 'learn_press_user_profile_link', $url, $user->get_id(), $tab );

			return apply_filters( 'learn-press/user-profile-url', $url, $user->get_id(), $tab );
		}

		/**
		 * Get current link of profile
		 *
		 * @param string $args           - Optional. Add more query args to url.
		 * @param bool   $with_permalink - Optional. TRUE to build url as friendly url.
		 *
		 * @return mixed|string
		 */
		public function get_current_url( $args = '', $with_permalink = false ) {
			$url = $this->get_tab_link( $this->get_current_tab(), $this->get_current_section() );

			if ( is_array( $args ) && $args ) {
				if ( ! $with_permalink ) {
					$url = add_query_arg( $args, $url );
				} else {
					$parts = array();
					foreach ( $args as $k => $v ) {
						$parts[] = "{$k}/{$v}";
					}
					$url = trailingslashit( $url ) . join( "/", $parts ) . '/';
				}
			}

			return $url;
		}

		/**
		 * Check if the $key is current tab.
		 *
		 * @param string $key
		 *
		 * @return bool
		 */
		public function is_current_tab( $key ) {
			return $this->get_current_tab() === $key;
		}

		/**
		 * Check if the $key is current section.
		 *
		 * @param string $key
		 * @param string $tab
		 *
		 * @return bool
		 */
		public function is_current_section( $key, $tab = '' ) {
			return $this->get_current_section() === $key;
		}

		/**
		 * Check if a tab or section is hidden.
		 *
		 * @param array $tab_or_section
		 *
		 * @return bool
		 */
		public function is_hidden( $tab_or_section ) {
			return array_key_exists( 'hidden', $tab_or_section ) && $tab_or_section['hidden'];
		}

		/**
		 * Get the slug of tab or section if defined.
		 *
		 * @param array  $tab_or_section
		 * @param string $default
		 *
		 * @return string
		 */
		public function get_slug( $tab_or_section, $default = '' ) {
			return array_key_exists( 'slug', $tab_or_section ) ? $tab_or_section['slug'] : $default;
		}

		/**
		 * Check if user can with a capability.
		 *
		 * @param string $capability
		 *
		 * @return mixed
		 */
		public function current_user_can( $capability ) {

			$tab         = substr( $capability, strlen( 'view-tab-' ) );
			$public_tabs = array( 'courses', 'quizzes' );

			// public profile courses and quizzes tab
			if ( in_array( $tab, $public_tabs ) ) {
				$can = true;
			} else {
				if ( get_current_user_id() === $this->_user->get_id() ) {
					$can = true;
				} else {
					$can = ! empty( $this->_publicity[ $capability ] ) && $this->_publicity[ $capability ] == true;
				}
			}

			return apply_filters( 'learn-press/profile-current-user-can', $can, $capability );
		}

		/**
		 * Save profile.
		 *
		 * @param string $nonce . Value of nonce depending on the action requested from profile tab.
		 *
		 * @return mixed
		 */
		public function save( $nonce ) {

			$action  = '';
			$message = '';

			// Find the action by checking the nonce
			foreach ( $this->_default_actions as $_action => $message ) {
				if ( wp_verify_nonce( $nonce, 'learn-press-save-profile-' . $_action ) ) {
					$action = $_action;
					break;
				}
			}

			// If none of actions found.
			if ( ! $action ) {
				return false;
			}
			$return = false;
			switch ( $action ) {
				case 'basic-information':
					$return = learn_press_update_user_profile_basic_information( true );
					break;
				case 'avatar':
					$return = learn_press_update_user_profile_avatar( true );
					break;
				case 'password':
					$return = learn_press_update_user_profile_change_password( true );
					break;
			}
			if ( is_wp_error( $return ) ) {
				learn_press_add_message( $return->get_error_message() );
			} else {
				if ( $return ) {
					learn_press_add_message( $message );
				}
			}

			if ( ! empty( $_REQUEST['redirect'] ) ) {
				$redirect = $_REQUEST['redirect'];
			} else {
				$redirect = learn_press_get_current_url();
			}

			$redirect = apply_filters( 'learn-press/profile-updated-redirect', $redirect, $action );

			if ( $redirect ) {
				wp_redirect( $redirect );
				exit;
			}

			return true;
		}

		/**
		 * Get all orders of profile's user.
		 *
		 * @param mixed $args
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		public function get_user_orders( $args = '' ) {

			$args = wp_parse_args(
				$args,
				array(
					'group_by_order' => true,
					'status'         => ''
				)
			);

			return $this->_curd->get_orders( $this->get_user_data( 'id' ), $args );
		}

		/**
		 * Query order of user is viewing profile.
		 *
		 * @param string $args
		 *
		 * @return array
		 */
		public function query_orders( $args = '' ) {
			global $wp_query;
			$query = array(
				'items'      => array(),
				'total'      => 0,
				'num_pages'  => 0,
				'pagination' => ''
			);

			$query_args = array();

			if ( is_array( $args ) ) {
				foreach ( array( 'status', 'group_by_order' ) as $k ) {
					if ( isset( $args[ $k ] ) ) {
						$query_args[ $k ] = $args[ $k ];
					}
				}
			}

			if ( empty( $query_args['status'] ) ) {
				$query_args['status'] = 'completed processing cancelled';
			}

			if ( $order_ids = $this->get_user_orders( $query_args ) ) {
				$default_args = array(
					'paged' => 1,
					'limit' => 10
				);

				// Page
				if ( $this->get_current_tab() === 'orders' && isset( $wp_query->query_vars['view_id'] ) ) {
					$default_args['paged'] = $wp_query->query_vars['view_id'];
				}

				$args   = wp_parse_args( $args, $default_args );
				$offset = isset( $args['limit'] ) && $args['limit'] > 0 && $args['paged'] ? ( $args['paged'] - 1 ) * $args['limit'] : 0;

				$query_order = new WP_Query(
					array(
						'post_type'      => LP_ORDER_CPT,
						'posts_per_page' => $args['limit'],
						'offset'         => $offset,
						'post_status'    => 'any',
						'post__in'       => array_keys( $order_ids ),
						'orderby'        => 'post__in',
						'fields'         => 'ids'
					)
				);

				if ( $query_order->have_posts() ) {

					$orders = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $query_order->posts : array_filter( array_map( 'learn_press_get_order', $query_order->posts ) );

					$query['orders']     = $orders;
					$query['total']      = $query_order->found_posts;
					$query['num_pages']  = $query_order->max_num_pages;
					$query['pagination'] = learn_press_paging_nav( array(
						'num_pages' => $query['num_pages'],
						'base'      => learn_press_user_profile_link( $this->get_user_data( 'id' ), LP()->settings->get( 'profile_endpoints.profile-orders' ) ),
						'format'    => $GLOBALS['wp_rewrite']->using_permalinks() ? user_trailingslashit( '%#%', '' ) : '?paged=%#%',
						'echo'      => false,
						'paged'     => $args['paged']
					) );

					$query = new LP_Query_List_Table(
						array(
							'total' => $query_order->found_posts,
							'paged' => $args['paged'],
							'limit' => $args['limit'],
							'pages' => $query['num_pages'],
							'items' => $orders
						)
					);
				}

			}

			return $query;
		}

		/**
		 * Query user's courses
		 *
		 * @param string $type - Optional. [own, purchased, enrolled, etc]
		 * @param mixed  $args - Optional.
		 *
		 * @return array|LP_Query_List_Table
		 */
		public function query_courses( $type = 'own', $args = '' ) {
			$query = false;
			switch ( $type ) {
				case 'purchased':
					$query = $this->_curd->query_purchased_courses( $this->get_user_data( 'id' ), $args );
					break;
				case 'enrolled':
					break;
				default:
					$query = $this->_curd->query_own_courses( $this->get_user_data( 'id' ), $args );
			}

			return $query;
		}

		/**
		 * @param mixed $args
		 *
		 * @return array|LP_Query_List_Table
		 */
		public function query_quizzes( $args = '' ) {
			return $this->_curd->query_quizzes( $this->get_user_data( 'id' ), $args );
		}

		/**
		 * Get the order is viewing details.
		 */
		public function get_view_order() {
			global $wp_query;
			$order = false;
			if ( isset( $wp_query->query_vars['view_id'] ) ) {
				$order = learn_press_get_order( $wp_query->query_vars['view_id'] );
			}

			return $order;
		}

		/**
		 * Get filters for own courses tab.
		 *
		 * @param string $current_filter
		 *
		 * @return array
		 */
		public function get_own_courses_filters( $current_filter = '' ) {
			$url      = $this->get_current_url();
			$defaults = array(
				'all'     => sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'All', 'learnpress' ) ),
				'publish' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'publish', $url ) ), __( 'Publish', 'learnpress' ) ),
				'pending' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'pending', $url ) ), __( 'Pending', 'learnpress' ) )
			);

			if ( ! $current_filter ) {
				$keys           = array_keys( $defaults );
				$current_filter = reset( $keys );
			}

			foreach ( $defaults as $k => $v ) {
				if ( $k === $current_filter ) {
					$defaults[ $k ] = sprintf( '<span>%s</span>', strip_tags( $v ) );
				}
			}

			return apply_filters(
				'learn-press/profile/own-courses-filters',
				$defaults
			);
		}

		/**
		 * Get filters for purchased courses tab.
		 *
		 * @param string $current_filter
		 *
		 * @return array
		 */
		public function get_purchased_courses_filters( $current_filter = '' ) {
			$url      = $this->get_current_url( false );
			$defaults = array(
				'all'          => sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'All', 'learnpress' ) ),
				'finished'     => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'finished', $url ) ), __( 'Finished', 'learnpress' ) ),
				'passed'       => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'passed', $url ) ), __( 'Passed', 'learnpress' ) ),
				'failed'       => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'failed', $url ) ), __( 'Failed', 'learnpress' ) ),
				'not-enrolled' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'not-enrolled', $url ) ), __( 'Not enrolled', 'learnpress' ) )
			);

			if ( ! $current_filter ) {
				$keys           = array_keys( $defaults );
				$current_filter = reset( $keys );
			}

			foreach ( $defaults as $k => $v ) {
				if ( $k === $current_filter ) {
					$defaults[ $k ] = sprintf( '<span>%s</span>', strip_tags( $v ) );
				}
			}

			return apply_filters(
				'learn-press/profile/purchased-courses-filters',
				$defaults
			);
		}

		/**
		 * Get filters for purchased courses tab.
		 *
		 * @param string $current_filter
		 *
		 * @return array
		 */
		public function get_quizzes_filters( $current_filter = '' ) {
			$url      = $this->get_current_url( false );
			$defaults = array(
				'all'       => sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'All', 'learnpress' ) ),
				'completed' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'completed', $url ) ), __( 'Finished', 'learnpress' ) ),
				'passed'    => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'passed', $url ) ), __( 'Passed', 'learnpress' ) ),
				'failed'    => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'filter-status', 'failed', $url ) ), __( 'Failed', 'learnpress' ) )
			);

			if ( ! $current_filter ) {
				$keys           = array_keys( $defaults );
				$current_filter = reset( $keys );
			}

			foreach ( $defaults as $k => $v ) {
				if ( $k === $current_filter ) {
					$defaults[ $k ] = sprintf( '<span>%s</span>', strip_tags( $v ) );
				}
			}

			return apply_filters(
				'learn-press/profile/quizzes-filters',
				$defaults
			);
		}

		/**
		 * @param bool $redirect
		 *
		 * @return string
		 */
		public function logout_url( $redirect = false ) {
			if ( $this->enable_login() ) {
				$profile_url = learn_press_get_page_link( 'profile' );
				$url         = add_query_arg( array(
					'lp-logout' => 'true',
					'nonce'     => wp_create_nonce( 'lp-logout' )
				), untrailingslashit( $profile_url ) );

				if ( $redirect !== false ) {
					$url = add_query_arg( 'redirect', urlencode( $redirect ), $url );
				}
			} else {
				$url = wp_logout_url( $redirect !== false ? $redirect : $this->get_current_url() );
			}

			return apply_filters( 'learn-press/logout-url', $url );
		}

		/**
		 * Echo class for main div.
		 *
		 * @param bool   $echo
		 * @param string $more
		 *
		 * @return string
		 */
		public function main_class( $echo = true, $more = '' ) {
			$classes = array( 'lp-user-profile' );
			if ( $this->is_current_user() ) {
				$classes[] = 'current-user';
			}

			if ( $this->get_user()->is_guest() ) {
				$classes[] = 'guest';
			}

			$classes = LP_Helper::merge_class( $classes, $more );

			$class = ' class="' . join( ' ', apply_filters( 'learn-press/profile/class', $classes ) ) . '"';
			if ( $echo ) {
				echo $class;
			}

			return $class;
		}

		/**
		 * @return array
		 */
		public function get_login_fields() {
			return LP_Shortcode_Login_Form::get_login_fields();
		}

		/**
		 * @return array
		 */
		public function get_register_fields() {
			return LP_Shortcode_Register_Form::get_register_fields();
		}

		/**
		 * TRUE if enable show login form in user profile if user is not logged in.
		 *
		 * @return bool
		 */
		public function enable_login() {
			return 'yes' === LP()->settings()->get( 'enable_login_profile' );
		}

		/**
		 * TRUE if enable show register form in user profile if user is not logged in.
		 *
		 * @return bool
		 */
		public function enable_register() {
			return 'yes' === LP()->settings()->get( 'enable_register_profile' );
		}

		/**
		 * Get an instance of LP_Profile for a user id
		 *
		 * @param $user_id
		 *
		 * @return LP_Profile mixed
		 */
		public static function instance( $user_id = 0 ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}
			if ( empty( self::$_instances[ $user_id ] ) ) {
				self::$_instances[ $user_id ] = new self( $user_id );
			}

			return self::$_instances[ $user_id ];
		}
	}
}