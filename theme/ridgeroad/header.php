<?php
/**
 * Site header: garland frame, top menu and logo.
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#08122b">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'ridgeroad' ); ?></a>

<div class="rr-side rr-side--left" aria-hidden="true"></div>
<div class="rr-side rr-side--right" aria-hidden="true"></div>
<div class="rr-frame" aria-hidden="true">
	<div class="rr-garland rr-garland--top"></div>
	<img class="rr-bow rr-bow--left" src="<?php echo esc_url( ridgeroad_img( 'bow-left-v137.webp' ) ); ?>" alt="">
	<img class="rr-bow rr-bow--right" src="<?php echo esc_url( ridgeroad_img( 'bow-right-v137.webp' ) ); ?>" alt="">
</div>

<canvas class="rr-snow" aria-hidden="true"></canvas>

<header class="rr-header">
	<nav class="rr-nav" aria-label="<?php esc_attr_e( 'Main', 'ridgeroad' ); ?>">
		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'rr-menu',
				'depth'          => 1,
			) );
		} else {
			ridgeroad_fallback_menu();
		}
		?>
	</nav>

	<div class="rr-hero<?php echo is_front_page() ? '' : ' rr-hero--small'; ?>">
		<?php if ( is_front_page() ) : ?><h1 class="rr-logo-h"><?php endif; // the logo (its alt text, the show name) is the home page's main heading for Google ?>
		<a class="rr-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => get_bloginfo( 'name' ) ) );
			} elseif ( function_exists( 'marquee_get' ) ) {
				printf( '<span class="rr-logo__text">%s</span>', esc_html( get_bloginfo( 'name' ) ) );
			} else {
				printf(
					'<picture><source srcset="%1$s" sizes="(max-width: 600px) 86vw, (min-width: 1700px) 470px, 340px" type="image/webp"><img src="%2$s" width="1300" height="785" alt="%3$s" fetchpriority="high"></picture>',
					esc_url( ridgeroad_img( 'logo-v190-700.webp' ) ) . ' 700w, ' . esc_url( ridgeroad_img( 'logo-v190-1000.webp' ) ) . ' 1000w, ' . esc_url( ridgeroad_img( 'logo-v190.webp' ) ) . ' 1300w',
					esc_url( ridgeroad_img( 'logo-v190.png' ) ),
					esc_attr( get_bloginfo( 'name' ) )
				);
			}
			?>
		</a>
		<?php if ( is_front_page() ) : ?></h1><?php endif; ?>
		<div class="rr-hero__text">
		<?php if ( ridgeroad_mod( 'subtitle' ) ) : ?>
			<p class="rr-subtitle"><?php echo esc_html( ridgeroad_mod( 'subtitle' ) ); ?></p>
		<?php endif; ?>
		<?php if ( is_front_page() ) : ?>
			<?php if ( ridgeroad_mod( 'tagline' ) ) : ?>
				<p class="rr-tagline"><?php echo esc_html( ridgeroad_mod( 'tagline' ) ); ?></p>
			<?php endif; ?>
			<?php if ( ridgeroad_mod( 'intro' ) ) : ?>
				<p class="rr-intro"><?php echo esc_html( ridgeroad_mod( 'intro' ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		</div>
	</div>
</header>
<div class="rr-scroll">
<main id="main" class="rr-main">
