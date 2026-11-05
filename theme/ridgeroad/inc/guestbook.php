<?php
/**
 * Guestbook stored as WordPress comments on a "Guestbook" page.
 * Every message is held for your approval (WordPress → Comments) before it appears.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ID of the page that holds the guestbook messages (created on first use). */
function ridgeroad_gb_page_id() {
	$id = (int) get_option( 'ridgeroad_gb_page' );
	if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
		return $id;
	}
	$found = get_page_by_path( 'guestbook-messages' );
	if ( $found ) {
		$id = (int) $found->ID;
	} else {
		$id = (int) wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'Guestbook',
			'post_name'      => 'guestbook-messages',
			'post_content'   => '',
			'comment_status' => 'open',
			'ping_status'    => 'closed',
		) );
	}
	if ( $id ) {
		update_option( 'ridgeroad_gb_page', $id, true );
		if ( 'open' !== get_post_field( 'comment_status', $id ) ) {
			wp_update_post( array( 'ID' => $id, 'comment_status' => 'open' ) );
		}
	}
	return $id;
}

function ridgeroad_gb_wp() {
	return 'rfpb' !== ridgeroad_mod( 'gb_source' );
}

function ridgeroad_is_gb_post() {
	return isset( $_POST['comment_post_ID'] ) && (int) $_POST['comment_post_ID'] === (int) get_option( 'ridgeroad_gb_page' ); // phpcs:ignore WordPress.Security.NonceVerification
}

/* Name only — no email needed for guestbook messages. */
add_filter( 'option_require_name_email', function ( $v ) {
	return ridgeroad_is_gb_post() ? 0 : $v;
} );
/* Guestbook messages work even if the site requires login to comment. */
add_filter( 'option_comment_registration', function ( $v ) {
	return ridgeroad_is_gb_post() ? 0 : $v;
} );

/* Always hold for review; honeypot hits go straight to spam. */
add_filter( 'pre_comment_approved', function ( $approved, $data ) {
	if ( (int) $data['comment_post_ID'] !== (int) get_option( 'ridgeroad_gb_page' ) ) {
		return $approved;
	}
	if ( 'spam' === $approved || ! empty( $_POST['website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return 'spam';
	}
	return 0;
}, 99, 2 );

/* After signing, go back to the guestbook with a thank-you. */
add_filter( 'comment_post_redirect', function ( $loc, $comment ) {
	if ( (int) $comment->comment_post_ID === (int) get_option( 'ridgeroad_gb_page' ) ) {
		return add_query_arg( 'gb', 'thanks', home_url( '/' ) ) . '#guestbook';
	}
	return $loc;
}, 10, 2 );

/* Errors (empty message, posting too fast) shown nicely instead of the WordPress error screen. */
add_filter( 'wp_die_handler', function ( $h ) {
	if ( ridgeroad_is_gb_post() ) {
		return function ( $message ) {
			$msg = is_wp_error( $message ) ? $message->get_error_message() : wp_strip_all_tags( (string) $message );
			wp_safe_redirect( add_query_arg( array( 'gb' => 'error', 'gbmsg' => rawurlencode( substr( $msg, 0, 160 ) ) ), home_url( '/' ) ) . '#guestbook' );
			exit;
		};
	}
	return $h;
} );

/** Approved messages, newest first. */
function ridgeroad_gb_messages( $limit = 50 ) {
	return get_comments( array(
		'post_id' => ridgeroad_gb_page_id(),
		'status'  => 'approve',
		'type'    => 'comment',
		'number'  => $limit,
		'orderby' => 'comment_date_gmt',
		'order'   => 'DESC',
	) );
}

/* Count pending messages in the admin bar so you notice new ones. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'moderate_comments' ) || ! ridgeroad_gb_wp() ) {
		return;
	}
	$n = (int) get_comments( array( 'post_id' => (int) get_option( 'ridgeroad_gb_page' ), 'status' => 'hold', 'count' => true ) );
	if ( $n ) {
		$bar->add_node( array(
			'id'    => 'rr-gb-pending',
			/* translators: %d: number of messages */
			'title' => sprintf( _n( '%d guestbook message to review', '%d guestbook messages to review', $n, 'ridgeroad' ), $n ),
			'href'  => admin_url( 'edit-comments.php?comment_status=moderated' ),
		) );
	}
}, 90 );

