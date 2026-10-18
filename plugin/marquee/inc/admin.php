<?php
/**
 * WP Admin → Marquee: the first-run setup wizard, and afterwards the same
 * questions as one Settings page.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Marquee', 'Marquee', 'manage_options', 'marquee', 'marquee_admin_page', 'dashicons-star-filled', 2 );
	add_submenu_page( 'marquee', __( 'Marquee Settings', 'marquee' ), get_option( 'marquee_setup_done' ) ? __( 'Settings', 'marquee' ) : __( 'Setup', 'marquee' ), 'manage_options', 'marquee', 'marquee_admin_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_marquee' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'marquee-admin', MARQUEE_URL . 'assets/admin.css', array(), MARQUEE_VERSION );
	wp_enqueue_script( 'marquee-qrcode', MARQUEE_URL . 'core/assets/js/qrcode.js', array(), '2.0.4', true );
	wp_enqueue_script( 'marquee-admin', MARQUEE_URL . 'assets/admin.js', array( 'marquee-qrcode' ), MARQUEE_VERSION, true );
	wp_localize_script( 'marquee-admin', 'MarqueeAdmin', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'marquee_admin' ),
	) );
} );

/* Save (normal form post, so it works even without JavaScript) */
add_action( 'admin_post_marquee_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t change these settings.', 'marquee' ) );
	}
	check_admin_referer( 'marquee_save' );
	marquee_save( $_POST ); // phpcs:ignore -- cleaned field by field in marquee_save()
	if ( isset( $_FILES['logo'] ) && ! empty( $_FILES['logo']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( 'logo', 0 );
		if ( ! is_wp_error( $id ) ) {
			set_theme_mod( 'custom_logo', $id );
		}
	}
	if ( ! empty( $_POST['make_pages'] ) && function_exists( 'marquee_make_pages' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above
		set_transient( 'marquee_pages_msg', marquee_make_pages(), 300 );
	}
	$first = ! get_option( 'marquee_setup_done' );
	update_option( 'marquee_setup_done', 1 );
	wp_safe_redirect( add_query_arg( array( 'page' => 'marquee', 'saved' => $first ? 'setup' : '1' ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* Address lookup */
add_action( 'wp_ajax_marquee_lookup', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$r = marquee_lookup( isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '' );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success( $r );
} );

/* Remote Falcon test */
add_action( 'wp_ajax_marquee_rf_test', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
	if ( '' === $secret || '••••••••' === $secret ) {
		$secret = marquee_get( 'rf_secret' );
	}
	$d = marquee_rf_request( '/showDetails', $token, $secret );
	if ( is_wp_error( $d ) ) {
		wp_send_json_error( array( 'message' => $d->get_error_message() ) );
	}
	$n = isset( $d['sequences'] ) && is_array( $d['sequences'] ) ? count( $d['sequences'] ) : 0;
	/* translators: %d: number of sequences */
	wp_send_json_success( array( 'message' => sprintf( _n( 'Connected. Remote Falcon has %d sequence.', 'Connected. Remote Falcon has %d sequences.', $n, 'marquee' ), $n ) ) );
} );

function marquee_admin_page() {
	$s     = marquee_get();
	$setup = ! get_option( 'marquee_setup_done' );
	$tm    = 'marquee_theme_get';
	$theme = 'ridgeroad' === get_template();
	$feat  = array(
		'gb_show'     => array( __( 'Guestbook', 'marquee' ), __( 'Visitors leave messages; each one waits for your OK in WordPress → Comments', 'marquee' ) ),
		'names_on'    => array( __( 'Your Name in Lights', 'marquee' ), __( 'Kids send a name to your matrix, projector or TV (needs the FPP script)', 'marquee' ) ),
		'kids_show'   => array( __( 'Kids’ Corner', 'marquee' ), __( 'Light Lab and contest, coloring pages, games', 'marquee' ) ),
		'lab_on'      => array( __( 'Make a Light Show', 'marquee' ), __( 'The Light Lab in the Kids’ Corner: kids light up a house to a song', 'marquee' ) ),
		'contest_on'  => array( __( 'Light show contest', 'marquee' ), __( 'Entries, Show Wall and hearts in the Kids’ Corner', 'marquee' ) ),
		'songs_show'  => array( __( 'Favorite song voting', 'marquee' ), __( 'Uses your Remote Falcon song list', 'marquee' ) ),
		'app_auto_on' => array( __( '“The lights are on!” alert', 'marquee' ), __( 'Sent to app users when FPP starts your show (needs https)', 'marquee' ) ),
		'snow'        => array( __( 'Falling snow', 'marquee' ), __( 'Snowflakes on every page (size and amount in Show Control)', 'marquee' ) ),
	);
	$names_url = function_exists( 'ridgeroad_names_key' ) ? home_url( '/' ) : '';
	?>
	<div class="wrap mq<?php echo $setup ? ' mq--wizard' : ' mq--settings'; ?>" data-mq>
		<h1><?php echo $setup ? esc_html__( 'Welcome to Marquee', 'marquee' ) : esc_html__( 'Marquee Settings', 'marquee' ); ?></h1>
		<p class="mq__intro"><?php echo $setup ? esc_html__( 'Answer a few questions and your show’s website is ready. You can change any of this later.', 'marquee' ) : esc_html__( 'Everything about your show in one place. Night-of switches are in Show Control, or on your phone in the Show Manager.', 'marquee' ); ?> <a href="<?php echo esc_url( marquee_feedback_url() ); ?>"><?php esc_html_e( 'Send feedback', 'marquee' ); ?></a><?php if ( MARQUEE_COFFEE_URL ) : ?> · <a href="<?php echo esc_url( MARQUEE_COFFEE_URL ); ?>" target="_blank" rel="noopener">☕ <?php esc_html_e( 'Buy me a coffee', 'marquee' ); ?></a><?php endif; ?></p>
		<?php
		$saved     = isset( $_GET['saved'] ) ? sanitize_key( $_GET['saved'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$pages_msg = $saved ? get_transient( 'marquee_pages_msg' ) : false;
		if ( $pages_msg ) {
			delete_transient( 'marquee_pages_msg' );
		}
		if ( 'setup' === $saved ) :
			?>
			<div class="mq__ready">
				<h2><?php esc_html_e( 'Your show’s website is ready!', 'marquee' ); ?></h2>
				<p><a class="button button-primary button-hero" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your site', 'marquee' ); ?></a></p>
				<?php marquee_pages_line( $pages_msg ); ?>
				<p><?php esc_html_e( 'A few more things make it shine. Do them now or any time; this list stays at the top of Marquee → Settings until they’re done.', 'marquee' ); ?></p>
				<?php marquee_checklist( true ); ?>
			</div>
		<?php else : ?>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'marquee' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your site', 'marquee' ); ?></a></p><?php marquee_pages_line( $pages_msg ); ?></div>
			<?php endif; ?>
			<?php if ( ! $setup ) { marquee_checklist( false ); } ?>
		<?php endif; ?>
		<?php if ( ! $setup && function_exists( 'marquee_manager_url' ) ) { marquee_manager_box(); } ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mq__wiz" novalidate>
			<input type="hidden" name="action" value="marquee_save">
			<?php wp_nonce_field( 'marquee_save' ); ?>
			<input type="hidden" name="features_sent" value="1">
			<ol class="mq__steps" aria-label="<?php esc_attr_e( 'Steps', 'marquee' ); ?>">
				<?php
				$steps = array( __( 'Your show', 'marquee' ), __( 'Location', 'marquee' ), __( 'Schedule', 'marquee' ), __( 'Connections', 'marquee' ), __( 'Features', 'marquee' ), __( 'Artwork', 'marquee' ) );
				foreach ( $steps as $i => $st ) {
					printf( '<li><button type="button" data-step="%d">%s</button></li>', (int) $i, esc_html( $st ) );
				}
				?>
			</ol>
			<div class="mq__panes">

			<section class="mq__pane" data-pane="0">
				<h2><?php esc_html_e( 'Your show', 'marquee' ); ?></h2>
				<div class="mq__row">
					<p><label for="mq-name"><?php esc_html_e( 'Show name', 'marquee' ); ?></label><input id="mq-name" name="show_name" type="text" value="<?php echo esc_attr( $s['show_name'] ); ?>" required><small><?php esc_html_e( 'Shown in the header, alerts, Google and share previews.', 'marquee' ); ?></small></p>
					<p><label for="mq-short"><?php esc_html_e( 'Short name', 'marquee' ); ?></label><input id="mq-short" name="short_name" type="text" maxlength="12" value="<?php echo esc_attr( $s['short_name'] ); ?>"><small><?php esc_html_e( 'Under the app icon on phones, 12 letters max.', 'marquee' ); ?></small></p>
				</div>
				<div class="mq__row">
					<p><label for="mq-radio"><?php esc_html_e( 'Radio station', 'marquee' ); ?></label><input id="mq-radio" name="radio" type="text" placeholder="88.1 FM" value="<?php echo esc_attr( $tm( 'radio' ) ); ?>"></p>
					<p><label for="mq-don"><?php esc_html_e( 'Donation link (optional)', 'marquee' ); ?></label><input id="mq-don" name="cashapp_url" type="url" placeholder="https://cash.app/$yourshow" value="<?php echo esc_attr( $tm( 'cashapp_url' ) ); ?>"><small><?php esc_html_e( 'Cash App, PayPal, Venmo or any link. Empty hides the donation box.', 'marquee' ); ?></small></p>
				</div>
				<p><label for="mq-logo"><?php esc_html_e( 'Logo (optional)', 'marquee' ); ?></label><input id="mq-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><small><?php esc_html_e( 'PNG with a see-through background works best.', 'marquee' ); ?><?php echo get_theme_mod( 'custom_logo' ) ? ' ' . esc_html__( 'A logo is already set; choose a file only to replace it.', 'marquee' ) : ''; ?></small></p>
				<?php if ( function_exists( 'ridgeroad_social_networks' ) ) : ?>
					<h3><?php esc_html_e( 'Social links', 'marquee' ); ?></h3>
					<p class="mq__fine"><?php esc_html_e( 'Buttons in Stay Connected and small icons at the bottom of every page. A button shows when it is turned on and has a link.', 'marquee' ); ?></p>
					<input type="hidden" name="social_sent" value="1">
					<?php foreach ( ridgeroad_social_networks() as $net => $n ) : ?>
						<p class="mq__social">
							<label for="mq-so-<?php echo esc_attr( $net ); ?>"><?php echo esc_html( sprintf( __( '%s link', 'marquee' ), $n[0] ) ); ?></label>
							<span class="mq__social-row">
								<input id="mq-so-<?php echo esc_attr( $net ); ?>" name="<?php echo esc_attr( $net ); ?>_url" type="url" placeholder="https://" value="<?php echo esc_attr( $tm( $net . '_url' ) ); ?>">
								<label class="mq__on"><input type="checkbox" name="social_on[]" value="<?php echo esc_attr( $net ); ?>" <?php checked( (bool) $tm( $net . '_show' ) ); ?>> <?php esc_html_e( 'Show', 'marquee' ); ?></label>
							</span>
						</p>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="mq__pane" data-pane="1">
				<h2><?php esc_html_e( 'Location', 'marquee' ); ?></h2>
				<p><label for="mq-addr"><?php esc_html_e( 'Show address', 'marquee' ); ?></label>
					<span class="mq__inline"><input id="mq-addr" name="address" type="text" value="<?php echo esc_attr( $s['address'] ); ?>" placeholder="123 Main St, Town, ST 12345"><button type="button" class="button" data-mq-lookup><?php esc_html_e( 'Find', 'marquee' ); ?></button></span>
					<small><?php esc_html_e( 'Or paste a Google Maps link to your house. Used for sunset, weather, radar and directions.', 'marquee' ); ?></small></p>
				<p class="mq__msg" data-mq-lookup-msg aria-live="polite"></p>
				<div class="mq__row mq__row--4">
					<p><label for="mq-lat"><?php esc_html_e( 'Latitude', 'marquee' ); ?></label><input id="mq-lat" name="lat" type="text" inputmode="decimal" value="<?php echo esc_attr( $s['lat'] ); ?>"></p>
					<p><label for="mq-lon"><?php esc_html_e( 'Longitude', 'marquee' ); ?></label><input id="mq-lon" name="lon" type="text" inputmode="decimal" value="<?php echo esc_attr( $s['lon'] ); ?>"></p>
					<p><label for="mq-tz"><?php esc_html_e( 'Time zone', 'marquee' ); ?></label><select id="mq-tz" name="tz"><?php echo wp_timezone_choice( $s['tz'] ); // phpcs:ignore ?></select></p>
					<p><label for="mq-radar"><?php esc_html_e( 'Radar station', 'marquee' ); ?></label><input id="mq-radar" name="radar_station" type="text" maxlength="4" value="<?php echo esc_attr( $tm( 'radar_station' ) ); ?>"><small><?php esc_html_e( 'US only. Empty hides the radar.', 'marquee' ); ?></small></p>
					<input id="mq-office" name="nws_office" type="hidden" value="<?php echo esc_attr( $s['nws_office'] ); ?>">
				</div>
				<div class="mq__row mq__row--4">
					<p><label for="mq-city"><?php esc_html_e( 'Town', 'marquee' ); ?></label><input id="mq-city" name="city" type="text" value="<?php echo esc_attr( $s['city'] ); ?>"></p>
					<p><label for="mq-region"><?php esc_html_e( 'State', 'marquee' ); ?></label><input id="mq-region" name="region" type="text" value="<?php echo esc_attr( $s['region'] ); ?>"></p>
					<p><label for="mq-zip"><?php esc_html_e( 'ZIP / postcode', 'marquee' ); ?></label><input id="mq-zip" name="zip" type="text" value="<?php echo esc_attr( $s['zip'] ); ?>"></p>
					<p><label for="mq-units"><?php esc_html_e( 'Weather units', 'marquee' ); ?></label><select id="mq-units" name="units"><option value="us" <?php selected( $s['units'], 'us' ); ?>>°F, mph</option><option value="metric" <?php selected( $s['units'], 'metric' ); ?>>°C, km/h</option></select></p>
				</div>
				<input type="hidden" name="country" value="<?php echo esc_attr( $s['country'] ); ?>" data-mq-country>
			</section>

			<section class="mq__pane" data-pane="2">
				<h2><?php esc_html_e( 'Schedule', 'marquee' ); ?></h2>
				<div class="mq__row">
					<p><label for="mq-ss"><?php esc_html_e( 'Opening night', 'marquee' ); ?></label><input id="mq-ss" name="season_start" type="date" value="<?php echo esc_attr( $tm( 'season_start' ) ); ?>"></p>
					<p><label for="mq-se"><?php esc_html_e( 'Last night', 'marquee' ); ?></label><input id="mq-se" name="season_end" type="date" value="<?php echo esc_attr( $tm( 'season_end' ) ); ?>"></p>
				</div>
				<p><label for="mq-mode"><?php esc_html_e( 'Lights on', 'marquee' ); ?></label><select id="mq-mode" name="start_mode"><option value="time" <?php selected( ! $tm( 'use_sunset' ) ); ?>><?php esc_html_e( 'At a set time', 'marquee' ); ?></option><option value="sunset" <?php selected( (bool) $tm( 'use_sunset' ) ); ?>><?php esc_html_e( 'At sunset', 'marquee' ); ?></option></select></p>
				<div class="mq__row mq__row--4">
					<p><label for="mq-ws"><?php esc_html_e( 'Sun–Thu start', 'marquee' ); ?></label><input id="mq-ws" name="weeknight_start" type="time" value="<?php echo esc_attr( $tm( 'weeknight_start' ) ); ?>"></p>
					<p><label for="mq-wc"><?php esc_html_e( 'Sun–Thu end', 'marquee' ); ?></label><input id="mq-wc" name="weeknight_close" type="time" value="<?php echo esc_attr( $tm( 'weeknight_close' ) ); ?>"></p>
					<p><label for="mq-fs"><?php esc_html_e( 'Fri–Sat start', 'marquee' ); ?></label><input id="mq-fs" name="weekend_start" type="time" value="<?php echo esc_attr( $tm( 'weekend_start' ) ); ?>"></p>
					<p><label for="mq-fc"><?php esc_html_e( 'Fri–Sat end', 'marquee' ); ?></label><input id="mq-fc" name="weekend_close" type="time" value="<?php echo esc_attr( $tm( 'weekend_close' ) ); ?>"></p>
				</div>
				<p class="mq__fine"><?php esc_html_e( 'An end time after midnight counts as the same show night.', 'marquee' ); ?></p>
			</section>

			<section class="mq__pane" data-pane="3">
				<h2><?php esc_html_e( 'Connections', 'marquee' ); ?></h2>
				<p class="mq__fine"><?php esc_html_e( 'Both are optional. Without them the site still shows hours, weather and the Kids’ Corner.', 'marquee' ); ?></p>
				<h3><?php esc_html_e( 'Remote Falcon', 'marquee' ); ?></h3>
				<div class="mq__row">
					<p><label for="mq-rft"><?php esc_html_e( 'Access token', 'marquee' ); ?></label><input id="mq-rft" name="rf_token" type="text" autocomplete="off" value="<?php echo esc_attr( $s['rf_token'] ); ?>"></p>
					<p><label for="mq-rfs"><?php esc_html_e( 'Secret key', 'marquee' ); ?></label><input id="mq-rfs" name="rf_secret" type="password" autocomplete="new-password" value="<?php echo $s['rf_secret'] ? '••••••••' : ''; ?>"></p>
				</div>
				<p><button type="button" class="button" data-mq-rftest><?php esc_html_e( 'Test connection', 'marquee' ); ?></button> <span class="mq__msg" data-mq-rfmsg aria-live="polite"></span></p>
				<p class="mq__fine"><?php esc_html_e( 'Both are on Remote Falcon → Account → API Access. They turn on Now Playing, the playlist and song voting, and never leave this server.', 'marquee' ); ?></p>
				<h3><?php esc_html_e( 'Leave out of song voting', 'marquee' ); ?></h3>
				<input type="hidden" name="vote_hide_sent" value="1">
				<?php
				$all_songs = function_exists( 'ridgeroad_song_list_all' ) ? ridgeroad_song_list_all() : array();
				if ( $all_songs ) :
					?>
					<p class="mq__fine"><?php esc_html_e( 'Tick anything that isn’t really a song, like your intro or an announcement. It stays in your show; people just can’t vote for it.', 'marquee' ); ?></p>
					<div class="mq__checks">
						<?php foreach ( $all_songs as $song ) : ?>
							<label><input type="checkbox" name="vote_hide[]" value="<?php echo esc_attr( $song['key'] ); ?>" <?php checked( in_array( $song['key'], (array) $s['vote_hide'], true ) ); ?>> <?php echo esc_html( $song['title'] ); ?></label>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="mq__fine"><?php esc_html_e( 'Your songs show up here once Remote Falcon is connected (save this page after a successful test).', 'marquee' ); ?></p>
				<?php endif; ?>
				<?php if ( $names_url ) : ?>
					<h3><?php esc_html_e( 'FPP: Your Name in Lights', 'marquee' ); ?></h3>
					<p class="mq__fine"><?php esc_html_e( 'A small script on your FPP shows the names kids send. Fill in your screens, save, then download the two files and follow the setup guide.', 'marquee' ); ?></p>
					<?php marquee_names_status(); ?>
					<input type="hidden" name="fpp_sent" value="1">
					<p><label for="mq-fpl"><?php esc_html_e( 'FPP playlist with your names break', 'marquee' ); ?></label><input id="mq-fpl" name="fpp_playlist" type="text" value="<?php echo esc_attr( $s['fpp_playlist'] ); ?>"><small><?php esc_html_e( 'A short sequence (about 30 seconds) that shows your screens, in its own playlist. The guide shows how to make one.', 'marquee' ); ?></small></p>
					<table class="mq__screens">
						<thead><tr><th><?php esc_html_e( 'Screen', 'marquee' ); ?></th><th><?php esc_html_e( 'FPP address', 'marquee' ); ?></th><th><?php esc_html_e( 'Overlay model name', 'marquee' ); ?></th></tr></thead>
						<tbody>
						<?php
						$scr = array_values( (array) $s['fpp_screens'] );
						for ( $i = 0; $i < 4; $i++ ) :
							$r = isset( $scr[ $i ] ) ? $scr[ $i ] : array( 'name' => '', 'fpp' => '', 'model' => '' );
							?>
							<tr>
								<td><input type="text" name="fpp_screens[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $r['name'] ); ?>" placeholder="<?php echo 0 === $i ? esc_attr__( 'Matrix', 'marquee' ) : ''; ?>" aria-label="<?php esc_attr_e( 'Screen name', 'marquee' ); ?>"></td>
								<td><input type="text" name="fpp_screens[<?php echo (int) $i; ?>][fpp]" value="<?php echo esc_attr( $r['fpp'] ); ?>" placeholder="<?php echo 0 === $i ? '127.0.0.1' : '192.168.1.'; ?>" aria-label="<?php esc_attr_e( 'FPP address', 'marquee' ); ?>"></td>
								<td><input type="text" name="fpp_screens[<?php echo (int) $i; ?>][model]" value="<?php echo esc_attr( $r['model'] ); ?>" placeholder="<?php echo 0 === $i ? esc_attr__( 'MatrixText', 'marquee' ) : ''; ?>" aria-label="<?php esc_attr_e( 'Overlay model name', 'marquee' ); ?>"></td>
							</tr>
						<?php endfor; ?>
						</tbody>
					</table>
					<small><?php esc_html_e( 'FPP address: the FPP that drives that screen (127.0.0.1 means the FPP running the script). Overlay model: its name under FPP → Input/Output Setup → Pixel Overlay Models.', 'marquee' ); ?></small>
					<p class="mq__media-btns">
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=marquee_names_json' ), 'marquee_names_json' ) ); ?>"><?php esc_html_e( 'Download marquee_names.json', 'marquee' ); ?></a>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=marquee_names_py' ), 'marquee_names_py' ) ); ?>"><?php esc_html_e( 'Download marquee_names.py', 'marquee' ); ?></a>
						<a class="button-link" href="<?php echo esc_url( MARQUEE_URL . 'core/fpp/marquee-names-setup-guide.txt' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Setup guide', 'marquee' ); ?></a>
					</p>
					<small><?php esc_html_e( 'Save first if you changed anything above; the settings file is made from the saved values. It contains your private key, so keep it private.', 'marquee' ); ?></small>
				<?php endif; ?>
			</section>

			<section class="mq__pane" data-pane="4">
				<h2><?php esc_html_e( 'Features', 'marquee' ); ?></h2>
				<?php
				foreach ( $feat as $k => $f ) {
					$on = 'snow' === $k ? 'off' !== $tm( 'snow_amount' ) : (bool) $tm( $k );
					printf(
						'<label class="mq__feat"><span><b>%s</b><small>%s</small></span><input type="checkbox" name="feat[]" value="%s" %s></label>',
						esc_html( $f[0] ), esc_html( $f[1] ), esc_attr( $k ), checked( $on, true, false )
					);
					if ( 'names_on' === $k ) {
						marquee_geo_row( $s );
					}
				}
				?>
				<h3><?php esc_html_e( 'Show pages', 'marquee' ); ?></h3>
				<?php if ( $theme ) : ?>
					<p class="mq__fine"><?php esc_html_e( 'The festive theme draws your Home, Kids’ Corner and Songs & Videos pages for you.', 'marquee' ); ?></p>
				<?php else : ?>
					<label class="mq__feat"><span><b><?php esc_html_e( 'Make my show pages', 'marquee' ); ?></b><small><?php esc_html_e( 'Creates Home (the whole show, made your front page), Kids’ Corner and Songs & Videos with the show sections in them. Pages that already have content are left alone. You can edit them like any page.', 'marquee' ); ?></small></span><input type="checkbox" name="make_pages" value="1" <?php checked( $setup ); ?>></label>
				<?php endif; ?>
			</section>

			<section class="mq__pane" data-pane="5">
				<h2><?php esc_html_e( 'Artwork', 'marquee' ); ?></h2>
				<p class="mq__fine"><?php esc_html_e( 'Pictures for the Kids’ Corner. All optional: anything left empty uses the standard pictures.', 'marquee' ); ?></p>
				<input type="hidden" name="art_sent" value="1">
				<h3><?php esc_html_e( 'Coloring pages', 'marquee' ); ?></h3>
				<p class="mq__fine"><?php echo get_theme_mod( 'custom_logo' ) ? esc_html__( 'Your logo is printed in the corner of every standard coloring page.', 'marquee' ) : esc_html__( 'Your show name is printed in the corner of every standard coloring page (or your logo, once you add one in step 1).', 'marquee' ); ?></p>
				<?php
				marquee_media_field( 'art_house', __( 'Your house or display', 'marquee' ), __( 'A black-and-white line drawing of your house, shown first as “Our light show”.', 'marquee' ), $s['art_house'] ? array( (int) $s['art_house'] ) : array(), false );
				marquee_media_field( 'art_pages', __( 'More coloring pages', 'marquee' ), __( 'Up to 12. Black-and-white line drawings print best. The picture’s title in the Media Library is the name kids see.', 'marquee' ), (array) $s['art_pages'], true );
				?>
				<h3><?php esc_html_e( 'Light Lab songs', 'marquee' ); ?></h3>
				<?php
				marquee_media_field( 'lab_songs', __( 'Songs kids can pick in Make a Light Show', 'marquee' ), __( 'MP3 files, up to 12. Use songs you have the right to share on your website. The song’s title in the Media Library is the name kids see. With no songs, the lights play on their own for a minute.', 'marquee' ), (array) $s['lab_songs'], true, '', 'audio' );
				?>
				<h3><?php esc_html_e( 'Kids’ Corner door pictures', 'marquee' ); ?></h3>
				<p class="mq__fine"><?php esc_html_e( 'Replace any of the four big pictures kids tap. Tall pictures work best (490 × 685 or bigger, same shape).', 'marquee' ); ?></p>
				<div class="mq__doors">
				<?php
				$doors = array(
					'names'    => array( __( 'Your Name in Lights', 'marquee' ), 'door-1.jpg' ),
					'show'     => array( __( 'Make a Light Show', 'marquee' ), 'door-2.jpg' ),
					'coloring' => array( __( 'Coloring Pages', 'marquee' ), 'door-coloring.jpg' ),
					'games'    => array( __( 'Games', 'marquee' ), 'door-4.jpg' ),
				);
				foreach ( $doors as $k => $d ) {
					$id = ! empty( $s['art_doors'][ $k ] ) ? array( (int) $s['art_doors'][ $k ] ) : array();
					marquee_media_field( 'art_door_' . $k, $d[0], '', $id, false, MARQUEE_URL . 'core/assets/img/kids/' . $d[1] );
				}
				?>
				</div>
			</section>
			</div>

			<div class="mq__nav">
				<button type="button" class="button" data-mq-back><?php esc_html_e( 'Back', 'marquee' ); ?></button>
				<button type="button" class="button button-primary" data-mq-next><?php esc_html_e( 'Next', 'marquee' ); ?></button>
				<button type="submit" class="button button-primary" data-mq-save><?php echo $setup ? esc_html__( 'Finish setup', 'marquee' ) : esc_html__( 'Save settings', 'marquee' ); ?></button>
			</div>
		</form>
	</div>
	<?php
}

/**
 * A picture picker (WordPress Media Library). Saves attachment IDs (comma-separated when $multi).
 * $std = the standard picture, shown faded while nothing is chosen.
 */
function marquee_media_field( $name, $label, $help, $ids, $multi, $std = '', $type = 'image' ) {
	$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	?>
	<div class="mq__media<?php echo $multi ? ' is-multi' : ''; ?>" data-mq-media data-type="<?php echo esc_attr( $type ); ?>" data-multi="<?php echo $multi ? '1' : '0'; ?>" data-title="<?php echo esc_attr( $label ); ?>">
		<b class="mq__media-label"><?php echo esc_html( $label ); ?></b>
		<div class="mq__thumbs" data-mq-thumbs data-std="<?php echo esc_url( $std ); ?>">
			<?php foreach ( $ids as $id ) : $u = 'audio' === $type ? wp_get_attachment_url( $id ) : wp_get_attachment_image_url( $id, 'medium' ); if ( ! $u ) { continue; } ?>
				<span class="mq__thumb<?php echo 'audio' === $type ? ' is-audio' : ''; ?>" data-id="<?php echo (int) $id; ?>"><?php if ( 'audio' === $type ) : ?><span class="mq__song">♪ <?php echo esc_html( get_the_title( $id ) ); ?></span><?php else : ?><img src="<?php echo esc_url( $u ); ?>" alt=""><?php endif; ?><?php if ( $multi ) : ?><button type="button" class="mq__x" data-mq-x aria-label="<?php esc_attr_e( 'Remove', 'marquee' ); ?>">×</button><?php endif; ?></span>
			<?php endforeach; ?>
			<?php if ( ! $ids && $std ) : ?><span class="mq__thumb is-std"><img src="<?php echo esc_url( $std ); ?>" alt=""><em><?php esc_html_e( 'Standard', 'marquee' ); ?></em></span><?php endif; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" data-mq-ids>
		<p class="mq__media-btns">
			<button type="button" class="button" data-mq-pick><?php echo $multi ? ( 'audio' === $type ? esc_html__( 'Add songs', 'marquee' ) : esc_html__( 'Add pictures', 'marquee' ) ) : ( $ids ? esc_html__( 'Change', 'marquee' ) : esc_html__( 'Choose picture', 'marquee' ) ); ?></button>
			<?php if ( ! $multi ) : ?><button type="button" class="button-link" data-mq-clear <?php echo $ids ? '' : 'hidden'; ?>><?php echo $std ? esc_html__( 'Use the standard picture', 'marquee' ) : esc_html__( 'Remove', 'marquee' ); ?></button><?php endif; ?>
		</p>
		<?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?>
	</div>
	<?php
}

/** What the FPP names script last reported (its setup check), for the Connections step. */
function marquee_names_status() {
	$seen = (int) get_option( 'ridgeroad_names_seen' );
	$rep  = get_option( 'ridgeroad_names_report' );
	echo '<div class="mq__fppstat">';
	if ( ! $seen ) {
		echo '<p><b>' . esc_html__( 'FPP hasn’t checked in yet.', 'marquee' ) . '</b> ' . esc_html__( 'Once the script runs on your FPP, its setup check appears here.', 'marquee' ) . '</p>';
	} else {
		/* translators: %s: time ago */
		echo '<p><b>' . esc_html( sprintf( __( 'FPP last checked in %s ago.', 'marquee' ), human_time_diff( $seen ) ) ) . '</b></p>';
		if ( is_array( $rep ) && ! empty( $rep['checks'] ) ) {
			/* translators: %s: time ago */
			echo '<p class="mq__fine">' . esc_html( sprintf( __( 'Setup check from %s ago:', 'marquee' ), human_time_diff( (int) $rep['t'] ) ) ) . '</p><ul>';
			foreach ( $rep['checks'] as $c ) {
				printf( '<li class="%s">%s %s</li>', $c['ok'] ? 'is-ok' : 'is-bad', $c['ok'] ? '✓' : '✗', esc_html( $c['msg'] ) );
			}
			echo '</ul>';
		}
	}
	echo '</div>';
}

/* The settings file for the FPP names script (website address, private key, screens). */
add_action( 'admin_post_marquee_names_json', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t download this.', 'marquee' ) );
	}
	check_admin_referer( 'marquee_names_json' );
	$s       = marquee_get();
	$screens = array();
	foreach ( (array) $s['fpp_screens'] as $r ) {
		if ( ! empty( $r['fpp'] ) && ! empty( $r['model'] ) ) {
			$screens[] = array( 'name' => $r['name'] ? $r['name'] : 'screen ' . ( count( $screens ) + 1 ), 'fpp' => ( 0 === strpos( $r['fpp'], 'http' ) ? $r['fpp'] : 'http://' . $r['fpp'] ), 'model' => $r['model'] );
		}
	}
	$data = array(
		'site'           => home_url( '/' ),
		'key'            => function_exists( 'ridgeroad_names_key' ) ? ridgeroad_names_key() : '',
		'break_playlist' => $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights',
		'screens'        => $screens,
	);
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="marquee_names.json"' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
} );

