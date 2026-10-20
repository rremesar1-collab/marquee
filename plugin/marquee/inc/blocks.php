<?php
/**
 * Marquee blocks and shortcodes: every show section can go on any page, in any theme.
 *
 *  - Block editor: "Marquee" group in the block inserter (one block per section).
 *  - Shortcodes:   [marquee section="kids"] or [marquee_kids], [marquee_weather], ...
 *
 * With the festive theme the sections use its look; with any other theme they get
 * Marquee's own plain styling (assets/marquee.css, everything inside .mq-skin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The sections: slug => title, description, dashicon. */
function marquee_sections() {
	return array(
		'home'      => array( __( 'Show Home Page', 'marquee' ), __( 'The whole show page in one block: status, countdown, tonight’s show, weather, playlist, tips, guestbook, donate and social.', 'marquee' ), 'star-filled' ),
		'status'    => array( __( 'Show Status', 'marquee' ), __( 'Big banner: show tonight, starting soon, canceled or off season. Updates by itself.', 'marquee' ), 'megaphone' ),
		'countdown' => array( __( 'Countdown', 'marquee' ), __( 'Counts down to showtime and to Christmas.', 'marquee' ), 'clock' ),
		'tonight'   => array( __( 'Tonight’s Show + Weather', 'marquee' ), __( 'Tonight’s Show and Weather side by side.', 'marquee' ), 'calendar-alt' ),
		'show'      => array( __( 'Tonight’s Show', 'marquee' ), __( 'Radio station, show hours, Now Playing from Remote Falcon and directions.', 'marquee' ), 'format-audio' ),
		'weather'   => array( __( 'Weather', 'marquee' ), __( 'Weather now, tonight hour by hour, and the radar.', 'marquee' ), 'cloud' ),
		'playlist'  => array( __( 'Tonight’s Playlist', 'marquee' ), __( 'The song list from Remote Falcon, with the song playing now lit up.', 'marquee' ), 'playlist-audio' ),
		'live-cam'  => array( __( 'Live Camera', 'marquee' ), __( 'Your YouTube live stream (set it in Appearance → Customize).', 'marquee' ), 'video-alt3' ),
		'visit'     => array( __( 'Before You Visit', 'marquee' ), __( 'Your visitor tips.', 'marquee' ), 'info' ),
		'explore'   => array( __( 'Songs & Kids’ Corner Links', 'marquee' ), __( 'Two big buttons to the Songs page and the Kids’ Corner.', 'marquee' ), 'admin-links' ),
		'stats'     => array( __( 'Behind the Lights', 'marquee' ), __( 'Your show’s numbers (lights, channels, songs…).', 'marquee' ), 'chart-bar' ),
		'guestbook' => array( __( 'Guestbook', 'marquee' ), __( 'Visitor messages and the sign-the-guestbook form.', 'marquee' ), 'book' ),
		'donate'    => array( __( 'Donate', 'marquee' ), __( 'Your donation link with a QR code.', 'marquee' ), 'heart' ),
		'social'    => array( __( 'Stay Connected', 'marquee' ), __( 'Facebook, TikTok and Share the Show buttons.', 'marquee' ), 'share' ),
		'app'       => array( __( 'Get the App', 'marquee' ), __( 'Install-the-app and show alerts card (shows only on phones that can use it).', 'marquee' ), 'smartphone' ),
		'kids'      => array( __( 'Kids’ Corner', 'marquee' ), __( 'Your Name in Lights, Make a Light Show (and the contest), coloring pages and games.', 'marquee' ), 'smiley' ),
		'songs'     => array( __( 'Songs & Videos', 'marquee' ), __( 'This year’s show videos and favorite song voting.', 'marquee' ), 'format-video' ),
	);
}

/** True when the festive theme draws the pages (it has its own look). */
function marquee_festive() {
	return 'ridgeroad' === get_template();
}

/** Kids' Corner scripts, wherever the Kids' Corner is placed. */
function marquee_kids_assets() {
	if ( wp_script_is( 'ridgeroad-kids', 'enqueued' ) || ! defined( 'RR_FEAT_URL' ) ) {
		return;
	}
	wp_enqueue_script( 'ridgeroad-lab', RR_FEAT_URL . 'assets/js/lightlab.js', array(), RIDGEROAD_VERSION, true );
	wp_enqueue_script( 'ridgeroad-kids', RR_FEAT_URL . 'assets/js/kids.js', array( 'ridgeroad-lab' ), RIDGEROAD_VERSION, true );
	wp_localize_script( 'ridgeroad-kids', 'RRShow', array(
		'ajax'  => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
		'watch' => function_exists( 'ridgeroad_show_for_watch' ) ? ridgeroad_show_for_watch() : array(),
	) );
}

