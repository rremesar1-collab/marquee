<?php
/**
 * Tonight's playlist.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast

$pl_when = ridgeroad_mod( 'playlist_when' );
$pl_vis  = ridgeroad_playlist_visible( $status['state'] );
if ( ridgeroad_mod( 'show_playlist' ) && ! empty( $np['playlist'] ) ) : ?>
<section id="playlist" class="rr-playlist" aria-labelledby="rr-pl-h" data-rr-pl-when="<?php echo esc_attr( $pl_when ); ?>"<?php echo $pl_vis ? '' : ' hidden'; ?>>
	<h2 id="rr-pl-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'playlist_title' ) ); ?></span></h2>
	<div class="rr-panel rr-panel--green rr-playlist__panel">
		<ol class="rr-pl" data-rr="playlist">
			<?php foreach ( $np['playlist'] as $i => $song ) : ?>
				<li class="rr-pl__item<?php echo ( $np['title'] && $song === $np['title'] ) ? ' is-now' : ''; ?>" data-song="<?php echo esc_attr( $song ); ?>">
					<span class="rr-pl__num"><?php echo (int) $i + 1; ?></span>
					<span class="rr-pl__name"><?php echo esc_html( $song ); ?></span>
					<span class="rr-pl__eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
					<span class="rr-pl__tag"><?php esc_html_e( 'Now Playing', 'ridgeroad' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<p class="rr-pl__recent" data-rr="recent"<?php echo empty( $np['recent'] ) ? ' hidden' : ''; ?>><?php echo ridgeroad_cicon( 'music' ); ?><span><b><?php esc_html_e( 'Recently played:', 'ridgeroad' ); ?></b> <span data-rr="recent-list"><?php echo esc_html( implode( ' · ', $np['recent'] ) ); ?></span></span></p>
	</div>
</section>
<?php endif; ?>
