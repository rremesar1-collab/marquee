<?php
/**
 * Visitors pick the language: English, Español or Français.
 *
 * The site starts in Settings → General → Site Language. When the show ticks two or more
 * languages in Marquee → Settings → Website basics, a small language button shows on the
 * website; a visitor's pick is kept in a cookie for a year. Only the public website (and the
 * Show Manager) follows it; WP Admin keeps each admin's own language.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The languages Marquee comes with: code => [ name in its own language, locale ]. */
function marquee_langs_all() {
	return array(
		'en' => array( 'English', 'en_US' ),
		'es' => array( 'Español', 'es_ES' ),
		'fr' => array( 'Français', 'fr_FR' ),
	);
}

/** The site's own language as a two-letter code (from Settings → General). */
function marquee_lang_site() {
	$l = strtolower( substr( (string) get_option( 'WPLANG' ), 0, 2 ) );
	return isset( marquee_langs_all()[ $l ] ) ? $l : 'en';
}

/** The languages visitors can pick from ([] = no button: the site language only). */
function marquee_langs_on() {
	$s  = get_option( 'marquee', array() );
	$on = isset( $s['langs'] ) ? array_values( array_intersect( array_keys( marquee_langs_all() ), (array) $s['langs'] ) ) : array();
	if ( count( $on ) < 2 ) {
		return array();
	}
	if ( ! in_array( marquee_lang_site(), $on, true ) ) {
		array_unshift( $on, marquee_lang_site() ); // the site language is always one of them
	}
	return $on;
}

/** True for a request from the public website (not WP Admin, the login page or the command line). */
function marquee_lang_public() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}
	if ( wp_doing_ajax() ) { // the website's own background requests follow the visitor; WP Admin's don't
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : ''; // phpcs:ignore
		return false === strpos( $ref, '/wp-admin/' );
	}
	if ( is_admin() ) {
		return false;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore
	return false === strpos( $uri, 'wp-login.php' );
}

