<?php
/**
 * Show Control (WP Admin → Show Control): everything you touch during a show on one
 * page, plus simple usage totals.
 *
 * - Stats are totals per show night only (what was used, never who). Nothing here
 *   is shown to visitors.
 * - FPP's status arrives with the names script's check-in (FPP reaches out; the
 *   website never connects into your home network).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Stats
 * ---------------------------------------------------------------------- */
const RR_STAT_EVENTS = array( 'visit', 'kids', 'lab_play', 'lab_save', 'elf', 'trivia', 'coloring', 'name_sent', 'name_shown', 'entry', 'heart', 'song_vote', 'app_install', 'app_open', 'alerts_on', 'buzz_sent' );

/** Show-night date: anything before 6 AM counts toward the night before. */
function ridgeroad_stat_day( $ts = null ) {
	$ts = $ts ? $ts : time();
	return wp_date( 'Y-m-d', $ts - 6 * HOUR_IN_SECONDS, ridgeroad_tz() );
}
function ridgeroad_stat_add( $ev, $n = 1, $sum = null ) {
	if ( ! in_array( $ev, RR_STAT_EVENTS, true ) ) {
		return;
	}
	$all = get_option( 'ridgeroad_stats', array() );
	$all = is_array( $all ) ? $all : array();
	$d   = ridgeroad_stat_day();
	if ( ! isset( $all[ $d ] ) ) {
		$all[ $d ] = array();
		if ( count( $all ) > 150 ) {
			ksort( $all );
			$all = array_slice( $all, -150, null, true );
		}
	}
	$all[ $d ][ $ev ] = ( isset( $all[ $d ][ $ev ] ) ? (int) $all[ $d ][ $ev ] : 0 ) + $n;
	if ( null !== $sum ) {
		$all[ $d ][ $ev . '_sum' ] = ( isset( $all[ $d ][ $ev . '_sum' ] ) ? (int) $all[ $d ][ $ev . '_sum' ] : 0 ) + (int) $sum;
	}
	update_option( 'ridgeroad_stats', $all, false );
}

/** Who is on the site right now (hashed, kept for 2.5 minutes). */
function ridgeroad_presence( $where = null ) {
	$p   = get_option( 'ridgeroad_presence', array() );
	$p   = is_array( $p ) ? $p : array();
	$now = time();
	foreach ( $p as $k => $v ) {
		if ( $now - (int) $v[0] > 150 ) {
			unset( $p[ $k ] );
		}
	}
	if ( $where ) {
		$p[ ridgeroad_ip_hash() ] = array( $now, $where );
		update_option( 'ridgeroad_presence', $p, false );
	}
	return $p;
}

add_action( 'wp_ajax_rr_stat', 'ridgeroad_stat_ajax' );
add_action( 'wp_ajax_nopriv_rr_stat', 'ridgeroad_stat_ajax' );
function ridgeroad_stat_ajax() {
	nocache_headers();
	$ev = isset( $_POST['ev'] ) ? sanitize_key( wp_unslash( $_POST['ev'] ) ) : '';
	$v  = isset( $_POST['v'] ) ? sanitize_key( wp_unslash( $_POST['v'] ) ) : '';
	if ( 'ping' === $ev ) {
		ridgeroad_presence( 'kids' === $v ? 'kids' : 'site' );
		wp_send_json_success();
	}
	if ( ! in_array( $ev, array( 'visit', 'kids', 'lab_play', 'lab_save', 'elf', 'trivia', 'coloring', 'app_install', 'app_open' ), true ) ) {
		wp_send_json_error( null, 400 );
	}
	// a little flood protection: one of each event per visitor every 3 seconds
	$k = 'rr_st_' . md5( ridgeroad_ip_hash() . $ev );
	if ( get_transient( $k ) ) {
		wp_send_json_success();
	}
	set_transient( $k, 1, 3 );
	if ( 'trivia' === $ev ) {
		ridgeroad_stat_add( 'trivia', 1, max( 0, min( 5, (int) $v ) ) );
	} else {
		ridgeroad_stat_add( $ev );
	}
	wp_send_json_success();
}

