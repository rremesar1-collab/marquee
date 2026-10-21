<?php
/**
 * Kids' Light Show contest (shows made in the Christmas Light Lab).
 *
 * - Entries are "rr_lightshow" posts saved as Pending: nothing is public until the
 *   site owner publishes it (WP Admin -> Kids’ Light Shows).
 * - Only the child's first name and age are ever shown. The optional parent
 *   email and the claim code stay private (admin only).
 * - The show itself is a small JSON sequence, checked against a strict list of
 *   songs, zones, effects and colors before it is saved.
 * - People's Choice: one heart per device/network per picture, until the deadline.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'rr_lightshow', array(
		'labels'              => array(
			'name'          => __( 'Light Show Contest', 'ridgeroad' ),
			'singular_name' => __( 'Light Show Entry', 'ridgeroad' ),
			'menu_name'     => __( 'Kids’ Light Shows', 'ridgeroad' ),
			'all_items'     => __( 'All Entries', 'ridgeroad' ),
			'edit_item'     => __( 'Review Entry', 'ridgeroad' ),
			'not_found'     => __( 'No entries yet.', 'ridgeroad' ),
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => function_exists( 'marquee_get' ) ? 'marquee' : true, // under the Marquee menu on Marquee sites
		'menu_icon'           => 'dashicons-lightbulb',
		'menu_position'       => 26,
		'supports'            => array( 'title', 'thumbnail' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'exclude_from_search' => true,
	) );
} );

/** Contest timing helpers (deadline = end of that day, show time zone). */
function ridgeroad_contest_end_ts() {
	$d = ridgeroad_mod( 'contest_deadline' );
	if ( ! $d ) {
		return 0;
	}
	try {
		$dt = new DateTime( $d . ' 23:59:59', ridgeroad_tz() );
		return $dt->getTimestamp();
	} catch ( Exception $e ) {
		return 0;
	}
}
function ridgeroad_contest_open() {
	$end = ridgeroad_contest_end_ts();
	return ridgeroad_mod( 'contest_on' ) && ( ! $end || time() <= $end );
}
function ridgeroad_contest_date( $key ) {
	$d = ridgeroad_mod( $key );
	$t = $d ? DateTime::createFromFormat( '!Y-m-d', $d ) : false;
	return $t ? $t->format( 'F j' ) : '';
}
function ridgeroad_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
}

