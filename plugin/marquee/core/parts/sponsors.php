<?php
/**
 * Thank You to Our Sponsors: local businesses that support the show.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 * Full width between the guestbook and Donate / Stay Connected; nothing at all when it's off or empty.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_list = function_exists( 'ridgeroad_sponsors' ) ? ridgeroad_sponsors() : array();
if ( ridgeroad_mod( 'sp_show' ) && $sp_list ) :
	$sp_n    = count( $sp_list );
	$sp_cols = (int) ceil( $sp_n / ceil( $sp_n / 5 ) ); // even rows: 6 = 3 + 3, 7 = 4 + 3, never 5 + 1
	$sp_ask  = ridgeroad_sponsor_ask_url();
	$sp_text = ridgeroad_mod( 'sp_intro' );
	?>
<section id="sponsors" class="rr-sponsors" aria-labelledby="rr-sp-h">
	<h2 id="rr-sp-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'sp_title' ) ); ?></span></h2>
	<div class="rr-panel rr-panel--green rr-sp__panel">
		<?php if ( '' !== trim( (string) $sp_text ) ) : ?>
			<p class="rr-sp__intro"><?php echo esc_html( $sp_text ); ?></p>
		<?php endif; ?>
		<ul class="rr-sp" style="--cols:<?php echo (int) $sp_cols; ?>">
			<?php
			foreach ( $sp_list as $s ) :
				$img  = $s['logo'] ? wp_get_attachment_image_url( $s['logo'], 'medium' ) : '';
				$tag  = $s['url'] ? 'a' : 'div';
				$attr = $s['url'] ? ' href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener"' : '';
				?>
				<li class="rr-sp__item">
					<<?php echo $tag . $attr; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url above ?> class="rr-sp__card">
						<span class="rr-sp__logo"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $s['name'] ); ?>" loading="lazy" decoding="async"><?php else : ?><b class="rr-sp__word"><?php echo esc_html( $s['name'] ); ?></b><?php endif; ?></span>
						<?php if ( $img && '' !== $s['name'] ) : ?><span class="rr-sp__name"><?php echo esc_html( $s['name'] ); ?></span><?php endif; ?>
						<?php if ( '' !== $s['line'] ) : ?><span class="rr-sp__line"><?php echo esc_html( $s['line'] ); ?></span><?php endif; ?>
					</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $sp_ask ) : ?>
			<p class="rr-sp__ask"><?php esc_html_e( 'Want to sponsor the show?', 'ridgeroad' ); ?> <a href="<?php echo esc_url( $sp_ask ); ?>"<?php echo 0 === strpos( $sp_ask, 'mailto:' ) ? '' : ' target="_blank" rel="noopener"'; ?>><?php esc_html_e( 'Get in touch', 'ridgeroad' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
