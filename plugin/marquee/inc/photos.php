<?php
/**
 * Kids' pictures on the screens (off unless the show owner turns it on and accepts the notice).
 *
 * A kid adds a picture with their name: a real photo, or a cartoon made from it on their own
 * phone (the owner picks which are allowed). The phone shrinks it to a small square first, so
 * no full-size photo ever leaves it. A parent has to say it's OK, and every picture waits for
 * the owner or a Show helper to approve it in Show Control or the Show Manager (or the optional
 * automatic check with the owner's Google Cloud Vision key passes it). The name waits
 * with it for up to 10 minutes, then goes up without the picture.
 * Pictures are kept in a private folder with unguessable names, never in the Media Library,
 * and deleted as soon as they've been shown, removed or turned down, and always within 2 hours.
 * The names script (3.14+) puts the picture on each screen, then the name. Needs FPP 10 or newer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MARQUEE_PHOTO_WAIT = 600;           // a name waits this long for its picture to be approved
const MARQUEE_PHOTO_KEEP = 2 * HOUR_IN_SECONDS;

/** '' (off), 'cartoon', 'photo' or 'both' (the kid picks). Off until the notice is accepted. */
function marquee_photos_mode() {
	$s = marquee_get();
	$m = isset( $s['photos'] ) ? (string) $s['photos'] : 'off';
	if ( empty( $s['photos_ack'] ) || ! in_array( $m, array( 'cartoon', 'photo', 'both' ), true ) ) {
		return '';
	}
	return $m;
}

function marquee_photo_dir( $url = false ) {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $url ? $u['baseurl'] : $u['basedir'] ) . 'marquee/kid-pictures/';
}

/* The name form asks for a picture only when this is on. */
add_filter( 'ridgeroad_names_photo_mode', function () {
	return marquee_photos_mode();
} );

/* ---------------------------------------------------------------- settings ---- */
add_filter( 'marquee_settings_saved', function ( $new, $in ) {
	if ( isset( $in['photos_sent'] ) ) {
		$m                  = isset( $in['photos'] ) ? sanitize_key( $in['photos'] ) : 'off';
		$new['photos']      = in_array( $m, array( 'off', 'cartoon', 'photo', 'both' ), true ) ? $m : 'off';
		$new['photos_ack']  = (int) ! empty( $in['photos_ack'] );
		$new['photos_auto'] = (int) ! empty( $in['photos_auto'] );
		$key                = isset( $in['vision_key'] ) ? trim( sanitize_text_field( wp_unslash( $in['vision_key'] ) ) ) : '';
		$new['vision_key']  = ( '' === $key || '••••••••' === $key ) ? (string) marquee_get( 'vision_key' ) : mb_substr( $key, 0, 200 );
		if ( ! empty( $in['vision_clear'] ) ) {
			$new['vision_key']  = '';
			$new['photos_auto'] = 0;
		}
		if ( ! $new['photos_ack'] ) {
			$new['photos'] = 'off';                   // can't be on without the notice accepted
		}
	} else {
		$cur = marquee_get();                         // another part of Settings was saved: keep these as they are
		foreach ( array( 'photos', 'photos_ack', 'photos_auto', 'vision_key' ) as $k ) {
			$new[ $k ] = $cur[ $k ];
		}
	}
	return $new;
}, 10, 2 );

