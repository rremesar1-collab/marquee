<?php
/**
 * "Win a prize!" box at the top of Make a Light Show (contest open).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dead   = ridgeroad_contest_date( 'contest_deadline' );
$pickup = ridgeroad_contest_date( 'contest_pickup' );
$prize1 = ridgeroad_mod( 'contest_prize1' );
$prize2 = ridgeroad_mod( 'contest_prize2' );
?>
<div class="rr-kc__prize">
	<div class="rr-kc__prize-head"><?php echo ridgeroad_cicon( 'gift' ); ?><div><b><?php esc_html_e( 'Win a prize!', 'ridgeroad' ); ?></b><span><?php
		/* translators: %s: deadline date */
		echo $dead ? esc_html( sprintf( __( 'Make a light show and send it in by %s. Kids ages 3 to 12.', 'ridgeroad' ), $dead ) ) : esc_html__( 'Make a light show and send it in. Kids ages 3 to 12.', 'ridgeroad' );
	?></span></div></div>
	<ul class="rr-kc__prizes">
		<li><small><?php esc_html_e( 'Judges’ Pick', 'ridgeroad' ); ?></small><strong><?php echo esc_html( $prize1 ); ?></strong><span><?php esc_html_e( 'Our family picks the most creative show', 'ridgeroad' ); ?></span></li>
		<li><small><?php esc_html_e( 'People’s Choice', 'ridgeroad' ); ?></small><strong><?php echo esc_html( $prize2 ); ?></strong><span><?php esc_html_e( 'The show with the most hearts wins', 'ridgeroad' ); ?></span></li>
	</ul>
	<details class="rr-rules">
		<summary><?php esc_html_e( 'Official contest rules', 'ridgeroad' ); ?></summary>
		<ol>
			<li><?php esc_html_e( 'Who can enter: kids ages 3 to 12. A parent or guardian must send the entry.', 'ridgeroad' ); ?></li>
			<li><?php esc_html_e( 'How to enter: make a light show in the Christmas Light Lab on this page and send it with the form. One entry per child.', 'ridgeroad' ); ?></li>
			<li><?php echo esc_html( ridgeroad_is_marquee() ? sprintf( __( 'Deadline: entries and hearts close at 11:59 PM (%1$s) on %2$s.', 'ridgeroad' ), wp_date( 'T', null, ridgeroad_tz() ), $dead ) : sprintf( __( 'Deadline: entries and hearts close at 11:59 PM Central time on %s.', 'ridgeroad' ), $dead ) ); ?></li>
			<li><?php echo esc_html( sprintf( ridgeroad_is_marquee() ? __( 'Judges’ Pick: chosen by our family for creativity and effort. Prize: %s.', 'ridgeroad' ) : __( 'Judges’ Pick: chosen by the Lights On Ridge Road family for creativity and effort. Prize: %s.', 'ridgeroad' ), $prize1 ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'People’s Choice: the show with the most hearts on the Show Wall when voting closes. One heart per show from each device. Hearts that look automated may be removed. Prize: %s.', 'ridgeroad' ), $prize2 ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Winners are announced on this page by first name and age. Prizes are picked up at the house during show hours by %s, with a parent and the claim code. Prizes not picked up by then may go to the runner-up.', 'ridgeroad' ), $pickup ) ); ?></li>
			<li><?php esc_html_e( 'Privacy: only your child’s first name, age and show appear on the website. A parent email is required so we can send the claim code and contact winners. It is used only for the contest and never shown or shared.', 'ridgeroad' ); ?></li>
			<li><?php esc_html_e( 'No purchase or donation is necessary. Every entry is reviewed before it is shown, and we may decline entries that are not appropriate.', 'ridgeroad' ); ?></li>
		</ol>
	</details>
</div>