/** Approved entries, newest first. */
function ridgeroad_art_entries( $limit = 60 ) {
	$out = array();
	$q   = get_posts( array(
		'post_type'      => 'rr_lightshow',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	foreach ( $q as $p ) {
		$img = get_the_post_thumbnail_url( $p, 'large' );
		if ( ! $img ) {
			continue;
		}
		$out[] = array(
			'id'     => $p->ID,
			'name'   => $p->post_title,
			'age'    => (int) get_post_meta( $p->ID, '_rr_age', true ),
			'img'    => $img,
			'show'   => get_post_meta( $p->ID, '_rr_show', true ),
			'title'  => get_post_meta( $p->ID, '_rr_showname', true ),
			'votes'  => (int) get_post_meta( $p->ID, '_rr_votes', true ),
			'judges' => (bool) get_post_meta( $p->ID, '_rr_judges', true ),
			'people' => (bool) get_post_meta( $p->ID, '_rr_people', true ),
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Submit an entry
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_rr_art_submit', 'ridgeroad_art_submit' );
add_action( 'wp_ajax_nopriv_rr_art_submit', 'ridgeroad_art_submit' );

function ridgeroad_art_submit() {
	$fail = function ( $msg ) {
		wp_send_json_error( array( 'message' => $msg ), 400 );
	};
	if ( ! check_ajax_referer( 'rr_lightshow', 'nonce', false ) ) {
		$fail( __( 'This page has been open a while. Please refresh it and try again.', 'ridgeroad' ) );
	}
	if ( ! ridgeroad_contest_open() ) {
		$fail( __( 'Sorry, the contest is closed for this year. You can still make and download shows for fun!', 'ridgeroad' ) );
	}
	// Honeypot: real people never fill this hidden field.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success( array( 'code' => 'ELF-' . wp_rand( 1000, 9999 ) ) );
	}
	$ipk   = 'rr_art_ip_' . ridgeroad_ip_hash();
	$count = (int) get_transient( $ipk );
	if ( $count >= 5 ) {
		$fail( __( 'Lots of entries from here today! Please try again tomorrow.', 'ridgeroad' ) );
	}
	if ( ! empty( $_POST['website'] ) ) {
		$fail( __( 'Something went wrong. Please try again.', 'ridgeroad' ) );
	}

	$name = isset( $_POST['child'] ) ? sanitize_text_field( wp_unslash( $_POST['child'] ) ) : '';
	$name = trim( preg_replace( '/[^\p{L}\'\- ]/u', '', $name ) );
	$name = preg_split( '/\s+/', $name )[0]; // first name only
	$name = mb_substr( $name, 0, 20 );
	$age  = isset( $_POST['age'] ) ? (int) $_POST['age'] : 0;
	$mail = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' === $name ) {
		$fail( __( 'Please type your child’s first name.', 'ridgeroad' ) );
	}
	if ( $age < 3 || $age > 12 ) {
		$fail( __( 'The contest is for kids ages 3 to 12.', 'ridgeroad' ) );
	}
	if ( ! $mail || ! is_email( $mail ) ) {
		$fail( __( 'Please enter a parent email so we can send your claim code and tell you if your child wins.', 'ridgeroad' ) );
	}
	if ( empty( $_POST['consent'] ) ) {
		$fail( __( 'A parent or guardian needs to check the permission box.', 'ridgeroad' ) );
	}

	// The light show (JSON) and a snapshot picture of it for the Show Wall.
	$show = ridgeroad_show_clean( isset( $_POST['show'] ) ? wp_unslash( $_POST['show'] ) : '' ); // phpcs:ignore
	if ( ! $show ) {
		$fail( __( 'Please make a light show first: light up at least one decoration in the Light Lab.', 'ridgeroad' ) );
	}
	$raw = '';
	if ( ! empty( $_POST['thumb'] ) ) {
		$d = wp_unslash( $_POST['thumb'] ); // phpcs:ignore
		if ( strlen( $d ) < 3 * MB_IN_BYTES && preg_match( '#^data:image/(png|jpeg);base64,#', $d, $m ) ) {
			$raw = base64_decode( substr( $d, strlen( $m[0] ) ), true );
		}
	}
	$jpg = $raw ? ridgeroad_art_clean_image( $raw ) : '';
	if ( ! $jpg ) {
		$fail( __( 'Something went wrong making the picture of your show. Please try again.', 'ridgeroad' ) );
	}
	$showname = isset( $_POST['showname'] ) ? sanitize_text_field( wp_unslash( $_POST['showname'] ) ) : '';
	$showname = mb_substr( trim( $showname ), 0, 40 );

	// Save the cleaned picture into the media library.
	$file = 'lightshow-' . strtolower( wp_generate_password( 12, false ) ) . '.jpg';
	$up   = wp_upload_bits( $file, null, $jpg );
	if ( ! empty( $up['error'] ) ) {
		$fail( __( 'Something went wrong saving the picture. Please try again.', 'ridgeroad' ) );
	}

	$code = ridgeroad_art_new_code();
	$id   = wp_insert_post( array(
		'post_type'   => 'rr_lightshow',
		'post_status' => 'pending',
		'post_title'  => $name,
	), true );
	if ( is_wp_error( $id ) ) {
		$fail( __( 'Something went wrong. Please try again.', 'ridgeroad' ) );
	}
	$att = wp_insert_attachment( array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'Light show — ' . $name,
		'post_status'    => 'inherit',
	), $up['file'], $id );
	if ( $att && ! is_wp_error( $att ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
		set_post_thumbnail( $id, $att );
	}
	update_post_meta( $id, '_rr_age', $age );
	update_post_meta( $id, '_rr_code', $code );
	update_post_meta( $id, '_rr_votes', 0 );
	update_post_meta( $id, '_rr_show', wp_json_encode( $show ) );
	update_post_meta( $id, '_rr_showname', $showname );
	update_post_meta( $id, '_rr_song', $show['song'] );
	if ( $mail ) {
		update_post_meta( $id, '_rr_email', $mail );
	}
	set_transient( $ipk, $count + 1, DAY_IN_SECONDS );
	ridgeroad_stat_add( 'entry' );

	// Let the site owner know there's something to review.
	$to = ridgeroad_mod( 'contest_notify' );
	$to = $to ? $to : get_option( 'admin_email' );
	wp_mail(
		$to,
		/* translators: %s: child's first name */
		sprintf( __( 'New light show contest entry from %s', 'ridgeroad' ), $name ),
		sprintf(
			"%s, age %d, sent a light show for the contest.\n\nClaim code: %s\n\nWatch and review it here (it stays hidden until you publish it):\n%s",
			$name,
			$age,
			$code,
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		)
	);

	// Confirmation to the parent with the claim code.
	ridgeroad_show_mail_entry( $id );

	wp_send_json_success( array( 'code' => $code ) );
}

/* -------------------------------------------------------------------------
 * Emails to parents
 * ---------------------------------------------------------------------- */
function ridgeroad_show_mail_details( $id ) {
	$addr = trim( (string) ridgeroad_mod( 'address' ) );
	return array(
		'name'   => get_the_title( $id ),
		'show'   => get_post_meta( $id, '_rr_showname', true ),
		'song'   => ridgeroad_song_name( get_post_meta( $id, '_rr_song', true ) ),
		'code'   => get_post_meta( $id, '_rr_code', true ),
		'mail'   => get_post_meta( $id, '_rr_email', true ),
		'dead'   => ridgeroad_contest_date( 'contest_deadline' ),
		'pickup' => ridgeroad_contest_date( 'contest_pickup' ),
		'where'  => ( $addr && ! preg_match( '#^https?://#i', $addr ) && ! preg_match( '#^-?\d+\.\d+\s*,#', $addr ) ) ? $addr : '',
		'map'    => ridgeroad_directions_url(),
		'site'   => get_bloginfo( 'name' ),
		'url'    => ridgeroad_kids_url(),
	);
}

/** "Your show is in!" email, sent right after a parent enters. */
function ridgeroad_show_mail_entry( $id ) {
	$d = ridgeroad_show_mail_details( $id );
	if ( ! $d['mail'] ) {
		return false;
	}
	$body  = sprintf( "Thank you for entering %s's light show in the %s Light Show Contest!\n\n", $d['name'], $d['site'] );
	$body .= sprintf( "Show: %s\nSong: %s\n\n", $d['show'] ? $d['show'] : 'My Light Show', $d['song'] );
	$body .= sprintf( "YOUR CLAIM CODE: %s\nKeep this email. If your child wins, bring this code to the house to pick up the prize.\n\n", $d['code'] );
	$body .= "What happens next:\n";
	$body .= "- We check every show before it goes on the Show Wall (usually within a day or two).\n";
	if ( $d['dead'] ) {
		$body .= sprintf( "- Entries and hearts close on %s. Then we pick the Judges' Pick and the People's Choice winners.\n", $d['dead'] );
	}
	$body .= "- If your child wins, we'll email you here.\n\n";
	if ( $d['url'] ) {
		$body .= sprintf( "Watch the Show Wall: %s\n\n", $d['url'] );
	}
	$body .= "We only use this email for the contest. It is never shown on the website or shared.\n";
	return wp_mail( $d['mail'], sprintf( 'Your light show is in! Claim code %s', $d['code'] ), $body );
}

/** "You won!" email, sent from the entry screen in WP Admin. */
function ridgeroad_show_mail_winner( $id ) {
	$d     = ridgeroad_show_mail_details( $id );
	$won   = array();
	$prize = array();
	if ( get_post_meta( $id, '_rr_judges', true ) ) {
		$won[]   = "Judges' Pick";
		$prize[] = ridgeroad_mod( 'contest_prize1' ) . " (Judges' Pick)";
	}
	if ( get_post_meta( $id, '_rr_people', true ) ) {
		$won[]   = "People's Choice";
		$prize[] = ridgeroad_mod( 'contest_prize2' ) . " (People's Choice)";
	}
	if ( ! $d['mail'] || ! $won ) {
		return false;
	}
	$body  = sprintf( "Congratulations! %s's light show%s won %s in the %s Light Show Contest!\n\n", $d['name'], $d['show'] ? ' "' . $d['show'] . '"' : '', implode( ' and ', $won ), $d['site'] );
	$body .= sprintf( "Prize: %s\n\n", implode( ', ', $prize ) );
	$body .= "How to pick it up:\n";
	$body .= sprintf( "- Come by the house with %s during show hours%s.\n", $d['name'], $d['pickup'] ? ' by ' . $d['pickup'] : '' );
	$body .= sprintf( "- Bring your claim code: %s\n", $d['code'] );
	if ( $d['where'] ) {
		$body .= sprintf( "- Address: %s\n", $d['where'] );
	}
	if ( $d['map'] ) {
		$body .= sprintf( "- Directions: %s\n", $d['map'] );
	}
	$body .= "\nThank you for being part of our show. Merry Christmas!\n";
	return wp_mail( $d['mail'], sprintf( 'You won! %s Light Show Contest', $d['site'] ), $body );
}

add_action( 'admin_post_rr_show_mail_winner', function () {
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! check_admin_referer( 'rr_won_' . $id ) ) {
		wp_die( esc_html__( 'Sorry, you can’t do that.', 'ridgeroad' ) );
	}
	$ok = ridgeroad_show_mail_winner( $id );
	if ( $ok ) {
		update_post_meta( $id, '_rr_won_mailed', time() );
	}
	wp_safe_redirect( add_query_arg( 'rr_won', $ok ? 'sent' : 'fail', admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
	exit;
} );
add_action( 'admin_notices', function () {
	if ( empty( $_GET['rr_won'] ) ) { // phpcs:ignore
		return;
	}
	$ok = 'sent' === $_GET['rr_won']; // phpcs:ignore
	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		$ok ? 'success' : 'error',
		$ok ? esc_html__( 'The “You won!” email was sent to the parent.', 'ridgeroad' ) : esc_html__( 'The email could not be sent. Check that the entry has a parent email and is marked as a winner (and saved), and that your site can send email.', 'ridgeroad' )
	);
} );

/** Decode, shrink to max 1600px, flatten on white and re-save as JPEG (drops EXIF/GPS). */
function ridgeroad_art_clean_image( $raw ) {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return '';
	}
	$info = @getimagesizefromstring( $raw ); // phpcs:ignore
	if ( ! $info || ! in_array( $info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF ), true ) ) {
		return '';
	}
	if ( $info[0] * $info[1] > 40000000 ) {
		return '';
	}
	$src = @imagecreatefromstring( $raw ); // phpcs:ignore
	if ( ! $src ) {
		return '';
	}
	// Phone photos are often stored sideways with an EXIF rotation flag.
	if ( IMAGETYPE_JPEG === $info[2] && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( 'data://image/jpeg;base64,' . base64_encode( $raw ) ); // phpcs:ignore
		$o    = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
		$rot  = array( 3 => 180, 6 => -90, 8 => 90 );
		if ( isset( $rot[ $o ] ) ) {
			$r = imagerotate( $src, $rot[ $o ], 0 );
			if ( $r ) {
				imagedestroy( $src );
				$src = $r;
			}
		}
	}
	$w     = imagesx( $src );
	$h     = imagesy( $src );
	$scale = min( 1, 1600 / max( $w, $h ) );
	$nw    = max( 1, (int) round( $w * $scale ) );
	$nh    = max( 1, (int) round( $h * $scale ) );
	$dst   = imagecreatetruecolor( $nw, $nh );
	imagefill( $dst, 0, 0, imagecolorallocate( $dst, 255, 255, 255 ) );
	imagecopyresampled( $dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
	ob_start();
	imagejpeg( $dst, null, 86 );
	$jpg = ob_get_clean();
	imagedestroy( $src );
	imagedestroy( $dst );
	return $jpg;
}

/** Unique claim code like ELF-4827. */
function ridgeroad_art_new_code() {
	$words = array( 'ELF', 'STAR', 'SNOW', 'JOY', 'BELL', 'TREE' );
	for ( $i = 0; $i < 20; $i++ ) {
		$code = $words[ wp_rand( 0, count( $words ) - 1 ) ] . '-' . wp_rand( 1000, 9999 );
		$hit  = get_posts( array(
			'post_type'   => 'rr_lightshow',
			'post_status' => 'any',
			'meta_key'    => '_rr_code', // phpcs:ignore
			'meta_value'  => $code, // phpcs:ignore
			'fields'      => 'ids',
		) );
		if ( ! $hit ) {
			return $code;
		}
	}
	return 'ELF-' . wp_rand( 10000, 99999 );
}

/* -------------------------------------------------------------------------
 * People's Choice votes
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_rr_art_vote', 'ridgeroad_art_vote' );
add_action( 'wp_ajax_nopriv_rr_art_vote', 'ridgeroad_art_vote' );

function ridgeroad_art_vote() {
	if ( ! check_ajax_referer( 'rr_lightshow', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Please refresh the page and try again.', 'ridgeroad' ) ), 400 );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$p  = $id ? get_post( $id ) : null;
	if ( ! $p || 'rr_lightshow' !== $p->post_type || 'publish' !== $p->post_status ) {
		wp_send_json_error( array( 'message' => __( 'That show isn’t on the Show Wall.', 'ridgeroad' ) ), 404 );
	}
	$votes = (int) get_post_meta( $id, '_rr_votes', true );
	if ( ! ridgeroad_contest_open() ) {
		wp_send_json_error( array( 'message' => __( 'Voting is closed.', 'ridgeroad' ), 'votes' => $votes ), 400 );
	}
	$who    = ridgeroad_ip_hash();
	$voters = get_post_meta( $id, '_rr_voters', true );
	$voters = is_array( $voters ) ? $voters : array();
	if ( in_array( $who, $voters, true ) ) {
		wp_send_json_success( array( 'votes' => $votes, 'already' => true ) );
	}
	$voters[] = $who;
	update_post_meta( $id, '_rr_voters', $voters );
	update_post_meta( $id, '_rr_votes', ++$votes );
	ridgeroad_stat_add( 'heart' );
	wp_send_json_success( array( 'votes' => $votes ) );
}

/* -------------------------------------------------------------------------
 * Admin: list columns + review box
 * ---------------------------------------------------------------------- */
add_filter( 'manage_rr_lightshow_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox" />',
		'rr_pic'    => __( 'Show', 'ridgeroad' ),
		'title'     => __( 'First name', 'ridgeroad' ),
		'rr_song'   => __( 'Song / show name', 'ridgeroad' ),
		'rr_age'    => __( 'Age', 'ridgeroad' ),
		'rr_votes'  => __( 'Hearts', 'ridgeroad' ),
		'rr_code'   => __( 'Claim code', 'ridgeroad' ),
		'rr_winner' => __( 'Winner', 'ridgeroad' ),
		'date'      => __( 'Sent', 'ridgeroad' ),
	);
} );
add_action( 'manage_rr_lightshow_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'rr_pic':
			$u = get_the_post_thumbnail_url( $id, 'medium' );
			if ( $u ) {
				printf( '<a href="%s" target="_blank" title="%s"><img src="%s" style="width:160px;height:auto;border-radius:6px;display:block" alt=""></a>', esc_url( ridgeroad_show_watch_url( $id ) ), esc_attr__( 'Watch this show', 'ridgeroad' ), esc_url( $u ) );
			}
			printf( '<a href="%s" target="_blank">%s</a>', esc_url( ridgeroad_show_watch_url( $id ) ), esc_html__( 'Watch show', 'ridgeroad' ) );
			break;
		case 'rr_song':
			echo esc_html( ridgeroad_song_name( get_post_meta( $id, '_rr_song', true ) ) );
			$sn = get_post_meta( $id, '_rr_showname', true );
			if ( $sn ) {
				echo '<br><em>“' . esc_html( $sn ) . '”</em>';
			}
			break;
		case 'rr_age':
			echo (int) get_post_meta( $id, '_rr_age', true );
			break;
		case 'rr_votes':
			echo '&#9829; ' . (int) get_post_meta( $id, '_rr_votes', true );
			break;
		case 'rr_code':
			echo '<code>' . esc_html( get_post_meta( $id, '_rr_code', true ) ) . '</code>';
			break;
		case 'rr_winner':
			$w = array();
			if ( get_post_meta( $id, '_rr_judges', true ) ) {
				$w[] = __( 'Judges’ Pick', 'ridgeroad' );
			}
			if ( get_post_meta( $id, '_rr_people', true ) ) {
				$w[] = __( 'People’s Choice', 'ridgeroad' );
			}
			echo esc_html( implode( ', ', $w ) );
			break;
	}
}, 10, 2 );
add_filter( 'manage_edit-rr_lightshow_sortable_columns', function ( $c ) {
	$c['rr_votes'] = 'rr_votes';
	return $c;
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'rr_votes' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_rr_votes' );
		$q->set( 'orderby', 'meta_value_num' );
	}
} );