/** Settings card: Kids' pictures, with the notice the owner has to accept. */
function marquee_photos_box( $s ) {
	$mode = isset( $s['photos'] ) ? $s['photos'] : 'off';
	$on   = '' !== marquee_photos_mode();
	?>
	<details class="mq__card" data-mq-photos <?php echo isset( $_GET['photos'] ) ? 'open' : ''; // phpcs:ignore WordPress.Security.NonceVerification ?>>
		<summary>
			<span class="mq__card-n" aria-hidden="true">📷</span>
			<span class="mq__card-t"><b><?php esc_html_e( 'Kids’ pictures on your screens', 'marquee' ); ?></b><small><?php esc_html_e( 'Kids can add a picture of themselves (or a cartoon of it) to their name. You approve every picture before it goes up.', 'marquee' ); ?></small></span>
			<span class="mq__pill <?php echo $on ? 'is-ok' : ''; ?>"><?php echo $on ? esc_html__( 'On', 'marquee' ) : esc_html__( 'Off', 'marquee' ); ?></span>
		</summary>
		<div class="mq__card-b">
			<input type="hidden" name="photos_sent" value="1">
			<div class="mq-notice mq-notice--warn" role="note">
				<p><b><?php esc_html_e( 'Please read before turning this on', 'marquee' ); ?></b></p>
				<ul>
					<li><?php esc_html_e( 'These are pictures of children, shown in public at your show. You are responsible for what goes up on your screens and for following the laws where you live.', 'marquee' ); ?></li>
					<li><?php esc_html_e( 'In the United States, collecting a picture from a child under 13 online is covered by COPPA, which requires a parent’s permission. Marquee asks a parent or guardian to tick a box before any picture is sent, but it can’t check who ticked it. Other countries have their own rules (in Europe, the GDPR).', 'marquee' ); ?></li>
					<li><?php esc_html_e( 'Every picture waits for you or a Show helper to approve it in Show Control or the Show Manager, unless you turn on the automatic check below: then only the pictures it isn’t sure about wait for you. A name whose picture isn’t approved within 10 minutes goes up without the picture.', 'marquee' ); ?></li>
					<li><?php esc_html_e( 'The phone shrinks the picture to a small square before sending it. Marquee keeps it in a private folder (never in your Media Library, never on a page) and deletes it as soon as it’s been shown, removed or turned down, and always within 2 hours.', 'marquee' ); ?></li>
					<li><?php esc_html_e( 'The cartoon version is made on the kid’s own phone: only the cartoon is sent, never the photo. It’s the safer choice and looks better on pixel screens.', 'marquee' ); ?></li>
					<li><?php esc_html_e( 'Pictures need FPP 10 or newer on the FPP that runs each screen, and the Marquee names script 3.14 or newer. On a small pixel matrix a face is only a few dozen pixels tall; it looks best on a TV or projector.', 'marquee' ); ?></li>
				</ul>
				<p class="mq__fine"><?php esc_html_e( 'This isn’t legal advice. If you’re unsure, ask someone who knows the rules where you live, or leave this off.', 'marquee' ); ?></p>
			</div>
			<label class="mq__feat"><span><b><?php esc_html_e( 'I’ve read this and I’m responsible for the pictures shown at my show', 'marquee' ); ?></b><small><?php esc_html_e( 'Needed before pictures can be turned on.', 'marquee' ); ?></small></span><input type="checkbox" name="photos_ack" value="1" <?php checked( ! empty( $s['photos_ack'] ) ); ?> data-mq-photos-ack></label>
			<p><label for="mq-photos"><b><?php esc_html_e( 'Pictures kids can add', 'marquee' ); ?></b></label>
				<select id="mq-photos" name="photos" data-mq-photos-mode>
					<option value="off" <?php selected( $mode, 'off' ); ?>><?php esc_html_e( 'Off (no pictures)', 'marquee' ); ?></option>
					<option value="cartoon" <?php selected( $mode, 'cartoon' ); ?>><?php esc_html_e( 'Cartoon only (recommended)', 'marquee' ); ?></option>
					<option value="photo" <?php selected( $mode, 'photo' ); ?>><?php esc_html_e( 'Real photo only', 'marquee' ); ?></option>
					<option value="both" <?php selected( $mode, 'both' ); ?>><?php esc_html_e( 'Let the kid pick: cartoon or real photo', 'marquee' ); ?></option>
				</select></p>
			<p class="mq__fine"><?php esc_html_e( 'A kid with a picture gets two turns in the names break: first their picture (while the voice says their name), then their name. In “During songs” mode the picture shows first, then the name.', 'marquee' ); ?></p>
			<?php
			// what the FPP reported: pictures need names script 3.14+ and FPP 10+
			$fx   = function_exists( 'marquee_fpp_extra' ) ? marquee_fpp_extra() : array( 'script' => '', 'fpp_version' => '' );
			$warn = array();
			if ( $fx['script'] && version_compare( $fx['script'], '3.14', '<' ) ) {
				/* translators: %s: version number */
				$warn[] = sprintf( __( 'Your FPP is running names script %s. Pictures need 3.14 or newer: update the Marquee plugin on your FPP (Content Setup → Plugin Manager), or upload the new marquee_names.py, then restart FPP. Until then names go up without their pictures.', 'marquee' ), $fx['script'] );
			}
			if ( $fx['fpp_version'] && preg_match( '/^(\d+)/', $fx['fpp_version'], $fm ) && (int) $fm[1] < 10 ) {
				/* translators: %s: FPP version */
				$warn[] = sprintf( __( 'Your FPP is version %s. Pictures need FPP 10 or newer; names still go up without them.', 'marquee' ), $fx['fpp_version'] );
			}
			if ( 'live' === ( $s['names_mode'] ?? '' ) ) {
				$warn[] = __( 'Names go up during songs (Names in Lights, How names go up), so they aren’t spoken: the voice would talk over your music. The picture shows first, then the name. Pick “Between songs: a names break” to have names said out loud.', 'marquee' );
			}
			if ( $fx['script'] || $warn ) :
				?>
				<div class="mq-notice<?php echo $warn ? ' mq-notice--warn' : ''; ?>">
					<?php if ( $fx['script'] ) : ?><p class="mq__fine"><?php echo esc_html( sprintf( /* translators: 1: script version, 2: FPP version */ __( 'Your FPP reported: names script %1$s, FPP %2$s.', 'marquee' ), $fx['script'], $fx['fpp_version'] ? $fx['fpp_version'] : '?' ) ); ?></p><?php endif; ?>
					<?php foreach ( $warn as $w ) : ?><p><?php echo esc_html( $w ); ?></p><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php $vk = (string) ( $s['vision_key'] ?? '' ); $vn = marquee_vision_count(); ?>
			<div class="mq-vision">
				<label class="mq__feat"><span><b><?php esc_html_e( 'Check pictures automatically', 'marquee' ); ?></b><small><?php esc_html_e( 'Google Cloud Vision looks at each picture first. One that’s clean, shows exactly one face and has no words in it goes up without waiting for you. Anything else still waits for you in Show Control, with the reason. You can still remove any name and its picture at any time.', 'marquee' ); ?></small></span><input type="checkbox" name="photos_auto" value="1" <?php checked( ! empty( $s['photos_auto'] ) ); ?>></label>
				<p><label for="mq-vision-key"><b><?php esc_html_e( 'Google Cloud Vision API key', 'marquee' ); ?></b></label>
					<input id="mq-vision-key" name="vision_key" type="password" autocomplete="new-password" value="<?php echo $vk ? '••••••••' : ''; ?>">
					<button type="button" class="button" data-mq-vision-test><?php esc_html_e( 'Test the key', 'marquee' ); ?></button>
					<span class="mq__msg" data-mq-vision-msg aria-live="polite"></span>
					<?php if ( $vk ) : ?><br><label><input type="checkbox" name="vision_clear" value="1"> <?php esc_html_e( 'Remove my Google key', 'marquee' ); ?></label><?php endif; ?></p>
				<details class="mq-vision__how"><summary><?php esc_html_e( 'How to get a free key (about 5 minutes)', 'marquee' ); ?></summary>
					<ol>
						<li><?php echo wp_kses( __( 'Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> and sign in with a Google account. Make a new project (any name, like “My light show”).', 'marquee' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></li>
						<li><?php esc_html_e( 'Search for “Cloud Vision API” at the top, open it and click Enable. Google asks you to set up billing (a card on file) even for the free checks.', 'marquee' ); ?></li>
						<li><?php esc_html_e( 'Go to APIs & Services → Credentials → Create credentials → API key. Copy the key and paste it above, then click Test the key and Save.', 'marquee' ); ?></li>
						<li><?php esc_html_e( 'Recommended: on the key, choose Restrict key → Cloud Vision API only.', 'marquee' ); ?></li>
					</ol>
					<p class="mq__fine"><?php esc_html_e( 'Each picture uses 3 checks (safe, faces, words). Google gives 1,000 checks a month free, about 330 pictures; after that it’s about $1.50 per 1,000 checks (Google’s price, it may change). The picture is sent to Google only to be checked.', 'marquee' ); ?></p>
				</details>
				<?php if ( $vn ) : ?><p class="mq__fine"><?php echo esc_html( sprintf( /* translators: %s: number */ __( 'Checks used this month: %s (1,000 free each month).', 'marquee' ), number_format_i18n( $vn ) ) ); ?></p><?php endif; ?>
			</div>
		</div>
	</details>
	<?php
}

/* -------------------------------------------------------------- sending ---- */
/** Check the picture that came with a name. Returns an error message, or ''. */
function marquee_photo_from_post() {
	// phpcs:disable WordPress.Security.NonceVerification -- the name form's nonce is checked before this runs
	$raw = isset( $_POST['photo'] ) ? (string) wp_unslash( $_POST['photo'] ) : '';
	if ( '' === $raw ) {
		return array( '', '' );
	}
	$mode = marquee_photos_mode();
	$kind = isset( $_POST['photo_kind'] ) && 'photo' === $_POST['photo_kind'] ? 'photo' : 'cartoon';
	// phpcs:enable
	if ( '' === $mode ) {
		return array( '', '' );                       // switched off since the page loaded: the name goes without it
	}
	if ( ( 'cartoon' === $mode && 'photo' === $kind ) || ( 'photo' === $mode && 'cartoon' === $kind ) ) {
		$kind = $mode;
	}
	if ( ! preg_match( '#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $raw, $m ) || strlen( $m[1] ) > 200000 ) {
		return new WP_Error( 'photo', __( 'That picture didn’t work. Please try another one.', 'ridgeroad' ) );
	}
	$bin  = base64_decode( $m[1], true );
	$info = $bin ? @getimagesizefromstring( $bin ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $info || IMAGETYPE_JPEG !== $info[2] || $info[0] < 32 || $info[1] < 32 || $info[0] > 640 || $info[1] > 640 ) {
		return new WP_Error( 'photo', __( 'That picture didn’t work. Please try another one.', 'ridgeroad' ) );
	}
	return array( $bin, $kind );
}

add_filter( 'ridgeroad_name_send_error', function ( $err ) {
	if ( $err || '' === marquee_photos_mode() || empty( $_POST['photo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $err;
	}
	if ( empty( $_POST['photo_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return __( 'A parent or guardian needs to tick the box to say your picture can go on our screens.', 'ridgeroad' );
	}
	$p = marquee_photo_from_post();
	return is_wp_error( $p ) ? $p->get_error_message() : '';
} );

add_action( 'ridgeroad_name_added', function ( $id ) {
	if ( '' === marquee_photos_mode() || empty( $_POST['photo'] ) || empty( $_POST['photo_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$p = marquee_photo_from_post();
	if ( is_wp_error( $p ) || '' === $p[0] ) {
		return;
	}
	$dir = marquee_photo_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return;
	}
	if ( ! file_exists( $dir . 'index.html' ) ) {
		file_put_contents( $dir . 'index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- no folder listing
	}
	$file = wp_generate_password( 32, false, false ) . '.jpg';
	// Save it again through WordPress's image tools: drops anything hidden in the file (location, camera details).
	$tmp = wp_tempnam( 'mq-pic' );
	file_put_contents( $tmp, $p[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ed = wp_get_image_editor( $tmp );
	$ok = false;
	if ( ! is_wp_error( $ed ) ) {
		$ed->resize( 320, 320, false );
		$ed->set_quality( 85 );
		$ok = ! is_wp_error( $ed->save( $dir . $file, 'image/jpeg' ) );
	}
	wp_delete_file( $tmp );
	if ( ! $ok ) {
		return;
	}
	update_post_meta( $id, '_mq_pic', $file );
	update_post_meta( $id, '_mq_pic_kind', $p[1] );
	update_post_meta( $id, '_mq_pic_ok', 0 );                  // 0 waiting, 1 approved, -1 turned down
	marquee_photo_cleanup_soon();
	if ( marquee_vision_on() ) {
		$r = marquee_vision_check( file_get_contents( $dir . $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( true === $r ) {
			update_post_meta( $id, '_mq_pic_ok', 1 );          // passed: no need to wait for the owner
			update_post_meta( $id, '_mq_pic_why', 'auto' );
		} else {
			update_post_meta( $id, '_mq_pic_why', (string) $r ); // why it waits for the owner
		}
	}
} );

/** Delete a name's picture (shown, removed, turned down or too old). */
function marquee_photo_delete( $id ) {
	$file = (string) get_post_meta( $id, '_mq_pic', true );
	if ( $file && preg_match( '/^[A-Za-z0-9]{32}\.jpg$/', $file ) ) {
		wp_delete_file( marquee_photo_dir() . $file );
	}
	delete_post_meta( $id, '_mq_pic' );
}
add_action( 'ridgeroad_name_shown', 'marquee_photo_delete' );
add_action( 'wp_trash_post', function ( $id ) {
	if ( 'rr_shoutout' === get_post_type( $id ) ) {
		marquee_photo_delete( $id );
	}
} );
add_action( 'before_delete_post', function ( $id ) {
	if ( 'rr_shoutout' === get_post_type( $id ) ) {
		marquee_photo_delete( $id );
	}
} );

/* Clean-up: anything older than 2 hours goes, even if it was never shown. */
function marquee_photo_cleanup_soon() {
	if ( ! wp_next_scheduled( 'marquee_photo_cleanup' ) ) {
		wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'hourly', 'marquee_photo_cleanup' );
	}
}
add_action( 'marquee_photo_cleanup', function () {
	foreach ( glob( marquee_photo_dir() . '*.jpg' ) ?: array() as $f ) {
		if ( filemtime( $f ) < time() - MARQUEE_PHOTO_KEEP ) {
			wp_delete_file( $f );
		}
	}
	$old = get_posts( array(
		'post_type'   => 'rr_shoutout',
		'post_status' => 'any',
		'numberposts' => 200,
		'fields'      => 'ids',
		'meta_key'    => '_mq_pic', // phpcs:ignore WordPress.DB.SlowDBQuery
		'date_query'  => array( array( 'before' => '2 hours ago' ) ),
	) );
	foreach ( $old as $id ) {
		marquee_photo_delete( $id );
	}
	if ( ! glob( marquee_photo_dir() . '*.jpg' ) && ! $old ) {
		wp_clear_scheduled_hook( 'marquee_photo_cleanup' );
	}
} );

/** A name's picture: array( url, state ) where state is 'wait', 'ok' or ''. */
function marquee_photo_of( $id ) {
	$file = (string) get_post_meta( $id, '_mq_pic', true );
	if ( ! $file || ! file_exists( marquee_photo_dir() . $file ) ) {
		return array( '', '' );
	}
	$ok = (int) get_post_meta( $id, '_mq_pic_ok', true );
	if ( $ok < 0 ) {
		return array( '', '' );
	}
	return array( marquee_photo_dir( true ) . $file, $ok > 0 ? 'ok' : 'wait' );
}

/* ------------------------------------------------------------ to the FPP ---- */
add_filter( 'ridgeroad_names_pull_item', function ( $item, $p ) {
	if ( ! $item ) {
		return $item;
	}
	list( $url, $state ) = marquee_photo_of( $p->ID );
	if ( 'wait' === $state ) {
		if ( time() - (int) get_post_time( 'U', true, $p ) < MARQUEE_PHOTO_WAIT ) {
			return false;                                       // the name waits for its picture to be checked
		}
		update_post_meta( $p->ID, '_mq_pic_ok', -1 );         // nobody checked it in time: the name goes without it
		marquee_photo_delete( $p->ID );
		return $item;
	}
	if ( 'ok' === $state && '' !== marquee_photos_mode() ) {
		$item['photo'] = $url;
	}
	return $item;
}, 20, 2 );

/* ------------------------------------------------ Show Control / Show Manager ---- */
add_filter( 'ridgeroad_ctl_waiting_item', function ( $row, $p ) {
	list( $url, $state ) = marquee_photo_of( $p->ID );
	if ( $url ) {
		$row['photo']       = $url;
		$row['photo_state'] = $state;
		$row['photo_kind']  = (string) get_post_meta( $p->ID, '_mq_pic_kind', true );
		$why                = (string) get_post_meta( $p->ID, '_mq_pic_why', true );
		$row['photo_why']   = 'auto' === $why ? __( 'Approved by the automatic check', 'marquee' ) : $why;
	}
	return $row;
}, 10, 2 );

add_action( 'wp_ajax_rr_ctl_photo', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! function_exists( 'ridgeroad_ctl_can' ) || ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( ! $id || 'rr_shoutout' !== get_post_type( $id ) ) {
		wp_send_json_error( null, 404 );
	}
	if ( ! empty( $_POST['approve'] ) ) {
		update_post_meta( $id, '_mq_pic_ok', 1 );
	} else {
		update_post_meta( $id, '_mq_pic_ok', -1 );            // the name still goes up, without the picture
		marquee_photo_delete( $id );
	}
	wp_send_json_success( function_exists( 'ridgeroad_control_state' ) ? ridgeroad_control_state() : null );
} );

/* ------------------------------------------------- automatic check (Google) ---- */
function marquee_vision_on() {
	$s = marquee_get();
	return ! empty( $s['photos_auto'] ) && '' !== (string) $s['vision_key'];
}

/** Checks used this month (each picture is 3). */
function marquee_vision_count( $add = 0 ) {
	$m = wp_date( 'Y-m' );
	$c = get_option( 'marquee_vision_count' );
	$n = ( is_array( $c ) && ( $c['m'] ?? '' ) === $m ) ? (int) $c['n'] : 0;
	if ( $add ) {
		$n += $add;
		update_option( 'marquee_vision_count', array( 'm' => $m, 'n' => $n ), false );
	}
	return $n;
}

/** Ask Google Cloud Vision about one picture. Returns the raw answer, or a WP_Error with Google's reason. */
function marquee_vision_ask( $jpeg, $key ) {
	$r = wp_remote_post( 'https://vision.googleapis.com/v1/images:annotate?key=' . rawurlencode( $key ), array(
		'timeout' => 8,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array( 'requests' => array( array(
			'image'    => array( 'content' => base64_encode( $jpeg ) ),
			'features' => array(
				array( 'type' => 'SAFE_SEARCH_DETECTION' ),
				array( 'type' => 'FACE_DETECTION', 'maxResults' => 5 ),
				array( 'type' => 'TEXT_DETECTION', 'maxResults' => 1 ),
			),
		) ) ) ),
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'vision', isset( $j['error']['message'] ) ? (string) $j['error']['message'] : 'HTTP ' . wp_remote_retrieve_response_code( $r ) );
	}
	marquee_vision_count( 3 );
	$a = isset( $j['responses'][0] ) ? $j['responses'][0] : array();
	if ( isset( $a['error']['message'] ) ) {
		return new WP_Error( 'vision', (string) $a['error']['message'] );
	}
	return $a;
}

/** true = fine to show without the owner; otherwise the reason it waits (shown in Show Control). */
function marquee_vision_check( $jpeg ) {
	$a = marquee_vision_ask( $jpeg, (string) marquee_get( 'vision_key' ) );
	if ( is_wp_error( $a ) ) {
		return __( 'The automatic check couldn’t run, so it waits for you', 'marquee' );
	}
	$bad   = array( 'POSSIBLE', 'LIKELY', 'VERY_LIKELY' );
	$safe  = isset( $a['safeSearchAnnotation'] ) ? $a['safeSearchAnnotation'] : array();
	foreach ( array( 'adult', 'violence', 'racy' ) as $k ) {
		if ( ! isset( $safe[ $k ] ) || in_array( $safe[ $k ], $bad, true ) ) {
			return __( 'The automatic check wasn’t sure this picture is OK', 'marquee' );
		}
	}
	$faces = isset( $a['faceAnnotations'] ) ? count( $a['faceAnnotations'] ) : 0;
	if ( 0 === $faces ) {
		return __( 'The automatic check didn’t find a face', 'marquee' );
	}
	if ( $faces > 1 ) {
		return __( 'The automatic check found more than one face', 'marquee' );
	}
	$words = isset( $a['textAnnotations'][0]['description'] ) ? preg_replace( '/[^\p{L}]+/u', '', (string) $a['textAnnotations'][0]['description'] ) : '';
	if ( mb_strlen( $words ) >= 3 ) {
		return __( 'The automatic check found words in the picture', 'marquee' );
	}
	return true;
}

/* Settings: "Test the key" */
add_action( 'wp_ajax_marquee_vision_test', function () {
	check_ajax_referer( 'marquee_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$key = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
	if ( '' === $key || '••••••••' === $key ) {
		$key = (string) marquee_get( 'vision_key' );
	}
	if ( '' === $key ) {
		wp_send_json_error( array( 'message' => __( 'Paste your Google Cloud Vision API key first.', 'marquee' ) ) );
	}
	// a small plain test picture made right here
	$im = imagecreatetruecolor( 64, 64 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 30, 60, 140 ) );
	ob_start();
	imagejpeg( $im, null, 80 );
	$jpeg = ob_get_clean();
	imagedestroy( $im );
	$a = marquee_vision_ask( $jpeg, $key );
	if ( is_wp_error( $a ) ) {
		/* translators: %s: Google's own message */
		wp_send_json_error( array( 'message' => sprintf( __( 'Google said: %s', 'marquee' ), $a->get_error_message() ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Your key works. Click Save to keep it.', 'marquee' ) ) );
} );
