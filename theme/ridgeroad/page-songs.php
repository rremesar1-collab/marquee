<?php
/**
 * Songs & Sequences page (slug "songs"): sequence videos + favorite song voting.
 */
get_header();
ridgeroad_part( 'songs' ); ?>
<p class="rr-kids__back"><a class="rr-btn rr-btn--red" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to the Show', 'ridgeroad' ); ?></a></p>
<?php
get_footer();
