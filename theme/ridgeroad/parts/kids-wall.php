<?php
/**
 * Contest step 3: the Show Wall (approved shows, hearts) + the pop-up player.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$entries = isset( $args['entries'] ) ? $args['entries'] : array();
$open    = ! empty( $args['open'] );
?>
<section id="show-wall" class="rr-wall" aria-labelledby="rr-wall-h">
	<h3 id="rr-wall-h" class="rr-kc__h"><?php esc_html_e( 'Kids’ Show Wall', 'ridgeroad' ); ?></h3>
	<?php if ( $entries ) : ?>
		<p class="rr-kc__note"><?php echo $open ? esc_html__( 'Watch the shows and tap the heart on your favorites!', 'ridgeroad' ) : esc_html__( 'Watch the shows our young light designers made!', 'ridgeroad' ); ?></p>
		<div class="rr-wall__grid" data-rr-wall data-open="<?php echo $open ? '1' : '0'; ?>">
			<?php foreach ( $entries as $e ) : ?>
				<figure class="rr-wall__card<?php echo ( $e['judges'] || $e['people'] ) ? ' is-winner' : ''; ?>">
					<button type="button" class="rr-wall__watch" data-show="<?php echo esc_attr( $e['show'] ); ?>" data-label="<?php echo esc_attr( sprintf( '%s, age %d', $e['name'], $e['age'] ) ); ?>" data-name="<?php echo esc_attr( $e['title'] ? $e['title'] : __( 'My Light Show', 'ridgeroad' ) ); ?>">
						<img src="<?php echo esc_url( $e['img'] ); ?>" alt="" loading="lazy">
						<span class="rr-wall__play" aria-hidden="true">&#9654;</span>
						<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Watch the show by %s', 'ridgeroad' ), $e['name'] ) ); ?></span>
					</button>
					<figcaption>
						<strong><?php echo esc_html( $e['title'] ? $e['title'] : __( 'My Light Show', 'ridgeroad' ) ); ?></strong>
						<span><?php echo esc_html( sprintf( '%s, age %d', $e['name'], $e['age'] ) ); ?></span>
						<?php if ( $e['judges'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'Judges’ Pick', 'ridgeroad' ); ?></em><?php endif; ?>
						<?php if ( $e['people'] ) : ?><em class="rr-wall__badge"><?php esc_html_e( 'People’s Choice', 'ridgeroad' ); ?></em><?php endif; ?>
					</figcaption>
					<button type="button" class="rr-wall__heart" data-id="<?php echo (int) $e['id']; ?>" <?php disabled( ! $open ); ?> aria-label="<?php esc_attr_e( 'Give this show a heart', 'ridgeroad' ); ?>"><b aria-hidden="true">&#9829;</b><span><?php echo (int) $e['votes']; ?></span></button>
				</figure>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="rr-kc__note"><?php esc_html_e( 'No shows yet. Make one and be the first! Shows appear here after we check them.', 'ridgeroad' ); ?></p>
		<p class="rr-wall__first"><a class="rr-btn rr-btn--gold" href="#light-show"><?php esc_html_e( 'Make a Light Show', 'ridgeroad' ); ?></a></p>
	<?php endif; ?>
</section>

<dialog class="rr-vmodal rr-showmodal<?php echo ( function_exists( 'marquee_festive' ) && ! marquee_festive() ) ? ' mq-skin' : ''; /* styled even if moved out of the page */ ?>" data-rr-showmodal aria-label="<?php esc_attr_e( 'Light show player', 'ridgeroad' ); ?>">
	<div class="rr-vmodal__box">
		<button type="button" class="rr-vmodal__x" data-rr-sm-close aria-label="<?php esc_attr_e( 'Close', 'ridgeroad' ); ?>">&times;</button>
		<p class="rr-showmodal__head"><strong data-rr-sm-title></strong> <span data-rr-sm-by></span></p>
		<div class="rr-showmodal__stage" data-rr-sm-stage></div>
		<div class="rr-showmodal__btns">
			<button type="button" class="rr-btn rr-btn--red" data-rr-sm-play><b aria-hidden="true">&#9654;</b> <span><?php esc_html_e( 'Play', 'ridgeroad' ); ?></span></button>
			<button type="button" class="rr-btn rr-btn--ghost" data-rr-sm-close><?php esc_html_e( 'Close', 'ridgeroad' ); ?></button>
		</div>
	</div>
</dialog>