/** Name to show for a guestbook message: never an email address. */
function ridgeroad_gb_name( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name || is_email( $name ) || false !== strpos( $name, '@' ) ) {
		return __( 'A Friend', 'ridgeroad' );
	}
	return $name;
}

/* Use the name typed in the form (even when logged in) and never keep an email address. */
add_filter( 'preprocess_comment', function ( $data ) {
	if ( (int) $data['comment_post_ID'] !== (int) get_option( 'ridgeroad_gb_page' ) ) {
		return $data;
	}
	$typed = isset( $_POST['author'] ) ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$data['comment_author']       = ridgeroad_gb_name( '' !== $typed ? $typed : $data['comment_author'] );
	$data['comment_author_email'] = '';
	$data['comment_author_url']   = '';
	// Hide any email address typed inside the message itself.
	$data['comment_content'] = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email hidden]', $data['comment_content'] );
	return $data;
}, 1 );

/* ------------------------------------------------------------------------------------------------
 * Keeping robots out of the guestbook (WordPress guestbook).
 * Robots are stopped quietly: they get the thank-you page and their message goes to Comments → Spam,
 * never to Pending, so you only review real messages. Real visitors never notice any of it.
 * Optional: Cloudflare Turnstile (a free "I'm not a robot" check) when you add its two keys.
 * --------------------------------------------------------------------------------------------- */
const RIDGEROAD_GB_MIN_SECONDS = 4;   // nobody types a name and a message faster than this

/** When the form was shown, signed so robots can't make one up. */
function ridgeroad_gb_stamp() {
	$t = time();
	return $t . '.' . substr( hash_hmac( 'sha256', 'rrgb' . $t, wp_salt( 'nonce' ) ), 0, 20 );
}
function ridgeroad_gb_stamp_age( $stamp ) {
	$p = explode( '.', (string) $stamp );
	if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) ) {
		return -1;
	}
	$want = substr( hash_hmac( 'sha256', 'rrgb' . $p[0], wp_salt( 'nonce' ) ), 0, 20 );
	return hash_equals( $want, $p[1] ) ? time() - (int) $p[0] : -1;
}

/** Turnstile is on when both keys are filled in (Customize → Guestbook). */
function ridgeroad_gb_turnstile() {
	$site = trim( (string) ridgeroad_mod( 'gb_ts_site' ) );
	$sec  = trim( (string) ridgeroad_mod( 'gb_ts_secret' ) );
	return ( '' !== $site && '' !== $sec ) ? array( $site, $sec ) : false;
}

/** Why a message looks like spam ('' when it looks fine). Only looks at what was written. */
function ridgeroad_gb_spam_words( $name, $text ) {
	$all = strtolower( $name . ' ' . $text );
	if ( preg_match( '~(https?://|www\.|\[url|<a\s|\b[a-z0-9-]+\.(com|net|org|ru|cn|xyz|top|click|online|site|store|shop|buzz|info|biz|io|co)\b)~i', $all ) ) {
		return 'link';
	}
	if ( preg_match( '/[\x{0400}-\x{04FF}\x{0590}-\x{06FF}\x{0E00}-\x{0E7F}\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $all ) ) {
		return 'foreign script';
	}
	foreach ( array( 'seo', 'backlink', 'casino', 'crypto', 'bitcoin', 'forex', 'viagra', 'cialis', 'porn', 'escort', 'loan', 'guest post', 'rank your', 'google ranking', 'web traffic', 'website traffic', 'lead generation', 'marketing agency', 'digital marketing', 'outsourc', 'virtual assistant', 'whatsapp', 'telegram', 'unsubscribe', 'dating', 'betting', 'payday', 'free trial', 'click here', 'buy now', 'promo code', 'discount code', 'your website', 'your site' ) as $w ) {
		if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '/i', $all ) ) {
			return 'spam word "' . $w . '"';
		}
	}
	if ( preg_match( '/\d{4,}/', $name ) || mb_strlen( $name ) > 60 ) {
		return 'odd name';
	}
	return '';
}

