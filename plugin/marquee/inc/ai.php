<?php
/**
 * "Need help?" assistant on the Marquee Settings page.
 *
 * Answers questions about Marquee with Claude (Anthropic), using the help guide that
 * comes with the plugin (inc/ai-guide.md) plus this site's live status: versions,
 * what's switched on, the FPP's last setup check and recent errors. Passwords and keys
 * are never sent.
 *
 * Who pays: a show owner can paste their own Claude API key (Settings → the help
 * panel). A host (for example Alateka's hosted show sites) can include it instead by
 * adding to wp-config.php:
 *     define( 'MARQUEE_AI_KEY', 'sk-ant-...' );      // the host's key, never shown
 *     define( 'MARQUEE_AI_DAILY', 40 );               // optional: questions per day
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MARQUEE_AI_API = 'https://api.anthropic.com/v1/messages';

/** The key to use, and whether it's the host's (included) or the owner's own. */
function marquee_ai_key() {
	if ( defined( 'MARQUEE_AI_KEY' ) && MARQUEE_AI_KEY ) {
		return array( (string) MARQUEE_AI_KEY, 'host' );
	}
	$k = (string) get_option( 'marquee_ai_key', '' );
	return $k ? array( $k, 'own' ) : array( '', '' );
}

function marquee_ai_model() {
	return (string) apply_filters( 'marquee_ai_model', defined( 'MARQUEE_AI_MODEL' ) ? MARQUEE_AI_MODEL : 'claude-haiku-5-5' );
}

/** Questions allowed per day (the host's key is limited; an own key only has a safety cap). */
function marquee_ai_daily() {
	list( , $who ) = marquee_ai_key();
	if ( 'host' === $who ) {
		return max( 1, (int) ( defined( 'MARQUEE_AI_DAILY' ) ? MARQUEE_AI_DAILY : 40 ) );
	}
	return 200;
}

/** This site's status for the assistant. No passwords, keys or visitor data. */
function marquee_ai_status() {
	$s    = marquee_get();
	$tm   = 'marquee_theme_get';
	$rep  = get_option( 'ridgeroad_names_report' );
	$seen = (int) get_option( 'ridgeroad_names_seen' );
	$verr = get_option( 'marquee_voice_error' );
	$brk  = function_exists( 'marquee_break_info' ) ? marquee_break_info() : null;
	$out  = array(
		'marquee_version'   => MARQUEE_VERSION,
		'wordpress'         => get_bloginfo( 'version' ),
		'theme'             => get_template() . ( 'ridgeroad' === get_template() ? ' (Marquee Festive)' : '' ),
		'https'             => 0 === strpos( home_url(), 'https://' ),
		'show_name'         => $s['show_name'],
		'country'           => $s['country'],
		'location_set'      => '' !== (string) $s['lat'],
		'season'            => $tm( 'season_start' ) . ' to ' . $tm( 'season_end' ),
		'show_status_now'   => function_exists( 'ridgeroad_status' ) ? ridgeroad_status()['state'] : '',
		'remote_falcon'     => function_exists( 'marquee_rf_configured' ) && marquee_rf_configured() ? 'keys saved' : 'not set up',
		'features_on'       => array_values( array_filter( array( 'gb_show', 'names_on', 'kids_show', 'lab_on', 'contest_on', 'songs_show', 'app_auto_on' ), function ( $k ) use ( $tm ) {
			return (bool) $tm( $k );
		} ) ),
		'names'             => array(
			'only_at_show'      => (bool) $tm( 'names_geo' ),
			'break_playlist'    => $s['fpp_playlist'],
			'first_name_at'     => $s['names_start'],
			'seconds_per_name'  => $s['names_slot'],
			'screens'           => array_map( function ( $r ) {
				return $r['name'] . ': model ' . $r['model'] . ' on ' . $r['fpp'];
			}, (array) $s['fpp_screens'] ),
			'fpp_last_check_in' => $seen ? human_time_diff( $seen ) . ' ago' : 'never',
			'fpp_script'        => is_array( $rep ) && ! empty( $rep['v'] ) ? $rep['v'] : 'unknown',
			'fpp_setup_check'   => is_array( $rep ) && ! empty( $rep['checks'] ) ? array_map( function ( $c ) {
				return ( $c['ok'] ? 'OK: ' : 'PROBLEM: ' ) . $c['msg'];
			}, $rep['checks'] ) : array(),
			'setup_check_from'  => is_array( $rep ) && ! empty( $rep['t'] ) ? human_time_diff( (int) $rep['t'] ) . ' ago' : '',
			'waiting_names'     => function_exists( 'ridgeroad_name_queue' ) ? count( ridgeroad_name_queue( 50 ) ) : 0,
		),
		'spoken_names'      => array(
			'on'          => function_exists( 'marquee_voice_on' ) && marquee_voice_on(),
			'key_saved'   => '' !== (string) $s['voice_key'],
			'voice'       => $s['voice_name'],
			'santa'       => (bool) $s['voice_santa'],
			'speed'       => $s['voice_speed'],
			'last_error'  => is_array( $verr ) && ! empty( $verr['msg'] ) ? $verr['msg'] . ' (' . human_time_diff( (int) $verr['t'] ) . ' ago)' : '',
		),
		'names_break_sound' => $brk ? 'built ' . human_time_diff( (int) $brk['t'] ) . ' ago, names start ' . $brk['start'] . ' s, every ' . $brk['slot'] . ' s' : 'not built',
		'themed_sequences'  => ! empty( $s['themes_on'] ) ? array_map( function ( $t ) {
			return $t['name'] . ' → playlist "' . $t['playlist'] . '"';
		}, (array) $s['themes'] ) : 'off',
	);
	return $out;
}

