<?php
/**
 * Single post.
 */
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'rr-panel rr-panel--night rr-content' ); ?>>
		<h1 class="rr-content__title"><?php the_title(); ?></h1>
		<p class="rr-meta"><?php echo esc_html( get_the_date() ); ?></p>
		<?php if ( has_post_thumbnail() ) : ?><div class="rr-content__img"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
		<div class="entry-content"><?php the_content(); ?></div>
		<?php wp_link_pages(); ?>
		<nav class="rr-postnav"><?php the_post_navigation(); ?></nav>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="rr-panel rr-panel--night rr-content">';
		comments_template();
		echo '</div>';
	}
endwhile;
get_footer();