/* The names script itself, sent through WordPress (some hosts block direct .py downloads). */
add_action( 'admin_post_marquee_names_py', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t download this.', 'marquee' ) );
	}
	check_admin_referer( 'marquee_names_py' );
	nocache_headers();
	header( 'Content-Type: text/x-python; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="marquee_names.py"' );
	readfile( MARQUEE_DIR . 'core/fpp/marquee_names.py' );
	exit;
} );

/** "Your show pages are ready: Home · Kids' Corner · Songs" (after Make my show pages). */
function marquee_pages_line( $msg ) {
	if ( ! $msg || empty( $msg['pages'] ) ) {
		return;
	}
	$links = array();
	foreach ( $msg['pages'] as $pid ) {
		$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( get_permalink( $pid ) ), esc_html( get_the_title( $pid ) ) );
	}
	echo '<p>' . esc_html__( 'Your show pages:', 'marquee' ) . ' ' . wp_kses( implode( ' · ', $links ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) );
	if ( ! empty( $msg['front_kept'] ) ) {
		echo '<br>' . esc_html__( 'Your front page already had content, so it was left alone. To add the show to it, edit it and add the “Show Home Page” block from the Marquee group.', 'marquee' );
	}
	echo '</p>';
}

/** What's left to set up: [ done?, label, why, link, link text ]. */
function marquee_checklist_items() {
	$s     = marquee_get();
	$tm    = 'marquee_theme_get';
	$step  = function ( $n ) {
		return admin_url( 'admin.php?page=marquee#mq-step-' . $n );
	};
	$items = array();
	$items[] = array( marquee_rf_configured(), __( 'Connect Remote Falcon', 'marquee' ), __( 'Now Playing, the playlist and song voting.', 'marquee' ), $step( 3 ), __( 'Connections', 'marquee' ) );
	$items[] = array( (bool) get_theme_mod( 'custom_logo' ), __( 'Add your logo', 'marquee' ), __( 'Header, app icon fallback, share previews and coloring pages.', 'marquee' ), $step( 0 ), __( 'Your show', 'marquee' ) );
	$items[] = array( '' !== (string) $tm( 'cashapp_url' ), __( 'Add a donation link', 'marquee' ), __( 'Optional: Cash App, PayPal, Venmo or any link.', 'marquee' ), $step( 0 ), __( 'Your show', 'marquee' ) );
	if ( $tm( 'kids_show' ) && $tm( 'lab_on' ) ) {
		$items[] = array( ! empty( $s['lab_songs'] ), __( 'Add songs for the Light Lab', 'marquee' ), __( 'Kids light up the house to them.', 'marquee' ), $step( 5 ), __( 'Artwork', 'marquee' ) );
	}
	if ( $tm( 'names_on' ) ) {
		$seen    = (int) get_option( 'ridgeroad_names_seen' );
		$items[] = array( $seen && time() - $seen < 3 * DAY_IN_SECONDS, __( 'Set up Your Name in Lights on FPP', 'marquee' ), __( 'Download the script and settings file, then follow the guide.', 'marquee' ), $step( 3 ), __( 'Connections', 'marquee' ) );
	}
	if ( 'ridgeroad' !== get_template() ) {
		$front   = (int) get_option( 'page_on_front' );
		$on_site = ( $front && has_block( 'marquee/home', $front ) ) || (int) get_option( 'ridgeroad_kids_page' );
		$items[] = array( (bool) $on_site, __( 'Put the show on your pages', 'marquee' ), __( 'Tick “Make my show pages”, or add Marquee blocks to any page.', 'marquee' ), $step( 4 ), __( 'Features', 'marquee' ) );
	}
	$items[] = array( (bool) get_option( 'marquee_tips_seen' ), __( 'Check your visitor tips and text', 'marquee' ), __( 'Before You Visit, guestbook and Kids’ Corner wording.', 'marquee' ), admin_url( 'customize.php?autofocus[panel]=ridgeroad' ), __( 'Customize', 'marquee' ) );
	$items[] = array( 0 === strpos( home_url(), 'https://' ), __( 'Use https for the app and alerts', 'marquee' ), __( 'Phones only install the app and allow alerts on a secure (https) site. Most hosts turn it on for free.', 'marquee' ), '', '' );
	return $items;
}

