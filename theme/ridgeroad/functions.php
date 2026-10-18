<?php
/**
 * Marquee Festive theme functions.
 *
 * The festive look for the Marquee plugin, which holds all the show features and
 * settings. The theme keeps the folder name and function prefix "ridgeroad" from
 * the show it was first built for (Lights On Ridge Road), so existing sites keep
 * updating cleanly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RIDGEROAD_VERSION' ) ) {
	define( 'RIDGEROAD_VERSION', '2.9.8' );
}
// Show location: from Marquee → Settings (0,0 until the Marquee plugin is set up).
$rr_mq = function_exists( 'marquee_get' ) ? marquee_get() : array();
defined( 'RIDGEROAD_LAT' ) || define( 'RIDGEROAD_LAT', ( isset( $rr_mq['lat'] ) && '' !== $rr_mq['lat'] ) ? (float) $rr_mq['lat'] : 0.0 );
defined( 'RIDGEROAD_LON' ) || define( 'RIDGEROAD_LON', ( isset( $rr_mq['lon'] ) && '' !== $rr_mq['lon'] ) ? (float) $rr_mq['lon'] : 0.0 );
unset( $rr_mq );

require get_template_directory() . '/inc/helpers.php'; // shared helpers (Marquee has the same file)

// Show features: from the Marquee plugin when it is active, else the theme's own copy.
if ( ! defined( 'MARQUEE_CORE' ) ) {
	if ( ! defined( 'RR_FEAT_URL' ) ) {
		define( 'RR_FEAT_URL', get_template_directory_uri() . '/' );
		define( 'RR_FEAT_DIR', get_template_directory() . '/' );
	}
	require get_template_directory() . '/inc/customizer.php';
	require get_template_directory() . '/inc/live.php';
	require get_template_directory() . '/inc/icons.php';
	require get_template_directory() . '/inc/cicons.php';
	require get_template_directory() . '/inc/guestbook.php';
	require get_template_directory() . '/inc/contest.php';
	require get_template_directory() . '/inc/songs.php';
	require get_template_directory() . '/inc/sequences.php';
	require get_template_directory() . '/inc/shoutouts.php';
	require get_template_directory() . '/inc/control.php';
	require get_template_directory() . '/inc/app.php';
	require get_template_directory() . '/inc/seo.php';
	require get_template_directory() . '/inc/assets.php';
}

/*
 * Marquee Festive (the copy shared with other shows, marked by a .marquee-dist file):
 * without the Marquee plugin it would fall back to Lights On Ridge Road's details, so
 * visitors see a short "getting ready" page and admins a note to install Marquee.
 */
if ( ! defined( 'MARQUEE_CORE' ) && file_exists( __DIR__ . '/.marquee-dist' ) ) {
	add_action( 'admin_notices', function () {
		if ( current_user_can( 'install_plugins' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Marquee Festive</strong> needs the free Marquee plugin. Plugins → Add New → Upload Plugin, then choose the Marquee zip. Until then, visitors see a short “getting ready” page.</p></div>';
		}
	} );
	add_action( 'template_redirect', function () {
		status_header( 200 );
		nocache_headers();
		printf(
			'<!doctype html><html %1$s><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%2$s</title></head><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#08122b;color:#fff4dc;font:18px/1.5 system-ui,sans-serif;text-align:center;padding:24px"><div><h1 style="font-weight:600;margin:0 0 8px">%2$s</h1><p style="margin:0;opacity:.85">%3$s</p></div></body></html>',
			get_language_attributes(),
			esc_html( get_bloginfo( 'name' ) ),
			esc_html__( 'Our show’s website is getting ready. Check back soon!', 'ridgeroad' )
		);
		exit;
	} );
}

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 785,
		'width'       => 1300,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	register_nav_menus( array(
		'primary' => __( 'Top Menu', 'ridgeroad' ),
	) );
} );

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	// Fonts are bundled in assets/fonts (no Google Fonts request needed).
	wp_enqueue_style( 'ridgeroad', get_stylesheet_uri(), array(), wp_get_theme( get_template() )->get( 'Version' ) );

	$bg = ridgeroad_mod( 'bg_image' );
	if ( $bg ) {
		wp_add_inline_style( 'ridgeroad', 'body.rr{--rr-bg:url(' . esc_url_raw( $bg ) . ');}' );
	}

	if ( ! defined( 'MARQUEE_CORE' ) ) {
		ridgeroad_feature_assets();
	}
} );

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'rr';
	return $classes;
} );

/** Preload the two display fonts so the header renders in the right faces. */
add_action( 'wp_head', function () {
	foreach ( array( 'marcellus-latin-400-normal.woff2', 'cinzel-latin-700-normal.woff2', 'playball-latin-400-normal.woff2' ) as $f ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_template_directory_uri() . '/assets/fonts/' . $f ) );
	}
}, 2 );


/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */
function ridgeroad_img( $file ) {
	return get_template_directory_uri() . '/assets/img/' . $file;
}

/** Fallback menu: the four anchor links from the design. */
function ridgeroad_fallback_menu() {
	$base = is_front_page() ? '' : home_url( '/' );
	$cur  = get_queried_object_id();
	$li   = function ( $url, $icon, $label, $active = false ) {
		printf(
			'<li><a href="%1$s"%4$s>%2$s<span>%3$s</span></a></li>',
			esc_url( $url ),
			ridgeroad_cicon( $icon ), // SVG markup from our own icon set.
			esc_html( $label ),
			$active ? ' class="is-active" aria-current="page"' : ''
		);
	};
	echo '<ul class="rr-menu">';
	// Only the separate pages: everything else is already on the home page.
	$li( home_url( '/' ), 'home', __( 'Home', 'ridgeroad' ), is_front_page() );
	if ( ridgeroad_songs_url() ) {
		if ( ridgeroad_mod( 'seq_show' ) && ridgeroad_sequence_videos() ) {
			$li( ridgeroad_songs_url() . '#sequences', 'camera', __( 'Videos', 'ridgeroad' ) );
		}
		if ( ridgeroad_mod( 'songs_show' ) && ridgeroad_song_list() ) {
			$li( ridgeroad_songs_url() . '#vote', 'music', __( 'Vote', 'ridgeroad' ) );
		}
	}
	if ( ridgeroad_mod( 'kids_show' ) && ridgeroad_kids_url() ) {
		$li( ridgeroad_kids_url(), 'gift', ridgeroad_mod( 'kids_title' ), $cur && $cur === (int) get_option( 'ridgeroad_kids_page' ) );
	}
	echo '</ul>';
}

/* No guestbook token of its own: the guestbook comes from Marquee. */
add_filter( 'ridgeroad_gb_token_default', function ( $t ) {
	return function_exists( 'marquee_get' ) ? $t : '';
} );
