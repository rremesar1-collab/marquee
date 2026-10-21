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
$rr_shows = ridgeroad_art_entries( 4 );
if ( ! $rr_shows ) {
	return;
}
$rr_open  = ridgeroad_contest_open();
$rr_kids  = ridgeroad_kids_url();
$rr_dead  = ridgeroad_contest_date( 'contest_deadline' );
$rr_prize = array_filter( array( ridgeroad_mod( 'contest_prize1' ), ridgeroad_mod( 'contest_prize2' ) ) );
if ( 3 === count( $rr_shows ) ) {
	$rr_shows = array_slice( $rr_shows, 0, 2 ); // 1, 2 or 4 fill the grid evenly
}
$rr_n     = count( $rr_shows );
?>
<section id="kids-shows" class="rr-homewall" aria-labelledby="rr-homewall-h">
	<h2 id="rr-homewall-h" class="rr-ribbon"><span><?php esc_html_e( 'Kids’ Show Wall', 'ridgeroad' ); ?></span></h2>
	<div class="rr-panel rr-panel--blue rr-homewall__box">
		<div class="rr-homewall__intro">
			<span class="rr-homewall__icon"><?php echo ridgeroad_cicon( 'star' ); ?></span>
			<h3><?php echo $rr_open ? esc_html__( 'Light shows made by kids', 'ridgeroad' ) : esc_html__( 'Our young light designers', 'ridgeroad' ); ?></h3>
			<p><?php echo $rr_open
				? esc_html__( 'Kids design their own light show for our house in the Christmas Light Lab. Watch them and give your favorites a heart: the show with the most hearts wins People’s Choice!', 'ridgeroad' )
				: esc_html__( 'Kids designed their own light shows for our house in the Christmas Light Lab. Tap one to watch it!', 'ridgeroad' ); ?></p>
			<?php if ( $rr_open && ( $rr_prize || $rr_dead ) ) : ?>
				<p class="rr-homewall__prize"><?php echo ridgeroad_cicon( 'gift' ); ?> <span><?php
					/* translators: 1: prizes, 2: deadline */
					echo esc_html( trim( ( $rr_prize ? sprintf( __( 'Prizes: %s.', 'ridgeroad' ), implode( ' & ', $rr_prize ) ) : '' ) . ' ' . ( $rr_dead ? sprintf( __( 'Enter and vote by %s.', 'ridgeroad' ), $rr_dead ) : '' ) ) );
				?></span></p>
			<?php endif; ?>
			<p class="rr-homewall__btns">
				<a class="rr-btn rr-btn--red" href="<?php echo esc_url( $rr_kids . '#show-wall' ); ?>"><?php echo $rr_open ? esc_html__( 'See all shows & vote', 'ridgeroad' ) : esc_html__( 'See all the shows', 'ridgeroad' ); ?></a>
				<?php if ( $rr_open ) : ?><a class="rr-btn rr-btn--gold" href="<?php echo esc_url( $rr_kids . '#light-show' ); ?>"><?php esc_html_e( 'Make your own', 'ridgeroad' ); ?></a><?php endif; ?>
			</p>
		</div>
		<div class="rr-homewall__grid" data-count="<?php echo (int) $rr_n; ?>">
			<?php foreach ( $rr_shows as $e ) :
				$rr_title = $e['title'] ? $e['title'] : __( 'My Light Show', 'ridgeroad' ); ?>
				<a class="rr-homewall__card<?php echo ( $e['judges'] || $e['people'] ) ? ' is-winner' : ''; ?>" href="<?php echo esc_url( ridgeroad_show_watch_url( $e['id'] ) ); ?>">
					<span class="rr-homewall__pic">
						<img src="<?php echo esc_url( $e['img'] ); ?>" alt="" loading="lazy">
						<span class="rr-wall__play" aria-hidden="true">&#9654;</span>
					</span>
					<span class="rr-homewall__meta">
						<b class="rr-homewall__title"><?php echo esc_html( $rr_title ); ?></b>
						<span class="rr-homewall__by"><?php echo esc_html( sprintf( '%s, age %d', $e['name'], $e['age'] ) ); ?></span>
						<?php if ( $e['judges'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'Judges’ Pick', 'ridgeroad' ); ?></em><?php endif; ?>
						<?php if ( $e['people'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'People’s Choice', 'ridgeroad' ); ?></em><?php endif; ?>
					</span>
					<span class="rr-homewall__hearts" aria-label="<?php echo esc_attr( sprintf( _n( '%d heart', '%d hearts', $e['votes'], 'ridgeroad' ), $e['votes'] ) ); ?>"><b aria-hidden="true">&#9829;</b> <?php echo (int) $e['votes']; ?></span>
					<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Watch the show by %s', 'ridgeroad' ), $e['name'] ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
