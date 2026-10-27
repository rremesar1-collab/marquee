<?php
/**
 * Status banner (show tonight / canceled / off season).
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<section id="status" class="rr-panel rr-panel--green rr-banner rr-banner--<?php echo esc_attr( $b_mod ); ?>" data-rr-banner data-mod="<?php echo esc_attr( $b_mod ); ?>"<?php echo ridgeroad_mod( 'ok_hide' ) ? ' data-ok-hide' . ( 'ok' === $b_mod ? ' hidden' : '' ) : ''; ?>>
	<span class="rr-banner__flake"><?php echo ridgeroad_cicon( 'snow' ); ?></span>
	<span class="rr-banner__icon"><?php echo ridgeroad_cicon( 'cloud', 'rr-bi-cloud' ) . ridgeroad_cicon( 'star', 'rr-bi-star' ); ?></span>
	<div class="rr-banner__copy">
		<h2 class="rr-banner__title" data-rr="banner-title"><?php echo esc_html( $b_title ); ?></h2>
		<p class="rr-banner__text" data-rr="banner-text"><?php echo nl2br( esc_html( $b_text ) ); ?></p>
	</div>
	<span class="rr-banner__flake rr-banner__flake--r"><?php echo ridgeroad_cicon( 'snow' ); ?></span>
</section>
