<?php
/**
 * Page builder: make your own show pages in the WordPress editor.
 *
 *  - Show Box block (marquee/show-box): a box in the show's colors (red, green, blue or night) with
 *    snow on top and lights around it, an optional ribbon title, and its own see-through amount.
 *    Anything goes inside. Ready-made kinds: Photo & Words, People, Then & Now, Questions, Photo Gallery.
 *  - "Start your page" layouts for new pages: Our Story, Questions & Answers, Photo Gallery, Meet the
 *    Team, Give Back.
 *  - Each page can have its own button at the top of the website (the page's "Show a button for this
 *    page at the top" setting).
 * The live parts of the show (countdown, playlist, live camera…) are the Marquee blocks (blocks.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The box colors. */
function marquee_box_colors() {
	return array(
		'red'   => __( 'Red', 'marquee' ),
		'green' => __( 'Green', 'marquee' ),
		'blue'  => __( 'Blue', 'marquee' ),
		'night' => __( 'Night', 'marquee' ),
	);
}

/** See-through amount (0–50) as the --rr-glass value the festive styles use (1 = solid). */
function marquee_glass_value( $pct ) {
	$pct = max( 0, min( 50, (int) $pct ) );
	return rtrim( rtrim( number_format( 1 - $pct / 100, 2, '.', '' ), '0' ), '.' );
}

