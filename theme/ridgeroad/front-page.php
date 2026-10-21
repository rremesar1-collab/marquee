<?php
/**
 * Front page: the show dashboard. Each section is a shared part (theme or Marquee).
 */
get_header();

ridgeroad_part( 'status' );
ridgeroad_part( 'countdown' );
ridgeroad_part( 'app-card' );
?>

<div class="rr-grid rr-grid--main">
<?php
ridgeroad_part( 'show' );
ridgeroad_part( 'weather' );
?>
</div>

<?php
ridgeroad_part( 'playlist' );
ridgeroad_part( 'live-cam' );
ridgeroad_part( 'visit' );
ridgeroad_part( 'explore' );
ridgeroad_part( 'showwall' );
ridgeroad_part( 'stats' );
ridgeroad_part( 'guestbook' );
?>

<div id="support" class="rr-grid rr-grid--support">
<?php
ridgeroad_part( 'donate' );
ridgeroad_part( 'social' );
?>
</div>

<?php
// If a static front page with content is set, show its content below the dashboard.
if ( 'page' === get_option( 'show_on_front' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) && ! has_block( 'marquee/home' ) ) : // the Home block is this page's dashboard
			?>
			<article class="rr-panel rr-panel--night rr-content entry-content">
				<?php the_content(); ?>
			</article>
			<?php
		endif;
	endwhile;
endif;

get_footer();
