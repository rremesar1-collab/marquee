<?php
/**
 * "Need help?" assistant on the Marquee Settings page.
 *
 * Answers questions about Marquee with the AI the show owner picks: ChatGPT (OpenAI) or
 * Claude (Anthropic). Either one gets the same help guide that comes with the plugin
 * (inc/ai-guide.md, plus the FPP setup guide) and this site's live setup: versions,
 * what's switched on, the FPP's last setup check and recent errors. So the owner never
 * has to describe their setup. Passwords and keys are never sent.
 *
 * Who pays: a show owner pastes their own API key (ChatGPT or Claude) in the help
 * panel. A host (for example Alateka's hosted show sites) can include it instead by
 * adding to wp-config.php:
 *     define( 'MARQUEE_AI_KEY', 'sk-...' );           // the host's key, never shown
 *     define( 'MARQUEE_AI_PROVIDER', 'chatgpt' );     // optional: 'chatgpt' or 'claude' (worked out from the key if left out)
 *     define( 'MARQUEE_AI_DAILY', 40 );               // optional: questions per day
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The AIs the helper can use, in alphabetical order (no favorite). */
function marquee_ai_providers() {
	return array(
		'chatgpt' => array(
			'name'   => 'ChatGPT',
			'by'     => 'OpenAI',
			'option' => 'marquee_ai_key_openai',
			'where'  => 'platform.openai.com → API keys',
			'url'    => 'https://platform.openai.com/api-keys',
			'ph'     => 'sk-…',
		),
		'claude'  => array(
			'name'   => 'Claude',
			'by'     => 'Anthropic',
			'option' => 'marquee_ai_key',
			'where'  => 'console.anthropic.com → API Keys',
			'url'    => 'https://console.anthropic.com/settings/keys',
			'ph'     => 'sk-ant-…',
		),
	);
}

/** Which AI a key belongs to, from how it starts ('' if it doesn't look like either). */
function marquee_ai_key_provider( $k ) {
	if ( 0 === strpos( $k, 'sk-ant-' ) ) {
		return 'claude';
	}
	return 0 === strpos( $k, 'sk-' ) ? 'chatgpt' : '';
}

/** The owner's saved keys, by AI. */
function marquee_ai_saved() {
	$out = array();
	foreach ( marquee_ai_providers() as $p => $info ) {
		$k = (string) get_option( $info['option'], '' );
		if ( $k ) {
			$out[ $p ] = $k;
		}
	}
	return $out;
}

/** The key to use: array( key, 'host'|'own'|'', provider ). */
function marquee_ai_key() {
	if ( defined( 'MARQUEE_AI_KEY' ) && MARQUEE_AI_KEY ) {
		$k = (string) MARQUEE_AI_KEY;
		$p = defined( 'MARQUEE_AI_PROVIDER' ) && isset( marquee_ai_providers()[ MARQUEE_AI_PROVIDER ] ) ? MARQUEE_AI_PROVIDER : ( marquee_ai_key_provider( $k ) ? marquee_ai_key_provider( $k ) : 'claude' );
		return array( $k, 'host', $p );
	}
	$saved = marquee_ai_saved();
	if ( ! $saved ) {
		return array( '', '', '' );
	}
	$p = (string) get_option( 'marquee_ai_provider', '' );
	if ( ! isset( $saved[ $p ] ) ) {
		$p = key( $saved );
	}
	return array( $saved[ $p ], 'own', $p );
}

/** The model to ask, per AI. Hosts can pick with MARQUEE_AI_MODEL / MARQUEE_AI_OPENAI_MODEL or the marquee_ai_model filter. */
function marquee_ai_model( $p = 'claude' ) {
	if ( 'chatgpt' === $p ) {
		$m = defined( 'MARQUEE_AI_OPENAI_MODEL' ) ? MARQUEE_AI_OPENAI_MODEL : 'gpt-5-mini';
	} else {
		$m = defined( 'MARQUEE_AI_MODEL' ) ? MARQUEE_AI_MODEL : 'claude-haiku-5-5';
	}
	return (string) apply_filters( 'marquee_ai_model', $m, $p );
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
	$out['site']               = home_url( '/' );
	$out['names_break_lines'] = array(
		'intro'           => '' !== (string) $s['break_intro'] ? $s['break_intro'] : '(example line)',
		'outro'           => '' !== (string) $s['break_outro'] ? $s['break_outro'] : '(example line)',
		'sequence_length' => $s['break_seq'] . ' s',
		'music'           => $s['break_music'] ? 'their own instrumental' : 'the one that comes with Marquee',
	);
	$out['spoken_names']['face_effect'] = (string) $s['voice_effect'];
	return $out;
}


