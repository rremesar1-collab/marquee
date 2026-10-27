<?php
/**
 * "Your Name in Lights": kids send their first name from the Kids' Corner and
 * FPP shows it on the garage-door projector and the TV in a short break between songs.
 *
 * - Names are accepted only while the show is running.
 * - First name only, letters only, plus a message picked from a short list (Marquee: your own messages).
 * - Bad-word filter (with l33t-speak folding), your own block list, per-phone limits.
 * - Names go live automatically if they pass the filter; block any of them in
 *   WP Admin -> Kids' Names.
 * - FPP pulls the queue with a secret key (the site never connects into your
 *   home network) and reports back what it showed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The standard messages kids pick from (NAME is where the name goes). */
function ridgeroad_name_greetings_std() {
	return array(
		'merry'    => 'Merry Christmas, NAME!',
		'holidays' => 'Happy Holidays, NAME!',
		'santa'    => 'Hi Santa! Love, NAME',
		'shine'    => 'NAME, you shine bright!',
	);
}

/** Messages kids pick from: key => 'Merry Christmas, NAME!'. Marquee lets the show owner write their own. */
function ridgeroad_name_greetings() {
	$g = apply_filters( 'ridgeroad_name_greetings', ridgeroad_name_greetings_std() );
	return is_array( $g ) && $g ? $g : ridgeroad_name_greetings_std();
}

/** The message as it goes on the screens: "MERRY CHRISTMAS, Emma!" */
function ridgeroad_name_message( $tpl, $name ) {
	$tpl = false === strpos( $tpl, 'NAME' ) ? $tpl . ' NAME' : $tpl;
	$k   = strpos( $tpl, 'NAME' );                        // only this one is the name ("Your name in lights, NAME!")
	return mb_strtoupper( substr( $tpl, 0, $k ), 'UTF-8' ) . $name . mb_strtoupper( substr( $tpl, $k + 4 ), 'UTF-8' );
}

