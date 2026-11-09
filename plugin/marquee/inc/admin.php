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
	wp_enqueue_style( 'marquee-admin', MARQUEE_URL . 'assets/admin.css', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/admin.css' ) );
	wp_enqueue_script( 'marquee-qrcode', MARQUEE_URL . 'core/assets/js/qrcode.js', array(), '2.0.4', true );
	wp_enqueue_script( 'marquee-admin', MARQUEE_URL . 'assets/admin.js', array( 'marquee-qrcode' ), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/admin.js' ), true );
	wp_enqueue_script( 'marquee-demo', MARQUEE_URL . 'assets/demo.js', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/demo.js' ), true );
	wp_enqueue_script( 'marquee-walk', MARQUEE_URL . 'assets/walk.js', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/walk.js' ), true );
	wp_enqueue_script( 'marquee-ai', MARQUEE_URL . 'assets/ai.js', array( 'marquee-admin' ), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/ai.js' ), true );
	wp_enqueue_script( 'marquee-tour', MARQUEE_URL . 'assets/tour.js', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/tour.js' ), true );
	wp_enqueue_script( 'marquee-seqmaker', MARQUEE_URL . 'assets/seqmaker.js', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/seqmaker.js' ), true );
	wp_enqueue_script( 'marquee-seqmaker-ui', MARQUEE_URL . 'assets/seqmaker-ui.js', array( 'marquee-seqmaker', 'marquee-admin' ), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/seqmaker-ui.js' ), true );
	wp_enqueue_script( 'marquee-break', MARQUEE_URL . 'assets/break.js', array( 'marquee-admin' ), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/break.js' ), true );
	wp_localize_script( 'marquee-admin', 'MarqueeAdmin', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'marquee_admin' ),
		'icons' => function_exists( 'ridgeroad_cicon' ) && function_exists( 'ridgeroad_fact_icons' ) ? array_combine( array_keys( ridgeroad_fact_icons() ), array_map( 'ridgeroad_cicon', array_keys( ridgeroad_fact_icons() ) ) ) : array(), // the picture next to each icon picker
	) );
} );

/* Save (normal form post, so it works even without JavaScript) */
add_action( 'admin_post_marquee_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t change these settings.', 'marquee' ) );
	}
	check_admin_referer( 'marquee_save' );
	marquee_save( $_POST ); // phpcs:ignore -- cleaned field by field in marquee_save()
	if ( ! empty( $_POST['remove_logo'] ) && empty( $_FILES['logo']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above
		set_theme_mod( 'custom_logo', '' ); // also clears Marquee's own copy of the setting
		remove_theme_mod( 'custom_logo' );
	}
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
	if ( 10 === ( isset( $_POST['mq_tab'] ) ? absint( $_POST['mq_tab'] ) : 0 ) && ! get_option( 'marquee_tips_seen' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above
		update_option( 'marquee_tips_seen', time(), false ); // saved from the Home page: "Check your visitor tips" is done
	}
	update_option( 'marquee_setup_done', 1 );
	$tab = isset( $_POST['mq_tab'] ) ? absint( $_POST['mq_tab'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked above
	$sub = isset( $_POST['mq_sub'] ) ? sanitize_key( wp_unslash( $_POST['mq_sub'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked above
	wp_safe_redirect( add_query_arg( array( 'page' => 'marquee', 'saved' => $first ? 'setup' : '1' ), admin_url( 'admin.php' ) ) . ( $first ? '' : '#mq-step-' . min( 12, $tab ) . ( 4 === $tab && $sub ? '-' . $sub : '' ) ) );
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
		'kids_show'   => array( __( 'Kids’ Corner', 'marquee' ), __( 'The Kids’ Corner page. Turn each part on or off below.', 'marquee' ) ),
		'names_on'    => array( __( 'Your Name in Lights', 'marquee' ), __( 'Kids send a name to your matrix, projector or TV (needs the FPP script)', 'marquee' ), 'kid' ),
		'lab_on'      => array( __( 'Make a Light Show', 'marquee' ), __( 'The Light Lab: kids light up a house to a song', 'marquee' ), 'kid' ),
		'contest_on'  => array( __( 'Light show contest', 'marquee' ), __( 'Entries, Show Wall and hearts (needs Make a Light Show)', 'marquee' ), 'kid2' ),
		'coloring_on' => array( __( 'Coloring Pages', 'marquee' ), __( 'Pages to print and color (pick which ones later under Kids’ Corner)', 'marquee' ), 'kid' ),
		'games_on'    => array( __( 'Games', 'marquee' ), __( 'Elf names and Christmas trivia', 'marquee' ), 'kid' ),
		'songs_show'  => array( __( 'Favorite song voting', 'marquee' ), __( 'Uses your Remote Falcon song list', 'marquee' ) ),
		'app_auto_on' => array( __( '“The lights are on!” alert', 'marquee' ), __( 'Sent to app users when FPP starts your show (needs https)', 'marquee' ) ),
		'sp_show'     => array( __( 'Sponsors', 'marquee' ), __( 'Thank local businesses that support your show, with their logos (add them later under Home page)', 'marquee' ) ),
		'facts_show'  => array( __( 'Show Facts', 'marquee' ), __( 'A page about your show with its own button at the top: your photo and numbers, fact cards and fan favorites (fill it in later under Show Facts)', 'marquee' ) ),
		'snow'        => array( __( 'Falling snow', 'marquee' ), __( 'Snowflakes on every page (size and amount in Show Control)', 'marquee' ) ),
	);
	$names_url = function_exists( 'ridgeroad_names_key' ) ? home_url( '/' ) : '';
	$sidebar  = array(
		array( __( 'Your show', 'marquee' ), array(
			8 => array( __( 'Start here', 'marquee' ), __( 'Your next step', 'marquee' ), 'admin-home' ),
			0 => array( __( 'Show details', 'marquee' ), __( 'Name, place, dates, hours', 'marquee' ), 'admin-site-alt3' ),
			5 => array( __( 'Features & pages', 'marquee' ), __( 'What visitors see', 'marquee' ), 'screenoptions' ),
			3 => array( __( 'Connections', 'marquee' ), __( 'Remote Falcon, Facebook, Instagram', 'marquee' ), 'admin-links' ),
		) ),
		array( __( 'Your pages', 'marquee' ), array(
			10 => array( __( 'Home page', 'marquee' ), __( 'Every box, top to bottom', 'marquee' ), 'admin-page' ),
			11 => array( __( 'Songs & Videos', 'marquee' ), __( 'Videos and song voting', 'marquee' ), 'format-video' ),
			6  => array( __( 'Kids’ Corner', 'marquee' ), __( 'Coloring, Light Lab, contest, games', 'marquee' ), 'smiley' ),
			4  => array( __( 'Names in Lights', 'marquee' ), __( 'Names on your screens', 'marquee' ), 'megaphone' ),
			12 => array( __( 'Show Facts', 'marquee' ), __( 'Photo, fun facts, fan favorites', 'marquee' ), 'star-filled' ),
		) ),
		array( '', array(
			'look' => array( __( 'Look & colors', 'marquee' ), __( 'Opens Customize', 'marquee' ), 'art' ),
			9      => array( __( 'Help', 'marquee' ), __( 'Support and your phone', 'marquee' ), 'sos' ),
		) ),
	);
	if ( $setup ) { // first run: only the questions, in order
		$sidebar = array( array( __( 'Get set up', 'marquee' ), array(
			0 => $sidebar[0][1][0],
			5 => $sidebar[0][1][5],
			3 => $sidebar[0][1][3],
		) ) );
	} else {
		unset( $sidebar[0][1][5] ); // the switches live on each page's own settings now
	}
	if ( ! $theme ) {
		unset( $sidebar[2][1]['look'] ); // the look is the festive theme's
	}
	?>
	<div class="wrap mq<?php echo $setup ? ' mq--wizard' : ' mq--settings'; ?>" data-mq>
		<header class="mq-top">
			<div class="mq-top__brand">
				<span class="mq-top__mark" aria-hidden="true">✦</span>
				<div>
					<h1><?php echo $setup ? esc_html__( 'Welcome to Marquee', 'marquee' ) : esc_html__( 'Marquee', 'marquee' ); ?></h1>
					<p><?php echo $setup ? esc_html__( 'Answer a few questions and your show’s website is ready. You can change any of this later.', 'marquee' ) : esc_html( $s['show_name'] ? $s['show_name'] : get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
			<p class="mq-top__links">
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php esc_html_e( 'View your site', 'marquee' ); ?></a>
				<?php if ( ! $setup ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rr-control' ) ); ?>"><span class="dashicons dashicons-performance" aria-hidden="true"></span> <?php esc_html_e( 'Show Control', 'marquee' ); ?></a><?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marquee-support' ) ); ?>"><?php esc_html_e( 'Get help', 'marquee' ); ?></a>
			</p>
		</header>
		<hr class="wp-header-end">

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
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mq__wiz" novalidate>
			<input type="hidden" name="action" value="marquee_save">
			<?php wp_nonce_field( 'marquee_save' ); ?>
			<input type="hidden" name="mq_tab" value="<?php echo $setup ? 0 : 8; ?>" data-mq-tab>
			<input type="hidden" name="mq_sub" value="" data-mq-subin>
			<nav class="mq-side" aria-label="<?php esc_attr_e( 'Settings sections', 'marquee' ); ?>">
				<?php
				$mq_i = 0;
				foreach ( $sidebar as $grp ) :
					if ( $grp[0] ) {
						echo '<p class="mq-side__group">' . esc_html( $grp[0] ) . '</p>';
					} else {
						echo '<hr class="mq-side__sep">';
					}
					echo '<ul class="mq__steps">';
					foreach ( $grp[1] as $n => $it ) {
						$mq_i++;
						if ( 'look' === $n ) { // not a page here: it opens the live preview
							printf(
								'<li><a class="mq-side__link" href="%1$s"><span class="mq__ico dashicons dashicons-%2$s" aria-hidden="true"></span><span class="mq-side__n" aria-hidden="true">%5$d</span><span class="mq-side__t"><b>%3$s</b><small>%4$s</small></span></a></li>',
								esc_url( add_query_arg( array( 'autofocus[section]' => 'rr_look', 'return' => rawurlencode( admin_url( 'admin.php?page=marquee' ) ) ), admin_url( 'customize.php' ) ) ), esc_attr( $it[2] ), esc_html( $it[0] ), esc_html( $it[1] ), (int) $mq_i
							);
							continue;
						}
						printf(
							'<li><button type="button" data-step="%1$d"><span class="mq__ico dashicons dashicons-%2$s" aria-hidden="true"></span><span class="mq-side__n" aria-hidden="true">%5$d</span><span class="mq-side__t"><b>%3$s</b><small>%4$s</small></span></button></li>',
							(int) $n, esc_attr( $it[2] ), esc_html( $it[0] ), esc_html( $it[1] ), (int) $mq_i
						);
					}
					echo '</ul>';
				endforeach;
				?>
				<p class="mq-side__foot"><?php esc_html_e( 'Night-of switches are in Show Control, or on your phone in the Show Manager.', 'marquee' ); ?></p>
			</nav>
			<div class="mq__panes">

			<?php if ( ! $setup ) : ?>
			<section class="mq__pane mq-home" data-pane="8">
				<?php marquee_home_view( $s ); ?>
			</section>
			<?php endif; ?>

			<section class="mq__pane" data-pane="0">
				<header class="mq-head">
<p class="mq-head__eyebrow"><?php esc_html_e( 'Your show', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Show details', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'Who you are, where you are and when the lights are on. Every page of your website uses these.', 'marquee' ); ?></p>
				</header>

				<div class="mq-sec" id="mq-sec-show">
<h3 class="mq-sec__title"><?php esc_html_e( 'Name, radio and logo', 'marquee' ); ?></h3>
				<div class="mq__row">
					<p><label for="mq-name"><?php esc_html_e( 'Show name', 'marquee' ); ?></label><input id="mq-name" name="show_name" type="text" value="<?php echo esc_attr( $s['show_name'] ); ?>" required><small><?php esc_html_e( 'Shown in the header, alerts, Google and share previews.', 'marquee' ); ?></small></p>
					<p><label for="mq-short"><?php esc_html_e( 'Short name', 'marquee' ); ?></label><input id="mq-short" name="short_name" type="text" maxlength="12" value="<?php echo esc_attr( $s['short_name'] ); ?>"><small><?php esc_html_e( 'Under the app icon on phones, 12 letters max.', 'marquee' ); ?></small></p>
				</div>
				<div class="mq__row">
					<p><label for="mq-radio"><?php esc_html_e( 'Radio station', 'marquee' ); ?></label><input id="mq-radio" name="radio" type="text" placeholder="88.1 FM" value="<?php echo esc_attr( $tm( 'radio' ) ); ?>"></p>
					<p><label for="mq-listen"><?php esc_html_e( 'Listen on your phone link (optional)', 'marquee' ); ?></label><input id="mq-listen" name="listen_url" type="url" placeholder="https://player.pulsemesh.io" value="<?php echo esc_attr( $tm( 'listen_url' ) ); ?>"><small><?php esc_html_e( 'Adds a “Listen on Your Phone” button under your radio station, for people who would rather hear the music on their phone. Use your PulseMesh link. Leave empty to hide it.', 'marquee' ); ?></small></p>
				</div>
				<?php marquee_field( 'listen_qr' ); ?>
				<?php $logo_id = (int) get_theme_mod( 'custom_logo' ); $logo_src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : ''; ?>
				<div class="mq__logo">
					<label for="mq-logo"><?php esc_html_e( 'Logo (optional)', 'marquee' ); ?></label>
					<div class="mq__logo-row">
						<div class="mq__logo-box" data-mq-logo-box<?php echo $logo_src ? '' : ' hidden'; ?>>
							<img src="<?php echo esc_url( $logo_src ); ?>" alt="<?php esc_attr_e( 'Your logo', 'marquee' ); ?>" data-mq-logo-img>
						</div>
						<div class="mq__logo-pick">
							<input id="mq-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-mq-logo-file>
							<small data-mq-logo-note><?php echo $logo_src ? esc_html__( 'This is your current logo. Choose a file only to replace it.', 'marquee' ) : esc_html__( 'PNG with a see-through background works best.', 'marquee' ); ?></small>
							<?php if ( $logo_src ) : ?>
								<label class="mq__on"><input type="checkbox" name="remove_logo" value="1"> <?php esc_html_e( 'Remove logo (your show name is shown instead)', 'marquee' ); ?></label>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php
				$ico_id = (int) get_option( 'site_icon' );
				$ico_m  = $ico_id ? wp_get_attachment_metadata( $ico_id ) : array();
				$ico_w  = ! empty( $ico_m['width'] ) ? (int) $ico_m['width'] : 0;
				$ico_go = admin_url( 'customize.php?autofocus[section]=title_tagline' );
				?>
				<div class="mq-icon">
					<?php if ( ! $ico_id ) : ?>
						<p><b><?php esc_html_e( 'App icon: not set yet.', 'marquee' ); ?></b> <?php esc_html_e( 'Phones and Google show a plain star until you add one. Pick a square picture, 512 × 512 pixels.', 'marquee' ); ?> <a href="<?php echo esc_url( $ico_go ); ?>"><?php esc_html_e( 'Choose the icon', 'marquee' ); ?></a></p>
					<?php elseif ( $ico_w && $ico_w < 512 ) : ?>
						<p class="is-bad"><img src="<?php echo esc_url( get_site_icon_url( 64 ) ); ?>" alt="" width="32" height="32"> <b><?php /* translators: %d: pixels */ echo esc_html( sprintf( __( 'Your app icon is only %1$d × %1$d pixels, so it looks blurry on phones and in Google.', 'marquee' ), $ico_w ) ); ?></b> <?php esc_html_e( 'Pick a square picture 512 × 512 pixels (WordPress makes the small sizes from it).', 'marquee' ); ?> <a href="<?php echo esc_url( $ico_go ); ?>"><?php esc_html_e( 'Change the icon', 'marquee' ); ?></a></p>
					<?php else : ?>
						<p class="is-ok"><img src="<?php echo esc_url( get_site_icon_url( 64 ) ); ?>" alt="" width="32" height="32"> <b><?php esc_html_e( 'App icon: sharp and ready.', 'marquee' ); ?></b> <?php esc_html_e( 'Used on phones, browser tabs and Google.', 'marquee' ); ?> <a href="<?php echo esc_url( $ico_go ); ?>"><?php esc_html_e( 'Change it', 'marquee' ); ?></a></p>
<?php endif; ?>
				</div>
				<?php marquee_field( 'share_image' ); ?>
				</div>
				<div class="mq-sec" id="mq-sec-location">
				<h3 class="mq-sec__title"><?php esc_html_e( 'Location & weather', 'marquee' ); ?></h3>
				<p><label for="mq-addr"><?php esc_html_e( 'Show address', 'marquee' ); ?></label>
					<span class="mq__inline"><input id="mq-addr" name="address" type="text" value="<?php echo esc_attr( $s['address'] ); ?>" placeholder="123 Main St, Town, ST 12345"><button type="button" class="button" data-mq-lookup><?php esc_html_e( 'Find', 'marquee' ); ?></button></span>
					<small><?php esc_html_e( 'Or paste a Google Maps link to your house. Used for sunset, weather, radar and directions.', 'marquee' ); ?></small></p>
<p class="mq__msg" data-mq-lookup-msg aria-live="polite"></p>
				<?php if ( ! $setup ) { marquee_field( 'parking_note' ); } ?>
				<div class="mq__row mq__row--4">
					<p><label for="mq-lat"><?php esc_html_e( 'Latitude', 'marquee' ); ?></label><input id="mq-lat" name="lat" type="text" inputmode="decimal" value="<?php echo esc_attr( $s['lat'] ); ?>"></p>
					<p><label for="mq-lon"><?php esc_html_e( 'Longitude', 'marquee' ); ?></label><input id="mq-lon" name="lon" type="text" inputmode="decimal" value="<?php echo esc_attr( $s['lon'] ); ?>"></p>
					<p><label for="mq-tz"><?php esc_html_e( 'Time zone', 'marquee' ); ?></label><select id="mq-tz" name="tz"><?php echo wp_timezone_choice( $s['tz'], determine_locale() ); // phpcs:ignore ?></select></p>
					<p><label for="mq-radar"><?php esc_html_e( 'Radar station', 'marquee' ); ?></label><input id="mq-radar" name="radar_station" type="text" maxlength="4" value="<?php echo esc_attr( $tm( 'radar_station' ) ); ?>"><small><?php esc_html_e( 'US only, filled in by Find. Outside the US a Windy.com radar map is used instead.', 'marquee' ); ?></small></p>
					<input id="mq-office" name="nws_office" type="hidden" value="<?php echo esc_attr( $s['nws_office'] ); ?>">
				</div>
				<div class="mq__row mq__row--4">
					<p><label for="mq-city"><?php esc_html_e( 'Town', 'marquee' ); ?></label><input id="mq-city" name="city" type="text" value="<?php echo esc_attr( $s['city'] ); ?>"></p>
					<p><label for="mq-region"><?php esc_html_e( 'State', 'marquee' ); ?></label><input id="mq-region" name="region" type="text" value="<?php echo esc_attr( $s['region'] ); ?>"></p>
					<p><label for="mq-zip"><?php esc_html_e( 'ZIP / postcode', 'marquee' ); ?></label><input id="mq-zip" name="zip" type="text" value="<?php echo esc_attr( $s['zip'] ); ?>"></p>
					<p><label for="mq-units"><?php esc_html_e( 'Weather units', 'marquee' ); ?></label><select id="mq-units" name="units"><option value="us" <?php selected( $s['units'], 'us' ); ?>>°F, mph</option><option value="metric" <?php selected( $s['units'], 'metric' ); ?>>°C, km/h</option></select></p>
				</div>
				<div class="mq__row mq__row--4">
					<p><label for="mq-country"><?php esc_html_e( 'Country', 'marquee' ); ?></label><input id="mq-country" name="country" type="text" maxlength="2" list="mq-countries" placeholder="US" value="<?php echo esc_attr( $s['country'] ); ?>" data-mq-country style="text-transform:uppercase"><small><?php esc_html_e( 'Two-letter code, filled in by Find.', 'marquee' ); ?></small></p>
					<datalist id="mq-countries">
						<?php
						foreach ( array( 'US' => 'United States', 'AU' => 'Australia', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'ZA' => 'South Africa', 'MX' => 'Mexico', 'DE' => 'Germany', 'NL' => 'Netherlands' ) as $cc => $cn ) {
							printf( '<option value="%s" label="%s"></option>', esc_attr( $cc ), esc_attr( $cn ) );
						}
						?>
					</datalist>
					<p class="mq__wide"><span class="mq__fine"><?php echo 'US' === strtoupper( (string) $s['country'] ) || '' === (string) $s['country']
						? esc_html__( 'Weather: forecast from Open-Meteo, radar and forecast links from the National Weather Service.', 'marquee' )
						: esc_html__( 'Weather: forecast from Open-Meteo, plus a live Windy.com radar map and “View Full Weather Forecast” button for your location. Pick °C, km/h above for metric.', 'marquee' ); ?></span></p>
				</div>
				</div>
				<div class="mq-sec" id="mq-sec-schedule">
				<h3 class="mq-sec__title"><?php esc_html_e( 'Schedule', 'marquee' ); ?></h3>
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
				<?php if ( ! $setup ) : ?>
					<div class="mq__row mq__row--3">
						<?php marquee_field( 'weeknight_label' ); marquee_field( 'weekend_label' ); marquee_field( 'start_offset' ); ?>
					</div>
				<?php endif; ?>
				</div>
				<?php marquee_langs_box( $s ); ?>
			</section>

<?php if ( $setup ) : ?>
			<section class="mq__pane" data-pane="5">
				<input type="hidden" name="features_sent" value="1">
				<header class="mq-head">
					<p class="mq-head__eyebrow"><?php esc_html_e( 'Get set up', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Features & pages', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'Turn on the parts of your website you want visitors to see. You can change these any time.', 'marquee' ); ?></p>
				</header>

				<?php
				foreach ( $feat as $k => $f ) {
$on = 'snow' === $k ? 'off' !== $tm( 'snow_amount' ) : (bool) $tm( $k );
					echo '<input type="hidden" name="feat_keys[]" value="' . esc_attr( $k ) . '">';
					printf(
						'<label class="mq__feat%s"><span><b>%s</b><small>%s</small></span><input type="checkbox" name="feat[]" value="%s" %s></label>',
						isset( $f[2] ) ? ' mq__feat--' . esc_attr( $f[2] ) : '', esc_html( $f[0] ), esc_html( $f[1] ), esc_attr( $k ), checked( $on, true, false )
					);
					if ( 'names_on' === $k ) {
						marquee_geo_row( $s );
					}
				}
				?>
<?php marquee_make_pages_row( $setup ); ?>
			</section>
			<?php endif; ?>

<?php if ( ! $setup ) : ?>
			<section class="mq__pane" data-pane="4" data-names-mode="<?php echo esc_attr( 'live' === $s['names_mode'] ? 'live' : 'break' ); ?>">
				<header class="mq-head">
<p class="mq-head__eyebrow"><?php esc_html_e( 'Your pages', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Names in Lights', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'Kids type their first name on your website and your show puts it up on your screens. Go through the steps in order; the panel below tells you what’s left.', 'marquee' ); ?></p>
				</header>

				<?php if ( ! $names_url ) : ?>
					<p class="mq__fine"><?php esc_html_e( 'Your Name in Lights isn’t available with this theme.', 'marquee' ); ?></p>
				<?php else : ?>
					<?php
					$seen   = (int) get_option( 'ridgeroad_names_seen' );
					$fpp_ok = $seen && time() - $seen < 15 * MINUTE_IN_SECONDS;
					$v_on   = function_exists( 'marquee_voice_on' ) && marquee_voice_on();
					$brk    = function_exists( 'marquee_break_info' ) ? marquee_break_info() : null;
marquee_switch( 'names_on', __( 'Your Name in Lights', 'marquee' ), __( 'Kids send a name to your matrix, projector or TV (needs the FPP script). Its door shows in the Kids’ Corner.', 'marquee' ), '', 'mq__feat--page' );
marquee_geo_row( $s );
					marquee_field( 'names_always', array( 'class' => 'mq-f--sub' ) );
					if ( ! marquee_theme_get( 'names_on' ) ) {
						echo '<div class="mq__note">' . esc_html__( 'Your Name in Lights is switched off, so kids can’t send names yet. Turn it on above when you’re ready.', 'marquee' ) . '</div>';
					}
					if ( function_exists( 'marquee_names_setup_panel' ) ) {
						marquee_names_setup_panel();
					}
					?>
					<?php
					$mq_subs = array(
						'how'     => array( __( 'How names go up', 'marquee' ), '' ),
						'fpp'     => array( __( 'Connect your FPP', 'marquee' ), $fpp_ok ? 'ok' : ( $seen ? 'warn' : '' ) ),
						'screens' => array( __( 'Screens', 'marquee' ), ! empty( $s['fpp_screens'] ) ? 'ok' : '' ),
						'break'   => array( __( 'Names break', 'marquee' ), $brk ? 'ok' : '' ),
						'voice'   => array( __( 'Voice', 'marquee' ), $v_on ? 'ok' : '' ),
						'extras'  => array( __( 'Messages & pictures', 'marquee' ), '' ),
					);
					$mq_keys = array_keys( $mq_subs );
					$mq_next = function ( $k ) use ( $mq_subs, $mq_keys ) {
						$i = array_search( $k, $mq_keys, true );
						if ( false === $i || ! isset( $mq_keys[ $i + 1 ] ) ) {
							return;
						}
						$n = $mq_keys[ $i + 1 ];
						/* translators: %s: name of the next step */
						printf( '<p class="mq-sub__next"><button type="button" class="button" data-mq-sub-go="%s">%s &rarr;</button></p>', esc_attr( $n ), esc_html( sprintf( __( 'Next: %s', 'marquee' ), $mq_subs[ $n ][0] ) ) );
					};
					?>
					<ol class="mq-subnav" role="tablist" aria-label="<?php esc_attr_e( 'Names in Lights steps', 'marquee' ); ?>">
						<?php $mq_n = 0; foreach ( $mq_subs as $k => $sub ) : $mq_n++; ?>
							<li><button type="button" role="tab" data-mq-sub="<?php echo esc_attr( $k ); ?>" class="<?php echo $sub[1] ? 'is-' . esc_attr( $sub[1] ) : ''; ?><?php echo in_array( $k, array( 'break', 'voice' ), true ) ? ' mq-only-break' : ''; ?>"><span class="mq-subnav__n" aria-hidden="true"><?php echo 'ok' === $sub[1] ? '✓' : (int) $mq_n; ?></span><?php echo esc_html( $sub[0] ); ?></button></li>
						<?php endforeach; ?>
					</ol>

					<div class="mq-sub" data-sub="how" role="tabpanel">

					<?php marquee_demo( 'names', $s ); ?>
					<div class="mq-how">
						<h3><?php esc_html_e( 'How it works', 'marquee' ); ?></h3>
						<p><?php esc_html_e( 'Marquee doesn’t change your show. Pick how the names go up:', 'marquee' ); ?></p>
						<div class="mq-how__modes" role="radiogroup" aria-label="<?php esc_attr_e( 'How names go up', 'marquee' ); ?>">
							<label class="mq-how__mode">
								<input type="radio" name="names_mode" value="break" <?php checked( 'live' !== $s['names_mode'] ); ?>>
								<span class="mq-how__card">
									<b><?php esc_html_e( 'Between songs: a names break', 'marquee' ); ?> <em><?php esc_html_e( 'Recommended', 'marquee' ); ?></em></b>
									<span class="mq-flow" aria-hidden="true"><span class="mq-flow__song">🎵 <?php esc_html_e( 'Your song', 'marquee' ); ?></span><i>→</i><span class="mq-flow__break">⭐ <?php esc_html_e( 'Names break', 'marquee' ); ?><small><?php esc_html_e( 'about 30 seconds', 'marquee' ); ?></small></span><i>→</i><span class="mq-flow__song">🎵 <?php esc_html_e( 'Next song', 'marquee' ); ?></span></span>
									<small><?php esc_html_e( 'When kids have sent names, your FPP slips a short “names break” sequence in after the current song: the names go up on your screens one by one (and a voice can say them), then your playlist carries on. No names waiting? Nothing changes. Your show needs to be playing as a playlist on the FPP that runs the names script. You need one short extra sequence for the break; Marquee can make it for you (step 4, Names break).', 'marquee' ); ?></small>
								</span>
							</label>
							<label class="mq-how__mode">
								<input type="radio" name="names_mode" value="live" <?php checked( 'live' === $s['names_mode'] ); ?>>
								<span class="mq-how__card">
									<b><?php esc_html_e( 'During songs, on a spare screen', 'marquee' ); ?></b>
									<span class="mq-flow" aria-hidden="true"><span class="mq-flow__song is-wide">🎵 <?php esc_html_e( 'Your show keeps playing', 'marquee' ); ?><small><?php esc_html_e( '+ names on a spare TV or projector', 'marquee' ); ?></small></span></span>
									<small><?php esc_html_e( 'For a TV or projector your sequences don’t use: names appear on it while your show plays. No extra sequence to make. Names aren’t spoken (it would talk over your music) and kids don’t pick a themed sequence.', 'marquee' ); ?></small>
								</span>
							</label>
						</div>
					</div>
					<div class="mq__row mq-only-live">
					<p><label for="mq-lsf"><?php esc_html_e( 'Seconds each name stays up', 'marquee' ); ?></label><input id="mq-lsf" name="live_show_for" type="number" min="3" max="20" step="0.5" value="<?php echo esc_attr( $s['live_show_for'] ); ?>"><small><?php esc_html_e( 'Names go up one after another while your show plays, within about 20 seconds of being sent.', 'marquee' ); ?></small></p>
					</div>
					<?php
					$mq_pls  = get_option( 'ridgeroad_fpp' );
					$mq_pls  = is_array( $mq_pls ) && ! empty( $mq_pls['playlists'] ) ? (array) $mq_pls['playlists'] : array();
					$mq_brk  = mb_strtolower( $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights' );
					$mq_pls  = array_values( array_filter( $mq_pls, function ( $p ) use ( $mq_brk ) {
						$l = mb_strtolower( (string) $p );
						return $l !== $mq_brk && $l !== $mq_brk . ' 1' && $l !== $mq_brk . ' 2';
					} ) );
					$mq_on   = array_map( 'mb_strtolower', (array) $s['names_playlists'] );
					$mq_more = array_values( array_filter( (array) $s['names_playlists'], function ( $p ) use ( $mq_pls ) {
						return ! in_array( mb_strtolower( $p ), array_map( 'mb_strtolower', $mq_pls ), true );
					} ) );
					?>
					<fieldset class="mq-playlists" data-mq-only>
						<legend><?php esc_html_e( 'Only during these playlists', 'marquee' ); ?></legend>
						<?php if ( $mq_pls ) : ?>
							<div class="mq-playlists__list">
							<?php foreach ( $mq_pls as $p ) : ?>
								<label><input type="checkbox" name="names_playlists[]" value="<?php echo esc_attr( $p ); ?>" <?php checked( in_array( mb_strtolower( $p ), $mq_on, true ) ); ?>> <?php echo esc_html( $p ); ?></label>
							<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<p><label for="mq-npl-more"><?php echo $mq_pls ? esc_html__( 'Other playlists (comma separated)', 'marquee' ) : esc_html__( 'Playlist names (comma separated)', 'marquee' ); ?></label><input id="mq-npl-more" name="names_playlists_more" type="text" value="<?php echo esc_attr( implode( ', ', $mq_more ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Show', 'marquee' ); ?>"></p>
						<small><?php esc_html_e( 'Leave everything blank to put names up during any playlist. Pick your main show playlist if you also run something like a static or background playlist: names then wait until the show playlist plays.', 'marquee' ); ?><?php if ( ! $mq_pls ) : ?> <?php esc_html_e( 'Your FPP playlists show up here as checkboxes once the names script has checked in.', 'marquee' ); ?><?php endif; ?></small>
					</fieldset>
						<?php $mq_next( 'how' ); ?>
					</div>

					<div class="mq-sub" data-sub="fpp" role="tabpanel" data-mq-card="fpp">
					<p class="mq__fine"><?php esc_html_e( 'Put the Marquee plugin on your FPP and paste your connection code (below). Your website never connects into your house: the FPP checks in with it.', 'marquee' ); ?></p>
					<?php marquee_tour_button( 'fpp' ); ?>
					<?php marquee_names_status(); ?>
					<input type="hidden" name="fpp_sent" value="1">
					<div class="mq__fppplug" data-mq-fppplug>
						<h4><?php esc_html_e( 'Put it on your FPP: the Marquee plugin (easiest)', 'marquee' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'On the FPP that plays your show: Status/Control → Settings → UI → User Interface Level: Developer (FPP only takes a plugin address at that level). Then Content Setup → Plugin Manager, paste this address in the Find a Plugin box and click Install:', 'marquee' ); ?>
								<span class="mq__copyrow"><input type="text" readonly value="<?php echo esc_attr( MARQUEE_FPP_PLUGIN_INFO ); ?>" data-mq-copysrc aria-label="<?php esc_attr_e( 'Plugin address', 'marquee' ); ?>"><button type="button" class="button" data-mq-copy><?php esc_html_e( 'Copy', 'marquee' ); ?></button></span></li>
							<li><?php esc_html_e( 'Click Install. Then open Content Setup → Marquee and paste your connection code:', 'marquee' ); ?>
								<?php if ( function_exists( 'marquee_is_support_user' ) && marquee_is_support_user() ) : ?>
									<em><?php esc_html_e( '(hidden during a support session: it contains your private key)', 'marquee' ); ?></em>
								<?php else : ?>
									<span class="mq__copyrow"><input type="text" readonly value="<?php echo esc_attr( marquee_fpp_code() ); ?>" data-mq-copysrc aria-label="<?php esc_attr_e( 'Connection code', 'marquee' ); ?>"><button type="button" class="button" data-mq-copy><?php esc_html_e( 'Copy', 'marquee' ); ?></button></span>
								<?php endif; ?></li>
							<li><?php esc_html_e( 'Click Connect. Within a minute the setup check shows here. No Command Presets, no files to upload, and when you change your screens here the FPP picks it up by itself.', 'marquee' ); ?></li>
						</ol>
						<small><?php esc_html_e( 'The connection code contains your private key, so keep it private.', 'marquee' ); ?></small>
					</div>
					<details class="mq__byhand">
						<summary><?php esc_html_e( 'Or put the script on your FPP by hand', 'marquee' ); ?></summary>
						<p class="mq__media-btns">
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=marquee_names_json' ), 'marquee_names_json' ) ); ?>"><?php esc_html_e( 'Download marquee_names.json', 'marquee' ); ?></a>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=marquee_names_py' ), 'marquee_names_py' ) ); ?>"><?php esc_html_e( 'Download marquee_names.py', 'marquee' ); ?></a>
						<a class="button-link" href="<?php echo esc_url( MARQUEE_URL . 'core/fpp/marquee-names-setup-guide.txt' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Setup guide', 'marquee' ); ?></a>
					</p>
						<small><?php esc_html_e( 'Save first if you changed anything above; the settings file is made from the saved values. It contains your private key, so keep it private.', 'marquee' ); ?></small>
					</details>
						<?php $mq_next( 'fpp' ); ?>
					</div>

					<div class="mq-sub" data-sub="screens" role="tabpanel">
						<p class="mq__fine"><?php esc_html_e( 'One row per screen the names go on: your matrix, TV or projector. Save when you’re done; your FPP picks up the change by itself.', 'marquee' ); ?></p>
					<?php $mq_models = function_exists( 'marquee_fpp_models' ) ? marquee_fpp_models() : null; ?>
					<table class="mq__screens" id="mq-screens">
						<thead><tr><th><?php esc_html_e( 'Screen', 'marquee' ); ?></th><th><?php esc_html_e( 'FPP address', 'marquee' ); ?></th><th><?php esc_html_e( 'Overlay model name', 'marquee' ); ?></th></tr></thead>
						<tbody>
						<?php
						$scr = array_values( (array) $s['fpp_screens'] );
						for ( $i = 0; $i < 4; $i++ ) :
							$r = isset( $scr[ $i ] ) ? $scr[ $i ] : array( 'name' => '', 'fpp' => '', 'model' => '' );
							?>
							<tr class="mq__scr-main">
								<td><input type="text" name="fpp_screens[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $r['name'] ); ?>" placeholder="<?php echo 0 === $i ? esc_attr__( 'Matrix', 'marquee' ) : ''; ?>" aria-label="<?php esc_attr_e( 'Screen name', 'marquee' ); ?>"></td>
								<td><input type="text" name="fpp_screens[<?php echo (int) $i; ?>][fpp]" value="<?php echo esc_attr( $r['fpp'] ); ?>" placeholder="<?php echo 0 === $i ? '127.0.0.1' : '192.168.1.'; ?>" aria-label="<?php esc_attr_e( 'FPP address', 'marquee' ); ?>"></td>
								<td>
									<?php
									$mq_pick = is_array( $mq_models ) && $mq_models && marquee_screen_is_local( $r['fpp'] );
									$mq_in   = $mq_pick && in_array( $r['model'], wp_list_pluck( $mq_models, 'name' ), true );
									if ( $mq_pick ) :
										?>
										<select data-mq-model-pick aria-label="<?php esc_attr_e( 'Overlay model', 'marquee' ); ?>">
											<option value=""><?php esc_html_e( '— Pick a screen —', 'marquee' ); ?></option>
											<?php
											$mq_groups = array(
												__( 'Screens', 'marquee' )      => array_filter( $mq_models, function ( $mm ) { return ! empty( $mm['screen'] ); } ),
												__( 'Other models', 'marquee' ) => array_filter( $mq_models, function ( $mm ) { return empty( $mm['screen'] ); } ),
											);
											$mq_split  = $mq_groups[ __( 'Screens', 'marquee' ) ] && $mq_groups[ __( 'Other models', 'marquee' ) ];
											foreach ( $mq_groups as $mq_glabel => $mq_gmodels ) :
												if ( ! $mq_gmodels ) {
													continue;
												}
												echo $mq_split ? '<optgroup label="' . esc_attr( $mq_glabel ) . '">' : '';
												foreach ( $mq_gmodels as $mm ) :
													?>
												<option value="<?php echo esc_attr( $mm['name'] ); ?>" <?php selected( $r['model'], $mm['name'] ); ?>><?php echo esc_html( $mm['name'] . ( $mm['w'] ? sprintf( ' (%d × %d)', $mm['w'], $mm['h'] ) : '' ) ); ?></option>
													<?php
												endforeach;
												echo $mq_split ? '</optgroup>' : '';
											endforeach;
											?>
											<?php if ( '' !== $r['model'] && ! $mq_in ) : ?>
												<option value="<?php echo esc_attr( $r['model'] ); ?>" selected><?php /* translators: %s: model name */ echo esc_html( sprintf( __( '%s (not on your FPP)', 'marquee' ), $r['model'] ) ); ?></option>
											<?php endif; ?>
											<option value="__type"><?php esc_html_e( 'Type a name…', 'marquee' ); ?></option>
										</select>
									<?php endif; ?>
									<input type="text" name="fpp_screens[<?php echo (int) $i; ?>][model]" value="<?php echo esc_attr( $r['model'] ); ?>" placeholder="<?php echo 0 === $i ? esc_attr__( 'MatrixText', 'marquee' ) : ''; ?>" aria-label="<?php esc_attr_e( 'Overlay model name', 'marquee' ); ?>" <?php echo $mq_pick ? 'data-mq-model-typed hidden' : ''; ?>>
								</td>
							</tr>
							<tr class="mq__scr-look">
								<td colspan="3">
									<?php
									$look = array(
										'style' => array( __( 'Text', 'marquee' ), array( 'auto' => __( 'Automatic', 'marquee' ), 'still' => __( 'Still', 'marquee' ), 'scroll' => __( 'Scrolling', 'marquee' ) ) ),
										'size'  => array( __( 'Size', 'marquee' ), array( 'auto' => __( 'Automatic', 'marquee' ), 's' => __( 'Small', 'marquee' ), 'm' => __( 'Medium', 'marquee' ), 'l' => __( 'Large', 'marquee' ), 'xl' => __( 'Fill the screen', 'marquee' ), 'custom' => __( 'Custom…', 'marquee' ) ) ),
										'scale' => array( __( 'Bigger or smaller', 'marquee' ), array( '100' => __( 'As picked', 'marquee' ), '60' => __( 'Smaller', 'marquee' ), '80' => __( 'A little smaller', 'marquee' ), '125' => __( 'A little bigger', 'marquee' ), '150' => __( 'Bigger', 'marquee' ), '200' => __( 'Biggest', 'marquee' ) ) ),
										'speed' => array( __( 'Scroll speed', 'marquee' ), array( 'normal' => __( 'Normal', 'marquee' ), 'slow' => __( 'Slow', 'marquee' ), 'fast' => __( 'Fast', 'marquee' ) ) ),
									);
									foreach ( $look as $k => $l ) :
										$cur_v = isset( $r[ $k ] ) ? (string) $r[ $k ] : (string) key( $l[1] );
										?>
										<label><?php echo esc_html( $l[0] ); ?>
											<select name="fpp_screens[<?php echo (int) $i; ?>][<?php echo esc_attr( $k ); ?>]">
												<?php foreach ( $l[1] as $v => $t ) : ?><option value="<?php echo esc_attr( $v ); ?>" <?php selected( $cur_v, $v ); ?>><?php echo esc_html( $t ); ?></option><?php endforeach; ?>
											</select>
										</label>
										<?php if ( 'size' === $k ) : ?>
											<label class="mq__scr-px" data-mq-px <?php echo 'custom' === $cur_v ? '' : 'hidden'; ?>><?php esc_html_e( 'Font size', 'marquee' ); ?>
												<input type="number" min="4" max="400" step="1" name="fpp_screens[<?php echo (int) $i; ?>][px]" value="<?php echo esc_attr( ! empty( $r['px'] ) ? (int) $r['px'] : '' ); ?>" placeholder="8" title="<?php esc_attr_e( 'About how many pixels tall the letters are', 'marquee' ); ?>">
											</label>
										<?php endif; ?>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endfor; ?>
						</tbody>
					</table>
					<small><?php echo is_array( $mq_models ) && $mq_models ? esc_html__( 'Pick your screen from the list: those are the Pixel Overlay Models on the FPP running the plugin. For a screen on a different FPP, type that FPP’s address and the model name. Not in the list? Upload your models to the FPP with FPP Connect (Models ticked).', 'marquee' ) : esc_html__( 'FPP address: the FPP that drives that screen (127.0.0.1 means the FPP running the script). Overlay model: its name under FPP → Input/Output Setup → Pixel Overlay Models.', 'marquee' ); ?></small>
					<small class="mq__fine"><?php esc_html_e( 'Text and Size: Automatic works for most screens. Pixel matrices (up to 256 pixels tall) get one big line that scrolls across when the message won’t fit; TVs and projectors get still text with the name on its own line. Bigger or smaller makes the letters bigger or smaller than that, but still text never goes past the edges of the screen (for even bigger letters, use Scrolling). Scrolling text always crosses the screen in the time each name is up; Slow may not quite finish on long messages. Custom size: the font size FPP uses, about how many pixels tall the letters are (a 10-pixel-tall matrix looks good at 8 or 9). Still text goes on one, two or three centered lines (the name on its own line after the comma), whichever lets the letters be biggest without going over that size; scrolling text keeps it.', 'marquee' ); ?></small>
						<?php $mq_next( 'screens' ); ?>
					</div>

					<div class="mq-sub" data-sub="break" role="tabpanel">
					<p class="mq__note mq-only-live"><?php esc_html_e( 'Spoken names, the names break audio and themed sequences work with the names break. Pick “Between songs” above to use them.', 'marquee' ); ?></p>
					<div class="mq__row mq-only-break">
					<p><label for="mq-fpl"><?php esc_html_e( 'FPP playlist with your names break', 'marquee' ); ?></label><input id="mq-fpl" name="fpp_playlist" type="text" value="<?php echo esc_attr( $s['fpp_playlist'] ); ?>"><small><?php esc_html_e( 'A short sequence (about 30 seconds) in its own playlist, with your screens left dark where the names go. Don’t have one? The Sequence maker (below) makes it for you, or leave “Make a simple names break for me” on.', 'marquee' ); ?></small></p>
					<p><label for="mq-nev"><?php esc_html_e( 'Names break after every', 'marquee' ); ?></label><select id="mq-nev" name="names_every">
						<?php foreach ( array( 1, 2, 3, 4, 5, 6 ) as $mq_n ) : ?>
							<option value="<?php echo (int) $mq_n; ?>" <?php selected( (int) $s['names_every'], $mq_n ); ?>><?php echo esc_html( 1 === $mq_n ? __( 'song (standard)', 'marquee' ) : sprintf( /* translators: %d: number of songs */ __( '%d songs', 'marquee' ), $mq_n ) ); ?></option>
						<?php endforeach; ?>
					</select><small><?php esc_html_e( 'Names collect while your show plays and go up together after that many songs, so a busy night doesn’t put a break between every song. If 6 names are waiting, the break comes sooner.', 'marquee' ); ?></small></p>
					</div>
					<p><label for="mq-npp"><?php esc_html_e( 'Names each phone can send', 'marquee' ); ?></label><select id="mq-npp" name="names_per_phone">
						<?php foreach ( array( 1, 2, 3, 4, 5 ) as $mq_n ) : ?>
							<option value="<?php echo (int) $mq_n; ?>" <?php selected( (int) ( $s['names_per_phone'] ?? 3 ), $mq_n ); ?>><?php echo esc_html( sprintf( /* translators: %d: names */ _n( '%d an hour', '%d an hour', $mq_n, 'marquee' ), $mq_n ) . ( 3 === $mq_n ? ' ' . __( '(standard)', 'marquee' ) : '' ) ); ?></option>
						<?php endforeach; ?>
					</select><small><?php esc_html_e( 'So one family can’t fill a whole names break. You skip this limit while you’re logged in, for testing.', 'marquee' ); ?></small></p>
					<label class="mq__feat mq-only-break"><span><b><?php esc_html_e( 'Make a simple names break for me', 'marquee' ); ?></b><small><?php esc_html_e( 'If your FPP has no names break playlist yet, Marquee makes one: the show pauses, the names go up on your screens (with the names break audio below, if you build it), then your show carries on. No sequence needed. A names break playlist you already have is never touched.', 'marquee' ); ?></small></span><input type="checkbox" name="names_simple" value="1" <?php checked( ! empty( $s['names_simple'] ) ); ?>></label>
					<details class="mq__adv mq-only-break">
						<summary><?php esc_html_e( 'Timing (you don’t normally need these)', 'marquee' ); ?></summary>
						<p class="mq__fine"><?php esc_html_e( 'Building the names break audio sets these for you, and with the simple names break each name gets the time it needs by itself. Change them only for your own names sequence, to match where its audio goes quiet.', 'marquee' ); ?></p>
						<div class="mq__row">
						<p><label for="mq-nst"><?php esc_html_e( 'When the first name appears', 'marquee' ); ?> <span class="mq__unit"><?php esc_html_e( 'seconds into the break', 'marquee' ); ?></span></label><input id="mq-nst" name="names_start" type="number" min="0" max="120" step="0.5" value="<?php echo esc_attr( $s['names_start'] ); ?>"><small><?php esc_html_e( 'Usually right after the announcer’s intro ends (“It’s time for Names in Lights!”).', 'marquee' ); ?></small></p>
						<p><label for="mq-nsl"><?php esc_html_e( 'Time between names', 'marquee' ); ?> <span class="mq__unit"><?php esc_html_e( 'seconds', 'marquee' ); ?></span></label><input id="mq-nsl" name="names_slot" type="number" min="2.5" max="10" step="0.05" value="<?php echo esc_attr( $s['names_slot'] ); ?>"><small><?php esc_html_e( 'How long each name gets before the next one goes up (up to 6 names per break).', 'marquee' ); ?></small></p>
						<p><label for="mq-npb"><?php esc_html_e( 'Most names per break', 'marquee' ); ?></label><select id="mq-npb" name="names_per_break">
							<?php foreach ( array( 6, 5, 4, 3, 2, 1 ) as $mq_n ) : ?>
								<option value="<?php echo (int) $mq_n; ?>" <?php selected( (int) $s['names_per_break'], $mq_n ); ?>><?php echo esc_html( 6 === $mq_n ? __( '6 (standard)', 'marquee' ) : (string) $mq_n ); ?></option>
							<?php endforeach; ?>
						</select><small><?php esc_html_e( 'Names that need longer to read already get fewer, longer turns by themselves. Pick a lower number to keep breaks short; any extra names go up in the next break.', 'marquee' ); ?></small></p>
						</div>
						<?php marquee_name_timeline( '#mq-nst', '#mq-nsl', (float) $s['break_seq'] ); ?>
					</details>
					<details class="mq__card mq-only-break" <?php echo ( $v_on && ! $brk ) ? 'open' : ''; ?>>
						<summary>
							<span class="mq__card-n">3</span>
							<span class="mq__card-t"><b><?php esc_html_e( 'Names break audio', 'marquee' ); ?></b><small><?php esc_html_e( 'What your radio plays during the names break: an intro, soft music under the names, and an outro, made for you', 'marquee' ); ?></small></span>
							<?php if ( $brk ) : ?><span class="mq__pill is-ok"><?php esc_html_e( 'Built', 'marquee' ); ?></span><?php else : ?><span class="mq__pill"><?php esc_html_e( 'Not built', 'marquee' ); ?></span><?php endif; ?>
						</summary>
						<div class="mq__card-b">
							<?php marquee_tour_button( 'break' ); ?>
							<?php marquee_break_box( $s ); ?>
						</div>
					</details>

					<details class="mq__card mq-only-break" data-mq-card="seq">
						<summary>
							<span class="mq__card-n">4</span>
							<span class="mq__card-t"><b><?php esc_html_e( 'Sequence maker', 'marquee' ); ?></b><small><?php esc_html_e( 'Makes the xLights files for your names break and themes, from your layout. Then render them and send them to your FPP.', 'marquee' ); ?></small></span>
							<span class="mq__pill"><?php esc_html_e( 'Optional', 'marquee' ); ?></span>
						</summary>
						<div class="mq__card-b">
							<?php marquee_tour_button( 'seqmaker' ); ?>
							<?php marquee_seqmaker_box( $s ); ?>
						</div>
					</details>

					<?php $th_on = ! empty( $s['themes_on'] ) && ! empty( $s['themes'] ); ?>
					<details class="mq__card mq-only-break">
						<summary>
							<span class="mq__card-n">5</span>
							<span class="mq__card-t"><b><?php esc_html_e( 'Themes kids can pick', 'marquee' ); ?></b><small><?php esc_html_e( 'Which themes kids see on your website (Candy Cane Swirl, Snowfall…) and the FPP playlist for each. Make the sequences first with the Sequence maker.', 'marquee' ); ?></small></span>
							<?php if ( $th_on ) : ?><span class="mq__pill is-ok"><?php /* translators: %d: number of themes */ echo esc_html( sprintf( _n( '%d theme', '%d themes', count( $s['themes'] ), 'marquee' ), count( $s['themes'] ) ) ); ?></span><?php else : ?><span class="mq__pill"><?php esc_html_e( 'Off', 'marquee' ); ?></span><?php endif; ?>
						</summary>
						<div class="mq__card-b">
							<?php marquee_tour_button( 'themes' ); ?>
							<?php marquee_themes_box( $s ); ?>
						</div>
					</details>
						<?php $mq_next( 'break' ); ?>
					</div>

					<div class="mq-sub" data-sub="voice" role="tabpanel">
					<p class="mq__note mq-only-live"><?php esc_html_e( 'Spoken names, the names break audio and themed sequences work with the names break. Pick “Between songs” above to use them.', 'marquee' ); ?></p>
						<div class="mq-only-break">
						<div class="mq-subhead">
							<span><b><?php esc_html_e( 'Say each name out loud', 'marquee' ); ?></b> <small><?php esc_html_e( 'An ElevenLabs voice (Santa, if you like) reads each name on your radio station', 'marquee' ); ?></small></span>
							<?php if ( $v_on ) : ?><span class="mq__pill is-ok"><?php echo esc_html( $s['voice_name'] ? $s['voice_name'] : __( 'On', 'marquee' ) ); ?></span><?php else : ?><span class="mq__pill"><?php esc_html_e( 'Off', 'marquee' ); ?></span><?php endif; ?>
						</div>
					<p class="mq__fine"><?php esc_html_e( 'An ElevenLabs voice reads each name over your radio station as it goes up on the screens: “Merry Christmas, Emma!”. Use the same voice as your show announcer and it sounds like part of the show.', 'marquee' ); ?></p>
					<input type="hidden" name="voice_sent" value="1">
					<?php marquee_tour_button( 'voice' ); ?>
					<?php
					$verr = get_option( 'marquee_voice_error' );
					if ( $s['voice_on'] && is_array( $verr ) && ! empty( $verr['msg'] ) ) {
						/* translators: 1: time ago, 2: error message */
						echo '<div class="mq__fppstat"><p class="is-bad">' . esc_html( sprintf( __( 'The last spoken name didn’t work (%1$s ago): %2$s', 'marquee' ), human_time_diff( (int) $verr['t'] ), $verr['msg'] ) ) . '</p></div>';
					}
					?>
					<label class="mq__feat"><span><b><?php esc_html_e( 'Speak the names', 'marquee' ); ?></b><small><?php esc_html_e( 'Needs an ElevenLabs account and the names script 3.1 or newer on your FPP.', 'marquee' ); ?></small></span><input type="checkbox" name="voice_on" value="1" <?php checked( (bool) $s['voice_on'] ); ?>></label>
					<div class="mq__row">
						<p><label for="mq-vkey"><?php esc_html_e( 'ElevenLabs API key', 'marquee' ); ?></label><input id="mq-vkey" name="voice_key" type="password" autocomplete="new-password" value="<?php echo $s['voice_key'] ? '••••••••' : ''; ?>"><small><?php esc_html_e( 'ElevenLabs → Developers → API Keys. Give it Text to Speech access, plus Voices: Read so the list below works. It stays on this server.', 'marquee' ); ?></small></p>
						<p><label for="mq-vmodel"><?php esc_html_e( 'Voice quality', 'marquee' ); ?></label><select id="mq-vmodel" name="voice_model">
							<?php foreach ( marquee_voice_models() as $mid => $mlab ) : ?>
								<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $s['voice_model'], $mid ); ?>><?php echo esc_html( $mlab ); ?></option>
							<?php endforeach; ?>
						</select></p>
					</div>
					<div class="mq__row">
						<p><label for="mq-vpick"><?php esc_html_e( 'Voice', 'marquee' ); ?></label>
							<select id="mq-vpick" data-mq-vpick>
								<?php if ( $s['voice_id'] ) : ?>
									<option value="<?php echo esc_attr( $s['voice_id'] ); ?>" selected><?php echo esc_html( $s['voice_name'] ? $s['voice_name'] : $s['voice_id'] ); ?></option>
								<?php else : ?>
									<option value=""><?php esc_html_e( '— Load my voices first —', 'marquee' ); ?></option>
								<?php endif; ?>
							</select>
							<input type="hidden" name="voice_id" id="mq-vid" value="<?php echo esc_attr( $s['voice_id'] ); ?>">
							<input type="hidden" name="voice_name" id="mq-vname" value="<?php echo esc_attr( $s['voice_name'] ); ?>">
							<small><button type="button" class="button-link" data-mq-vload><?php esc_html_e( 'Load my voices', 'marquee' ); ?></button> <span class="mq__msg" data-mq-vlmsg aria-live="polite"></span></small></p>
						<p><label for="mq-vtest"><?php esc_html_e( 'Try it with a name', 'marquee' ); ?></label><input id="mq-vtest" type="text" maxlength="14" value="Emma">
							<small><button type="button" class="button" data-mq-vtest><?php esc_html_e( 'Test voice', 'marquee' ); ?></button> <span class="mq__msg" data-mq-vmsg aria-live="polite"></span></small></p>
					</div>
					<div class="mq__row">
						<p><label for="mq-vspeed"><?php esc_html_e( 'Speaking speed', 'marquee' ); ?></label><select id="mq-vspeed" name="voice_speed">
							<?php foreach ( array( '0.75' => __( 'Slow', 'marquee' ), '0.85' => __( 'A little slow', 'marquee' ), '0.9' => __( 'Relaxed (recommended)', 'marquee' ), '1' => __( 'Normal', 'marquee' ), '1.1' => __( 'Quick', 'marquee' ) ) as $sv => $sl ) : ?>
								<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( abs( (float) $s['voice_speed'] - (float) $sv ) < 0.01 ); ?>><?php echo esc_html( $sl ); ?></option>
							<?php endforeach; ?>
						</select></p>
						<p><label class="mq__chk"><input type="checkbox" id="mq-vsanta" name="voice_santa" value="1" <?php checked( (bool) $s['voice_santa'] ); ?>> <?php esc_html_e( 'My voice is Santa', 'marquee' ); ?></label><small><?php esc_html_e( 'Then “Hi Santa! Love, Mia” is answered “Ho ho ho! Hi Mia, Santa loves you too!” (the screens still show the kid’s message).', 'marquee' ); ?></small></p>
					</div>
					<audio data-mq-vaudio controls hidden></audio>
					<p><label for="mq-veff"><?php esc_html_e( 'Talking faces effect (optional)', 'marquee' ); ?></label><input id="mq-veff" name="voice_effect" type="text" value="<?php echo esc_attr( $s['voice_effect'] ); ?>" placeholder="<?php esc_attr_e( 'Names Talking', 'marquee' ); ?>"><small><?php esc_html_e( 'The name of an FPP effect (.eseq) that moves your singing faces’ mouths (several with commas). It plays while each name is spoken. Leave empty for voice only. The setup guide shows how to make one.', 'marquee' ); ?></small></p>
					<p class="mq__fine"><?php esc_html_e( 'Cost: about 25 ElevenLabs credits per name, and a name that was already said is free after that (the clip is saved on your website). Kids’ first names are sent to ElevenLabs to make the audio; Settings → Privacy has a line you can add to your privacy page.', 'marquee' ); ?>
						<?php if ( $s['voice_key'] ) : ?><label><input type="checkbox" name="voice_clear" value="1"> <?php esc_html_e( 'Remove my ElevenLabs key', 'marquee' ); ?></label><?php endif; ?></p>
						</div>
						<?php $mq_next( 'voice' ); ?>
					</div>

<div class="mq-sub" data-sub="extras" role="tabpanel">
					<?php marquee_field( 'names_color' ); ?>
					<?php
					$msg_std = function_exists( 'ridgeroad_name_greetings_std' ) ? array_values( ridgeroad_name_greetings_std() ) : array();
					$msg_now = $s['name_msgs'] ? array_values( (array) $s['name_msgs'] ) : $msg_std;
					?>
					<details class="mq__card" data-mq-msgs>
						<summary>
							<span class="mq__card-n" aria-hidden="true">💬</span>
							<span class="mq__card-t"><b><?php esc_html_e( 'Messages kids can pick', 'marquee' ); ?></b><small><?php esc_html_e( 'The messages under “Pick your message” on your website, like “Merry Christmas, Emma!”. Write your own if you like.', 'marquee' ); ?></small></span>
							<span class="mq__pill <?php echo $s['name_msgs'] ? 'is-ok' : ''; ?>"><?php echo $s['name_msgs'] ? esc_html__( 'Your own', 'marquee' ) : esc_html__( 'Standard', 'marquee' ); ?></span>
						</summary>
						<div class="mq__card-b">
							<input type="hidden" name="msgs_sent" value="1">
							<p class="mq__fine"><?php echo wp_kses( __( 'Type <b>NAME</b> where the kid’s name goes. Kids pick one of these when they send their name; it goes up on your screens in capital letters (and the voice says it, if you set that up). Up to 6 messages, 34 letters each. Leave a box empty to drop it.', 'marquee' ), array( 'b' => array() ) ); ?></p>
							<ol class="mq-msgs">
								<?php for ( $i = 0; $i < 6; $i++ ) : ?>
									<li><input type="text" name="name_msgs[]" maxlength="34" value="<?php echo esc_attr( isset( $msg_now[ $i ] ) ? $msg_now[ $i ] : '' ); ?>" placeholder="<?php echo 0 === $i ? esc_attr__( 'Merry Christmas, NAME!', 'marquee' ) : esc_attr__( 'Another message (optional)', 'marquee' ); ?>" aria-label="<?php /* translators: %d: number */ echo esc_attr( sprintf( __( 'Message %d', 'marquee' ), $i + 1 ) ); ?>" data-mq-msg>
										<span class="mq-msgs__scr" data-mq-msg-scr aria-hidden="true"></span><small class="mq-msgs__warn" data-mq-msg-warn hidden></small></li>
								<?php endfor; ?>
							</ol>
							<p><button type="button" class="button-link" data-mq-msgs-std data-std="<?php echo esc_attr( wp_json_encode( $msg_std ) ); ?>"><?php esc_html_e( 'Put back the standard messages', 'marquee' ); ?></button></p>
							<p class="mq__fine"><?php esc_html_e( 'Ideas: “Feliz Navidad, NAME!”, “Happy Hanukkah, NAME!”, “Thanks for visiting, NAME!”, “Go team, NAME!”. Kids can’t type their own message: only these, so nothing unexpected goes up on your screens. Save when you’re done.', 'marquee' ); ?></p>
						</div>
					</details>

					<?php marquee_photos_box( $s ); ?>

					<?php
					$blk = array_values( array_filter( array_map( 'trim', preg_split( '/[,\n]+/', (string) marquee_theme_get( 'names_block' ) ) ) ) );
					?>
					<details class="mq__card" data-mq-block>
						<summary>
							<span class="mq__card-n" aria-hidden="true">🚫</span>
							<span class="mq__card-t"><b><?php esc_html_e( 'Blocked names and words', 'marquee' ); ?></b><small><?php esc_html_e( 'Names you blocked, and your own words to keep off your screens. Bad words are always blocked for you.', 'marquee' ); ?></small></span>
							<span class="mq__pill" data-mq-block-pill><?php /* translators: %d: number of words */ echo esc_html( sprintf( _n( '%d blocked', '%d blocked', count( $blk ), 'marquee' ), count( $blk ) ) ); ?></span>
						</summary>
						<div class="mq__card-b">
							<input type="hidden" name="block_sent" value="1">
							<input type="hidden" name="names_block" value="<?php echo esc_attr( implode( ', ', $blk ) ); ?>" data-mq-block-val>
							<p class="mq__fine"><?php esc_html_e( 'Marquee already stops bad words, rude names and tricks like swapping letters for numbers (“b4d”). You don’t need to set anything up. Every name also shows in Show Control and the Show Manager, where “Block word” takes it off the screens and adds it here.', 'marquee' ); ?></p>
							<ul class="mq-block" data-mq-block-list></ul>
							<p class="mq-block__none" data-mq-block-none hidden><?php esc_html_e( 'Nothing blocked yet.', 'marquee' ); ?></p>
							<p class="mq-block__add"><label for="mq-block-new" class="screen-reader-text"><?php esc_html_e( 'Name or word to block', 'marquee' ); ?></label><input id="mq-block-new" type="text" maxlength="30" placeholder="<?php esc_attr_e( 'A name or word to block', 'marquee' ); ?>" data-mq-block-new> <button type="button" class="button" data-mq-block-add><?php esc_html_e( 'Block it', 'marquee' ); ?></button></p>
							<p class="mq__fine"><?php esc_html_e( 'Blocked one by mistake? Click × next to it and Save, and that name can be sent again. A blocked word is stopped when it’s the whole name (blocking “Bob” doesn’t block “Bobby”).', 'marquee' ); ?></p>
						</div>
					</details>
					</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<section class="mq__pane" data-pane="3">
				<header class="mq-head">
<p class="mq-head__eyebrow"><?php esc_html_e( 'Your show', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Connections', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'Optional. Remote Falcon puts what’s playing, your playlist and song voting on your website. Facebook and Instagram get a post when you cancel a night.', 'marquee' ); ?></p>
				</header>
				<div class="mq-sec" id="mq-sec-rf">
				<h3 class="mq-sec__title"><?php esc_html_e( 'Remote Falcon', 'marquee' ); ?></h3>
				<?php marquee_tour_button( 'rf' ); ?>
				<div class="mq__row">
					<p><label for="mq-rft"><?php esc_html_e( 'Access token', 'marquee' ); ?></label><input id="mq-rft" name="rf_token" type="text" autocomplete="off" value="<?php echo esc_attr( ( $s['rf_token'] && function_exists( 'marquee_is_support_user' ) && marquee_is_support_user() ) ? '••••••••' : $s['rf_token'] ); ?>"></p>
					<p><label for="mq-rfs"><?php esc_html_e( 'Secret key', 'marquee' ); ?></label><input id="mq-rfs" name="rf_secret" type="password" autocomplete="new-password" value="<?php echo $s['rf_secret'] ? '••••••••' : ''; ?>"></p>
				</div>
				<p><label for="mq-rfv"><?php esc_html_e( 'Your Remote Falcon viewer page (optional)', 'marquee' ); ?></label><input id="mq-rfv" name="rf_viewer_url" type="url" placeholder="https://yourshow.remotefalcon.com" value="<?php echo esc_attr( marquee_theme_get( 'rf_viewer_url' ) ); ?>"><small><?php esc_html_e( 'Adds a “Pick the Next Song” button to Tonight’s Show while the show is running. Visitors pick right on your website: Marquee shows your Remote Falcon songs and sends their vote or request to Remote Falcon, with your Remote Falcon rules (one pick each, queue size, location check). Copy the address from Remote Falcon → Control Panel → View Viewer Page.', 'marquee' ); ?></small></p>
				<p><button type="button" class="button" data-mq-rftest><?php esc_html_e( 'Test connection', 'marquee' ); ?></button> <span class="mq__msg" data-mq-rfmsg aria-live="polite"></span></p>
				<p class="mq__fine"><?php esc_html_e( 'Both are on Remote Falcon → Account → API Access. They turn on Now Playing, the playlist and song voting, and never leave this server.', 'marquee' ); ?> <strong><?php esc_html_e( 'Your FPP also needs the Remote Falcon plugin installed and running (FPP → Content Setup → Plugin Manager), or Now Playing stays empty.', 'marquee' ); ?></strong></p>
				</div>
				<div class="mq-sec" id="mq-sec-posts">
				<h3 class="mq-sec__title"><?php esc_html_e( 'Social posts', 'marquee' ); ?></h3>
				<p class="mq__lead"><?php esc_html_e( 'When you cancel tonight’s show (in Show Control or on your phone in the Show Manager), Marquee can post it on your Facebook Page and Instagram for you, with your picture. Once per night, so it never posts twice.', 'marquee' ); ?></p>
				<?php marquee_social_box(); ?>
				</div>
			</section>

<?php if ( ! $setup ) : ?>
			<section class="mq__pane" data-pane="6">
				<header class="mq-head">
					<p class="mq-head__eyebrow"><?php esc_html_e( 'Your pages', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Kids’ Corner', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'A page just for kids. Turn on the parts you want; open one to change its pictures and words. Anything left empty uses the standard pictures.', 'marquee' ); ?></p>
				</header>
				<?php
				marquee_switch( 'kids_show', __( 'Show the Kids’ Corner page', 'marquee' ), __( 'Its own button at the top of your website, and a big link on your home page.', 'marquee' ), '', 'mq__feat--page' );
				marquee_field( 'kids_title' );
				?>
				<input type="hidden" name="art_sent" value="1">
				<?php marquee_pbox( 'coloring', __( 'Coloring Pages', 'marquee' ), __( 'Pages to print and color', 'marquee' ), 'coloring_on' ); ?>
				<p class="mq__fine"><?php echo get_theme_mod( 'custom_logo' ) ? esc_html__( 'Your logo is printed in the corner of every standard coloring page.', 'marquee' ) : esc_html__( 'Your show name is printed in the corner of every standard coloring page (or your logo, once you add one in Show details).', 'marquee' ); ?></p>
				<?php
				marquee_media_field( 'art_house', __( 'Your house or display', 'marquee' ), __( 'A black-and-white line drawing of your house, shown first as “Our light show”.', 'marquee' ), $s['art_house'] ? array( (int) $s['art_house'] ) : array(), false );
				if ( function_exists( 'ridgeroad_std_coloring' ) ) :
					$std_dir = function_exists( 'ridgeroad_asset_url' ) ? ridgeroad_asset_url( 'coloring/' ) : MARQUEE_URL . 'core/assets/coloring/';
					$hidden  = (array) $s['art_hide'];
					?>
					<div class="mq__media is-multi">
						<b class="mq__media-label"><?php esc_html_e( 'Coloring pages that come with Marquee', 'marquee' ); ?></b>
						<small class="mq__fine"><?php esc_html_e( 'Untick any you want off for now, or click Remove to take it out of this list. If every page is off and you add none of your own, the Coloring Pages door is hidden.', 'marquee' ); ?></small>
						<?php
						$removed = (array) $s['art_removed'];
						$card    = function ( $slug, $title, $gone ) use ( $std_dir, $hidden ) {
							?>
							<div class="mq__std-pg" data-mq-std="<?php echo esc_attr( $slug ); ?>">
								<label><img src="<?php echo esc_url( $std_dir . $slug . '-thumb.webp' ); ?>" alt="" loading="lazy">
								<span><input type="checkbox" name="art_std[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( ! $gone && ! in_array( $slug, $hidden, true ) ); ?> <?php disabled( $gone ); ?>> <?php echo esc_html( $title ); ?></span></label>
								<input type="hidden" name="art_removed[]" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( ! $gone ); ?>>
								<button type="button" class="button-link mq__std-x" data-mq-std-x><?php echo $gone ? esc_html__( 'Restore', 'marquee' ) : esc_html__( 'Remove', 'marquee' ); ?></button>
							</div>
							<?php
						};
						?>
						<div class="mq__std" data-mq-std-list>
							<?php
							foreach ( ridgeroad_std_coloring() as $slug => $title ) {
								if ( 'house' !== $slug && ! in_array( $slug, $removed, true ) ) {
									$card( $slug, $title, false );
								}
							}
							?>
						</div>
						<div class="mq__std-gone" data-mq-std-gone <?php echo $removed ? '' : 'hidden'; ?>>
							<b><?php esc_html_e( 'Removed pages', 'marquee' ); ?></b> <small class="mq__fine"><?php esc_html_e( 'Kids never see these. Click Restore to bring one back.', 'marquee' ); ?></small>
							<div class="mq__std mq__std--gone" data-mq-std-gonelist>
								<?php
								foreach ( ridgeroad_std_coloring() as $slug => $title ) {
									if ( 'house' !== $slug && in_array( $slug, $removed, true ) ) {
										$card( $slug, $title, true );
									}
								}
								?>
							</div>
						</div>
					</div>
					<?php
				endif;
				marquee_media_field( 'art_pages', __( 'More coloring pages', 'marquee' ), __( 'Up to 12. Black-and-white line drawings print best. The picture’s title in the Media Library is the name kids see.', 'marquee' ), (array) $s['art_pages'], true );
				?>
<?php marquee_pbox_end(); ?>
				<?php marquee_pbox( 'lab', __( 'Make a Light Show', 'marquee' ), __( 'The Light Lab: kids light up a house to a song', 'marquee' ), 'lab_on' ); ?>
				<?php
				marquee_media_field( 'lab_songs', __( 'Songs kids can pick in Make a Light Show', 'marquee' ), __( 'MP3 files, up to 12. Use songs you have the right to share on your website. The song’s title in the Media Library is the name kids see. With no songs, the lights play on their own for a minute.', 'marquee' ), (array) $s['lab_songs'], true, '', 'audio' );
				?>
<?php marquee_pbox_end(); ?>
				<?php marquee_pbox( 'contest', __( 'Light show contest', 'marquee' ), __( 'Entries, the Show Wall and hearts (needs Make a Light Show)', 'marquee' ), 'contest_on' ); ?>
					<?php marquee_fields( array( 'wall_home', 'contest_deadline', 'contest_prize1', 'contest_prize2', 'contest_pickup', 'contest_notify' ) ); ?>
				<?php marquee_pbox_end(); ?>
				<?php marquee_pbox( 'games', __( 'Games', 'marquee' ), __( 'Elf names and Christmas trivia', 'marquee' ), 'games_on' ); ?>
					<p class="mq__fine"><?php esc_html_e( 'Nothing to set up: the games are ready to play.', 'marquee' ); ?></p>
				<?php marquee_pbox_end(); ?>
				<?php marquee_pbox( 'doors', __( 'Door pictures', 'marquee' ), __( 'The big pictures kids tap', 'marquee' ) ); ?>
				<p class="mq__fine"><?php esc_html_e( 'Replace any of the four big pictures kids tap. Tall pictures work best (490 × 685 or bigger, same shape).', 'marquee' ); ?> <?php esc_html_e( 'A PNG with see-through edges blends into your page best.', 'marquee' ); ?></p>
				<div class="mq__doors">
				<?php
				$doors = array(
					'names'    => array( __( 'Your Name in Lights', 'marquee' ), 'door-1.webp' ),
					'show'     => array( __( 'Make a Light Show', 'marquee' ), 'door-2.webp' ),
					'coloring' => array( __( 'Coloring Pages', 'marquee' ), 'door-coloring.webp' ),
					'games'    => array( __( 'Games', 'marquee' ), 'door-4.webp' ),
				);
				foreach ( $doors as $k => $d ) {
					$id = ! empty( $s['art_doors'][ $k ] ) ? array( (int) $s['art_doors'][ $k ] ) : array();
					marquee_media_field( 'art_door_' . $k, $d[0], '', $id, false, MARQUEE_URL . 'core/assets/img/kids/' . $d[1] );
				}
				?>
				</div>
				<?php marquee_pbox_end(); ?>
			</section>
			<?php endif; ?>

			<?php if ( ! $setup ) : ?>
			<section class="mq__pane" data-pane="10">
				<header class="mq-head">
					<p class="mq-head__eyebrow"><?php esc_html_e( 'Your pages', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Home page', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'Your home page, box by box from top to bottom. Use a box’s switch to show or hide it; open it to change its words.', 'marquee' ); ?></p>
				</header>
				<?php marquee_make_pages_row( false ); ?>
				<div class="mq-pboxes">
				<?php marquee_pbox( 'header', __( 'Top of the page', 'marquee' ), __( 'The lines under your logo and your welcome', 'marquee' ) ); ?>
					<?php marquee_fields( array( 'subtitle', 'tagline', 'intro' ) ); ?>
					<p class="mq__fine"><?php echo $theme ? esc_html__( 'Falling snow and your own background photo are under Look & colors.', 'marquee' ) : esc_html__( 'Falling snow and your own background photo are in Appearance → Customize → Show Website → Snow & background.', 'marquee' ); ?></p>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'status', __( 'Status banner', 'marquee' ), __( 'Show tonight, canceled or off season. It changes by itself.', 'marquee' ) ); ?>
					<h4><?php esc_html_e( 'Show tonight', 'marquee' ); ?></h4>
					<?php marquee_fields( array( 'ok_title', 'ok_text', 'ok_hide' ) ); ?>
					<h4><?php esc_html_e( 'Canceled', 'marquee' ); ?></h4>
					<?php marquee_fields( array( 'cancel_title', 'cancel_text' ) ); ?>
					<h4><?php esc_html_e( 'Off season', 'marquee' ); ?></h4>
					<?php marquee_fields( array( 'off_title', 'off_text' ) ); ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'countdown', __( 'Countdown', 'marquee' ), __( 'Counts down to showtime and to Christmas', 'marquee' ), 'show_countdown' ); ?>
					<?php marquee_field( 'count_live' ); ?>
				<?php $cto = marquee_theme_get( 'count_to' ); ?>
				<h3><?php esc_html_e( 'Countdown', 'marquee' ); ?></h3>
				<div class="mq__row">
					<p><label for="mq-cto"><?php esc_html_e( 'Count down to', 'marquee' ); ?></label>
						<select id="mq-cto" name="count_to" data-mq-cto>
							<?php foreach ( array( 'auto' => __( 'Automatic', 'marquee' ), 'christmas' => __( 'Christmas', 'marquee' ), 'halloween' => __( 'Halloween', 'marquee' ), 'custom' => __( 'A date I pick', 'marquee' ) ) as $v => $l ) : ?>
								<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $cto ? $cto : 'auto', $v ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<small><?php esc_html_e( 'Automatic: Halloween if your last night is in August, September or October, otherwise Christmas. Shown under the countdown on your home page (“12 days until Halloween”).', 'marquee' ); ?></small></p>
					<p data-mq-cto-custom <?php echo 'custom' === $cto ? '' : 'hidden'; ?>><label for="mq-cnm"><?php esc_html_e( 'Its name', 'marquee' ); ?></label><input id="mq-cnm" name="count_name" type="text" maxlength="40" placeholder="<?php esc_attr_e( 'New Year’s Eve', 'marquee' ); ?>" value="<?php echo esc_attr( marquee_theme_get( 'count_name' ) ); ?>"></p>
					<p data-mq-cto-custom <?php echo 'custom' === $cto ? '' : 'hidden'; ?>><label for="mq-cdt"><?php esc_html_e( 'The date', 'marquee' ); ?></label><input id="mq-cdt" name="count_date" type="date" value="<?php echo esc_attr( marquee_theme_get( 'count_date' ) ); ?>"><small><?php esc_html_e( 'It repeats every year, so the year doesn’t matter.', 'marquee' ); ?></small></p>
				</div>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'show', __( 'Tonight’s Show', 'marquee' ), __( 'Radio station, hours, Now Playing and directions' , 'marquee' ) ); ?>
					<p class="mq__fine"><?php esc_html_e( 'This box is made from your Show details (radio, hours, address, parking) and, once it’s connected, Remote Falcon (Now Playing and Pick the Next Song).', 'marquee' ); ?></p>
					<p><button type="button" class="button" data-step="0"><?php esc_html_e( 'Show details', 'marquee' ); ?></button> <button type="button" class="button" data-step="3"><?php esc_html_e( 'Connections', 'marquee' ); ?></button></p>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'weather', __( 'Weather', 'marquee' ), __( 'Weather now, tonight hour by hour, and the radar', 'marquee' ) ); ?>
					<?php marquee_field( 'show_radar' ); ?>
					<p class="mq__fine"><?php esc_html_e( 'The weather is for your show’s address in Show details.', 'marquee' ); ?></p>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'playlist', __( 'Tonight’s Playlist', 'marquee' ), __( 'Your Remote Falcon song list, with the song playing now lit up', 'marquee' ), 'show_playlist' ); ?>
					<?php marquee_fields( array( 'playlist_title', 'playlist_when' ) ); ?>
					<p class="mq__fine"><?php esc_html_e( 'A long list gets its own scroll bar, so it never takes over the page.', 'marquee' ); ?></p>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'live', __( 'Live camera', 'marquee' ), __( 'Shows when you add a YouTube link', 'marquee' ) ); ?>
					<?php marquee_fields( array( 'live_url', 'live_title', 'live_text' ) ); ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'visit', __( 'Before You Visit', 'marquee' ), __( 'Up to four tips for visitors. A tip with no title is hidden.', 'marquee' ) ); ?>
					<?php marquee_field( 'tips_title' ); ?>
					<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
						<div class="mq-pbox__item">
							<b class="mq-pbox__n"><?php /* translators: %d: tip number */ echo esc_html( sprintf( __( 'Tip %d', 'marquee' ), $i ) ); ?></b>
							<div class="mq__row"><?php marquee_fields( array( "tip{$i}_title", "tip{$i}_icon" ) ); ?></div>
							<?php marquee_field( "tip{$i}_text" ); ?>
						</div>
					<?php endfor; ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'stats', __( 'Behind the Lights', 'marquee' ), __( 'Your show’s numbers: lights, channels, songs…', 'marquee' ), 'stats_show' ); ?>
					<?php marquee_fields( array( 'stats_title', 'stats_text' ) ); ?>
					<p class="mq__fine"><?php esc_html_e( 'The Show Facts page uses these numbers too. A number left empty is hidden.', 'marquee' ); ?></p>
					<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
						<div class="mq__row mq__row--3 mq-pbox__item"><?php marquee_fields( array( "stat{$i}_num", "stat{$i}_label", "stat{$i}_icon" ) ); ?></div>
					<?php endfor; ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'guestbook', __( 'Guestbook', 'marquee' ), __( 'Visitors leave messages; each one waits for your OK in WordPress → Comments', 'marquee' ), 'gb_show' ); ?>
					<?php marquee_fields( array( 'gb_title', 'gb_eyebrow', 'gb_intro', 'gb_count', 'gb_source', 'gb_site_key', 'guestbook_url' ) ); ?>
					<h4><?php esc_html_e( 'Stop spam (optional)', 'marquee' ); ?></h4>
					<?php marquee_fields( array( 'gb_ts_site', 'gb_ts_secret' ) ); ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'sponsors', __( 'Sponsors', 'marquee' ), __( 'Thank local businesses that support your show, with their logos', 'marquee' ), 'sp_show' ); ?>
				<div class="mq-sec" id="mq-sec-sponsors">
					<input type="hidden" name="sp_sent" value="1">
					<p><label for="mq-sp-color"><?php esc_html_e( 'Box color', 'marquee' ); ?></label><select id="mq-sp-color" name="sp_color"><?php foreach ( array( 'green' => __( 'Green', 'marquee' ), 'red' => __( 'Red', 'marquee' ), 'blue' => __( 'Blue', 'marquee' ) ) as $mq_c => $mq_l ) : ?><option value="<?php echo esc_attr( $mq_c ); ?>" <?php selected( $tm( 'sp_color' ), $mq_c ); ?>><?php echo esc_html( $mq_l ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Green sits nicely between the guestbook and the red Donate box. If Box colors (Appearance → Customize → Look) is set to one color for every box, that color is used here too.', 'marquee' ); ?></small></p>
					<p><label for="mq-sp-title"><?php esc_html_e( 'Title', 'marquee' ); ?></label><input id="mq-sp-title" name="sp_title" type="text" maxlength="60" value="<?php echo esc_attr( $tm( 'sp_title' ) ); ?>"></p>
					<p><label for="mq-sp-intro"><?php esc_html_e( 'Thank-you line (optional)', 'marquee' ); ?></label><input id="mq-sp-intro" name="sp_intro" type="text" maxlength="160" value="<?php echo esc_attr( $tm( 'sp_intro' ) ); ?>"><small><?php esc_html_e( 'Shows under the title. Leave it empty for just the logos.', 'marquee' ); ?></small></p>
					<p><label for="mq-sp-ask"><?php esc_html_e( '“Want to sponsor the show?” link (optional)', 'marquee' ); ?></label><input id="mq-sp-ask" name="sp_ask" type="text" placeholder="you@example.com" value="<?php echo esc_attr( $tm( 'sp_ask' ) ); ?>"><small><?php esc_html_e( 'An email address or a web page. Adds “Want to sponsor the show? Get in touch” under the logos. Leave it empty to hide it.', 'marquee' ); ?></small></p>
					<?php
					$mq_sp = function_exists( 'ridgeroad_sponsors' ) ? ridgeroad_sponsors() : array();
					$mq_sp_row = function ( $r ) {
						?>
						<li class="mq-sp__row" data-mq-sprow>
							<div class="mq-sp__logo"><?php marquee_media_field( 'sp_logo[]', __( 'Logo', 'marquee' ), '', $r['logo'] ? array( $r['logo'] ) : array(), false ); ?></div>
							<div class="mq-sp__fields">
								<p><label><?php esc_html_e( 'Business name', 'marquee' ); ?><input name="sp_name[]" type="text" maxlength="60" value="<?php echo esc_attr( $r['name'] ); ?>"></label></p>
								<p><label><?php esc_html_e( 'Their website (optional)', 'marquee' ); ?><input name="sp_url[]" type="text" placeholder="https://" value="<?php echo esc_attr( $r['url'] ); ?>"></label></p>
								<p><label><?php esc_html_e( 'Short line under the name (optional)', 'marquee' ); ?><input name="sp_line[]" type="text" maxlength="90" placeholder="<?php esc_attr_e( 'Hot cocoa for visitors on Friday nights', 'marquee' ); ?>" value="<?php echo esc_attr( $r['line'] ); ?>"></label></p>
							</div>
							<p class="mq-sp__tools">
								<button type="button" class="button" data-mq-spmove="-1" aria-label="<?php esc_attr_e( 'Move up', 'marquee' ); ?>">↑</button>
								<button type="button" class="button" data-mq-spmove="1" aria-label="<?php esc_attr_e( 'Move down', 'marquee' ); ?>">↓</button>
								<button type="button" class="button-link-delete" data-mq-spdel><?php esc_html_e( 'Remove sponsor', 'marquee' ); ?></button>
							</p>
						</li>
						<?php
					};
					?>
					<ol class="mq-sp" data-mq-splist>
						<?php foreach ( $mq_sp as $r ) { $mq_sp_row( $r ); } ?>
					</ol>
					<template data-mq-sptpl><?php $mq_sp_row( array( 'logo' => 0, 'name' => '', 'url' => '', 'line' => '' ) ); ?></template>
					<p class="mq-sp__add"><button type="button" class="button" data-mq-spadd><?php esc_html_e( 'Add a sponsor', 'marquee' ); ?></button> <small data-mq-spempty <?php echo $mq_sp ? 'hidden' : ''; ?>><?php esc_html_e( 'No sponsors yet. The box stays hidden until you add one.', 'marquee' ); ?></small></p>
				</div>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'donate', __( 'Donate', 'marquee' ), __( 'Shows when you add a donation link', 'marquee' ) ); ?>
				<div class="mq-sec" id="mq-sec-donate">
					<p><label for="mq-don"><?php esc_html_e( 'Donation link (optional)', 'marquee' ); ?></label><input id="mq-don" name="cashapp_url" type="url" placeholder="https://cash.app/$yourshow" value="<?php echo esc_attr( $tm( 'cashapp_url' ) ); ?>"><small><?php esc_html_e( 'Cash App, PayPal, Venmo or any link. Empty hides the donation box.', 'marquee' ); ?></small></p>
				</div>
					<?php marquee_fields( array( 'donate_title', 'donate_text', 'cashapp_label', 'cashapp_qr' ) ); ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'social', __( 'Stay Connected', 'marquee' ), __( 'Your social buttons, a hashtag and Share the Show', 'marquee' ) ); ?>
					<?php marquee_fields( array( 'social_title', 'social_text', 'social_hashtag', 'social_share' ) ); ?>
				<div class="mq-sec" id="mq-sec-social">
				<?php if ( function_exists( 'ridgeroad_social_networks' ) ) : ?>
					<p class="mq__fine"><?php esc_html_e( 'Buttons in Stay Connected and small icons at the bottom of every page. A button shows when it is turned on and has a link.', 'marquee' ); ?></p>
					<input type="hidden" name="social_links_sent" value="1">
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
				</div>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'app', __( 'Get the App', 'marquee' ), __( 'Shows on phones that can install your website as an app', 'marquee' ), 'app_show' ); ?>
					<?php marquee_switch( 'app_auto_on', __( '“The lights are on!” alert', 'marquee' ), __( 'Sent to app users when FPP starts your show (needs https)', 'marquee' ) ); ?>
				<?php marquee_pbox_end(); ?>

				<?php marquee_pbox( 'footer', __( 'Bottom of the page', 'marquee' ), __( 'The small line at the very bottom', 'marquee' ) ); ?>
					<?php marquee_field( 'footer_text' ); ?>
					<?php marquee_field( 'credit_show' ); ?>
				<?php marquee_pbox_end(); ?>
				</div>
			</section>

			<section class="mq__pane" data-pane="11">
				<header class="mq-head">
					<p class="mq-head__eyebrow"><?php esc_html_e( 'Your pages', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Songs & Videos', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'A page with this year’s show videos and favorite song voting. It shows when either part is on and has something in it.', 'marquee' ); ?></p>
				</header>
				<?php marquee_field( 'songs_card' ); ?>
				<div class="mq-pboxes">
				<?php marquee_pbox( 'videos', __( 'Show videos', 'marquee' ), __( 'Your MP4 videos, one for each song', 'marquee' ), 'seq_show' ); ?>
					<?php marquee_fields( array( 'seq_title', 'seq_text' ) ); ?>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=rr_sequence' ) ); ?>"><?php esc_html_e( 'Add or change videos', 'marquee' ); ?></a></p>
				<?php marquee_pbox_end(); ?>
				<?php marquee_pbox( 'voting', __( 'Favorite song voting', 'marquee' ), __( 'Visitors vote for their favorite song', 'marquee' ), 'songs_show' ); ?>
					<?php marquee_fields( array( 'songs_title', 'songs_text', 'songs_source', 'songs_manual', 'songs_friday', 'songs_friday_text' ) ); ?>
				<h4><?php esc_html_e( 'Leave out of song voting', 'marquee' ); ?></h4>
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
				<?php if ( function_exists( 'marquee_covers_box' ) ) { marquee_covers_box( $s ); } ?>
				<?php marquee_pbox_end(); ?>
				</div>
			</section>

			<section class="mq__pane" data-pane="12">
				<header class="mq-head">
					<p class="mq-head__eyebrow"><?php esc_html_e( 'Your pages', 'marquee' ); ?></p>
					<h2><?php esc_html_e( 'Show Facts', 'marquee' ); ?></h2>
					<p class="mq__lead"><?php esc_html_e( 'A page about your show, with its own button at the top next to Kids’ Corner. It has three parts; show the ones you like.', 'marquee' ); ?></p>
				</header>
				<div class="mq-sec" id="mq-sec-facts">
					<input type="hidden" name="facts_sent" value="1">
					<?php marquee_switch( 'facts_show', __( 'Show the Show Facts page', 'marquee' ), __( 'Its own button at the top of your website.', 'marquee' ), 'data-mq-factsshow', 'mq__feat--page' ); ?>
					<fieldset class="mq-fstyle"><legend><?php esc_html_e( 'Page style', 'marquee' ); ?></legend>
						<?php
						$mq_fs = array(
							'photo' => array( __( 'Photo', 'marquee' ), __( 'A big photo of your show with your numbers under it, fact cards with big words, and each year’s favorites as light strings.', 'marquee' ) ),
							'boxes' => array( __( 'Boxes', 'marquee' ), __( 'Your numbers in boxes like Behind the Lights, small fact cards together in one box, and the favorites of each year side by side.', 'marquee' ) ),
						);
						$mq_fs_cur = in_array( $tm( 'facts_style' ), array( 'photo', 'boxes' ), true ) ? $tm( 'facts_style' ) : 'photo';
						foreach ( $mq_fs as $k => $f ) :
							?>
							<label class="mq-fstyle__opt"><input type="radio" name="facts_style" value="<?php echo esc_attr( $k ); ?>" <?php checked( $mq_fs_cur, $k ); ?>><img src="<?php echo esc_url( MARQUEE_URL . 'assets/img/facts-' . $k . '.jpg' ); ?>" alt="" width="240" height="135" loading="lazy"><b><?php echo esc_html( $f[0] ); ?></b><small><?php echo esc_html( $f[1] ); ?></small></label>
						<?php endforeach; ?>
					</fieldset>
					<p><label for="mq-facts-title"><?php esc_html_e( 'Page title', 'marquee' ); ?></label><input id="mq-facts-title" name="facts_title" type="text" maxlength="40" value="<?php echo esc_attr( $tm( 'facts_title' ) ); ?>"><small><?php esc_html_e( 'Also the name on the button at the top.', 'marquee' ); ?></small></p>
					<?php marquee_lang_boxes( 'facts_title', __( 'Page title', 'marquee' ) ); ?>

					<h4 class="mq-facts__part"><label><input type="checkbox" name="facts_hero" value="1" <?php checked( (bool) $tm( 'facts_hero' ) ); ?>> <?php esc_html_e( 'Your show: a photo and your numbers', 'marquee' ); ?></label></h4>
					<div class="mq-facts__box">
						<?php marquee_media_field( 'facts_photo', __( 'Photo of your show', 'marquee' ), __( 'A wide night photo of your display works best.', 'marquee' ), $tm( 'facts_photo' ) ? array( (int) $tm( 'facts_photo' ) ) : array(), false ); ?>
						<p><label for="mq-facts-head"><?php esc_html_e( 'Headline on the photo (optional)', 'marquee' ); ?></label><input id="mq-facts-head" name="facts_tagline" type="text" maxlength="70" placeholder="<?php esc_attr_e( 'Lighting up our street since 2019', 'marquee' ); ?>" value="<?php echo esc_attr( $tm( 'facts_tagline' ) ); ?>"></p>
						<?php marquee_lang_boxes( 'facts_tagline', __( 'Headline on the photo', 'marquee' ) ); ?>
						<p><label for="mq-facts-line"><?php esc_html_e( 'Line under the headline (optional)', 'marquee' ); ?></label><input id="mq-facts-line" name="facts_line" type="text" maxlength="120" value="<?php echo esc_attr( $tm( 'facts_line' ) ); ?>"></p>
						<?php marquee_lang_boxes( 'facts_line', __( 'Line under the headline', 'marquee' ) ); ?>
						<?php marquee_field( 'facts_cap' ); ?>
						<p class="mq__fine"><?php esc_html_e( 'The numbers under the photo are your Behind the Lights numbers. They count up when the page opens.', 'marquee' ); ?> <a href="#mq-step-10-stats"><?php esc_html_e( 'Change the numbers', 'marquee' ); ?></a></p>
					</div>

					<h4 class="mq-facts__part"><label><input type="checkbox" name="facts_did" value="1" <?php checked( (bool) $tm( 'facts_did' ) ); ?>> <?php esc_html_e( 'Did You Know? fact cards', 'marquee' ); ?></label></h4>
					<div class="mq-facts__box">
						<?php marquee_field( 'facts_did_title', array( 'label' => __( 'Title of this part', 'marquee' ) ) ); ?>
						<?php
						$mq_fc = array_values( array_filter( (array) $tm( 'facts' ), function ( $r ) {
							return is_array( $r ) && ( '' !== trim( (string) ( $r['big'] ?? '' ) ) || '' !== trim( (string) ( $r['text'] ?? '' ) ) );
						} ) ); // as typed (with each language's words), not as a visitor sees them
						$mq_fc_langs = function_exists( 'ridgeroad_lang_extra' ) ? ridgeroad_lang_extra() : array();
						$mq_fc_names = function_exists( 'marquee_langs_all' ) ? marquee_langs_all() : array();
						$mq_icons = function_exists( 'ridgeroad_fact_icons' ) ? ridgeroad_fact_icons() : array( 'star' => 'Star' );
						$mq_fc_row = function ( $r ) use ( $mq_icons, $mq_fc_langs, $mq_fc_names ) {
							?>
							<li class="mq-fc__row" data-mq-fcrow>
								<label class="mq-fc__icon"><?php esc_html_e( 'Picture', 'marquee' ); ?><select name="fc_icon[]"><?php foreach ( $mq_icons as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['icon'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
								<label class="mq-fc__big"><?php esc_html_e( 'Big words', 'marquee' ); ?><input name="fc_big[]" type="text" maxlength="24" placeholder="<?php esc_attr_e( '40 hours', 'marquee' ); ?>" value="<?php echo esc_attr( $r['big'] ); ?>"></label>
								<label class="mq-fc__text"><?php esc_html_e( 'The fact', 'marquee' ); ?><input name="fc_text[]" type="text" maxlength="140" placeholder="<?php esc_attr_e( 'to program one song. Every light is set by hand.', 'marquee' ); ?>" value="<?php echo esc_attr( $r['text'] ); ?>"></label>
								<?php if ( $mq_fc_langs ) : ?>
									<details class="mq-f__langs mq-fc__langs"<?php echo array_filter( array_map( function ( $c ) use ( $r ) { return ( $r[ 'big_' . $c ] ?? '' ) . ( $r[ 'text_' . $c ] ?? '' ); }, $mq_fc_langs ) ) ? ' open' : ''; ?>>
										<summary><?php echo esc_html( sprintf( /* translators: %s: language names */ __( 'Other languages: %s', 'marquee' ), implode( ', ', array_map( function ( $c ) use ( $mq_fc_names ) { return $mq_fc_names[ $c ][0] ?? $c; }, $mq_fc_langs ) ) ) ); ?></summary>
										<?php foreach ( $mq_fc_langs as $c ) : ?>
											<span class="mq-fc__lang">
												<label class="mq-fc__big"><?php echo esc_html( __( 'Big words', 'marquee' ) . ' · ' . ( $mq_fc_names[ $c ][0] ?? $c ) ); ?><input name="fc_big_<?php echo esc_attr( $c ); ?>[]" type="text" maxlength="24" value="<?php echo esc_attr( $r[ 'big_' . $c ] ?? '' ); ?>"></label>
												<label class="mq-fc__text"><?php echo esc_html( __( 'The fact', 'marquee' ) . ' · ' . ( $mq_fc_names[ $c ][0] ?? $c ) ); ?><input name="fc_text_<?php echo esc_attr( $c ); ?>[]" type="text" maxlength="140" value="<?php echo esc_attr( $r[ 'text_' . $c ] ?? '' ); ?>"></label>
											</span>
										<?php endforeach; ?>
									</details>
								<?php endif; ?>
								<span class="mq-fc__tools">
									<button type="button" class="button" data-mq-fcmove="-1" aria-label="<?php esc_attr_e( 'Move up', 'marquee' ); ?>">↑</button>
									<button type="button" class="button" data-mq-fcmove="1" aria-label="<?php esc_attr_e( 'Move down', 'marquee' ); ?>">↓</button>
									<button type="button" class="button-link-delete" data-mq-fcdel><?php esc_html_e( 'Remove', 'marquee' ); ?></button>
								</span>
							</li>
							<?php
						};
						?>
						<ol class="mq-fc" data-mq-fclist><?php foreach ( $mq_fc as $r ) { $mq_fc_row( $r ); } ?></ol>
						<template data-mq-fctpl><?php $mq_fc_row( array( 'icon' => 'star', 'big' => '', 'text' => '' ) ); ?></template>
						<p><button type="button" class="button" data-mq-fcadd><?php esc_html_e( 'Add a fact', 'marquee' ); ?></button> <small class="mq__fine"><?php esc_html_e( 'Four or eight cards fill the rows evenly.', 'marquee' ); ?></small></p>
					</div>

					<h4 class="mq-facts__part"><label><input type="checkbox" name="facts_fav" value="1" <?php checked( (bool) $tm( 'facts_fav' ) ); ?>> <?php esc_html_e( 'Fan Favorites by year', 'marquee' ); ?></label></h4>
					<div class="mq-facts__box">
						<?php marquee_field( 'facts_fav_title', array( 'label' => __( 'Title of this part', 'marquee' ) ) ); ?>
						<p class="mq__fine"><?php esc_html_e( 'Years with song voting fill in by themselves, with the top 5 songs and their votes. For years before you had voting, type the top songs here.', 'marquee' ); ?></p>
						<?php
						$mq_fp = array_values( array_filter( (array) $tm( 'fav_past' ), 'is_array' ) ); // nothing saved yet = an empty list, not one empty row
						$mq_fp_row = function ( $r ) {
							?>
							<li class="mq-fp__row" data-mq-fprow>
								<label class="mq-fp__year"><?php esc_html_e( 'Year', 'marquee' ); ?><input name="fp_year[]" type="number" min="1990" max="2100" value="<?php echo $r['year'] ? (int) $r['year'] : ''; ?>"></label>
								<label class="mq-fp__songs"><?php esc_html_e( 'Top songs, one per line, most popular first', 'marquee' ); ?><textarea name="fp_songs[]" rows="4"><?php echo esc_textarea( implode( "\n", (array) $r['songs'] ) ); ?></textarea></label>
								<button type="button" class="button-link-delete" data-mq-fpdel><?php esc_html_e( 'Remove', 'marquee' ); ?></button>
							</li>
							<?php
						};
						?>
						<ol class="mq-fp" data-mq-fplist><?php foreach ( $mq_fp as $r ) { $mq_fp_row( $r ); } ?></ol>
						<template data-mq-fptpl><?php $mq_fp_row( array( 'year' => 0, 'songs' => array() ) ); ?></template>
						<p><button type="button" class="button" data-mq-fpadd><?php esc_html_e( 'Add a past year', 'marquee' ); ?></button></p>
					</div>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( ! $setup ) : ?>
			<section class="mq__pane" data-pane="9">
				<?php marquee_help_view(); ?>
			</section>
			<?php endif; ?>
			</div>

			<div class="mq__nav" data-hint="<?php echo $setup ? '' : esc_attr__( 'One Save keeps your changes in every section.', 'marquee' ); ?>">
				<button type="button" class="button" data-mq-back><?php esc_html_e( 'Back', 'marquee' ); ?></button>
				<button type="button" class="button button-primary" data-mq-next><?php esc_html_e( 'Next', 'marquee' ); ?></button>
				<button type="submit" class="button button-primary" data-mq-save><?php echo $setup ? esc_html__( 'Finish setup', 'marquee' ) : esc_html__( 'Save settings', 'marquee' ); ?></button>
			</div>
		</form>
		<?php marquee_ai_panel(); ?>
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
			<button type="button" class="button" data-mq-pick><?php echo $multi ? ( 'audio' === $type ? esc_html__( 'Add songs', 'marquee' ) : esc_html__( 'Add pictures', 'marquee' ) ) : ( $ids ? esc_html__( 'Change', 'marquee' ) : ( 'audio' === $type ? esc_html__( 'Choose MP3', 'marquee' ) : esc_html__( 'Choose picture', 'marquee' ) ) ); ?></button>
			<?php if ( ! $multi ) : ?><button type="button" class="button-link" data-mq-clear <?php echo $ids ? '' : 'hidden'; ?>><?php echo $std ? esc_html__( 'Use the standard picture', 'marquee' ) : esc_html__( 'Remove', 'marquee' ); ?></button><?php endif; ?>
		</p>
		<?php if ( $help ) : ?><small><?php echo esc_html( $help ); ?></small><?php endif; ?>
	</div>
	<?php
}

/** What the FPP names script last reported (its setup check), for the Names & FPP step. */
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
	$s    = marquee_get();
	$data = array_merge( array(
		'site' => home_url( '/' ),
		'key'  => function_exists( 'ridgeroad_names_key' ) ? ridgeroad_names_key() : '',
		'mode' => 'live' === $s['names_mode'] ? 'live' : 'break',
		'live_show_for' => (float) $s['live_show_for'],
	), marquee_fpp_config() );
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
	$items[] = array( marquee_rf_configured(), __( 'Connect Remote Falcon', 'marquee' ), __( 'Now Playing, the playlist and song voting.', 'marquee' ), $step( 3 ), __( 'Remote Falcon', 'marquee' ) );
	$items[] = array( (bool) get_theme_mod( 'custom_logo' ), __( 'Add your logo', 'marquee' ), __( 'Header, app icon fallback, share previews and coloring pages.', 'marquee' ), $step( 0 ), __( 'Show details', 'marquee' ) );
	$items[] = array( '' !== (string) $tm( 'cashapp_url' ), __( 'Add a donation link', 'marquee' ), __( 'Optional: Cash App, PayPal, Venmo or any link.', 'marquee' ), $step( '10-donate' ), __( 'Home page', 'marquee' ) );
	if ( $tm( 'kids_show' ) && $tm( 'lab_on' ) ) {
		$items[] = array( ! empty( $s['lab_songs'] ), __( 'Add songs for the Light Lab', 'marquee' ), __( 'Kids light up the house to them.', 'marquee' ), $step( '6-lab' ), __( 'Kids’ Corner', 'marquee' ) );
	}
	if ( $tm( 'names_on' ) ) {
		$seen    = (int) get_option( 'ridgeroad_names_seen' );
		$items[] = array( $seen && time() - $seen < 3 * DAY_IN_SECONDS, __( 'Set up Your Name in Lights on FPP', 'marquee' ), __( 'Install the Marquee plugin on your FPP and paste your connection code.', 'marquee' ), $step( 4 ), __( 'Names in Lights', 'marquee' ) );
	}
	if ( 'ridgeroad' !== get_template() ) {
		$front   = (int) get_option( 'page_on_front' );
		$on_site = ( $front && has_block( 'marquee/home', $front ) ) || (int) get_option( 'ridgeroad_kids_page' );
		$items[] = array( (bool) $on_site, __( 'Put the show on your pages', 'marquee' ), __( 'Tick “Make my show pages”, or add Marquee blocks to any page.', 'marquee' ), $step( 10 ), __( 'Home page', 'marquee' ) );
	}
	$items[] = array( (bool) get_option( 'marquee_tips_seen' ), __( 'Check your visitor tips and text', 'marquee' ), __( 'Before You Visit, guestbook and Kids’ Corner wording.', 'marquee' ), $step( '10-visit' ), __( 'Home page', 'marquee' ) );
	$items[] = array( 0 === strpos( home_url(), 'https://' ), __( 'Use https for the app and alerts', 'marquee' ), __( 'Phones only install the app and allow alerts on a secure (https) site. Most hosts turn it on for free.', 'marquee' ), '', '' );
	return $items;
}

/** Setup home's list: the basics first, then the checklist. */
function marquee_home_items() {
	$s  = marquee_get();
	$tm = 'marquee_theme_get';
	$go = function ( $n ) {
		return admin_url( 'admin.php?page=marquee#mq-step-' . $n );
	};
	return array_merge( array(
		array( '' !== trim( (string) $s['show_name'] ), __( 'Name your show', 'marquee' ), __( 'Shown in the header, alerts, Google and share previews.', 'marquee' ), $go( 0 ), __( 'Show details', 'marquee' ) ),
		array( '' !== (string) $s['lat'] && '' !== (string) $s['lon'], __( 'Set your show’s location', 'marquee' ), __( 'Sunset, weather, radar and directions all come from it.', 'marquee' ), $go( 1 ), __( 'Location', 'marquee' ) ),
		array( '' !== (string) $tm( 'season_start' ) && '' !== (string) $tm( 'season_end' ), __( 'Set your show dates and hours', 'marquee' ), __( 'The countdown and Tonight’s Show use them.', 'marquee' ), $go( 2 ), __( 'Schedule', 'marquee' ) ),
	), marquee_checklist_items() );
}

/** Setup home: the next thing to do, everything that's set up, and the show's connections. */
function marquee_home_view( $s ) {
	$items = marquee_home_items();
	$done  = count( array_filter( wp_list_pluck( $items, 0 ) ) );
	$next  = null;
	foreach ( $items as $it ) {
		if ( ! $it[0] && $it[3] ) {
			$next = $it;
			break;
		}
	}
	$seen = (int) get_option( 'ridgeroad_names_seen' );
	?>
	<div class="mq-hero<?php echo $next ? '' : ' is-done'; ?>">
		<div>
			<p class="mq-hero__eyebrow"><?php echo $next ? esc_html__( 'Your next step', 'marquee' ) : esc_html__( 'All set', 'marquee' ); ?></p>
			<h2><?php echo $next ? esc_html( $next[1] ) : esc_html__( 'Your show’s website is ready', 'marquee' ); ?></h2>
			<p><?php echo $next ? esc_html( $next[2] ) : esc_html__( 'Everything on the list is done. Changes you make here show on your website right away.', 'marquee' ); ?></p>
		</div>
		<?php if ( $next ) : ?>
			<a class="button button-hero mq-hero__go" href="<?php echo esc_url( $next[3] ); ?>"><?php echo esc_html( $next[4] ); ?> &rarr;</a>
		<?php else : ?>
			<a class="button button-hero mq-hero__go" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your site', 'marquee' ); ?> &rarr;</a>
		<?php endif; ?>
	</div>

	<div class="mq-box">
		<div class="mq-box__head">
			<h3><?php esc_html_e( 'Your setup', 'marquee' ); ?></h3>
			<span class="mq-prog" role="img" aria-label="<?php /* translators: 1: done, 2: total */ echo esc_attr( sprintf( __( '%1$d of %2$d done', 'marquee' ), $done, count( $items ) ) ); ?>"><span class="mq-prog__bar"><i style="width:<?php echo (int) round( 100 * $done / max( 1, count( $items ) ) ); ?>%"></i></span><?php /* translators: 1: done, 2: total */ echo esc_html( sprintf( __( '%1$d of %2$d done', 'marquee' ), $done, count( $items ) ) ); ?></span>
		</div>
		<ol class="mq-list">
			<?php foreach ( $items as $it ) : ?>
				<li class="<?php echo $it[0] ? 'is-done' : ''; ?>">
					<span class="mq__tick" aria-hidden="true"><?php echo $it[0] ? '✓' : ''; ?></span>
					<span class="mq-list__t"><b><?php echo esc_html( $it[1] ); ?></b><small><?php echo esc_html( $it[2] ); ?></small></span>
					<?php if ( ! $it[0] && $it[3] ) : ?><a class="button" href="<?php echo esc_url( $it[3] ); ?>"><?php echo esc_html( $it[4] ); ?></a><?php elseif ( $it[0] ) : ?><span class="screen-reader-text"><?php esc_html_e( 'Done', 'marquee' ); ?></span><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>

	<h3 class="mq-home__h"><?php esc_html_e( 'Your website’s look and words', 'marquee' ); ?></h3>
	<p class="mq__fine mq-home__sub"><?php esc_html_e( 'Look & colors opens the live preview of your website. The others open that box of your page here.', 'marquee' ); ?></p>
	<div class="mq-conns mq-site">
		<?php
		$cz = function ( $section ) {
			return add_query_arg( array( 'autofocus[section]' => $section, 'return' => rawurlencode( admin_url( 'admin.php?page=marquee' ) ) ), admin_url( 'customize.php' ) );
		};
		$here = function ( $n ) {
			return admin_url( 'admin.php?page=marquee#mq-step-' . $n );
		};
		$site = array();
		if ( function_exists( 'marquee_festive' ) && marquee_festive() ) {
			$site[] = array( $cz( 'rr_look' ), 'art', __( 'Look & colors', 'marquee' ), __( 'Classic, Christmas Village or Santa’s North Pole, colors, snow and your background.', 'marquee' ) );
		}
		$site = array_merge( $site, array(
			array( $here( '10-header' ), 'format-image', __( 'Top of the page', 'marquee' ), __( 'The lines under your logo and your welcome.', 'marquee' ) ),
			array( $here( '10-status' ), 'megaphone', __( 'Banner messages', 'marquee' ), __( 'What the banner says when the show is on, canceled or off season.', 'marquee' ) ),
			array( $here( '10-visit' ), 'car', __( 'Before You Visit tips', 'marquee' ), __( 'Parking, headlights and your other tips for visitors.', 'marquee' ) ),
			array( $here( 6 ), 'smiley', __( 'Kids’ Corner', 'marquee' ), __( 'Which parts show, the contest and its prizes.', 'marquee' ) ),
			array( $here( '10-donate' ), 'heart', __( 'Donations & social', 'marquee' ), __( 'Your donation link and words, and your social buttons.', 'marquee' ) ),
			array( admin_url( 'post-new.php?post_type=page' ), 'welcome-add-page', __( 'Make a new page', 'marquee' ), __( 'Our Story, Questions & Answers, a photo gallery… Start from a layout in your show’s colors.', 'marquee' ) ),
		) );
		foreach ( $site as $c ) :
			?>
			<a class="mq-conn" href="<?php echo esc_url( $c[0] ); ?>">
				<span class="mq-conn__top"><span class="dashicons dashicons-<?php echo esc_attr( $c[1] ); ?>" aria-hidden="true"></span><span class="mq__pill"><?php esc_html_e( 'Open', 'marquee' ); ?> &rarr;</span></span>
				<b><?php echo esc_html( $c[2] ); ?></b>
				<small><?php echo esc_html( $c[3] ); ?></small>
			</a>
		<?php endforeach; ?>
	</div>

	<h3 class="mq-home__h"><?php esc_html_e( 'Your show’s connections', 'marquee' ); ?></h3>
	<div class="mq-conns">
		<?php
		if ( ! marquee_theme_get( 'names_on' ) ) {
			$n = array( '', __( 'Off', 'marquee' ), __( 'Turned off. Kids can’t send names yet.', 'marquee' ) );
		} elseif ( $seen && time() - $seen < 15 * MINUTE_IN_SECONDS ) {
			/* translators: %s: time ago */
			$n = array( 'ok', __( 'Connected', 'marquee' ), sprintf( __( 'Your FPP checked in %s ago.', 'marquee' ), human_time_diff( $seen ) ) );
		} elseif ( $seen ) {
			/* translators: %s: time ago */
			$n = array( 'warn', sprintf( __( 'Last seen %s ago', 'marquee' ), human_time_diff( $seen ) ), __( 'Is the FPP on, with the Marquee plugin running?', 'marquee' ) );
		} else {
			$n = array( '', __( 'Not set up', 'marquee' ), __( 'Put kids’ names on your matrix, TV or projector.', 'marquee' ) );
		}
		$rf = function_exists( 'marquee_rf_configured' ) && marquee_rf_configured();
		$so = function_exists( 'marquee_social_ready' ) && marquee_social_ready();
		$conns = array(
			array( 4, 'megaphone', __( 'Names in Lights', 'marquee' ), $n ),
			array( 3, 'playlist-audio', __( 'Remote Falcon', 'marquee' ), $rf ? array( 'ok', __( 'Connected', 'marquee' ), __( 'Now Playing, your playlist and song picks are on your website.', 'marquee' ) ) : array( '', __( 'Not connected', 'marquee' ), __( 'Show what’s playing and let visitors pick songs.', 'marquee' ) ) ),
			array( 3, 'share', __( 'Social posts', 'marquee' ), $so ? array( 'ok', __( 'Connected', 'marquee' ), __( 'A cancelled night is posted for you.', 'marquee' ) ) : array( '', __( 'Not set up', 'marquee' ), __( 'Post on Facebook and Instagram when you cancel a night.', 'marquee' ) ) ),
		);
		foreach ( $conns as $c ) :
			?>
			<a class="mq-conn" href="<?php echo esc_url( admin_url( 'admin.php?page=marquee#mq-step-' . $c[0] ) ); ?>">
				<span class="mq-conn__top"><span class="dashicons dashicons-<?php echo esc_attr( $c[1] ); ?>" aria-hidden="true"></span><span class="mq__pill<?php echo $c[3][0] ? ' is-' . esc_attr( $c[3][0] ) : ''; ?>"><?php echo esc_html( $c[3][1] ); ?></span></span>
				<b><?php echo esc_html( $c[2] ); ?></b>
				<small><?php echo esc_html( $c[3][2] ); ?></small>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

/** Help: support, ideas and the Show Manager for your phone. */
function marquee_help_view() {
	?>
	<header class="mq-head">
		<p class="mq-head__eyebrow"><?php esc_html_e( 'Help', 'marquee' ); ?></p>
		<h2><?php esc_html_e( 'Help and your phone', 'marquee' ); ?></h2>
		<p class="mq__lead"><?php esc_html_e( 'Stuck on something? Marquee Support can look at your setup with you. And on show nights, run everything from your phone.', 'marquee' ); ?></p>
	</header>
	<div class="mq-conns">
		<a class="mq-conn" href="<?php echo esc_url( admin_url( 'admin.php?page=marquee-support' ) ); ?>">
			<span class="mq-conn__top"><span class="dashicons dashicons-sos" aria-hidden="true"></span></span>
			<b><?php esc_html_e( 'Get help from Marquee Support', 'marquee' ); ?></b>
			<small><?php esc_html_e( 'Ask a question, or let support see your setup for a while.', 'marquee' ); ?></small>
		</a>
		<a class="mq-conn" href="<?php echo esc_url( admin_url( 'admin.php?page=marquee#mq-step-10' ) ); ?>">
			<span class="mq-conn__top"><span class="dashicons dashicons-edit" aria-hidden="true"></span></span>
			<b><?php esc_html_e( 'Website words and tips', 'marquee' ); ?></b>
			<small><?php esc_html_e( 'Every box on your home page, with its words, under Your pages → Home page.', 'marquee' ); ?></small>
		</a>
		<a class="mq-conn" href="<?php echo esc_url( admin_url( 'admin.php?page=marquee-ideas' ) ); ?>">
			<span class="mq-conn__top"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span></span>
			<b><?php esc_html_e( 'Suggest an idea', 'marquee' ); ?></b>
			<small><?php esc_html_e( 'A feature you wish Marquee had, or something that could work better.', 'marquee' ); ?></small>
		</a>
		<?php if ( MARQUEE_COFFEE_URL ) : ?>
			<a class="mq-conn" href="<?php echo esc_url( MARQUEE_COFFEE_URL ); ?>" target="_blank" rel="noopener">
				<span class="mq-conn__top"><span aria-hidden="true">☕</span></span>
				<b><?php esc_html_e( 'Buy me a coffee', 'marquee' ); ?></b>
				<small><?php esc_html_e( 'Marquee is free. A coffee keeps it going.', 'marquee' ); ?></small>
			</a>
		<?php endif; ?>
	</div>
	<?php if ( function_exists( 'marquee_manager_url' ) ) : ?>
		<div class="mq-help__mgr"><?php marquee_manager_box( true ); ?></div>
	<?php endif; ?>
	<p class="mq__fine mq-help__ver"><?php /* translators: %s: version */ echo esc_html( sprintf( __( 'Marquee %s', 'marquee' ), MARQUEE_VERSION ) ); ?></p>
	<?php
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

/* Visiting Customize also ticks "Check your visitor tips" (Marquee 0.18 and earlier kept the tips there). */
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

add_filter( 'plugin_action_links_' . plugin_basename( MARQUEE_DIR . 'marquee.php' ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=marquee' ) ) . '">' . esc_html__( 'Settings', 'marquee' ) . '</a>' );
	$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=marquee-ideas' ) ) . '">' . esc_html__( 'Suggest an idea', 'marquee' ) . '</a>';
	if ( MARQUEE_COFFEE_URL ) {
		$links[] = '<a href="' . esc_url( MARQUEE_COFFEE_URL ) . '" target="_blank" rel="noopener">☕ ' . esc_html__( 'Buy me a coffee', 'marquee' ) . '</a>';
	}
	return $links;
} );

/** "Run show nights from your phone" box on Marquee → Settings. */
function marquee_manager_box( $open = false ) {
	$url = marquee_manager_url();
	?>
	<details class="mq__mgr" <?php echo $open ? 'open' : ''; ?>>
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
	$unit  = (string) marquee_theme_get( 'names_geo_unit' ); // the owner's choice; until they pick, it follows the weather units
	$km    = '' !== $unit ? 'km' === $unit : 'metric' === ( isset( $s['units'] ) ? $s['units'] : '' ); // stored in miles either way
	$shown = $km ? (string) round( (float) $mi * 1.609344, 1 ) : $mi;
	$where = '' !== (string) $s['lat'] && '' !== (string) $s['lon'];
	?>
	<div class="mq__feat mq__feat--sub">
		<span>
<input type="hidden" name="feat_keys[]" value="names_geo">
			<label class="mq__geo-on"><input type="checkbox" name="feat[]" value="names_geo" <?php checked( (bool) marquee_theme_get( 'names_geo' ) ); ?>> <b><?php esc_html_e( 'Only at the show', 'marquee' ); ?></b></label>
			<label class="mq__geo-mi"><?php esc_html_e( 'within', 'marquee' ); ?> <input type="number" name="names_geo_miles" min="0.1" max="80" step="0.1" value="<?php echo esc_attr( $shown ); ?>" class="small-text" data-mq-geo-dist> <select name="names_geo_unit" data-mq-geo-unit aria-label="<?php esc_attr_e( 'Distance unit', 'marquee' ); ?>"><option value="mi" <?php selected( ! $km ); ?>><?php esc_html_e( 'miles', 'marquee' ); ?></option><option value="km" <?php selected( $km ); ?>><?php esc_html_e( 'km', 'marquee' ); ?></option></select> <?php esc_html_e( 'of your show', 'marquee' ); ?></label>
			<small><?php esc_html_e( 'When a kid sends a name, the phone asks to share its location. It is only compared with your show’s address and never saved. You and your Show helpers can still send test names from anywhere.', 'marquee' ); ?> <?php echo $km ? esc_html__( '1 km suits most shows; phones can be off by about 100 metres.', 'marquee' ) : esc_html__( '0.5 mile suits most shows; phones can be off by a few hundred feet.', 'marquee' ); ?></small>
			<?php if ( ! $where ) : ?><small class="mq__warn"><?php esc_html_e( 'Set your show’s address in Show details first: without it, names can be sent from anywhere.', 'marquee' ); ?></small><?php endif; ?>
		</span>
	</div>
	<?php
}

/** With another WordPress theme: the button that makes the show's pages. */
function marquee_make_pages_row( $setup ) {
	if ( 'ridgeroad' === get_template() ) {
		return; // the festive theme draws these pages itself
	}
	?>
	<h3><?php esc_html_e( 'Show pages', 'marquee' ); ?></h3>

					<label class="mq__feat"><span><b><?php esc_html_e( 'Make my show pages', 'marquee' ); ?></b><small><?php esc_html_e( 'Creates Home (the whole show, made your front page), Kids’ Corner and Songs & Videos with the show sections in them. Pages that already have content are left alone. You can edit them like any page.', 'marquee' ); ?></small></span><input type="checkbox" name="make_pages" value="1" <?php checked( $setup ); ?>></label>
	<?php
}

/** The on/off settings saved with the switches list (feat[]); everything else is a show setting (tm[]). */
function marquee_feat_keys() {
	return array( 'gb_show', 'names_on', 'names_geo', 'kids_show', 'lab_on', 'coloring_on', 'games_on', 'songs_show', 'contest_on', 'app_auto_on', 'sp_show', 'facts_show' );
}

/**
 * One on/off switch. Only the switches drawn on the page are changed when it is saved, so a page
 * without the switch never turns it off. $attr: extra attributes on the checkbox.
 */
function marquee_switch( $key, $label, $desc = '', $attr = '', $cls = '' ) {
	$on = (bool) marquee_theme_get( $key );
	if ( in_array( $key, marquee_feat_keys(), true ) ) {
		$pre  = '<input type="hidden" name="feat_keys[]" value="' . esc_attr( $key ) . '">';
		$name = 'feat[]';
		$val  = $key;
	} else {
		$pre  = '<input type="hidden" name="tm[' . esc_attr( $key ) . ']" value="0">';
		$name = 'tm[' . $key . ']';
		$val  = '1';
	}
	printf(
		'%1$s<label class="mq__feat%7$s"><span><b>%2$s</b>%3$s</span><input type="checkbox" name="%4$s" value="%5$s" %6$s data-mq-sw="%8$s"%9$s></label>',
		$pre, esc_html( $label ), $desc ? '<small>' . esc_html( $desc ) . '</small>' : '', esc_attr( $name ), esc_attr( $val ), checked( $on, true, false ), $cls ? ' ' . esc_attr( $cls ) : '', esc_attr( $key ), $attr ? ' ' . $attr : '' // phpcs:ignore WordPress.Security.EscapeOutput -- $pre and $attr are built here from fixed strings
	);
}

/**
 * One box of a page's settings: its name, a line about it, its switch (if it has one) and,
 * opened, all its words and settings (drawn after this, up to marquee_pbox_end()).
 */
function marquee_pbox( $id, $title, $sum = '', $key = '', $open = false ) {
	$on = $key ? (bool) marquee_theme_get( $key ) : null;
	?>
	<details class="mq-pbox<?php echo false === $on ? ' is-off' : ''; ?>" id="mq-box-<?php echo esc_attr( $id ); ?>" <?php echo $open ? 'open' : ''; ?>>
		<summary>
			<span class="mq-pbox__t"><b><?php echo esc_html( $title ); ?></b><?php if ( $sum ) : ?><small><?php echo esc_html( $sum ); ?></small><?php endif; ?></span>
			<?php if ( $key ) : ?>
				<?php
				$feat = in_array( $key, marquee_feat_keys(), true );
				echo $feat ? '<input type="hidden" name="feat_keys[]" value="' . esc_attr( $key ) . '">' : '<input type="hidden" name="tm[' . esc_attr( $key ) . ']" value="0">';
				?>
				<label class="mq-pbox__sw"><input type="checkbox" name="<?php echo esc_attr( $feat ? 'feat[]' : 'tm[' . $key . ']' ); ?>" value="<?php echo esc_attr( $feat ? $key : '1' ); ?>" <?php checked( $on ); ?> data-mq-sw="<?php echo esc_attr( $key ); ?>"><span class="mq-pbox__track" aria-hidden="true"></span><span class="mq-pbox__on" aria-hidden="true"><?php esc_html_e( 'On', 'marquee' ); ?></span><span class="mq-pbox__offl" aria-hidden="true"><?php esc_html_e( 'Off', 'marquee' ); ?></span><span class="screen-reader-text"><?php /* translators: %s: box name */ echo esc_html( sprintf( __( 'Show %s', 'marquee' ), $title ) ); ?></span></label>
			<?php endif; ?>
			<span class="mq-pbox__chev" aria-hidden="true"></span>
		</summary>
		<div class="mq-pbox__body">
	<?php
}

/** The end of a box opened with marquee_pbox(). */
function marquee_pbox_end() {
	echo '</div></details>';
}

/** "See it in action": an animated preview of what kids and visitors see. $mode: names | themes. */
function marquee_demo( $mode, $s ) {
	$themes = array();
	if ( 'themes' === $mode ) {
		foreach ( (array) $s['themes'] as $t ) {
			$themes[] = $t['name'];
		}
		if ( ! $themes ) {
			$themes = array( 'Candy Cane Swirl', 'Snowfall', 'Rainbow Party' );
		}
	}
	$voice = function_exists( 'marquee_voice_on' ) && marquee_voice_on();
	$lab   = function_exists( 'ridgeroad_asset_url' ) ? ridgeroad_asset_url( 'lightlab/' ) : MARQUEE_URL . 'core/assets/lightlab/';
	?>
	<div class="mq-demo" data-mq-demo data-lab="<?php echo esc_url( $lab ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>" data-themes="<?php echo esc_attr( wp_json_encode( $themes ) ); ?>" data-santa="<?php echo $s['voice_santa'] ? '1' : '0'; ?>" data-voice="1">
		<div class="mq-demo__label"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> <?php esc_html_e( 'See it in action', 'marquee' ); ?></div>
		<div class="mq-demo__stage">
			<div class="mq-demo__phone" aria-hidden="true"><div data-demo-phone></div></div>
			<div class="mq-demo__arrow" aria-hidden="true">→</div>
			<div class="mq-demo__tv">
				<canvas width="840" height="235" role="img" aria-label="<?php esc_attr_e( 'Your matrix or TV showing a kid’s name during the names break', 'marquee' ); ?>"></canvas>
				<div class="mq-demo__say" data-demo-say hidden></div>
				<div class="mq-demo__tvlabel"><?php esc_html_e( 'Your show: names go up on your matrix and TV', 'marquee' ); ?></div>
			</div>
		</div>
		<p class="mq-demo__step" data-demo-step aria-live="off"></p>
		<?php if ( ! $voice ) : ?><p class="mq-demo__note"><?php esc_html_e( 'The 🔊 line is what you hear on your radio station with “Say each name out loud” on.', 'marquee' ); ?></p><?php endif; ?>
	</div>
	<?php
}


/** A little live timeline of the names break: where each name goes up. Reads two number fields. */
function marquee_name_timeline( $start_sel, $slot_sel, $len, $compact = false ) {
	printf(
		'<div class="mq-nt%s" data-mq-nt data-start-in="%s" data-slot-in="%s" data-len="%s" aria-hidden="true"><div class="mq-nt__bar"></div><div class="mq-nt__axis"></div><p class="mq-nt__say"></p></div>',
		$compact ? ' is-compact' : '',
		esc_attr( $start_sel ),
		esc_attr( $slot_sel ),
		esc_attr( $len > 0 ? $len : 29.6 )
	);
}