/* -------------------------------------------------------------------------
 * Switches that live outside the Customizer
 * ---------------------------------------------------------------------- */
/** "Cancel tonight's show" from Show Control: lasts until 6 AM tomorrow. */
function ridgeroad_cancel_tonight_on() {
	return (int) get_option( 'ridgeroad_cancel_until' ) > time();
}
function ridgeroad_names_paused() {
	return (bool) get_option( 'ridgeroad_names_paused' );
}

/** Can this person run show nights? Site admins, and "Show helper" accounts (Marquee). */
function ridgeroad_ctl_can() {
	return current_user_can( 'manage_options' ) || current_user_can( 'marquee_manage' );
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_menu_page( __( 'Show Control', 'ridgeroad' ), __( 'Show Control', 'ridgeroad' ), 'manage_options', 'rr-control', 'ridgeroad_control_page', 'dashicons-performance', 3 );
} );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_rr-control' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'rr-control', RR_FEAT_URL . 'assets/css/control.css', array(), RIDGEROAD_VERSION );
	wp_enqueue_script( 'rr-control', RR_FEAT_URL . 'assets/js/control.js', array(), RIDGEROAD_VERSION, true );
	wp_localize_script( 'rr-control', 'RRCtl', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'rr_ctl' ),
	) );
} );

