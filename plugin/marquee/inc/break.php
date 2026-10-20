<?php
/**
 * Names break builder: makes the audio for the "Names in Lights" break.
 *
 * The announcer (the ElevenLabs voice picked for spoken names) says an intro and an
 * outro line, music plays soft under the names, and everything is leveled to the
 * loudness show songs are usually mastered to (-14 LUFS). Three versions are made:
 * the full break (6 names) and short ones for 1 or 2 waiting names (each said twice).
 *
 * The lines are made here (the ElevenLabs key never leaves the server); the mix is
 * done in the browser on the Settings page (Web Audio), then saved in
 * uploads/marquee-voice/break/. The FPP names script downloads the files and sets up
 * its playlists on its own.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** What was built last: urls, timing and a version the FPP script compares. */
function marquee_break_info() {
	$b = get_option( 'marquee_break' );
	return is_array( $b ) && ! empty( $b['full'] ) ? $b : null;
}

function marquee_break_dir( $url = false ) {
	return marquee_voice_dir( $url ) . 'break/';
}

/** The default lines, using the show's website as people would say it. */
function marquee_break_defaults() {
	$s     = marquee_get();
	$santa = ! empty( $s['voice_santa'] );
	$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$parts = explode( '.', $host );
	$tld   = count( $parts ) > 1 ? array_pop( $parts ) : '';
	$name  = implode( '.', $parts );
	$show  = $s['show_name'] ? $s['show_name'] : get_bloginfo( 'name' );
	// "lightsonridgeroad.com" + show name "Lights On Ridge Road" → "lights on ridge road dot com"
	if ( $tld && strtolower( preg_replace( '/[^a-z0-9]/i', '', $show ) ) === strtolower( preg_replace( '/[^a-z0-9]/i', '', $name ) ) ) {
		$site = strtolower( $show ) . ' dot ' . $tld;
	} elseif ( $tld && ! preg_match( '/[^a-z]/i', $name ) && strlen( $name ) <= 12 ) {
		$site = $name . ' dot ' . $tld;
	} else {
		$site = '';
	}
	$intro = $santa ? 'Ho ho ho! It’s time for Names in Lights!' : 'It’s time for Names in Lights! Your name might be up next!';
	if ( $site ) {
		$outro = $santa ? 'Want Santa to say your name? Visit ' . $site . '!' : 'Want to see your name in lights? Visit ' . $site . '!';
	} else {
		$outro = $santa ? 'Want Santa to say your name? Visit our website!' : 'Want to see your name in lights? Visit our website!';
	}
	return array( 'intro' => $intro, 'outro' => $outro );
}

/** Music for the break: the show's own upload, or the soft music box that comes with Marquee. */
function marquee_break_music_url() {
	$id = (int) marquee_get( 'break_music' );
	$u  = $id ? wp_get_attachment_url( $id ) : '';
	return $u ? $u : MARQUEE_URL . 'core/assets/audio/marquee-break-music.mp3';
}

/* Step 1 (Settings page): make the announcer lines. */
add_action( 'wp_ajax_marquee_break_lines', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	if ( ! function_exists( 'marquee_voice_make' ) || ! marquee_get( 'voice_key' ) || ! marquee_get( 'voice_id' ) ) {
		wp_send_json_error( array( 'message' => __( 'Set up “Say each name out loud” first (API key and voice), and Save.', 'marquee' ) ) );
	}
	$out = array();
	foreach ( array( 'intro', 'outro' ) as $k ) {
		$t = isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : '';
		$t = mb_substr( $t ? $t : marquee_break_defaults()[ $k ], 0, 160 );
		$r = marquee_voice_make( $t, array( 'raw' => true ), 25 );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		$out[ $k ] = array( 'url' => $r[0], 'secs' => $r[1], 'text' => $t );
	}
	$mid          = isset( $_POST['music'] ) ? absint( $_POST['music'] ) : 0;
	$out['music'] = ( $mid && 0 === strpos( (string) get_post_mime_type( $mid ), 'audio/' ) ) ? wp_get_attachment_url( $mid ) : marquee_break_music_url();
	if ( isset( $_POST['music'] ) && ! $mid ) {
		$out['music'] = MARQUEE_URL . 'core/assets/audio/marquee-break-music.mp3';
	}
	wp_send_json_success( $out );
} );