/** The system prompt: the guide (cached by the API) and this site's status. */
function marquee_ai_system() {
	$guide = (string) file_get_contents( MARQUEE_DIR . 'inc/ai-guide.md' ); // phpcs:ignore
	$extra = '';
	$names = MARQUEE_DIR . 'core/fpp/marquee-names-setup-guide.txt';
	if ( file_exists( $names ) ) {
		$extra = "\n\n## The FPP setup guide (verbatim)\n\n" . file_get_contents( $names ); // phpcs:ignore
	}
	return array(
		array(
			'type'          => 'text',
			'text'          => "You are the help assistant inside the Marquee WordPress plugin's settings page. You help the show owner set up and fix their light show website and FPP connection.\n\n" . $guide . $extra,
			'cache_control' => array( 'type' => 'ephemeral' ),
		),
		array(
			'type' => 'text',
			'text' => "## This site right now\n\n" . wp_json_encode( marquee_ai_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		),
	);
}

/* Ask a question. */
add_action( 'wp_ajax_marquee_ai_ask', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	list( $key ) = marquee_ai_key();
	if ( ! $key ) {
		wp_send_json_error( array( 'message' => __( 'Add a Claude API key first.', 'marquee' ), 'need_key' => true ) );
	}
	$day  = 'marquee_ai_n_' . wp_date( 'Ymd' );
	$used = (int) get_transient( $day );
	if ( $used >= marquee_ai_daily() ) {
		wp_send_json_error( array( 'message' => __( 'That’s all the questions for today. Try again tomorrow, or use Send feedback.', 'marquee' ) ) );
	}
	$raw  = isset( $_POST['messages'] ) ? json_decode( wp_unslash( $_POST['messages'] ), true ) : array(); // phpcs:ignore
	$msgs = array();
	foreach ( array_slice( is_array( $raw ) ? $raw : array(), -12 ) as $m ) {
		if ( is_array( $m ) && in_array( $m['role'] ?? '', array( 'user', 'assistant' ), true ) && '' !== trim( (string) ( $m['text'] ?? '' ) ) ) {
			$msgs[] = array( 'role' => $m['role'], 'content' => mb_substr( (string) $m['text'], 0, 4000 ) );
		}
	}
	while ( $msgs && 'user' !== $msgs[0]['role'] ) {
		array_shift( $msgs );
	}
	if ( ! $msgs || 'user' !== end( $msgs )['role'] ) {
		wp_send_json_error( array( 'message' => 'bad request' ), 400 );
	}
	$r = wp_remote_post( MARQUEE_AI_API, array(
		'timeout' => 45,
		'headers' => array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'      => marquee_ai_model(),
			'max_tokens' => 900,
			'system'     => marquee_ai_system(),
			'messages'   => $msgs,
		) ),
	) );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => __( 'Couldn’t reach the assistant: ', 'marquee' ) . $r->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$j    = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code ) {
		$type = $j['error']['type'] ?? '';
		if ( 401 === $code || 'authentication_error' === $type ) {
			$msg = __( 'Claude didn’t accept the API key. Check it in the Anthropic Console (console.anthropic.com → API Keys).', 'marquee' );
		} elseif ( 429 === $code ) {
			$msg = __( 'The assistant is busy right now. Try again in a minute.', 'marquee' );
		} elseif ( 'billing_error' === $type || false !== stripos( (string) ( $j['error']['message'] ?? '' ), 'credit' ) ) {
			$msg = __( 'The Claude account for this key is out of credit.', 'marquee' );
		} else {
			/* translators: 1: HTTP code, 2: message */
			$msg = sprintf( __( 'The assistant answered %1$d. %2$s', 'marquee' ), $code, (string) ( $j['error']['message'] ?? '' ) );
		}
		wp_send_json_error( array( 'message' => $msg ) );
	}
	$text = '';
	foreach ( (array) ( $j['content'] ?? array() ) as $c ) {
		if ( 'text' === ( $c['type'] ?? '' ) ) {
			$text .= $c['text'];
		}
	}
	set_transient( $day, $used + 1, DAY_IN_SECONDS );
	wp_send_json_success( array( 'text' => $text, 'left' => marquee_ai_daily() - $used - 1 ) );
} );