function ridgeroad_control_page() {
	$cust = admin_url( 'customize.php?autofocus[section]=rr_kids' );
	?>
	<div class="wrap rrc" data-rrc>
		<div class="rrc__head">
			<h1><?php esc_html_e( 'Show Control', 'ridgeroad' ); ?></h1>
			<p><?php esc_html_e( 'Everything you need during a show, on one page. Updates every 15 seconds.', 'ridgeroad' ); ?></p>
			<span class="rrc__saved" data-rrc-saved aria-live="polite"></span>
		</div>

		<section class="rrc__status" aria-label="<?php esc_attr_e( 'Right now', 'ridgeroad' ); ?>">
			<div class="rrc__st" data-rrc-st="show"><small><?php esc_html_e( 'Tonight’s show', 'ridgeroad' ); ?></small><b data-rrc="show"></b><span data-rrc="show-sub"></span></div>
			<div class="rrc__st" data-rrc-st="fpp"><small>FPP-Master</small><b data-rrc="fpp"></b><span data-rrc="fpp-sub"></span></div>
			<div class="rrc__st" data-rrc-st="names"><small><?php esc_html_e( 'Name in Lights', 'ridgeroad' ); ?></small><b data-rrc="names"></b><span data-rrc="names-sub"></span></div>
			<div class="rrc__st"><small><?php esc_html_e( 'On the website now', 'ridgeroad' ); ?></small><b data-rrc="people"></b><span data-rrc="people-sub"></span></div>
		</section>

		<div class="rrc__grid">
			<section class="rrc__card">
				<header><h2><?php esc_html_e( 'Switches', 'ridgeroad' ); ?></h2></header>
				<div class="rrc__body">
					<?php
					$sw = array(
						'cancel'      => array( __( 'Cancel tonight’s show', 'ridgeroad' ), __( 'Shows the canceled banner on the website until 6 AM', 'ridgeroad' ), 'danger' ),
						'names_on'    => array( __( 'Name in Lights', 'ridgeroad' ), ( function_exists( 'marquee_get' ) ? __( 'Kids can send names to your show’s screens', 'ridgeroad' ) : __( 'Kids can send names to the garage door and TV', 'ridgeroad' ) ), '' ),
						'paused'      => array( __( 'Pause the name queue', 'ridgeroad' ), __( 'Names still come in, but wait until you turn this off', 'ridgeroad' ), '' ),
						'names_always' => array( __( 'Accept names any time', 'ridgeroad' ), __( 'For testing outside show hours', 'ridgeroad' ), '' ),
						'contest_on'  => array( __( 'Light show contest', 'ridgeroad' ), __( 'Entries, Show Wall and hearts', 'ridgeroad' ), '' ),
						'songs_show'  => array( __( 'Favorite song voting', 'ridgeroad' ), __( 'On the Songs & Videos page', 'ridgeroad' ), '' ),
						'kids_show'   => array( __( 'Kids’ Corner', 'ridgeroad' ), __( 'The whole kids’ page', 'ridgeroad' ), '' ),
					);
					foreach ( $sw as $k => $s ) {
						printf(
							'<div class="rrc__sw"><div><b>%s</b><small>%s</small></div><button type="button" class="rrc__tog %s" role="switch" aria-checked="false" data-rrc-tog="%s" aria-label="%s"></button></div>',
							esc_html( $s[0] ), esc_html( $s[1] ), esc_attr( $s[2] ), esc_attr( $k ), esc_attr( $s[0] )
						);
						if ( 'cancel' === $k ) :
							?>
							<div class="rrc__confirm" data-rrc-cancel-confirm hidden>
								<p><b><?php esc_html_e( 'Cancel tonight’s show?', 'ridgeroad' ); ?></b> <?php esc_html_e( 'The website will show the canceled banner until 6 AM.', 'ridgeroad' ); ?></p>
								<label data-rrc-cancel-alert-row><input type="checkbox" data-rrc-cancel-alert checked> <span data-rrc="cancel-alert-label"></span></label>
								<p class="rrc__btns"><button type="button" class="rrc__btn del-pri" data-rrc-cancel-yes><?php esc_html_e( 'Cancel the show', 'ridgeroad' ); ?></button> <button type="button" class="rrc__btn" data-rrc-cancel-no><?php esc_html_e( 'Keep it on', 'ridgeroad' ); ?></button></p>
							</div>
							<?php
						endif;
					}
					?>
					<div class="rrc__sw"><div><b><?php esc_html_e( 'Falling snow', 'ridgeroad' ); ?></b><small><?php esc_html_e( 'On every page', 'ridgeroad' ); ?></small></div>
						<select data-rrc-set="snow_amount" aria-label="<?php esc_attr_e( 'Falling snow', 'ridgeroad' ); ?>"><option value="off"><?php esc_html_e( 'Off', 'ridgeroad' ); ?></option><option value="light"><?php esc_html_e( 'Light', 'ridgeroad' ); ?></option><option value="medium"><?php esc_html_e( 'Medium', 'ridgeroad' ); ?></option><option value="heavy"><?php esc_html_e( 'Heavy', 'ridgeroad' ); ?></option></select></div>
					<div class="rrc__sw"><div><b><?php esc_html_e( 'Snowflake size', 'ridgeroad' ); ?></b><small><?php esc_html_e( 'How big the falling flakes are', 'ridgeroad' ); ?></small></div>
						<select data-rrc-set="snow_size" aria-label="<?php esc_attr_e( 'Snowflake size', 'ridgeroad' ); ?>"><option value="small"><?php esc_html_e( 'Small', 'ridgeroad' ); ?></option><option value="medium"><?php esc_html_e( 'Medium', 'ridgeroad' ); ?></option><option value="large"><?php esc_html_e( 'Large', 'ridgeroad' ); ?></option></select></div>
					<div class="rrc__sw"><div><b><?php esc_html_e( 'Name color on the screens', 'ridgeroad' ); ?></b><small><?php esc_html_e( 'White works best on the marquee', 'ridgeroad' ); ?></small></div>
						<input type="color" data-rrc-set="names_color" aria-label="<?php esc_attr_e( 'Name color on the screens', 'ridgeroad' ); ?>"></div>
				</div>
			</section>

			<section class="rrc__card">
				<header><h2><?php esc_html_e( 'Name queue', 'ridgeroad' ); ?></h2><span><?php esc_html_e( 'Shown tonight:', 'ridgeroad' ); ?> <b data-rrc="shown-n">0</b></span></header>
				<div class="rrc__body">
					<p class="rrc__sub"><?php esc_html_e( 'Waiting for the next break', 'ridgeroad' ); ?></p>
					<ul class="rrc__list" data-rrc-list="waiting"></ul>
					<p class="rrc__sub"><?php esc_html_e( 'Just shown', 'ridgeroad' ); ?></p>
					<ul class="rrc__list" data-rrc-list="shown"></ul>
					<p class="rrc__fine"><?php esc_html_e( '“Block word” removes the name and adds it to your block list, so it can’t be sent again.', 'ridgeroad' ); ?></p>
				</div>
			</section>
		</div>

		<section class="rrc__card" data-rrc-app>
			<header><h2><?php esc_html_e( 'App & Alerts', 'ridgeroad' ); ?></h2><span data-rrc="app-sub"></span></header>
			<div class="rrc__body rrc__grid">
				<div>
					<div class="rrc__tiles rrc__tiles--3" data-rrc-app-tiles></div>
					<div class="rrc__sw"><div><b><?php esc_html_e( '“The lights are on!” alert', 'ridgeroad' ); ?></b><small data-rrc="auto-sub"></small></div><button type="button" class="rrc__tog" role="switch" aria-checked="false" data-rrc-tog="app_auto_on" aria-label="<?php esc_attr_e( 'The lights are on alert', 'ridgeroad' ); ?>"></button></div>
					<div class="rrc__sw"><div><b><?php esc_html_e( '“Your name is up next” buzz', 'ridgeroad' ); ?></b><small><?php esc_html_e( 'Kids can ask for a buzz when they send a name', 'ridgeroad' ); ?></small></div><button type="button" class="rrc__tog" role="switch" aria-checked="false" data-rrc-tog="app_buzz_on" aria-label="<?php esc_attr_e( 'Your name is up next buzz', 'ridgeroad' ); ?>"></button></div>
					<p class="rrc__sub"><?php esc_html_e( 'Recent alerts', 'ridgeroad' ); ?></p>
					<ul class="rrc__list" data-rrc-list="alerts"></ul>
				</div>
				<div class="rrc__send">
					<p class="rrc__sub"><label for="rrc-msg"><?php esc_html_e( 'Send an alert', 'ridgeroad' ); ?></label></p>
					<textarea id="rrc-msg" rows="3" maxlength="180" data-rrc-msg placeholder="<?php esc_attr_e( 'Santa is visiting Saturday from 6 to 8 PM!', 'ridgeroad' ); ?>"></textarea>
					<div class="rrc__sendbar"><small data-rrc="app-to"></small><span class="rrc__count" data-rrc="msg-count">0 / 180</span></div>
					<p class="rrc__btns" data-rrc-send-row><button type="button" class="rrc__btn pri" data-rrc-send><?php esc_html_e( 'Send alert', 'ridgeroad' ); ?></button></p>
					<div class="rrc__confirm" data-rrc-send-confirm hidden>
						<p><b data-rrc="send-q"></b></p>
						<p class="rrc__btns"><button type="button" class="rrc__btn pri" data-rrc-send-yes><?php esc_html_e( 'Yes, send it', 'ridgeroad' ); ?></button> <button type="button" class="rrc__btn" data-rrc-send-no><?php esc_html_e( 'Not yet', 'ridgeroad' ); ?></button></p>
					</div>
					<p class="rrc__fine"><?php esc_html_e( 'The alert’s title is your site name. Keep it short: phones show about two lines.', 'ridgeroad' ); ?></p>
				</div>
			</div>
		</section>

		<section class="rrc__card">
			<header><h2><?php esc_html_e( 'Light show contest', 'ridgeroad' ); ?></h2><span data-rrc="contest-sub"></span></header>
			<div class="rrc__body rrc__grid">
				<div>
					<p class="rrc__sub"><?php esc_html_e( 'Waiting for you to check', 'ridgeroad' ); ?></p>
					<ul class="rrc__list" data-rrc-list="pending"></ul>
				</div>
				<div>
					<p class="rrc__sub"><?php esc_html_e( 'Most hearts (People’s Choice)', 'ridgeroad' ); ?></p>
					<ol class="rrc__lead" data-rrc-list="top"></ol>
					<p class="rrc__sub"><?php esc_html_e( 'Prizes and dates', 'ridgeroad' ); ?></p>
					<p class="rrc__fine"><span data-rrc="prizes"></span> · <a href="<?php echo esc_url( $cust ); ?>"><?php esc_html_e( 'Change', 'ridgeroad' ); ?></a></p>
				</div>
			</div>
		</section>

		<section class="rrc__card">
			<header><h2><?php esc_html_e( 'Stats', 'ridgeroad' ); ?></h2>
				<div class="rrc__seg" role="group" aria-label="<?php esc_attr_e( 'Time range', 'ridgeroad' ); ?>"><button type="button" aria-pressed="true" data-rrc-range="tonight"><?php esc_html_e( 'Tonight', 'ridgeroad' ); ?></button><button type="button" aria-pressed="false" data-rrc-range="season"><?php esc_html_e( 'This season', 'ridgeroad' ); ?></button></div>
			</header>
			<div class="rrc__tiles" data-rrc-tiles></div>
			<div class="rrc__chart">
				<h3><?php esc_html_e( 'Names sent each night', 'ridgeroad' ); ?></h3>
				<p><?php esc_html_e( 'Last 14 show nights. Point at a bar for the exact number.', 'ridgeroad' ); ?></p>
				<div class="rrc__plot" data-rrc-plot><div class="rrc__tip" data-rrc-tip hidden></div></div>
			</div>
		</section>
		<p class="rrc__fine"><?php esc_html_e( 'Counts are totals only. The site never records who did what, and nothing here is shown to visitors.', 'ridgeroad' ); ?></p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Data for the page (polled every 15 s)
 * ---------------------------------------------------------------------- */
function ridgeroad_control_state() {
	$s      = ridgeroad_status();
	$now    = time();
	$labels = array( 'running' => __( 'Running', 'ridgeroad' ), 'upcoming' => __( 'Not started yet', 'ridgeroad' ), 'ended' => __( 'Ended for tonight', 'ridgeroad' ), 'offseason' => __( 'Off season', 'ridgeroad' ), 'canceled' => __( 'Canceled', 'ridgeroad' ) );
	$sub    = $s['start'] . ' – ' . $s['end'];
	if ( 'running' === $s['state'] ) {
		$sub .= ' · ' . sprintf( __( 'ends in %s', 'ridgeroad' ), human_time_diff( $now, (int) $s['end_ts'] ) );
	} elseif ( 'upcoming' === $s['state'] ) {
		$sub .= ' · ' . sprintf( __( 'starts in %s', 'ridgeroad' ), human_time_diff( $now, (int) $s['start_ts'] ) );
	} elseif ( 'canceled' === $s['state'] ) {
		$sub = __( 'The website shows the canceled banner', 'ridgeroad' );
	} elseif ( $s['note'] ) {
		$sub = $s['note'];
	}

	$fpp  = get_option( 'ridgeroad_fpp', array() );
	$seen = (int) get_option( 'ridgeroad_names_seen' );

	$waiting = array();
	foreach ( ridgeroad_name_queue( 30 ) as $p ) {
		$waiting[] = array( 'id' => $p->ID, 'text' => get_post_meta( $p->ID, '_rr_text', true ), 'time' => get_the_date( 'g:i A', $p ) );
	}
	$shown = array();
	$q     = get_posts( array(
		'post_type'   => 'rr_shoutout',
		'post_status' => 'publish',
		'numberposts' => 5,
		'meta_key'    => '_rr_shown', // phpcs:ignore
		'orderby'     => 'meta_value_num',
		'order'       => 'DESC',
		'meta_query'  => array( array( 'key' => '_rr_shown', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ), // phpcs:ignore
	) );
	foreach ( $q as $p ) {
		$shown[] = array( 'text' => get_post_meta( $p->ID, '_rr_text', true ), 'time' => wp_date( 'g:i A', (int) get_post_meta( $p->ID, '_rr_shown', true ) ) );
	}

	$pending = array();
	foreach ( get_posts( array( 'post_type' => 'rr_lightshow', 'post_status' => 'pending', 'numberposts' => 20 ) ) as $p ) {
		$pending[] = array( 'id' => $p->ID, 'name' => $p->post_title, 'age' => (int) get_post_meta( $p->ID, '_rr_age', true ), 'title' => get_post_meta( $p->ID, '_rr_showname', true ), 'thumb' => get_the_post_thumbnail_url( $p, 'medium' ), 'watch' => ridgeroad_show_watch_url( $p->ID ) );
	}
	$top = array();
	foreach ( get_posts( array( 'post_type' => 'rr_lightshow', 'post_status' => 'publish', 'numberposts' => 3, 'meta_key' => '_rr_votes', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ) as $p ) { // phpcs:ignore
		$top[] = array( 'title' => get_post_meta( $p->ID, '_rr_showname', true ), 'name' => $p->post_title, 'age' => (int) get_post_meta( $p->ID, '_rr_age', true ), 'votes' => (int) get_post_meta( $p->ID, '_rr_votes', true ) );
	}
	$counts = wp_count_posts( 'rr_lightshow' );

	$gb = array();
	if ( function_exists( 'ridgeroad_gb_wp' ) && ridgeroad_gb_wp() ) {
		foreach ( get_comments( array( 'post_id' => (int) get_option( 'ridgeroad_gb_page' ), 'status' => 'hold', 'type' => 'comment', 'number' => 30, 'orderby' => 'comment_date_gmt', 'order' => 'ASC' ) ) as $c ) {
			$gb[] = array( 'id' => (int) $c->comment_ID, 'name' => $c->comment_author, 'text' => wp_strip_all_tags( $c->comment_content ), 'when' => wp_date( 'M j, g:i A', strtotime( $c->comment_date_gmt . ' UTC' ), ridgeroad_tz() ) );
		}
	}

	$pres = ridgeroad_presence();
	$kids = 0;
	foreach ( $pres as $v ) {
		if ( 'kids' === $v[1] ) {
			$kids++;
		}
	}

	$all   = get_option( 'ridgeroad_stats', array() );
	$all   = is_array( $all ) ? $all : array();
	$today = ridgeroad_stat_day();
	$tn    = isset( $all[ $today ] ) ? $all[ $today ] : array();
	$sea   = array();
	$start = ridgeroad_mod( 'season_start' );
	foreach ( $all as $d => $row ) {
		if ( $start && $d < $start ) {
			continue;
		}
		foreach ( $row as $k => $v ) {
			$sea[ $k ] = ( isset( $sea[ $k ] ) ? $sea[ $k ] : 0 ) + (int) $v;
		}
	}
	ksort( $all );
	$nights = array();
	foreach ( array_slice( $all, -14, null, true ) as $d => $row ) {
		$nights[] = array( wp_date( 'M j', strtotime( $d . ' 12:00' ), ridgeroad_tz() ), isset( $row['name_sent'] ) ? (int) $row['name_sent'] : 0 );
	}
	$top_song = '';
	if ( function_exists( 'ridgeroad_votes_public' ) ) {
		$pub = ridgeroad_votes_public();
		if ( is_array( $pub ) && ! empty( $pub['season'][0]['title'] ) ) {
			$top_song = $pub['season'][0]['title'];
		}
	}

	return array(
		'show'     => array( 'state' => $s['state'], 'label' => isset( $labels[ $s['state'] ] ) ? $labels[ $s['state'] ] : $s['state'], 'sub' => $sub ),
		'fpp'      => array(
			'seen'     => $seen ? $now - $seen : null,
			'status'   => isset( $fpp['status'] ) ? $fpp['status'] : '',
			'playlist' => isset( $fpp['playlist'] ) ? $fpp['playlist'] : '',
			'sequence' => isset( $fpp['sequence'] ) ? $fpp['sequence'] : '',
		),
		'names'    => array( 'open' => ridgeroad_names_open(), 'waiting' => $waiting, 'shown' => $shown, 'shown_tonight' => isset( $tn['name_shown'] ) ? (int) $tn['name_shown'] : 0 ),
		'switches' => array(
			'cancel'       => ridgeroad_cancel_tonight_on(),
			'names_on'     => (bool) ridgeroad_mod( 'names_on' ),
			'paused'       => ridgeroad_names_paused(),
			'names_always' => (bool) ridgeroad_mod( 'names_always' ),
			'names_geo'    => (bool) ridgeroad_mod( 'names_geo' ),
			'names_geo_mi' => (string) ridgeroad_mod( 'names_geo_miles' ),
			'names_geo_km' => '' !== (string) ridgeroad_mod( 'names_geo_unit' ) ? 'km' === ridgeroad_mod( 'names_geo_unit' ) : ( function_exists( 'ridgeroad_metric' ) && ridgeroad_metric() ),
			'contest_on'   => (bool) ridgeroad_mod( 'contest_on' ),
			'songs_show'   => (bool) ridgeroad_mod( 'songs_show' ),
			'kids_show'    => (bool) ridgeroad_mod( 'kids_show' ),
			'snow_amount'  => ridgeroad_mod( 'snow_amount' ),
			'snow_size'    => ridgeroad_mod( 'snow_size' ),
			'names_color'  => ridgeroad_mod( 'names_color' ),
			'app_auto_on'  => (bool) ridgeroad_mod( 'app_auto_on' ),
			'app_buzz_on'  => (bool) ridgeroad_mod( 'app_buzz_on' ),
		),
		'contest'  => array(
			'pending' => $pending,
			'top'     => $top,
			'total'   => (int) $counts->publish + (int) $counts->pending,
			'closes'  => ridgeroad_contest_date( 'contest_deadline' ),
			'prizes'  => sprintf( __( 'Judges’ Pick: %1$s · People’s Choice: %2$s · Pick-up by %3$s', 'ridgeroad' ), ridgeroad_mod( 'contest_prize1' ), ridgeroad_mod( 'contest_prize2' ), ridgeroad_contest_date( 'contest_pickup' ) ),
		),
		'guestbook' => $gb,
		'people'   => array( 'now' => count( $pres ), 'kids' => $kids ),
		'stats'    => array( 'tonight' => $tn, 'season' => $sea, 'nights' => $nights, 'top_song' => $top_song ),
		'app'      => array(
			'ready'    => function_exists( 'ridgeroad_push_ready' ) && ridgeroad_push_ready() && (bool) ridgeroad_vapid(),
			'installs' => isset( $sea['app_install'] ) ? (int) $sea['app_install'] : 0,
			'opens'    => isset( $tn['app_open'] ) ? (int) $tn['app_open'] : 0,
			'subs'     => ridgeroad_push_count(),
			'start'    => ridgeroad_push_count( 'start' ),
			'news'     => ridgeroad_push_count( 'news' ),
			'buzz'     => isset( $tn['buzz_sent'] ) ? (int) $tn['buzz_sent'] : 0,
			'sent_on'  => get_option( 'ridgeroad_push_on_night' ) === $today,
			'log'      => array_map( function ( $l ) {
				return array( 'title' => $l['title'], 'body' => $l['body'], 'kind' => $l['kind'], 'sent' => (int) $l['sent'], 'failed' => (int) $l['failed'], 'when' => wp_date( 'M j, g:i A', (int) $l['t'], ridgeroad_tz() ) );
			}, array_slice( (array) get_option( 'ridgeroad_push_log', array() ), 0, 6 ) ),
		),
	);
}

add_action( 'wp_ajax_rr_ctl_state', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	wp_send_json_success( ridgeroad_control_state() );
} );

add_action( 'wp_ajax_rr_ctl_set', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	$k = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$v = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
	$on = in_array( $v, array( '1', 'true' ), true );
	switch ( $k ) {
		case 'cancel':
			if ( $on ) {
				$tz  = ridgeroad_tz();
				$six = new DateTimeImmutable( 'now', $tz );
				$six = ( (int) $six->format( 'G' ) < 6 ? $six : $six->modify( '+1 day' ) )->setTime( 6, 0 );
				update_option( 'ridgeroad_cancel_until', $six->getTimestamp(), false );
				if ( ! empty( $_POST['alert'] ) && function_exists( 'ridgeroad_app_cancel_alert' ) ) {
					$sent = ridgeroad_app_cancel_alert();
				}
			} else {
				delete_option( 'ridgeroad_cancel_until' );
			}
			break;
		case 'paused':
			update_option( 'ridgeroad_names_paused', $on ? 1 : 0, false );
			break;
		case 'names_on':
		case 'names_always':
		case 'names_geo':
		case 'contest_on':
		case 'songs_show':
		case 'kids_show':
		case 'app_auto_on':
		case 'app_buzz_on':
			set_theme_mod( $k, $on ? 1 : 0 );
			break;
		case 'snow_amount':
			if ( in_array( $v, array( 'off', 'light', 'medium', 'heavy' ), true ) ) {
				set_theme_mod( 'snow_amount', $v );
			}
			break;
		case 'snow_size':
			if ( in_array( $v, array( 'small', 'medium', 'large' ), true ) ) {
				set_theme_mod( 'snow_size', $v );
			}
			break;
		case 'names_color':
			$c = sanitize_hex_color( $v );
			if ( $c ) {
				set_theme_mod( 'names_color', strtoupper( $c ) );
			}
			break;
		default:
			wp_send_json_error( null, 400 );
	}
	if ( function_exists( 'ridgeroad_flush_cache' ) ) {
		ridgeroad_flush_cache();
	}
	$st = ridgeroad_control_state();
	if ( isset( $sent ) ) {
		$st['pushed'] = $sent;
	}
	wp_send_json_success( $st );
} );

add_action( 'wp_ajax_rr_ctl_name', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( ! $id || 'rr_shoutout' !== get_post_type( $id ) ) {
		wp_send_json_error( null, 404 );
	}
	if ( ! empty( $_POST['block'] ) ) {
		$name  = strtolower( get_the_title( $id ) );
		$block = array_filter( array_map( 'trim', explode( ',', (string) ridgeroad_mod( 'names_block' ) ) ) );
		if ( $name && ! in_array( $name, array_map( 'strtolower', $block ), true ) ) {
			$block[] = $name;
			set_theme_mod( 'names_block', implode( ', ', $block ) );
		}
	}
	wp_trash_post( $id );
	wp_send_json_success( ridgeroad_control_state() );
} );

