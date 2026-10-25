<?php
/**
 * Get help from Marquee Support.
 *
 * The show owner describes the problem; Marquee sends a support report (no keys or passwords) to
 * Marquee Support and, if they allow it, opens a support session for 24 hours:
 * - a private sign-in link for a limited "Marquee Support" user (Marquee pages and the media library
 *   only; no plugins, themes or users), removed when the session ends;
 * - a chat on the Get help page (both sides, with pictures);
 * - every change Marquee Support makes, written into the chat in plain words, and a summary at the end;
 * - a live view of the FPP through the names script (read-only, plus "send a test name").
 * Nothing leaves the site except the emails to Marquee Support and the owner.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'MARQUEE_SUPPORT_EMAIL' ) || define( 'MARQUEE_SUPPORT_EMAIL', 'support@alateka.com' );
define( 'MARQUEE_SUPPORT_HOURS', 24 );
define( 'MARQUEE_SUPPORT_KEEP_DAYS', 30 );

/* ------------------------------------------------------------------ state */

function marquee_support() {
	wp_cache_delete( 'marquee_support', 'options' ); // always the newest copy: the chat page, the FPP and Support all write it
	$s = get_option( 'marquee_support' );
	return is_array( $s ) ? $s : array();
}
function marquee_support_save( $s ) {
	update_option( 'marquee_support', $s, false );
}
function marquee_support_active() {
	$s = marquee_support();
	return ! empty( $s['active'] ) && (int) $s['expires'] > time();
}
/** True for the Marquee Support user (the one a session signs in). */
function marquee_is_support_user( $uid = null ) {
	$uid = null === $uid ? get_current_user_id() : (int) $uid;
	return $uid && get_user_meta( $uid, '_marquee_support', true );
}
function marquee_support_chat() {
	wp_cache_delete( 'marquee_support_chat', 'options' );
	$c = get_option( 'marquee_support_chat' );
	return is_array( $c ) ? $c : array();
}
/** Add a chat line. $who: owner, support or system. */
function marquee_support_say( $who, $text, $img = 0 ) {
	$c    = marquee_support_chat();
	$last = $c ? end( $c ) : null;
	$id   = $last ? (int) ( $last['id'] ?? count( $c ) ) + 1 : 1;
	$c[]  = array( 'id' => $id, 't' => time(), 'who' => $who, 'text' => (string) $text, 'img' => (int) $img );
	update_option( 'marquee_support_chat', array_values( array_slice( $c, -400 ) ), false );
	return $id;
}

/* ------------------------------------------------------------------ report */

/** Everything Marquee Support needs to see what's wrong. No keys, tokens or passwords. */
function marquee_support_report_data() {
	$s   = marquee_get();
	$out = function_exists( 'marquee_ai_status' ) ? marquee_ai_status() : array( 'marquee_version' => MARQUEE_VERSION );
	$out['php']        = PHP_VERSION;
	$out['site_name']  = get_bloginfo( 'name' );
	$out['names_mode'] = 'live' === $s['names_mode'] ? 'during songs on a spare screen' : 'names break between songs';
	$fpp               = get_option( 'ridgeroad_fpp' );
	if ( is_array( $fpp ) ) {
		$out['fpp_now'] = trim( $fpp['status'] . ( $fpp['playlist'] ? ' · playlist ' . $fpp['playlist'] : '' ) . ( $fpp['sequence'] ? ' · ' . $fpp['sequence'] : '' ) );
	}
	if ( function_exists( 'marquee_social_get' ) ) {
		$so                   = marquee_social_get();
		$out['social_posts'] = ( marquee_social_fb_ready( $so ) ? 'Facebook connected' : 'Facebook not connected' ) . ( marquee_social_th_ready( $so ) ? ', Threads connected' : '' );
	}
	$view = get_option( 'marquee_support_fpp' );
	if ( is_array( $view ) && ! empty( $view['log'] ) ) {
		$out['names_script_log'] = array_slice( (array) $view['log'], -40 );
	}
	array_walk_recursive( $out, function ( &$v ) {
		if ( is_string( $v ) ) {
			$v = preg_replace( '#(//)[^/@\s]+@#', '$1', $v ); // no user:password in addresses
		}
	} );
	return $out;
}
/** The report as plain text (for the email and the chat). */
function marquee_support_report_text() {
	$lines = array();
	$walk  = function ( $v, $ind ) use ( &$walk, &$lines ) {
		foreach ( (array) $v as $k => $x ) {
			$label = is_int( $k ) ? '-' : str_replace( '_', ' ', $k ) . ':';
			if ( is_array( $x ) ) {
				if ( ! $x ) {
					$lines[] = $ind . $label . ' (none)';
					continue;
				}
				$lines[] = $ind . $label;
				$walk( $x, $ind . '   ' );
			} else {
				$lines[] = $ind . $label . ' ' . ( is_bool( $x ) ? ( $x ? 'yes' : 'no' ) : (string) $x );
			}
		}
	};
	$walk( marquee_support_report_data(), '' );
	return implode( "\n", $lines );
}

/* ----------------------------------------------------------------- session */

/** Start a session: the support user, the private link, the first chat lines and the email. */
function marquee_support_start( $message, $access ) {
	$now   = time();
	$token = wp_generate_password( 40, false, false );
	$uid   = 0;
	if ( $access ) {
		$uid = marquee_support_make_user();
		if ( is_wp_error( $uid ) ) {
			return $uid;
		}
	}
	update_option( 'marquee_support_chat', array(), false );
	delete_option( 'marquee_support_fpp' );
	$s = array(
		'id'       => wp_generate_password( 10, false, false ),
		'token'    => $access ? $token : '',
		'active'   => true,
		'access'   => (bool) $access,
		'started'  => $now,
		'expires'  => $now + MARQUEE_SUPPORT_HOURS * HOUR_IN_SECONDS,
		'user'     => $uid,
		'owner'    => get_current_user_id(),
		'want'     => 1,
		'tests'    => array(),
		'actions'  => array(),
		'changes'  => array(),
		'ended'    => 0,
	);
	marquee_support_save( $s );
	set_transient( 'mq_sup_seen_owner', $now, DAY_IN_SECONDS );
	delete_transient( 'mq_sup_seen_support' );
	marquee_support_say( 'owner', $message );
	marquee_support_say( 'system', $access
		? __( 'Your request was sent to Marquee Support with a support report (your settings without any keys or passwords, and your FPP’s setup check). Marquee Support can sign in to this site for 24 hours to help; you’ll see everything they change right here.', 'marquee' )
		: __( 'Your request was sent to Marquee Support with a support report (your settings without any keys or passwords, and your FPP’s setup check). They’ll answer here.', 'marquee' ) );
	marquee_support_mail_support( 'new', $message );
	return true;
}