add_action( 'add_meta_boxes_rr_lightshow', function () {
	add_meta_box( 'rr_art_box', __( 'Contest details', 'ridgeroad' ), 'ridgeroad_art_box', 'rr_lightshow', 'normal', 'high' );
} );
function ridgeroad_art_box( $post ) {
	wp_nonce_field( 'rr_art_box', 'rr_art_box_nonce' );
	$img = get_the_post_thumbnail_url( $post, 'large' );
	if ( $img ) {
		printf( '<p><a href="%s" target="_blank"><img src="%s" style="max-width:100%%;height:auto;border:1px solid #ccc;border-radius:8px" alt=""></a></p>', esc_url( ridgeroad_show_watch_url( $post->ID ) ), esc_url( $img ) );
	}
	printf( '<p><a class="button button-primary" href="%s" target="_blank">%s</a></p>', esc_url( ridgeroad_show_watch_url( $post->ID ) ), esc_html__( 'Watch this light show', 'ridgeroad' ) );
	$mail = get_post_meta( $post->ID, '_rr_email', true );
	echo '<table class="form-table" role="presentation"><tbody>';
	printf( '<tr><th>%s</th><td><input type="number" name="rr_age" min="3" max="12" value="%d"></td></tr>', esc_html__( 'Age', 'ridgeroad' ), (int) get_post_meta( $post->ID, '_rr_age', true ) );
	printf( '<tr><th>%s</th><td><code style="font-size:16px">%s</code><p class="description">%s</p></td></tr>', esc_html__( 'Claim code', 'ridgeroad' ), esc_html( get_post_meta( $post->ID, '_rr_code', true ) ), esc_html__( 'The family was shown this code. Ask for it when they pick up a prize. It is never shown on the website.', 'ridgeroad' ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Parent email (private)', 'ridgeroad' ), $mail ? '<a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a>' : '—' );
	printf( '<tr><th>%s</th><td>&#9829; %d</td></tr>', esc_html__( 'People’s Choice hearts', 'ridgeroad' ), (int) get_post_meta( $post->ID, '_rr_votes', true ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Song', 'ridgeroad' ), esc_html( ridgeroad_song_name( get_post_meta( $post->ID, '_rr_song', true ) ) ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Show name', 'ridgeroad' ), esc_html( get_post_meta( $post->ID, '_rr_showname', true ) ) );
	printf(
		'<tr><th>%s</th><td><label><input type="checkbox" name="rr_judges" value="1" %s> %s</label><br><label><input type="checkbox" name="rr_people" value="1" %s> %s</label><p class="description">%s</p></td></tr>',
		esc_html__( 'Winner', 'ridgeroad' ),
		checked( (bool) get_post_meta( $post->ID, '_rr_judges', true ), true, false ),
		esc_html__( 'Judges’ Pick', 'ridgeroad' ),
		checked( (bool) get_post_meta( $post->ID, '_rr_people', true ), true, false ),
		esc_html__( 'People’s Choice', 'ridgeroad' ),
		esc_html__( 'Winners are announced on the Kids’ Corner page (first name and age only). The entry must be Published.', 'ridgeroad' )
	);
	$is_winner = get_post_meta( $post->ID, '_rr_judges', true ) || get_post_meta( $post->ID, '_rr_people', true );
	$sent      = (int) get_post_meta( $post->ID, '_rr_won_mailed', true );
	echo '<tr><th>' . esc_html__( 'Tell the winner', 'ridgeroad' ) . '</th><td>';
	if ( ! $mail ) {
		echo esc_html__( 'No parent email on this entry.', 'ridgeroad' );
	} elseif ( ! $is_winner ) {
		echo '<span class="description">' . esc_html__( 'Tick Judges’ Pick or People’s Choice and click Update first. Then a button appears here to email the parent.', 'ridgeroad' ) . '</span>';
	} else {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=rr_show_mail_winner&post=' . $post->ID ), 'rr_won_' . $post->ID );
		printf(
			'<a class="button button-primary" href="%s" onclick="return confirm(%s);">%s</a>',
			esc_url( $url ),
			esc_attr( wp_json_encode( __( 'Send the “You won!” email to the parent now?', 'ridgeroad' ) ) ),
			$sent ? esc_html__( 'Send the “You won!” email again', 'ridgeroad' ) : esc_html__( 'Send “You won!” email', 'ridgeroad' )
		);
		if ( $sent ) {
			/* translators: %s: date */
			echo ' <span class="description">' . esc_html( sprintf( __( 'Sent %s', 'ridgeroad' ), wp_date( 'M j, g:i a', $sent ) ) ) . '</span>';
		}
		echo '<p class="description">' . esc_html__( 'Tells them what they won, to bring the claim code, and when to pick up the prize.', 'ridgeroad' ) . '</p>';
	}
	echo '</td></tr>';
	echo '</tbody></table>';
	echo '<p>' . esc_html__( 'To show it on the Show Wall, click Publish. To turn it down, move it to the Trash.', 'ridgeroad' ) . '</p>';
}
add_action( 'save_post_rr_lightshow', function ( $id ) {
	if ( ! isset( $_POST['rr_art_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_art_box_nonce'] ) ), 'rr_art_box' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	if ( isset( $_POST['rr_age'] ) ) {
		update_post_meta( $id, '_rr_age', max( 3, min( 12, (int) $_POST['rr_age'] ) ) );
	}
	update_post_meta( $id, '_rr_judges', empty( $_POST['rr_judges'] ) ? 0 : 1 );
	update_post_meta( $id, '_rr_people', empty( $_POST['rr_people'] ) ? 0 : 1 );
} );

/** Pending-entry count bubble on the admin menu. */
add_action( 'admin_menu', function () {
	global $menu;
	$n = (int) wp_count_posts( 'rr_lightshow' )->pending;
	if ( ! $n ) {
		return;
	}
	foreach ( $menu as $k => $m ) {
		if ( isset( $m[2] ) && 'edit.php?post_type=rr_lightshow' === $m[2] ) {
			$menu[ $k ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>'; // phpcs:ignore
		}
	}
}, 99 );

/** Delete the picture file too when an entry is permanently deleted. */
add_action( 'before_delete_post', function ( $id ) {
	if ( 'rr_lightshow' === get_post_type( $id ) ) {
		$t = get_post_thumbnail_id( $id );
		if ( $t ) {
			wp_delete_attachment( $t, true );
		}
	}
} );

/** Fresh nonce for cached pages (admin-ajax is never page-cached). */
add_action( 'wp_ajax_rr_art_nonce', 'ridgeroad_art_nonce' );
add_action( 'wp_ajax_nopriv_rr_art_nonce', 'ridgeroad_art_nonce' );
function ridgeroad_art_nonce() {
	nocache_headers();
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'rr_lightshow' ), 'open' => ridgeroad_contest_open() ) );
}


