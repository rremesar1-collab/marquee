<?php
/**
 * Kids' Corner page (slug "kids").
 */
get_header();
if ( ridgeroad_mod( 'kids_show' ) ) {
	ridgeroad_part( 'kids' );
} else {
	echo '<article class="rr-panel rr-panel--night rr-content"><p>' . esc_html__( 'The Kids’ Corner is resting right now — check back soon!', 'ridgeroad' ) . '</p></article>';
}
?>
<p class="rr-kids__back"><a class="rr-btn rr-btn--red" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to the Show', 'ridgeroad' ); ?></a></p>
<?php
get_footer();