add_action( 'init', function () {
	register_post_type( 'rr_shoutout', array(
		'labels'          => array(
			'name'          => __( 'Kids’ Names', 'ridgeroad' ),
			'singular_name' => __( 'Kid’s Name', 'ridgeroad' ),
			'menu_name'     => __( 'Kids’ Names', 'ridgeroad' ),
			'all_items'     => __( 'Names in Lights', 'ridgeroad' ),
			'not_found'     => __( 'No names yet.', 'ridgeroad' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => function_exists( 'marquee_get' ) ? 'marquee' : true, // under the Marquee menu on Marquee sites
		'menu_icon'       => 'dashicons-megaphone',
		'menu_position'   => 27,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
	) );
} );

/** The FPP key (created once, shown in the Customizer). */
function ridgeroad_names_key() {
	$k = get_option( 'ridgeroad_names_key' );
	if ( ! $k ) {
		$k = wp_generate_password( 32, false );
		update_option( 'ridgeroad_names_key', $k, false );
	}
	return $k;
}

function ridgeroad_names_open() {
	if ( ! ridgeroad_mod( 'names_on' ) ) {
		return false;
	}
	$s = ridgeroad_status();
	return 'running' === $s['state'] || ridgeroad_mod( 'names_always' );
}

/**
 * "Only at the show": the distance (miles) a phone must be within to send a name,
 * or null when names can be sent from anywhere (or the show's location isn't set).
 */
function ridgeroad_names_geo() {
	if ( ! ridgeroad_mod( 'names_geo' ) || ( ! (float) RIDGEROAD_LAT && ! (float) RIDGEROAD_LON ) ) {
		return null;
	}
	$mi = (float) ridgeroad_mod( 'names_geo_miles' );
	return max( 0.1, min( 50.0, $mi > 0 ? $mi : 0.5 ) );
}
/** Miles between two points (great-circle). */
function ridgeroad_miles_between( $lat1, $lon1, $lat2, $lon2 ) {
	$r = 3958.8;
	$a = sin( deg2rad( $lat2 - $lat1 ) / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( deg2rad( $lon2 - $lon1 ) / 2 ) ** 2;
	return 2 * $r * asin( min( 1, sqrt( $a ) ) );
}

/* -------------------------------------------------------------------------
 * Filter
 * ---------------------------------------------------------------------- */
function ridgeroad_name_fold( $s ) {
	$s = strtolower( remove_accents( $s ) );
	$s = strtr( $s, array( '0' => 'o', '1' => 'i', '!' => 'i', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't', '8' => 'b', '+' => 't', '|' => 'l' ) );
	$s = preg_replace( '/[^a-z]/', '', $s );
	return preg_replace( '/(.)\1+/', '$1', $s ); // "fuuuun" -> "fun"
}

/** Offensive pieces blocked anywhere inside a name (folded). */
function ridgeroad_name_block_contains() {
	return array_map( 'ridgeroad_name_fold', array(
		'fuck', 'fuk', 'fck', 'shit', 'shyt', 'bitch', 'btch', 'cunt', 'cock', 'pussy', 'pusy', 'asshole', 'bastard',
		'slut', 'whore', 'nigg', 'niga', 'nigr', 'kike', 'retard', 'rapist', 'penis', 'vagina', 'porn', 'nazi', 'hitler', 'dick',
	) );
}
/** Words blocked when they ARE the whole name (folded). Your own block list is added here. */
function ridgeroad_name_block_exact() {
	$base  = array( 'ass', 'arse', 'hoe', 'hoes', 'fag', 'spic', 'chink', 'rape', 'sex', 'sexy', 'tits', 'boob', 'boobs', 'butt',
		'poop', 'pee', 'fart', 'damn', 'hell', 'crap', 'kill', 'killer', 'die', 'dead', 'death', 'gun', 'guns', 'drug', 'drugs',
		'weed', 'stupid', 'idiot', 'dumb', 'loser', 'hate', 'ugly', 'kkk', 'gay', 'satan', 'devil', 'trump', 'biden', 'balls',
		'anal', 'anus', 'milf', 'diaper', 'nobody', 'anonymous', 'test', 'santa', 'grinch', 'mom', 'dad', 'yourmom', 'none', 'name' );
	$extra = preg_split( '/[\s,]+/', strtolower( (string) ridgeroad_mod( 'names_block' ) ) );
	return array_values( array_filter( array_unique( array_map( 'ridgeroad_name_fold', array_merge( $base, $extra ) ) ) ) );
}

/** Real names that contain a blocked piece, so they still work. */
function ridgeroad_name_allow() {
	return array( 'dickson', 'dickens', 'dickinson', 'benedick', 'hancock', 'peacock', 'cockburn', 'scunthorpe', 'dickie' );
}

/** Returns a clean upper-case first name, or '' if it can't be shown. */
function ridgeroad_clean_kid_name( $raw ) {
	$raw = trim( wp_strip_all_tags( (string) $raw ) );
	$raw = preg_split( '/\s+/', $raw )[0];
	if ( ! preg_match( "/^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'\-]{1,13}$/u", $raw ) ) {
		return '';
	}
	$fold = ridgeroad_name_fold( $raw );
	if ( strlen( $fold ) < 2 ) {
		return '';
	}
	if ( in_array( $fold, ridgeroad_name_block_exact(), true ) ) {
		return '';
	}
	if ( ! in_array( $fold, array_map( 'ridgeroad_name_fold', ridgeroad_name_allow() ), true ) ) {
		foreach ( ridgeroad_name_block_contains() as $w ) {
			if ( $w && false !== strpos( $fold, $w ) ) {
				return '';
			}
		}
		// keyboard mash / no vowels ("xkcd", "zzzz")
		if ( ! preg_match( '/[aeiouy]/', $fold ) ) {
			return '';
		}
	}
	return mb_strtoupper( remove_accents( $raw ) );
}

/* -------------------------------------------------------------------------
 * Kids send a name
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_rr_name_send', 'ridgeroad_name_send' );
add_action( 'wp_ajax_nopriv_rr_name_send', 'ridgeroad_name_send' );
function ridgeroad_name_send() {
	nocache_headers();
	$fail = function ( $m, $code = 400 ) {
		wp_send_json_error( array( 'message' => $m ), $code );
	};
	if ( ! check_ajax_referer( 'rr_names', 'nonce', false ) ) {
		$fail( __( 'Please refresh the page and try again.', 'ridgeroad' ) );
	}
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success( array( 'place' => 1 ) );
	}
	if ( ! ridgeroad_names_open() ) {
		$fail( __( 'Names can only be sent while the show is on. Come back during show time!', 'ridgeroad' ) );
	}
	// "Only at the show": the phone's location is compared with the show's and never stored.
	// The show owner and Show helpers can send test names from anywhere.
	$geo = ridgeroad_names_geo();
	if ( $geo && ! ( function_exists( 'ridgeroad_ctl_can' ) && ridgeroad_ctl_can() ) ) {
		$lat = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : 0.0;
		$lon = isset( $_POST['lon'] ) ? (float) $_POST['lon'] : 0.0;
		if ( ! isset( $_POST['lat'], $_POST['lon'] ) || abs( $lat ) > 90 || abs( $lon ) > 180 || ( ! $lat && ! $lon ) ) {
			$fail( __( 'To send your name, let this page see your location when your phone asks. It’s only used to check you’re at the show.', 'ridgeroad' ), 403 );
		}
		$acc   = isset( $_POST['acc'] ) ? max( 0.0, (float) $_POST['acc'] ) : 0.0; // meters
		$slack = min( $acc / 1609.344, 0.25 ); // allow for a fuzzy phone fix, up to a quarter mile
		if ( ridgeroad_miles_between( $lat, $lon, (float) RIDGEROAD_LAT, (float) RIDGEROAD_LON ) - $slack > $geo ) {
			$fail( __( 'Your name can only go up in lights when you’re at the show. Come see us!', 'ridgeroad' ), 403 );
		}
	}
	$name = ridgeroad_clean_kid_name( isset( $_POST['kid'] ) ? wp_unslash( $_POST['kid'] ) : '' );
	if ( '' === $name ) {
		$fail( __( 'Hmm, Santa’s elves can’t put that one in lights. Please type just your first name.', 'ridgeroad' ) );
	}
	$msgs = ridgeroad_name_greetings();
	$g    = isset( $_POST['greet'] ) ? sanitize_key( $_POST['greet'] ) : '';
	$g    = isset( $msgs[ $g ] ) ? $g : array_keys( $msgs )[0];

	// limits: a few an hour from one phone/network (3 unless the show picked another number), and
	// no repeats of the same name for 20 minutes. A logged-in site admin (you, testing) skips both limits.
	$admin = current_user_can( 'manage_options' );
	$ip    = ridgeroad_ip_hash();
	$k     = 'rr_nm_' . $ip;
	$n     = (int) get_transient( $k );
	$most  = max( 1, min( 10, (int) apply_filters( 'ridgeroad_names_per_phone', 3 ) ) );
	if ( ! $admin && $n >= $most ) {
		$fail( 1 === $most
			? __( 'You already sent your name! Watch for it in the show. You can send another in a little while.', 'ridgeroad' )
			/* translators: %d: how many names one phone can send in an hour */
			: sprintf( _n( 'You already sent %d name! Watch for it in the show. You can send more in a little while.', 'You already sent %d names! Watch for them in the show. You can send more in a little while.', $most, 'ridgeroad' ), $most ), 429 );
	}
	$dup = get_posts( array(
		'post_type'   => 'rr_shoutout',
		'post_status' => 'publish',
		'title'       => $name,
		'date_query'  => array( array( 'after' => '20 minutes ago' ) ),
		'fields'      => 'ids',
		'numberposts' => 1,
	) );
	if ( $dup && ! $admin ) {
		wp_send_json_success( array( 'place' => ridgeroad_name_place( $dup[0] ), 'text' => get_post_meta( $dup[0], '_rr_text', true ), 'again' => true, 'live' => (bool) apply_filters( 'ridgeroad_names_live', false ), 'songs' => ridgeroad_names_songs_left() ) );
	}
	if ( count( ridgeroad_name_queue( 40 ) ) >= 40 ) {
		$fail( __( 'Lots of names right now! Please try again in a few minutes.', 'ridgeroad' ), 429 );
	}
	$err = (string) apply_filters( 'ridgeroad_name_send_error', '' ); // Marquee: a picture sent with the name is checked here
	if ( '' !== $err ) {
		$fail( $err );
	}
	$id = wp_insert_post( array( 'post_type' => 'rr_shoutout', 'post_status' => 'publish', 'post_title' => $name ), true );
	if ( is_wp_error( $id ) ) {
		$fail( __( 'Something went wrong. Please try again.', 'ridgeroad' ) );
	}
	$text = ridgeroad_name_message( $msgs[ $g ], $name );
	update_post_meta( $id, '_rr_text', $text );
	// themed sequences (Marquee): the theme the kid picked, '' = the classic break
	$theme = apply_filters( 'ridgeroad_name_theme', '', isset( $_POST['theme'] ) ? sanitize_title( wp_unslash( $_POST['theme'] ) ) : '' );
	if ( $theme ) {
		update_post_meta( $id, '_rr_theme', $theme );
		ridgeroad_stat_add( 'name_theme' );
	}
	update_post_meta( $id, '_rr_shown', 0 );
	ridgeroad_stat_add( 'name_sent' );
	if ( ! $admin ) {
		set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	}
	do_action( 'ridgeroad_name_added', $id, $text ); // Marquee: make the spoken version
	$theme_name = ( $theme && function_exists( 'marquee_themes' ) && isset( marquee_themes()[ $theme ] ) ) ? marquee_themes()[ $theme ]['name'] : '';
	wp_send_json_success( array( 'place' => ridgeroad_name_place( $id ), 'text' => $text, 'id' => $id, 'buzz' => ridgeroad_buzz_token( $id ), 'theme' => $theme_name, 'live' => (bool) apply_filters( 'ridgeroad_names_live', false ), 'songs' => ridgeroad_names_songs_left() ) );
}