function marquee_support_link() {
	$s = marquee_support();
	return ! empty( $s['token'] ) ? add_query_arg( 'marquee_support', $s['token'], home_url( '/' ) ) : '';
}

/** The limited "Marquee Support" user. */
function marquee_support_make_user() {
	if ( ! get_role( 'marquee_support' ) ) {
		add_role( 'marquee_support', 'Marquee Support', array( 'read' => true, 'upload_files' => true, 'manage_options' => true ) );
	}
	$login = 'marquee-support';
	while ( username_exists( $login ) ) {
		$login = 'marquee-support-' . wp_generate_password( 4, false, false );
	}
	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 40, true, true ),
		'display_name' => 'Marquee Support',
		'nickname'     => 'Marquee Support',
		'first_name'   => 'Marquee',
		'last_name'    => 'Support',
		'role'         => 'marquee_support',
	) );
	if ( ! is_wp_error( $uid ) ) {
		update_user_meta( $uid, '_marquee_support', 1 );
	}
	return $uid;
}

/** End the session: sign Marquee Support out and remove the user, and post the summary. */
function marquee_support_end( $why = 'owner' ) {
	$s = marquee_support();
	if ( empty( $s['active'] ) ) {
		return;
	}
	$s['active'] = false;
	$s['ended']  = time();
	$s['token']  = '';
	marquee_support_save( $s );
	if ( ! empty( $s['user'] ) && get_userdata( (int) $s['user'] ) ) {
		WP_Session_Tokens::get_instance( (int) $s['user'] )->destroy_all();
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $s['user'], (int) $s['owner'] ? (int) $s['owner'] : null );
	}
	remove_role( 'marquee_support' );
	delete_transient( 'mq_sup_seen_support' );
	$what = array(
		'owner'   => __( 'You ended the support session.', 'marquee' ),
		'support' => __( 'Marquee Support finished and ended the session.', 'marquee' ),
		'expired' => __( 'The support session ended (24 hours passed).', 'marquee' ),
	);
	$sum = $what[ $why ] ?? $what['owner'];
	$sum .= ' ' . ( ! empty( $s['access'] ) ? __( 'Marquee Support is signed out and can’t sign in again.', 'marquee' ) : '' );
	if ( ! empty( $s['changes'] ) ) {
		$sum .= "\n" . __( 'What Marquee Support changed:', 'marquee' ) . "\n• " . implode( "\n• ", array_values( $s['changes'] ) );
	} elseif ( ! empty( $s['access'] ) ) {
		$sum .= ' ' . __( 'No settings were changed.', 'marquee' );
	}
	marquee_support_say( 'system', trim( $sum ) );
}

/* Timeouts and the 30-day clean-up. */
add_action( 'init', function () {
	$s = marquee_support();
	if ( ! empty( $s['active'] ) && (int) $s['expires'] <= time() ) {
		marquee_support_end( 'expired' );
	}
	if ( ! wp_next_scheduled( 'marquee_support_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'marquee_support_cleanup' );
	}
}, 5 );
add_action( 'marquee_support_cleanup', function () {
	$s = marquee_support();
	if ( empty( $s['active'] ) && ! empty( $s['ended'] ) && time() - (int) $s['ended'] > MARQUEE_SUPPORT_KEEP_DAYS * DAY_IN_SECONDS ) {
		foreach ( marquee_support_chat() as $m ) {
			if ( ! empty( $m['img'] ) && get_post_meta( (int) $m['img'], '_marquee_support', true ) ) {
				wp_delete_attachment( (int) $m['img'], true );
			}
		}
		delete_option( 'marquee_support_chat' );
		delete_option( 'marquee_support_fpp' );
		delete_option( 'marquee_support' );
	}
} );

/* The private sign-in link. */
add_action( 'init', function () {
	if ( empty( $_GET['marquee_support'] ) ) { // phpcs:ignore
		return;
	}
	nocache_headers();
	$tok = sanitize_text_field( wp_unslash( $_GET['marquee_support'] ) ); // phpcs:ignore
	$s   = marquee_support();
	$ip  = 'mq_sup_try_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	if ( (int) get_transient( $ip ) >= 30 ) {
		wp_die( esc_html__( 'Too many tries. Please wait a few minutes.', 'marquee' ), 429 );
	}
	if ( ! marquee_support_active() || empty( $s['token'] ) || ! hash_equals( (string) $s['token'], $tok ) || ! get_userdata( (int) $s['user'] ) ) {
		set_transient( $ip, (int) get_transient( $ip ) + 1, 10 * MINUTE_IN_SECONDS );
		wp_die( esc_html__( 'This support link has ended. The site owner can start a new request from Marquee → Get help.', 'marquee' ), esc_html__( 'Support link ended', 'marquee' ), array( 'response' => 403 ) );
	}
	wp_set_current_user( (int) $s['user'] );
	wp_set_auth_cookie( (int) $s['user'], false, is_ssl() );
	wp_safe_redirect( admin_url( 'admin.php?page=marquee-support' ) );
	exit;
}, 1 );

/* ------------------------------------------------------- what Support can do */