/** Wrapper and box classes shared by the Show Box and the Live Camera block. */
function marquee_box_open( $a, $extra_box = '', $extra_wrap = '' ) {
	$color = isset( $a['color'], marquee_box_colors()[ $a['color'] ] ) ? $a['color'] : 'red';
	$wrap  = 'mq-boxwrap' . ( $extra_wrap ? ' ' . $extra_wrap : '' );
	if ( ! marquee_festive() ) {
		$wrap .= ' marquee mq-skin';
	}
	$style = '';
	if ( ! empty( $a['seeOwn'] ) ) {
		$wrap .= ' mq-see';
		$style = '--rr-glass:' . marquee_glass_value( $a['see'] ?? 0 );
	}
	$box = 'rr-panel rr-panel--' . $color . ' mq-box' . ( $extra_box ? ' ' . $extra_box : '' );
	if ( isset( $a['snow'] ) && ! $a['snow'] ) {
		$box .= ' mq-box--nosnow';
	}
	if ( isset( $a['lights'] ) && ! $a['lights'] ) {
		$box .= ' mq-box--nolights';
	}
	$attrs = function_exists( 'get_block_wrapper_attributes' ) && WP_Block_Supports::$block_to_render
		? get_block_wrapper_attributes( array_filter( array( 'class' => $wrap, 'style' => $style ) ) )
		: 'class="' . esc_attr( $wrap ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' );
	$out = '<section ' . $attrs . '>';
	$t   = trim( (string) ( $a['title'] ?? '' ) );
	if ( ! empty( $a['ribbon'] ) && '' !== $t ) {
		$out .= '<h2 class="rr-ribbon"><span>' . esc_html( $t ) . '</span></h2>';
	}
	return $out . '<div class="' . esc_attr( $box ) . '">';
}

/** The Show Box on the website. */
function marquee_box_render( $a, $content ) {
	$kind = isset( $a['kind'] ) ? sanitize_key( $a['kind'] ) : '';
	if ( '' === trim( wp_strip_all_tags( $content, true ) ) && false === strpos( $content, '<img' ) && false === strpos( $content, '<iframe' ) ) {
		return ''; // an empty box isn't drawn
	}
	return marquee_box_open( $a, $kind ? 'mq-box--' . $kind : '' ) . $content . '</div></section>';
}

/**
 * The Live Camera block: your live camera (or another YouTube link) in a show box, its own words,
 * and only during show hours if you like.
 */
function marquee_live_render( $a ) {
	$url = ! empty( $a['ownUrl'] ) ? trim( (string) ( $a['url'] ?? '' ) ) : (string) ridgeroad_mod( 'live_url' );
	$emb = function_exists( 'ridgeroad_youtube_embed' ) ? ridgeroad_youtube_embed( $url ) : '';
	if ( ! $emb ) {
		return current_user_can( 'edit_posts' ) ? '<p class="mq-off">' . esc_html__( 'Marquee: the Live Camera needs a YouTube link (in this block, or Marquee → Settings → Home page → Live camera). Only editors see this note.', 'marquee' ) . '</p>' : '';
	}
	if ( ! empty( $a['hoursOnly'] ) && function_exists( 'ridgeroad_status' ) ) {
		$st = ridgeroad_status();
		if ( 'running' !== ( $st['state'] ?? '' ) ) {
			return '';
		}
	}
	$title = isset( $a['title'] ) && '' !== trim( $a['title'] ) ? $a['title'] : ridgeroad_mod( 'live_title' );
	$text  = isset( $a['text'] ) && '' !== trim( $a['text'] ) ? $a['text'] : ridgeroad_mod( 'live_text' );
	$a['ribbon'] = '' !== trim( (string) $title );
	$a['title']  = $title;
	ob_start();
	?>
	<div class="rr-live__frame" data-rr-live="<?php echo esc_url( $emb ); ?>">
		<button class="rr-live__poster" type="button" aria-label="<?php esc_attr_e( 'Play the live camera', 'ridgeroad' ); ?>">
			<span class="rr-live__badge"><i></i><?php esc_html_e( 'LIVE CAM', 'ridgeroad' ); ?></span>
			<span class="rr-live__play"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30" fill="#e5121e"/><path d="M26 20l20 12-20 12z" fill="#fff"/></svg></span>
			<span class="rr-live__cta"><?php esc_html_e( 'Tap to watch & listen', 'ridgeroad' ); ?></span>
		</button>
	</div>
	<?php if ( '' !== trim( (string) $text ) ) : ?>
		<p class="rr-live__text"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
	<?php
	return marquee_box_open( $a, 'rr-live__panel', 'rr-live' ) . ob_get_clean() . '</div></section>';
}

/** Words used by the editor script (assets/builder.js), in the admin's language. */
function marquee_builder_i18n() {
	return array(
		'Start your page' => __( 'Start your page', 'marquee' ),
		'Pick a layout to start from. It’s already in your show’s colors; change the words and photos, move boxes around, or add more.' => __( 'Pick a layout to start from. It’s already in your show’s colors; change the words and photos, move boxes around, or add more.', 'marquee' ),
		'Blank page' => __( 'Blank page', 'marquee' ),
		'Start with nothing and add boxes yourself' => __( 'Start with nothing and add boxes yourself', 'marquee' ),
		'Add a button for this page at the top of my website' => __( 'Add a button for this page at the top of my website', 'marquee' ),
		'Next to Kids’ Corner and Show Facts. You can turn it off later.' => __( 'Next to Kids’ Corner and Show Facts. You can turn it off later.', 'marquee' ),
		'Use this layout' => __( 'Use this layout', 'marquee' ),
		'Start with a blank page' => __( 'Start with a blank page', 'marquee' ),
		'Color' => __( 'Color', 'marquee' ),
		'Your website’s Box colors setting (Look & colors) shows every box in one color.' => __( 'Your website’s Box colors setting (Look & colors) shows every box in one color.', 'marquee' ),
		'See-through' => __( 'See-through', 'marquee' ),
		'Use my website’s setting' => __( 'Use my website’s setting', 'marquee' ),
		'Your background shows through this box only. The rest of your site keeps its own setting (Look & colors).' => __( 'Your background shows through this box only. The rest of your site keeps its own setting (Look & colors).', 'marquee' ),
		'Your website’s boxes are set to' => __( 'Your website’s boxes are set to', 'marquee' ),
		'Your website’s boxes are solid.' => __( 'Your website’s boxes are solid.', 'marquee' ),
		'Solid' => __( 'Solid', 'marquee' ),
		'See-through works with the Marquee Festive theme.' => __( 'See-through works with the Marquee Festive theme.', 'marquee' ),
		'Title' => __( 'Title', 'marquee' ),
		'Ribbon title above the box' => __( 'Ribbon title above the box', 'marquee' ),
		'Snow and lights' => __( 'Snow and lights', 'marquee' ),
		'Snow on top' => __( 'Snow on top', 'marquee' ),
		'Lights around the edge' => __( 'Lights around the edge', 'marquee' ),
		'The box changes with your look: Classic, Christmas Village or Santa’s North Pole.' => __( 'The box changes with your look: Classic, Christmas Village or Santa’s North Pole.', 'marquee' ),
		'Show Box' => __( 'Show Box', 'marquee' ),
		'A box in your show’s colors, with snow on top and lights around it. Put anything inside.' => __( 'A box in your show’s colors, with snow on top and lights around it. Put anything inside.', 'marquee' ),
		'Type here, or press / to add a photo, a video, columns, buttons…' => __( 'Type here, or press / to add a photo, a video, columns, buttons…', 'marquee' ),
		'Photo & Words' => __( 'Photo & Words', 'marquee' ),
		'A photo next to a few lines of words.' => __( 'A photo next to a few lines of words.', 'marquee' ),
		'How it started' => __( 'How it started', 'marquee' ),
		'Tell your story here.' => __( 'Tell your story here.', 'marquee' ),
		'People' => __( 'People', 'marquee' ),
		'Round photos with a name and a line about each person.' => __( 'Round photos with a name and a line about each person.', 'marquee' ),
		'Meet the Family' => __( 'Meet the Family', 'marquee' ),
		'Name' => __( 'Name', 'marquee' ),
		'What they do' => __( 'What they do', 'marquee' ),
		'Then & Now' => __( 'Then & Now', 'marquee' ),
		'Two photos side by side, like your first year and this year.' => __( 'Two photos side by side, like your first year and this year.', 'marquee' ),
		'Then and Now' => __( 'Then and Now', 'marquee' ),
		'Our first year' => __( 'Our first year', 'marquee' ),
		'This year' => __( 'This year', 'marquee' ),
		'Year one: a few hundred lights. This year: thousands.' => __( 'Year one: a few hundred lights. This year: thousands.', 'marquee' ),
		'Questions' => __( 'Questions', 'marquee' ),
		'Questions visitors can open to read the answer.' => __( 'Questions visitors can open to read the answer.', 'marquee' ),
		'Questions We Get' => __( 'Questions We Get', 'marquee' ),
		'Where do I park?' => __( 'Where do I park?', 'marquee' ),
		'Type the answer.' => __( 'Type the answer.', 'marquee' ),
		'How do I hear the music?' => __( 'How do I hear the music?', 'marquee' ),
		'Can we get out of the car?' => __( 'Can we get out of the car?', 'marquee' ),
		'Photo Gallery' => __( 'Photo Gallery', 'marquee' ),
		'A grid of your photos. Visitors can tap one to see it big.' => __( 'A grid of your photos. Visitors can tap one to see it big.', 'marquee' ),
		'box' => __( 'box', 'marquee' ),
		'section' => __( 'section', 'marquee' ),
		'panel' => __( 'panel', 'marquee' ),
		'Your YouTube live stream in a show box. Visitors tap to watch and listen.' => __( 'Your YouTube live stream in a show box. Visitors tap to watch and listen.', 'marquee' ),
		'live' => __( 'live', 'marquee' ),
		'camera' => __( 'camera', 'marquee' ),
		'YouTube link' => __( 'YouTube link', 'marquee' ),
		'Use my live camera (the link in Marquee → Settings → Home page)' => __( 'Use my live camera (the link in Marquee → Settings → Home page)', 'marquee' ),
		'Use a different link (a second camera, or last year’s show)' => __( 'Use a different link (a second camera, or last year’s show)', 'marquee' ),
		'No live camera link yet. Add it in Marquee → Settings → Home page → Live camera.' => __( 'No live camera link yet. Add it in Marquee → Settings → Home page → Live camera.', 'marquee' ),
		'Words' => __( 'Words', 'marquee' ),
		'Line under the video' => __( 'Line under the video', 'marquee' ),
		'Only during show hours' => __( 'Only during show hours', 'marquee' ),
		'Hidden when the lights are off.' => __( 'Hidden when the lights are off.', 'marquee' ),
		'LIVE CAM' => __( 'LIVE CAM', 'marquee' ),
		'Tap to watch & listen' => __( 'Tap to watch & listen', 'marquee' ),
		'Add a YouTube link in the block settings' => __( 'Add a YouTube link in the block settings', 'marquee' ),
		'Top of your website' => __( 'Top of your website', 'marquee' ),
		'Show a button for this page at the top' => __( 'Show a button for this page at the top', 'marquee' ),
		'Next to Kids’ Corner and Show Facts. On phones, extra buttons go under More.' => __( 'Next to Kids’ Corner and Show Facts. On phones, extra buttons go under More.', 'marquee' ),
	);
}

/* ---------- Blocks ---------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$box_attrs = array(
		'color'  => array( 'type' => 'string', 'default' => 'red' ),
		'title'  => array( 'type' => 'string', 'default' => '' ),
		'ribbon' => array( 'type' => 'boolean', 'default' => false ),
		'snow'   => array( 'type' => 'boolean', 'default' => true ),
		'lights' => array( 'type' => 'boolean', 'default' => true ),
		'seeOwn' => array( 'type' => 'boolean', 'default' => false ),
		'see'    => array( 'type' => 'number', 'default' => 25 ),
		'align'  => array( 'type' => 'string' ),
	);
	wp_register_script( 'marquee-builder', MARQUEE_URL . 'assets/builder.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-plugins', 'wp-edit-post', 'wp-core-data', 'marquee-blocks' ), MARQUEE_VERSION . '.' . (int) @filemtime( MARQUEE_DIR . 'assets/builder.js' ), true );
	wp_register_style( 'marquee-builder-editor', MARQUEE_URL . 'assets/builder-editor.css', array(), MARQUEE_VERSION . '.' . (int) @filemtime( MARQUEE_DIR . 'assets/builder-editor.css' ) );
	register_block_type( 'marquee/show-box', array(
		'api_version'     => 2,
		'title'           => __( 'Show Box', 'marquee' ),
		'category'        => 'marquee-boxes',
		'editor_script'   => 'marquee-builder',
		'editor_style'    => 'marquee-builder-editor',
		'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ), 'anchor' => true ),
		'attributes'      => $box_attrs + array( 'kind' => array( 'type' => 'string', 'default' => '' ) ),
		'render_callback' => 'marquee_box_render',
	) );
	// the Live Camera block gets its own settings (registered in blocks.php; its attributes are added here)
	$reg = WP_Block_Type_Registry::get_instance()->get_registered( 'marquee/live-cam' );
	if ( $reg ) {
		$reg->attributes = array_merge( (array) $reg->attributes, array_diff_key( $box_attrs, array( 'title' => 1, 'ribbon' => 1 ) ), array(
			'ownUrl'    => array( 'type' => 'boolean', 'default' => false ),
			'url'       => array( 'type' => 'string', 'default' => '' ),
			'title'     => array( 'type' => 'string', 'default' => '' ),
			'text'      => array( 'type' => 'string', 'default' => '' ),
			'hoursOnly' => array( 'type' => 'boolean', 'default' => false ),
			'boxed'     => array( 'type' => 'boolean', 'default' => false ),
		) );
		$plain = $reg->render_callback;
		$reg->render_callback = function ( $a, $content = '', $block = null ) use ( $plain ) {
			// a Live Camera added before 0.20 (no settings changed) looks exactly as before
			return empty( $a['boxed'] ) ? call_user_func( $plain, $a, $content, $block ) : marquee_live_render( $a );
		};
	}
	register_block_style( 'core/image', array( 'name' => 'mq-round', 'label' => __( 'Round photo', 'marquee' ) ) );
	register_block_style( 'core/image', array( 'name' => 'mq-badge', 'label' => __( 'Caption as a badge', 'marquee' ) ) );
	register_post_meta( 'page', 'mq_menu', array(
		'type'          => 'boolean',
		'single'        => true,
		'default'       => false,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_pages' );
		},
	) );
	wp_add_inline_script( 'marquee-builder', 'window.MarqueeBuilder = ' . wp_json_encode( array(
		'colors'   => marquee_box_colors(),
		'festive'  => marquee_festive(),
		'siteSee'  => function_exists( 'ridgeroad_see_through_pct' ) ? ridgeroad_see_through_pct() : 0,
		'boxAll'   => marquee_festive() && 'classic' !== (string) get_theme_mod( 'box_colors', 'classic' ) ? (string) get_theme_mod( 'box_colors' ) : '',
		'liveUrl'  => (string) ridgeroad_mod( 'live_url' ),
		'liveT'    => (string) ridgeroad_mod( 'live_title' ),
		'liveX'    => (string) ridgeroad_mod( 'live_text' ),
		'img'      => MARQUEE_URL . 'assets/img/builder/',
		't'        => marquee_builder_i18n(),
		'layouts'  => array_map( function ( $slug, $p ) {
			return array( 'slug' => $slug, 'title' => $p[0], 'desc' => $p[1], 'img' => MARQUEE_URL . 'assets/img/builder/layout-' . $slug . '.webp', 'content' => $p[2] );
		}, array_keys( marquee_page_layouts() ), marquee_page_layouts() ),
	) ) . ';', 'before' );
}, 20 ); // after blocks.php registered the Marquee blocks

add_filter( 'block_categories_all', function ( $cats ) {
	array_unshift( $cats, array( 'slug' => 'marquee-boxes', 'title' => __( 'Show boxes', 'marquee' ), 'icon' => 'star-filled' ) );
	return $cats;
}, 11 );

/* The editor looks like the website on pages: the show's picture behind, its fonts and colors. */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script( 'marquee-builder' );
	wp_enqueue_style( 'marquee-builder-editor' );
	$scr = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! marquee_festive() || ! $scr || 'page' !== $scr->post_type ) {
		return;
	}
	$t   = get_template_directory_uri() . '/';
	$css = '';
	foreach ( array( array( 'Cinzel', 700, 'cinzel-latin-700-normal' ), array( 'Marcellus', 400, 'marcellus-latin-400-normal' ), array( 'Playball', 400, 'playball-latin-400-normal' ), array( 'Nunito Sans', 400, 'nunito-sans-latin-400-normal' ), array( 'Nunito Sans', 700, 'nunito-sans-latin-700-normal' ), array( 'Nunito Sans', 800, 'nunito-sans-latin-800-normal' ) ) as $f ) {
		$css .= sprintf( '@font-face{font-family:"%1$s";font-weight:%2$d;font-display:swap;src:url(%3$sassets/fonts/%4$s.woff2) format("woff2")}', $f[0], $f[1], esc_url( $t ), $f[2] );
	}
	$scene = $t . 'assets/img/scene-v275.webp';
	if ( function_exists( 'ridgeroad_look' ) && function_exists( 'ridgeroad_look_img' ) && 'classic' !== ridgeroad_look() ) {
		$scene = ridgeroad_look_img( 'hero.webp' );
	}
	$bg = get_theme_mod( 'bg_image' );
	if ( $bg ) {
		$scene = $bg;
	}
	$css .= '.mq-ed-show .editor-styles-wrapper{--mq-scene:url(' . esc_url( $scene ) . ')}';
	wp_add_inline_style( 'marquee-builder-editor', $css );
} );

