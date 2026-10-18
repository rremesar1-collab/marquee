<?php
/**
 * Show Manager: a phone-friendly page on the show's own website (yourshow.com/manage)
 * for show nights, so nobody has to open the WordPress dashboard.
 *
 * - Uses the same switches and data as WP Admin → Show Control.
 * - Sign-in is the normal WordPress account (styled sign-in form on the page).
 * - "Show helper" role: can use the Show Manager and nothing else in WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MARQUEE_MANAGE_SLUG = 'manage';

/** Show Manager address. */
function marquee_manager_url( $args = array() ) {
	$url = get_option( 'permalink_structure' ) ? home_url( '/' . MARQUEE_MANAGE_SLUG . '/' ) : add_query_arg( 'marquee_manage', '1', home_url( '/' ) );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/** Can the signed-in person run show nights? */
function marquee_can_manage() {
	return current_user_can( 'manage_options' ) || current_user_can( 'marquee_manage' );
}

/** A Show helper account (no other WordPress access). */
function marquee_is_helper( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && $user->exists() && $user->has_cap( 'marquee_manage' ) && ! $user->has_cap( 'edit_posts' );
}

/* ---- Role ---- */
function marquee_add_role() {
	if ( ! get_role( 'marquee_helper' ) ) {
		add_role( 'marquee_helper', __( 'Show helper', 'marquee' ), array( 'read' => true, 'marquee_manage' => true ) );
	}
}
add_action( 'init', function () {
	if ( '1' !== get_option( 'marquee_roles_v' ) ) {
		marquee_add_role();
		update_option( 'marquee_roles_v', '1' );
	}
}, 5 );

/* ---- Address: /manage ---- */
add_action( 'init', function () {
	add_rewrite_rule( '^' . MARQUEE_MANAGE_SLUG . '/?$', 'index.php?marquee_manage=1', 'top' );
} );
// save the address once, after every plugin and the theme have added theirs
add_action( 'wp_loaded', function () {
	if ( 'm2' !== get_option( 'marquee_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'marquee_rw', 'm2' );
	}
} );
/** Is this request for /manage, even if the saved addresses are out of date? */
function marquee_is_manage_request() {
	if ( get_query_var( 'marquee_manage' ) ) {
		return true;
	}
	if ( ! is_404() ) {
		return false;
	}
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
	$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) + 1 );
	}
	return MARQUEE_MANAGE_SLUG === $path;
}
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'marquee_manage';
	return $v;
} );
add_action( 'template_redirect', function () {
	if ( ! marquee_is_manage_request() ) {
		return;
	}
	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	if ( isset( $_GET['signout'] ) && is_user_logged_in() && check_admin_referer( 'mq_signout' ) ) { // phpcs:ignore
		wp_logout();
		wp_safe_redirect( marquee_manager_url() );
		exit;
	}
	marquee_manager_render();
	exit;
}, 0 );

/* ---- Helpers stay out of the dashboard ---- */
add_filter( 'login_redirect', function ( $to, $requested, $user ) {
	return ( $user instanceof WP_User && marquee_is_helper( $user ) ) ? marquee_manager_url() : $to;
}, 20, 3 );
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! marquee_is_helper() ) {
		return;
	}
	global $pagenow;
	if ( 'profile.php' === $pagenow || 'admin-post.php' === $pagenow ) { // they can still change their password
		return;
	}
	wp_safe_redirect( marquee_manager_url() );
	exit;
} );
add_action( 'admin_page_access_denied', function () { // a bookmarked settings page → Show Manager, not an error
	if ( marquee_is_helper() ) {
		wp_safe_redirect( marquee_manager_url() );
		exit;
	}
} );
add_filter( 'show_admin_bar', function ( $show ) {
	return marquee_is_helper() ? false : $show;
} );

/* ---- Sign-in from the Show Manager: a wrong password comes back to the page ---- */
add_filter( 'authenticate', function ( $user ) {
	if ( is_wp_error( $user ) && ! empty( $_POST['mq_mgr'] ) && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore
		$code = $user->get_error_code();
		wp_safe_redirect( marquee_manager_url( array( 'signin' => in_array( $code, array( 'empty_username', 'empty_password' ), true ) ? 'empty' : 'wrong' ) ) );
		exit;
	}
	return $user;
}, 100 );