/* No plugins, themes, users, files, import/export, or raw HTML. */
add_filter( 'user_has_cap', function ( $all, $caps, $args, $user ) {
	if ( ! $user || empty( $user->ID ) || ! ( get_user_meta( $user->ID, '_marquee_support', true ) || in_array( 'marquee_support', (array) $user->roles, true ) ) ) {
		return $all;
	}
	if ( ! marquee_support_active() ) {
		return array(); // a session that's over can't do anything
	}
	foreach ( array( 'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins', 'upload_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'delete_themes', 'update_themes', 'upload_themes', 'update_core', 'edit_users', 'create_users', 'delete_users', 'promote_users', 'list_users', 'remove_users', 'add_users', 'export', 'import', 'edit_files', 'unfiltered_html', 'unfiltered_upload', 'delete_site', 'manage_network', 'manage_sites', 'install_languages', 'update_languages', 'customize', 'edit_theme_options', 'export_others_personal_data', 'erase_others_personal_data', 'manage_privacy_options', 'edit_posts', 'edit_pages', 'publish_posts', 'moderate_comments', 'edit_dashboard', 'view_site_health_checks' ) as $c ) {
		$all[ $c ] = false;
	}
	return $all;
}, 99, 4 );

/* Only the Marquee pages, the media library and their own profile. */
/** What the support user may call through admin-ajax.php and admin-post.php. Everything else is refused. */
function marquee_support_allowed_actions() {
	return apply_filters( 'marquee_support_allowed_actions', array(
		'marquee_support_poll', 'marquee_support_send', 'marquee_support_end', 'marquee_support_fpp_ask',
		'marquee_lookup', 'marquee_break_build', 'marquee_break_lines', 'marquee_break_save', 'marquee_voice_test', 'marquee_voice_list', 'marquee_rf_test',
		'marquee_save', 'heartbeat', 'query-attachments', 'upload-attachment', 'get-attachment', 'save-attachment', 'save-attachment-compat', 'send-attachment-to-editor', 'image-editor', 'imgedit-preview', 'wp-remove-post-lock', 'closed-postboxes', 'rest-nonce',
	) );
}
add_action( 'admin_init', function () {
	if ( ! marquee_is_support_user() ) {
		return;
	}
	global $pagenow;
	if ( wp_doing_ajax() || 'admin-post.php' === $pagenow ) {
		$act = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore
		if ( ! marquee_support_active() || ! in_array( $act, marquee_support_allowed_actions(), true ) ) {
			wp_die( esc_html__( 'Marquee Support can’t do that on this site.', 'marquee' ), 403 );
		}
		return;
	}
	if ( ! marquee_support_active() ) {
		wp_logout();
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	$ok   = ( 'admin.php' === $pagenow && 0 === strpos( $page, 'marquee' ) ) || in_array( $pagenow, array( 'upload.php', 'async-upload.php', 'media-upload.php' ), true );
	if ( ! $ok ) {
		wp_safe_redirect( admin_url( 'admin.php?page=marquee-support' ) );
		exit;
	}
} );
/* No REST API (except the media library), XML-RPC or application passwords for Marquee Support. */
add_filter( 'rest_authentication_errors', function ( $err ) {
	if ( ! empty( $err ) || ! marquee_is_support_user() ) {
		return $err;
	}
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore
	if ( marquee_support_active() && 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && preg_match( '#/wp/v2/media\b#', $route ) ) {
		return $err;
	}
	return new WP_Error( 'marquee_support', __( 'Marquee Support can’t use this.', 'marquee' ), array( 'status' => 403 ) );
}, 99 );
add_filter( 'wp_is_application_passwords_available_for_user', function ( $ok, $user ) {
	return ( $user && get_user_meta( $user->ID, '_marquee_support', true ) ) ? false : $ok;
}, 10, 2 );
add_filter( 'authenticate', function ( $user ) {   // the support user only ever signs in through the private link
	if ( $user instanceof WP_User && ( get_user_meta( $user->ID, '_marquee_support', true ) || in_array( 'marquee_support', (array) $user->roles, true ) ) ) {
		return new WP_Error( 'marquee_support', __( 'Use the support link.', 'marquee' ) );
	}
	return $user;
}, 999 );
add_action( 'admin_menu', function () {
	if ( ! marquee_is_support_user() ) {
		return;
	}
	global $menu;
	foreach ( (array) $menu as $m ) {
		if ( ! empty( $m[2] ) && ! in_array( $m[2], array( 'marquee', 'upload.php' ), true ) ) {
			remove_menu_page( $m[2] );
		}
	}
}, 9999 );
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	return ( $user instanceof WP_User && get_user_meta( $user->ID, '_marquee_support', true ) ) ? admin_url( 'admin.php?page=marquee-support' ) : $to;
}, 10, 3 );

/* ------------------------------------------------------------- change log */