/** Waiting names, oldest first (names older than 2 hours are skipped). */
function ridgeroad_name_queue( $limit = 50 ) {
	return get_posts( array(
		'post_type'   => 'rr_shoutout',
		'post_status' => 'publish',
		'numberposts' => $limit,
		'orderby'     => 'date',
		'order'       => 'ASC',
		'meta_query'  => array( array( 'key' => '_rr_shown', 'value' => '0' ) ), // phpcs:ignore
		'date_query'  => array( array( 'after' => '45 minutes ago' ) ),
	) );
}
function ridgeroad_name_place( $id ) {
	$ids = wp_list_pluck( ridgeroad_name_queue( 200 ), 'ID' );
	$i   = array_search( (int) $id, array_map( 'intval', $ids ), true );
	return false === $i ? 1 : $i + 1;
}

/* -------------------------------------------------------------------------
 * FPP pulls the queue / reports what it showed (secret key)
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_rr_names_pull', 'ridgeroad_names_pull' );
add_action( 'wp_ajax_nopriv_rr_names_pull', 'ridgeroad_names_pull' );
function ridgeroad_names_pull() {
	nocache_headers();
	header( 'X-Cache-Enabled: False' );
	$key = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
	if ( ! hash_equals( ridgeroad_names_key(), $key ) ) {
		wp_send_json_error( array( 'message' => 'bad key' ), 403 );
	}
	update_option( 'ridgeroad_names_seen', time(), false ); // "FPP last checked in" for the admin screen
	if ( ! empty( $_POST['done'] ) ) {
		foreach ( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['done'] ) ) ) ) as $id ) {
			if ( $id && 'rr_shoutout' === get_post_type( $id ) && ! (int) get_post_meta( $id, '_rr_shown', true ) ) {
				update_post_meta( $id, '_rr_shown', time() );
				ridgeroad_stat_add( 'name_shown' );
				do_action( 'ridgeroad_name_shown', $id ); // Marquee: delete the kid's picture once it's been shown
			}
		}
	}
	// what FPP-Master is doing right now (for Show Control)
	if ( ! empty( $_POST['fpp'] ) ) {
		$f = json_decode( wp_unslash( $_POST['fpp'] ), true ); // phpcs:ignore
		if ( is_array( $f ) ) {
			update_option( 'ridgeroad_fpp', array(
				'status'   => isset( $f['status'] ) ? sanitize_text_field( mb_substr( (string) $f['status'], 0, 30 ) ) : '',
				'playlist' => isset( $f['playlist'] ) ? sanitize_text_field( mb_substr( (string) $f['playlist'], 0, 80 ) ) : '',
				'sequence' => isset( $f['sequence'] ) ? sanitize_text_field( mb_substr( preg_replace( '/\.fseq$/i', '', (string) $f['sequence'] ), 0, 80 ) ) : '',
				// the FPP's playlists (script 3.9+), for "Only during these playlists" in Settings; kept when a ping leaves them out
				// names script 3.13+: songs played since the last names break, the "every N songs" setting, a break lined up
				'played'   => isset( $f['played'] ) ? max( 0, (int) $f['played'] ) : null,
				'every'    => isset( $f['every'] ) ? max( 1, (int) $f['every'] ) : null,
				'queued'   => ! empty( $f['queued'] ),
				'playlists' => isset( $f['playlists'] ) && is_array( $f['playlists'] )
					? array_slice( array_values( array_filter( array_map( function ( $p ) { return sanitize_text_field( mb_substr( (string) $p, 0, 80 ) ); }, $f['playlists'] ) ) ), 0, 60 )
					: ( is_array( $prev = get_option( 'ridgeroad_fpp' ) ) && isset( $prev['playlists'] ) ? (array) $prev['playlists'] : array() ),
			), false );
			if ( function_exists( 'ridgeroad_app_fpp_checkin' ) ) {
				ridgeroad_app_fpp_checkin( isset( $f['status'] ) ? (string) $f['status'] : '', isset( $f['playlist'] ) ? (string) $f['playlist'] : '' );
			}
		}
	}
	// the names script's setup check (shown in Marquee → Settings → Connections)
	if ( ! empty( $_POST['report'] ) ) {
		$r = json_decode( wp_unslash( $_POST['report'] ), true ); // phpcs:ignore
		if ( is_array( $r ) && ! empty( $r['checks'] ) && is_array( $r['checks'] ) ) {
			$checks = array();
			foreach ( array_slice( $r['checks'], 0, 20 ) as $c ) {
				if ( is_array( $c ) ) {
					$checks[] = array( 'ok' => ! empty( $c['ok'] ), 'msg' => sanitize_text_field( mb_substr( (string) ( $c['msg'] ?? '' ), 0, 240 ) ) );
				}
			}
			update_option( 'ridgeroad_names_report', array( 't' => time(), 'v' => sanitize_text_field( mb_substr( (string) ( $r['v'] ?? '' ), 0, 10 ) ), 'checks' => $checks ), false );
		}
	}
	$out = array();
	if ( ridgeroad_mod( 'names_on' ) && ! ridgeroad_names_paused() ) {
		foreach ( ridgeroad_name_queue( 12 ) as $i => $p ) {
			// Marquee adds the spoken clip here ('audio', 'say_for') when spoken names are on
			// Marquee also adds the kid's picture ('photo'), or returns false to hold a name whose picture isn't approved yet
			$item = apply_filters( 'ridgeroad_names_pull_item', array( 'id' => $p->ID, 'text' => get_post_meta( $p->ID, '_rr_text', true ) ), $p, $i );
			if ( $item ) {
				$out[] = $item;
			}
		}
		// the first 6 go up at the next break: buzz the phones that asked, once that break is coming up
		if ( $out && function_exists( 'ridgeroad_app_buzz_names' ) && ridgeroad_names_songs_left() <= 1 ) {
			ridgeroad_app_buzz_names( wp_list_pluck( array_slice( $out, 0, 6 ), 'id' ) );
		}
	}
	wp_send_json_success( apply_filters( 'ridgeroad_names_pull_data', array( 'names' => $out, 'color' => ridgeroad_mod( 'names_color' ) ) ) );
}

/**
 * Songs until the next names break: 1 = after the current song. Uses what the FPP reported (names script 3.13+)
 * and "Names break after every N songs"; an older script or no report = after the current song.
 */
