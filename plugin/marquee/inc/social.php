<?php
/**
 * Social posts: when the show is canceled for tonight (Show Control or the Show Manager), Marquee
 * posts a ready-made announcement on the show's Facebook Page, and on Instagram if that's linked,
 * once per night. Optionally a "we're back on" post if the cancel is undone.
 *
 * Connecting uses the show's own Meta app (developers.facebook.com, free, no app review for your
 * own Page): paste the App ID, App secret and a token from the Graph API Explorer, and Marquee
 * swaps it for a Page token that doesn't expire. The App secret isn't kept.
 *
 * Stored in its own option (marquee_social), not autoloaded.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_social_api() {
	return 'https://graph.facebook.com/' . apply_filters( 'marquee_social_api_version', 'v25.0' ) . '/';
}

function marquee_social_defaults() {
	return array(
		'page_id'    => '',
		'page_name'  => '',
		'token'      => '',
		'ig_id'      => '',
		'ig_name'    => '',
		'th_id'      => '',      // Threads (same Meta app, its own token; renewed every week)
		'th_name'    => '',
		'th_token'   => '',
		'th_on'      => 1,
		'th_renewed' => 0,
		'th_bad'     => '',      // why the Threads token stopped working ('' = fine)
		'auto'       => 1,       // post when the show is canceled
		'ig_on'      => 1,       // also on Instagram (needs a picture)
		'cancel_msg' => '',      // '' = the ready-made one
		'cancel_img' => 0,
		'resume_on'  => 0,       // post when the cancel is undone
		'resume_msg' => '',
		'resume_img' => 0,
		'posted'     => array(), // night (Y-m-d) => array( 'cancel' => post ids, 'resume' => … )
		'last'       => array(), // the last post: what, t, lines (shown in Show Control / the Show Manager)
		'log'        => array(), // newest first: t, what, net, ok, msg, link
	);
}
function marquee_social_get() {
	return wp_parse_args( (array) get_option( 'marquee_social', array() ), marquee_social_defaults() );
}
function marquee_social_put( $s ) {
	update_option( 'marquee_social', $s, false );
}
function marquee_social_fb_ready( $s ) {
	return '' !== $s['page_id'] && '' !== $s['token'];
}
function marquee_social_th_ready( $s ) {
	return '' !== $s['th_id'] && '' !== $s['th_token'] && '' === $s['th_bad'];
}
/** Anything connected to post on? */
function marquee_social_ready( $s = null ) {
	$s = $s ? $s : marquee_social_get();
	return marquee_social_fb_ready( $s ) || marquee_social_th_ready( $s );
}
/** "Facebook, Instagram and Threads" — where a post goes. */
function marquee_social_where( $s ) {
	$n = array();
	if ( marquee_social_fb_ready( $s ) ) {
		$n[] = 'Facebook';
		if ( $s['ig_on'] && $s['ig_id'] ) {
			$n[] = 'Instagram';
		}
	}
	if ( marquee_social_th_ready( $s ) && $s['th_on'] ) {
		$n[] = 'Threads';
	}
	return count( $n ) > 1 ? implode( ', ', array_slice( $n, 0, -1 ) ) . ' ' . __( 'and', 'marquee' ) . ' ' . end( $n ) : implode( '', $n );
}

/** The ready-made messages, in the show's own words. */
function marquee_social_template( $what ) {
	if ( 'resume' === $what ) {
		return "🎄✨ WE'RE BACK ON TONIGHT! ✨🎄\nGood news: tonight's {show} Christmas light show is ON after all!\n📻 Tune your radio to {radio} and enjoy the show.\n📍 {city}\n🌐 {site}";
	}
	return "🎄🌧️ SHOW CANCELED TONIGHT 🌧️🎄\nUnfortunately, tonight's {show} Christmas light show has been canceled due to weather conditions.\n❤️ The safety of our visitors and neighbors always comes first.\nWe hope to see you tomorrow, weather permitting!\n📍 {city}\n🌐 {site}\n✨ {tagline}";
}

/** Fill in {show}, {city}, {site}, {radio}, {tagline}, {date}; lines left empty are dropped. */
function marquee_social_fill( $text ) {
	$m    = function_exists( 'marquee_get' ) ? marquee_get() : array();
	$city = trim( ( $m['city'] ?? '' ) . ( ! empty( $m['region'] ) ? ', ' . $m['region'] : '' ), ', ' );
	$rep  = array(
		'{show}'    => ! empty( $m['show_name'] ) ? $m['show_name'] : get_bloginfo( 'name' ),
		'{city}'    => $city,
		'{site}'    => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url() ) ),
		'{radio}'   => function_exists( 'ridgeroad_mod' ) ? (string) ridgeroad_mod( 'radio' ) : '',
		'{tagline}' => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
		'{date}'    => wp_date( 'l, F j' ),
	);
	$out = array();
	foreach ( explode( "\n", str_replace( "\r", '', $text ) ) as $line ) {
		preg_match_all( '/\{(show|city|site|radio|tagline|date)\}/', $line, $m );
		if ( $m[0] && '' === implode( '', array_map( function ( $k ) use ( $rep ) { return trim( $rep[ $k ] ); }, $m[0] ) ) ) {
			continue;   // "📍 {city}" with no city set: leave the line out
		}
		$out[] = strtr( $line, $rep );
	}
	return trim( implode( "\n", $out ) );
}
function marquee_social_message( $what, $s = null ) {
	$s   = $s ? $s : marquee_social_get();
	$raw = 'resume' === $what ? $s['resume_msg'] : $s['cancel_msg'];
	return marquee_social_fill( '' !== trim( (string) $raw ) ? $raw : marquee_social_template( $what ) );
}

/** The night a cancel belongs to (the date the show night started). */
function marquee_social_night() {
	$tz  = function_exists( 'ridgeroad_tz' ) ? ridgeroad_tz() : wp_timezone();
	$now = new DateTimeImmutable( 'now', $tz );
	return ( (int) $now->format( 'G' ) < 6 ? $now->modify( '-1 day' ) : $now )->format( 'Y-m-d' );
}