/* -------------------------------------------------------------------------
 * Light show data (Light Lab v3)
 * ---------------------------------------------------------------------- */
/** Songs in the Light Lab: real recordings in assets/lightlab/songs (beat measured from each file). */
function ridgeroad_lab_songs() {
	$std = array(
		'jingle-bells'       => array( 'name' => 'Jingle Bells', 'file' => 'jingle-bells.mp3', 'bpm' => 100.0, 'offset' => 0.17, 'len' => 89.6 ),
		'deck-the-halls'     => array( 'name' => 'Deck the Halls', 'file' => 'deck-the-halls.mp3', 'bpm' => 110.0, 'offset' => 0.5, 'len' => 89.4 ),
		'we-wish-you'        => array( 'name' => 'We Wish You a Merry Christmas', 'file' => 'we-wish-you.mp3', 'bpm' => 171.7, 'offset' => 0.17, 'len' => 89.9 ),
		'carol-of-the-bells' => array( 'name' => 'Carol of the Bells', 'file' => 'carol-of-the-bells.mp3', 'bpm' => 146.05, 'offset' => 0.38, 'len' => 89.8 ),
		'joy-to-the-world'   => array( 'name' => 'Joy to the World', 'file' => 'joy-to-the-world.mp3', 'bpm' => 127.1, 'offset' => 0.13, 'len' => 89.5 ),
	);
	if ( ! function_exists( 'marquee_get' ) ) {
		return $std;
	}
	// Marquee shows: the standard songs only if they ship with the plugin, plus the show's own uploads.
	$out = array();
	foreach ( $std as $k => $song ) {
		if ( defined( 'RR_FEAT_DIR' ) && file_exists( RR_FEAT_DIR . 'assets/lightlab/songs/' . $song['file'] ) ) {
			$out[ $k ] = $song;
		}
	}
	foreach ( (array) marquee_get( 'lab_songs' ) as $id ) {
		$id  = (int) $id;
		$url = $id ? wp_get_attachment_url( $id ) : '';
		if ( ! $url ) {
			continue;
		}
		$meta = wp_get_attachment_metadata( $id );
		$bpm  = (float) get_post_meta( $id, '_marquee_bpm', true );
		$out[ 'u' . $id ] = array(
			'name'   => get_the_title( $id ),
			'src'    => $url,
			'bpm'    => ( $bpm >= 40 && $bpm <= 240 ) ? $bpm : 120.0, // lights pulse at 120 beats a minute unless the show sets the song's speed
			'offset' => 0,
			'len'    => ! empty( $meta['length'] ) ? (float) $meta['length'] : 90.0,
		);
	}
	return $out;
}
function ridgeroad_song_name( $k ) {
	$s   = ridgeroad_lab_songs();
	$old = array( 'jingle' => 'Jingle Bells', 'deck' => 'Deck the Halls', 'wish' => 'We Wish You a Merry Christmas' );
	return isset( $s[ $k ] ) ? $s[ $k ]['name'] : ( isset( $old[ $k ] ) ? $old[ $k ] : '' );
}