/* Save or remove the owner's own key. */
add_action( 'wp_ajax_marquee_ai_key', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$k = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
	if ( '' === $k ) {
		delete_option( 'marquee_ai_key' );
		wp_send_json_success( array( 'saved' => false ) );
	}
	if ( 0 !== strpos( $k, 'sk-ant-' ) ) {
		wp_send_json_error( array( 'message' => __( 'That doesn’t look like a Claude API key. It starts with sk-ant-.', 'marquee' ) ) );
	}
	update_option( 'marquee_ai_key', $k, false );
	wp_send_json_success( array( 'saved' => true ) );
} );

/** The help button and panel (Settings page only). */
function marquee_ai_panel() {
	list( $key, $who ) = marquee_ai_key();
	?>
	<button type="button" class="mq-ai__fab" data-mq-ai-open aria-expanded="false" aria-controls="mq-ai"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> <?php esc_html_e( 'Need help?', 'marquee' ); ?></button>
	<div class="mq-ai" id="mq-ai" data-mq-ai data-ready="<?php echo $key ? '1' : '0'; ?>" hidden>
		<div class="mq-ai__head">
			<div><b><?php esc_html_e( 'Marquee help', 'marquee' ); ?></b><small><?php esc_html_e( 'Ask anything about setting up your show site. It can see your setup check.', 'marquee' ); ?></small></div>
			<button type="button" class="mq-ai__x" data-mq-ai-close aria-label="<?php esc_attr_e( 'Close', 'marquee' ); ?>">×</button>
		</div>
		<div class="mq-ai__key" data-mq-ai-keybox <?php echo $key ? 'hidden' : ''; ?>>
			<p><?php esc_html_e( 'The helper uses Claude, an AI assistant by Anthropic. Add your own Claude API key to turn it on: create one at console.anthropic.com → API Keys (pay as you go).', 'marquee' ); ?></p>
			<p><input type="password" autocomplete="off" placeholder="sk-ant-…" data-mq-ai-keyin> <button type="button" class="button button-primary" data-mq-ai-keysave><?php esc_html_e( 'Save key', 'marquee' ); ?></button></p>
			<p class="mq-ai__fine" data-mq-ai-keymsg></p>
		</div>
		<div class="mq-ai__log" data-mq-ai-log aria-live="polite">
			<div class="mq-ai__msg is-bot"><?php esc_html_e( 'Hi! I know how Marquee works and I can see your site’s setup check. What can I help with?', 'marquee' ); ?></div>
		</div>
		<div class="mq-ai__chips" data-mq-ai-chips>
			<button type="button" data-mq-ai-chip><?php esc_html_e( 'Check my setup for problems', 'marquee' ); ?></button>
			<button type="button" data-mq-ai-chip><?php esc_html_e( 'Why aren’t names showing on my screens?', 'marquee' ); ?></button>
			<button type="button" data-mq-ai-chip><?php esc_html_e( 'How do I set up spoken names?', 'marquee' ); ?></button>
			<button type="button" data-mq-ai-chip><?php esc_html_e( 'How do themed sequences work?', 'marquee' ); ?></button>
		</div>
		<form class="mq-ai__form" data-mq-ai-form>
			<textarea rows="2" placeholder="<?php esc_attr_e( 'Type your question…', 'marquee' ); ?>" data-mq-ai-in></textarea>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Ask', 'marquee' ); ?></button>
		</form>
		<p class="mq-ai__fine">
			<?php esc_html_e( 'Your question and your site’s setup status (never passwords or keys) are sent to Anthropic’s Claude to answer. Answers can be wrong; double-check before changing your show.', 'marquee' ); ?>
			<?php if ( 'own' === $who ) : ?><button type="button" class="button-link" data-mq-ai-keyclear><?php esc_html_e( 'Remove my key', 'marquee' ); ?></button><?php elseif ( 'host' === $who ) : ?><?php esc_html_e( 'Included with your hosting.', 'marquee' ); ?><?php endif; ?>
		</p>
	</div>
	<?php
}
