<?php
/**
 * Blog / archive fallback.
 */
get_header();
?>
<div class="rr-stack">
	<?php if ( is_archive() || is_search() || is_home() ) : ?>
		<h1 class="rr-ribbon"><span>
			<?php
			if ( is_search() ) {
				/* translators: %s: search terms */
				printf( esc_html__( 'Search: %s', 'ridgeroad' ), esc_html( get_search_query() ) );
			} elseif ( is_archive() ) {
				echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			} else {
				echo esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) ?: __( 'Show News', 'ridgeroad' ) );
			}
			?>
		</span></h1>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'rr-panel rr-panel--night rr-content rr-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a class="rr-card__img" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large' ); ?></a>
				<?php endif; ?>
				<h2 class="rr-content__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="rr-meta"><?php echo esc_html( get_the_date() ); ?></p>
				<div class="entry-content"><?php the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>
		<div class="rr-pager"><?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?></div>
	<?php else : ?>
		<article class="rr-panel rr-panel--night rr-content">
			<p><?php esc_html_e( 'Nothing here yet — check back soon!', 'ridgeroad' ); ?></p>
		</article>
	<?php endif; ?>
</div>
<?php
get_footer();
