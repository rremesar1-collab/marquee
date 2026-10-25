<?php
/**
 * Live data: show schedule, Now Playing (via the plugin) and weather.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Schedule (sunset → close). Remote Falcon's playingNow is NOT used for the
 * running badge, as the plugin notes it can't reliably tell running/stopped.
 * ---------------------------------------------------------------------- */
/** The show's time zone: Marquee's setting, else Central (Lights On Ridge Road). */
function ridgeroad_tz() {
	static $tz = null;
	if ( null === $tz ) {
		$name = function_exists( 'marquee_get' ) ? marquee_get( 'tz' ) : '';
		try {
			$tz = new DateTimeZone( $name && false === strpos( $name, 'UTC' ) ? $name : 'America/Chicago' );
		} catch ( \Exception $e ) {
			$tz = new DateTimeZone( 'America/Chicago' );
		}
	}
	return $tz;
}

function ridgeroad_metric() {
	return function_exists( 'marquee_get' ) && 'metric' === marquee_get( 'units' );
}
function ridgeroad_units() {
	return ridgeroad_metric() ? array( 't' => '°C', 'w' => 'km/h' ) : array( 't' => '°F', 'w' => 'mph' );
}
/** Remote Falcon show data: Marquee's own connection when set up, else the Lights on Ridge Road Live plugin. */
function ridgeroad_rf_data() {
	if ( function_exists( 'marquee_rf_configured' ) && marquee_rf_configured() ) {
		return marquee_rf_show_data();
	}
	return function_exists( 'lor_show_data' ) ? lor_show_data() : null;
}
function ridgeroad_rf_on() {
	return ( function_exists( 'marquee_rf_configured' ) && marquee_rf_configured() ) || function_exists( 'lor_show_data' );
}

function ridgeroad_plugin_options() {
	return function_exists( 'lor_options' ) ? (array) lor_options() : array();
}

function ridgeroad_is_canceled() {
	$o = ridgeroad_plugin_options();
	return ! empty( $o['canceled'] ) || ( function_exists( 'ridgeroad_cancel_tonight_on' ) && ridgeroad_cancel_tonight_on() );
}

/** Start/end of the show night that begins on the given local date. */
function ridgeroad_night( DateTimeImmutable $day ) {
	$tz   = ridgeroad_tz();
	$weekend = in_array( (int) $day->format( 'w' ), array( 5, 6 ), true ); // Fri, Sat.
	if ( ridgeroad_mod( 'use_sunset' ) ) {
		$noon = $day->setTime( 12, 0 );
		$sun  = date_sun_info( $noon->getTimestamp(), RIDGEROAD_LAT, RIDGEROAD_LON );
		$set  = ( new DateTimeImmutable( '@' . (int) $sun['sunset'] ) )->setTimezone( $tz );
		$set  = $set->modify( ( (int) ridgeroad_mod( 'start_offset' ) ) . ' minutes' );
	} else {
		list( $sh, $sm ) = array_map( 'intval', explode( ':', ridgeroad_mod( $weekend ? 'weekend_start' : 'weeknight_start' ) ) );
		$set = $day->setTime( $sh, $sm );
	}

	$close   = ridgeroad_mod( $weekend ? 'weekend_close' : 'weeknight_close' );
	list( $h, $m ) = array_map( 'intval', explode( ':', $close ) );
	$end = $day->setTime( $h, $m );
	if ( $h < 6 ) { // A close time after midnight belongs to the next calendar day.
		$end = $end->modify( '+1 day' );
	}
	return array( 'start' => $set, 'end' => $end, 'date' => $day->format( 'Y-m-d' ) );
}

function ridgeroad_in_season( $ymd ) {
	$s = ridgeroad_mod( 'season_start' );
	$e = ridgeroad_mod( 'season_end' );
	if ( $s && $ymd < $s ) {
		return false;
	}
	if ( $e && $ymd > $e ) {
		return false;
	}
	return true;
}

function ridgeroad_time( DateTimeImmutable $t ) {
	return wp_date( __( 'g:i A', 'ridgeroad' ), $t->getTimestamp(), ridgeroad_tz() );
}

/**
 * state: running | upcoming | ended | offseason | canceled
 */
