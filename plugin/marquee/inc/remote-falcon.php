<?php
/**
 * Remote Falcon external API. Each request is signed: a JWT (HS256) whose
 * "accessToken" claim is the show's access token, signed with the secret key.
 * The token and secret never leave the server.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_rf_base() {
	return apply_filters( 'marquee_rf_base', 'https://remotefalcon.com/remote-falcon-external-api' );
}
function marquee_rf_configured() {
	return '' !== marquee_get( 'rf_token' ) && '' !== marquee_get( 'rf_secret' );
}
function marquee_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
}
function marquee_rf_jwt( $token, $secret ) {
	$h = marquee_b64u( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
	$p = marquee_b64u( wp_json_encode( array( 'accessToken' => $token ) ) );
	return $h . '.' . $p . '.' . marquee_b64u( hash_hmac( 'sha256', $h . '.' . $p, $secret, true ) );
}

/**
 * GET /showDetails. Returns the decoded array, or WP_Error.
 * $token/$secret override the saved ones (used by "Test connection").
 */
function marquee_rf_request( $path, $token = null, $secret = null ) {
	$token  = null === $token ? marquee_get( 'rf_token' ) : $token;
	$secret = null === $secret ? marquee_get( 'rf_secret' ) : $secret;
	if ( '' === $token || '' === $secret ) {
		return new WP_Error( 'marquee_rf_missing', __( 'Add your Remote Falcon access token and secret key.', 'marquee' ) );
	}
	$r = wp_remote_get( marquee_rf_base() . $path, array(
		'timeout' => 8,
		'headers' => array( 'Authorization' => 'Bearer ' . marquee_rf_jwt( $token, $secret ), 'Accept' => 'application/json' ),
	) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'marquee_rf_down', __( 'Could not reach Remote Falcon. Try again in a minute.', 'marquee' ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'marquee_rf_auth', __( 'Remote Falcon did not accept the token and secret key. Copy both again from Remote Falcon → Account → API Access.', 'marquee' ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code || ! is_array( $data ) ) {
		/* translators: %d: HTTP status code */
		return new WP_Error( 'marquee_rf_bad', sprintf( __( 'Remote Falcon answered with an error (%d).', 'marquee' ), $code ) );
	}
	return $data;
}

/** Show details, cached for 10 seconds (null when not set up or unavailable). */
function marquee_rf_show_data() {
	if ( ! marquee_rf_configured() ) {
		return null;
	}
	$c = get_transient( 'marquee_rf_show' );
	if ( is_array( $c ) ) {
		return $c ? $c : null;
	}
	$d = marquee_rf_request( '/showDetails' );
	if ( is_wp_error( $d ) ) {
		set_transient( 'marquee_rf_show', array(), 30 ); // short back-off
		return null;
	}
	set_transient( 'marquee_rf_show', $d, 10 );
	return $d;
}