/** The setup checklist card. $open = start expanded. Hidden when everything is done. */
function marquee_checklist( $open ) {
	$items = marquee_checklist_items();
	$done  = count( array_filter( wp_list_pluck( $items, 0 ) ) );
	if ( $done === count( $items ) && ! $open ) {
		return;
	}
	?>
	<details class="mq__todo" <?php echo $open ? 'open' : ''; ?>>
		<summary><?php /* translators: 1: done, 2: total */ echo esc_html( sprintf( __( 'Setup checklist: %1$d of %2$d done', 'marquee' ), $done, count( $items ) ) ); ?></summary>
		<ul>
			<?php foreach ( $items as $it ) : ?>
				<li class="<?php echo $it[0] ? 'is-done' : ''; ?>">
					<span class="mq__tick" aria-hidden="true"><?php echo $it[0] ? '✓' : ''; ?></span>
					<span><b><?php echo esc_html( $it[1] ); ?></b> <small><?php echo esc_html( $it[2] ); ?></small></span>
					<?php if ( ! $it[0] && $it[3] ) : ?><a class="button button-small" href="<?php echo esc_url( $it[3] ); ?>"><?php echo esc_html( $it[4] ); ?></a><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
	<?php
}

/* Visiting the show's Customizer panel ticks "Check your visitor tips". */
add_action( 'customize_controls_init', function () {
	if ( current_user_can( 'manage_options' ) && ! get_option( 'marquee_tips_seen' ) ) {
		update_option( 'marquee_tips_seen', time(), false );
	}
} );