function ridgeroad_status() {
	$tz    = ridgeroad_tz();
	$now   = new DateTimeImmutable( 'now', $tz );
	$today = $now->setTime( 0, 0 );

	// A night that started yesterday may still be going (close after midnight).
	$candidates = array( ridgeroad_night( $today->modify( '-1 day' ) ), ridgeroad_night( $today ) );
	$night      = $candidates[1];
	foreach ( $candidates as $n ) {
		if ( $now >= $n['start'] && $now < $n['end'] ) {
			$night = $n;
		}
	}

	$out = array(
		'state' => 'upcoming',
		'badge' => '',
		'start' => ridgeroad_time( $night['start'] ),
		'end'   => ridgeroad_time( $night['end'] ),
		'note'  => '',
	);

	// Countdown targets (Unix time).
	$out['now_ts']   = $now->getTimestamp();
	$out['start_ts'] = $night['start']->getTimestamp();
	$out['end_ts']   = $night['end']->getTimestamp();
	if ( $now >= $night['end'] ) {
		$tom              = ridgeroad_night( $today->modify( '+1 day' ) );
		$out['start_ts']  = $tom['start']->getTimestamp();
	}
	$season = ridgeroad_mod( 'season_start' );
	$out['opening_ts'] = 0;
	if ( $season && $night['date'] < $season ) {
		$open              = ridgeroad_night( new DateTimeImmutable( $season . ' 00:00', $tz ) );
		$out['opening_ts'] = $open['start']->getTimestamp();
	}

	if ( ridgeroad_is_canceled() ) {
		$out['state'] = 'canceled';
		$out['badge'] = __( 'Canceled Tonight', 'ridgeroad' );
		return $out;
	}

	if ( ! ridgeroad_in_season( $night['date'] ) ) {
		$out['state'] = 'offseason';
		$out['badge'] = __( 'Off Season', 'ridgeroad' );
		$s            = ridgeroad_mod( 'season_start' );
		if ( $s && $night['date'] < $s ) {
			/* translators: %s: opening date */
			$out['note'] = sprintf( __( 'Opening night: %s', 'ridgeroad' ), wp_date( __( 'l, F j', 'ridgeroad' ), strtotime( $s . ' 12:00' ), ridgeroad_tz() ) );
		} else {
			$out['note'] = __( 'Thanks for a wonderful season!', 'ridgeroad' );
		}
		return $out;
	}


	if ( $now >= $night['start'] && $now < $night['end'] ) {
		$out['state'] = 'running';
		$out['badge'] = __( 'Show Running', 'ridgeroad' );
		/* translators: %s: end time */
		$out['note'] = sprintf( __( 'Running until %s tonight', 'ridgeroad' ), $out['end'] );
	} elseif ( $now < $night['start'] ) {
		$out['state'] = 'upcoming';
		/* translators: %s: start time */
		$out['badge'] = sprintf( __( 'Starts %s', 'ridgeroad' ), $out['start'] );
		/* translators: %s: start time */
		$out['note'] = ( ridgeroad_mod( 'use_sunset' ) ? sprintf( __( 'Lights on at sunset — about %s tonight', 'ridgeroad' ), $out['start'] ) : sprintf( __( 'Lights on at %s tonight', 'ridgeroad' ), $out['start'] ) );
	} else {
		$next         = ridgeroad_night( $today->modify( '+1 day' ) );
		$out['state'] = 'ended';
		$out['badge'] = __( 'Show Ended', 'ridgeroad' );
		$out['note']  = ridgeroad_in_season( $next['date'] )
			/* translators: %s: start time */
			? ( ridgeroad_mod( 'use_sunset' ) ? sprintf( __( 'See you tomorrow at sunset — about %s', 'ridgeroad' ), ridgeroad_time( $next['start'] ) ) : sprintf( __( 'See you tomorrow at %s', 'ridgeroad' ), ridgeroad_time( $next['start'] ) ) )
			: __( 'That was our last night — thank you!', 'ridgeroad' );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Now Playing — read through the plugin so the token stays server side.
 * ---------------------------------------------------------------------- */
function ridgeroad_now_playing() {
	if ( ! ridgeroad_rf_on() ) {
		return array( 'title' => '', 'next' => '', 'connected' => false );
	}
	$data = ridgeroad_rf_data();
	if ( ! is_array( $data ) ) {
		return array( 'title' => '', 'next' => '', 'connected' => false );
	}
	$names = array();
	if ( ! empty( $data['sequences'] ) && is_array( $data['sequences'] ) ) {
		foreach ( $data['sequences'] as $s ) {
			if ( isset( $s['name'] ) ) {
				$names[ $s['name'] ] = ! empty( $s['displayName'] ) ? $s['displayName'] : $s['name'];
			}
		}
	}
	$pretty = function ( $raw ) use ( $names ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $raw ) {
			return '';
		}
		if ( isset( $names[ $raw ] ) ) {
			return $names[ $raw ];
		}
		return trim( str_replace( '_', ' ', preg_replace( '/\.(fseq|xsq|mp3)$/i', '', $raw ) ) );
	};
	$next = isset( $data['playingNext'] ) ? $data['playingNext'] : ( isset( $data['playingNextFromSchedule'] ) ? $data['playingNextFromSchedule'] : '' );

	// Full playlist (in Remote Falcon's order), hidden sequences left out.
	$list = array();
	if ( ! empty( $data['sequences'] ) && is_array( $data['sequences'] ) ) {
		$seqs = $data['sequences'];
		usort( $seqs, function ( $a, $b ) {
			return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
		} );
		foreach ( $seqs as $q ) {
			if ( ! ridgeroad_rf_seq_shown( $q ) ) {
				continue;
			}
			if ( isset( $q['name'] ) ) {
				$list[] = ! empty( $q['displayName'] ) ? $q['displayName'] : $pretty( $q['name'] );
			}
		}
	}
	$title = $pretty( isset( $data['playingNow'] ) ? $data['playingNow'] : '' );

	// Recently played: remember the last 5 songs as they change.
	$recent = get_option( 'ridgeroad_recent', array() );
	if ( ! is_array( $recent ) ) {
		$recent = array();
	}
	if ( $title && ( empty( $recent ) || $recent[0]['t'] !== $title ) ) {
		array_unshift( $recent, array( 't' => $title, 'at' => time() ) );
		$recent = array_slice( $recent, 0, 6 );
		update_option( 'ridgeroad_recent', $recent, false );
	}
	$played = array();
	foreach ( $recent as $r ) {
		if ( $r['t'] !== $title && time() - $r['at'] < 6 * HOUR_IN_SECONDS ) {
			$played[] = $r['t'];
		}
	}

	return array(
		'title'     => $title,
		'next'      => $pretty( $next ),
		'playlist'  => $list,
		'recent'    => array_slice( $played, 0, 5 ),
		'connected' => true,
	);
}

/**
 * Should a Remote Falcon sequence show on the website? Remote Falcon keeps songs from
 * older playlists and marks them inactive; hidden ones are turned off on purpose.
 */
function ridgeroad_rf_seq_shown( $q ) {
	if ( ! is_array( $q ) || empty( $q['name'] ) ) {
		return false;
	}
	foreach ( array( 'visible', 'active' ) as $k ) {
		if ( isset( $q[ $k ] ) && ( false === $q[ $k ] || 0 === $q[ $k ] || 'false' === $q[ $k ] ) ) {
			return false;
		}
	}
	return true;
}

/** Status banner wording for a status (cancel message comes from the plugin). */
function ridgeroad_banner( $status ) {
	$opts = ridgeroad_plugin_options();
	if ( 'canceled' === $status['state'] ) {
		return array( 'mod' => 'canceled', 'icon' => 'cloud', 'title' => ridgeroad_mod( 'cancel_title' ), 'text' => ! empty( $opts['notice'] ) ? $opts['notice'] : ridgeroad_mod( 'cancel_text' ) );
	}
	if ( 'offseason' === $status['state'] ) {
		return array( 'mod' => 'off', 'icon' => 'star', 'title' => ridgeroad_mod( 'off_title' ), 'text' => trim( ridgeroad_mod( 'off_text' ) . ' ' . $status['note'] ) );
	}
	return array( 'mod' => 'ok', 'icon' => 'cloud', 'title' => ridgeroad_mod( 'ok_title' ), 'text' => ridgeroad_mod( 'ok_text' ) );
}

/** Now Playing: shown whenever Remote Falcon reports a song, whatever the schedule says. */
function ridgeroad_now_playing_for( $status ) {
	return ridgeroad_now_playing();
}

/* -------------------------------------------------------------------------
 * Weather — Open-Meteo (same service + coordinates as the plugin), adding
 * hourly data for tonight's show hours.
 * ---------------------------------------------------------------------- */
function ridgeroad_weather_raw() {
	$key    = 'ridgeroad_wx_v1';
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$url = add_query_arg( array(
		'latitude'         => RIDGEROAD_LAT,
		'longitude'        => RIDGEROAD_LON,
		'current'          => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day,precipitation',
		'hourly'           => 'temperature_2m,precipitation_probability,weather_code,is_day',
		'daily'            => 'weather_code,temperature_2m_max,temperature_2m_min,sunset',
		'temperature_unit' => ridgeroad_metric() ? 'celsius' : 'fahrenheit',
		'wind_speed_unit'  => ridgeroad_metric() ? 'kmh' : 'mph',
		'timezone'         => ridgeroad_tz()->getName(),
		'forecast_days'    => 2,
	), 'https://api.open-meteo.com/v1/forecast' );
	$r = wp_remote_get( $url, array( 'timeout' => 9 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		set_transient( $key, array(), 120 ); // Brief back-off.
		return array();
	}
	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $data ) || empty( $data['current'] ) ) {
		set_transient( $key, array(), 120 );
		return array();
	}
	set_transient( $key, $data, 600 );
	return $data;
}

