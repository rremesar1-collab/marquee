<?php
/**
 * Behind the Lights numbers.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
<?php if ( ridgeroad_mod( 'stats_show' ) ) : ?>
<section id="stats" class="rr-stats-sec" aria-labelledby="rr-stats-h">
	<h2 id="rr-stats-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'stats_title' ) ); ?></span></h2>
	<?php if ( ridgeroad_mod( 'stats_text' ) ) : ?><p class="rr-stats-sec__text"><?php echo esc_html( ridgeroad_mod( 'stats_text' ) ); ?></p><?php endif; ?>


	<div class="rr-statgrid">
		<?php for ( $i = 1; $i <= 6; $i++ ) :
			$label = ridgeroad_mod( "stat{$i}_label" );
			$num   = trim( (string) ridgeroad_mod( "stat{$i}_num" ) );
			if ( 3 === $i && '' === $num && ! empty( $np['playlist'] ) ) {
				$num = (string) count( $np['playlist'] );
			}
			if ( ! $label || '' === $num ) { continue; } ?>
			<div class="rr-stat">
				<span class="rr-stat__icon"><?php echo ridgeroad_cicon( ridgeroad_mod( "stat{$i}_icon" ) ); ?></span>
				<b class="rr-stat__num" data-count="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $num ); ?></b>
				<span class="rr-stat__label"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endfor; ?>
	</div>
</section>
<?php endif; ?>