/* Under the Marquee menu: clear names for the show's lists, and Settings first (it's where the menu opens). */
add_filter( 'register_post_type_args', function ( $args, $type ) {
	$names = array(
		'rr_lightshow' => __( 'Contest entries', 'marquee' ),
		'rr_sequence'  => __( 'Show videos', 'marquee' ),
		'rr_shoutout'  => __( 'Names in Lights', 'marquee' ),
	);
	if ( isset( $names[ $type ] ) ) {
		$args['labels']              = isset( $args['labels'] ) ? (array) $args['labels'] : array();
		$args['labels']['all_items'] = $names[ $type ];
	}
	return $args;
}, 10, 2 );
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['marquee'] ) ) {
		return;
	}
	usort( $submenu['marquee'], function ( $a, $b ) {
		return ( 'marquee' === $b[2] ) - ( 'marquee' === $a[2] );
	} );
}, 999 );

/** A pre-filled email to Marquee support (version and site included, nothing private). */
function marquee_feedback_url() {
	$body = sprintf( "\n\n---\nMarquee %s · WordPress %s · theme %s · %s", MARQUEE_VERSION, get_bloginfo( 'version' ), get_template(), home_url( '/' ) );
	return 'mailto:support@alateka.com?subject=' . rawurlencode( 'Marquee feedback' ) . '&body=' . rawurlencode( $body );
}
add_filter( 'plugin_action_links_' . plugin_basename( MARQUEE_DIR . 'marquee.php' ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=marquee' ) ) . '">' . esc_html__( 'Settings', 'marquee' ) . '</a>' );
	$links[] = '<a href="' . esc_url( marquee_feedback_url() ) . '">' . esc_html__( 'Send feedback', 'marquee' ) . '</a>';
	if ( MARQUEE_COFFEE_URL ) {
		$links[] = '<a href="' . esc_url( MARQUEE_COFFEE_URL ) . '" target="_blank" rel="noopener">☕ ' . esc_html__( 'Buy me a coffee', 'marquee' ) . '</a>';
	}
	return $links;
} );

