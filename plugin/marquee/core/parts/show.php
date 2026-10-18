<?php
/**
 * Tonight's Show: radio, hours, Now Playing, directions.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
	<section class="rr-panel rr-panel--red rr-show" aria-labelledby="rr-show-h">
		<div class="rr-show__head">
			<h2 id="rr-show-h" class="rr-h"><span class="rr-h__tree"><?php echo ridgeroad_cicon( 'tree' ); ?></span><?php esc_html_e( 'Tonight’s Show', 'ridgeroad' ); ?></h2>
			<span class="rr-badge rr-badge--<?php echo esc_attr( $status['state'] ); ?>" data-rr="badge"><i></i><span><?php echo esc_html( $status['badge'] ); ?></span></span>
		</div>
		<div class="rr-show__row">
			<div class="rr-tile">
				<span class="rr-tile__icon rr-gold"><?php echo ridgeroad_cicon( 'radio' ); ?></span>
				<div>
					<span class="rr-tile__label"><?php esc_html_e( 'Tune Your Radio', 'ridgeroad' ); ?></span>
					<strong class="rr-radio"><?php echo esc_html( ridgeroad_mod( 'radio' ) ); ?></strong>
				</div>
			</div>
			<div class="rr-tile">
				<span class="rr-tile__icon rr-gold"><?php echo ridgeroad_cicon( 'clock' ); ?></span>
				<div>
					<span class="rr-tile__label"><?php esc_html_e( 'Show Hours', 'ridgeroad' ); ?></span>
					<span class="rr-hours"><b><?php echo esc_html( ridgeroad_mod( 'weeknight_label' ) ); ?>:</b> <?php echo esc_html( ( ridgeroad_mod( 'use_sunset' ) ? __( 'Sunset', 'ridgeroad' ) : ridgeroad_clock( ridgeroad_mod( 'weeknight_start' ) ) ) . ' – ' . ridgeroad_clock( ridgeroad_mod( 'weeknight_close' ) ) ); ?></span>
					<span class="rr-hours"><b><?php echo esc_html( ridgeroad_mod( 'weekend_label' ) ); ?>:</b> <?php echo esc_html( ( ridgeroad_mod( 'use_sunset' ) ? __( 'Sunset', 'ridgeroad' ) : ridgeroad_clock( ridgeroad_mod( 'weekend_start' ) ) ) . ' – ' . ridgeroad_clock( ridgeroad_mod( 'weekend_close' ) ) ); ?></span>
				</div>
			</div>
		</div>
		<div class="rr-tile rr-np<?php echo $np['title'] ? ' is-playing' : ''; ?>" data-rr="np">
			<span class="rr-tile__icon rr-np__note"><?php echo ridgeroad_cicon( 'music' ); ?></span>
			<div class="rr-np__copy">
				<span class="rr-tile__label"><?php esc_html_e( 'Now Playing', 'ridgeroad' ); ?></span>
				<strong class="rr-np__title" data-rr="np-title" aria-live="polite"><?php echo esc_html( $np_text ); ?></strong>
				<span class="rr-np__next" data-rr="np-next"<?php echo $np['next'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Up next:', 'ridgeroad' ); ?> <span><?php echo esc_html( $np['next'] ); ?></span></span>
			</div>
			<div class="rr-eq" aria-hidden="true"><?php echo str_repeat( '<i></i>', 28 ); ?></div>
		</div>
		<p class="rr-show__note" data-rr="note"><?php echo esc_html( $status['note'] ); ?></p>
		<?php $dir = ridgeroad_directions_url(); if ( $dir ) : ?>
			<div class="rr-dir">
				<a class="rr-btn rr-btn--gold" href="<?php echo esc_url( $dir ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_cicon( 'pin' ); ?><?php esc_html_e( 'Get Directions', 'ridgeroad' ); ?></a>
				<?php if ( ridgeroad_mod( 'parking_note' ) ) : ?><span class="rr-dir__note"><?php echo ridgeroad_cicon( 'car' ); ?><?php echo esc_html( ridgeroad_mod( 'parking_note' ) ); ?></span><?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