/** The instructions: the guide first (it never changes, so both AIs can cache it), then this site's setup. */
function marquee_ai_guide() {
	$guide = (string) file_get_contents( MARQUEE_DIR . 'inc/ai-guide.md' ); // phpcs:ignore
	$extra = '';
	$names = MARQUEE_DIR . 'core/fpp/marquee-names-setup-guide.txt';
	if ( file_exists( $names ) ) {
		$extra = "\n\n## The FPP setup guide (verbatim)\n\n" . file_get_contents( $names ); // phpcs:ignore
	}
	return "You are the help assistant inside the Marquee WordPress plugin's settings page. You help the show owner set up and fix their light show website and FPP connection.\n\n"
		. "You can already see this site's setup below (under \"This site right now\"), so never ask the owner to describe their setup, versions or settings: look there first, and only ask about things it can't show (what they see on their FPP screen, for example).\n\n"
		. $guide . $extra;
}

function marquee_ai_site_now() {
	return "## This site right now\n\n" . wp_json_encode( marquee_ai_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** Ask Claude. Returns array( text ) or array( null, error message ). */
function marquee_ai_ask_claude( $key, $msgs, $system = null, $max = 900 ) {
	$r = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 45,
		'headers' => array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'      => marquee_ai_model( 'claude' ),
			'max_tokens' => $max,
			'system'     => null !== $system ? array( array( 'type' => 'text', 'text' => $system ) ) : array(
				array( 'type' => 'text', 'text' => marquee_ai_guide(), 'cache_control' => array( 'type' => 'ephemeral' ) ),
				array( 'type' => 'text', 'text' => marquee_ai_site_now() ),
			),
			'messages'   => $msgs,
		) ),
	) );
	if ( is_wp_error( $r ) ) {
		return array( null, __( 'Couldn’t reach Claude: ', 'marquee' ) . $r->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$j    = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code ) {
		$type = $j['error']['type'] ?? '';
		if ( 401 === $code || 'authentication_error' === $type ) {
			return array( null, __( 'Claude didn’t accept the API key. Check it in the Anthropic Console (console.anthropic.com → API Keys).', 'marquee' ) );
		}
		if ( 429 === $code ) {
			return array( null, __( 'Claude is busy right now. Try again in a minute.', 'marquee' ) );
		}
		if ( 'billing_error' === $type || false !== stripos( (string) ( $j['error']['message'] ?? '' ), 'credit' ) ) {
			return array( null, __( 'The Claude account for this key is out of credit.', 'marquee' ) );
		}
		/* translators: 1: HTTP code, 2: message */
		return array( null, sprintf( __( 'Claude answered %1$d. %2$s', 'marquee' ), $code, (string) ( $j['error']['message'] ?? '' ) ) );
	}
	$text = '';
	foreach ( (array) ( $j['content'] ?? array() ) as $c ) {
		if ( 'text' === ( $c['type'] ?? '' ) ) {
			$text .= $c['text'];
		}
	}
	return array( $text );
}

