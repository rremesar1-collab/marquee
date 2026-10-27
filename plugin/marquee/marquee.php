<?php
/**
 * Plugin Name: Marquee – Show Website for xLights & FPP
 * Description: Show status, Remote Falcon, Your Name in Lights, Kids' Corner, app and alerts for Christmas light shows. Start with Marquee → Setup.
 * Version: 0.12.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Alateka Corporation
 * Author URI: https://alateka.com
 * License: GPLv2 or later
 * Text Domain: marquee
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MARQUEE_VERSION', '0.12.3' );
defined( 'MARQUEE_FPP_PLUGIN_INFO' ) || define( 'MARQUEE_FPP_PLUGIN_INFO', 'https://raw.githubusercontent.com/rremesar1-collab/fpp-marquee/main/pluginInfo.json' );
define( 'MARQUEE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MARQUEE_URL', plugin_dir_url( __FILE__ ) );
// GitHub "owner/repo" where new versions are published (empty = no update checks).
defined( 'MARQUEE_GITHUB_REPO' ) || define( 'MARQUEE_GITHUB_REPO', 'rremesar1-collab/marquee' );
// "Buy me a coffee" link shown to site admins only (Settings page and Plugins list), never to visitors.
defined( 'MARQUEE_COFFEE_URL' ) || define( 'MARQUEE_COFFEE_URL', 'https://www.buymeacoffee.com/marqueeforxlights' );

require MARQUEE_DIR . 'inc/settings.php';
require MARQUEE_DIR . 'inc/langs.php';
require MARQUEE_DIR . 'inc/remote-falcon.php';
require MARQUEE_DIR . 'inc/lookup.php';
require MARQUEE_DIR . 'inc/admin.php';
require MARQUEE_DIR . 'inc/blocks.php';
require MARQUEE_DIR . 'inc/updates.php';
require MARQUEE_DIR . 'inc/manager.php';
require MARQUEE_DIR . 'inc/voice.php';
require MARQUEE_DIR . 'inc/break.php';
require MARQUEE_DIR . 'inc/themes.php';
require MARQUEE_DIR . 'inc/seqmaker.php';
require MARQUEE_DIR . 'inc/social.php';
require MARQUEE_DIR . 'inc/tour.php';
require MARQUEE_DIR . 'inc/ai.php';
require MARQUEE_DIR . 'inc/walk.php';
require MARQUEE_DIR . 'inc/support.php';
require MARQUEE_DIR . 'inc/names-setup.php';
require MARQUEE_DIR . 'inc/covers.php';
require MARQUEE_DIR . 'inc/photos.php';
require MARQUEE_DIR . 'inc/i18n-js.php';

/* -------------------------------------------------------------------------
 * The show features (status, Remote Falcon, names, Kids' Corner, contest, songs,
 * videos, app and alerts, Show Control, share/Google). The festive theme uses
 * these when Marquee is active instead of its own copy.
 * ---------------------------------------------------------------------- */
/*
 * Never crash, whatever theme or theme version is active and whatever order
 * things were installed in: tell the theme Marquee is here (MARQUEE_CORE), then
 * load the features only AFTER the theme has loaded, and only if the theme has
 * not already loaded its own copy (an older Ridge Road theme always does).
 */
function marquee_old_theme() {
	if ( 'ridgeroad' !== get_template() ) {
		return false;
	}
	$v = get_file_data( get_template_directory() . '/style.css', array( 'v' => 'Version' ) );
	return '' !== $v['v'] && version_compare( $v['v'], '2.9.0', '<' );
}
if ( ! function_exists( 'ridgeroad_mod' ) && ! marquee_old_theme() ) {
	defined( 'MARQUEE_CORE' ) || define( 'MARQUEE_CORE', true );
	defined( 'RR_FEAT_URL' ) || define( 'RR_FEAT_URL', MARQUEE_URL . 'core/' );
	defined( 'RR_FEAT_DIR' ) || define( 'RR_FEAT_DIR', MARQUEE_DIR . 'core/' );
	$marquee_loc = marquee_get();
	defined( 'RIDGEROAD_LAT' ) || define( 'RIDGEROAD_LAT', '' !== $marquee_loc['lat'] ? (float) $marquee_loc['lat'] : 0.0 );
	defined( 'RIDGEROAD_LON' ) || define( 'RIDGEROAD_LON', '' !== $marquee_loc['lon'] ? (float) $marquee_loc['lon'] : 0.0 );
	unset( $marquee_loc );
}
/*
 * Languages: the website follows the language picked in Settings → General → Site Language
 * (and each admin's own language in their profile). Spanish and French come with Marquee;
 * any Spanish or French variant (es_MX, fr_CA…) uses them.
 */
function marquee_load_languages() {
	$lang = strtolower( substr( determine_locale(), 0, 2 ) );
	if ( ! in_array( $lang, array( 'es', 'fr' ), true ) ) {
		return;
	}
	foreach ( array( 'marquee', 'ridgeroad' ) as $domain ) {
		$mo = MARQUEE_DIR . 'languages/' . $domain . '-' . $lang . '.mo';
		if ( file_exists( $mo ) ) {
			load_textdomain( $domain, $mo );
		}
	}
}
add_action( 'after_setup_theme', 'marquee_load_languages', -2000 );

function marquee_load_core() {
	if ( function_exists( 'ridgeroad_mod' ) ) { // the theme brought its own copy → use that, no double load
		$GLOBALS['marquee_core_by_theme'] = true;
		return;
	}
	defined( 'RIDGEROAD_VERSION' ) || define( 'RIDGEROAD_VERSION', MARQUEE_VERSION );
	foreach ( array( 'helpers', 'customizer', 'live', 'icons', 'cicons', 'guestbook', 'contest', 'songs', 'sequences', 'shoutouts', 'control', 'app', 'seo', 'assets', 'i18n' ) as $f ) {
		require_once MARQUEE_DIR . 'core/' . $f . '.php';
	}
}
add_action( 'after_setup_theme', 'marquee_load_core', -1000 );

/* An older version of the festive theme (folder "ridgeroad") is active: say so instead of breaking anything. */
add_action( 'admin_notices', function () {
	if ( empty( $GLOBALS['marquee_core_by_theme'] ) || ! current_user_can( 'update_themes' ) || 'ridgeroad' !== get_template() ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Marquee:</strong> ' . esc_html__( 'Your festive theme is older than 2.9.0, so it is still using its own built-in show features. Upload Marquee Festive 2.9.0 or newer (Appearance → Themes → Add New → Upload, then “Replace current”) to finish the switch.', 'marquee' ) . '</p></div>';
} );

/* First activation: send the admin to the setup wizard once. */
register_activation_hook( __FILE__, function () {
	delete_option( 'marquee_rw' ); // add the /manage address again
	if ( ! get_option( 'marquee_setup_done' ) ) {
		set_transient( 'marquee_go_setup', 1, 60 );
	}
} );
add_action( 'admin_init', function () {
	if ( get_transient( 'marquee_go_setup' ) && current_user_can( 'manage_options' ) && ! wp_doing_ajax() && empty( $_GET['activate-multi'] ) ) { // phpcs:ignore
		delete_transient( 'marquee_go_setup' );
		wp_safe_redirect( admin_url( 'admin.php?page=marquee' ) );
		exit;
	}
} );
