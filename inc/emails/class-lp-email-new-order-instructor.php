<?php
/**
 * Email for instructor when has new order.
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Email_New_Order_Instructor' ) ) {

	/**
	 * Class LP_Email_New_Order_Instructor
	 */
	class LP_Email_New_Order_Instructor extends LP_Email_Type_Order {
		/**
		 * LP_Email_New_Order_Instructor constructor.
		 */
		public function __construct() {
			$this->id          = 'new-order-instructor';
			$this->title       = __( 'Instructor', 'learnpress' );
			$this->description = __( 'Send email to course\'s instructor when user has purchased course.', 'learnpress' );

			$this->default_subject = __( 'New order placed on {{order_date}}', 'learnpress' );
			$this->default_heading = __( 'New user order', 'learnpress' );

			parent::__construct();
		}

		/**
		 * Trigger email notification.
		 *
		 * @param $order_id
		 *
		 * @return bool|mixed
		 */
		public function trigger( $order_id ) {
			if ( ! $this->enable ) {
				return false;
			}

			$this->order_id = $order_id;

			$instructors = $this->get_course_instructors();

			if ( ! $instructors ) {
				return false;
			}

			$return = array();

			foreach ( $instructors as $user_id ) {
				$user = get_user_by( 'ID', $user_id );
				if ( ! $user ) {
					continue;
				}
				$this->recipient     = $user->user_email;
				$this->instructor_id = $user_id;

				$this->get_object();
				$this->get_variable();

				if ( $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), array(), $this->get_attachments() ) ) {
					$return[] = $this->get_recipient();
				}
			}

			return $return;
		}
	}
}

return new LP_Email_New_Order_Instructor();