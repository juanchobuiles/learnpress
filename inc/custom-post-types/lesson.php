<?php
/**
 * Class LP_Lesson_Post_Type
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Lesson_Post_Type' ) ) {

	/**
	 * Class LP_Lesson_Post_Type
	 */
	final class LP_Lesson_Post_Type extends LP_Abstract_Post_Type {
		/**
		 * @var null
		 */
		protected static $_instance = null;

		/**
		 * LP_Lesson_Post_Type constructor.
		 *
		 * @param $post_type
		 */
		public function __construct( $post_type ) {

			$this->add_map_method( 'before_delete', 'before_delete_lesson' );

			// hide View Lesson link if not assigned to course
			add_action( 'admin_footer', array( $this, 'hide_view_lesson_link' ) );

			parent::__construct( $post_type );
		}

		/**
		 * Register lesson post type.
		 */
		public function register() {
			return
				array(
					'labels'             => array(
						'name'               => __( 'Lessons', 'learnpress' ),
						'menu_name'          => __( 'Lessons', 'learnpress' ),
						'singular_name'      => __( 'Lesson', 'learnpress' ),
						'add_new_item'       => __( 'Add New Lesson', 'learnpress' ),
						'all_items'          => __( 'Lessons', 'learnpress' ),
						'view_item'          => __( 'View Lesson', 'learnpress' ),
						'add_new'            => __( 'Add New', 'learnpress' ),
						'edit_item'          => __( 'Edit Lesson', 'learnpress' ),
						'update_item'        => __( 'Update Lesson', 'learnpress' ),
						'search_items'       => __( 'Search Lessons', 'learnpress' ),
						'not_found'          => __( 'No lesson found', 'learnpress' ),
						'not_found_in_trash' => __( 'No lesson found in Trash', 'learnpress' ),
					),
					'public'             => true, // no access directly via lesson permalink url
					'query_var'          => true,
					'taxonomies'         => array( 'lesson_tag' ),
					'publicly_queryable' => true,
					'show_ui'            => true,
					'has_archive'        => false,
					'capability_type'    => LP_LESSON_CPT,
					'map_meta_cap'       => true,
					'show_in_menu'       => 'learn_press',
					'show_in_admin_bar'  => true,
					'show_in_nav_menus'  => true,
					'supports'           => array(
						'title',
						'editor',
						'thumbnail',
						'post-formats',
						'revisions',
						'comments'
						//'excerpt'
					),
					'hierarchical'       => true,
					'rewrite'            => array( 'slug' => 'lessons', 'hierarchical' => true, 'with_front' => false )
				);


		}

		/**
		 * Meta boxes.
		 */
		public function add_meta_boxes() {

			$meta_boxes = apply_filters( 'learn_press_lesson_meta_box_args',
				array(
					'id'     => 'lesson_settings',
					'title'  => __( 'Lesson Settings', 'learnpress' ),
					'pages'  => array( LP_LESSON_CPT ),
					'fields' => array(
						array(
							'name'         => __( 'Lesson Duration', 'learnpress' ),
							'id'           => '_lp_duration',
							'type'         => 'duration',
							'default_time' => 'minute',
							'desc'         => __( 'Duration of the lesson. Set 0 to disable.', 'learnpress' ),
							'std'          => 30,
						),
						array(
							'name' => __( 'Preview Lesson', 'learnpress' ),
							'id'   => '_lp_preview',
							'type' => 'yes-no',
							'desc' => __( 'If this is a preview lesson, then student can view this lesson content without taking the course.', 'learnpress' ),
							'std'  => 'no'
						)
					)
				)
			);

			new RW_Meta_Box( $meta_boxes );
			parent::add_meta_boxes();
		}

		/**
		 * Remove lesson form course items.
		 *
		 * @since 3.0.0
		 *
		 * @param $post_id
		 */
		public function before_delete_lesson( $post_id ) {
			// lesson curd
			$curd = new LP_Lesson_CURD();
			// remove lesson from course items
			$curd->delete( $post_id );
		}

		/**
		 * hide View Lesson link if not assigned to course
		 */
		public function hide_view_lesson_link() {
			$current_screen = get_current_screen();
			global $post;
			if ( ! $post ) {
				return;
			}
			if ( $current_screen->id === LP_LESSON_CPT && ! learn_press_get_item_course_id( $post->ID, $post->post_type ) ) {
				?>
                <style type="text/css">
                    #wp-admin-bar-view {
                        display: none;
                    }

                    #sample-permalink a {
                        pointer-events: none;
                        cursor: default;
                        text-decoration: none;
                        color: #666;
                    }

                    #preview-action {
                        display: none;
                    }
                </style>
				<?php
			}
		}

		/**
		 * Add columns to admin manage lesson page
		 *
		 * @param  array $columns
		 *
		 * @return array
		 */
		public function columns_head( $columns ) {

			// append new column after title column
			$pos         = array_search( 'title', array_keys( $columns ) );
			$new_columns = array(
				'author'      => __( 'Author', 'learnpress' ),
				LP_COURSE_CPT => __( 'Course', 'learnpress' )
			);
			if ( current_theme_supports( 'post-formats' ) ) {
				$new_columns['format']   = __( 'Format', 'learnpress' );
				$new_columns['duration'] = __( 'Duration', 'learnpress' );
			}
			$new_columns['preview'] = __( 'Preview', 'learnpress' );
			if ( false !== $pos && ! array_key_exists( LP_COURSE_CPT, $columns ) ) {
				$columns = array_merge(
					array_slice( $columns, 0, $pos + 1 ),
					$new_columns,
					array_slice( $columns, $pos + 1 )
				);

			}

			unset ( $columns['taxonomy-lesson-tag'] );
			$user = wp_get_current_user();
			if ( in_array( LP_TEACHER_ROLE, $user->roles ) ) {
				unset( $columns['author'] );
			}

			return $columns;
		}

		/**
		 * Display content for custom column
		 *
		 * @param string $name
		 * @param int $post_id
		 */
		public function columns_content( $name, $post_id = 0 ) {
			switch ( $name ) {
				case LP_COURSE_CPT:
					$courses = learn_press_get_item_courses( $post_id );
					if ( $courses ) {
						foreach ( $courses as $course ) {
							echo '<div><a href="' . esc_url( add_query_arg( array( 'filter_course' => $course->ID ) ) ) . '">' . get_the_title( $course->ID ) . '</a>';
							echo '<div class="row-actions">';
							printf( '<a href="%s">%s</a>', admin_url( sprintf( 'post.php?post=%d&action=edit', $course->ID ) ), __( 'Edit', 'learnpress' ) );
							echo "&nbsp;|&nbsp;";
							printf( '<a href="%s">%s</a>', get_the_permalink( $course->ID ), __( 'View', 'learnpress' ) );
							echo "&nbsp;|&nbsp;";
							if ( $course_id = learn_press_get_request( 'filter_course' ) ) {
								printf( '<a href="%s">%s</a>', remove_query_arg( 'filter_course' ), __( 'Remove Filter', 'learnpress' ) );
							} else {
								printf( '<a href="%s">%s</a>', add_query_arg( 'filter_course', $course->ID ), __( 'Filter', 'learnpress' ) );
							}
							echo '</div></div>';
						}

					} else {
						_e( 'Not assigned yet', 'learnpress' );
					}

					break;
				case 'preview':
					printf(
						'<input type="checkbox" class="learn-press-checkbox learn-press-toggle-lesson-preview" %s value="%s" data-nonce="%s" />',
						get_post_meta( $post_id, '_lp_preview', true ) == 'yes' ? ' checked="checked"' : '',
						$post_id,
						wp_create_nonce( 'learn-press-toggle-lesson-preview' )
					);
					break;
				case 'format':
					learn_press_item_meta_format( $post_id, __( 'Standard', 'learnpress' ) );
					break;
				case 'duration':
					$duration = absint( get_post_meta( $post_id, '_lp_duration', true ) ) * 60;
					if ( $duration >= 600 ) {
						echo date( 'H:i:s', $duration );
					} elseif ( $duration > 0 ) {
						echo date( 'i:s', $duration );
					} else {
						echo '-';
					}
			}
		}

		/**
		 * @param $columns
		 *
		 * @return mixed
		 */
		public function sortable_columns( $columns ) {
			$columns[ LP_COURSE_CPT ] = 'course-name';
			$columns['author']        = 'author';

			return $columns;
		}

		private function _is_archive() {
			global $pagenow, $post_type;
			if ( ! is_admin() || ( $pagenow != 'edit.php' ) || ( LP_LESSON_CPT != $post_type ) ) {
				return false;
			}

			return true;
		}

		private function _get_orderby() {
			return isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : '';
		}

		private function _get_search() {
			return isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : false;
		}

		private function _filter_course() {
			return ! empty( $_REQUEST['filter_course'] ) ? absint( $_REQUEST['filter_course'] ) : false;
		}

		/**
		 * Admin scripts.
		 */
		public function admin_scripts() {
			if ( in_array( get_post_type(), array( LP_LESSON_CPT ) ) ) {
				wp_enqueue_script( 'jquery-caret', LP()->plugin_url( 'assets/js/vendor/jquery.caret.js' ) );
			}
		}

		/**
		 * Add admin params.
		 *
		 * @return array
		 */
		public function admin_params() {
			return array( 'notice_empty_lesson' => '' );
		}

		/**
		 * Enqueue script.
		 */
		public function enqueue_script() {
			if ( LP_LESSON_CPT != get_post_type() ) {
				return;
			}
			LP_Assets::enqueue_script( 'select2', LP_PLUGIN_URL . '/lib/meta-box/js/select2/select2.min.js' );
			LP_Assets::enqueue_style( 'select2', LP_PLUGIN_URL . '/lib/meta-box/css/select2/select2.css' );
			ob_start();
			?>
            <script>
                var form = $('#post');
                form.submit(function (evt) {
                    var $title = $('#title'),
                        is_error = false;
                    if (0 === $title.val().length) {
                        alert('<?php _e( 'Please enter the title of the lesson', 'learnpress' );?>');
                        $title.focus();
                        is_error = true;
                    }
                    if (is_error) {
                        evt.preventDefault();
                        return false;
                    }
                });
            </script>
			<?php
			$script = ob_get_clean();
			$script = preg_replace( '!</?script>!', '', $script );
			learn_press_enqueue_script( $script );
		}

		/**
		 * Lesson assigned view.
		 *
		 * @since 3.0.0
		 */
		public static function lesson_assigned() {
			learn_press_admin_view( 'meta-boxes/course/assigned.php' );
		}

		/**
		 * @return LP_Lesson_Post_Type|null
		 */
		public static function instance() {
			if ( ! self::$_instance ) {
				self::$_instance = new self( LP_LESSON_CPT );
			}

			return self::$_instance;
		}
	}

	// LP_Lesson_Post_Type
	$lesson_post_type = LP_Lesson_Post_Type::instance();

	// add meta box
	$lesson_post_type
		->add_meta_box( 'lesson_assigned', __( 'Assigned', 'learnpress' ), 'lesson_assigned', 'side', 'high' );
}