function marquee_support_labels() {
	return array(
		'show_name' => __( 'Show name', 'marquee' ), 'short_name' => __( 'Short name', 'marquee' ), 'radio' => __( 'Radio station', 'marquee' ),
		'city' => __( 'City', 'marquee' ), 'region' => __( 'State', 'marquee' ), 'zip' => __( 'ZIP code', 'marquee' ), 'country' => __( 'Country', 'marquee' ),
		'lat' => __( 'Map location', 'marquee' ), 'lon' => __( 'Map location', 'marquee' ), 'tz' => __( 'Time zone', 'marquee' ), 'address' => __( 'Address', 'marquee' ),
		'season_start' => __( 'Opening night', 'marquee' ), 'season_end' => __( 'Last night', 'marquee' ),
		'weeknight_start' => __( 'Sun–Thu start time', 'marquee' ), 'weeknight_close' => __( 'Sun–Thu end time', 'marquee' ),
		'weekend_start' => __( 'Fri–Sat start time', 'marquee' ), 'weekend_close' => __( 'Fri–Sat end time', 'marquee' ), 'use_sunset' => __( 'Start at sunset', 'marquee' ),
		'names_mode' => __( 'How names go up', 'marquee' ), 'fpp_playlist' => __( 'FPP playlist with your names break', 'marquee' ),
		'names_start' => __( 'When the first name appears', 'marquee' ), 'names_slot' => __( 'Time between names', 'marquee' ),
		'live_show_for' => __( 'Seconds each name stays up', 'marquee' ), 'fpp_screens' => __( 'Your screens', 'marquee' ),
		'voice_on' => __( 'Speak the names', 'marquee' ), 'voice_id' => __( 'Voice', 'marquee' ), 'voice_name' => __( 'Voice', 'marquee' ), 'voice_model' => __( 'Voice quality', 'marquee' ),
		'voice_speed' => __( 'Speaking speed', 'marquee' ), 'voice_santa' => __( 'My voice is Santa', 'marquee' ), 'voice_effect' => __( 'Talking faces effect', 'marquee' ),
		'break_intro' => __( 'Names break intro', 'marquee' ), 'break_outro' => __( 'Names break outro', 'marquee' ), 'break_seq' => __( 'Names sequence length', 'marquee' ), 'break_music' => __( 'Music under the names', 'marquee' ),
		'themes_on' => __( 'Let kids pick a themed sequence', 'marquee' ), 'themes' => __( 'Themes kids can pick', 'marquee' ), 'classic_img' => __( 'Classic card picture', 'marquee' ),
		'name_msgs' => __( 'Messages kids can pick', 'marquee' ), 'names_block' => __( 'Blocked names and words', 'marquee' ),
		'names_on' => __( 'Your Name in Lights', 'marquee' ), 'names_geo' => __( 'Only at the show', 'marquee' ), 'names_geo_miles' => __( 'Distance for names', 'marquee' ),
		'gb_show' => __( 'Guestbook', 'marquee' ), 'kids_show' => __( 'Kids’ Corner', 'marquee' ), 'lab_on' => __( 'Make a Light Show', 'marquee' ), 'contest_on' => __( 'Light show contest', 'marquee' ),
		'songs_show' => __( 'Song voting', 'marquee' ), 'app_auto_on' => __( '“The lights are on” alert', 'marquee' ), 'snow_amount' => __( 'Falling snow', 'marquee' ),
		'stats_show' => __( 'Behind the Lights section', 'marquee' ), 'cashapp_url' => __( 'Donation link', 'marquee' ), 'voice_key' => __( 'ElevenLabs key', 'marquee' ), 'rf_token' => __( 'Remote Falcon access token', 'marquee' ), 'rf_secret' => __( 'Remote Falcon secret key', 'marquee' ),
		'facebook_url' => __( 'Facebook link', 'marquee' ), 'instagram_url' => __( 'Instagram link', 'marquee' ), 'youtube_url' => __( 'YouTube link', 'marquee' ), 'tiktok_url' => __( 'TikTok link', 'marquee' ),
		'vote_hide' => __( 'Left out of song voting', 'marquee' ), 'lab_songs' => __( 'Light Lab songs', 'marquee' ), 'art_pages' => __( 'Coloring pages', 'marquee' ),
	);
}
function marquee_support_value( $k, $v ) {
	if ( is_array( $v ) || is_object( $v ) ) {
		return null;
	}
	$v = (string) $v;
	if ( in_array( $v, array( '0', '1', '' ), true ) && preg_match( '/(_on|_show|_santa|geo|^use_)$/', $k ) ) {
		return '1' === $v ? __( 'On', 'marquee' ) : __( 'Off', 'marquee' );
	}
	if ( 'names_mode' === $k ) {
		return 'live' === $v ? __( 'During songs', 'marquee' ) : __( 'Between songs', 'marquee' );
	}
	return '' === $v ? __( '(empty)', 'marquee' ) : '“' . mb_strimwidth( $v, 0, 60, '…' ) . '”';
}
/** Write Marquee Support's setting changes into the chat, in plain words. */
function marquee_support_log_diff( $old, $new ) {
	if ( ! marquee_is_support_user() || ! marquee_support_active() || ! is_array( $old ) || ! is_array( $new ) ) {
		return;
	}
	$labels = marquee_support_labels();
	$done   = array();
	foreach ( $new as $k => $v ) {
		$was = $old[ $k ] ?? null;
		if ( $was == $v ) { // phpcs:ignore -- loose: '1' vs 1
			continue;
		}
		$label = $labels[ $k ] ?? ucfirst( str_replace( '_', ' ', (string) $k ) );
		if ( isset( $done[ $label ] ) ) {
			continue;
		}
		$done[ $label ] = 1;
		if ( preg_match( '/key|token|secret|pass/i', (string) $k ) ) {
			$line = sprintf( __( 'Changed %s (hidden)', 'marquee' ), $label );
		} else {
			$a    = marquee_support_value( $k, $was );
			$b    = marquee_support_value( $k, $v );
			$line = ( null === $a || null === $b ) ? sprintf( __( 'Updated %s', 'marquee' ), $label ) : sprintf( __( 'Changed %1$s from %2$s to %3$s', 'marquee' ), $label, $a, $b );
		}
		marquee_support_change( $line, $label );
	}
}
/** Note a change in the chat. The end summary keeps the last change per setting. */
function marquee_support_change( $line, $what = '' ) {
	$s = marquee_support();
	if ( empty( $s['active'] ) ) {
		return;
	}
	$s['changes'][ $what ? $what : md5( $line ) ] = $line;
	marquee_support_save( $s );
	/* translators: %s: what changed */
	marquee_support_say( 'system', sprintf( __( 'Marquee Support: %s', 'marquee' ), $line ) );
}
add_action( 'update_option_marquee', 'marquee_support_log_diff', 10, 2 );
add_action( 'add_option_marquee', function ( $name, $value ) {
	marquee_support_log_diff( array(), $value );
}, 10, 2 );
add_action( 'init', function () {
	add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'marquee_support_log_diff', 10, 2 );
} );
add_action( 'trashed_post', function ( $id ) {
	if ( marquee_is_support_user() && marquee_support_active() && 'rr_shoutout' === get_post_type( $id ) ) {
		/* translators: %s: a kid's name */
		marquee_support_change( sprintf( __( 'Took the name “%s” off the list', 'marquee' ), get_the_title( $id ) ) );
	}
} );
add_action( 'wp_ajax_marquee_break_build', function () {
	if ( marquee_is_support_user() && marquee_support_active() ) {
		marquee_support_change( __( 'Built the names break audio again', 'marquee' ) );
	}
}, 1 );

/* ------------------------------------------------------------------ emails */

