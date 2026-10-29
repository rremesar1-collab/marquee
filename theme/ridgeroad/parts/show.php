<?php
/**
 * Tonight's Show: radio, hours, Now Playing, directions.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
	<section class="rr-panel rr-panel--red rr-show" aria-labelledby="rr-show-h">
		<div class="rr-show__head">
			<h2 id="rr-show-h" class="rr-h"><span class="rr-h__tree"><?php echo ridgeroad_cicon( 'tree' ); ?></span><?php esc_html_e( 'Tonight’s Show', 'ridgeroad' ); ?></h2>
			<span class="rr-badge rr-badge--<?php echo esc_attr( $status['state'] ); ?>" data-rr="badge"><i></i><span><?php echo esc_html( $status['badge'] ); ?></span></span>
		</div>
		<?php
		$listen    = ridgeroad_mod( 'listen_url' );
		$listen_qr = $listen && ridgeroad_mod( 'listen_qr' );
		if ( $listen_qr ) {
			wp_enqueue_script( 'ridgeroad-qrcode', RR_FEAT_URL . 'assets/js/qrcode.js', array(), '2.0.4', true );
		}
		?>
		<div class="rr-show__row">
			<div class="rr-tile">
				<span class="rr-tile__icon rr-gold"><?php echo ridgeroad_cicon( 'radio' ); ?></span>
				<div>
					<span class="rr-tile__label"><?php esc_html_e( 'Tune Your Radio', 'ridgeroad' ); ?></span>
					<strong class="rr-radio"><?php echo esc_html( ridgeroad_mod( 'radio' ) ); ?></strong>
				</div>
			</div>
			<div class="rr-tile">
				<span class="rr-tile__icon rr-gold"><?php echo ridgeroad_cicon( 'clock' ); ?></span>
				<div>
					<span class="rr-tile__label"><?php esc_html_e( 'Show Hours', 'ridgeroad' ); ?></span>
					<span class="rr-hours"><b><?php echo esc_html( ridgeroad_mod( 'weeknight_label' ) ); ?>:</b> <?php echo esc_html( ( ridgeroad_mod( 'use_sunset' ) ? __( 'Sunset', 'ridgeroad' ) : ridgeroad_clock( ridgeroad_mod( 'weeknight_start' ) ) ) . ' – ' . ridgeroad_clock( ridgeroad_mod( 'weeknight_close' ) ) ); ?></span>
					<span class="rr-hours"><b><?php echo esc_html( ridgeroad_mod( 'weekend_label' ) ); ?>:</b> <?php echo esc_html( ( ridgeroad_mod( 'use_sunset' ) ? __( 'Sunset', 'ridgeroad' ) : ridgeroad_clock( ridgeroad_mod( 'weekend_start' ) ) ) . ' – ' . ridgeroad_clock( ridgeroad_mod( 'weekend_close' ) ) ); ?></span>
				</div>
			</div>
		</div>
		<?php if ( $listen ) : ?>
			<div class="rr-listen">
				<div class="rr-listen__main">
					<a class="rr-btn rr-btn--gold" href="<?php echo esc_url( $listen ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_cicon( 'headphones' ); ?><?php esc_html_e( 'Listen on Your Phone', 'ridgeroad' ); ?></a>
					<span class="rr-listen__note"><?php esc_html_e( 'No radio handy? Hear the music on your phone.', 'ridgeroad' ); ?></span>
				</div>
				<?php if ( $listen_qr ) : ?>
					<div class="rr-listen__qr">
						<div class="rr-listen__code"><div class="rr-qr__gen" data-qr="<?php echo esc_url( $listen ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code to listen on your phone', 'ridgeroad' ); ?>"></div></div>
						<span><?php esc_html_e( 'Scan to listen', 'ridgeroad' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="rr-tile rr-np<?php echo $np['title'] ? ' is-playing' : ''; ?>" data-rr="np">
			<span class="rr-tile__icon rr-np__note"><?php echo ridgeroad_cicon( 'music' ); ?></span>
			<div class="rr-np__copy">
				<span class="rr-tile__label"><?php esc_html_e( 'Now Playing', 'ridgeroad' ); ?></span>
				<strong class="rr-np__title" data-rr="np-title" aria-live="polite"><?php echo esc_html( $np_text ); ?></strong>
				<span class="rr-np__next" data-rr="np-next"<?php echo $np['next'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Up next:', 'ridgeroad' ); ?> <span><?php echo esc_html( $np['next'] ); ?></span></span>
			</div>
			<div class="rr-eq" aria-hidden="true"><?php echo str_repeat( '<i></i>', 28 ); ?></div>
		</div>
		<?php
		$rf_pick = ridgeroad_mod( 'rf_viewer_url' );
		$rf_prev = $rf_pick && 'running' !== $status['state'] && current_user_can( 'manage_options' ); // the owner sees it any time, to check it
		$rf_sub  = $rf_pick && preg_match( '#^https?://([a-z0-9-]+)\.remotefalcon\.com#i', $rf_pick, $rf_m ) ? strtolower( $rf_m[1] ) : '';
		if ( $rf_pick ) :
			if ( $rf_sub ) {
				wp_enqueue_script( 'ridgeroad-rfpick', RR_FEAT_URL . 'assets/js/rfpick.js', array(), RIDGEROAD_VERSION . '.' . (int) @filemtime( RR_FEAT_DIR . 'assets/js/rfpick.js' ), true );
			}
			?>
			<?php
			$rf_hide = array(); // songs ticked under "Leave out of song voting" are left out here too
			if ( function_exists( 'marquee_get' ) && function_exists( 'ridgeroad_song_list_all' ) ) {
				$rf_off = (array) marquee_get( 'vote_hide' );
				foreach ( ridgeroad_song_list_all() as $rf_s ) {
					if ( in_array( $rf_s['key'], $rf_off, true ) ) {
						$rf_hide[] = $rf_s['title'];
					}
				}
			}
			?>
			<div class="rr-rfpick<?php echo $rf_prev ? ' is-preview' : ''; ?>" data-rr-rfpick data-hide="<?php echo esc_attr( wp_json_encode( $rf_hide ) ); ?>" data-sub="<?php echo esc_attr( $rf_sub ); ?>" data-api="<?php echo esc_url( apply_filters( 'ridgeroad_rf_viewer_api', 'https://remotefalcon.com/remote-falcon-viewer/graphql' ) ); ?>" <?php echo $rf_prev ? 'data-preview="1"' : ''; ?> <?php echo ( 'running' === $status['state'] || $rf_prev ) ? '' : 'hidden'; ?>>
				<a class="rr-btn rr-btn--red" href="<?php echo esc_url( $rf_pick ); ?>" target="_blank" rel="noopener" data-rf-open><?php echo ridgeroad_cicon( 'music' ); ?><?php esc_html_e( 'Pick the Next Song', 'ridgeroad' ); ?></a>
				<span class="rr-rfpick__note"><?php esc_html_e( 'Choose what plays next!', 'ridgeroad' ); ?></span>
				<?php if ( $rf_prev ) : ?><em class="rr-rfpick__preview"><?php esc_html_e( 'Only you can see this right now (you’re logged in). Visitors see it while the show is running.', 'ridgeroad' ); ?></em><?php endif; ?>
			</div>
			<?php if ( $rf_sub ) : ?>
			<dialog class="rr-vmodal rr-rf<?php echo ( function_exists( 'marquee_festive' ) && ! marquee_festive() ) ? ' mq-skin' : ''; /* styled even if moved out of the page */ ?>" data-rr-rfdlg aria-labelledby="rr-rf-h">
				<div class="rr-vmodal__box rr-rf__box">
					<button type="button" class="rr-vmodal__x" data-rf-close aria-label="<?php esc_attr_e( 'Close', 'ridgeroad' ); ?>">&times;</button>
					<h3 id="rr-rf-h" class="rr-rf__h"><?php echo ridgeroad_cicon( 'music' ); ?><?php esc_html_e( 'Pick the Next Song', 'ridgeroad' ); ?></h3>
					<div class="rr-rf__head" data-rf-head></div>
					<p class="rr-rf__msg" data-rf-msg aria-live="polite"></p>
					<ul class="rr-rf__list" data-rf-list></ul>
				</div>
			</dialog>
			<?php endif; ?>
		<?php endif; ?>
		<p class="rr-show__note" data-rr="note"><?php echo esc_html( $status['note'] ); ?></p>
		<?php $dir = ridgeroad_directions_url(); if ( $dir ) : ?>
			<div class="rr-dir">
				<a class="rr-btn rr-btn--gold" href="<?php echo esc_url( $dir ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_cicon( 'pin' ); ?><?php esc_html_e( 'Get Directions', 'ridgeroad' ); ?></a>
				<?php if ( ridgeroad_mod( 'parking_note' ) ) : ?><span class="rr-dir__note"><?php echo ridgeroad_cicon( 'car' ); ?><?php echo esc_html( ridgeroad_mod( 'parking_note' ) ); ?></span><?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
