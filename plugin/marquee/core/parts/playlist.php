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
<?php $pl_long = count( $np['playlist'] ) > 12; // a long list scrolls inside its own box instead of making the page long ?>
<section id="playlist" class="rr-playlist<?php echo $pl_long ? ' is-long' : ''; ?>" aria-labelledby="rr-pl-h" data-rr-pl-when="<?php echo esc_attr( $pl_when ); ?>"<?php echo $pl_vis ? '' : ' hidden'; ?>>
	<h2 id="rr-pl-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'playlist_title' ) ); ?></span></h2>
	<div class="rr-panel rr-panel--green rr-playlist__panel">
		<?php
		// song voting is on: a button straight to it
		$pl_vote = ridgeroad_mod( 'songs_show' ) && function_exists( 'ridgeroad_songs_url' ) && ridgeroad_songs_url() && function_exists( 'ridgeroad_song_list' ) && ridgeroad_song_list() ? ridgeroad_songs_url() . '#vote' : '';
		?>
		<?php if ( $pl_long || $pl_vote ) : ?>
			<div class="rr-pl__top">
				<?php if ( $pl_long ) : ?>
					<p class="rr-pl__hint"><?php echo esc_html( sprintf( /* translators: %d: number of songs */ __( '%d songs tonight. Scroll the list to see them all.', 'ridgeroad' ), count( $np['playlist'] ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $pl_vote ) : ?>
					<a class="rr-btn rr-btn--red rr-pl__vote" href="<?php echo esc_url( $pl_vote ); ?>"><?php echo ridgeroad_cicon( 'heart' ); ?><?php esc_html_e( 'Vote for your favorite', 'ridgeroad' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<ol class="rr-pl" data-rr="playlist"<?php echo $pl_long ? ' tabindex="0" aria-label="' . esc_attr__( 'Tonight’s songs', 'ridgeroad' ) . '"' : ''; ?>>
			<?php foreach ( $np['playlist'] as $i => $song ) : ?>
				<li class="rr-pl__item<?php echo ( $np['title'] && $song === $np['title'] ) ? ' is-now' : ''; ?>" data-song="<?php echo esc_attr( $song ); ?>">
					<span class="rr-pl__num"><?php echo (int) $i + 1; ?></span>
					<?php $art = function_exists( 'ridgeroad_song_cover' ) ? ridgeroad_song_cover( $song ) : ''; ?>
					<?php if ( $art ) : ?><img class="rr-pl__art" src="<?php echo esc_url( $art ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php endif; ?>
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
