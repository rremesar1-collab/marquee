<?php
/**
 * Front-end scripts for the show features (live refresh, snow, voting, app, Kids' Corner).
 * Loaded by the Marquee plugin when it is active, else by the theme.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_feature_assets() {
	wp_enqueue_script( 'ridgeroad', RR_FEAT_URL . 'assets/js/main.js', array(), RIDGEROAD_VERSION, true );
	wp_localize_script( 'ridgeroad', 'RidgeRoad', array(
		'endpoint' => esc_url_raw( admin_url( 'admin-ajax.php?action=ridgeroad_live' ) ),
		'ajax'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
		'fallback' => esc_url_raw( rest_url( 'ridgeroad/v1/live' ) ),
		'interval' => 5,
		'snow'     => ridgeroad_mod( 'snow_amount' ),
		'snowSize' => ridgeroad_mod( 'snow_size' ),
		'windUnit' => ridgeroad_units()['w'],
		'snowReal' => (bool) ridgeroad_mod( 'snow_real' ),
	) );

	// The app: install card, alerts, offline screen.
	wp_enqueue_script( 'ridgeroad-app', RR_FEAT_URL . 'assets/js/app.js', array( 'ridgeroad' ), RIDGEROAD_VERSION, true );
	wp_localize_script( 'ridgeroad-app', 'RRApp', ridgeroad_app_frontend() );

	// Kids' Corner: online coloring, contest and Art Wall.
	if ( is_page( (int) get_option( 'ridgeroad_kids_page' ) ) ) {
		wp_enqueue_script( 'ridgeroad-lab', RR_FEAT_URL . 'assets/js/lightlab.js', array(), RIDGEROAD_VERSION, true );
		wp_enqueue_script( 'ridgeroad-kids', RR_FEAT_URL . 'assets/js/kids.js', array( 'ridgeroad-lab' ), RIDGEROAD_VERSION, true );
		wp_localize_script( 'ridgeroad-kids', 'RRShow', array(
			'ajax'  => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'watch' => ridgeroad_show_for_watch(),
		) );
	}

	// QR code only needed when a Cash App link is set but no QR image was uploaded.
	if ( is_front_page() && ridgeroad_mod( 'cashapp_url' ) && ! ridgeroad_mod( 'cashapp_qr' ) ) {
		wp_enqueue_script( 'ridgeroad-qrcode', RR_FEAT_URL . 'assets/js/qrcode.js', array(), '2.0.4', true );
	}
}
if ( defined( 'MARQUEE_CORE' ) ) {
	add_action( 'wp_enqueue_scripts', 'ridgeroad_feature_assets', 20 );
}
