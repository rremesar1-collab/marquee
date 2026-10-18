<?php
/**
 * This Year's Sequences: MP4 videos, played in a pop-up player.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! ridgeroad_mod( 'seq_show' ) ) {
	return;
}
$vids = ridgeroad_sequence_videos();
if ( ! $vids ) {
	return;
}
?>
<section id="sequences" class="rr-seq<?php echo count( $vids ) > 4 ? ' is-short' : ''; ?>" aria-labelledby="rr-seq-h">
	<h2 id="rr-seq-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'seq_title' ) ); ?></span></h2>
	<?php if ( ridgeroad_mod( 'seq_text' ) ) : ?><p class="rr-kids__intro"><?php echo esc_html( ridgeroad_mod( 'seq_text' ) ); ?></p><?php endif; ?>
	<div class="rr-seq__grid">
		<?php foreach ( $vids as $v ) : ?>
			<button type="button" class="rr-seq__card" data-video="<?php echo esc_url( $v['video'] ); ?>" data-title="<?php echo esc_attr( $v['title'] . ( $v['artist'] ? ' — ' . $v['artist'] : '' ) ); ?>">
				<span class="rr-seq__media">
					<?php if ( $v['cover'] ) : ?>
						<img src="<?php echo esc_url( $v['cover'] ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<video data-src="<?php echo esc_url( $v['video'] ); ?>#t=1" preload="none" muted playsinline tabindex="-1" aria-hidden="true"></video>
					<?php endif; ?>
					<span class="rr-seq__play" aria-hidden="true">&#9654;</span>
					<?php if ( $v['new'] ) : ?><em class="rr-seq__new"><?php esc_html_e( 'New this year', 'ridgeroad' ); ?></em><?php endif; ?>
				</span>
				<span class="rr-seq__info">
					<b><?php echo esc_html( $v['title'] ); ?></b>
					<?php if ( $v['artist'] ) : ?><small><?php echo esc_html( $v['artist'] ); ?></small><?php endif; ?>
				</span>
			</button>
		<?php endforeach; ?>
	</div>
	<?php if ( count( $vids ) > 4 ) : ?>
		<p class="rr-seq__more"><button type="button" class="rr-btn rr-btn--gold" data-rr-seq-more aria-expanded="false" aria-controls="sequences">
			<?php
			/* translators: %d: number of videos */
			echo esc_html( sprintf( __( 'Show all %d videos', 'ridgeroad' ), count( $vids ) ) );
			?>
		</button></p>
	<?php endif; ?>
	<dialog class="rr-vmodal" data-rr-vmodal aria-label="<?php esc_attr_e( 'Sequence video', 'ridgeroad' ); ?>">
		<div class="rr-vmodal__box">
			<button type="button" class="rr-vmodal__x" data-rr-vclose aria-label="<?php esc_attr_e( 'Close', 'ridgeroad' ); ?>">&times;</button>
			<video controls playsinline preload="auto" data-rr-vplayer></video>
			<p class="rr-vmodal__title" data-rr-vtitle></p>
		</div>
	</dialog>
</section>