function ridgeroad_wx_label( $code ) {
	$code = (int) $code;
	if ( function_exists( 'lor_weather_label' ) ) {
		$l = lor_weather_label( $code );
		if ( 'Mostly clear' === $l && 2 === $code ) {
			return 'Partly Cloudy';
		}
		return ucwords( $l );
	}
	$map = array(
		0 => 'Clear Skies', 1 => 'Mostly Clear', 2 => 'Partly Cloudy', 3 => 'Cloudy', 45 => 'Fog', 48 => 'Fog',
	);
	if ( isset( $map[ $code ] ) ) {
		return $map[ $code ];
	}
	if ( $code >= 51 && $code <= 57 ) {
		return 'Drizzle';
	}
	if ( ( $code >= 61 && $code <= 67 ) || ( $code >= 80 && $code <= 82 ) ) {
		return 'Rain';
	}
	if ( ( $code >= 71 && $code <= 77 ) || 85 === $code || 86 === $code ) {
		return 'Snow';
	}
	if ( $code >= 95 ) {
		return 'Thunderstorms';
	}
	return 'Conditions Unavailable';
}

function ridgeroad_weather() {
	$raw = ridgeroad_weather_raw();
	$out = array( 'ok' => false, 'hours' => array() );

	if ( ! empty( $raw['current'] ) ) {
		$c   = $raw['current'];
		$out = array_merge( $out, array(
			'ok'       => true,
			'temp'     => round( $c['temperature_2m'] ),
			'feels'    => round( $c['apparent_temperature'] ),
			'humidity' => round( $c['relative_humidity_2m'] ),
			'wind'     => round( $c['wind_speed_10m'] ),
			'code'     => (int) $c['weather_code'],
			'is_day'   => (int) $c['is_day'],
			'label'    => ridgeroad_wx_label( $c['weather_code'] ),
			'precip'   => null,
		) );
	} elseif ( function_exists( 'lor_weather_data' ) ) {
		$p = lor_weather_data(); // Plugin fallback (no hourly data).
		if ( is_array( $p ) && ! empty( $p['current'] ) ) {
			$c   = $p['current'];
			$h   = (int) wp_date( 'G', null, ridgeroad_tz() );
			$out = array_merge( $out, array(
				'ok'       => true,
				'temp'     => round( $c['temperature_2m'] ),
				'feels'    => null,
				'humidity' => round( $c['relative_humidity_2m'] ),
				'wind'     => round( $c['wind_speed_10m'] ),
				'code'     => (int) $c['weather_code'],
				'is_day'   => ( $h >= 7 && $h < 17 ) ? 1 : 0,
				'label'    => ridgeroad_wx_label( $c['weather_code'] ),
				'precip'   => null,
			) );
		}
	}

	if ( empty( $raw['hourly']['time'] ) ) {
		return $out;
	}

	// Pick 5 hours: ending at tonight's close, or from now if later than that.
	$tz      = ridgeroad_tz();
	$now     = new DateTimeImmutable( 'now', $tz );
	$night   = ridgeroad_night( $now->setTime( 0, 0 ) );
	$closeH  = (int) $night['end']->format( 'G' );
	$closeH  = $closeH < 6 ? 23 : $closeH;
	$startTs = $now->setTime( max( (int) $now->format( 'G' ), $closeH - 4 ), 0 )->getTimestamp();
	$curHour = $now->setTime( (int) $now->format( 'G' ), 0 )->format( 'Y-m-d\TH:i' );

	$h = $raw['hourly'];
	foreach ( $h['time'] as $i => $t ) {
		if ( $t === $curHour && isset( $h['precipitation_probability'][ $i ] ) ) {
			$out['precip'] = (int) $h['precipitation_probability'][ $i ];
		}
		$ts = ( new DateTimeImmutable( $t, ridgeroad_tz() ) )->getTimestamp();
		if ( $ts < $startTs || count( $out['hours'] ) >= 5 ) {
			continue;
		}
		$out['hours'][] = array(
			'label'  => wp_date( __( 'g A', 'ridgeroad' ), $ts, ridgeroad_tz() ),
			'temp'   => round( $h['temperature_2m'][ $i ] ),
			'code'   => (int) $h['weather_code'][ $i ],
			'is_day' => (int) $h['is_day'][ $i ],
			'pop'    => isset( $h['precipitation_probability'][ $i ] ) ? (int) $h['precipitation_probability'][ $i ] : null,
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * REST endpoint for the live refresh: /wp-json/ridgeroad/v1/live
 * ---------------------------------------------------------------------- */
function ridgeroad_live_payload() {
	$status = ridgeroad_status();
	$np     = ridgeroad_now_playing_for( $status );
	$wx     = ridgeroad_weather();
	return array(
		'status'     => $status,
		'banner'     => ridgeroad_banner( $status ),
		'nowPlaying' => $np,
		'weather'    => $wx['ok'] ? array(
			'temp'     => $wx['temp'],
			'label'    => $wx['label'],
			'feels'    => $wx['feels'],
			'humidity' => $wx['humidity'],
			'wind'     => $wx['wind'],
			'precip'   => $wx['precip'],
			'code'     => $wx['code'],
		) : null,
		'votes'      => function_exists( 'ridgeroad_votes_public' ) ? ridgeroad_votes_public() : null,
		'advisory'   => function_exists( 'ridgeroad_advisory_public' ) ? ridgeroad_advisory_public() : null,
		'time'       => time(),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'ridgeroad/v1', '/live', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$res = new WP_REST_Response( ridgeroad_live_payload() );
			$res->header( 'Cache-Control', 'no-store, max-age=0' );
			return $res;
		},
	) );
} );