function marquee_social_log( &$s, $what, $net, $ok, $msg, $link = '' ) {
	if ( $ok && in_array( $what, array( 'cancel', 'resume' ), true ) && 'all' !== $net && function_exists( 'ridgeroad_stat_add' ) ) {
		ridgeroad_stat_add( 'social_post' );
	}
	array_unshift( $s['log'], array( 't' => time(), 'what' => $what, 'net' => $net, 'ok' => (bool) $ok, 'msg' => (string) $msg, 'link' => (string) $link ) );
	$s['log'] = array_slice( $s['log'], 0, 25 );
}

function marquee_social_call( $method, $path, $args ) {
	$url = marquee_social_api() . ltrim( $path, '/' );
	$r   = 'GET' === $method
		? wp_remote_get( add_query_arg( $args, $url ), array( 'timeout' => 20 ) )
		: wp_remote_post( $url, array( 'timeout' => 30, 'body' => $args ) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'net', __( 'Couldn’t reach Facebook: ', 'marquee' ) . $r->get_error_message() );
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $j ) || isset( $j['error'] ) ) {
		$e   = is_array( $j ) && isset( $j['error'] ) ? $j['error'] : array();
		$msg = (string) ( $e['message'] ?? __( 'Unexpected answer from Facebook.', 'marquee' ) );
		if ( 190 === (int) ( $e['code'] ?? 0 ) ) {
			return new WP_Error( 'token', __( 'Facebook no longer accepts the saved connection (the password was changed or the app was removed). Connect your Page again under Social posts.', 'marquee' ), $msg );
		}
		return new WP_Error( 'fb', $msg );
	}
	return $j;
}

/** Threads has its own address and its own tokens. */
function marquee_social_th_call( $method, $path, $args ) {
	$url = ( 0 === strpos( $path, 'oauth' ) || in_array( $path, array( 'access_token', 'refresh_access_token' ), true ) ? 'https://graph.threads.net/' : 'https://graph.threads.net/v1.0/' ) . ltrim( $path, '/' );
	$r   = 'GET' === $method
		? wp_remote_get( add_query_arg( $args, $url ), array( 'timeout' => 20 ) )
		: wp_remote_post( $url, array( 'timeout' => 30, 'body' => $args ) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'net', __( 'Couldn’t reach Threads: ', 'marquee' ) . $r->get_error_message() );
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $j ) || isset( $j['error'] ) ) {
		$e = is_array( $j ) && isset( $j['error'] ) ? $j['error'] : array();
		if ( 190 === (int) ( $e['code'] ?? 0 ) ) {
			return new WP_Error( 'token', __( 'Threads no longer accepts the saved connection. Connect Threads again under Social posts.', 'marquee' ), (string) ( $e['message'] ?? '' ) );
		}
		return new WP_Error( 'th', (string) ( $e['message'] ?? __( 'Unexpected answer from Threads.', 'marquee' ) ) );
	}
	return $j;
}

/** Threads posts are 500 bytes at most (emoji count as several). */
function marquee_social_cut( $text, $max = 500 ) {
	if ( strlen( $text ) <= $max ) {
		return $text;
	}
	while ( strlen( $text ) > $max - 3 ) {
		$text = mb_substr( $text, 0, -1 );
	}
	return rtrim( $text ) . '…';
}

/* Keep the Threads connection alive: its token lasts 60 days, renewed here every week. */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'marquee_social_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'marquee_social_daily' );
	}
} );
add_action( 'marquee_social_daily', 'marquee_social_th_renew' );
function marquee_social_th_renew( $force = false ) {
	$s = marquee_social_get();
	if ( ! $s['th_token'] || ( ! $force && time() - (int) $s['th_renewed'] < 7 * DAY_IN_SECONDS ) ) {
		return;
	}
	$r = marquee_social_th_call( 'GET', 'refresh_access_token', array( 'grant_type' => 'th_refresh_token', 'access_token' => $s['th_token'] ) );
	if ( is_wp_error( $r ) ) {
		if ( 'token' === $r->get_error_code() ) {
			$s['th_bad'] = $r->get_error_message();
			marquee_social_log( $s, 'connect', 'threads', false, $r->get_error_message() );
		}
	} elseif ( ! empty( $r['access_token'] ) ) {
		$s['th_token']   = (string) $r['access_token'];
		$s['th_renewed'] = time();
		$s['th_bad']     = '';
	}
	marquee_social_put( $s );
}

/** A JPEG copy of a picture for Instagram (it only takes JPEG), saved once in uploads. */
function marquee_social_jpeg( $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return '';
	}
	if ( 'image/jpeg' === get_post_mime_type( $id ) ) {
		return wp_get_attachment_url( $id );
	}
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'marquee-social/';
	$name = 'img-' . (int) $id . '-' . filemtime( $file ) . '.jpg';
	if ( ! file_exists( $dir . $name ) ) {
		wp_mkdir_p( $dir );
		$ed = wp_get_image_editor( $file );
		if ( is_wp_error( $ed ) ) {
			return '';
		}
		$ed->resize( 1440, 1440, false );
		$saved = $ed->save( $dir . $name, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return '';
		}
	}
	return trailingslashit( $up['baseurl'] ) . 'marquee-social/' . $name;
}

