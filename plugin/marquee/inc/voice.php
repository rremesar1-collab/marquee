<?php
/**
 * Spoken names (optional): each name kids send is read out loud by an ElevenLabs
 * voice during the names break.
 *
 * - The website makes the audio, so the ElevenLabs key stays on this server and
 *   never goes to the FPP.
 * - The clip is made right after the name is sent, so it is ready before the
 *   break. If that fails, the next FPP check-in tries again.
 * - Clips are saved in uploads/marquee-voice/ and reused: the second "Merry
 *   Christmas, Emma!" costs nothing.
 * - The FPP script downloads the clip and plays it as the name goes up.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MARQUEE_VOICE_API = 'https://api.elevenlabs.io/v1/';

/** Voice models people can pick (ElevenLabs model id => label). */
function marquee_voice_models() {
	return array(
		'eleven_multilingual_v2' => __( 'Best quality', 'marquee' ),
		'eleven_flash_v2_5'      => __( 'Faster, about half the credits', 'marquee' ),
	);
}

/** Spoken names are switched on and have a key and a voice. */
function marquee_voice_on() {
	$s = marquee_get();
	return ! empty( $s['voice_on'] ) && '' !== (string) $s['voice_key'] && '' !== (string) $s['voice_id'];
}

/** "MERRY CHRISTMAS, EMMA!" → "Merry Christmas, Emma!" (all-caps text can sound shouted or get spelled out). */
function marquee_voice_text( $text ) {
	$t = mb_convert_case( mb_strtolower( trim( (string) $text ) ), MB_CASE_TITLE, 'UTF-8' );
	return preg_replace( '/\s+/', ' ', $t );
}

function marquee_voice_dir( $url = false ) {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $url ? $u['baseurl'] : $u['basedir'] ) . 'marquee-voice/';
}

/** The saved clip for this text with the current voice: array( url, seconds ) or null. */
function marquee_voice_cached( $text, $voice = '', $model = '' ) {
	$file = marquee_voice_file_base( $text, $voice, $model );
	$hits = glob( marquee_voice_dir() . $file . '-*.mp3' );
	if ( ! $hits ) {
		return null;
	}
	$name = basename( $hits[0] );
	return array( marquee_voice_dir( true ) . $name, (int) substr( $name, strlen( $file ) + 1, -4 ) / 1000 );
}
function marquee_voice_file_base( $text, $voice = '', $model = '' ) {
	$s     = marquee_get();
	$voice = $voice ? $voice : $s['voice_id'];
	$model = $model ? $model : $s['voice_model'];
	return substr( md5( wp_salt( 'auth' ) . '|' . $voice . '|' . $model . '|' . marquee_voice_text( $text ) ), 0, 20 );
}

/** One call to ElevenLabs. Returns the decoded JSON or a WP_Error with a message people can act on. */
function marquee_voice_api( $path, $key, $body = null, $timeout = 15 ) {
	$args = array(
		'timeout' => $timeout,
		'headers' => array( 'xi-api-key' => $key, 'Accept' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
		$r                               = wp_remote_post( MARQUEE_VOICE_API . $path, $args );
	} else {
		$r = wp_remote_get( MARQUEE_VOICE_API . $path, $args );
	}
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'voice', __( 'Couldn’t reach ElevenLabs: ', 'marquee' ) . $r->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$j    = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 === $code && is_array( $j ) ) {
		return $j;
	}
	$detail = is_array( $j ) && isset( $j['detail'] ) ? $j['detail'] : array();
	$status = is_array( $detail ) && isset( $detail['status'] ) ? (string) $detail['status'] : '';
	$msg    = is_array( $detail ) && isset( $detail['message'] ) ? (string) $detail['message'] : ( is_string( $detail ) ? $detail : '' );
	if ( 401 === $code && 'missing_permissions' !== $status ) {
		$out = __( 'ElevenLabs didn’t accept the API key. Copy it again from ElevenLabs → Developers → API Keys.', 'marquee' );
	} elseif ( 'quota_exceeded' === $status ) {
		$out = __( 'Your ElevenLabs account is out of credits for this month.', 'marquee' );
	} elseif ( 'missing_permissions' === $status ) {
		$out = __( 'This API key isn’t allowed to do that. In ElevenLabs → Developers → API Keys, give it Text to Speech access (and Voices: Read to list your voices).', 'marquee' );
	} elseif ( 'voice_not_found' === $status || ( 404 === $code && false !== strpos( $path, 'text-to-speech' ) ) ) {
		$out = __( 'ElevenLabs can’t find that voice. Click “Load my voices” and pick one.', 'marquee' );
	} else {
		/* translators: 1: HTTP status code, 2: message from ElevenLabs */
		$out = sprintf( __( 'ElevenLabs answered %1$d. %2$s', 'marquee' ), $code, $msg );
	}
	return new WP_Error( 'voice', trim( $out ) );
}