/* Step 2: the browser sends back each finished mix (WAV). */
add_action( 'wp_ajax_marquee_break_save', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	// the WAV comes in pieces of 1 MB (many hosts only accept small uploads)
	$kind  = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : '';
	$part  = isset( $_POST['part'] ) ? absint( $_POST['part'] ) : 0;
	$parts = isset( $_POST['parts'] ) ? absint( $_POST['parts'] ) : 0;
	if ( ! in_array( $kind, array( 'full', 'short1', 'short2' ), true ) || ! $parts || $parts > 40 || $part >= $parts || empty( $_FILES['audio']['tmp_name'] ) ) { // phpcs:ignore
		wp_send_json_error( array( 'message' => __( 'Your web host refused the upload (it may only allow very small uploads).', 'marquee' ) ), 400 );
	}
	$tmp = $_FILES['audio']['tmp_name']; // phpcs:ignore
	if ( ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 2 * MB_IN_BYTES || ( 0 === $part && 'RIFF' !== file_get_contents( $tmp, false, null, 0, 4 ) ) ) { // phpcs:ignore
		wp_send_json_error( array( 'message' => __( 'That isn’t a WAV file.', 'marquee' ) ) );
	}
	$dir = marquee_break_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		wp_send_json_error( array( 'message' => __( 'Couldn’t make the uploads/marquee-voice/break folder.', 'marquee' ) ) );
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	$v    = isset( $_POST['v'] ) ? substr( preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['v'] ) ), 0, 12 ) : '';
	$v    = $v ? $v : (string) time();
	$name = 'names-break-' . $kind . '-' . $v . '.wav';
	$data = file_get_contents( $tmp ); // phpcs:ignore
	if ( false === $data || false === file_put_contents( $dir . $name . '.part', $data, 0 === $part ? 0 : FILE_APPEND ) ) { // phpcs:ignore
		wp_send_json_error( array( 'message' => __( 'Couldn’t save the break audio.', 'marquee' ) ) );
	}
	if ( $part < $parts - 1 ) {
		wp_send_json_success( array( 'done' => false ) );
	}
	if ( filesize( $dir . $name . '.part' ) > 40 * MB_IN_BYTES || ! rename( $dir . $name . '.part', $dir . $name ) ) { // phpcs:ignore
		wp_delete_file( $dir . $name . '.part' );
		wp_send_json_error( array( 'message' => __( 'Couldn’t save the break audio.', 'marquee' ) ) );
	}
	$pending          = get_transient( 'marquee_break_pending' );
	$pending          = is_array( $pending ) && ( isset( $pending['v'] ) && $pending['v'] === $v ) ? $pending : array();
	$pending[ $kind ] = marquee_break_dir( true ) . $name;
	if ( 'full' === $kind ) {
		foreach ( array( 'start', 'slot', 'secs', 'seq' ) as $k ) {
			$pending[ $k ] = isset( $_POST[ $k ] ) ? round( (float) $_POST[ $k ], 3 ) : 0;
		}
	}
	$pending['v'] = $v;
	if ( empty( $pending['full'] ) || empty( $pending['short1'] ) || empty( $pending['short2'] ) ) {
		set_transient( 'marquee_break_pending', $pending, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'done' => false ) );
	}
	// all three are in: this is the new break
	delete_transient( 'marquee_break_pending' );
	$old = marquee_break_info();
	$pending['t'] = time();
	update_option( 'marquee_break', $pending, false );
	// the names timing follows the break
	$s                = (array) get_option( 'marquee', array() );
	$s['names_start'] = $pending['start'];
	$s['names_slot']  = $pending['slot'];
	update_option( 'marquee', $s, false );
	// the previous version's files are no longer needed
	if ( $old ) {
		foreach ( array( 'full', 'short1', 'short2' ) as $k ) {
			if ( ! empty( $old[ $k ] ) && $old[ $k ] !== $pending[ $k ] ) {
				wp_delete_file( $dir . basename( $old[ $k ] ) );
			}
		}
	}
	wp_send_json_success( array( 'done' => true, 'start' => $pending['start'], 'slot' => $pending['slot'] ) );
} );

