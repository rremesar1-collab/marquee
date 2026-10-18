<?php
/**
 * Donate.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<?php if ( ridgeroad_mod( 'cashapp_url' ) ) : ?>
	<section class="rr-panel rr-panel--red rr-donate">
		<div class="rr-donate__copy">
			<h2 class="rr-h"><span class="rr-h__heart"><?php echo ridgeroad_cicon( 'heart' ); ?></span><?php echo esc_html( ridgeroad_mod( 'donate_title' ) ); ?></h2>
			<p><?php echo esc_html( ridgeroad_mod( 'donate_text' ) ); ?></p>
			<a class="rr-btn rr-btn--red" href="<?php echo esc_url( ridgeroad_mod( 'cashapp_url' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( ridgeroad_mod( 'cashapp_label' ) ); ?><?php echo ridgeroad_cicon( 'external' ); ?></a>
		</div>
		<div class="rr-qr">
			<?php if ( ridgeroad_mod( 'cashapp_qr' ) ) : ?>
				<img src="<?php echo esc_url( ridgeroad_mod( 'cashapp_qr' ) ); ?>" alt="<?php esc_attr_e( 'Cash App QR code', 'ridgeroad' ); ?>" width="150" height="150">
			<?php else : ?>
				<div class="rr-qr__gen" data-qr="<?php echo esc_url( ridgeroad_mod( 'cashapp_url' ) ); ?>" role="img" aria-label="<?php esc_attr_e( 'Cash App QR code', 'ridgeroad' ); ?>"></div>
			<?php endif; ?>
			<span class="rr-qr__badge">$</span>
		</div>
	</section>
<?php endif; ?>
