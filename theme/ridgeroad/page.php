<?php
/**
 * Single page.
 */
get_header();
while ( have_posts() ) :
	the_post();
	if ( function_exists( 'marquee_is_show_page' ) && marquee_is_show_page() ) :
		// a page built with show boxes: the boxes stand on the show's picture, like the show's own pages
		?>
		<div class="rr-showpage">
			<h1 class="rr-ribbon rr-showpage__title"><span><?php the_title(); ?></span></h1>
			<div class="rr-showpage__body"><?php the_content(); ?></div>
		</div>
		<p class="rr-kids__back"><a class="rr-btn rr-btn--red" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to the Show', 'ridgeroad' ); ?></a></p>
		<?php
		continue;
	endif;
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'rr-panel rr-panel--night rr-content' ); ?>>
		<h1 class="rr-content__title"><?php the_title(); ?></h1>
		<?php if ( has_post_thumbnail() ) : ?><div class="rr-content__img"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
		<div class="entry-content"><?php the_content(); ?></div>
		<?php wp_link_pages(); ?>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="rr-panel rr-panel--night rr-content">';
		comments_template();
		echo '</div>';
	}
endwhile;
get_footer();
