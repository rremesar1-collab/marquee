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
