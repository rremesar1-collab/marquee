<?php
/**
 * Home page: the newest kids' light shows from the contest's Show Wall.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 * Only shows when the contest is on and at least one show is approved.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! ridgeroad_mod( 'wall_home' ) || ! ridgeroad_mod( 'kids_show' ) || ! ridgeroad_mod( 'lab_on' ) || ! ridgeroad_mod( 'contest_on' ) || ! ridgeroad_kids_url() ) {
	return;
}
$rr_shows = ridgeroad_art_entries( 6 );
if ( ! $rr_shows ) {
	return;
}
$rr_open = ridgeroad_contest_open();
$rr_all  = ridgeroad_kids_url() . '#show-wall';
?>
<section id="kids-shows" class="rr-homewall" aria-labelledby="rr-homewall-h">
	<h2 id="rr-homewall-h" class="rr-ribbon"><span><?php esc_html_e( 'Kids’ Show Wall', 'ridgeroad' ); ?></span></h2>
	<p class="rr-homewall__text"><?php echo $rr_open ? esc_html__( 'Light shows made by our young light designers. Watch one and give your favorites a heart!', 'ridgeroad' ) : esc_html__( 'Light shows made by our young light designers. Tap one to watch it!', 'ridgeroad' ); ?></p>
	<div class="rr-homewall__grid">
		<?php foreach ( $rr_shows as $e ) :
			$rr_title = $e['title'] ? $e['title'] : __( 'My Light Show', 'ridgeroad' ); ?>
			<a class="rr-homewall__card<?php echo ( $e['judges'] || $e['people'] ) ? ' is-winner' : ''; ?>" href="<?php echo esc_url( ridgeroad_show_watch_url( $e['id'] ) ); ?>">
				<span class="rr-homewall__pic">
					<img src="<?php echo esc_url( $e['img'] ); ?>" alt="" loading="lazy">
					<span class="rr-wall__play" aria-hidden="true">&#9654;</span>
				</span>
				<b class="rr-homewall__title"><?php echo esc_html( $rr_title ); ?></b>
				<span class="rr-homewall__by"><?php echo esc_html( sprintf( '%s, age %d', $e['name'], $e['age'] ) ); ?></span>
				<?php if ( $e['judges'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'Judges’ Pick', 'ridgeroad' ); ?></em><?php endif; ?>
				<?php if ( $e['people'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'People’s Choice', 'ridgeroad' ); ?></em><?php endif; ?>
				<span class="rr-homewall__hearts" aria-label="<?php echo esc_attr( sprintf( _n( '%d heart', '%d hearts', $e['votes'], 'ridgeroad' ), $e['votes'] ) ); ?>"><b aria-hidden="true">&#9829;</b> <?php echo (int) $e['votes']; ?></span>
				<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Watch the show by %s', 'ridgeroad' ), $e['name'] ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<p class="rr-homewall__more"><a class="rr-btn rr-btn--red" href="<?php echo esc_url( $rr_all ); ?>"><?php echo $rr_open ? esc_html__( 'See all shows & vote', 'ridgeroad' ) : esc_html__( 'See all the shows', 'ridgeroad' ); ?> &rarr;</a></p>
</section>
