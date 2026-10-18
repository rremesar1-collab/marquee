<?php
/**
 * Songs page: this year's videos + favorite song voting.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$np = function_exists( 'ridgeroad_now_playing' ) ? ridgeroad_now_playing() : array();
?>
<section class="rr-songs-page" aria-labelledby="rr-songs-h">
	<h1 id="rr-songs-h" class="screen-reader-text"><?php echo esc_html( get_the_title() ); ?></h1>
	<?php if ( ! ridgeroad_songs_page_on() ) : ?>
		<article class="rr-panel rr-panel--night rr-content"><p><?php esc_html_e( 'Check back soon for this year’s songs!', 'ridgeroad' ); ?></p></article>
	<?php endif; ?>
	<?php ridgeroad_part( 'sequences' ); ?>
	<?php ridgeroad_part( 'vote', array( 'np' => $np ) ); ?>
</section>
