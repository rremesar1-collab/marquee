<?php
/**
 * Stay Connected.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<section class="rr-panel rr-panel--night rr-social">
	<h2 class="rr-h"><span class="rr-h__tree"><?php echo ridgeroad_cicon( 'tree' ); ?></span><?php echo esc_html( ridgeroad_mod( 'social_title' ) ); ?></h2>
	<p class="rr-social__text"><?php echo esc_html( ridgeroad_mod( 'social_text' ) ); ?></p>
	<div class="rr-social__btns">
		<?php foreach ( ridgeroad_social_links() as $net => $l ) : ?>
			<a class="rr-btn rr-btn--<?php echo esc_attr( $l['class'] ); ?>" href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_cicon( $net ); ?><?php echo esc_html( $l['label'] ); ?></a>
		<?php endforeach; ?>
		<?php if ( ridgeroad_mod( 'social_share' ) ) : ?>
			<button class="rr-btn rr-btn--gold" type="button" data-rr-share data-title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-url="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo ridgeroad_cicon( 'gift' ); ?><span><?php esc_html_e( 'Share the Show', 'ridgeroad' ); ?></span></button>
		<?php endif; ?>
	</div>
	<?php if ( ridgeroad_mod( 'social_hashtag' ) ) : ?>
		<p class="rr-social__tag"><?php echo ridgeroad_cicon( 'camera' ); ?><span><?php esc_html_e( 'Snap a photo? Tag it', 'ridgeroad' ); ?> <strong><?php echo esc_html( ridgeroad_mod( 'social_hashtag' ) ); ?></strong></span></p>
	<?php endif; ?>
</section>