/**
 * Make (or reuse) the clip for one line of text. Returns array( url, seconds ) or WP_Error.
 * $opts can override key / voice / model (for the Test button before saving).
 */
function marquee_voice_make( $text, $opts = array(), $timeout = 15 ) {
	$s     = marquee_get();
	$key   = ! empty( $opts['key'] ) ? $opts['key'] : $s['voice_key'];
	$voice = ! empty( $opts['voice'] ) ? $opts['voice'] : $s['voice_id'];
	$model = ! empty( $opts['model'] ) ? $opts['model'] : $s['voice_model'];
	if ( ! $key || ! $voice ) {
		return new WP_Error( 'voice', __( 'Add your ElevenLabs API key and pick a voice first.', 'marquee' ) );
	}
	$hit = marquee_voice_cached( $text, $voice, $model );
	if ( $hit ) {
		return $hit;
	}
	$j = marquee_voice_api(
		'text-to-speech/' . rawurlencode( $voice ) . '/with-timestamps?output_format=mp3_44100_128',
		$key,
		array(
			'text'           => marquee_voice_text( $text ),
			'model_id'       => $model,
			'voice_settings' => array( 'stability' => 0.5, 'similarity_boost' => 0.75 ),
		),
		$timeout
	);
	if ( is_wp_error( $j ) ) {
		return $j;
	}
	$mp3 = isset( $j['audio_base64'] ) ? base64_decode( (string) $j['audio_base64'], true ) : false; // phpcs:ignore
	if ( ! $mp3 || strlen( $mp3 ) < 500 ) {
		return new WP_Error( 'voice', __( 'ElevenLabs sent back no audio.', 'marquee' ) );
	}
	$len = 0.0;
	foreach ( array( 'alignment', 'normalized_alignment' ) as $a ) {
		if ( ! empty( $j[ $a ]['character_end_times_seconds'] ) ) {
			$len = max( $len, (float) end( $j[ $a ]['character_end_times_seconds'] ) );
		}
	}
	if ( $len <= 0 ) {
		$len = strlen( $mp3 ) / 16000; // 128 kbit/s
	}
	$dir = marquee_voice_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'voice', __( 'Couldn’t make the uploads/marquee-voice folder.', 'marquee' ) );
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	$name = marquee_voice_file_base( $text, $voice, $model ) . '-' . (int) round( $len * 1000 ) . '.mp3';
	if ( false === file_put_contents( $dir . $name, $mp3 ) ) { // phpcs:ignore
		return new WP_Error( 'voice', __( 'Couldn’t save the voice clip in uploads.', 'marquee' ) );
	}
	if ( function_exists( 'ridgeroad_stat_add' ) ) {
		ridgeroad_stat_add( 'voice_made' );
	}
	return array( marquee_voice_dir( true ) . $name, round( $len, 2 ) );
}

/** Make the clip for one queued name and remember it on the name. */
function marquee_voice_for_name( $id, $timeout = 15 ) {
	$text = get_post_meta( $id, '_rr_text', true );
	if ( ! $text ) {
		return null;
	}
	$r = marquee_voice_make( $text, array(), $timeout );
	if ( is_wp_error( $r ) ) {
		update_post_meta( $id, '_rr_voice_fail', time() );
		update_option( 'marquee_voice_error', array( 't' => time(), 'msg' => $r->get_error_message() ), false );
		return null;
	}
	delete_post_meta( $id, '_rr_voice_fail' );
	if ( get_option( 'marquee_voice_error' ) ) {
		delete_option( 'marquee_voice_error' );
	}
	return $r;
}

/* A name was just sent: make its clip now, so it's ready before the break. */
add_action( 'ridgeroad_name_added', function ( $id ) {
	if ( marquee_voice_on() ) {
		marquee_voice_for_name( $id, 8 );
	}
} );

/* FPP asks for the queue: add the clip to each name (making any that are missing). */
add_filter( 'ridgeroad_names_pull_item', function ( $item, $post, $i ) {
	if ( ! marquee_voice_on() ) {
		return $item;
	}
	$hit = marquee_voice_cached( $item['text'] );
	if ( ! $hit && $i < 6 ) { // only the names going up at the next break
		$fail = (int) get_post_meta( $post->ID, '_rr_voice_fail', true );
		if ( ! $fail || time() - $fail > 120 ) {
			$hit = marquee_voice_for_name( $post->ID, 8 );
		}
	}
	if ( $hit ) {
		@touch( marquee_voice_dir() . basename( $hit[0] ) ); // phpcs:ignore -- "last used", for the yearly tidy-up
		$item['audio']   = $hit[0];
		$item['say_for'] = $hit[1];
	}
	return $item;
}, 10, 3 );