/** Validate a show sent from the Light Lab. Returns a clean array or false. */
function ridgeroad_show_clean( $json ) {
	if ( ! is_string( $json ) || strlen( $json ) > 20000 ) {
		return false;
	}
	$s = json_decode( $json, true );
	if ( ! is_array( $s ) || 3 !== ( isset( $s['v'] ) ? (int) $s['v'] : 0 ) || empty( $s['cells'] ) || ! is_array( $s['cells'] ) ) {
		return false;
	}
	$songs  = ridgeroad_lab_songs();
	$props  = array( 'tree', 'roof', 'win', 'flake', 'spin', 'faces', 'arch', 'mini', 'gift', 'garage' );
	$styles = array( 'steady', 'twinkle', 'chase', 'pulse', 'rainbow', 'candy', 'off' );
	$colors = array( 'red', 'green', 'blue', 'gold', 'purple', 'ice', 'white', 'orange', 'pink', 'mix' );
	$song   = isset( $s['song'] ) && isset( $songs[ $s['song'] ] ) ? $s['song'] : '';
	if ( ! $song && $songs ) { // a show with no songs at all still works (lights only)
		return false;
	}
	$out = array( 'v' => 3, 'song' => $song, 'name' => '', 'cells' => array() );
	$lit = 0;
	foreach ( $props as $p ) {
		$row = isset( $s['cells'][ $p ] ) && is_array( $s['cells'][ $p ] ) ? array_values( $s['cells'][ $p ] ) : array();
		$out['cells'][ $p ] = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$c  = isset( $row[ $i ] ) && is_array( $row[ $i ] ) ? $row[ $i ] : array();
			$st = isset( $c['s'] ) && in_array( $c['s'], $styles, true ) ? $c['s'] : 'off';
			$co = isset( $c['c'] ) && in_array( $c['c'], $colors, true ) ? $c['c'] : 'white';
			if ( 'off' !== $st ) {
				$lit++;
			}
			$out['cells'][ $p ][] = array( 's' => $st, 'c' => $co );
		}
	}
	return $lit ? $out : false;
}