/** The festive theme already draws the whole show on its home page, and the Kids/Songs pages. */
function marquee_drawn_by_theme( $slug ) {
	if ( ! marquee_festive() ) {
		return false;
	}
	if ( 'home' === $slug ) {
		return is_front_page();
	}
	return in_array( $slug, array( 'kids', 'songs' ), true ) && is_page() && (int) get_option( 'ridgeroad_' . $slug . '_page' ) === get_queried_object_id();
}

/** HTML of one section (no wrapper). */
function marquee_section_html( $slug ) {
	if ( ! function_exists( 'ridgeroad_part' ) || ! function_exists( 'ridgeroad_mod' ) ) {
		return '';
	}
	$donate = false;
	ob_start();
	switch ( $slug ) {
		case 'home':
			foreach ( array( 'status', 'countdown', 'app-card' ) as $p ) {
				ridgeroad_part( $p );
			}
			echo '<div class="rr-grid rr-grid--main">';
			ridgeroad_part( 'show' );
			ridgeroad_part( 'weather' );
			echo '</div>';
			foreach ( array( 'playlist', 'live-cam', 'visit', 'explore', 'stats', 'guestbook' ) as $p ) {
				ridgeroad_part( $p );
			}
			echo '<div id="support" class="rr-grid rr-grid--support">';
			ridgeroad_part( 'donate' );
			ridgeroad_part( 'social' );
			echo '</div>';
			$donate = true;
			break;
		case 'tonight':
			echo '<div class="rr-grid rr-grid--main">';
			ridgeroad_part( 'show' );
			ridgeroad_part( 'weather' );
			echo '</div>';
			break;
		case 'app':
			ridgeroad_part( 'app-card' );
			break;
		case 'kids':
			if ( ridgeroad_mod( 'kids_show' ) ) {
				ridgeroad_part( 'kids' );
				marquee_kids_assets();
			} else {
				echo '<article class="rr-panel rr-panel--night rr-content"><p>' . esc_html__( 'The Kids’ Corner is resting right now — check back soon!', 'marquee' ) . '</p></article>';
			}
			break;
		case 'donate':
			ridgeroad_part( 'donate' );
			$donate = true;
			break;
		default:
			if ( isset( marquee_sections()[ $slug ] ) ) {
				ridgeroad_part( $slug );
			}
	}
	$html = trim( ob_get_clean() );
	if ( $donate && ridgeroad_mod( 'cashapp_url' ) && ! ridgeroad_mod( 'cashapp_qr' ) ) {
		wp_enqueue_script( 'ridgeroad-qrcode', RR_FEAT_URL . 'assets/js/qrcode.js', array(), '2.0.4', true );
	}
	return $html;
}

/** Wrapper classes: plain styling outside the festive theme; no repeated title on the section's own page. */
function marquee_block_class( $slug ) {
	$c = 'marquee mq-block mq-block--' . $slug;
	if ( ! marquee_festive() ) {
		$c .= ' mq-skin';
		$own = array( 'kids' => 'ridgeroad_kids_page', 'songs' => 'ridgeroad_songs_page' );
		if ( isset( $own[ $slug ] ) && is_page() && (int) get_option( $own[ $slug ] ) === get_queried_object_id() ) {
			$c .= ' mq-notitle';
		}
	}
	return $c;
}

/** One section with its wrapper; $attrs = extra wrapper attributes from the block editor. */
function marquee_render( $slug, $wrapper = '' ) {
	$slug = sanitize_key( str_replace( '_', '-', $slug ) );
	if ( marquee_drawn_by_theme( $slug ) ) {
		return '';
	}
	$html = marquee_section_html( $slug );
	if ( '' === $html ) {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( marquee_sections()[ $slug ] ) ) {
			return '';
		}
		// Only people who can edit the site see why a section is empty.
		$html = '<p class="mq-off">' . esc_html( sprintf( __( 'Marquee: “%s” has nothing to show right now (turned off, or not set up yet in Marquee → Settings or Appearance → Customize). Only editors see this note.', 'marquee' ), marquee_sections()[ $slug ][0] ) ) . '</p>';
	}
	$class = marquee_block_class( $slug );
	if ( $wrapper ) {
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
	return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
}