/** Ask ChatGPT. Returns array( text ) or array( null, error message ). */
function marquee_ai_ask_chatgpt( $key, $msgs, $system = null, $max = 900 ) {
	$sys = null !== $system ? array( array( 'role' => 'system', 'content' => $system ) ) : array(
		array( 'role' => 'system', 'content' => marquee_ai_guide() ),
		array( 'role' => 'system', 'content' => marquee_ai_site_now() ),
	);
	/* If OpenAI retires the model, fall back to older small ones. */
	$models = array_values( array_unique( array( marquee_ai_model( 'chatgpt' ), 'gpt-5-mini', 'gpt-4.1-mini', 'gpt-4o-mini' ) ) );
	foreach ( $models as $i => $model ) {
		$body = array(
			'model'    => $model,
			'messages' => array_merge( $sys, $msgs ),
		);
		if ( preg_match( '/^(gpt-[5-9]|o\d)/', $model ) ) {
			$body['max_completion_tokens'] = 4000; // includes its thinking
			$body['reasoning_effort']      = 'low';
		} else {
			$body['max_tokens'] = $max;
		}
		$r = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $r ) ) {
			return array( null, __( 'Couldn’t reach ChatGPT: ', 'marquee' ) . $r->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$j    = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( 200 === $code ) {
			$text = (string) ( $j['choices'][0]['message']['content'] ?? '' );
			if ( '' === trim( $text ) ) {
				return array( null, __( 'ChatGPT didn’t send an answer. Try asking again.', 'marquee' ) );
			}
			return array( $text );
		}
		$err  = (array) ( $j['error'] ?? array() );
		$ecode = (string) ( $err['code'] ?? '' );
		$emsg  = (string) ( $err['message'] ?? '' );
		$gone  = 'model_not_found' === $ecode || ( 404 === $code ) || ( 400 === $code && false !== stripos( $emsg, 'model' ) );
		if ( $gone && $i < count( $models ) - 1 ) {
			continue;
		}
		if ( 401 === $code || 'invalid_api_key' === $ecode ) {
			return array( null, __( 'ChatGPT didn’t accept the API key. Check it at platform.openai.com → API keys.', 'marquee' ) );
		}
		if ( 'insufficient_quota' === $ecode || false !== stripos( $emsg, 'quota' ) || false !== stripos( $emsg, 'billing' ) ) {
			return array( null, __( 'The OpenAI account for this key is out of credit. Add credit at platform.openai.com → Billing.', 'marquee' ) );
		}
		if ( 429 === $code ) {
			return array( null, __( 'ChatGPT is busy right now. Try again in a minute.', 'marquee' ) );
		}
		/* translators: 1: HTTP code, 2: message */
		return array( null, sprintf( __( 'ChatGPT answered %1$d. %2$s', 'marquee' ), $code, $emsg ) );
	}
	return array( null, __( 'ChatGPT didn’t answer.', 'marquee' ) );
}

/* Ask a question. */
add_action( 'wp_ajax_marquee_ai_ask', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	list( $key, , $p ) = marquee_ai_key();
	if ( ! $key ) {
		wp_send_json_error( array( 'message' => __( 'Add a ChatGPT or Claude API key first.', 'marquee' ), 'need_key' => true ) );
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
	$res = 'chatgpt' === $p ? marquee_ai_ask_chatgpt( $key, $msgs ) : marquee_ai_ask_claude( $key, $msgs );
	if ( null === $res[0] ) {
		wp_send_json_error( array( 'message' => $res[1] ) );
	}
	set_transient( $day, $used + 1, DAY_IN_SECONDS );
	wp_send_json_success( array( 'text' => $res[0], 'left' => marquee_ai_daily() - $used - 1 ) );
} );

