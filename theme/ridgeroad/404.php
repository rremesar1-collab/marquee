<?php
/**
 * Not found.
 */
get_header();
?>
<article class="rr-panel rr-panel--night rr-content rr-404">
	<h1 class="rr-content__title"><?php esc_html_e( 'This light must have burned out!', 'ridgeroad' ); ?></h1>
	<p><?php esc_html_e( 'We couldn’t find that page.', 'ridgeroad' ); ?></p>
	<p><a class="rr-btn rr-btn--red" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the Show', 'ridgeroad' ); ?></a></p>
</article>
<?php
get_footer();
