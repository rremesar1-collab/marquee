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

/** The latest release (cached 6 hours; 1 hour after a failed check). */
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
	$r = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', array(
		'timeout' => 8,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Marquee/' . MARQUEE_VERSION ),
	) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		set_site_transient( 'marquee_release', array(), HOUR_IN_SECONDS );
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
	set_site_transient( 'marquee_release', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

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