/* Same data through admin-ajax.php, which page caches (SiteGround, etc.) never cache. */
function ridgeroad_ajax_live() {
	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'X-Cache-Enabled: False' );
	wp_send_json( ridgeroad_live_payload() );
}
add_action( 'wp_ajax_ridgeroad_live', 'ridgeroad_ajax_live' );
add_action( 'wp_ajax_nopriv_ridgeroad_live', 'ridgeroad_ajax_live' );

/* -------------------------------------------------------------------------
 * Guestbook messages image, served from this site (RF Page Builder blocks
 * its images from loading on other websites). Cached for 60 seconds.
 * ---------------------------------------------------------------------- */
function ridgeroad_gb_site_key() {
	$k = trim( sanitize_text_field( ridgeroad_mod( 'gb_site_key' ) ) );
	if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $k ) ) {
		$k = ridgeroad_defaults()['gb_site_key'];
	}
	return $k;
}
function ridgeroad_gb_remote_url() {
	return 'https://rfpagebuilder.com/api/guestbook/img/' . rawurlencode( ridgeroad_gb_site_key() ) . '?' . http_build_query( array(
		'bg'     => '#0b1a3a',
		'fg'     => '#fff6e6',
		'accent' => '#f7c95a',
		'font'   => 'Georgia',
		'count'  => max( 1, min( 20, (int) ridgeroad_mod( 'gb_count' ) ) ),
		'order'  => 'newest',
		'width'  => 900,
	), '', '&', PHP_QUERY_RFC3986 );
}
function ridgeroad_ajax_gb_img() {
	$url = ridgeroad_gb_remote_url();
	$key = 'ridgeroad_gbimg_' . md5( $url );
	$img = get_transient( $key );
	if ( false === $img ) {
		$r = wp_remote_get( $url, array(
			'timeout'    => 10,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
			'headers'    => array( 'Accept' => 'image/avif,image/webp,image/png,image/*;q=0.8,*/*;q=0.5' ),
		) );
		$type = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'content-type' );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) || 0 !== strpos( $type, 'image/' ) ) {
			if ( current_user_can( 'manage_options' ) && isset( $_GET['debug'] ) ) { // Admin-only diagnostics.
				wp_die( esc_html( is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $r ) . ' / ' . $type ) );
			}
			status_header( 502 );
			nocache_headers();
			exit;
		}
		$img = array( 'type' => $type, 'data' => base64_encode( wp_remote_retrieve_body( $r ) ) );
		set_transient( $key, $img, 60 );
	}
	status_header( 200 );
	header( 'Content-Type: ' . $img['type'] );
	header( 'Cache-Control: public, max-age=60' );
	header( 'X-Content-Type-Options: nosniff' );
	echo base64_decode( $img['data'] ); // phpcs:ignore -- binary image data.
	exit;
}
add_action( 'wp_ajax_ridgeroad_gb_img', 'ridgeroad_ajax_gb_img' );
add_action( 'wp_ajax_nopriv_ridgeroad_gb_img', 'ridgeroad_ajax_gb_img' );

