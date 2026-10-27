<?php
/**
 * Looks: one theme, three styles to pick from in Appearance → Customize → Show Website → Look.
 *   Classic             the original garland frame and night forest
 *   Christmas Village   sapphire and champagne gold, a lit village by the river
 *   Santa's North Pole  red and gold, Santa's workshop and sleigh
 * A look only changes pictures and colors; every section, setting and feature stays the same.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The looks and their names (for the Customize list). */
function ridgeroad_looks() {
	return array(
		'classic'   => __( 'Classic', 'ridgeroad' ),
		'village'   => __( 'Christmas Village', 'ridgeroad' ),
		'northpole' => __( 'Santa’s North Pole', 'ridgeroad' ),
	);
}

/** The look in use: classic, village or northpole. */
function ridgeroad_look() {
	$l = (string) get_theme_mod( 'look', 'classic' );
	return isset( ridgeroad_looks()[ $l ] ) ? $l : 'classic';
}

/** URL of a picture for the look in use. */
function ridgeroad_look_img( $file ) {
	return get_template_directory_uri() . '/looks/' . ridgeroad_look() . '/' . $file;
}

add_action( 'customize_register', function ( WP_Customize_Manager $wp ) {
	$wp->add_section( 'rr_look', array(
		'title'       => __( 'Look', 'ridgeroad' ),
		'description' => __( 'Pick the style of your website. Only the pictures and colors change; your sections and settings stay the same.', 'ridgeroad' ),
		'panel'       => $wp->get_panel( 'ridgeroad' ) ? 'ridgeroad' : '',
		'priority'    => 1,
	) );
	$wp->add_setting( 'look', array(
		'default'           => 'classic',
		'transport'         => 'refresh',
		'sanitize_callback' => function ( $v ) {
			return isset( ridgeroad_looks()[ $v ] ) ? $v : 'classic';
		},
	) );
	$wp->add_control( 'look', array(
		'label'   => __( 'Look', 'ridgeroad' ),
		'section' => 'rr_look',
		'type'    => 'radio',
		'choices' => ridgeroad_looks(),
	) );
}, 20 );

add_filter( 'body_class', function ( $classes ) {
	if ( 'classic' !== ridgeroad_look() ) {
		$classes[] = 'rr-looks';
		$classes[] = 'rr-look--' . ridgeroad_look();
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( 'classic' === ridgeroad_look() ) {
		return;
	}
	$f = get_template_directory() . '/looks/looks.css';
	wp_enqueue_style( 'ridgeroad-look', get_template_directory_uri() . '/looks/looks.css', array( 'ridgeroad' ), wp_get_theme( get_template() )->get( 'Version' ) . '.' . (int) @filemtime( $f ) );
	// The look's picture behind the page, like Classic's forest (phones get a tall version when there is one).
	$phone = file_exists( get_template_directory() . '/looks/' . ridgeroad_look() . '/scene-small.webp' ) ? 'scene-small.webp' : 'hero.webp';
	wp_add_inline_style( 'ridgeroad-look', sprintf(
		'body.rr-look--%1$s{--rr-scene:url(%2$s);}@media (max-width:860px){body.rr-look--%1$s{--rr-scene:url(%3$s);}}',
		ridgeroad_look(),
		esc_url( ridgeroad_look_img( 'hero.webp' ) ),
		esc_url( ridgeroad_look_img( $phone ) )
	) );
}, 20 );

/** The look's picture loads first (it's behind every page). */
add_action( 'wp_head', function () {
	if ( 'classic' === ridgeroad_look() || ridgeroad_mod( 'bg_image' ) ) {
		return;
	}
	$phone = file_exists( get_template_directory() . '/looks/' . ridgeroad_look() . '/scene-small.webp' ) ? 'scene-small.webp' : 'hero.webp';
	printf( '<link rel="preload" as="image" href="%s" media="(min-width: 861px)" fetchpriority="high">' . "\n", esc_url( ridgeroad_look_img( 'hero.webp' ) ) );
	printf( '<link rel="preload" as="image" href="%s" media="(max-width: 860px)" fetchpriority="high">' . "\n", esc_url( ridgeroad_look_img( $phone ) ) );
}, 3 );