/** The visitor's pick: from ?mq_lang= (just clicked) or the cookie; '' = none. */
function marquee_lang_pick() {
	static $pick = null;
	if ( null !== $pick ) {
		return $pick;
	}
	$pick = '';
	$on   = marquee_langs_on();
	if ( ! $on || ! marquee_lang_public() ) {
		return $pick;
	}
	$want = isset( $_GET['mq_lang'] ) ? sanitize_key( wp_unslash( $_GET['mq_lang'] ) ) : ( isset( $_COOKIE['mq_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['mq_lang'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_GET['rr_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the live updates (sent without cookies) say the language
		$want = sanitize_key( wp_unslash( $_GET['rr_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( in_array( $want, $on, true ) ) {
		$pick = $want;
	}
	return $pick;
}

/** The locale for a language code: the site's own variant when it's the site language (fr_CA stays fr_CA). */
function marquee_lang_locale( $code ) {
	$site = (string) get_option( 'WPLANG' );
	if ( $code === marquee_lang_site() && '' !== $site ) {
		return $site;
	}
	$all = marquee_langs_all();
	return $all[ $code ][1];
}

/* The page is built in the visitor's language (Marquee, the theme and WordPress itself). */
function marquee_lang_filter( $locale ) {
	$p = marquee_lang_pick();
	return $p ? marquee_lang_locale( $p ) : $locale;
}
add_filter( 'determine_locale', 'marquee_lang_filter', 20 );
add_filter( 'locale', 'marquee_lang_filter', 20 );

/* The live updates are sent without cookies: they carry the visitor's language in the address. */
add_filter( 'ridgeroad_live_url', function ( $url ) {
	return marquee_lang_pick() ? add_query_arg( 'rr_lang', marquee_lang_pick(), $url ) : $url;
} );

/* A click on the button: keep the pick for a year, then show the same page without ?mq_lang. */
add_action( 'init', function () {
	if ( ! isset( $_GET['mq_lang'] ) || ! marquee_lang_pick() ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	setcookie( 'mq_lang', marquee_lang_pick(), array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ) );
	wp_safe_redirect( remove_query_arg( 'mq_lang' ) );
	exit;
}, 1 );

/* Pages in a picked language mustn't be served to everyone from a page cache. */
add_action( 'template_redirect', function () {
	if ( marquee_langs_on() && ( isset( $_COOKIE['mq_lang'] ) ) ) { // phpcs:ignore
		defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );
		nocache_headers();
	}
} );

/** The language button's links: [ code => [ name, url, current? ] ]. */
function marquee_lang_links() {
	$on = marquee_langs_on();
	if ( ! $on ) {
		return array();
	}
	$cur = marquee_lang_pick() ? marquee_lang_pick() : marquee_lang_site();
	$all = marquee_langs_all();
	$out = array();
	foreach ( $on as $code ) {
		$out[ $code ] = array( $all[ $code ][0], add_query_arg( 'mq_lang', $code ), $code === $cur );
	}
	return $out;
}

/* The button: top corner of every page of the website. */
add_action( 'wp_body_open', 'marquee_lang_button' );
add_action( 'wp_footer', 'marquee_lang_button' ); // themes that don't call wp_body_open
function marquee_lang_button() {
	static $done = false;
	$links = marquee_lang_links();
	if ( $done || ! $links || ! marquee_lang_public() ) {
		return;
	}
	$done = true;
	echo '<nav class="mq-lang" aria-label="Language / Idioma / Langue">';
	foreach ( $links as $code => $l ) {
		printf(
			'<a href="%1$s" hreflang="%2$s" lang="%2$s"%3$s>%4$s</a>',
			esc_url( $l[1] ),
			esc_attr( $code ),
			$l[2] ? ' class="is-on" aria-current="true"' : '',
			esc_html( $l[0] )
		);
	}
	echo '</nav>';
}
add_action( 'wp_head', function () {
	if ( ! marquee_lang_links() || ! marquee_lang_public() ) {
		return;
	}
	echo '<style id="mq-lang-css">.mq-lang{position:fixed;top:12px;right:14px;z-index:9999;display:flex;gap:2px;padding:3px;border-radius:999px;background:rgba(8,14,36,.82);box-shadow:0 2px 10px rgba(0,0,0,.35),inset 0 0 0 1px rgba(255,215,130,.45);font:600 13px/1 system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}'
		. '.mq-lang a{padding:7px 11px;border-radius:999px;color:#fff3d6;text-decoration:none}.mq-lang a:hover,.mq-lang a:focus-visible{background:rgba(255,255,255,.14);color:#fff;outline:0}'
		. '.mq-lang a.is-on{background:#f2c75a;color:#2a1600}.admin-bar .mq-lang{top:44px}'
		. '@media (max-width:600px){.mq-lang{top:auto;bottom:14px;right:50%;transform:translateX(50%);font-size:12.5px}.admin-bar .mq-lang{top:auto}}</style>' . "\n";
	// tell search engines which languages the page comes in
	foreach ( marquee_lang_links() as $code => $l ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $code ), esc_url( $l[1] ) );
	}
}, 5 );

/** Settings: the language choices (Marquee → Settings → Website basics). */
function marquee_langs_box( $s ) {
	$on   = isset( $s['langs'] ) ? (array) $s['langs'] : array();
	$site = marquee_lang_site();
	?>
	<div class="mq-sec" id="mq-sec-langs">
		<h3 class="mq-sec__title"><?php esc_html_e( 'Languages', 'marquee' ); ?></h3>
		<input type="hidden" name="langs_sent" value="1">
		<p class="mq__fine"><?php esc_html_e( 'Your website starts in the language picked in Settings → General → Site Language. Tick two or more to show a small language button on your website, so visitors can switch. Their pick is remembered on their phone.', 'marquee' ); ?></p>
		<div class="mq__checks mq-langs">
			<?php foreach ( marquee_langs_all() as $code => $l ) : ?>
				<label><input type="checkbox" name="langs[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $on, true ) || ( $code === $site && count( $on ) > 1 ) ); ?>> <?php echo esc_html( $l[0] ); ?><?php echo $code === $site ? ' <small>(' . esc_html__( 'your Site Language', 'marquee' ) . ')</small>' : ''; ?></label>
			<?php endforeach; ?>
		</div>
		<p class="mq__fine"><?php esc_html_e( 'Words you typed yourself (your banner messages, tips and button words in Customize) show the way you wrote them. With two or more languages ticked, Customize has an extra box under each of them for every other language: fill it in and visitors who pick that language see those words instead.', 'marquee' ); ?></p>
		<p class="mq__fine"><?php esc_html_e( 'Using a page cache plugin (WP Rocket, LiteSpeed Cache, W3 Total Cache…)? Add the cookie mq_lang to its list of cookies that skip the cache, so each visitor gets their own language.', 'marquee' ); ?></p>
	</div>
	<?php
}

/*
 * Day and month names ("Friday 27 November") come from WordPress's own language files, which most
 * sites only have in their Site Language. When they're missing for the language a visitor picked,
 * Marquee supplies the names, so dates read right too.
 */
function marquee_lang_dates( $lang ) {
	static $names = array(
		'es' => array(
			'Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado',
			'Sun' => 'dom', 'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mié', 'Thu' => 'jue', 'Fri' => 'vie', 'Sat' => 'sáb',
			'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
			'Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic',
		),
		'fr' => array(
			'Sunday' => 'dimanche', 'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi',
			'Sun' => 'dim', 'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mer', 'Thu' => 'jeu', 'Fri' => 'ven', 'Sat' => 'sam',
			'January' => 'janvier', 'February' => 'février', 'March' => 'mars', 'April' => 'avril', 'May' => 'mai', 'June' => 'juin', 'July' => 'juillet', 'August' => 'août', 'September' => 'septembre', 'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre',
			'Jan' => 'janv', 'Feb' => 'févr', 'Mar' => 'mars', 'Apr' => 'avr', 'Jun' => 'juin', 'Jul' => 'juil', 'Aug' => 'août', 'Sep' => 'sept', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'déc',
		),
	);
	return isset( $names[ $lang ] ) ? $names[ $lang ] : array();
}
function marquee_lang_date_word( $tr, $text ) {
	if ( $tr !== $text || ! marquee_lang_public() ) {
		return $tr; // WordPress already has it in this language
	}
	$map = marquee_lang_dates( strtolower( substr( determine_locale(), 0, 2 ) ) );
	return isset( $map[ $text ] ) ? $map[ $text ] : $tr;
}
add_filter( 'gettext_default', 'marquee_lang_date_word', 20, 2 );
add_filter( 'gettext_with_context_default', function ( $tr, $text, $ctx ) {
	return ( false !== strpos( $ctx, 'abbreviation' ) || false !== strpos( $ctx, 'genitive' ) ) ? marquee_lang_date_word( $tr, $text ) : $tr;
}, 20, 3 );
