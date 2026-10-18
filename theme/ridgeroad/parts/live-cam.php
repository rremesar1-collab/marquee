<?php
/**
 * Watch Live (YouTube).
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<?php $live_embed = ridgeroad_youtube_embed( ridgeroad_mod( 'live_url' ) ); ?>
<?php if ( $live_embed ) : ?>
<section id="live" class="rr-live" aria-labelledby="rr-live-h">
	<h2 id="rr-live-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'live_title' ) ); ?></span></h2>
	<div class="rr-panel rr-panel--red rr-live__panel">
		<div class="rr-live__frame" data-rr-live="<?php echo esc_url( $live_embed ); ?>">
			<button class="rr-live__poster" type="button" aria-label="<?php esc_attr_e( 'Play the live camera', 'ridgeroad' ); ?>">
				<span class="rr-live__badge"><i></i><?php esc_html_e( 'LIVE CAM', 'ridgeroad' ); ?></span>
				<span class="rr-live__play"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#e5121e"/><path d="M26 20l20 12-20 12z" fill="#fff"/></svg></span>
				<span class="rr-live__cta"><?php esc_html_e( 'Tap to watch & listen', 'ridgeroad' ); ?></span>
			</button>
		</div>
		<p class="rr-live__text"><?php echo esc_html( ridgeroad_mod( 'live_text' ) ); ?> <small><?php esc_html_e( 'If the stream is off, check back during show hours.', 'ridgeroad' ); ?></small></p>
	</div>
</section>
<?php endif; ?>