/* ---------- Blocks ---------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	wp_register_script( 'marquee-blocks', MARQUEE_URL . 'assets/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ), MARQUEE_VERSION, true );
	$list = array();
	foreach ( marquee_sections() as $slug => $s ) {
		$list[] = array( 'slug' => $slug, 'title' => $s[0], 'desc' => $s[1], 'icon' => $s[2] );
		register_block_type( 'marquee/' . $slug, array(
			'api_version'     => 2,
			'title'           => $s[0],
			'category'        => 'marquee',
			'editor_script'   => 'marquee-blocks',
			'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
			'attributes'      => array( 'align' => array( 'type' => 'string' ) ),
			'render_callback' => function () use ( $slug ) {
				return marquee_render( $slug, get_block_wrapper_attributes( array( 'class' => marquee_block_class( $slug ) ) ) );
			},
		) );
	}
	wp_add_inline_script( 'marquee-blocks', 'window.MarqueeBlocks = ' . wp_json_encode( array(
		'sections' => $list,
		'note'     => __( 'It shows live on your site; preview the page to see it.', 'marquee' ),
	) ) . ';', 'before' );
} );

add_filter( 'block_categories_all', function ( $cats ) {
	array_unshift( $cats, array( 'slug' => 'marquee', 'title' => __( 'Marquee (light show)', 'marquee' ), 'icon' => 'star-filled' ) );
	return $cats;
} );

/* ---------- Shortcodes ---------- */
add_action( 'init', function () {
	add_shortcode( 'marquee', function ( $a ) {
		$a = shortcode_atts( array( 'section' => 'home' ), $a, 'marquee' );
		return marquee_render( $a['section'] );
	} );
	foreach ( array_keys( marquee_sections() ) as $slug ) {
		add_shortcode( 'marquee_' . str_replace( '-', '_', $slug ), function () use ( $slug ) {
			return marquee_render( $slug );
		} );
	}
} );

/* ---------- Other themes: plain styling, snow, app steps, Kids/Songs pages ---------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( marquee_festive() || ! function_exists( 'ridgeroad_part' ) ) {
		return;
	}
	wp_enqueue_style( 'marquee', MARQUEE_URL . 'assets/marquee.css', array(), MARQUEE_VERSION . '.' . (int) @filemtime( MARQUEE_DIR . 'assets/marquee.css' ) );
}, 30 );

add_action( 'wp_footer', function () {
	if ( marquee_festive() || ! function_exists( 'ridgeroad_part' ) ) {
		return;
	}
	if ( 'off' !== ridgeroad_mod( 'snow_amount' ) ) {
		echo '<canvas class="rr-snow mq-snow" aria-hidden="true"></canvas>' . "\n";
	}
	echo '<div class="mq-skin mq-appsheet">';
	ridgeroad_part( 'app-sheet' );
	echo '</div>' . "\n";
}, 5 );

/**
 * The Kids' Corner and Songs pages always show their section in other themes, even
 * when the page itself is empty (Marquee makes those pages automatically).
 */
add_filter( 'the_content', function ( $content ) {
	if ( marquee_festive() || ! function_exists( 'ridgeroad_part' ) || ! in_the_loop() || ! is_main_query() || ! is_page() ) {
		return $content;
	}
	$id  = get_the_ID();
	$map = array(
		'kids'  => (int) get_option( 'ridgeroad_kids_page' ),
		'songs' => (int) get_option( 'ridgeroad_songs_page' ),
	);
	foreach ( $map as $slug => $page ) {
		if ( $page && $id === $page && ! has_block( 'marquee/' . $slug ) && ! has_shortcode( $content, 'marquee_' . $slug ) && false === strpos( $content, 'section="' . $slug . '"' ) ) {
			return $content . marquee_render( $slug );
		}
	}
	return $content;
}, 20 );

/**
 * "Make my show pages": Home (whole show, set as the front page), Kids' Corner and
 * Songs & Videos. Never overwrites a page that already has content.
 */
function marquee_make_pages() {
	$out = array( 'pages' => array(), 'front_kept' => false );
	$fill = function ( $id, $slug ) {
		$p = get_post( $id );
		if ( $p && '' === trim( $p->post_content ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => '<!-- wp:marquee/' . $slug . ' {"align":"wide"} /-->' ) );
		}
	};
	$front = (int) get_option( 'page_on_front' );
	if ( 'page' === get_option( 'show_on_front' ) && $front && 'publish' === get_post_status( $front ) ) {
		if ( '' === trim( (string) get_post_field( 'post_content', $front ) ) ) {
			$fill( $front, 'home' );
		} else {
			$out['front_kept'] = ! has_block( 'marquee/home', $front );
		}
		$home = $front;
	} else {
		$home = (int) wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => __( 'Home', 'marquee' ),
			'post_content'   => '<!-- wp:marquee/home {"align":"wide"} /-->',
			'comment_status' => 'closed',
		) );
		if ( $home ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
		}
	}
	if ( $home ) {
		$out['pages'][] = $home;
	}
	foreach ( array( 'kids' => 'ridgeroad_kids_url', 'songs' => 'ridgeroad_songs_url' ) as $slug => $fn ) {
		if ( ! function_exists( $fn ) ) {
			continue;
		}
		$fn(); // creates the page if it is missing
		$id = (int) get_option( 'ridgeroad_' . $slug . '_page' );
		if ( $id ) {
			$fill( $id, $slug );
			$out['pages'][] = $id;
		}
	}
	return $out;
}