/** Post one announcement. $what: 'cancel' | 'resume'. Returns the log entries it added. */
function marquee_social_post( $what, $force = false ) {
	$s = marquee_social_get();
	if ( ! marquee_social_ready( $s ) ) {
		return array();
	}
	$night = marquee_social_night();
	$done  = isset( $s['posted'][ $night ] ) ? (array) $s['posted'][ $night ] : array();
	if ( ! $force && ! empty( $done[ $what ] ) ) {
		marquee_social_log( $s, $what, 'all', true, __( 'Already posted for tonight, so it wasn’t posted again.', 'marquee' ) );
		marquee_social_put( $s );
		return array_slice( $s['log'], 0, 1 );
	}
	$msg  = marquee_social_message( $what, $s );
	$img  = (int) ( 'resume' === $what ? $s['resume_img'] : $s['cancel_img'] );
	$imgu = $img ? wp_get_attachment_url( $img ) : '';
	$ids  = array();
	$n    = count( $s['log'] );

	// Facebook: a photo post with the message, or a text post with a link to the site.
	$r = ! marquee_social_fb_ready( $s ) ? null : ( $imgu
		? marquee_social_call( 'POST', $s['page_id'] . '/photos', array( 'url' => $imgu, 'caption' => $msg, 'access_token' => $s['token'] ) )
		: marquee_social_call( 'POST', $s['page_id'] . '/feed', array( 'message' => $msg, 'link' => home_url( '/' ), 'access_token' => $s['token'] ) ) );
	if ( null === $r ) {
		$r = null;      // Facebook isn't connected (Threads only)
	} elseif ( is_wp_error( $r ) ) {
		marquee_social_log( $s, $what, 'facebook', false, $r->get_error_message() );
	} else {
		$pid         = (string) ( $r['post_id'] ?? $r['id'] ?? '' );
		$ids['fb']   = $pid;
		marquee_social_log( $s, $what, 'facebook', true, __( 'Posted on your Facebook Page.', 'marquee' ), $pid ? 'https://www.facebook.com/' . rawurlencode( $pid ) : '' );
	}

	// Instagram: needs a picture (JPEG, on a public address).
	if ( marquee_social_fb_ready( $s ) && $s['ig_on'] && $s['ig_id'] ) {
		$jpg = $img ? marquee_social_jpeg( $img ) : '';
		if ( ! $jpg ) {
			marquee_social_log( $s, $what, 'instagram', false, __( 'Instagram posts need a picture: pick one under Social posts.', 'marquee' ) );
		} else {
			$c = marquee_social_call( 'POST', $s['ig_id'] . '/media', array( 'image_url' => $jpg, 'caption' => $msg, 'access_token' => $s['token'] ) );
			if ( ! is_wp_error( $c ) && ! empty( $c['id'] ) ) {
				for ( $i = 0; $i < 6; $i++ ) {               // wait until Instagram has the picture
					$st = marquee_social_call( 'GET', $c['id'], array( 'fields' => 'status_code', 'access_token' => $s['token'] ) );
					if ( is_wp_error( $st ) || in_array( $st['status_code'] ?? '', array( 'FINISHED', 'ERROR', 'EXPIRED' ), true ) ) {
						break;
					}
					sleep( 2 );
				}
				$c = marquee_social_call( 'POST', $s['ig_id'] . '/media_publish', array( 'creation_id' => $c['id'], 'access_token' => $s['token'] ) );
			}
			if ( is_wp_error( $c ) ) {
				marquee_social_log( $s, $what, 'instagram', false, $c->get_error_message() );
			} else {
				$ids['ig'] = (string) ( $c['id'] ?? '' );
				marquee_social_log( $s, $what, 'instagram', true, __( 'Posted on Instagram.', 'marquee' ), $s['ig_name'] ? 'https://www.instagram.com/' . rawurlencode( $s['ig_name'] ) . '/' : '' );
			}
		}
	}
	// Threads: text (500 bytes) with the picture if there is one.
	if ( marquee_social_th_ready( $s ) && $s['th_on'] ) {
		$tx   = marquee_social_cut( $msg );
		$pic  = $img ? marquee_social_jpeg( $img ) : '';
		$args = $pic ? array( 'media_type' => 'IMAGE', 'image_url' => $pic, 'text' => $tx, 'access_token' => $s['th_token'] ) : array( 'media_type' => 'TEXT', 'text' => $tx, 'access_token' => $s['th_token'] );
		$c    = marquee_social_th_call( 'POST', $s['th_id'] . '/threads', $args );
		if ( ! is_wp_error( $c ) && ! empty( $c['id'] ) ) {
			for ( $i = 0; $pic && $i < 10; $i++ ) {          // pictures take a moment
				$st = marquee_social_th_call( 'GET', $c['id'], array( 'fields' => 'status', 'access_token' => $s['th_token'] ) );
				if ( is_wp_error( $st ) || in_array( $st['status'] ?? '', array( 'FINISHED', 'ERROR', 'EXPIRED' ), true ) ) {
					break;
				}
				sleep( 3 );
			}
			$c = marquee_social_th_call( 'POST', $s['th_id'] . '/threads_publish', array( 'creation_id' => $c['id'], 'access_token' => $s['th_token'] ) );
		}
		if ( is_wp_error( $c ) ) {
			if ( 'token' === $c->get_error_code() ) {
				$s['th_bad'] = $c->get_error_message();
			}
			marquee_social_log( $s, $what, 'threads', false, $c->get_error_message() );
		} else {
			$ids['th'] = (string) ( $c['id'] ?? '' );
			marquee_social_log( $s, $what, 'threads', true, __( 'Posted on Threads.', 'marquee' ), $s['th_name'] ? 'https://www.threads.net/@' . rawurlencode( $s['th_name'] ) : '' );
		}
	}
	$added       = array_slice( $s['log'], 0, count( $s['log'] ) - $n );
	$s['last']   = array( 'what' => $what, 't' => time(), 'night' => $night, 'lines' => $added );
	if ( $ids ) {
		$done[ $what ]        = $ids;
		$s['posted'][ $night ] = $done;
		$s['posted']           = array_slice( $s['posted'], -20, null, true );
	}
	marquee_social_put( $s );
	return $added;
}

/* ------------------------------------------------- in the background ---- */
/* The cancel button shouldn't wait for Facebook and Instagram: the post runs in a second request
   to this site, and Show Control / the Show Manager show the result when it's done. If that
   request can't start (some hosts block it), the next status check runs it instead. */