/* Tell the names script spoken names are on (so its setup check looks at sound too). */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	$data['voice'] = marquee_voice_on();
	return $data;
} );

/* Settings page: test the voice / list the account's voices. */
add_action( 'wp_ajax_marquee_voice_test', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$opts = marquee_voice_posted();
	$name = isset( $_POST['name'] ) && function_exists( 'ridgeroad_clean_kid_name' ) ? ridgeroad_clean_kid_name( wp_unslash( $_POST['name'] ) ) : '';
	$name = $name ? $name : 'EMMA';
	$r    = marquee_voice_make( sprintf( 'MERRY CHRISTMAS, %s!', $name ), $opts );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	$left = '';
	$sub  = marquee_voice_api( 'user/subscription', $opts['key'] ? $opts['key'] : marquee_get( 'voice_key' ), null, 8 );
	if ( ! is_wp_error( $sub ) && isset( $sub['character_count'], $sub['character_limit'] ) ) {
		/* translators: 1: characters used, 2: monthly characters */
		$left = ' ' . sprintf( __( 'Used this month: %1$s of %2$s credits.', 'marquee' ), number_format_i18n( (int) $sub['character_count'] ), number_format_i18n( (int) $sub['character_limit'] ) );
	}
	wp_send_json_success( array( 'url' => $r[0], 'message' => __( 'It works! Playing it now.', 'marquee' ) . $left ) );
} );

add_action( 'wp_ajax_marquee_voice_list', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$opts = marquee_voice_posted();
	if ( ! $opts['key'] ) {
		wp_send_json_error( array( 'message' => __( 'Paste your ElevenLabs API key first.', 'marquee' ) ) );
	}
	$j = marquee_voice_api( 'voices', $opts['key'] );
	if ( is_wp_error( $j ) ) {
		wp_send_json_error( array( 'message' => $j->get_error_message() ) );
	}
	$out = array();
	foreach ( isset( $j['voices'] ) ? (array) $j['voices'] : array() as $v ) {
		if ( ! empty( $v['voice_id'] ) ) {
			$mine  = isset( $v['category'] ) && 'premade' !== $v['category'];
			$out[] = array( 'id' => (string) $v['voice_id'], 'name' => (string) ( $v['name'] ?? $v['voice_id'] ), 'mine' => $mine );
		}
	}
	usort( $out, function ( $a, $b ) {
		return ( $b['mine'] <=> $a['mine'] ) ?: strcasecmp( $a['name'], $b['name'] );
	} );
	wp_send_json_success( array( 'voices' => $out ) );
} );

/** Key / voice / model typed on the settings page (falls back to the saved ones). */
function marquee_voice_posted() {
	$s     = marquee_get();
	$key   = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller
	$voice = isset( $_POST['voice'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_POST['voice'] ) ) : ''; // phpcs:ignore
	$model = isset( $_POST['model'] ) ? sanitize_key( wp_unslash( $_POST['model'] ) ) : ''; // phpcs:ignore
	return array(
		'key'   => ( '' === $key || '••••••••' === $key ) ? $s['voice_key'] : $key,
		'voice' => $voice ? $voice : $s['voice_id'],
		'model' => isset( marquee_voice_models()[ $model ] ) ? $model : $s['voice_model'],
	);
}

/* Old clips: anything not played in 400 days is cleared once a day. */
add_action( 'marquee_voice_tidy', function () {
	foreach ( (array) glob( marquee_voice_dir() . '*.mp3' ) as $f ) {
		if ( $f && filemtime( $f ) < time() - 400 * DAY_IN_SECONDS ) {
			wp_delete_file( $f );
		}
	}
} );
add_action( 'init', function () {
	if ( marquee_voice_on() && ! wp_next_scheduled( 'marquee_voice_tidy' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'marquee_voice_tidy' );
	}
} );

/* Privacy policy text the site owner can copy (Settings → Privacy). */
add_action( 'admin_init', function () {
	if ( function_exists( 'wp_add_privacy_policy_content' ) && marquee_voice_on() ) {
		wp_add_privacy_policy_content( 'Marquee', wp_kses_post( '<p>' . __( 'When you send a first name for “Your Name in Lights”, the name and the greeting you picked are sent to ElevenLabs (elevenlabs.io) to make the spoken version that plays during the show. Nothing else about you is sent.', 'marquee' ) . '</p>' ) );
	}
} );