/** Checks only a robot fails. Returns why, or '' for a real person. */
function ridgeroad_gb_robot() {
	// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	if ( ! empty( $_POST['website'] ) ) {
		return 'hidden box filled';
	}
	$age = ridgeroad_gb_stamp_age( isset( $_POST['gb_t'] ) ? wp_unslash( $_POST['gb_t'] ) : '' );
	if ( $age < 0 ) {
		return 'sent without the form';
	}
	if ( $age < RIDGEROAD_GB_MIN_SECONDS ) {
		return 'sent too fast';
	}
	// the form's little script adds this when someone really types; robots that just post the form don't run it
	$h = isset( $_POST['gb_h'] ) ? sanitize_text_field( wp_unslash( $_POST['gb_h'] ) ) : '';
	if ( ! preg_match( '/^k(\d+)\.p(\d+)$/', $h, $m ) || ( (int) $m[1] + (int) $m[2] ) < 3 ) {
		return 'no typing';
	}
	// phpcs:enable
	return '';
}

/* Turnstile: a visitor who didn't pass the check gets a friendly "try again" (nothing is saved). */
add_filter( 'preprocess_comment', function ( $data ) {
	if ( (int) $data['comment_post_ID'] !== (int) get_option( 'ridgeroad_gb_page' ) || ! ( $ts = ridgeroad_gb_turnstile() ) ) {
		return $data;
	}
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$ok    = false;
	if ( '' !== $token ) {
		$r = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 8,
			'body'    => array( 'secret' => $ts[1], 'response' => $token, 'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ),
		) );
		// Cloudflare unreachable: let it through (the other checks still run) rather than lock people out
		$ok = is_wp_error( $r ) || ! empty( json_decode( wp_remote_retrieve_body( $r ), true )['success'] );
	}
	if ( ! $ok ) {
		wp_die( esc_html__( 'Please finish the “I’m not a robot” check, then send your message again.', 'ridgeroad' ) );
	}
	return $data;
}, 5 );

/* Robots and spammy messages go straight to Spam instead of Pending. */
add_filter( 'pre_comment_approved', function ( $approved, $data ) {
	if ( (int) $data['comment_post_ID'] !== (int) get_option( 'ridgeroad_gb_page' ) || 'spam' === $approved || is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
		return $approved;
	}
	$why = ridgeroad_gb_robot();
	if ( '' === $why ) {
		$why = ridgeroad_gb_spam_words( $data['comment_author'], $data['comment_content'] );
	}
	if ( '' !== $why ) {
		$GLOBALS['ridgeroad_gb_spam_why'] = $why;
		return 'spam';
	}
	return $approved;
}, 100, 2 );
add_action( 'comment_post', function ( $id ) {
	if ( ! empty( $GLOBALS['ridgeroad_gb_spam_why'] ) ) {
		add_comment_meta( $id, '_rr_gb_spam', sanitize_text_field( $GLOBALS['ridgeroad_gb_spam_why'] ), true );
	}
} );
/* Clear out the robot messages already waiting in Pending (once), and empty guestbook spam after 30 days. */
add_action( 'admin_init', function () {
	if ( get_option( 'ridgeroad_gb_swept' ) || ! current_user_can( 'moderate_comments' ) ) {
		return;
	}
	update_option( 'ridgeroad_gb_swept', time(), false );
	$pid = (int) get_option( 'ridgeroad_gb_page' );
	if ( ! $pid ) {
		return;
	}
	$moved = 0;
	foreach ( get_comments( array( 'post_id' => $pid, 'status' => 'hold', 'number' => 2000 ) ) as $c ) {
		$why = ridgeroad_gb_spam_words( $c->comment_author, $c->comment_content );
		if ( '' !== $why ) {
			wp_spam_comment( $c );
			add_comment_meta( $c->comment_ID, '_rr_gb_spam', $why, true );
			$moved++;
		}
	}
	if ( $moved ) {
		update_option( 'ridgeroad_gb_swept_n', $moved, false );
	}
} );
add_action( 'ridgeroad_gb_clean', function () {
	$pid = (int) get_option( 'ridgeroad_gb_page' );
	if ( ! $pid ) {
		return;
	}
	foreach ( get_comments( array( 'post_id' => $pid, 'status' => 'spam', 'number' => 500, 'date_query' => array( array( 'before' => '30 days ago' ) ), 'fields' => 'ids' ) ) as $id ) {
		wp_delete_comment( $id, true );
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ridgeroad_gb_clean' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ridgeroad_gb_clean' );
	}
} );