add_action( 'wp_ajax_rr_ctl_entry', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( ! $id || 'rr_lightshow' !== get_post_type( $id ) || ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	if ( ! empty( $_POST['approve'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	} else {
		wp_trash_post( $id );
	}
	wp_send_json_success( ridgeroad_control_state() );
} );

/* Send an alert from Show Control */
add_action( 'wp_ajax_rr_ctl_push', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	$msg = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$msg = trim( preg_replace( '/\s+/', ' ', $msg ) );
	if ( '' === $msg || mb_strlen( $msg ) > 180 ) {
		wp_send_json_error( array( 'message' => __( 'Type a message of up to 180 characters.', 'ridgeroad' ) ), 400 );
	}
	$res = ridgeroad_push_topic( 'news', array(
		'title' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'body'  => $msg,
		'tag'   => 'news-' . time(),
		'ttl'   => 12 * HOUR_IN_SECONDS,
	), 'you' );
	$st           = ridgeroad_control_state();
	$st['pushed'] = $res;
	wp_send_json_success( $st );
} );

/* Approve or delete a guestbook message (Show Manager) */
add_action( 'wp_ajax_rr_ctl_gb', function () {
	check_ajax_referer( 'rr_ctl', 'nonce' );
	if ( ! ridgeroad_ctl_can() ) {
		wp_send_json_error( null, 403 );
	}
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$c  = $id ? get_comment( $id ) : null;
	if ( ! $c || (int) $c->comment_post_ID !== (int) get_option( 'ridgeroad_gb_page' ) ) {
		wp_send_json_error( null, 404 );
	}
	wp_set_comment_status( $id, ! empty( $_POST['approve'] ) ? 'approve' : 'trash' );
	if ( function_exists( 'ridgeroad_flush_cache' ) ) {
		ridgeroad_flush_cache();
	}
	wp_send_json_success( ridgeroad_control_state() );
} );