function ridgeroad_names_songs_left() {
	$f = get_option( 'ridgeroad_fpp' );
	if ( ! is_array( $f ) || empty( $f['every'] ) || ! isset( $f['played'] ) ) {
		return 1;
	}
	if ( ! empty( $f['queued'] ) || (int) $f['every'] <= 1 ) {
		return 1;
	}
	return max( 1, (int) $f['every'] - (int) $f['played'] );
}

add_action( 'wp_ajax_rr_names_nonce', 'ridgeroad_names_nonce' );
add_action( 'wp_ajax_nopriv_rr_names_nonce', 'ridgeroad_names_nonce' );
function ridgeroad_names_nonce() {
	nocache_headers();
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'rr_names' ), 'open' => ridgeroad_names_open() ) );
}

/* -------------------------------------------------------------------------
 * Admin list
 * ---------------------------------------------------------------------- */
add_filter( 'manage_rr_shoutout_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox" />', 'title' => __( 'Name', 'ridgeroad' ), 'rr_text' => __( 'Shows as', 'ridgeroad' ), 'rr_shown' => __( 'On the screens', 'ridgeroad' ), 'date' => __( 'Sent', 'ridgeroad' ) );
} );
add_action( 'manage_rr_shoutout_posts_custom_column', function ( $col, $id ) {
	if ( 'rr_text' === $col ) {
		echo esc_html( get_post_meta( $id, '_rr_text', true ) );
	} elseif ( 'rr_shown' === $col ) {
		$t = (int) get_post_meta( $id, '_rr_shown', true );
		echo $t ? esc_html( sprintf( __( 'Shown at %s', 'ridgeroad' ), wp_date( __( 'g:i a', 'ridgeroad' ), $t ) ) ) : esc_html__( 'Waiting', 'ridgeroad' );
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( $a, $post ) {
	if ( 'rr_shoutout' === $post->post_type && isset( $a['trash'] ) ) {
		$a['trash'] = str_replace( '>' . __( 'Trash' ) . '<', '>' . esc_html__( 'Block', 'ridgeroad' ) . '<', $a['trash'] );
		unset( $a['edit'], $a['inline hide-if-no-js'] );
	}
	return $a;
}, 10, 2 );
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( ! $s || 'edit-rr_shoutout' !== $s->id ) {
		return;
	}
	$seen = (int) get_option( 'ridgeroad_names_seen' );
	printf(
		'<div class="notice notice-info"><p>%s %s</p><p>%s</p></div>',
		( function_exists( 'marquee_get' ) ? esc_html__( 'Names go up in the show automatically if they pass the word filter. Click “Block” under any name to stop it.', 'ridgeroad' ) : esc_html__( 'Names go on the garage door and TV automatically if they pass the word filter. Click “Block” under any name to stop it.', 'ridgeroad' ) ),
		$seen ? esc_html( sprintf( __( 'FPP last checked in %s ago.', 'ridgeroad' ), human_time_diff( $seen ) ) ) : esc_html__( 'FPP has not checked in yet.', 'ridgeroad' ),
		esc_html__( 'Add words to block in Appearance → Customize → Show Website → Kids’ Corner.', 'ridgeroad' )
	);
} );
