<?php
/**
 * Looks: one theme, three styles to pick from in Appearance → Customize → Show Website → Look.
 *   Classic             the original garland frame and night forest
 *   Christmas Village   sapphire and champagne gold, a lit village by the river
 *   Santa's North Pole  red and gold, Santa's workshop and sleigh
 * A look only changes the picture behind the page; everything else is Classic.
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
		'description' => __( 'Pick the picture behind your website. Everything else stays the same in every look: your sections, colors and settings.', 'ridgeroad' ),
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
	$wp->add_control( new Ridgeroad_Look_Control( $wp, 'look', array(
		'label'   => __( 'Look', 'ridgeroad' ),
		'section' => 'rr_look',
		'choices' => ridgeroad_looks(),
	) ) );
}, 20 );

/* The Look choices as picture cards: a small picture of each look, tap one to pick it. */
add_action( 'customize_register', function () {
	if ( class_exists( 'Ridgeroad_Look_Control' ) ) {
		return;
	}
	class Ridgeroad_Look_Control extends WP_Customize_Control {
		public $type = 'rr_look';
		public function render_content() {
			$name = '_customize-radio-' . $this->id;
			?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php if ( ridgeroad_mod( 'bg_image' ) ) : ?>
				<p class="rr-looknote"><?php esc_html_e( 'You’re using your own Background photo, so it shows behind your website with every look. To see a look’s picture, remove your photo in Header & Intro → Background photo.', 'ridgeroad' ); ?></p>
			<?php endif; ?>
			<div class="rr-lookcards" role="radiogroup" aria-label="<?php echo esc_attr( $this->label ); ?>">
				<?php foreach ( $this->choices as $value => $label ) : ?>
					<label class="rr-lookcard">
						<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php $this->link(); ?> <?php checked( $this->value(), $value ); ?>>
						<img src="<?php echo esc_url( get_template_directory_uri() . '/looks/thumb-' . $value . '.webp' ); ?>" width="560" height="350" alt="" loading="lazy">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
}, 1 );
add_action( 'customize_controls_print_styles', function () {
	echo '<style id="rr-lookcards">'
		. '.rr-looknote{margin:4px 0 8px;padding:8px 10px;border-left:4px solid #dba617;background:#fcf9e8;font-size:12.5px;line-height:1.45}'
		. '.rr-lookcards{display:grid;gap:12px;margin-top:6px}'
		. '.rr-lookcard{position:relative;display:block;border-radius:10px;overflow:hidden;cursor:pointer;border:3px solid transparent;box-shadow:0 1px 4px rgba(0,0,0,.25);background:#08122b}'
		. '.rr-lookcard input{position:absolute;opacity:0;pointer-events:none}'
		. '.rr-lookcard img{display:block;width:100%;height:auto}'
		. '.rr-lookcard span{display:block;padding:8px 10px;font-weight:600;color:#fff;background:#14213d}'
		. '.rr-lookcard:has(input:checked){border-color:#2271b1;box-shadow:0 0 0 2px #fff,0 0 0 4px #2271b1}'
		. '.rr-lookcard:has(input:checked) span::after{content:" \2713";color:#7fd1ff}'
		. '.rr-lookcard:has(input:focus-visible){outline:2px solid #2271b1;outline-offset:2px}'
		. '</style>';
} );

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
	// The look's picture behind the page, in place of Classic's (your own Background photo replaces it too).
	wp_add_inline_style( 'ridgeroad-look', sprintf( 'body.rr-look--%1$s{--rr-scene:url(%2$s);}', ridgeroad_look(), esc_url( ridgeroad_look_img( 'hero.webp' ) ) ) );
}, 20 );

/** The look's picture loads first (it's behind every page). */
add_action( 'wp_head', function () {
	if ( 'classic' === ridgeroad_look() || ridgeroad_mod( 'bg_image' ) ) {
		return;
	}
	printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( ridgeroad_look_img( 'hero.webp' ) ) );
}, 3 );