/** Where the site owner can watch an entry (works for pending entries too). */
function ridgeroad_show_watch_url( $id ) {
	$u = ridgeroad_kids_url();
	return $u ? add_query_arg( 'rr_watch', (int) $id, $u ) . '#show-wall' : '';
}

/** Show data for ?rr_watch=ID (published, or any status for editors). */
function ridgeroad_show_for_watch() {
	$id = isset( $_GET['rr_watch'] ) ? (int) $_GET['rr_watch'] : 0; // phpcs:ignore
	$p  = $id ? get_post( $id ) : null;
	if ( ! $p || 'rr_lightshow' !== $p->post_type ) {
		return null;
	}
	if ( 'publish' !== $p->post_status && ! current_user_can( 'edit_post', $id ) ) {
		return null;
	}
	return array(
		'show'  => get_post_meta( $id, '_rr_show', true ),
		'label' => sprintf( '%s, age %d', $p->post_title, (int) get_post_meta( $id, '_rr_age', true ) ),
		'title' => get_post_meta( $id, '_rr_showname', true ),
	);
}

/**
 * Clear the page cache when a contest entry or show video goes up or comes down. These are
 * private post types, so caching plugins don't clear the Kids' page by themselves.
 */
function ridgeroad_purge_page_cache() {
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();                      // SiteGround Speed Optimizer
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();                            // WP Rocket
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();                                 // W3 Total Cache
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();                           // WP Super Cache
	}
	do_action( 'litespeed_purge_all' );                   // LiteSpeed Cache
}
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $new !== $old && ( 'publish' === $new || 'publish' === $old ) && in_array( $post->post_type, array( 'rr_lightshow', 'rr_sequence' ), true ) ) {
		ridgeroad_purge_page_cache();
	}
}, 10, 3 );