/* ---- Links to it from WP Admin ---- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'marquee', __( 'Show Manager', 'marquee' ), __( 'Show Manager ↗', 'marquee' ), 'manage_options', 'marquee-manager', '__return_null' );
}, 20 );
add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'marquee-manager' === $_GET['page'] && current_user_can( 'manage_options' ) ) { // phpcs:ignore
		wp_safe_redirect( marquee_manager_url() );
		exit;
	}
}, 1 );
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! marquee_can_manage() ) {
		return;
	}
	$bar->add_node( array( 'id' => 'marquee-manager', 'parent' => 'site-name', 'title' => __( 'Show Manager', 'marquee' ), 'href' => marquee_manager_url() ) );
}, 80 );

/* ---------------------------------------------------------------------------
 * The page
 * ------------------------------------------------------------------------ */
function marquee_manager_logo() {
	$id = (int) get_theme_mod( 'custom_logo' );
	$u  = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	return $u ? $u : get_site_icon_url( 128 );
}

function marquee_manager_render() {
	$name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$logo   = marquee_manager_logo();
	$signed = is_user_logged_in();
	$can    = $signed && marquee_can_manage() && function_exists( 'ridgeroad_control_state' );
	$v      = MARQUEE_VERSION;
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1736">
<title><?php echo esc_html( sprintf( /* translators: %s: show name */ __( 'Show Manager · %s', 'marquee' ), $name ) ); ?></title>
<?php if ( get_site_icon_url() ) : ?><link rel="icon" href="<?php echo esc_url( get_site_icon_url( 192 ) ); ?>"><link rel="apple-touch-icon" href="<?php echo esc_url( get_site_icon_url( 180 ) ); ?>"><?php endif; ?>
<style>@font-face{font-family:"Fredoka";font-weight:600;font-display:swap;src:url(<?php echo esc_url( MARQUEE_URL . 'core/assets/fonts/fredoka-latin-600-normal.woff2' ); ?>) format("woff2")}</style>
<link rel="stylesheet" href="<?php echo esc_url( MARQUEE_URL . 'assets/manager.css?ver=' . $v ); ?>">
</head>
<body class="mm<?php echo $can ? ' mm--app' : ' mm--gate'; ?>">
<header class="mm-top">
	<a class="mm-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?>
		<span><b><?php echo esc_html( $name ); ?></b><small><?php esc_html_e( 'Show Manager', 'marquee' ); ?></small></span>
	</a>
	<?php if ( $signed ) : ?>
		<a class="mm-out" href="<?php echo esc_url( wp_nonce_url( marquee_manager_url( array( 'signout' => 1 ) ), 'mq_signout' ) ); ?>"><?php esc_html_e( 'Sign out', 'marquee' ); ?></a>
	<?php endif; ?>
</header>
<?php
if ( ! $signed ) {
	marquee_manager_signin();
} elseif ( ! $can ) {
	?>
	<main class="mm-gate">
		<h1><?php esc_html_e( 'This account can’t manage the show', 'marquee' ); ?></h1>
		<p><?php echo esc_html( sprintf( /* translators: %s: user name */ __( 'You’re signed in as %s. Ask the show owner to make your account a Show helper, or sign out and use the owner’s account.', 'marquee' ), wp_get_current_user()->display_name ) ); ?></p>
		<p><a class="mm-btn pri" href="<?php echo esc_url( wp_nonce_url( marquee_manager_url( array( 'signout' => 1 ) ), 'mq_signout' ) ); ?>"><?php esc_html_e( 'Sign out', 'marquee' ); ?></a></p>
	</main>
	<?php
} else {
	marquee_manager_app();
}
?>
</body>
</html>
	<?php
}