/* Save a key (ChatGPT or Claude, worked out from how it starts), switch AI, or remove a key. */
add_action( 'wp_ajax_marquee_ai_key', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$all = marquee_ai_providers();
	$use = isset( $_POST['use'] ) ? sanitize_key( wp_unslash( $_POST['use'] ) ) : '';
	if ( $use ) {
		if ( ! isset( marquee_ai_saved()[ $use ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Add a key for that one first.', 'marquee' ) ) );
		}
		update_option( 'marquee_ai_provider', $use, false );
		wp_send_json_success( array( 'saved' => true, 'provider' => $use, 'name' => $all[ $use ]['name'] ) );
	}
	$k = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
	if ( '' === $k ) {
		list( , $who, $p ) = marquee_ai_key();
		if ( 'own' === $who ) {
			delete_option( $all[ $p ]['option'] );
		}
		list( $left, , $np ) = marquee_ai_key();
		wp_send_json_success( array( 'saved' => (bool) $left, 'provider' => $np, 'name' => $np ? $all[ $np ]['name'] : '' ) );
	}
	$p = marquee_ai_key_provider( $k );
	if ( ! $p ) {
		wp_send_json_error( array( 'message' => __( 'That doesn’t look like an API key. A ChatGPT key starts with sk-, a Claude key starts with sk-ant-.', 'marquee' ) ) );
	}
	update_option( $all[ $p ]['option'], $k, false );
	update_option( 'marquee_ai_provider', $p, false );
	wp_send_json_success( array( 'saved' => true, 'provider' => $p, 'name' => $all[ $p ]['name'] ) );
} );

/** The help button and panel (Settings page only). */
function marquee_ai_panel() {
	list( $key, $who, $p ) = marquee_ai_key();
	$all   = marquee_ai_providers();
	$saved = 'host' === $who ? array() : marquee_ai_saved();
	$name  = $p ? $all[ $p ]['name'] : '';
	?>
	<button type="button" class="mq-ai__fab" data-mq-ai-open aria-expanded="false" aria-controls="mq-ai"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> <?php esc_html_e( 'Need help?', 'marquee' ); ?></button>
	<div class="mq-ai" id="mq-ai" data-mq-ai data-ready="<?php echo $key ? '1' : '0'; ?>" data-ai="<?php echo esc_attr( $p ); ?>" data-saved="<?php echo esc_attr( implode( ',', array_keys( $saved ) ) ); ?>" data-ais="<?php echo esc_attr( wp_json_encode( array_map( function ( $i ) { return array( 'name' => $i['name'], 'by' => $i['by'] ); }, $all ) ) ); ?>" hidden>
		<div class="mq-ai__head">
			<div><b><?php esc_html_e( 'Marquee help', 'marquee' ); ?></b><small><?php esc_html_e( 'Ask anything about setting up your show. It already knows your setup, so you don’t have to explain it.', 'marquee' ); ?></small>
				<small class="mq-ai__who" data-mq-ai-who <?php echo $key ? '' : 'hidden'; ?>><?php esc_html_e( 'Answers by', 'marquee' ); ?> <b data-mq-ai-name><?php echo esc_html( $name ); ?></b><?php if ( 'host' !== $who ) : ?> · <button type="button" class="button-link" data-mq-ai-change><?php esc_html_e( 'Change', 'marquee' ); ?></button><?php endif; ?></small>
			</div>
			<button type="button" class="mq-ai__x" data-mq-ai-close aria-label="<?php esc_attr_e( 'Close', 'marquee' ); ?>">×</button>
		</div>
		<div class="mq-ai__key" data-mq-ai-keybox <?php echo $key ? 'hidden' : ''; ?>>
			<p><b><?php esc_html_e( 'Pick the AI that answers.', 'marquee' ); ?></b> <?php esc_html_e( 'Both work the same way and both know your setup. Use whichever account you already have (pay as you go, a question costs about a cent or less).', 'marquee' ); ?></p>
			<div class="mq-ai__picks">
				<?php foreach ( $all as $id => $info ) : ?>
					<div class="mq-ai__pick<?php echo $id === $p ? ' is-on' : ''; ?>" data-mq-ai-pick="<?php echo esc_attr( $id ); ?>">
						<b><?php echo esc_html( $info['name'] ); ?></b> <small><?php echo esc_html( $info['by'] ); ?></small>
						<span><?php esc_html_e( 'Get a key:', 'marquee' ); ?> <a href="<?php echo esc_url( $info['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $info['where'] ); ?></a></span>
						<button type="button" class="button button-small" data-mq-ai-use="<?php echo esc_attr( $id ); ?>" <?php echo isset( $saved[ $id ] ) && $id !== $p ? '' : 'hidden'; ?>><?php echo esc_html( sprintf( /* translators: AI name */ __( 'Use %s (key saved)', 'marquee' ), $info['name'] ) ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="mq-ai__keyrow"><input type="password" autocomplete="off" placeholder="<?php esc_attr_e( 'Paste your ChatGPT or Claude API key', 'marquee' ); ?>" data-mq-ai-keyin> <button type="button" class="button button-primary" data-mq-ai-keysave><?php esc_html_e( 'Save key', 'marquee' ); ?></button></p>
			<p class="mq-ai__fine" data-mq-ai-keymsg><?php esc_html_e( 'Marquee tells which one it is from the key.', 'marquee' ); ?></p>
		</div>
		<div class="mq-ai__log" data-mq-ai-log aria-live="polite">
			<div class="mq-ai__msg is-bot"><?php esc_html_e( 'Hi! I know how Marquee works and I can already see your setup and your FPP’s setup check, so just ask. What can I help with?', 'marquee' ); ?></div>
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
			<?php esc_html_e( 'Your question and your site’s setup (never passwords or keys) are sent to', 'marquee' ); ?> <span data-mq-ai-to><?php echo esc_html( $p ? $all[ $p ]['name'] . ' (' . $all[ $p ]['by'] . ')' : __( 'the AI you pick', 'marquee' ) ); ?></span> <?php esc_html_e( 'to answer. Answers can be wrong; double-check before changing your show.', 'marquee' ); ?>
			<?php if ( 'host' !== $who ) : ?><button type="button" class="button-link" data-mq-ai-keyclear <?php echo 'own' === $who ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove this key', 'marquee' ); ?></button><?php elseif ( 'host' === $who ) : ?><?php esc_html_e( 'Included with your hosting.', 'marquee' ); ?><?php endif; ?>
		</p>
	</div>
	<?php
}


/* Sequence maker: turn "purple and gold Mardi Gras" into a theme (colors + which effect each kind
   of prop gets). Only the description and the kinds of props (with their names) are sent. */
add_action( 'wp_ajax_marquee_ai_theme', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	list( $key, , $p ) = marquee_ai_key();
	if ( ! $key ) {
		wp_send_json_error( array( 'message' => __( 'Describing your own theme uses the Need help assistant: add a ChatGPT or Claude key there first (the ready-made themes work without one).', 'marquee' ), 'need_key' => true ) );
	}
	$day  = 'marquee_ai_n_' . wp_date( 'Ymd' );
	$used = (int) get_transient( $day );
	if ( $used >= marquee_ai_daily() ) {
		wp_send_json_error( array( 'message' => __( 'That’s all the AI questions for today. Try again tomorrow, or pick a ready-made theme.', 'marquee' ) ) );
	}
	$want  = isset( $_POST['want'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['want'] ) ), 0, 200 ) : '';
	$props = isset( $_POST['props'] ) ? json_decode( wp_unslash( $_POST['props'] ), true ) : array(); // phpcs:ignore
	if ( '' === trim( $want ) ) {
		wp_send_json_error( array( 'message' => __( 'Describe the theme first, like “purple and gold Mardi Gras”.', 'marquee' ) ) );
	}
	$kinds = array();
	foreach ( (array) $props as $k => $names ) {
		if ( is_string( $k ) && preg_match( '/^[a-z]+$/', $k ) ) {
			$kinds[ $k ] = array_slice( array_map( 'sanitize_text_field', (array) $names ), 0, 8 );
		}
	}
	$system = "You design themed versions of a short Christmas light show sequence (xLights) for a kids' \"names in lights\" break. "
		. "Answer with ONLY a JSON object, no other text: {\"name\": short theme name (max 30 chars), \"colors\": 2 to 5 colors as \"#RRGGBB\" (the theme's palette, most important first; bright, saturated colors look best on pixels; use #FFFFFF for white), "
		. "\"swaps\": an object mapping prop kinds to an effect for the main part of the break}. "
		. "Prop kinds: tree, star, arch, cane, spinner, flake, window, line (rooflines, icicles, outlines), flood, present, matrix, other. "
		. "Effects you may use: spirals (stripes winding round, great on trees), pinwheel (spinning arms, for spinners), chase (moving stripes along a line), bars (moving bands, good on windows and matrices), twinkle (random twinkling), shimmer (all lights flickering together), wash (slow color fade), snowfall (dots falling down), on (steady). "
		. "Only include kinds this show has. Pick effects that make the theme recognisable (for example snowfall for snow themes, spirals for candy canes).";
	$user = 'Theme: ' . $want . "\nThis show's props by kind: " . wp_json_encode( $kinds );
	$msgs = array( array( 'role' => 'user', 'content' => $user ) );
	$res  = 'chatgpt' === $p ? marquee_ai_ask_chatgpt( $key, $msgs, $system, 600 ) : marquee_ai_ask_claude( $key, $msgs, $system, 600 );
	if ( null === $res[0] ) {
		wp_send_json_error( array( 'message' => $res[1] ) );
	}
	set_transient( $day, $used + 1, DAY_IN_SECONDS );
	$text = (string) $res[0];
	$j    = json_decode( $text, true );
	if ( ! is_array( $j ) && preg_match( '/\{.*\}/s', $text, $m ) ) {
		$j = json_decode( $m[0], true );
	}
	if ( ! is_array( $j ) || empty( $j['colors'] ) ) {
		wp_send_json_error( array( 'message' => __( 'The AI’s answer didn’t make sense. Try describing it a little differently.', 'marquee' ) ) );
	}
	wp_send_json_success( array( 'theme' => array(
		'name'   => mb_substr( sanitize_text_field( (string) ( $j['name'] ?? $want ) ), 0, 30 ),
		'colors' => array_values( array_filter( (array) $j['colors'], function ( $c ) { return is_string( $c ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $c ); } ) ),
		'swaps'  => array_map( 'sanitize_key', array_filter( (array) ( $j['swaps'] ?? array() ), 'is_string' ) ),
	) ) );
} );