function marquee_support_mail_support( $kind, $text ) {
	$s    = marquee_support();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$link = marquee_support_link();
	if ( 'new' === $kind ) {
		/* translators: 1: site name, 2: first words of the request */
		$subj = sprintf( __( '[Marquee support] %1$s: %2$s', 'marquee' ), $site, mb_strimwidth( preg_replace( '/\s+/', ' ', $text ), 0, 60, '…' ) );
		$body = __( 'New support request.', 'marquee' ) . "\n\n" . $text . "\n\n"
			. ( $link ? __( 'Sign in to help (works until', 'marquee' ) . ' ' . wp_date( __( 'M j, g:i A', 'marquee' ), (int) $s['expires'] ) . "):\n" . $link . "\n\n" : __( 'The owner didn’t allow sign-in. Answer in the chat:', 'marquee' ) . "\n" . admin_url( 'admin.php?page=marquee-support' ) . "\n\n" )
			. "---- " . __( 'Support report', 'marquee' ) . " ----\n" . marquee_support_report_text();
	} else {
		/* translators: %s: site name */
		$subj = sprintf( __( '[Marquee support] %s wrote back', 'marquee' ), $site );
		$body = $text . "\n\n" . ( $link ? __( 'Open the session:', 'marquee' ) . "\n" . $link : '' );
	}
	$headers = array();
	$owner   = get_userdata( (int) ( $s['owner'] ?? 0 ) );
	if ( $owner && $owner->user_email ) {
		$headers[] = 'Reply-To: ' . $owner->display_name . ' <' . $owner->user_email . '>';
	}
	wp_mail( MARQUEE_SUPPORT_EMAIL, $subj, $body, $headers );
}
function marquee_support_mail_owner( $text ) {
	$s     = marquee_support();
	$owner = get_userdata( (int) ( $s['owner'] ?? 0 ) );
	$to    = $owner && $owner->user_email ? $owner->user_email : get_option( 'admin_email' );
	wp_mail( $to, __( 'Marquee Support answered', 'marquee' ), $text . "\n\n" . __( 'Read and answer it here:', 'marquee' ) . "\n" . admin_url( 'admin.php?page=marquee-support' ) );
}
/** Email the other side when they're not on the page (at most every 10 minutes). */
function marquee_support_nudge( $from, $text ) {
	$to = 'owner' === $from ? 'support' : 'owner';
	if ( time() - (int) get_transient( 'mq_sup_seen_' . $to ) < 3 * MINUTE_IN_SECONDS || get_transient( 'mq_sup_mailed_' . $to ) ) {
		return;
	}
	set_transient( 'mq_sup_mailed_' . $to, 1, 10 * MINUTE_IN_SECONDS );
	if ( 'support' === $to ) {
		marquee_support_mail_support( 'reply', $text );
	} else {
		marquee_support_mail_owner( $text );
	}
}

/* -------------------------------------------------------- FPP live view */

/* While a session is on, the names script gets 'support' with each check-in (and checks in every 5 s). */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	if ( marquee_support_active() ) {
		$s               = marquee_support();
		$data['support'] = array( 'on' => true, 'id' => (string) $s['id'], 'want' => (int) $s['want'], 'tests' => array_values( (array) $s['tests'] ), 'actions' => array_values( (array) ( $s['actions'] ?? array() ) ) );
	}
	return $data;
}, 20 );
/* The script sends what it sees (with the names key). */
function marquee_support_fpp_in() {
	nocache_headers();
	$key = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : ''; // phpcs:ignore
	if ( ! function_exists( 'ridgeroad_names_key' ) || ! hash_equals( ridgeroad_names_key(), $key ) ) {
		wp_send_json_error( null, 403 );
	}
	if ( ! marquee_support_active() ) {
		wp_send_json_success( array( 'on' => false ) );
	}
	$v = json_decode( wp_unslash( $_POST['view'] ?? '' ), true ); // phpcs:ignore
	if ( ! is_array( $v ) ) {
		wp_send_json_error( null, 400 );
	}
	$clean = function ( $x, $n = 200 ) {
		return sanitize_text_field( mb_substr( (string) $x, 0, $n ) );
	};
	$view  = array(
		't'              => time(),
		'v'              => $clean( $v['v'] ?? '', 12 ),
		'status'         => $clean( $v['status'] ?? '', 40 ),
		'playlist'       => $clean( $v['playlist'] ?? '', 80 ),
		'sequence'       => $clean( $v['sequence'] ?? '', 120 ),
		'song'           => $clean( $v['song'] ?? '', 120 ),
		'fpp_version'    => $clean( $v['fpp_version'] ?? '', 30 ),
		'mode'           => $clean( $v['mode'] ?? '', 10 ),
		'break_playlist' => $clean( $v['break_playlist'] ?? '', 80 ),
		'names_start'    => (float) ( $v['names_start'] ?? 0 ),
		'slot'           => (float) ( $v['slot'] ?? 0 ),
		'playlists'      => array_map( $clean, array_slice( (array) ( $v['playlists'] ?? array() ), 0, 60 ) ),
		'screens'        => array(),
		'plugin'         => ! empty( $v['plugin'] ),
		'sequences'      => array_map( $clean, array_slice( (array) ( $v['sequences'] ?? array() ), 0, 80 ) ),
		'log'            => array_map( function ( $l ) use ( $clean ) {
			return $clean( $l, 300 );
		}, array_slice( (array) ( $v['log'] ?? array() ), -60 ) ),
	);
	foreach ( array_slice( (array) ( $v['screens'] ?? array() ), 0, 6 ) as $sc ) {
		if ( is_array( $sc ) ) {
			$view['screens'][] = array( 'name' => $clean( $sc['name'] ?? '', 40 ), 'fpp' => $clean( $sc['fpp'] ?? '', 80 ), 'model' => $clean( $sc['model'] ?? '', 80 ), 'ok' => ! empty( $sc['ok'] ), 'size' => $clean( $sc['size'] ?? '', 20 ), 'error' => $clean( $sc['error'] ?? '', 120 ) );
		}
	}
	update_option( 'marquee_support_fpp', $view, false );
	$s = marquee_support();
	if ( empty( $s['active'] ) ) {
		wp_send_json_success( array( 'on' => false ) );
	}
	foreach ( (array) ( $v['actions'] ?? array() ) as $a ) {
		$id = $clean( $a['id'] ?? '', 20 );
		if ( isset( $s['actions'][ $id ] ) ) {
			/* translators: %s: what the FPP said */
			marquee_support_say( 'system', sprintf( ! empty( $a['ok'] ) ? __( 'Your FPP: %s', 'marquee' ) : __( 'Your FPP couldn’t do that: %s', 'marquee' ), $clean( $a['msg'] ?? '', 200 ) ) );
			unset( $s['actions'][ $id ] );
		}
	}
	foreach ( (array) ( $v['tests'] ?? array() ) as $t ) {
		$id = $clean( $t['id'] ?? '', 20 );
		if ( isset( $s['tests'][ $id ] ) ) {
			$n = (int) ( $t['screens'] ?? 0 );
			/* translators: 1: the test text, 2: number of screens */
			marquee_support_say( 'system', $n ? sprintf( _n( 'The test name %1$s went up on %2$d screen.', 'The test name %1$s went up on %2$d screens.', $n, 'marquee' ), '“' . $s['tests'][ $id ]['text'] . '”', $n ) : sprintf( __( 'The FPP tried the test name %s, but no screen took it.', 'marquee' ), '“' . $s['tests'][ $id ]['text'] . '”' ) );
			unset( $s['tests'][ $id ] );
		}
	}
	marquee_support_save( $s );
	wp_send_json_success( array( 'on' => true ) );
}
add_action( 'wp_ajax_marquee_support_fpp', 'marquee_support_fpp_in' );
add_action( 'wp_ajax_nopriv_marquee_support_fpp', 'marquee_support_fpp_in' );

