<?php
/**
 * Updates from GitHub releases: when a newer release is published, WordPress shows
 * the normal "update available" notice for Marquee (and the festive theme, if its zip
 * is attached to the same release).
 *
 * Release = a GitHub release whose tag is the version (v0.7.0 or 0.7.0) with the zips
 * attached: marquee-0.7.0.zip (plugin) and marquee-festive-2.9.6.zip (theme).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** "owner/repo" on GitHub. Empty = update checks are off. */
function marquee_repo() {
	$repo = defined( 'MARQUEE_GITHUB_REPO' ) ? MARQUEE_GITHUB_REPO : '';
	return (string) apply_filters( 'marquee_github_repo', $repo );
}

/**
 * The latest release (cached 6 hours; 15 minutes after a failed check).
 *
 * Asks the GitHub API first. Shared hosting often shares one IP address between many
 * sites, and GitHub allows only 60 anonymous API calls an hour per address, so when the
 * API says no, it reads the public release page on github.com instead. The result of
 * every check is kept in the marquee_update_status option and shown to admins.
 */
function marquee_latest_release( $force = false ) {
	$repo = marquee_repo();
	if ( ! preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ) {
		return null;
	}
	// Dashboard → Updates → "Check again" asks GitHub fresh (once per page load)
	static $fresh = false;
	if ( ! $fresh && is_admin() && isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore
		$force = $fresh = true;
	}
	$c = get_site_transient( 'marquee_release' );
	if ( ! $force && is_array( $c ) ) {
		return $c ? $c : null;
	}

	$out    = marquee_release_from_api( $repo, $api_error );
	$source = 'api';
	if ( ! $out ) {
		$out    = marquee_release_from_page( $repo, $page_error );
		$source = 'page';
	}
	if ( ! $out ) {
		update_site_option( 'marquee_update_status', array(
			'time'  => time(),
			'ok'    => false,
			'error' => trim( $api_error . ' / ' . $page_error, ' /' ),
		) );
		set_site_transient( 'marquee_release', array(), 15 * MINUTE_IN_SECONDS );
		return null;
	}
	update_site_option( 'marquee_update_status', array(
		'time'    => time(),
		'ok'      => true,
		'source'  => $source,
		'version' => $out['version'],
		'error'   => 'page' === $source ? $api_error : '',
	) );
	set_site_transient( 'marquee_release', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

/** Plain-language reason for a failed request. */
function marquee_http_error( $r, $what ) {
	if ( is_wp_error( $r ) ) {
		return $what . ': ' . $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 403 === $code || 429 === $code ) {
		if ( '0' === (string) wp_remote_retrieve_header( $r, 'x-ratelimit-remaining' ) ) {
			return $what . ': GitHub\'s hourly limit for this server\'s address is used up (HTTP ' . $code . ')';
		}
	}
	return $what . ': HTTP ' . $code;
}

/** Latest release from the GitHub API (null on failure, reason in $error). */
function marquee_release_from_api( $repo, &$error ) {
	$error = '';
	$r     = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', array(
		'timeout' => 8,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Marquee/' . MARQUEE_VERSION ),
	) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		$error = marquee_http_error( $r, 'GitHub API' );
		return null;
	}
	$j   = json_decode( wp_remote_retrieve_body( $r ), true );
	$out = array(
		'version' => ltrim( (string) ( $j['tag_name'] ?? '' ), 'vV' ),
		'url'     => (string) ( $j['html_url'] ?? '' ),
		'notes'   => (string) ( $j['body'] ?? '' ),
		'date'    => (string) ( $j['published_at'] ?? '' ),
		'plugin'  => '',
		'theme'   => '',
		'theme_v' => '',
	);
	foreach ( (array) ( $j['assets'] ?? array() ) as $a ) {
		$name = (string) ( $a['name'] ?? '' );
		if ( preg_match( '/^marquee-(\d[\w.]*)\.zip$/', $name ) ) {
			$out['plugin'] = (string) $a['browser_download_url'];
		} elseif ( preg_match( '/^marquee-festive-(\d[\w.]*)\.zip$/', $name, $m ) ) {
			$out['theme']   = (string) $a['browser_download_url'];
			$out['theme_v'] = $m[1];
		}
	}
	if ( '' === $out['version'] ) {
		$error = 'GitHub API: no release tag';
		return null;
	}
	return $out;
}

