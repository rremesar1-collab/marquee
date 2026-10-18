<?php
/**
 * Countdown to the show and to Christmas.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<?php if ( ridgeroad_mod( 'show_countdown' ) ) :
	$xmas = new DateTimeImmutable( wp_date( 'Y', null, ridgeroad_tz() ) . '-12-25 00:00', ridgeroad_tz() );
	if ( $xmas->getTimestamp() + DAY_IN_SECONDS < time() ) {
		$xmas = $xmas->modify( '+1 year' );
	}
	?>
<section class="rr-count" aria-label="<?php esc_attr_e( 'Countdown', 'ridgeroad' ); ?>"
	data-rr-count data-opening="<?php echo (int) $status['opening_ts']; ?>" data-start="<?php echo (int) $status['start_ts']; ?>" data-end="<?php echo (int) $status['end_ts']; ?>" data-now="<?php echo (int) $status['now_ts']; ?>" data-xmas="<?php echo (int) $xmas->getTimestamp(); ?>">
	<p class="rr-count__label" data-rr="count-label"><?php esc_html_e( 'Countdown', 'ridgeroad' ); ?></p>
	<div class="rr-count__tiles" data-rr="count-tiles">
		<div class="rr-count__tile"><b data-u="d">00</b><span><?php esc_html_e( 'Days', 'ridgeroad' ); ?></span></div>
		<div class="rr-count__tile"><b data-u="h">00</b><span><?php esc_html_e( 'Hours', 'ridgeroad' ); ?></span></div>
		<div class="rr-count__tile"><b data-u="m">00</b><span><?php esc_html_e( 'Minutes', 'ridgeroad' ); ?></span></div>
		<div class="rr-count__tile"><b data-u="s">00</b><span><?php esc_html_e( 'Seconds', 'ridgeroad' ); ?></span></div>
	</div>
	<p class="rr-count__xmas"><?php echo ridgeroad_cicon( 'gift' ); ?><span data-rr="xmas"></span></p>
</section>
<?php endif; ?>
