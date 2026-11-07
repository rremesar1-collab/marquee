<?php
/**
 * Show Facts page (slug "facts").
 */
get_header();
if ( function_exists( 'ridgeroad_facts_on' ) && ridgeroad_facts_on() ) {
	ridgeroad_part( 'facts' );
} else {
	echo '<article class="rr-panel rr-panel--night rr-content"><p>' . esc_html__( 'Show facts are coming soon. Check back!', 'ridgeroad' ) . '</p></article>';
}
?>
<p class="rr-kids__back"><a class="rr-btn rr-btn--red" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to the Show', 'ridgeroad' ); ?></a></p>
<?php
get_footer();