function marquee_manager_signin() {
	$err = isset( $_GET['signin'] ) ? sanitize_key( $_GET['signin'] ) : ''; // phpcs:ignore
	?>
	<main class="mm-gate">
		<h1><?php esc_html_e( 'Sign in to run your show', 'marquee' ); ?></h1>
		<p><?php esc_html_e( 'Use your website account. On your own phone, leave “Keep me signed in” on and you won’t be asked again.', 'marquee' ); ?></p>
		<?php if ( $err ) : ?>
			<p class="mm-err" role="alert"><?php echo 'empty' === $err ? esc_html__( 'Type your username and password.', 'marquee' ) : esc_html__( 'That username or password isn’t right. Try again.', 'marquee' ); ?></p>
		<?php endif; ?>
		<form class="mm-form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
			<label><span><?php esc_html_e( 'Username or email', 'marquee' ); ?></span><input type="text" name="log" autocomplete="username" autocapitalize="off" required></label>
			<label><span><?php esc_html_e( 'Password', 'marquee' ); ?></span><input type="password" name="pwd" autocomplete="current-password" required></label>
			<label class="mm-check"><input type="checkbox" name="rememberme" value="forever" checked> <?php esc_html_e( 'Keep me signed in', 'marquee' ); ?></label>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( marquee_manager_url() ); ?>">
			<input type="hidden" name="mq_mgr" value="1">
			<button class="mm-btn pri big" type="submit"><?php esc_html_e( 'Sign in', 'marquee' ); ?></button>
		</form>
		<p class="mm-fine"><a href="<?php echo esc_url( wp_lostpassword_url( marquee_manager_url() ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'marquee' ); ?></a></p>
	</main>
	<?php
}

