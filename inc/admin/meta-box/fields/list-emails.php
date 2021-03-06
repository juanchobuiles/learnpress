<?php
/**
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 3.0.0
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RWMB_List_Emails_Field' ) ) {
	class RWMB_List_Emails_Field extends RWMB_Field {
		/**
		 * Get field HTML
		 *
		 * @param mixed $meta
		 * @param mixed $field
		 *
		 * @return string
		 */
		public static function html( $meta, $field = '' ) {
			$emails = LP_Emails::instance()->emails;
			ob_start();
			?>
            <table class="learn-press-emails">
                <thead>
                <tr>
                    <th><?php _e( 'Email', 'learnpress' ); ?></th>
                    <th><?php _e( 'Description', 'learnpress' ); ?></th>
                    <th class="status"><?php _e( 'Status', 'learnpress' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $emails as $email ) {
					$group = '';
					if ( $email->group ) {
						$url = esc_url( add_query_arg( array(
							'section'     => $email->group->group_id,
							'sub-section' => $email->id
						), admin_url( 'admin.php?page=learn-press-settings&tab=emails' ) ) );

						$group = $email->group;
					} else {
						$url = esc_url( add_query_arg( array( 'section' => $email->id ), admin_url( 'admin.php?page=learn-press-settings&tab=emails' ) ) );
					} ?>
                    <tr>
                        <td class="name">
                            <a href="<?php echo $url; ?>"><?php echo join( ' &rarr; ', array( $group, $email->title ) ); ?></a>
                        </td>
                        <td class="description"><?php echo $email->description; ?></td>
                        <td class="status<?php echo $email->enable ? ' enabled' : ( $email->is_configured() ? '' : ' config' ); ?>">
							<?php if ( $email->is_configured() ) { ?>
                                <span class="change-email-status dashicons dashicons-yes"
                                      data-status="<?php echo $email->enable ? 'on' : 'off'; ?>"
                                      data-id="<?php echo $email->id; ?>"></span>
							<?php } else { ?>
                                <a href="<?php echo $url; ?>"><?php _e( 'Settings', 'learnpress' ); ?></a>
							<?php } ?>
                        </td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
			<?php
			return ob_get_clean();
		}
	}
}