/* --------------------------------------------------------------- page AJAX */

function marquee_support_who() {
	return marquee_is_support_user() ? 'support' : 'owner';
}
function marquee_support_ajax_check() {
	check_ajax_referer( 'marquee_support', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'marquee' ) ), 403 );
	}
}
function marquee_support_state( $since = 0 ) {
	$s    = marquee_support();
	$chat = marquee_support_chat();
	$msgs = array();
	$next = 0;
	foreach ( $chat as $k => $m ) {
		$id   = (int) ( $m['id'] ?? $k + 1 );
		$next = max( $next, $id );
		if ( $id <= $since ) {
			continue;
		}
		$msgs[] = array(
			'i'    => $id,
			'who'  => $m['who'],
			'text' => $m['text'],
			'time' => wp_date( __( 'g:i A', 'marquee' ), (int) $m['t'] ),
			'img'  => $m['img'] ? wp_get_attachment_image_url( (int) $m['img'], 'large' ) : '',
			'full' => $m['img'] ? wp_get_attachment_url( (int) $m['img'] ) : '',
		);
	}
	$view = get_option( 'marquee_support_fpp' );
	return array(
		'active'  => marquee_support_active(),
		'access'  => ! empty( $s['access'] ),
		'ends'    => ! empty( $s['expires'] ) ? wp_date( 'D M j, g:i A', (int) $s['expires'] ) : '',
		'msgs'    => $msgs,
		'next'    => $next,
		'fpp'     => is_array( $view ) ? array_merge( $view, array( 'ago' => human_time_diff( (int) $view['t'] ) ) ) : null,
		'support_here' => time() - (int) get_transient( 'mq_sup_seen_support' ) < 2 * MINUTE_IN_SECONDS,
	);
}
add_action( 'wp_ajax_marquee_support_poll', function () {
	marquee_support_ajax_check();
	if ( marquee_support_active() ) {
		set_transient( 'mq_sup_seen_' . marquee_support_who(), time(), DAY_IN_SECONDS );
	}
	wp_send_json_success( marquee_support_state( isset( $_POST['since'] ) ? (int) $_POST['since'] : 0 ) ); // phpcs:ignore
} );
add_action( 'wp_ajax_marquee_support_start', function () {
	marquee_support_ajax_check();
	if ( marquee_is_support_user() ) {
		wp_send_json_error( null, 403 );
	}
	if ( marquee_support_active() ) {
		wp_send_json_error( array( 'message' => __( 'A support session is already open.', 'marquee' ) ) );
	}
	$msg = trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) ); // phpcs:ignore
	if ( mb_strlen( $msg ) < 5 ) {
		wp_send_json_error( array( 'message' => __( 'Please tell Marquee Support what’s wrong.', 'marquee' ) ) );
	}
	$r = marquee_support_start( mb_substr( $msg, 0, 3000 ), ! empty( $_POST['access'] ) ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success( marquee_support_state() );
} );
add_action( 'wp_ajax_marquee_support_send', function () {
	marquee_support_ajax_check();
	if ( ! marquee_support_active() ) {
		wp_send_json_error( array( 'message' => __( 'The support session has ended.', 'marquee' ) ) );
	}
	$who = marquee_support_who();
	$txt = trim( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ) ); // phpcs:ignore
	$img = 0;
	if ( ! empty( $_FILES['img']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$type = wp_check_filetype( sanitize_file_name( $_FILES['img']['name'] ) ); // phpcs:ignore
		if ( ! in_array( $type['ext'], array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Pictures only (JPG, PNG, GIF or WebP).', 'marquee' ) ) );
		}
		$img = media_handle_upload( 'img', 0, array( 'post_title' => __( 'Support picture', 'marquee' ) ) );
		if ( is_wp_error( $img ) ) {
			wp_send_json_error( array( 'message' => $img->get_error_message() ) );
		}
		update_post_meta( $img, '_marquee_support', 1 );
	}
	if ( '' === $txt && ! $img ) {
		wp_send_json_error( array( 'message' => __( 'Type a message first.', 'marquee' ) ) );
	}
	marquee_support_say( $who, mb_substr( $txt, 0, 3000 ), $img );
	marquee_support_nudge( $who, $txt ? $txt : __( '(sent a picture)', 'marquee' ) );
	wp_send_json_success( marquee_support_state( isset( $_POST['since'] ) ? (int) $_POST['since'] : 0 ) ); // phpcs:ignore
} );
add_action( 'wp_ajax_marquee_support_end', function () {
	marquee_support_ajax_check();
	marquee_support_end( marquee_support_who() );
	wp_send_json_success( array( 'ended' => true, 'support' => marquee_is_support_user() ) );
} );
add_action( 'wp_ajax_marquee_support_extend', function () {
	marquee_support_ajax_check();
	if ( marquee_is_support_user() || ! marquee_support_active() ) {
		wp_send_json_error( null, 403 );
	}
	$s            = marquee_support();
	$s['expires'] = max( (int) $s['expires'], time() ) + MARQUEE_SUPPORT_HOURS * HOUR_IN_SECONDS;
	marquee_support_save( $s );
	/* translators: %s: date and time */
	marquee_support_say( 'system', sprintf( __( 'You gave Marquee Support more time: the session now ends %s.', 'marquee' ), wp_date( 'D M j, g:i A', (int) $s['expires'] ) ) );
	wp_send_json_success( marquee_support_state( (int) ( $_POST['since'] ?? 0 ) ) ); // phpcs:ignore
} );
add_action( 'wp_ajax_marquee_support_fpp_ask', function () {
	marquee_support_ajax_check();
	if ( ! marquee_is_support_user() || ! marquee_support_active() ) {
		wp_send_json_error( null, 403 );
	}
	$s = marquee_support();
	++$s['want'];
	$fixes = array(
		'check'     => __( 'Marquee Support ran the setup check on your FPP.', 'marquee' ),
		'playlists' => __( 'Marquee Support set up your names break playlists on the FPP again.', 'marquee' ),
		'restart'   => __( 'Marquee Support restarted the names script on your FPP.', 'marquee' ),
	);
	$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) ); // phpcs:ignore
	if ( isset( $fixes[ $do ] ) ) {
		if ( count( (array) ( $s['actions'] ?? array() ) ) >= 3 ) {
			wp_send_json_error( array( 'message' => __( 'Wait for the FPP to finish the last one first.', 'marquee' ) ) );
		}
		$id                  = wp_generate_password( 8, false, false );
		$s['actions'][ $id ] = array( 'id' => $id, 'do' => $do );
		marquee_support_save( $s );
		marquee_support_say( 'system', $fixes[ $do ] );
		wp_send_json_success( array( 'ok' => true ) );
	}
	$test = trim( sanitize_text_field( wp_unslash( $_POST['test'] ?? '' ) ) ); // phpcs:ignore
	if ( '' !== $test ) {
		$test = function_exists( 'ridgeroad_clean_kid_name' ) ? ridgeroad_clean_kid_name( $test ) : '';
		if ( '' === $test ) {
			wp_send_json_error( array( 'message' => __( 'Use one plain word for the test, like TEST.', 'marquee' ) ) );
		}
		if ( count( (array) $s['tests'] ) >= 3 ) {
			wp_send_json_error( array( 'message' => __( 'Wait for the FPP to show the last test first.', 'marquee' ) ) );
		}
		$id                 = wp_generate_password( 8, false, false );
		$s['tests'][ $id ]  = array( 'id' => $id, 'text' => mb_substr( $test, 0, 40 ) );
		marquee_support_save( $s );
		/* translators: %s: the test text */
		marquee_support_say( 'system', sprintf( __( 'Marquee Support sent a test name to your screens: %s', 'marquee' ), '“' . mb_substr( $test, 0, 40 ) . '”' ) );
	} else {
		marquee_support_save( $s );
	}
	wp_send_json_success( array( 'ok' => true ) );
} );