/* The names script gets the break with every check-in, and sets itself up when it changes. */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	$b = marquee_break_info();
	if ( $b ) {
		$data['break'] = array(
			'v'      => (string) $b['v'],
			'full'   => $b['full'],
			'short1' => $b['short1'],
			'short2' => $b['short2'],
			'start'  => (float) $b['start'],
			'slot'   => (float) $b['slot'],
		);
	}
	return $data;
} );

/** Settings page box. */
function marquee_break_box( $s ) {
	$b    = marquee_break_info();
	$def  = marquee_break_defaults();
	$mus  = (int) $s['break_music'];
	?>
	<h3 id="mq-break"><?php esc_html_e( 'Names break audio', 'marquee' ); ?></h3>
	<p class="mq__fine"><?php esc_html_e( 'Marquee makes the sound for your names break: your voice says an intro and an outro, and music plays soft under the names, at the same loudness as your songs. It also makes short versions for when only 1 or 2 names are waiting (each said twice). Your names script puts them on your FPP and sets up the playlists by itself.', 'marquee' ); ?></p>
	<?php if ( $b ) : ?>
		<div class="mq__fppstat"><p class="is-ok">
			<?php
			/* translators: 1: time ago, 2: seconds, 3: seconds */
			echo esc_html( sprintf( __( '✓ Built %1$s ago. Names start at %2$s s, one every %3$s s.', 'marquee' ), human_time_diff( (int) $b['t'] ), $b['start'], $b['slot'] ) );
			?>
			<a href="<?php echo esc_url( $b['full'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Full', 'marquee' ); ?></a> ·
			<a href="<?php echo esc_url( $b['short2'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '2 names', 'marquee' ); ?></a> ·
			<a href="<?php echo esc_url( $b['short1'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '1 name', 'marquee' ); ?></a>
		</p></div>
	<?php endif; ?>
	<div class="mq__row">
		<p><label for="mq-bintro"><?php esc_html_e( 'Intro line', 'marquee' ); ?></label><input id="mq-bintro" name="break_intro" type="text" maxlength="160" value="<?php echo esc_attr( $s['break_intro'] ); ?>" placeholder="<?php echo esc_attr( $def['intro'] ); ?>"><small><?php esc_html_e( 'Keep it short (about 4 seconds). Empty uses the example.', 'marquee' ); ?></small></p>
		<p><label for="mq-boutro"><?php esc_html_e( 'Outro line', 'marquee' ); ?></label><input id="mq-boutro" name="break_outro" type="text" maxlength="160" value="<?php echo esc_attr( $s['break_outro'] ); ?>" placeholder="<?php echo esc_attr( $def['outro'] ); ?>"><small><?php esc_html_e( 'Write your website the way it’s said: “lights on main street dot com”.', 'marquee' ); ?></small></p>
	</div>
	<div class="mq__row">
		<p><label for="mq-bseq"><?php esc_html_e( 'Length of your names sequence (seconds)', 'marquee' ); ?></label><input id="mq-bseq" name="break_seq" type="number" min="15" max="120" step="0.1" value="<?php echo esc_attr( $s['break_seq'] ); ?>"><small><?php esc_html_e( 'The full break is made exactly this long, so your xLights sequence fits. FPP → Content Setup → File Manager → Sequences shows it.', 'marquee' ); ?></small></p>
		<div><?php marquee_media_field( 'break_music', __( 'Music (optional)', 'marquee' ), __( 'An instrumental MP3 with no singing or talking. Without one, a soft music box comes with Marquee.', 'marquee' ), $mus ? array( $mus ) : array(), false, '', 'audio' ); ?></div>
	</div>
	<p><button type="button" class="button button-primary" data-mq-break-build><?php esc_html_e( 'Build the break audio', 'marquee' ); ?></button> <span class="mq__msg" data-mq-break-msg aria-live="polite"></span></p>
	<div class="mq__break-prev" data-mq-break-prev hidden></div>
	<small><?php esc_html_e( 'Uses the voice saved above (Save first if you just changed it) and about 100 ElevenLabs credits. After building, your FPP updates itself within a minute (names script 3.3 or newer). Build again whenever you change the lines, the music or the voice.', 'marquee' ); ?></small>
	<?php
}