add_filter( 'admin_body_class', function ( $c ) {
	$scr = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( marquee_festive() && $scr && 'page' === $scr->post_type && $scr->is_block_editor() ) {
		$c .= ' mq-ed-show';
	}
	return $c;
} );

/* A box with its own see-through: the see-through styles are switched on for that page (the rest of
   the page keeps the site's own setting, solid unless Look & colors says otherwise). */
function marquee_page_has_own_see() {
	if ( ! is_singular() ) {
		return false;
	}
	$c = (string) get_post_field( 'post_content', get_queried_object_id() );
	return false !== strpos( $c, '"seeOwn":true' );
}
add_filter( 'body_class', function ( $classes ) {
	if ( marquee_festive() && marquee_page_has_own_see() && ! in_array( 'rr-glass', $classes, true ) ) {
		$classes[] = 'rr-glass';
		$classes[] = 'mq-glass-solid';
	}
	return $classes;
}, 20 );
add_action( 'wp_head', function () {
	if ( marquee_festive() && marquee_page_has_own_see() ) {
		echo "<style id=\"mq-glass-solid\">body.mq-glass-solid{--rr-glass:1}</style>\n"; // the rest of the page stays solid
	}
}, 31 );

/* ---------- "Start your page" layouts ---------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'marquee-pages', array( 'label' => __( 'Show pages', 'marquee' ) ) );
	foreach ( marquee_page_layouts() as $slug => $p ) {
		register_block_pattern( 'marquee/page-' . $slug, array(
			'title'       => $p[0],
			'description' => $p[1],
			'categories'  => array( 'marquee-pages' ),
			'postTypes'   => array( 'page' ),
			'viewportWidth' => 900, // in the Patterns list; new pages get Marquee's own "Start your page" picker
			'content'     => $p[2],
		) );
	}
}, 25 );

/** Small helpers to write the layouts. */
function marquee_bx( $a, $inner ) {
	$attrs = wp_json_encode( $a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$kind  = isset( $a['kind'] ) && $a['kind'] ? ' mq-box--' . $a['kind'] : '';
	return "<!-- wp:marquee/show-box {$attrs} -->\n{$inner}\n<!-- /wp:marquee/show-box -->\n\n";
}
function marquee_bp( $t, $cls = '' ) {
	$c = $cls ? ' {"className":"' . $cls . '"}' : '';
	return "<!-- wp:paragraph{$c} -->\n<p" . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . esc_html( $t ) . "</p>\n<!-- /wp:paragraph -->";
}
function marquee_bh( $t, $level = 3 ) {
	return '<!-- wp:heading {"level":' . $level . '} -->' . "\n<h{$level} class=\"wp-block-heading\">" . esc_html( $t ) . "</h{$level}>\n<!-- /wp:heading -->";
}
function marquee_bimg( $file, $style = '', $caption = '' ) {
	$src = MARQUEE_URL . 'assets/img/builder/' . $file;
	$cls = $style ? 'is-style-' . $style : '';
	$a   = $style ? ' {"className":"' . $cls . '"}' : '';
	return "<!-- wp:image{$a} -->\n<figure class=\"wp-block-image" . ( $cls ? ' ' . $cls : '' ) . '"><img src="' . esc_url( $src ) . '" alt=""/>' . ( '' !== $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '' ) . "</figure>\n<!-- /wp:image -->";
}
function marquee_bcols( $cols ) {
	$o = "<!-- wp:columns -->\n<div class=\"wp-block-columns\">";
	foreach ( $cols as $c ) {
		$o .= "<!-- wp:column -->\n<div class=\"wp-block-column\">" . implode( "\n", $c ) . "</div>\n<!-- /wp:column -->";
	}
	return $o . "</div>\n<!-- /wp:columns -->";
}
function marquee_bfaq( $q, $a ) {
	return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . esc_html( $q ) . '</summary>' . marquee_bp( $a ) . "</details>\n<!-- /wp:details -->";
}
function marquee_bmt( $file, $title, $paras, $right = false ) {
	$src = MARQUEE_URL . 'assets/img/builder/' . $file;
	$o   = '<!-- wp:media-text {"mediaType":"image"' . ( $right ? ',"mediaPosition":"right"' : '' ) . '} -->' . "\n";
	$o  .= '<div class="wp-block-media-text' . ( $right ? ' has-media-on-the-right' : '' ) . ' is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="' . esc_url( $src ) . '" alt=""/></figure><div class="wp-block-media-text__content">';
	$o  .= marquee_bh( $title ) . "\n";
	foreach ( $paras as $p ) {
		$o .= marquee_bp( $p ) . "\n";
	}
	return $o . "</div></div>\n<!-- /wp:media-text -->";
}
function marquee_bscript( $t ) {
	return marquee_bp( $t, 'mq-script' );
}

/** The layouts: slug => title, description, blocks. Words are examples to change. */
function marquee_page_layouts() {
	$people = function () {
		$p = array(
			array( 'person-1.webp', __( 'Your name', 'marquee' ), __( 'Lights and programming', 'marquee' ) ),
			array( 'person-2.webp', __( 'Name', 'marquee' ), __( 'Hot cocoa department', 'marquee' ) ),
			array( 'person-3.webp', __( 'Name', 'marquee' ), __( 'Picks the songs', 'marquee' ) ),
			array( 'person-4.webp', __( 'Name', 'marquee' ), __( 'Head of security', 'marquee' ) ),
		);
		$c = array();
		foreach ( $p as $x ) {
			$c[] = array( marquee_bimg( $x[0], 'mq-round' ), marquee_bh( $x[1], 4 ), marquee_bp( $x[2] ) );
		}
		return marquee_bcols( $c );
	};
	$then = marquee_bcols( array(
		array( marquee_bimg( 'then.webp', 'mq-badge', __( 'Our first year', 'marquee' ) ) ),
		array( marquee_bimg( 'now.webp', 'mq-badge', __( 'This year', 'marquee' ) ) ),
	) ) . "\n" . marquee_bp( __( 'Year one: a few hundred lights. This year: thousands, all dancing to the music.', 'marquee' ), 'has-text-align-center' );
	$faq = function ( $list ) {
		$o = array();
		foreach ( $list as $q ) {
			$o[] = marquee_bfaq( $q[0], $q[1] );
		}
		return implode( "\n", $o );
	};
	$gallery = '<!-- wp:gallery {"linkTo":"none"} -->' . "\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">";
	foreach ( array( 'gallery-1.webp', 'gallery-2.webp', 'gallery-3.webp', 'now.webp', 'then.webp', 'gallery-4.webp' ) as $g ) {
		$gallery .= marquee_bimg( $g );
	}
	$gallery .= "</figure>\n<!-- /wp:gallery -->";

	return array(
		'story'   => array(
			__( 'Our Story', 'marquee' ),
			__( 'How it started, meet the family, then and now', 'marquee' ),
			marquee_bscript( __( 'Lighting up our street since 2019', 'marquee' ) ) . "\n\n"
			. marquee_bx( array( 'color' => 'red', 'kind' => 'photo' ), marquee_bmt( 'house.webp', __( 'How it started', 'marquee' ), array( __( 'It began with one strand of lights on the porch and a kid who wanted “more.” Every year since, we’ve added a little more, and a lot more extension cords.', 'marquee' ), __( 'Today the whole yard dances to music on the radio, and every bit of it is planned, built and programmed right here at home.', 'marquee' ) ) ) )
			. marquee_bx( array( 'color' => 'green', 'kind' => 'people', 'ribbon' => true, 'title' => __( 'Meet the Family', 'marquee' ) ), $people() )
			. marquee_bx( array( 'color' => 'blue', 'kind' => 'then', 'ribbon' => true, 'title' => __( 'Then and Now', 'marquee' ) ), $then ),
		),
		'faq'     => array(
			__( 'Questions & Answers', 'marquee' ),
			__( 'Parking, the radio, can we get out of the car…', 'marquee' ),
			marquee_bx( array( 'color' => 'green', 'kind' => 'faq' ), $faq( array(
				array( __( 'Where do I park?', 'marquee' ), __( 'Pull over on the right side of the street and keep driveways clear for our neighbors.', 'marquee' ) ),
				array( __( 'How do I hear the music?', 'marquee' ), __( 'Tune your car radio to our station. You can also listen on your phone from our home page.', 'marquee' ) ),
				array( __( 'Can we get out of the car?', 'marquee' ), __( 'Yes! Please stay on the sidewalk and don’t touch the lights.', 'marquee' ) ),
				array( __( 'Does it cost anything?', 'marquee' ), __( 'The show is free. If you’d like to help, there’s a donation link on our home page.', 'marquee' ) ),
				array( __( 'How long does it take to set up?', 'marquee' ), __( 'About six weekends in the fall, plus a lot of evenings at the computer.', 'marquee' ) ),
			) ) ),
		),
		'gallery' => array(
			__( 'Photo Gallery', 'marquee' ),
			__( 'Your best pictures, year by year', 'marquee' ),
			marquee_bx( array( 'color' => 'blue', 'kind' => 'gallery' ), $gallery )
			. marquee_bx( array( 'color' => 'red', 'kind' => 'then', 'ribbon' => true, 'title' => __( 'Then and Now', 'marquee' ) ), $then ),
		),
		'team'    => array(
			__( 'Meet the Team', 'marquee' ),
			__( 'The people (and pets) behind the lights', 'marquee' ),
			marquee_bx( array( 'color' => 'green', 'kind' => 'people' ), $people() )
			. marquee_bx( array( 'color' => 'red', 'kind' => 'photo' ), marquee_bmt( 'house.webp', __( 'Behind the scenes', 'marquee' ), array( __( 'Tell visitors who builds the show, how long it takes and what everyone’s favorite part is.', 'marquee' ) ), true ) ),
		),
		'give'    => array(
			__( 'Give Back', 'marquee' ),
			__( 'The charity you support and how to help', 'marquee' ),
			marquee_bscript( __( 'Lights for a Cause', 'marquee' ) ) . "\n\n"
			. marquee_bx( array( 'color' => 'red', 'kind' => 'photo' ), marquee_bmt( 'give.webp', __( 'Who we’re helping this year', 'marquee' ), array( __( 'Write a few lines about the charity you support and why it matters to your family.', 'marquee' ), __( 'Every donation goes straight to them.', 'marquee' ) ) ) )
			. marquee_bx( array( 'color' => 'green', 'kind' => 'faq', 'ribbon' => true, 'title' => __( 'Ways to Help', 'marquee' ) ), $faq( array(
				array( __( 'Donate online', 'marquee' ), __( 'Use the donation link on our home page. Every dollar goes to the charity.', 'marquee' ) ),
				array( __( 'Bring a can', 'marquee' ), __( 'Drop off canned food in the bin by the mailbox during show hours.', 'marquee' ) ),
				array( __( 'Share the show', 'marquee' ), __( 'Tell your friends! The more visitors, the more we raise.', 'marquee' ) ),
			) ) ),
		),
	);
}

/* ---------- A button at the top for your own pages ---------- */

/** Your own pages that asked for a button at the top (in menu order, then title). */
function marquee_menu_pages() {
	static $pages = null;
	if ( null !== $pages ) {
		return $pages;
	}
	$skip  = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'ridgeroad_kids_page' ), (int) get_option( 'ridgeroad_songs_page' ), (int) get_option( 'ridgeroad_facts_page' ) ) );
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'meta_key'       => 'mq_menu', // phpcs:ignore WordPress.DB.SlowDBQuery -- a handful of pages
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'post__not_in'   => $skip,
		'no_found_rows'  => true,
	) );
	return $pages;
}

/** Does this page use the show's boxes or Marquee blocks? (then it's drawn like the show's own pages) */
function marquee_is_show_page( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	return has_block( 'marquee/show-box', $post ) || ( false !== strpos( $post->post_content, '<!-- wp:marquee/' ) && ! has_block( 'marquee/home', $post ) );
}

/* A new page made with show boxes gets its button at the top the first time it's published (the
   owner can turn it off in the page's "Top of your website" setting). */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'page' !== $post->post_type || metadata_exists( 'post', $post->ID, 'mq_menu' ) ) {
		return;
	}
	if ( has_block( 'marquee/show-box', $post ) ) {
		update_post_meta( $post->ID, 'mq_menu', true );
	}
}, 10, 3 );
