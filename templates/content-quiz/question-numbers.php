<?php
/**
 * Template for displaying number question in quiz.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/content-quiz/question-numbers.php.
 *
 * @author  ThimPress
 * @package  Learnpress/Templates
 * @version  3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();
?>

<?php
$course    = LP_Global::course();
$quiz      = LP_Global::course_item_quiz();
$questions = $quiz->get_questions();
$user      = LP_Global::user();
$quiz_data = $user->get_quiz_data( $quiz->get_id() );
$result    = $quiz_data->get_results();
$position  = 0;
?>

<ul class="question-numbers">

	<?php foreach ( $questions as $question_id ) {
		$position ++;
		$class = array( "question-" . $position );

		if ( $quiz->is_viewing_question( $question_id ) ) {
			$class[] = 'current';
		} ?>

        <li class="<?php echo join( ' ', $class ); ?>">
            <a href="<?php echo $quiz->get_question_link( $question_id ); ?>">
                <span><?php echo $position; ?></span>
            </a>
        </li>

	<?php } ?>

</ul>