/** Latest release from the public github.com pages (no API limit). */
function marquee_release_from_page( $repo, &$error ) {
	$error = '';
	$args  = array( 'timeout' => 8, 'redirection' => 0, 'headers' => array( 'User-Agent' => 'Marquee/' . MARQUEE_VERSION ) );
	$r     = wp_remote_head( 'https://github.com/' . $repo . '/releases/latest', $args );
	$loc   = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'location' );
	if ( ! preg_match( '#/releases/tag/([^/?\#]+)$#', $loc, $t ) ) {
		$error = is_wp_error( $r ) || $loc ? marquee_http_error( $r, 'github.com' ) : 'github.com: no release found';
		return null;
	}
	$tag = rawurldecode( $t[1] );
	$r   = wp_remote_get( 'https://github.com/' . $repo . '/releases/expanded_assets/' . rawurlencode( $tag ), $args );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		$error = marquee_http_error( $r, 'github.com' );
		return null;
	}
	$base = 'https://github.com/' . $repo . '/releases/download/' . rawurlencode( $tag ) . '/';
	$out  = array(
		'version' => ltrim( $tag, 'vV' ),
		'url'     => 'https://github.com/' . $repo . '/releases/tag/' . rawurlencode( $tag ),
		'notes'   => 'See what\'s new: https://github.com/' . $repo . '/releases/tag/' . rawurlencode( $tag ),
		'date'    => '',
		'plugin'  => '',
		'theme'   => '',
		'theme_v' => '',
	);
	if ( preg_match_all( '#/releases/download/[^/"]+/(marquee-(festive-)?(\d[\w.]*)\.zip)"#', wp_remote_retrieve_body( $r ), $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $m ) {
			if ( $m[2] ) {
				$out['theme']   = $base . $m[1];
				$out['theme_v'] = $m[3];
			} else {
				$out['plugin'] = $base . $m[1];
			}
		}
	}
	return $out;
}

/* Admins see why the last update check failed, on Dashboard → Updates and Plugins. */
add_action( 'admin_notices', function () {
	global $pagenow;
	if ( ! in_array( $pagenow, array( 'update-core.php', 'plugins.php' ), true ) || ! current_user_can( 'update_plugins' ) || ! marquee_repo() ) {
		return;
	}
	$st = get_site_option( 'marquee_update_status' );
	if ( ! is_array( $st ) || ! empty( $st['ok'] ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>Marquee:</strong> %s</p></div>',
		esc_html( sprintf(
			/* translators: 1: how long ago, 2: reason */
			__( 'Couldn’t check GitHub for Marquee updates (%1$s ago): %2$s. It tries again every 15 minutes, or click “Check again”.', 'marquee' ),
			human_time_diff( (int) $st['time'] ),
			(string) $st['error']
		) )
	);
} );

/* "Last update check" under Marquee on the Plugins page. */
add_filter( 'plugin_row_meta', function ( $meta, $file ) {
	if ( plugin_basename( MARQUEE_DIR . 'marquee.php' ) !== $file || ! current_user_can( 'update_plugins' ) ) {
		return $meta;
	}
	$st = get_site_option( 'marquee_update_status' );
	if ( is_array( $st ) && ! empty( $st['time'] ) ) {
		$meta[] = esc_html( sprintf(
			/* translators: 1: how long ago, 2: result */
			__( 'Update check %1$s ago: %2$s', 'marquee' ),
			human_time_diff( (int) $st['time'] ),
			! empty( $st['ok'] ) ? sprintf( __( 'OK, latest is %s', 'marquee' ), $st['version'] ) : __( 'failed', 'marquee' )
		) );
	}
	return $meta;
}, 10, 2 );

add_filter( 'pre_set_site_transient_update_plugins', function ( $t ) {
	$rel = marquee_latest_release();
	if ( ! is_object( $t ) || ! $rel || ! $rel['plugin'] || ! version_compare( $rel['version'], MARQUEE_VERSION, '>' ) ) {
		return $t;
	}
	$file                  = plugin_basename( MARQUEE_DIR . 'marquee.php' );
	$t->response[ $file ] = (object) array(
		'slug'        => 'marquee',
		'plugin'      => $file,
		'new_version' => $rel['version'],
		'package'     => $rel['plugin'],
		'url'         => $rel['url'],
		'icons'       => array(),
		'tested'      => get_bloginfo( 'version' ),
	);
	return $t;
} );

add_filter( 'pre_set_site_transient_update_themes', function ( $t ) {
	$rel = marquee_latest_release();
	if ( ! is_object( $t ) || ! $rel || ! $rel['theme'] ) {
		return $t;
	}
	$theme = wp_get_theme( 'ridgeroad' );
	// only the festive theme from Marquee (not Lights On Ridge Road's own copy)
	if ( ! $theme->exists() || ! file_exists( $theme->get_stylesheet_directory() . '/.marquee-dist' ) || ! version_compare( $rel['theme_v'], $theme->get( 'Version' ), '>' ) ) {
		return $t;
	}
	$t->response['ridgeroad'] = array(
		'theme'       => 'ridgeroad',
		'new_version' => $rel['theme_v'],
		'url'         => $rel['url'],
		'package'     => $rel['theme'],
	);
	return $t;
} );

/* "View details" for the update: the release notes. */
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'marquee' !== $args->slug ) {
		return $res;
	}
	$rel = marquee_latest_release();
	if ( ! $rel ) {
		return $res;
	}
	return (object) array(
		'name'          => 'Marquee',
		'slug'          => 'marquee',
		'version'       => $rel['version'],
		'author'        => 'Alateka Corporation',
		'homepage'      => $rel['url'],
		'download_link' => $rel['plugin'],
		'last_updated'  => $rel['date'],
		'sections'      => array( 'changelog' => wpautop( esc_html( $rel['notes'] ) ) ),
	);
}, 10, 3 );
