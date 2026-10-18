<?php
/**
 * The website as an installable app ("Add to Home Screen") plus show alerts.
 *
 * - Manifest, service worker and offline page are served by the theme from the site
 *   root (?rr_app=...), so no files need to be copied anywhere.
 * - Alerts are standard Web Push, sent straight from this site (VAPID + aes128gcm
 *   encryption with PHP's OpenSSL). No outside service or account.
 * - Stored per phone: the push address and keys the browser gives us, and which
 *   alerts they chose. Nothing else.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RR_APP_BG = '#08122b';

function ridgeroad_app_base() {
	$p = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return $p ? trailingslashit( $p ) : '/';
}
function ridgeroad_app_url( $what ) {
	return add_query_arg( 'rr_app', $what, home_url( '/' ) );
}
function ridgeroad_app_icon( $f ) {
	if ( function_exists( 'marquee_get' ) ) {
		// another show: its WordPress Site Icon (Settings → General) if set, else a neutral star
		$sizes = array( 'icon-512.png' => 512, 'icon-maskable-512.png' => 512, 'icon-192.png' => 192, 'icon-maskable-192.png' => 192, 'apple-touch-icon.png' => 180, 'icon-96.png' => 96 );
		if ( isset( $sizes[ $f ] ) && get_option( 'site_icon' ) ) {
			$u = get_site_icon_url( $sizes[ $f ] );
			if ( $u ) {
				return $u;
			}
		}
		return RR_FEAT_URL . 'assets/img/app-generic/' . $f;
	}
	return RR_FEAT_URL . 'assets/img/app/' . $f;
}
function ridgeroad_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
}
function ridgeroad_b64u_dec( $s ) {
	$s = strtr( (string) $s, '-_', '+/' );
	return base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
}

/* -------------------------------------------------------------------------
 * <head>: manifest + iPhone tags
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( ridgeroad_app_url( 'manifest' ) ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( ridgeroad_app_icon( 'apple-touch-icon.png' ) ) );
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
	printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( ridgeroad_app_short_name() ) );
}, 3 );
// WordPress' own Site Icon also prints an apple-touch-icon; keep ours so the home screen icon is the app icon.
add_filter( 'site_icon_meta_tags', function ( $tags ) {
	return array_values( array_filter( (array) $tags, function ( $t ) {
		return false === strpos( $t, 'apple-touch-icon' );
	} ) );
} );

function ridgeroad_app_short_name() {
	$s = function_exists( 'marquee_get' ) ? marquee_get( 'short_name' ) : '';
	return apply_filters( 'ridgeroad_app_short_name', $s ? $s : ( function_exists( 'marquee_get' ) ? mb_substr( get_bloginfo( 'name' ), 0, 12 ) : 'Ridge Road' ) );
}

/* -------------------------------------------------------------------------
 * Manifest, service worker, offline page
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( empty( $_GET['rr_app'] ) ) { // phpcs:ignore
		return;
	}
	$what = sanitize_key( wp_unslash( $_GET['rr_app'] ) ); // phpcs:ignore
	if ( 'manifest' === $what ) {
		ridgeroad_app_manifest();
	} elseif ( 'sw' === $what ) {
		ridgeroad_app_sw();
	} elseif ( 'offline' === $what ) {
		ridgeroad_app_offline();
	}
}, 1 );

function ridgeroad_app_manifest() {
	$base = ridgeroad_app_base();
	$kids = function_exists( 'ridgeroad_kids_url' ) ? ridgeroad_kids_url() : '';
	$m    = array(
		'id'               => $base,
		'name'             => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'short_name'       => ridgeroad_app_short_name(),
		'description'      => wp_specialchars_decode( ridgeroad_mod( 'intro' ), ENT_QUOTES ),
		'start_url'        => $base . '?source=app',
		'scope'            => $base,
		'display'          => 'standalone',
		'background_color' => RR_APP_BG,
		'theme_color'      => RR_APP_BG,
		'icons'            => array(
			array( 'src' => ridgeroad_app_icon( 'icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => ridgeroad_app_icon( 'icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => ridgeroad_app_icon( 'icon-maskable-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ),
			array( 'src' => ridgeroad_app_icon( 'icon-maskable-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
		),
	);
	if ( $kids && ridgeroad_mod( 'kids_show' ) ) {
		$m['shortcuts'] = array(
			array( 'name' => __( 'Kids’ Corner', 'ridgeroad' ), 'url' => $kids, 'icons' => array( array( 'src' => ridgeroad_app_icon( 'icon-96.png' ), 'sizes' => '96x96' ) ) ),
		);
		if ( ridgeroad_mod( 'names_on' ) ) {
			$m['shortcuts'][] = array( 'name' => __( 'Your Name in Lights', 'ridgeroad' ), 'url' => $kids . '#names', 'icons' => array( array( 'src' => ridgeroad_app_icon( 'icon-96.png' ), 'sizes' => '96x96' ) ) );
		}
	}
	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( $m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}

function ridgeroad_app_sw() {
	$base = ridgeroad_app_base();
	$v    = ridgeroad_vapid();
	$cfg  = array(
		'v'       => RIDGEROAD_VERSION,
		'scope'   => $base,
		'offline' => ridgeroad_app_url( 'offline' ),
		'icon'    => ridgeroad_app_icon( 'icon-192.png' ),
		'badge'   => ridgeroad_app_icon( 'badge-96.png' ),
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'key'     => $v ? $v['pub'] : '',
	);
	nocache_headers();
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: ' . $base );
	echo '/* Show website app (Marquee) */' . "\n" . 'var CFG = ' . wp_json_encode( $cfg, JSON_UNESCAPED_SLASHES ) . ";\n";
	readfile( RR_FEAT_DIR . 'assets/js/sw.js' );
	exit;
}