/* ------------------------------------------------------------- the page */

add_action( 'admin_menu', function () {
	add_submenu_page( 'marquee', __( 'Get help', 'marquee' ), marquee_support_active() ? __( 'Get help', 'marquee' ) . ' <span class="awaiting-mod">●</span>' : __( 'Get help', 'marquee' ), 'manage_options', 'marquee-support', 'marquee_support_page' );
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'marquee-support' ) ) {
		return;
	}
	wp_enqueue_style( 'marquee-support', MARQUEE_URL . 'assets/support.css', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/support.css' ) );
	wp_enqueue_script( 'marquee-support', MARQUEE_URL . 'assets/support.js', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/support.js' ), true );
	wp_localize_script( 'marquee-support', 'MQSupport', array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'marquee_support' ),
		'who'     => marquee_support_who(),
		'site'    => home_url( '/' ),
		'confirm' => __( 'End the support session? Marquee Support is signed out right away.', 'marquee' ),
	) );
} );

/* A banner on every admin page while a session is on. */
add_action( 'admin_notices', function () {
	if ( ! marquee_support_active() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s   = marquee_support();
	$url = admin_url( 'admin.php?page=marquee-support' );
	echo '<div class="notice notice-info mq-sup-banner"><p>';
	if ( marquee_is_support_user() ) {
		/* translators: 1: site name, 2: date and time */
		echo esc_html( sprintf( __( 'Support session for %1$s, until %2$s. Every setting you change is shown to the owner in the chat.', 'marquee' ), get_bloginfo( 'name' ), wp_date( 'D M j, g:i A', (int) $s['expires'] ) ) ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the chat', 'marquee' ) . '</a>';
	} else {
		echo esc_html( ! empty( $s['access'] )
			/* translators: %s: date and time */
			? sprintf( __( 'Marquee Support is helping you (until %s). Everything they change shows in the chat.', 'marquee' ), wp_date( 'D M j, g:i A', (int) $s['expires'] ) )
			: __( 'Your request to Marquee Support is open.', 'marquee' ) ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the chat', 'marquee' ) . '</a> · <a href="' . esc_url( $url . '#end' ) . '">' . esc_html__( 'End support', 'marquee' ) . '</a>';
	}
	echo '</p></div>';
} );

function marquee_support_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$active = marquee_support_active();
	$sup    = marquee_is_support_user();
	$chat   = marquee_support_chat();
	?>
	<div class="wrap mq-sup" data-mq-sup data-active="<?php echo $active ? '1' : '0'; ?>">
		<h1><?php echo $sup ? esc_html( sprintf( /* translators: %s: site name */ __( 'Support session: %s', 'marquee' ), get_bloginfo( 'name' ) ) ) : esc_html__( 'Get help from Marquee Support', 'marquee' ); ?></h1>

		<?php if ( ! $active && ! $sup ) : ?>
			<div class="mq-sup__start" data-mq-sup-start>
				<p class="mq-sup__lead"><?php esc_html_e( 'Stuck? Tell Marquee Support what’s going on. They get a support report so they can see your setup right away, and can sign in to fix it with you.', 'marquee' ); ?></p>
				<label for="mq-sup-msg"><b><?php esc_html_e( 'What’s wrong?', 'marquee' ); ?></b></label>
				<textarea id="mq-sup-msg" rows="5" maxlength="3000" placeholder="<?php esc_attr_e( 'For example: kids can send names and it says they’re in the queue, but they never show up on my matrix.', 'marquee' ); ?>" data-mq-sup-first></textarea>
				<label class="mq-sup__allow"><input type="checkbox" checked data-mq-sup-access> <span><b><?php esc_html_e( 'Let Marquee Support sign in to fix it', 'marquee' ); ?></b><small><?php esc_html_e( 'For 24 hours, or until you end it. They can use Marquee’s pages and your media library only (no plugins, themes or users), and see your FPP’s status, playlists and log through the names script. Every change shows up in the chat.', 'marquee' ); ?></small></span></label>
				<details class="mq-sup__report"><summary><?php esc_html_e( 'See the support report that’s sent', 'marquee' ); ?></summary><pre><?php echo esc_html( marquee_support_report_text() ); ?></pre><p class="description"><?php esc_html_e( 'No keys, tokens or passwords are included.', 'marquee' ); ?></p></details>
				<p><button type="button" class="button button-primary button-hero" data-mq-sup-go><?php esc_html_e( 'Send to Marquee Support', 'marquee' ); ?></button> <span class="mq-sup__msg" data-mq-sup-err aria-live="polite"></span></p>
				<?php if ( $chat ) : ?><p class="description"><?php esc_html_e( 'Your last support chat is below.', 'marquee' ); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="mq-sup__grid" data-mq-sup-main <?php echo ( $active || $chat ) ? '' : 'hidden'; ?>>
			<section class="mq-sup__chat">
				<div class="mq-sup__head"><b><?php esc_html_e( 'Chat', 'marquee' ); ?></b> <span class="mq-sup__here" data-mq-sup-here hidden><?php echo $sup ? esc_html__( 'The owner is here', 'marquee' ) : esc_html__( 'Marquee Support is here', 'marquee' ); ?></span></div>
				<ol class="mq-sup__msgs" data-mq-sup-msgs aria-live="polite"></ol>
				<form class="mq-sup__form" data-mq-sup-form <?php echo $active ? '' : 'hidden'; ?>>
					<textarea rows="2" maxlength="3000" placeholder="<?php esc_attr_e( 'Write a message…', 'marquee' ); ?>" data-mq-sup-text></textarea>
					<div class="mq-sup__row">
						<label class="button mq-sup__pic"><?php esc_html_e( 'Add a picture', 'marquee' ); ?><input type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden data-mq-sup-img></label>
						<span class="mq-sup__picname" data-mq-sup-picname></span>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'marquee' ); ?></button>
					</div>
				</form>
				<p class="mq-sup__ended" data-mq-sup-ended <?php echo $active ? 'hidden' : ''; ?>><?php esc_html_e( 'This support session has ended. The chat is kept here for 30 days.', 'marquee' ); ?></p>
			</section>
			<aside class="mq-sup__side">
				<div class="mq-sup__card" data-mq-sup-session <?php echo $active ? '' : 'hidden'; ?>>
					<b><?php esc_html_e( 'Support session', 'marquee' ); ?></b>
					<p><?php esc_html_e( 'Open until', 'marquee' ); ?> <span data-mq-sup-ends></span></p>
					<p id="end">
						<?php if ( ! $sup ) : ?><button type="button" class="button" data-mq-sup-extend><?php esc_html_e( 'Give 24 more hours', 'marquee' ); ?></button><?php endif; ?>
						<button type="button" class="button mq-sup__end" data-mq-sup-end><?php echo $sup ? esc_html__( 'Finish and sign out', 'marquee' ) : esc_html__( 'End support', 'marquee' ); ?></button>
					</p>
				</div>
				<div class="mq-sup__card" data-mq-sup-fppcard <?php echo $active ? '' : 'hidden'; ?>>
					<b><?php esc_html_e( 'Your FPP right now', 'marquee' ); ?></b>
					<div data-mq-sup-fpp><p class="description"><?php esc_html_e( 'Waiting for your FPP to check in (the names script needs to be running)…', 'marquee' ); ?></p></div>
					<?php if ( $sup ) : ?>
						<p class="mq-sup__tools"><button type="button" class="button" data-mq-sup-refresh><?php esc_html_e( 'Refresh', 'marquee' ); ?></button>
						<input type="text" maxlength="40" value="TEST" data-mq-sup-testtext aria-label="<?php esc_attr_e( 'Test name', 'marquee' ); ?>"> <button type="button" class="button" data-mq-sup-test><?php esc_html_e( 'Send a test name', 'marquee' ); ?></button></p>
						<p class="mq-sup__tools"><b><?php esc_html_e( 'Fixes on the FPP', 'marquee' ); ?></b><br>
						<button type="button" class="button" data-mq-sup-do="check"><?php esc_html_e( 'Run the setup check', 'marquee' ); ?></button>
						<button type="button" class="button" data-mq-sup-do="playlists"><?php esc_html_e( 'Set up names break playlists again', 'marquee' ); ?></button>
						<button type="button" class="button" data-mq-sup-do="restart"><?php esc_html_e( 'Restart the names script', 'marquee' ); ?></button></p>
					<?php endif; ?>
				</div>
				<?php if ( $sup ) : ?>
					<div class="mq-sup__card">
						<b><?php esc_html_e( 'Support report', 'marquee' ); ?></b>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=marquee' ) ); ?>"><?php esc_html_e( 'Open Marquee Settings', 'marquee' ); ?></a></p>
						<details open><summary><?php esc_html_e( 'Their setup', 'marquee' ); ?></summary><pre><?php echo esc_html( marquee_support_report_text() ); ?></pre></details>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
	<?php
}

/* A "Get help" link in the Plugins list. */
add_filter( 'plugin_action_links_' . plugin_basename( MARQUEE_DIR . 'marquee.php' ), function ( $links ) {
	array_splice( $links, 1, 0, '<a href="' . esc_url( admin_url( 'admin.php?page=marquee-support' ) ) . '">' . esc_html__( 'Get help', 'marquee' ) . '</a>' );
	return $links;
}, 20 );
