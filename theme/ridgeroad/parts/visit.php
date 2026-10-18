<?php
/**
 * Before You Visit tips.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<section id="visit" class="rr-visit" aria-labelledby="rr-visit-h">
	<h2 id="rr-visit-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'tips_title' ) ); ?></span></h2>
	<div class="rr-grid rr-grid--tips">
		<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
			<?php if ( ! ridgeroad_mod( "tip{$i}_title" ) ) { continue; } ?>
			<article class="rr-panel rr-panel--green rr-tip">
				<span class="rr-tip__icon rr-tip__icon--<?php echo esc_attr( ridgeroad_mod( "tip{$i}_icon" ) ); ?>"><?php echo ridgeroad_cicon( ridgeroad_mod( "tip{$i}_icon" ) ); ?></span>
				<h3><?php echo esc_html( ridgeroad_mod( "tip{$i}_title" ) ); ?></h3>
				<p><?php echo esc_html( ridgeroad_mod( "tip{$i}_text" ) ); ?></p>
			</article>
		<?php endfor; ?>
	</div>
</section>
