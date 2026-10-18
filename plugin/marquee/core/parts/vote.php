<?php
/**
 * Vote for Your Favorite Song + live leaderboard + Fan Favorite Friday.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! ridgeroad_mod( 'songs_show' ) ) {
	return;
}
$songs = ridgeroad_song_list();
if ( ! $songs ) {
	return;
}
$v      = ridgeroad_votes_public();
$np     = isset( $args['np'] ) ? $args['np'] : array();
$now    = isset( $np['title'] ) ? $np['title'] : '';
$counts = (array) $v['counts'];
$ff     = $v['friday'];
$ff_txt = ridgeroad_mod( 'songs_friday_text' );
?>
<section id="vote" class="rr-vote" aria-labelledby="rr-vote-h" data-rr-vote data-ff-text="<?php echo esc_attr( $ff_txt ); ?>">
	<h2 id="rr-vote-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'songs_title' ) ); ?></span></h2>
	<?php if ( ridgeroad_mod( 'songs_text' ) ) : ?><p class="rr-kids__intro"><?php echo esc_html( ridgeroad_mod( 'songs_text' ) ); ?></p><?php endif; ?>

	<?php if ( $ff ) : ?>
		<div class="rr-ff<?php echo $ff['today'] ? ' is-today' : ''; ?>" data-rr-ff>
			<span class="rr-ff__icon"><?php echo ridgeroad_cicon( 'star' ); ?></span>
			<div class="rr-ff__body" data-rr-ff-body>
				<?php if ( $ff['today'] && $ff['leader'] ) : ?>
					<b><?php esc_html_e( 'It’s Fan Favorite Friday!', 'ridgeroad' ); ?></b>
					<span><?php esc_html_e( 'This week’s winner is', 'ridgeroad' ); ?> <strong><?php echo esc_html( $ff['leader']['title'] ); ?></strong>. <?php echo esc_html( $ff_txt ); ?></span>
				<?php elseif ( $ff['today'] ) : ?>
					<b><?php esc_html_e( 'It’s Fan Favorite Friday!', 'ridgeroad' ); ?></b>
					<span><?php esc_html_e( 'No votes yet this week. Vote now to pick next Friday’s favorite!', 'ridgeroad' ); ?></span>
				<?php else : ?>
					<b><?php
						/* translators: %d: days until Friday */
						echo esc_html( 1 === $ff['days'] ? __( 'Fan Favorite Friday is tomorrow!', 'ridgeroad' ) : sprintf( __( 'Fan Favorite Friday is in %d days', 'ridgeroad' ), $ff['days'] ) );
					?></b>
					<span><?php if ( $ff['leader'] ) : ?><?php esc_html_e( 'Leading this week:', 'ridgeroad' ); ?> <strong><?php echo esc_html( $ff['leader']['title'] ); ?></strong>. <?php endif; ?><?php esc_html_e( 'The song with the most votes this week gets featured on Friday!', 'ridgeroad' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="rr-grid rr-grid--vote">
		<div class="rr-panel rr-panel--red rr-vote__songs">
			<h3 class="rr-h"><span class="rr-h__star"><?php echo ridgeroad_cicon( 'music' ); ?></span><?php esc_html_e( 'Pick Your Favorite', 'ridgeroad' ); ?></h3>
			<ol class="rr-vlist">
				<?php foreach ( $songs as $i => $s ) : ?>
					<li class="rr-vlist__item<?php echo ( $now && $now === $s['title'] ) ? ' is-now' : ''; ?>" data-key="<?php echo esc_attr( $s['key'] ); ?>" data-title="<?php echo esc_attr( $s['title'] ); ?>">
						<span class="rr-vlist__num"><?php echo (int) $i + 1; ?></span>
						<span class="rr-vlist__name"><b><?php echo esc_html( $s['title'] ); ?></b><?php if ( $s['artist'] ) : ?><small><?php echo esc_html( $s['artist'] ); ?></small><?php endif; ?></span>
						<?php if ( $s['new'] ) : ?><em class="rr-vlist__new"><?php esc_html_e( 'New', 'ridgeroad' ); ?></em><?php endif; ?>
						<span class="rr-vlist__now"><?php esc_html_e( 'Now Playing', 'ridgeroad' ); ?></span>
						<button type="button" class="rr-vlist__vote" aria-label="<?php echo esc_attr( sprintf( __( 'Vote for %s', 'ridgeroad' ), $s['title'] ) ); ?>"><b aria-hidden="true">&#9829;</b><span><?php echo (int) ( $counts[ $s['key'] ] ?? 0 ); ?></span></button>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>

		<div class="rr-panel rr-panel--blue rr-vote__board">
			<h3 class="rr-h"><span class="rr-h__star"><?php echo ridgeroad_cicon( 'star' ); ?></span><?php esc_html_e( 'Leaderboard', 'ridgeroad' ); ?></h3>
			<div class="rr-lbtabs" role="group" aria-label="<?php esc_attr_e( 'Leaderboard period', 'ridgeroad' ); ?>">
				<button type="button" data-board="week" aria-pressed="true"><?php esc_html_e( 'This Week', 'ridgeroad' ); ?></button>
				<button type="button" data-board="season" aria-pressed="false"><?php esc_html_e( 'All Season', 'ridgeroad' ); ?></button>
			</div>
			<ol class="rr-lb" data-rr-lb></ol>
			<p class="rr-lb__empty" data-rr-lb-empty hidden><?php esc_html_e( 'No votes yet. Be the first!', 'ridgeroad' ); ?></p>
			<p class="rr-lb__total" data-rr-lb-total></p>
			<p class="rr-lb__mine" data-rr-mine hidden></p>
			<p class="rr-lb__msg" data-rr-vote-msg aria-live="polite"></p>
			<script type="application/json" data-rr-votes><?php echo wp_json_encode( $v ); ?></script>
		</div>
	</div>
</section>