/** "Run show nights from your phone" box on Marquee → Settings. */
function marquee_manager_box() {
	$url = marquee_manager_url();
	?>
	<details class="mq__mgr">
		<summary><?php esc_html_e( 'Run show nights from your phone: the Show Manager', 'marquee' ); ?></summary>
		<div class="mq__mgr-in">
			<div class="mq__qr" data-mq-qr="<?php echo esc_attr( $url ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for the Show Manager', 'marquee' ); ?>"></div>
			<div>
				<p><?php esc_html_e( 'Cancel the show, approve names, contest entries and guestbook messages, and send alerts, all from a page on your own website. Scan the code with your phone, sign in, then add it to your home screen.', 'marquee' ); ?></p>
				<p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( preg_replace( '#^https?://#', '', $url ) ); ?></code></a></p>
				<p><b><?php esc_html_e( 'Helpers:', 'marquee' ); ?></b> <?php esc_html_e( 'give a family member or friend their own account with the role “Show helper”. They can use the Show Manager, and nothing else in WordPress.', 'marquee' ); ?> <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add a helper', 'marquee' ); ?></a></p>
			</div>
		</div>
	</details>
	<?php
}

/** Features: "Only at the show" under Your Name in Lights. */
function marquee_geo_row( $s ) {
	$mi    = (string) marquee_theme_get( 'names_geo_miles' );
	$mi    = '' !== $mi ? $mi : '0.5';
	$where = '' !== (string) $s['lat'] && '' !== (string) $s['lon'];
	?>
	<div class="mq__feat mq__feat--sub">
		<span>
			<label class="mq__geo-on"><input type="checkbox" name="feat[]" value="names_geo" <?php checked( (bool) marquee_theme_get( 'names_geo' ) ); ?>> <b><?php esc_html_e( 'Only at the show', 'marquee' ); ?></b></label>
			<label class="mq__geo-mi"><?php esc_html_e( 'within', 'marquee' ); ?> <input type="number" name="names_geo_miles" min="0.1" max="50" step="0.1" value="<?php echo esc_attr( $mi ); ?>" class="small-text"> <?php esc_html_e( 'miles of your show', 'marquee' ); ?></label>
			<small><?php esc_html_e( 'When a kid sends a name, the phone asks to share its location. It is only compared with your show’s address and never saved. You and your Show helpers can still send test names from anywhere. 0.5 mile suits most shows; phones can be off by a few hundred feet.', 'marquee' ); ?></small>
			<?php if ( ! $where ) : ?><small class="mq__warn"><?php esc_html_e( 'Set your show’s address in the Location step first: without it, names can be sent from anywhere.', 'marquee' ); ?></small><?php endif; ?>
		</span>
	</div>
	<?php
}
