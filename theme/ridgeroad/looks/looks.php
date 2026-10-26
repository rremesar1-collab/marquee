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
		$classes[] = 'rr-look';  // the page scrolls normally (no pinned top) in these looks
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
}, 20 );

/** The big picture loads first on the home page. */
add_action( 'wp_head', function () {
	if ( 'classic' === ridgeroad_look() || ! is_front_page() ) {
		return;
	}
	printf( '<link rel="preload" as="image" href="%1$s" imagesrcset="%2$s 768w, %1$s 1536w" imagesizes="(max-width: 860px) 100vw, 1280px" fetchpriority="high">' . "\n", esc_url( ridgeroad_look_img( 'hero.webp' ) ), esc_url( ridgeroad_look_img( 'hero-small.webp' ) ) );
	foreach ( array( 'cinzel-latin-700-normal.woff2', 'great-vibes-latin-400-normal.woff2' ) as $font ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_template_directory_uri() . '/assets/fonts/' . $font ) );
	}
}, 3 );

/**
 * Home page header picture with the show's own name on the sky.
 * On phones the whole picture shows and the name sits just below it.
 */
function ridgeroad_look_hero() {
	$logo_id = get_theme_mod( 'custom_logo' );
	?>
	<div class="rr-scene">
		<picture class="rr-scene__art">
			<source media="(max-width: 860px)" srcset="<?php echo esc_url( ridgeroad_look_img( 'hero-small.webp' ) ); ?>">
			<img src="<?php echo esc_url( ridgeroad_look_img( 'hero.webp' ) ); ?>" width="1536" height="1024" alt="" fetchpriority="high">
		</picture>
		<div class="rr-scene__title">
			<h1 class="rr-logo-h">
				<a class="rr-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php
					if ( $logo_id ) {
						echo wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => get_bloginfo( 'name' ) ) );
					} else {
						printf( '<span class="rr-logo__text">%s</span>', esc_html( get_bloginfo( 'name' ) ) );
					}
					?>
				</a>
			</h1>
			<?php if ( ridgeroad_mod( 'subtitle' ) ) : ?>
				<p class="rr-subtitle"><?php echo esc_html( ridgeroad_mod( 'subtitle' ) ); ?></p>
			<?php endif; ?>
			<?php if ( ridgeroad_mod( 'tagline' ) ) : ?>
				<p class="rr-tagline"><?php echo esc_html( ridgeroad_mod( 'tagline' ) ); ?></p>
			<?php endif; ?>
			<?php if ( ridgeroad_mod( 'intro' ) ) : ?>
				<p class="rr-intro"><?php echo esc_html( ridgeroad_mod( 'intro' ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/** Home page: "Your Name in Lights" sign that leads to the form in Kids' Corner. */
function ridgeroad_look_names() {
	if ( 'classic' === ridgeroad_look() || ! ridgeroad_mod( 'names_on' ) || ! ridgeroad_mod( 'kids_show' ) || ! ridgeroad_kids_url() ) {
		return;
	}
	$open = function_exists( 'ridgeroad_names_open' ) && ridgeroad_names_open();
	?>
	<section class="rr-namesign" aria-labelledby="rr-namesign-h">
		<div class="rr-namesign__board">
			<h2 id="rr-namesign-h" class="rr-namesign__title"><?php esc_html_e( 'Your Name in Lights', 'ridgeroad' ); ?></h2>
			<p class="rr-namesign__led" aria-hidden="true"><span><?php esc_html_e( 'Merry Christmas!', 'ridgeroad' ); ?></span></p>
			<p class="rr-namesign__text"><?php echo esc_html( $open ? __( 'Type your first name and watch for it in our light show between songs!', 'ridgeroad' ) : __( 'Send your name to the show during show hours and watch for it between songs!', 'ridgeroad' ) ); ?></p>
			<a class="rr-btn rr-btn--red" href="<?php echo esc_url( ridgeroad_kids_url() . '#names' ); ?>"><?php echo esc_html( $open ? __( 'Put my name in lights', 'ridgeroad' ) : __( 'See how it works', 'ridgeroad' ) ); ?> &rarr;</a>
		</div>
	</section>
	<?php
}