function marquee_social_queue( $what ) {
	$job = array( 'what' => $what, 't' => time(), 'key' => wp_generate_password( 20, false ) );
	update_option( 'marquee_social_job', $job, false );
	wp_remote_post( admin_url( 'admin-ajax.php' ), array(
		'timeout'   => 0.5,
		'blocking'  => false,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'body'      => array( 'action' => 'marquee_social_run', 'key' => $job['key'] ),
	) );
}
function marquee_social_run_job( $key = null ) {
	$job = get_option( 'marquee_social_job' );
	if ( ! is_array( $job ) || ( null !== $key && ! hash_equals( (string) $job['key'], (string) $key ) ) ) {
		return false;
	}
	delete_option( 'marquee_social_job' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore
	}
	ignore_user_abort( true );
	marquee_social_post( $job['what'] );
	return true;
}
$marquee_social_run = function () {
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked against the saved job key
	wp_send_json( array( 'ran' => marquee_social_run_job( $key ) ) );
};
add_action( 'wp_ajax_marquee_social_run', $marquee_social_run );
add_action( 'wp_ajax_nopriv_marquee_social_run', $marquee_social_run );

/* The show was canceled / is back on (Show Control and the Show Manager). */
add_action( 'ridgeroad_show_canceled', function ( $until, $want = null ) {
	$s = marquee_social_get();
	if ( marquee_social_ready( $s ) && ( null === $want ? ! empty( $s['auto'] ) : $want ) ) {
		marquee_social_queue( 'cancel' );
	}
}, 10, 2 );
add_action( 'ridgeroad_show_uncanceled', function () {
	$s     = marquee_social_get();
	$night = marquee_social_night();
	if ( marquee_social_ready( $s ) && ! empty( $s['resume_on'] ) && ! empty( $s['posted'][ $night ]['cancel'] ) ) {
		marquee_social_queue( 'resume' );    // only when tonight's cancel was posted
	}
} );

/* The cancel confirm in Show Control / the Show Manager shows "Also post on Facebook". */
add_filter( 'ridgeroad_social_state', function () {
	$job = get_option( 'marquee_social_job' );
	if ( is_array( $job ) && time() - (int) $job['t'] > 45 ) {
		marquee_social_run_job();                 // the background request never started: post now
	}
	$s    = marquee_social_get();
	$out  = array( 'ready' => marquee_social_ready( $s ), 'setup' => admin_url( 'admin.php?page=marquee#mq-step-7' ) );
	$last = $s['last'];
	if ( is_array( $job ) ) {
		$out['posting'] = true;
	}
	if ( ! empty( $last['night'] ) && marquee_social_night() === $last['night'] ) {
		$names       = array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'threads' => 'Threads', 'all' => '' );
		$out['last'] = array(
			'what'  => $last['what'],
			'ago'   => human_time_diff( (int) $last['t'] ),
			'lines' => array_map( function ( $l ) use ( $names ) {
				return array( 'ok' => (bool) $l['ok'], 'net' => $names[ $l['net'] ] ?? $l['net'], 'msg' => $l['msg'], 'link' => $l['link'] );
			}, (array) $last['lines'] ),
		);
	}
	$out['text'] = marquee_social_message( 'cancel', $s );   // for "copy it for other sites"
	if ( ! $out['ready'] ) {
		$out['hint'] = __( 'Marquee can also post this on Facebook, Instagram and Threads for you.', 'marquee' );
		return $out;
	}
	$out['auto']  = (bool) $s['auto'];
	$out['nets']  = array_values( array_filter( array(
		marquee_social_fb_ready( $s ) ? 'Facebook' : '',
		marquee_social_fb_ready( $s ) && $s['ig_id'] && $s['ig_on'] ? 'Instagram' : '',
		marquee_social_th_ready( $s ) && $s['th_on'] ? 'Threads' : '',
	) ) );
	$out['history'] = array_map( function ( $l ) {
		return array( 'ok' => (bool) $l['ok'], 'what' => $l['what'], 'net' => $l['net'], 'msg' => $l['msg'], 'link' => $l['link'], 'when' => wp_date( 'M j, g:i A', (int) $l['t'] ) );
	}, array_slice( array_values( array_filter( (array) $s['log'], function ( $l ) { return 'connect' !== $l['what']; } ) ), 0, 5 ) );
	/* translators: %s: Facebook, Instagram and Threads */
	$out['label'] = sprintf( __( 'Also post “Show canceled tonight” on %s', 'marquee' ), marquee_social_where( $s ) );
	return $out;
} );

/* ---------------------------------------------------------------- admin ---- */

/* Connect: App ID + App secret + a short-lived token from the Graph API Explorer → Pages you run. */
add_action( 'wp_ajax_marquee_social_connect', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$app = isset( $_POST['app'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['app'] ) ) : '';
	$sec = isset( $_POST['secret'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_POST['secret'] ) ) : '';
	$tok = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_\-|]/', '', wp_unslash( $_POST['token'] ) ) : '';
	if ( ! $app || ! $sec || ! $tok ) {
		wp_send_json_error( array( 'message' => __( 'Fill in all three: App ID, App secret and the token.', 'marquee' ) ) );
	}
	$long = marquee_social_call( 'GET', 'oauth/access_token', array( 'grant_type' => 'fb_exchange_token', 'client_id' => $app, 'client_secret' => $sec, 'fb_exchange_token' => $tok ) );
	if ( is_wp_error( $long ) ) {
		$why = 'token' === $long->get_error_code() ? (string) $long->get_error_data() : $long->get_error_message();
		/* translators: %s: Facebook's message */
		wp_send_json_error( array( 'message' => sprintf( __( 'Facebook didn’t accept that: %s. Check the App ID and secret, and make a new token in the Graph API Explorer (they only last about an hour).', 'marquee' ), rtrim( $why, '.' ) ) ) );
	}
	$pages = marquee_social_call( 'GET', 'me/accounts', array( 'fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 50, 'access_token' => $long['access_token'] ) );
	if ( is_wp_error( $pages ) ) {
		wp_send_json_error( array( 'message' => $pages->get_error_message() ) );
	}
	$list = array();
	foreach ( (array) ( $pages['data'] ?? array() ) as $p ) {
		if ( empty( $p['id'] ) || empty( $p['access_token'] ) ) {
			continue;
		}
		$list[] = array(
			'id'      => (string) $p['id'],
			'name'    => (string) ( $p['name'] ?? '' ),
			'token'   => (string) $p['access_token'],
			'ig_id'   => (string) ( $p['instagram_business_account']['id'] ?? '' ),
			'ig_name' => (string) ( $p['instagram_business_account']['username'] ?? '' ),
		);
	}
	if ( ! $list ) {
		wp_send_json_error( array( 'message' => __( 'Facebook didn’t list any Pages. In the Graph API Explorer, make sure you picked your Page when it asked, and added pages_show_list and pages_manage_posts.', 'marquee' ) ) );
	}
	set_transient( 'marquee_social_pages_' . get_current_user_id(), $list, 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'pages' => array_map( function ( $p ) {
		return array( 'id' => $p['id'], 'name' => $p['name'], 'ig' => $p['ig_name'] );
	}, $list ) ) );
} );

