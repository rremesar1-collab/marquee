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

/**
 * What the voice says for a name line.
 * "MERRY CHRISTMAS, EMMA!" → "Merry Christmas, Emma!" (all-caps text can sound
 * shouted or get spelled out). With a Santa voice, "HI SANTA! LOVE, MIA" becomes
 * "Ho ho ho! Hi Mia, Santa loves you too!".
 */
function marquee_voice_text( $text, $santa = null ) {
	$santa = null === $santa ? ! empty( marquee_get( 'voice_santa' ) ) : $santa;
	$t     = preg_replace( '/\s+/', ' ', mb_convert_case( mb_strtolower( trim( (string) $text ) ), MB_CASE_TITLE, 'UTF-8' ) );
	if ( $santa && preg_match( '/^Hi Santa! Love, (.+)$/u', $t, $m ) ) {
		return 'Ho ho ho! Hi ' . $m[1] . ', Santa loves you too!';
	}
	// one natural sentence (a forced pause before the name made it sound spliced in)
	return $t;
}

/** Speaking speed for ElevenLabs (0.7 slow … 1.2 fast). */
function marquee_voice_speed() {
	$v = (float) marquee_get( 'voice_speed' );
	return $v >= 0.7 && $v <= 1.2 ? round( $v, 2 ) : 0.9;
}

function marquee_voice_dir( $url = false ) {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $url ? $u['baseurl'] : $u['basedir'] ) . 'marquee-voice/';
}

/** The saved clip for this text with the current voice: array( url, seconds ) or null. */
function marquee_voice_cached( $text, $voice = '', $model = '' ) {
	$file = marquee_voice_file_base( $text, $voice, $model );
	$hits = glob( marquee_voice_dir() . $file . '-*.{wav,mp3}', GLOB_BRACE );
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
	// "w2": stereo WAV clips (FPP's extra sound slots play mono clips too fast)
	return substr( md5( wp_salt( 'auth' ) . '|w4|' . $voice . '|' . $model . '|' . marquee_voice_speed() . '|' . marquee_voice_text( $text ) ), 0, 20 );
}

/**
 * ElevenLabs PCM (16-bit mono, $rate Hz) → 44.1 kHz 16-bit stereo WAV, the format FPP's
 * media slots play at the right speed. 22050 Hz is doubled exactly; other rates are resampled.
 */
function marquee_voice_wav( $pcm, $rate ) {
	$in  = array_values( unpack( 's*', $pcm ) );
	$n   = count( $in );
	// Level: bring the speech to about -14 LUFS, the level show songs are usually mastered to,
	// so names are as loud as the music (gated RMS of 50 ms windows; LUFS ≈ RMS + 2.3 for speech).
	$win = (int) ( $rate * 0.05 );
	$sum = 0.0;
	$cnt = 0;
	for ( $i = 0; $i + $win <= $n; $i += $win ) {
		$e = 0.0;
		for ( $j = $i; $j < $i + $win; $j++ ) {
			$e += $in[ $j ] * $in[ $j ];
		}
		$e /= $win;
		if ( $e > 1073741824 * 0.0001 ) { // above -40 dBFS
			$sum += $e;
			$cnt++;
		}
	}
	$gain = 1.0;
	if ( $cnt ) {
		$rms  = 10 * log10( $sum / $cnt / 1073741824 ); // dBFS
		$gain = min( 4.0, pow( 10, ( -16.4 - $rms ) / 20 ) );
	}
	$lim = 29000;
	foreach ( $in as $k => $v ) {
		$v = $v * $gain;
		if ( abs( $v ) > $lim ) { // soft limit instead of clipping
			$over     = ( abs( $v ) - $lim ) / ( 32767 - $lim );
			$v        = ( $v < 0 ? -1 : 1 ) * ( $lim + ( 32767 - $lim ) * tanh( $over ) );
		}
		$in[ $k ] = $v;
	}
	$out = 44100;
	$len = (int) floor( $n * $out / $rate );
	$buf = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$pos = $i * $rate / $out;
		$a   = (int) $pos;
		$b   = min( $n - 1, $a + 1 );
		$v   = (int) max( -32767, min( 32767, round( $in[ $a ] + ( $in[ $b ] - $in[ $a ] ) * ( $pos - $a ) ) ) );
		$buf .= pack( 'vv', $v & 0xFFFF, $v & 0xFFFF );
	}
	$size = strlen( $buf );
	return 'RIFF' . pack( 'V', 36 + $size ) . 'WAVEfmt ' . pack( 'VvvVVvv', 16, 1, 2, $out, $out * 4, 4, 16 ) . 'data' . pack( 'V', $size ) . $buf;
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
		'text-to-speech/' . rawurlencode( $voice ) . '/with-timestamps?output_format=pcm_22050',
		$key,
		array(
			'text'           => marquee_voice_text( $text ),
			'model_id'       => $model,
			'voice_settings' => array( 'stability' => 0.6, 'similarity_boost' => 0.75, 'speed' => marquee_voice_speed() ),
		),
		$timeout
	);
	if ( is_wp_error( $j ) ) {
		return $j;
	}
	$pcm = isset( $j['audio_base64'] ) ? base64_decode( (string) $j['audio_base64'], true ) : false; // phpcs:ignore
	if ( ! $pcm || strlen( $pcm ) < 2000 ) {
		return new WP_Error( 'voice', __( 'ElevenLabs sent back no audio.', 'marquee' ) );
	}
	$pcm = substr( $pcm, 0, strlen( $pcm ) - strlen( $pcm ) % 2 );
	$wav = marquee_voice_wav( $pcm, 22050 );
	$len = 0.0;
	foreach ( array( 'alignment', 'normalized_alignment' ) as $a ) {
		if ( ! empty( $j[ $a ]['character_end_times_seconds'] ) ) {
			$len = max( $len, (float) end( $j[ $a ]['character_end_times_seconds'] ) );
		}
	}
	if ( $len <= 0 ) {
		$len = strlen( $pcm ) / 44100; // 22050 Hz x 2 bytes
	}
	$dir = marquee_voice_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'voice', __( 'Couldn’t make the uploads/marquee-voice folder.', 'marquee' ) );
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	$name = marquee_voice_file_base( $text, $voice, $model ) . '-' . (int) round( $len * 1000 ) . '.wav';
	if ( false === file_put_contents( $dir . $name, $wav ) ) { // phpcs:ignore
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
	// try the speed / Santa switch shown on the page, even before Save
	$try = array();
	if ( isset( $_POST['speed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above
		$try['voice_speed'] = (float) $_POST['speed']; // phpcs:ignore
	}
	if ( isset( $_POST['santa'] ) ) { // phpcs:ignore
		$try['voice_santa'] = (int) ! empty( $_POST['santa'] ); // phpcs:ignore
	}
	if ( $try ) {
		add_filter( 'option_marquee', function ( $v ) use ( $try ) {
			return array_merge( (array) $v, $try );
		} );
	}
	$name = isset( $_POST['name'] ) && function_exists( 'ridgeroad_clean_kid_name' ) ? ridgeroad_clean_kid_name( wp_unslash( $_POST['name'] ) ) : '';
	$name = $name ? $name : 'EMMA';
	$g    = isset( $_POST['greet'] ) && 'santa' === $_POST['greet'] ? 'HI SANTA! LOVE, %s' : 'MERRY CHRISTMAS, %s!'; // phpcs:ignore
	$r    = marquee_voice_make( sprintf( $g, $name ), $opts );
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
	foreach ( (array) glob( marquee_voice_dir() . '*.{wav,mp3}', GLOB_BRACE ) as $f ) {
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