function ridgeroad_app_offline() {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	$name  = get_bloginfo( 'name' );
	$radio = ridgeroad_mod( 'radio' );
	?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?php echo esc_attr( RR_APP_BG ); ?>"><title><?php echo esc_html( $name ); ?></title>
<style>
html,body{height:100%;margin:0}
body{display:grid;place-items:center;align-content:center;gap:14px;padding:30px;box-sizing:border-box;text-align:center;color:#f4efe4;background:radial-gradient(ellipse at 50% 30%,#1a2f66,<?php echo esc_attr( RR_APP_BG ); ?> 70%);font:16px/1.5 "Segoe UI",Roboto,-apple-system,Arial,sans-serif}
img{width:96px;height:96px;border-radius:22px}
h1{margin:0;font:400 26px/1.2 Georgia,serif;color:#f7c95a}
p{margin:0;max-width:30ch;color:#e2dbca}
.fm{font-size:38px;font-weight:800;color:#fff;letter-spacing:.02em}
button{margin-top:6px;padding:11px 20px;border-radius:10px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.08);color:#fff;font:700 15px inherit;font-family:inherit;cursor:pointer}
</style></head>
<body>
<img src="data:image/png;base64,<?php echo base64_encode( (string) file_get_contents( RR_FEAT_DIR . ( function_exists( 'marquee_get' ) ? 'assets/img/app-generic/icon-96.png' : 'assets/img/app/icon-96.png' ) ) ); // phpcs:ignore ?>" width="96" height="96" alt="">
<h1><?php esc_html_e( 'You’re offline', 'ridgeroad' ); ?></h1>
<p><?php esc_html_e( 'The app needs the internet to load. The show still plays on the radio:', 'ridgeroad' ); ?></p>
<div class="fm"><?php echo esc_html( $radio ); ?></div>
<button type="button" onclick="location.reload()"><?php esc_html_e( 'Try again', 'ridgeroad' ); ?></button>
</body></html>
	<?php
	exit;
}

/* -------------------------------------------------------------------------
 * Subscriptions table
 * ---------------------------------------------------------------------- */
function ridgeroad_push_table() {
	global $wpdb;
	return $wpdb->prefix . 'rr_push';
}
add_action( 'init', 'ridgeroad_push_install', 5 );
function ridgeroad_push_install() {
	if ( '1' === get_option( 'ridgeroad_push_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = ridgeroad_push_table();
	dbDelta( "CREATE TABLE {$t} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
h char(40) NOT NULL,
endpoint text NOT NULL,
p256dh varchar(120) NOT NULL,
auth varchar(40) NOT NULL,
t_start tinyint(1) NOT NULL DEFAULT 0,
t_news tinyint(1) NOT NULL DEFAULT 0,
created int(10) unsigned NOT NULL DEFAULT 0,
seen int(10) unsigned NOT NULL DEFAULT 0,
fails smallint(5) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
UNIQUE KEY h (h)
) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'ridgeroad_push_db', '1' );
}

/** Only real browser push services (so the site can't be used to post anywhere else). */
function ridgeroad_push_endpoint_ok( $url ) {
	$p  = wp_parse_url( $url );
	$ok = ! empty( $p['scheme'] ) && 'https' === $p['scheme'] && ! empty( $p['host'] ) && (bool) preg_match(
		'/(^|\.)(fcm\.googleapis\.com|android\.googleapis\.com|push\.services\.mozilla\.com|push\.apple\.com|notify\.windows\.com)$/i',
		$p['host']
	);
	return (bool) apply_filters( 'ridgeroad_push_endpoint_ok', $ok, $url );
}

/** A push address from the request, untouched (they contain : = and long tokens). */
function ridgeroad_push_ep_in( $k = 'endpoint' ) {
	$e = isset( $_POST[ $k ] ) ? trim( (string) wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore
	return ( strlen( $e ) <= 1000 && preg_match( '#^https?://[^\s"\'<>\\\\]+$#', $e ) ) ? $e : '';
}

/** Reads endpoint/keys from the request; dies with an error when they look wrong. */
function ridgeroad_push_read_sub() {
	$ep   = ridgeroad_push_ep_in();
	$p256 = isset( $_POST['p256dh'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $_POST['p256dh'] ) ) : ''; // phpcs:ignore
	$auth = isset( $_POST['auth'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $_POST['auth'] ) ) : ''; // phpcs:ignore
	$pk   = ridgeroad_b64u_dec( $p256 );
	$ak   = ridgeroad_b64u_dec( $auth );
	if ( ! $ep || strlen( $ep ) > 1000 || ! ridgeroad_push_endpoint_ok( $ep ) || 65 !== strlen( (string) $pk ) || "\x04" !== $pk[0] || 16 !== strlen( (string) $ak ) ) {
		wp_send_json_error( array( 'message' => 'bad subscription' ), 400 );
	}
	return array( 'endpoint' => $ep, 'h' => sha1( $ep ), 'p256dh' => $p256, 'auth' => $auth );
}
function ridgeroad_push_flood() {
	$k = 'rr_ps_' . ridgeroad_ip_hash();
	$n = (int) get_transient( $k );
	if ( $n > 30 ) {
		wp_send_json_error( array( 'message' => 'slow down' ), 429 );
	}
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
}
function ridgeroad_push_row( $h ) {
	global $wpdb;
	$t = ridgeroad_push_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE h = %s", $h ), ARRAY_A ); // phpcs:ignore
}
/** Insert or update one phone. $topics null = keep what it had (new phones get none). */
function ridgeroad_push_save( $s, $topics = null ) {
	global $wpdb;
	$t   = ridgeroad_push_table();
	$row = ridgeroad_push_row( $s['h'] );
	$d   = array( 'endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth'], 'seen' => time(), 'fails' => 0 );
	if ( null !== $topics ) {
		$d['t_start'] = $topics['start'] ? 1 : 0;
		$d['t_news']  = $topics['news'] ? 1 : 0;
	}
	if ( $row ) {
		$wpdb->update( $t, $d, array( 'id' => $row['id'] ) ); // phpcs:ignore
	} else {
		$d['h']       = $s['h'];
		$d['created'] = time();
		$d['t_start'] = isset( $d['t_start'] ) ? $d['t_start'] : 0;
		$d['t_news']  = isset( $d['t_news'] ) ? $d['t_news'] : 0;
		$wpdb->insert( $t, $d ); // phpcs:ignore
	}
	return ridgeroad_push_row( $s['h'] );
}

/* Turn alerts on / change choices */
add_action( 'wp_ajax_rr_push_sub', 'ridgeroad_push_sub_ajax' );
add_action( 'wp_ajax_nopriv_rr_push_sub', 'ridgeroad_push_sub_ajax' );
function ridgeroad_push_sub_ajax() {
	nocache_headers();
	ridgeroad_push_flood();
	$s = ridgeroad_push_read_sub();
	global $wpdb;
	$t = ridgeroad_push_table();
	// the browser replaced its push address: move the old row (and its choices) to the new one
	if ( ! empty( $_POST['old'] ) ) { // phpcs:ignore
		$old = sha1( ridgeroad_push_ep_in( 'old' ) );
		$or  = ridgeroad_push_row( $old );
		if ( $or && ! ridgeroad_push_row( $s['h'] ) ) {
			$wpdb->update( $t, array( 'h' => $s['h'], 'endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth'], 'seen' => time(), 'fails' => 0 ), array( 'id' => $or['id'] ) ); // phpcs:ignore
			wp_send_json_success( array( 'moved' => true ) );
		}
	}
	$topics = null;
	if ( isset( $_POST['start'] ) || isset( $_POST['news'] ) ) { // phpcs:ignore
		$topics = array( 'start' => ! empty( $_POST['start'] ), 'news' => ! empty( $_POST['news'] ) ); // phpcs:ignore
	}
	$row = ridgeroad_push_save( $s, $topics );
	if ( $row && ! empty( $_POST['first'] ) ) { // phpcs:ignore
		ridgeroad_stat_add( 'alerts_on' );
	}
	wp_send_json_success( array( 'start' => (bool) $row['t_start'], 'news' => (bool) $row['t_news'] ) );
}

/* What this phone chose (the alerts card shows it) */
add_action( 'wp_ajax_rr_push_get', 'ridgeroad_push_get_ajax' );
add_action( 'wp_ajax_nopriv_rr_push_get', 'ridgeroad_push_get_ajax' );
function ridgeroad_push_get_ajax() {
	nocache_headers();
	$ep  = ridgeroad_push_ep_in();
	$row = $ep ? ridgeroad_push_row( sha1( $ep ) ) : null;
	wp_send_json_success( $row ? array( 'known' => true, 'start' => (bool) $row['t_start'], 'news' => (bool) $row['t_news'] ) : array( 'known' => false ) );
}

/* Turn alerts off */
add_action( 'wp_ajax_rr_push_unsub', 'ridgeroad_push_unsub_ajax' );
add_action( 'wp_ajax_nopriv_rr_push_unsub', 'ridgeroad_push_unsub_ajax' );
function ridgeroad_push_unsub_ajax() {
	nocache_headers();
	$ep = ridgeroad_push_ep_in();
	if ( $ep ) {
		global $wpdb;
		$wpdb->delete( ridgeroad_push_table(), array( 'h' => sha1( $ep ) ) ); // phpcs:ignore
	}
	wp_send_json_success();
}

/* "Buzz me when my name is up" */
function ridgeroad_buzz_token( $id ) {
	return substr( wp_hash( 'rr_buzz|' . (int) $id ), 0, 20 );
}
add_action( 'wp_ajax_rr_push_buzz', 'ridgeroad_push_buzz_ajax' );
add_action( 'wp_ajax_nopriv_rr_push_buzz', 'ridgeroad_push_buzz_ajax' );
function ridgeroad_push_buzz_ajax() {
	nocache_headers();
	ridgeroad_push_flood();
	$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
	$tok = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore
	if ( ! $id || 'rr_shoutout' !== get_post_type( $id ) || ! hash_equals( ridgeroad_buzz_token( $id ), $tok ) ) {
		wp_send_json_error( array( 'message' => 'bad name' ), 400 );
	}
	if ( ! ridgeroad_mod( 'app_buzz_on' ) ) {
		wp_send_json_error( array( 'message' => 'off' ), 400 );
	}
	$s = ridgeroad_push_read_sub();
	ridgeroad_push_save( $s, null );
	update_post_meta( $id, '_rr_buzz', $s['h'] );
	wp_send_json_success();
}

/* -------------------------------------------------------------------------
 * Sending (VAPID + RFC 8291 aes128gcm)
 * ---------------------------------------------------------------------- */
function ridgeroad_push_ready() {
	return function_exists( 'openssl_pkey_derive' ) && function_exists( 'hash_hkdf' ) && in_array( 'aes-128-gcm', openssl_get_cipher_methods(), true );
}

/** The site's own VAPID key pair, made once. */
function ridgeroad_vapid() {
	$v = get_option( 'ridgeroad_vapid' );
	if ( is_array( $v ) && ! empty( $v['pem'] ) && ! empty( $v['pub'] ) ) {
		return $v;
	}
	if ( ! ridgeroad_push_ready() ) {
		return null;
	}
	$k = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	if ( ! $k || ! openssl_pkey_export( $k, $pem ) ) {
		return null;
	}
	$d = openssl_pkey_get_details( $k );
	$v = array( 'pem' => $pem, 'pub' => ridgeroad_b64u( ridgeroad_ec_point( $d ) ) );
	add_option( 'ridgeroad_vapid', $v, '', 'no' ); // first one wins if two requests race
	return get_option( 'ridgeroad_vapid' );
}
function ridgeroad_ec_point( $details ) {
	return "\x04" . str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
}
function ridgeroad_ec_pub_pem( $raw ) {
	$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $raw;
	return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
}
/** ECDSA DER signature → raw r||s (64 bytes) for a JWT. */
function ridgeroad_der_to_raw( $der ) {
	$o = 2;
	if ( ord( $der[1] ) & 0x80 ) {
		$o += ord( $der[1] ) & 0x7f;
	}
	$o++;
	$lr = ord( $der[ $o ] );
	$r  = substr( $der, $o + 1, $lr );
	$o += 1 + $lr + 1;
	$ls = ord( $der[ $o ] );
	$s  = substr( $der, $o + 1, $ls );
	$fix = function ( $x ) {
		return str_pad( substr( ltrim( $x, "\0" ), -32 ), 32, "\0", STR_PAD_LEFT );
	};
	return $fix( $r ) . $fix( $s );
}
function ridgeroad_vapid_header( $endpoint, $v ) {
	$p   = wp_parse_url( $endpoint );
	$aud = $p['scheme'] . '://' . $p['host'] . ( ! empty( $p['port'] ) ? ':' . $p['port'] : '' );
	$h   = ridgeroad_b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
	$c   = ridgeroad_b64u( wp_json_encode( array( 'aud' => $aud, 'exp' => time() + HOUR_IN_SECONDS, 'sub' => 'mailto:' . get_option( 'admin_email' ) ), JSON_UNESCAPED_SLASHES ) );
	if ( ! openssl_sign( $h . '.' . $c, $der, $v['pem'], OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	return 'vapid t=' . $h . '.' . $c . '.' . ridgeroad_b64u( ridgeroad_der_to_raw( $der ) ) . ', k=' . $v['pub'];
}

/**
 * Encrypt one message for one phone (RFC 8291). $local = array( key, pub ) made once per send.
 */
function ridgeroad_push_encrypt( $payload, $p256dh, $auth, $local ) {
	$ua   = ridgeroad_b64u_dec( $p256dh );
	$sec  = ridgeroad_b64u_dec( $auth );
	$peer = openssl_pkey_get_public( ridgeroad_ec_pub_pem( $ua ) );
	if ( ! $peer ) {
		return false;
	}
	$shared = openssl_pkey_derive( $peer, $local['key'], 32 );
	if ( false === $shared ) {
		return false;
	}
	$ikm   = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\0" . $ua . $local['pub'], $sec );
	$salt  = random_bytes( 16 );
	$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
	$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );
	$tag   = '';
	$ct    = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
	if ( false === $ct ) {
		return false;
	}
	return $salt . pack( 'N', 4096 ) . chr( 65 ) . $local['pub'] . $ct . $tag;
}

/**
 * Send one message to many phones. $rows from the table. Returns array( sent, failed ).
 */
function ridgeroad_push_send( $rows, $msg, $urgent = false ) {
	$out = array( 'sent' => 0, 'failed' => 0 );
	$v   = ridgeroad_vapid();
	if ( ! $rows || ! $v ) {
		return $out;
	}
	$k = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	if ( ! $k ) {
		return $out;
	}
	$local   = array( 'key' => $k, 'pub' => ridgeroad_ec_point( openssl_pkey_get_details( $k ) ) );
	$payload = wp_json_encode( array(
		'title' => wp_specialchars_decode( $msg['title'], ENT_QUOTES ),
		'body'  => wp_specialchars_decode( $msg['body'], ENT_QUOTES ),
		'url'   => isset( $msg['url'] ) ? $msg['url'] : home_url( '/' ),
		'tag'   => isset( $msg['tag'] ) ? $msg['tag'] : 'rr',
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$auth_cache = array();
	global $wpdb;
	$t = ridgeroad_push_table();

	foreach ( array_chunk( $rows, 40 ) as $chunk ) {
		$reqs = array();
		foreach ( $chunk as $i => $r ) {
			$body = ridgeroad_push_encrypt( $payload, $r['p256dh'], $r['auth'], $local );
			if ( false === $body ) {
				$out['failed']++;
				continue;
			}
			$host = wp_parse_url( $r['endpoint'], PHP_URL_HOST );
			if ( ! isset( $auth_cache[ $host ] ) ) {
				$auth_cache[ $host ] = ridgeroad_vapid_header( $r['endpoint'], $v );
			}
			$reqs[ $i ] = array(
				'url'     => $r['endpoint'],
				'type'    => 'POST',
				'data'    => $body,
				'headers' => array(
					'Authorization'    => $auth_cache[ $host ],
					'Content-Encoding' => 'aes128gcm',
					'Content-Type'     => 'application/octet-stream',
					'TTL'              => (string) ( isset( $msg['ttl'] ) ? (int) $msg['ttl'] : 3 * HOUR_IN_SECONDS ),
					'Urgency'          => $urgent ? 'high' : 'normal',
				),
			);
		}
		if ( ! $reqs ) {
			continue;
		}
		$res = ridgeroad_push_http( $reqs );
		foreach ( $reqs as $i => $q ) {
			$code = isset( $res[ $i ] ) ? (int) $res[ $i ] : 0;
			$r    = $chunk[ $i ];
			if ( $code >= 200 && $code < 300 ) {
				$out['sent']++;
				if ( (int) $r['fails'] ) {
					$wpdb->update( $t, array( 'fails' => 0 ), array( 'id' => $r['id'] ) ); // phpcs:ignore
				}
			} else {
				$out['failed']++;
				if ( 404 === $code || 410 === $code || (int) $r['fails'] >= 5 ) {
					$wpdb->delete( $t, array( 'id' => $r['id'] ) ); // phpcs:ignore  // the phone turned alerts off or the app was removed
				} else {
					$wpdb->update( $t, array( 'fails' => (int) $r['fails'] + 1 ), array( 'id' => $r['id'] ) ); // phpcs:ignore
				}
			}
		}
	}
	return $out;
}

/** POST several requests at once; returns status codes by key. */
function ridgeroad_push_http( $reqs ) {
	$codes = array();
	$opt   = array( 'timeout' => 15, 'connect_timeout' => 6, 'useragent' => 'RidgeRoad/' . RIDGEROAD_VERSION );
	$cls   = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( 'Requests' ) ? 'Requests' : '' );
	if ( $cls ) {
		try {
			$res = call_user_func( array( $cls, 'request_multiple' ), $reqs, $opt );
			foreach ( $res as $i => $r ) {
				$codes[ $i ] = is_object( $r ) && isset( $r->status_code ) ? (int) $r->status_code : 0;
			}
			return $codes;
		} catch ( \Throwable $e ) { // fall back to one at a time
			$codes = array();
		}
	}
	foreach ( $reqs as $i => $q ) {
		$r           = wp_remote_post( $q['url'], array( 'timeout' => 15, 'headers' => $q['headers'], 'body' => $q['data'] ) );
		$codes[ $i ] = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	}
	return $codes;
}

/** Send to everyone who chose a topic ('start' or 'news') and log it for Show Control. */
function ridgeroad_push_topic( $topic, $msg, $kind ) {
	global $wpdb;
	$t    = ridgeroad_push_table();
	$col  = 'start' === $topic ? 't_start' : 't_news';
	$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE {$col} = 1", ARRAY_A ); // phpcs:ignore
	$res  = ridgeroad_push_send( $rows, $msg, 'start' === $topic );
	$log  = get_option( 'ridgeroad_push_log', array() );
	$log  = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => time(), 'title' => $msg['title'], 'body' => $msg['body'], 'kind' => $kind, 'sent' => $res['sent'], 'failed' => $res['failed'] ) );
	update_option( 'ridgeroad_push_log', array_slice( $log, 0, 12 ), false );
	return $res;
}
function ridgeroad_push_count( $col = '' ) {
	global $wpdb;
	$t = ridgeroad_push_table();
	if ( 'start' === $col || 'news' === $col ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE t_{$col} = 1" ); // phpcs:ignore
	}
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE t_start = 1 OR t_news = 1" ); // phpcs:ignore
}

/** Run something after the response is sent (FPP's check-in doesn't wait for the alerts). */
function ridgeroad_push_later( $fn ) {
	static $queue = null;
	if ( null === $queue ) {
		$queue = array();
		register_shutdown_function( function () use ( &$queue ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} elseif ( function_exists( 'litespeed_finish_request' ) ) {
				litespeed_finish_request();
			}
			ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore
			}
			foreach ( $queue as $f ) {
				try {
					$f();
				} catch ( \Throwable $e ) {
					error_log( 'Marquee alerts: ' . $e->getMessage() ); // phpcs:ignore
				}
			}
		} );
	}
	$queue[] = $fn;
}

/* -------------------------------------------------------------------------
 * Automatic alerts
 * ---------------------------------------------------------------------- */
/** Called on every FPP check-in. Sends "The lights are on!" once a night when the show playlist starts. */
function ridgeroad_app_fpp_checkin( $status, $playlist ) {
	if ( 'playing' !== $status || '' === $playlist || false !== stripos( $playlist, 'names in lights' ) || ! ridgeroad_mod( 'app_auto_on' ) ) {
		return;
	}
	$s = ridgeroad_status();
	if ( 'running' !== $s['state'] && ! ( 'upcoming' === $s['state'] && time() >= (int) $s['start_ts'] - 15 * MINUTE_IN_SECONDS ) ) {
		return; // a test outside show hours, canceled, or off season
	}
	$night = ridgeroad_stat_day();
	if ( get_option( 'ridgeroad_push_on_night' ) === $night ) {
		return;
	}
	update_option( 'ridgeroad_push_on_night', $night, false );
	ridgeroad_push_later( function () {
		// tidy up phones that only asked for a name buzz (nothing else to send them)
		global $wpdb;
		$t = ridgeroad_push_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE t_start = 0 AND t_news = 0 AND seen < %d", time() - 2 * DAY_IN_SECONDS ) ); // phpcs:ignore
		ridgeroad_push_topic( 'start', array(
			'title' => __( 'The lights are on! 🎄', 'ridgeroad' ),
			/* translators: %s: radio frequency */
			'body'  => sprintf( __( 'The show just started. Tune your radio to %s.', 'ridgeroad' ), ridgeroad_mod( 'radio' ) ),
			'tag'   => 'lights-on',
			'ttl'   => HOUR_IN_SECONDS,
		), 'auto' );
	} );
}

/** Called when FPP picks up names for the next break: buzz the phones that asked. */
function ridgeroad_app_buzz_names( $ids ) {
	if ( ! ridgeroad_mod( 'app_buzz_on' ) ) {
		return;
	}
	$todo = array();
	foreach ( $ids as $id ) {
		$h = get_post_meta( $id, '_rr_buzz', true );
		if ( $h && ! get_post_meta( $id, '_rr_buzzed', true ) ) {
			update_post_meta( $id, '_rr_buzzed', time() );
			$todo[ $id ] = $h;
		}
	}
	if ( ! $todo ) {
		return;
	}
	ridgeroad_push_later( function () use ( $todo ) {
		foreach ( $todo as $id => $h ) {
			$row = ridgeroad_push_row( $h );
			if ( ! $row ) {
				continue;
			}
			$res = ridgeroad_push_send( array( $row ), array(
				'title' => __( 'Your name is up next! ⭐', 'ridgeroad' ),
				/* translators: %s: the name text, e.g. MERRY CHRISTMAS, LIAM! */
				'body'  => sprintf( __( 'Watch for your name after this song: %s', 'ridgeroad' ), get_post_meta( $id, '_rr_text', true ) ),
				'tag'   => 'name-' . $id,
				'url'   => function_exists( 'ridgeroad_kids_url' ) ? ridgeroad_kids_url() . '#names' : home_url( '/' ),
				'ttl'   => 10 * MINUTE_IN_SECONDS,
			), true );
			if ( $res['sent'] ) {
				ridgeroad_stat_add( 'buzz_sent' );
			}
		}
	} );
}

/** Cancel alert, same words as the website's canceled banner. */
function ridgeroad_app_cancel_alert() {
	$b = ridgeroad_banner( array( 'state' => 'canceled' ) );
	return ridgeroad_push_topic( 'news', array(
		'title' => $b['title'],
		'body'  => wp_strip_all_tags( $b['text'] ),
		'tag'   => 'canceled',
		'ttl'   => 6 * HOUR_IN_SECONDS,
	), 'cancel' );
}

/* -------------------------------------------------------------------------
 * Data for the page
 * ---------------------------------------------------------------------- */
function ridgeroad_app_frontend() {
	$v = ridgeroad_vapid();
	return array(
		'sw'    => ridgeroad_app_url( 'sw' ),
		'scope' => ridgeroad_app_base(),
		'key'   => $v ? $v['pub'] : '',
		'buzz'  => (bool) ridgeroad_mod( 'app_buzz_on' ),
		'name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'short' => ridgeroad_app_short_name(),
	);
}