/* Pick the Page (from the list above). */
add_action( 'wp_ajax_marquee_social_pick', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$id   = isset( $_POST['page'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['page'] ) ) : '';
	$list = (array) get_transient( 'marquee_social_pages_' . get_current_user_id() );
	foreach ( $list as $p ) {
		if ( is_array( $p ) && $p['id'] === $id ) {
			$s              = marquee_social_get();
			$s['page_id']   = $p['id'];
			$s['page_name'] = $p['name'];
			$s['token']     = $p['token'];
			$s['ig_id']     = $p['ig_id'];
			$s['ig_name']   = $p['ig_name'];
			marquee_social_log( $s, 'connect', 'facebook', true, sprintf( /* translators: page */ __( 'Connected to %s.', 'marquee' ), $p['name'] ) . ( $p['ig_name'] ? ' ' . sprintf( /* translators: account */ __( 'Instagram: @%s.', 'marquee' ), $p['ig_name'] ) : '' ) );
			marquee_social_put( $s );
			delete_transient( 'marquee_social_pages_' . get_current_user_id() );
			wp_send_json_success( array( 'name' => $p['name'], 'ig' => $p['ig_name'] ) );
		}
	}
	wp_send_json_error( array( 'message' => __( 'That Page isn’t in the list anymore. Connect again.', 'marquee' ) ) );
} );

/* Connect Threads: a token from the app's Threads "User Token Generator" (and the Threads app
   secret, to swap a short token for a 60-day one). */
add_action( 'wp_ajax_marquee_social_threads', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$tok = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_\-|]/', '', wp_unslash( $_POST['token'] ) ) : '';
	$sec = isset( $_POST['secret'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_POST['secret'] ) ) : '';
	if ( ! $tok ) {
		wp_send_json_error( array( 'message' => __( 'Paste the Threads token first.', 'marquee' ) ) );
	}
	if ( $sec ) {     // a short (1 hour) token becomes a 60-day one; an already long one stays as it is
		$long = marquee_social_th_call( 'GET', 'access_token', array( 'grant_type' => 'th_exchange_token', 'client_secret' => $sec, 'access_token' => $tok ) );
		if ( ! is_wp_error( $long ) && ! empty( $long['access_token'] ) ) {
			$tok = (string) $long['access_token'];
		}
	}
	$me = marquee_social_th_call( 'GET', 'me', array( 'fields' => 'id,username', 'access_token' => $tok ) );
	if ( is_wp_error( $me ) || empty( $me['id'] ) ) {
		$why = is_wp_error( $me ) ? ( 'token' === $me->get_error_code() ? (string) $me->get_error_data() : $me->get_error_message() ) : '';
		/* translators: %s: Threads' message */
		wp_send_json_error( array( 'message' => sprintf( __( 'Threads didn’t accept that token%s. Make a new one with the User Token Generator (step 2) and paste it right away.', 'marquee' ), $why ? ': ' . rtrim( $why, '.' ) : '' ) ) );
	}
	$s               = marquee_social_get();
	$s['th_id']      = (string) $me['id'];
	$s['th_name']    = (string) ( $me['username'] ?? '' );
	$s['th_token']   = $tok;
	$s['th_renewed'] = time();
	$s['th_bad']     = '';
	marquee_social_log( $s, 'connect', 'threads', true, sprintf( /* translators: account */ __( 'Connected to Threads: @%s.', 'marquee' ), $s['th_name'] ) );
	marquee_social_put( $s );
	wp_send_json_success( array( 'name' => $s['th_name'] ) );
} );

/* Check the connection (reads the Page; posts nothing). */
add_action( 'wp_ajax_marquee_social_check', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$s   = marquee_social_get();
	$out = array();
	if ( marquee_social_fb_ready( $s ) ) {
		$r = marquee_social_call( 'GET', $s['page_id'], array( 'fields' => 'name,instagram_business_account{username}', 'access_token' => $s['token'] ) );
		if ( is_wp_error( $r ) ) {
			$out[] = '✗ Facebook: ' . $r->get_error_message();
		} else {
			$ig    = (string) ( $r['instagram_business_account']['username'] ?? '' );
			$out[] = '✓ ' . sprintf( /* translators: page */ __( 'Facebook: %s.', 'marquee' ), $r['name'] ?? $s['page_name'] ) . ' ' . ( $ig ? sprintf( /* translators: account */ __( 'Instagram: @%s.', 'marquee' ), $ig ) : __( 'No Instagram account is linked to this Page.', 'marquee' ) );
		}
	}
	if ( $s['th_token'] ) {
		$me    = marquee_social_th_call( 'GET', 'me', array( 'fields' => 'username', 'access_token' => $s['th_token'] ) );
		$out[] = is_wp_error( $me ) ? '✗ Threads: ' . $me->get_error_message() : '✓ ' . sprintf( /* translators: account */ __( 'Threads: @%s.', 'marquee' ), $me['username'] ?? $s['th_name'] );
	}
	if ( ! $out ) {
		wp_send_json_error( array( 'message' => __( 'Not connected yet.', 'marquee' ) ) );
	}
	wp_send_json_success( array( 'message' => implode( '  ', $out ) ) );
} );

/* Post it now (the "Post it now" buttons). */
add_action( 'wp_ajax_marquee_social_now', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$what = isset( $_POST['what'] ) && 'resume' === $_POST['what'] ? 'resume' : 'cancel';
	$out  = marquee_social_post( $what, true );
	wp_send_json_success( array( 'log' => $out ) );
} );

