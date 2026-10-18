<?php
/**
 * Guestbook.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast

$gb_key = ridgeroad_gb_site_key();
if ( ridgeroad_mod( 'gb_show' ) && ( ridgeroad_gb_wp() || $gb_key ) ) : // WordPress-kept messages need no RF Page Builder key
	// Served through this site so the browser never blocks it; changes every minute so new messages show.
	$gb_img = admin_url( 'admin-ajax.php?action=ridgeroad_gb_img&m=' . floor( time() / 60 ) );
	?>
<section id="guestbook" class="rr-guestbook" aria-labelledby="rr-gb-h">
	<h2 id="rr-gb-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'gb_title' ) ); ?></span></h2>
	<?php if ( ridgeroad_mod( 'gb_eyebrow' ) || ridgeroad_mod( 'gb_intro' ) ) : ?>
		<div class="rr-gb-intro">
			<?php if ( ridgeroad_mod( 'gb_eyebrow' ) ) : ?><p class="rr-gb-eyebrow"><?php echo esc_html( ridgeroad_mod( 'gb_eyebrow' ) ); ?></p><?php endif; ?>

			<?php if ( ridgeroad_mod( 'gb_intro' ) ) : ?><p><?php echo esc_html( ridgeroad_mod( 'gb_intro' ) ); ?></p><?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="rr-grid rr-grid--gb">
		<div class="rr-panel rr-panel--blue rr-gb-messages">
			<h3 class="rr-h"><span class="rr-h__star"><?php echo ridgeroad_cicon( 'star' ); ?></span><?php esc_html_e( 'Messages From Our Visitors', 'ridgeroad' ); ?></h3>
			<?php if ( ridgeroad_gb_wp() ) :
				$gb_list = ridgeroad_gb_messages( 50 ); ?>
				<?php if ( $gb_list ) : ?>
					<ul class="rr-gbl" data-rr-gbl>
						<?php foreach ( $gb_list as $i => $c ) : ?>
							<li class="rr-gbl__item"<?php echo $i >= 5 ? ' hidden' : ''; ?>>
								<p class="rr-gbl__msg"><?php echo nl2br( esc_html( wp_trim_words( preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email hidden]', $c->comment_content ), 90 ) ) ); ?></p>
								<p class="rr-gbl__meta"><b><?php echo esc_html( ridgeroad_gb_name( $c->comment_author ) ); ?></b><span><?php echo esc_html( sprintf( __( '%s ago', 'ridgeroad' ), human_time_diff( strtotime( $c->comment_date_gmt . ' UTC' ) ) ) ); ?></span></p>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( count( $gb_list ) > 5 ) : ?>
						<button class="rr-link rr-gbl__more" type="button" data-rr-gbl-more><?php esc_html_e( 'Show more messages', 'ridgeroad' ); ?></button>
					<?php endif; ?>
				<?php else : ?>
					<p class="rr-gb-empty"><?php esc_html_e( 'No messages yet — be the first to sign!', 'ridgeroad' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
			<img src="<?php echo esc_url( $gb_img ); ?>" data-direct="<?php echo esc_url( ridgeroad_gb_remote_url() ); ?>" alt="<?php esc_attr_e( 'Recent visitor guestbook messages', 'ridgeroad' ); ?>" loading="lazy" width="900" referrerpolicy="no-referrer" onerror="if(this.dataset.direct&&this.src!==this.dataset.direct){this.src=this.dataset.direct;}else{this.hidden=true;this.nextElementSibling.hidden=false;}">
			<p class="rr-gb-empty" hidden><?php esc_html_e( 'No messages yet — be the first to sign!', 'ridgeroad' ); ?></p>
			<a class="rr-link" href="<?php echo esc_url( 'https://rfpagebuilder.com/guestbook/view/' . rawurlencode( $gb_key ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View all guestbook messages', 'ridgeroad' ); ?><?php echo ridgeroad_cicon( 'external' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="rr-panel rr-panel--green rr-gb-form">
			<h3 class="rr-h"><span class="rr-h__mail"><?php echo ridgeroad_cicon( 'mail' ); ?></span><?php esc_html_e( 'Sign Our Guestbook', 'ridgeroad' ); ?></h3>
			<?php
			$gb_state = isset( $_GET['gb'] ) ? sanitize_key( $_GET['gb'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'thanks' === $gb_state ) : ?>
				<p class="rr-gb-note rr-gb-note--ok"><?php echo ridgeroad_cicon( 'tree' ); ?><span><?php esc_html_e( 'Thank you! Your message was sent and will appear here after we read it.', 'ridgeroad' ); ?></span></p>
			<?php elseif ( 'error' === $gb_state ) : ?>
				<p class="rr-gb-note rr-gb-note--err"><?php echo esc_html( isset( $_GET['gbmsg'] ) ? wp_unslash( rawurldecode( $_GET['gbmsg'] ) ) : __( 'Something went wrong — please try again.', 'ridgeroad' ) ); // phpcs:ignore ?></p>
			<?php endif; ?>
			<?php if ( ridgeroad_gb_wp() ) : ?>
			<form action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>" method="POST">
				<input name="comment_post_ID" type="hidden" value="<?php echo (int) ridgeroad_gb_page_id(); ?>">
				<input name="comment_parent" type="hidden" value="0">
				<div class="rr-gb-honeypot" aria-hidden="true"><label for="gb-website">Website</label><input autocomplete="off" id="gb-website" name="website" tabindex="-1" type="text"></div>
				<label for="gb-name"><?php esc_html_e( 'Your name', 'ridgeroad' ); ?></label>
				<input id="gb-name" maxlength="100" name="author" placeholder="<?php esc_attr_e( 'Your name', 'ridgeroad' ); ?>" required type="text">
				<label for="gb-message"><?php esc_html_e( 'Your Christmas message', 'ridgeroad' ); ?></label>
				<textarea id="gb-message" maxlength="500" name="comment" placeholder="<?php esc_attr_e( 'Leave a message...', 'ridgeroad' ); ?>" required rows="5"></textarea>
				<button class="rr-btn rr-btn--red" type="submit"><?php esc_html_e( 'Sign Guestbook', 'ridgeroad' ); ?></button>
				<p class="rr-gb-fine"><?php esc_html_e( 'Messages appear after we review them.', 'ridgeroad' ); ?></p>
			</form>
			<?php else : ?>
			<form action="https://rfpagebuilder.com/api/guestbook/submit" method="POST">
				<input name="site_key" type="hidden" value="<?php echo esc_attr( $gb_key ); ?>">
				<input name="rf_url" type="hidden" value="">
				<input name="gb_token" type="hidden" value="<?php echo esc_attr( ridgeroad_gb_token() ); ?>">
				<div class="rr-gb-honeypot" aria-hidden="true"><label for="gb-website">Website</label><input autocomplete="off" id="gb-website" name="website" tabindex="-1" type="text"></div>
				<label for="gb-name"><?php esc_html_e( 'Your name', 'ridgeroad' ); ?></label>
				<input id="gb-name" maxlength="100" name="name" placeholder="<?php esc_attr_e( 'Your name', 'ridgeroad' ); ?>" required type="text">
				<label for="gb-message"><?php esc_html_e( 'Your Christmas message', 'ridgeroad' ); ?></label>
				<textarea id="gb-message" maxlength="500" name="message" placeholder="<?php esc_attr_e( 'Leave a message...', 'ridgeroad' ); ?>" required rows="5"></textarea>
				<button class="rr-btn rr-btn--red" type="submit"><?php esc_html_e( 'Sign Guestbook', 'ridgeroad' ); ?></button>
			</form>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>