function marquee_manager_app() {
	$admin = current_user_can( 'manage_options' );
	$cfg   = array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'rr_ctl' ),
		'admin' => $admin,
	);
	$toggle = function ( $key, $label, $help, $cls = '' ) {
		printf(
			'<div class="mm-sw"><div><b>%s</b><small>%s</small></div><button type="button" class="mm-tog %s" role="switch" aria-checked="false" data-mm-tog="%s" aria-label="%s"></button></div>',
			esc_html( $label ), esc_html( $help ), esc_attr( $cls ), esc_attr( $key ), esc_attr( $label )
		);
	};
	?>
	<main class="mm-app" data-mm>
		<section class="mm-status" aria-label="<?php esc_attr_e( 'Right now', 'marquee' ); ?>">
			<div class="mm-st" data-mm-st="show"><small><?php esc_html_e( 'Tonight’s show', 'marquee' ); ?></small><b data-mm="show">…</b><span data-mm="show-sub"></span></div>
			<div class="mm-st" data-mm-st="fpp"><small><?php esc_html_e( 'Now playing', 'marquee' ); ?></small><b data-mm="fpp">…</b><span data-mm="fpp-sub"></span></div>
			<div class="mm-st" data-mm-st="names"><small><?php esc_html_e( 'Names in Lights', 'marquee' ); ?></small><b data-mm="names">…</b><span data-mm="names-sub"></span></div>
			<div class="mm-st"><small><?php esc_html_e( 'On the website', 'marquee' ); ?></small><b data-mm="people">…</b><span data-mm="people-sub"></span></div>
		</section>

		<div class="mm-panes">
		<section class="mm-pane is-on" id="mm-tonight" data-mm-pane="tonight" aria-labelledby="mm-t-tonight">
			<div class="mm-card mm-cancel" data-mm-cancel>
				<div data-mm-cancel-off>
					<h2><?php esc_html_e( 'Weather or trouble?', 'marquee' ); ?></h2>
					<p><?php esc_html_e( 'Shows the “No show tonight” banner on your website until 6 AM.', 'marquee' ); ?></p>
					<button type="button" class="mm-btn del big" data-mm-cancel-ask><?php esc_html_e( 'Cancel tonight’s show', 'marquee' ); ?></button>
					<div class="mm-confirm" data-mm-cancel-confirm hidden>
						<p><b><?php esc_html_e( 'Cancel tonight’s show?', 'marquee' ); ?></b></p>
						<label class="mm-check" data-mm-cancel-alert-row><input type="checkbox" data-mm-cancel-alert checked> <span data-mm="cancel-alert-label"></span></label>
						<p class="mm-btns"><button type="button" class="mm-btn del" data-mm-cancel-yes><?php esc_html_e( 'Yes, cancel it', 'marquee' ); ?></button><button type="button" class="mm-btn" data-mm-cancel-no><?php esc_html_e( 'Keep it on', 'marquee' ); ?></button></p>
					</div>
				</div>
				<div data-mm-cancel-on hidden>
					<h2><?php esc_html_e( 'Tonight’s show is canceled', 'marquee' ); ?></h2>
					<p><?php esc_html_e( 'Your website shows the “No show tonight” banner until 6 AM.', 'marquee' ); ?></p>
					<button type="button" class="mm-btn big" data-mm-cancel-undo><?php esc_html_e( 'Undo: the show is on', 'marquee' ); ?></button>
				</div>
			</div>

			<div class="mm-card">
				<h2><?php esc_html_e( 'On the website', 'marquee' ); ?></h2>
				<?php
				$toggle( 'names_on', __( 'Your Name in Lights', 'marquee' ), __( 'Kids can send names to your screens', 'marquee' ) );
				$toggle( 'contest_on', __( 'Light show contest', 'marquee' ), __( 'Entries, Show Wall and hearts', 'marquee' ) );
				$toggle( 'songs_show', __( 'Favorite song voting', 'marquee' ), __( 'On the Songs & Videos page', 'marquee' ) );
				$toggle( 'kids_show', __( 'Kids’ Corner', 'marquee' ), __( 'The whole kids’ page', 'marquee' ) );
				?>
				<div class="mm-sw"><div><b><?php esc_html_e( 'Falling snow', 'marquee' ); ?></b><small><?php esc_html_e( 'On every page', 'marquee' ); ?></small></div>
					<select data-mm-set="snow_amount" aria-label="<?php esc_attr_e( 'Falling snow', 'marquee' ); ?>"><option value="off"><?php esc_html_e( 'Off', 'marquee' ); ?></option><option value="light"><?php esc_html_e( 'Light', 'marquee' ); ?></option><option value="medium"><?php esc_html_e( 'Medium', 'marquee' ); ?></option><option value="heavy"><?php esc_html_e( 'Heavy', 'marquee' ); ?></option></select></div>
			</div>

			<div class="mm-card">
				<h2><?php esc_html_e( 'Tonight so far', 'marquee' ); ?></h2>
				<div class="mm-tiles" data-mm-tiles></div>
			</div>
		</section>

		<section class="mm-pane" id="mm-names" data-mm-pane="names" aria-labelledby="mm-t-names">
			<div class="mm-card">
				<?php
				$toggle( 'paused', __( 'Pause the name queue', 'marquee' ), __( 'Names still come in, but wait until you turn this off', 'marquee' ) );
				$toggle( 'names_always', __( 'Accept names any time', 'marquee' ), __( 'For testing outside show hours', 'marquee' ) );
				$toggle( 'names_geo', __( 'Only at the show', 'marquee' ), __( 'Names only from phones near your show', 'marquee' ) );
				?>
			</div>
			<div class="mm-card">
				<h2><?php esc_html_e( 'Waiting for the next break', 'marquee' ); ?></h2>
				<ul class="mm-list mm-list--names" data-mm-list="waiting"></ul>
				<p class="mm-fine"><?php esc_html_e( '“Block” removes the name and adds it to your block list, so it can’t be sent again.', 'marquee' ); ?></p>
			</div>
			<div class="mm-card">
				<h2><?php esc_html_e( 'Just shown', 'marquee' ); ?> <span class="mm-count" data-mm="shown-n"></span></h2>
				<ul class="mm-list" data-mm-list="shown"></ul>
			</div>
		</section>

		<section class="mm-pane" id="mm-approve" data-mm-pane="approve" aria-labelledby="mm-t-approve">
			<div class="mm-card">
				<h2><?php esc_html_e( 'Light show contest', 'marquee' ); ?> <span class="mm-sub" data-mm="contest-sub"></span></h2>
				<ul class="mm-list mm-list--entries" data-mm-list="pending"></ul>
			</div>
			<div class="mm-card" data-mm-gb-card>
				<h2><?php esc_html_e( 'Guestbook messages', 'marquee' ); ?></h2>
				<ul class="mm-list mm-list--gb" data-mm-list="gb"></ul>
			</div>
		</section>

		<section class="mm-pane" id="mm-alerts" data-mm-pane="alerts" aria-labelledby="mm-t-alerts">
			<div class="mm-card" data-mm-send-card>
				<h2><label for="mm-msg"><?php esc_html_e( 'Send an alert', 'marquee' ); ?></label></h2>
				<p class="mm-fine" data-mm="app-to"></p>
				<textarea id="mm-msg" rows="3" maxlength="180" data-mm-msg placeholder="<?php esc_attr_e( 'Santa is visiting Saturday from 6 to 8 PM!', 'marquee' ); ?>"></textarea>
				<div class="mm-sendbar"><small><?php esc_html_e( 'Phones show about two lines.', 'marquee' ); ?></small><span data-mm="msg-count">0 / 180</span></div>
				<p class="mm-btns" data-mm-send-row><button type="button" class="mm-btn pri big" data-mm-send><?php esc_html_e( 'Send alert', 'marquee' ); ?></button></p>
				<div class="mm-confirm" data-mm-send-confirm hidden>
					<p><b data-mm="send-q"></b></p>
					<p class="mm-btns"><button type="button" class="mm-btn pri" data-mm-send-yes><?php esc_html_e( 'Yes, send it', 'marquee' ); ?></button><button type="button" class="mm-btn" data-mm-send-no><?php esc_html_e( 'Not yet', 'marquee' ); ?></button></p>
				</div>
			</div>
			<p class="mm-card mm-note" data-mm-noapp hidden><?php esc_html_e( 'Alerts need your website on https with a recent PHP. Ask your web host if alerts don’t work.', 'marquee' ); ?></p>
			<div class="mm-card">
				<?php
				$toggle( 'app_auto_on', __( '“The lights are on!” alert', 'marquee' ), __( 'Sent once a night when the show starts', 'marquee' ) );
				$toggle( 'app_buzz_on', __( '“Your name is up next” buzz', 'marquee' ), __( 'Kids can ask for a buzz when they send a name', 'marquee' ) );
				?>
			</div>
			<div class="mm-card">
				<h2><?php esc_html_e( 'Recent alerts', 'marquee' ); ?></h2>
				<ul class="mm-list" data-mm-list="alerts"></ul>
			</div>
		</section>
		</div>

		<?php if ( $admin ) : ?>
			<p class="mm-fine mm-foot"><?php esc_html_e( 'Setup, artwork and connections:', 'marquee' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=marquee' ) ); ?>"><?php esc_html_e( 'Marquee settings in WP Admin', 'marquee' ); ?></a></p>
		<?php endif; ?>
	</main>

	<nav class="mm-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Show Manager', 'marquee' ); ?>">
		<?php
		$tabs = array(
			'tonight' => array( __( 'Tonight', 'marquee' ), '<path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3 5 5M19 19l-1.3-1.3M6.3 17.7 5 19M19 5l-1.3 1.3"/><circle cx="12" cy="12" r="4"/>' ),
			'names'   => array( __( 'Names', 'marquee' ), '<path d="M4 7V5h16v2M9 19h6M12 5v14"/>' ),
			'approve' => array( __( 'Approve', 'marquee' ), '<path d="m5 12 4 4L19 6"/>' ),
			'alerts'  => array( __( 'Alerts', 'marquee' ), '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0"/>' ),
		);
		foreach ( $tabs as $k => $t ) {
			printf(
				'<button type="button" role="tab" id="mm-t-%1$s" aria-controls="mm-%1$s" aria-selected="%2$s" data-mm-tab="%1$s"><svg viewBox="0 0 24 24" aria-hidden="true">%3$s</svg><span>%4$s</span><i class="mm-badge" data-mm-badge="%1$s" hidden></i></button>',
				esc_attr( $k ), 'tonight' === $k ? 'true' : 'false', $t[1], esc_html( $t[0] ) // phpcs:ignore -- fixed SVG
			);
		}
		?>
	</nav>
	<div class="mm-toast" data-mm-toast role="status" aria-live="polite"></div>
	<script>window.MQMgr = <?php echo wp_json_encode( $cfg ); ?>;</script>
	<script src="<?php echo esc_url( MARQUEE_URL . 'assets/manager.js?ver=' . MARQUEE_VERSION ); ?>"></script>
	<?php
}

/* ---- A quiet "Show owner" link at the very bottom of every page ---- */
add_action( 'wp_footer', function () {
	if ( is_admin() || ! apply_filters( 'marquee_owner_link', true ) ) {
		return;
	}
	printf(
		'<p class="mq-owner"><a href="%s" rel="nofollow">%s</a></p><style>.mq-owner{margin:0;padding:10px 16px 14px;text-align:center;font-size:12px;line-height:1.4}.rr-footer .mq-owner{padding:6px 0 0}.rr-footer .mq-owner a{opacity:.8}.mq-owner a{color:inherit;opacity:.6;text-decoration:none}.mq-owner a:hover,.mq-owner a:focus-visible{opacity:1;text-decoration:underline}</style>'
		// the festive theme scrolls inside its own frame: put the link inside its footer
		. '<script>(function(){var f=document.querySelector(".rr-footer"),l=document.querySelector(".mq-owner");if(f&&l){f.appendChild(l);}})();</script>',
		esc_url( marquee_manager_url() ),
		esc_html__( 'Show owner', 'marquee' )
	);
}, 50 );