/* Save the post settings (part of the Settings form). */
add_filter( 'marquee_settings_saved', function ( $new, $in ) {
	if ( ! isset( $in['social_sent'] ) ) {
		return $new;
	}
	$s = marquee_social_get();
	foreach ( array( 'auto', 'ig_on', 'th_on', 'resume_on' ) as $k ) {
		$s[ $k ] = empty( $in[ 'social_' . $k ] ) ? 0 : 1;
	}
	foreach ( array( 'cancel_msg', 'resume_msg' ) as $k ) {
		$v       = isset( $in[ 'social_' . $k ] ) ? sanitize_textarea_field( wp_unslash( $in[ 'social_' . $k ] ) ) : '';
		$s[ $k ] = trim( $v ) === trim( marquee_social_template( 'cancel_msg' === $k ? 'cancel' : 'resume' ) ) ? '' : mb_substr( $v, 0, 2000 );
	}
	foreach ( array( 'cancel_img', 'resume_img' ) as $k ) {
		$id      = absint( $in[ 'social_' . $k ] ?? 0 );
		$s[ $k ] = $id && wp_attachment_is_image( $id ) ? $id : 0;
	}
	if ( ! empty( $in['social_disconnect'] ) ) {
		foreach ( array( 'page_id', 'page_name', 'token', 'ig_id', 'ig_name' ) as $k ) {
			$s[ $k ] = '';
		}
		marquee_social_log( $s, 'connect', 'facebook', true, __( 'Disconnected.', 'marquee' ) );
	}
	if ( ! empty( $in['social_th_disconnect'] ) ) {
		foreach ( array( 'th_id', 'th_name', 'th_token', 'th_bad' ) as $k ) {
			$s[ $k ] = '';
		}
		marquee_social_log( $s, 'connect', 'threads', true, __( 'Disconnected.', 'marquee' ) );
	}
	marquee_social_put( $s );
	return $new;
}, 10, 2 );