/**
 * YouTube embed URL from a channel link/ID or a video link. '' when not usable.
 */
function ridgeroad_youtube_embed( $in ) {
	$in = trim( (string) $in );
	if ( '' === $in ) {
		return '';
	}
	if ( preg_match( '~(UC[0-9A-Za-z_-]{22})~', $in, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/live_stream?channel=' . $m[1] . '&autoplay=1&rel=0';
	}
	if ( preg_match( '~(?:youtu\.be/|v=|/live/|/embed/|/shorts/)([0-9A-Za-z_-]{11})~', $in, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0';
	}
	return '';
}

/** Google Maps directions link for the show address ('' when not set). */
function ridgeroad_directions_url() {
	$a = trim( (string) ridgeroad_mod( 'address' ) );
	if ( '' === $a ) {
		return '';
	}
	$dir = 'https://www.google.com/maps/dir/?api=1&destination=';
	if ( preg_match( '#^https?://#i', $a ) ) {
		// A pasted Google Maps link: use its pin coordinates when they are in the link
		// (google.com/maps/place/...!3dLAT!4dLON or .../@LAT,LON), otherwise open the link itself
		// (maps.app.goo.gl short links open the place, with a Directions button).
		if ( preg_match( '#!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)#', $a, $m ) || preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)#', $a, $m ) ) {
			return $dir . $m[1] . ',' . $m[2];
		}
		return $a;
	}
	// Plain coordinates like "39.7817, -89.6501".
	if ( preg_match( '#^(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)$#', $a, $m ) ) {
		return $dir . $m[1] . ',' . $m[2];
	}
	return $dir . rawurlencode( $a );
}

/** Whether the playlist should be visible for a show state. */
function ridgeroad_playlist_visible( $state ) {
	$when = ridgeroad_mod( 'playlist_when' );
	if ( 'always' === $when ) {
		return true;
	}
	if ( 'season' === $when ) {
		return ! in_array( $state, array( 'offseason', 'canceled' ), true );
	}
	return 'running' === $state;
}

/** URL of the Kids' Corner page (created on first use, slug "kids"). */
function ridgeroad_kids_url() {
	$id = (int) get_option( 'ridgeroad_kids_page' );
	if ( ! $id || 'page' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
		$found = get_page_by_path( 'kids' );
		$id    = $found ? (int) $found->ID : (int) wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'Kids’ Corner',
			'post_name'      => 'kids',
			'post_content'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( $id ) {
			update_option( 'ridgeroad_kids_page', $id, true );
		}
	}
	return $id ? get_permalink( $id ) : '';
}
/* Use the Kids' Corner template for that page even if its address is changed. */
add_filter( 'template_include', function ( $t ) {
	$id = (int) get_option( 'ridgeroad_kids_page' );
	if ( $id && is_page( $id ) ) {
		$k = locate_template( 'page-kids.php' );
		return $k ? $k : $t;
	}
	return $t;
} );
add_action( 'after_switch_theme', 'ridgeroad_kids_url' );

/** URL of the Songs & Sequences page (created on first use, slug "songs"). */
function ridgeroad_songs_url() {
	$id = (int) get_option( 'ridgeroad_songs_page' );
	if ( ! $id || 'page' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
		$found = get_page_by_path( 'songs' );
		$id    = $found ? (int) $found->ID : (int) wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'Songs & Videos',
			'post_name'      => 'songs',
			'post_content'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( $id ) {
			update_option( 'ridgeroad_songs_page', $id, true );
		}
	}
	return $id ? get_permalink( $id ) : '';
}
/** Whether the Songs & Sequences page has anything to show. */
function ridgeroad_songs_page_on() {
	return ( ridgeroad_mod( 'seq_show' ) && ridgeroad_sequence_videos() ) || ( ridgeroad_mod( 'songs_show' ) && ridgeroad_song_list() );
}
add_filter( 'template_include', function ( $t ) {
	$id = (int) get_option( 'ridgeroad_songs_page' );
	if ( $id && is_page( $id ) ) {
		$k = locate_template( 'page-songs.php' );
		return $k ? $k : $t;
	}
	return $t;
} );