/** Settings → Social posts. */
function marquee_social_box() {
	$s     = marquee_social_get();
	$ready = marquee_social_ready( $s );
	$m     = function_exists( 'marquee_get' ) ? marquee_get() : array();
	$fill  = array(
		'{show}'    => ! empty( $m['show_name'] ) ? $m['show_name'] : get_bloginfo( 'name' ),
		'{city}'    => trim( ( $m['city'] ?? '' ) . ( ! empty( $m['region'] ) ? ', ' . $m['region'] : '' ), ', ' ),
		'{site}'    => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url() ) ),
		'{radio}'   => function_exists( 'ridgeroad_mod' ) ? (string) ridgeroad_mod( 'radio' ) : '',
		'{tagline}' => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
		'{date}'    => wp_date( 'l, F j' ),
	);
	?>
	<input type="hidden" name="social_sent" value="1">
	<div class="mq-so" data-mq-social data-fill="<?php echo esc_attr( wp_json_encode( $fill ) ); ?>">
		<div class="mq-how">
			<div class="mq-flow mq-flow--so" aria-hidden="true">
				<span>🌧️ <?php esc_html_e( 'You cancel tonight', 'marquee' ); ?><small><?php esc_html_e( 'Show Control or your phone', 'marquee' ); ?></small></span><i>→</i>
				<span><?php esc_html_e( 'Website says', 'marquee' ); ?><small><?php esc_html_e( '“No show tonight”', 'marquee' ); ?></small></span><i>→</i>
				<span><?php esc_html_e( 'Phones get an alert', 'marquee' ); ?><small><?php esc_html_e( 'if you tick it', 'marquee' ); ?></small></span><i>→</i>
				<span class="mq-flow__break"><?php esc_html_e( 'Facebook, Instagram, Threads', 'marquee' ); ?><small><?php esc_html_e( 'your post goes up', 'marquee' ); ?></small></span>
			</div>
		</div>

		<details class="mq__card" <?php echo $ready ? '' : 'open'; ?>>
			<summary>
				<span class="mq__card-n">1</span>
				<span class="mq__card-t"><b><?php esc_html_e( 'Connect your Facebook Page', 'marquee' ); ?></b><small><?php esc_html_e( 'And the Instagram account linked to it, if you have one', 'marquee' ); ?></small></span>
				<?php $ready = marquee_social_fb_ready( $s ); ?>
				<?php if ( $ready ) : ?><span class="mq__pill is-ok"><?php echo esc_html( $s['page_name'] . ( $s['ig_name'] ? ' + @' . $s['ig_name'] : '' ) ); ?></span><?php else : ?><span class="mq__pill"><?php esc_html_e( 'Not connected', 'marquee' ); ?></span><?php endif; ?>
			</summary>
			<div class="mq__card-b">
				<?php if ( $ready ) : ?>
					<p><?php echo esc_html( sprintf( /* translators: page */ __( 'Connected to %s.', 'marquee' ), $s['page_name'] ) ); ?> <?php echo $s['ig_name'] ? esc_html( sprintf( /* translators: account */ __( 'Instagram: @%s.', 'marquee' ), $s['ig_name'] ) ) : esc_html__( 'No Instagram account is linked to this Page (in Instagram: Settings → Account type → Business or Creator, then link it to your Facebook Page, and connect again).', 'marquee' ); ?></p>
					<p><button type="button" class="button" data-so-check><?php esc_html_e( 'Check the connection', 'marquee' ); ?></button> <span class="mq__msg" data-so-checkmsg aria-live="polite"></span></p>
					<p><label class="mq__chk"><input type="checkbox" name="social_disconnect" value="1"> <?php esc_html_e( 'Disconnect (when you Save)', 'marquee' ); ?></label></p>
					<details class="mq__faq"><summary><?php esc_html_e( 'Connect a different Page', 'marquee' ); ?></summary>
				<?php endif; ?>
				<ol class="mq-sm__steps mq-so__steps">
					<li><b><?php esc_html_e( 'Make a free Meta app (one time, about 5 minutes)', 'marquee' ); ?></b><span><?php esc_html_e( 'Go to developers.facebook.com, log in with the Facebook account that runs your Page, and choose My Apps → Create app. Name it after your show (only you see it). If it asks what it’s for, pick managing your Page / “Other” → Business. No app review is needed: it only posts on your own Page.', 'marquee' ); ?></span><a class="button-link" href="https://developers.facebook.com/apps/" target="_blank" rel="noopener"><?php esc_html_e( 'Open Meta for Developers', 'marquee' ); ?></a></li>
					<li><b><?php esc_html_e( 'Copy the App ID and App secret', 'marquee' ); ?></b><span><?php esc_html_e( 'In your app: App settings → Basic. Click Show next to App secret.', 'marquee' ); ?></span>
						<span class="mq__row"><input type="text" inputmode="numeric" autocomplete="off" placeholder="<?php esc_attr_e( 'App ID', 'marquee' ); ?>" data-so-app> <input type="password" autocomplete="new-password" placeholder="<?php esc_attr_e( 'App secret', 'marquee' ); ?>" data-so-secret></span></li>
					<li><b><?php esc_html_e( 'Get a token from the Graph API Explorer', 'marquee' ); ?></b><span><?php esc_html_e( 'Tools → Graph API Explorer. Pick your app at the top right, then add these permissions: pages_show_list, pages_read_engagement, pages_manage_posts (for Instagram also: instagram_basic, instagram_content_publish, business_management). Click Generate Access Token, choose your Page (and Instagram account) when it asks, and copy the token.', 'marquee' ); ?></span><a class="button-link" href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener"><?php esc_html_e( 'Open the Graph API Explorer', 'marquee' ); ?></a>
						<input type="password" autocomplete="off" placeholder="<?php esc_attr_e( 'Paste the token (starts with EAA…)', 'marquee' ); ?>" data-so-token></li>
					<li><b><?php esc_html_e( 'Connect and pick your Page', 'marquee' ); ?></b><span><?php esc_html_e( 'Marquee swaps that token for a Page connection that doesn’t expire. Your App secret is only used for that and isn’t saved.', 'marquee' ); ?></span>
						<button type="button" class="button button-primary" data-so-connect><?php esc_html_e( 'Connect', 'marquee' ); ?></button> <span class="mq__msg" data-so-msg aria-live="polite"></span>
						<span class="mq-so__pages" data-so-pages hidden></span></li>
				</ol>
				<?php if ( $ready ) : ?></details><?php endif; ?>
			</div>
		</details>

		<?php $th = '' !== $s['th_token']; ?>
		<details class="mq__card" <?php echo ( $th && $s['th_bad'] ) ? 'open' : ''; ?>>
			<summary>
				<span class="mq__card-n">2</span>
				<span class="mq__card-t"><b><?php esc_html_e( 'Connect Threads', 'marquee' ); ?></b><small><?php esc_html_e( 'Optional. Uses the same Meta app as Facebook', 'marquee' ); ?></small></span>
				<?php if ( $th && ! $s['th_bad'] ) : ?><span class="mq__pill is-ok">@<?php echo esc_html( $s['th_name'] ); ?></span><?php elseif ( $th ) : ?><span class="mq__pill is-warn"><?php esc_html_e( 'Connect again', 'marquee' ); ?></span><?php else : ?><span class="mq__pill"><?php esc_html_e( 'Not connected', 'marquee' ); ?></span><?php endif; ?>
			</summary>
			<div class="mq__card-b">
				<?php if ( $th ) : ?>
					<p><?php echo $s['th_bad'] ? esc_html( $s['th_bad'] ) : esc_html( sprintf( /* translators: account */ __( 'Connected to Threads: @%s. Marquee renews the connection every week by itself (Threads connections last 60 days).', 'marquee' ), $s['th_name'] ) ); ?></p>
					<p><label class="mq__chk"><input type="checkbox" name="social_th_disconnect" value="1"> <?php esc_html_e( 'Disconnect Threads (when you Save)', 'marquee' ); ?></label></p>
				<?php endif; ?>
				<ol class="mq-sm__steps mq-so__steps">
					<li><b><?php esc_html_e( 'Add Threads to your Meta app', 'marquee' ); ?></b><span><?php esc_html_e( 'In the same app as Facebook (developers.facebook.com → My Apps → your app): Use cases → Add use case → “Access the Threads API”. Under its Permissions, turn on threads_basic and threads_content_publish.', 'marquee' ); ?></span><a class="button-link" href="https://developers.facebook.com/apps/" target="_blank" rel="noopener"><?php esc_html_e( 'Open Meta for Developers', 'marquee' ); ?></a></li>
					<li><b><?php esc_html_e( 'Let the app post as you', 'marquee' ); ?></b><span><?php esc_html_e( 'In the Threads use case settings, add your Threads username as a Threads tester. Then in the Threads app: Settings → Account → Website permissions → Invites, and accept. Back in the settings, the User Token Generator now shows your account: click Generate and copy the token. The Threads app secret is on the same page.', 'marquee' ); ?></span>
						<span class="mq__row"><input type="password" autocomplete="off" placeholder="<?php esc_attr_e( 'Threads token', 'marquee' ); ?>" data-so-thtoken> <input type="password" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Threads app secret (optional)', 'marquee' ); ?>" data-so-thsecret></span></li>
					<li><b><?php esc_html_e( 'Connect', 'marquee' ); ?></b><span><?php esc_html_e( 'With the secret, a short token is swapped for a 60-day one; Marquee keeps renewing it. The secret isn’t saved.', 'marquee' ); ?></span>
						<button type="button" class="button button-primary" data-so-thconnect><?php esc_html_e( 'Connect Threads', 'marquee' ); ?></button> <span class="mq__msg" data-so-thmsg aria-live="polite"></span></li>
				</ol>
			</div>
		</details>

		<?php $ready = marquee_social_ready( $s ); ?>
		<?php foreach ( array( 'cancel' => array( '3', __( 'When you cancel the show', 'marquee' ), __( 'Posted once a night, the moment you cancel', 'marquee' ) ), 'resume' => array( '4', __( 'When the show is back on', 'marquee' ), __( 'Optional: if you undo the cancel the same night', 'marquee' ) ) ) as $what => $c ) : ?>
			<?php $on = 'cancel' === $what ? $s['auto'] : $s['resume_on']; ?>
			<details class="mq__card" <?php echo $ready && 'cancel' === $what ? 'open' : ''; ?>>
				<summary>
					<span class="mq__card-n"><?php echo esc_html( $c[0] ); ?></span>
					<span class="mq__card-t"><b><?php echo esc_html( $c[1] ); ?></b><small><?php echo esc_html( $c[2] ); ?></small></span>
					<span class="mq__pill<?php echo $on ? ' is-ok' : ''; ?>"><?php echo $on ? esc_html__( 'On', 'marquee' ) : esc_html__( 'Off', 'marquee' ); ?></span>
				</summary>
				<div class="mq__card-b">
					<?php if ( 'cancel' === $what ) : ?>
						<label class="mq__feat"><span><b><?php esc_html_e( 'Post automatically when I cancel', 'marquee' ); ?></b><small><?php esc_html_e( 'The cancel button also shows a box to skip it for one night.', 'marquee' ); ?></small></span><input type="checkbox" name="social_auto" value="1" <?php checked( (bool) $s['auto'] ); ?>></label>
						<label class="mq__feat"><span><b><?php esc_html_e( 'Also post on Instagram', 'marquee' ); ?></b><small><?php esc_html_e( 'Instagram needs a picture with every post.', 'marquee' ); ?></small></span><input type="checkbox" name="social_ig_on" value="1" <?php checked( (bool) $s['ig_on'] ); ?>></label>
						<label class="mq__feat"><span><b><?php esc_html_e( 'Also post on Threads', 'marquee' ); ?></b><small><?php esc_html_e( 'Up to 500 characters (a longer post is shortened), with your picture if there is one.', 'marquee' ); ?></small></span><input type="checkbox" name="social_th_on" value="1" <?php checked( (bool) $s['th_on'] ); ?>></label>
					<?php else : ?>
						<label class="mq__feat"><span><b><?php esc_html_e( 'Post “We’re back on” when I undo a cancel', 'marquee' ); ?></b><small><?php esc_html_e( 'Only if tonight’s cancel was posted, so followers aren’t left confused.', 'marquee' ); ?></small></span><input type="checkbox" name="social_resume_on" value="1" <?php checked( (bool) $s['resume_on'] ); ?>></label>
					<?php endif; ?>
					<div class="mq-so__compose">
						<p><label for="mq-so-<?php echo esc_attr( $what ); ?>"><?php esc_html_e( 'The post', 'marquee' ); ?></label>
							<textarea id="mq-so-<?php echo esc_attr( $what ); ?>" name="social_<?php echo esc_attr( $what ); ?>_msg" rows="8" data-so-text><?php echo esc_textarea( '' !== trim( (string) $s[ $what . '_msg' ] ) ? $s[ $what . '_msg' ] : marquee_social_template( $what ) ); ?></textarea>
							<small><?php esc_html_e( 'These fill in for you: {show} {city} {site} {radio} {tagline} {date}. A line whose fill-in is empty (no city set, say) is left out.', 'marquee' ); ?></small></p>
						<div class="mq-so__preview">
							<b><?php esc_html_e( 'How it will look', 'marquee' ); ?></b>
							<div class="mq-so__post">
								<div class="mq-so__who"><span class="mq-so__av" aria-hidden="true">★</span><span><b><?php echo esc_html( $s['page_name'] ? $s['page_name'] : $fill['{show}'] ); ?></b><small><?php esc_html_e( 'Just now', 'marquee' ); ?> · 🌎</small></span></div>
								<p data-so-preview></p>
								<span class="mq-so__img" data-so-img></span>
							</div>
						</div>
					</div>
					<div data-so-pic><?php marquee_media_field( 'social_' . $what . '_img', __( 'Picture', 'marquee' ), 'cancel' === $what ? __( 'Your “Show canceled tonight” graphic. Square or 4:5 pictures look best on both. Without one, Facebook gets a text post with a link to your website.', 'marquee' ) : '', $s[ $what . '_img' ] ? array( (int) $s[ $what . '_img' ] ) : array(), false ); ?></div>
					<?php if ( $ready ) : ?>
						<p class="mq-so__now" data-so-nowrow><button type="button" class="button" data-so-ask><?php esc_html_e( 'Post it now…', 'marquee' ); ?></button>
							<span data-so-confirm hidden><?php esc_html_e( 'This posts it publicly right now (Save first if you changed it).', 'marquee' ); ?> <button type="button" class="button button-primary" data-so-now="<?php echo esc_attr( $what ); ?>"><?php esc_html_e( 'Yes, post it', 'marquee' ); ?></button> <button type="button" class="button-link" data-so-no><?php esc_html_e( 'Not now', 'marquee' ); ?></button></span>
							<span class="mq__msg" data-so-nowmsg aria-live="polite"></span></p>
					<?php endif; ?>
				</div>
			</details>
		<?php endforeach; ?>

		<h3><?php esc_html_e( 'Posting history', 'marquee' ); ?></h3>
		<?php if ( ! $s['log'] ) : ?>
			<p class="mq__fine"><?php esc_html_e( 'Nothing posted yet.', 'marquee' ); ?></p>
		<?php else : ?>
			<ul class="mq-so__log">
				<?php foreach ( $s['log'] as $l ) : ?>
					<li class="<?php echo $l['ok'] ? 'is-ok' : 'is-bad'; ?>">
						<span><?php echo esc_html( wp_date( 'M j, g:i a', (int) $l['t'] ) ); ?></span>
						<b><?php echo esc_html( array( 'cancel' => __( 'Canceled post', 'marquee' ), 'resume' => __( 'Back on post', 'marquee' ), 'connect' => __( 'Connection', 'marquee' ) )[ $l['what'] ] ?? $l['what'] ); ?> · <?php echo esc_html( array( 'instagram' => 'Instagram', 'threads' => 'Threads', 'all' => __( 'All', 'marquee' ) )[ $l['net'] ] ?? 'Facebook' ); ?></b>
						<span><?php echo esc_html( $l['msg'] ); ?><?php if ( $l['link'] ) : ?> <a href="<?php echo esc_url( $l['link'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'marquee' ); ?></a><?php endif; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
