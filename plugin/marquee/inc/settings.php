<?php
/**
 * Marquee settings: one option array, plus the show schedule/feature switches that
 * the festive theme keeps as theme settings (so there is only one place for each).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_defaults() {
	return array(
		'show_name'  => get_bloginfo( 'name' ),
		'short_name' => '',
		'address'    => '',
		'city'       => '',
		'region'     => '',
		'zip'        => '',
		'country'    => 'US',
		'lat'        => '',
		'lon'        => '',
		'tz'         => wp_timezone_string(),
		'nws_office' => '',   // National Weather Service office (e.g. HUN), from the address lookup
		'units'      => 'us',   // us = °F / mph, metric = °C / km/h
		'rf_token'   => '',
		'rf_secret'  => '',
		'names_key'  => '',
		'art_house'  => 0,        // the show's own house coloring page (attachment ID)
		'art_pages'  => array(),  // extra coloring pages (attachment IDs)
		'art_hide'   => array(),  // standard coloring pages turned off (slugs)
		'art_removed' => array(), // standard coloring pages removed from the Settings list (slugs; also in art_hide)
		'art_doors'  => array(),  // Kids' Corner door pictures: names / show / coloring / games => attachment ID
		'lab_songs'  => array(),  // Light Lab songs (audio attachment IDs)
		'vote_hide'  => array(),  // song keys left out of voting (intros, announcements)
		'fpp_playlist' => 'Names in Lights', // FPP playlist holding the names break
		'fpp_screens'  => array(),           // [ name, fpp address, overlay model ] for the names script
		'voice_on'     => 0,                 // say each name out loud (ElevenLabs)
		'voice_key'    => '',
		'voice_id'     => '',
		'voice_name'   => '',
		'voice_model'  => 'eleven_multilingual_v2',
		'voice_effect' => '',                // FPP effect (.eseq) that moves the singing faces while a name is spoken
		'voice_speed'  => 0.9,               // ElevenLabs speaking speed (0.7–1.2)
		'voice_santa'  => 0,                 // the voice is Santa: "Hi Santa! Love, Mia" is answered "Hi Mia, Santa loves you too!"
		'names_start'  => 4,                 // seconds into the names break when the first name goes up
		'names_slot'   => 3.75,              // seconds from one name to the next
		'break_intro'  => '',                // names break builder: announcer lines (empty = example)
		'break_outro'  => '',
		'break_seq'    => 29.6,              // length of the names sequence (seconds)
		'break_music'  => 0,                 // instrumental MP3 (attachment ID), 0 = the one that comes with Marquee
	);
}

/** All Marquee settings, or one of them. */
function marquee_get( $key = null ) {
	$memo = wp_parse_args( (array) get_option( 'marquee', array() ), marquee_defaults() );
	if ( null === $key ) {
		return $memo;
	}
	return isset( $memo[ $key ] ) ? $memo[ $key ] : '';
}

/** Settings the theme stores as theme mods; Marquee edits them in the same place. */
function marquee_theme_keys() {
	return array(
		'radio', 'season_start', 'season_end', 'use_sunset', 'weeknight_start', 'weekend_start', 'weeknight_close', 'weekend_close',
		'radar_station', 'cashapp_url', 'gb_show', 'names_on', 'names_geo', 'names_geo_miles', 'kids_show', 'lab_on', 'songs_show', 'contest_on', 'app_auto_on', 'snow_amount',
		'names_geo_unit', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_show', 'instagram_show', 'youtube_show', 'tiktok_show',
	);
}
function marquee_theme_get( $k ) {
	if ( function_exists( 'ridgeroad_mod' ) ) {
		return ridgeroad_mod( $k );
	}
	return get_theme_mod( $k, '' );
}

/** Clean and save everything posted from the wizard or the settings page. */
function marquee_save( $in ) {
	$cur = marquee_get();
	$txt = function ( $k, $max = 120 ) use ( $in, $cur ) {
		return isset( $in[ $k ] ) ? mb_substr( sanitize_text_field( wp_unslash( $in[ $k ] ) ), 0, $max ) : $cur[ $k ];
	};
	$num = function ( $k, $lo, $hi ) use ( $in, $cur ) {
		if ( ! isset( $in[ $k ] ) || '' === trim( (string) $in[ $k ] ) ) {
			return isset( $in[ $k ] ) ? '' : $cur[ $k ];
		}
		$v = (float) $in[ $k ];
		return ( $v >= $lo && $v <= $hi ) ? round( $v, 5 ) : $cur[ $k ];
	};
	$new = array(
		'show_name'  => $txt( 'show_name', 80 ),
		'short_name' => $txt( 'short_name', 12 ),
		'address'    => isset( $in['address'] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $in['address'] ) ) ), 0, 400 ) : $cur['address'],
		'city'       => $txt( 'city', 60 ),
		'region'     => $txt( 'region', 40 ),
		'zip'        => $txt( 'zip', 12 ),
		'country'    => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $txt( 'country', 2 ) ), 0, 2 ) ),
		'lat'        => $num( 'lat', -90, 90 ),
		'lon'        => $num( 'lon', -180, 180 ),
		'tz'         => in_array( $txt( 'tz', 60 ), timezone_identifiers_list(), true ) ? $txt( 'tz', 60 ) : $cur['tz'],
		'nws_office' => isset( $in['nws_office'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', wp_unslash( $in['nws_office'] ) ), 0, 3 ) ) : $cur['nws_office'],
		'units'      => ( isset( $in['units'] ) && 'metric' === $in['units'] ) ? 'metric' : ( isset( $in['units'] ) ? 'us' : $cur['units'] ),
		'rf_token'   => $txt( 'rf_token', 200 ),
		'rf_secret'  => ( isset( $in['rf_secret'] ) && '' !== $in['rf_secret'] && '••••••••' !== $in['rf_secret'] ) ? $txt( 'rf_secret', 200 ) : $cur['rf_secret'],
		'names_key'  => $cur['names_key'],
		'art_house'  => $cur['art_house'],
		'art_pages'  => $cur['art_pages'],
		'art_hide'   => $cur['art_hide'],
		'art_removed' => $cur['art_removed'],
		'art_doors'  => $cur['art_doors'],
		'lab_songs'  => $cur['lab_songs'],
		'fpp_playlist' => isset( $in['fpp_playlist'] ) && '' !== trim( (string) $in['fpp_playlist'] ) ? mb_substr( sanitize_text_field( wp_unslash( $in['fpp_playlist'] ) ), 0, 80 ) : $cur['fpp_playlist'],
		'fpp_screens'  => isset( $in['fpp_sent'] ) ? marquee_clean_screens( isset( $in['fpp_screens'] ) ? (array) wp_unslash( $in['fpp_screens'] ) : array() ) : $cur['fpp_screens'],
		'voice_on'     => isset( $in['voice_sent'] ) ? (int) ! empty( $in['voice_on'] ) : $cur['voice_on'],
		'voice_key'    => ( isset( $in['voice_key'] ) && '' !== trim( (string) $in['voice_key'] ) && '••••••••' !== $in['voice_key'] ) ? $txt( 'voice_key', 200 ) : $cur['voice_key'],
		'voice_id'     => isset( $in['voice_id'] ) ? substr( preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $in['voice_id'] ) ), 0, 40 ) : $cur['voice_id'],
		'voice_name'   => $txt( 'voice_name', 80 ),
		'voice_model'  => ( isset( $in['voice_model'] ) && function_exists( 'marquee_voice_models' ) && isset( marquee_voice_models()[ $in['voice_model'] ] ) ) ? $in['voice_model'] : $cur['voice_model'],
		'voice_effect' => isset( $in['voice_effect'] ) ? $txt( 'voice_effect', 200 ) : $cur['voice_effect'],
		'voice_speed'  => isset( $in['voice_speed'] ) ? max( 0.7, min( 1.2, round( (float) $in['voice_speed'], 2 ) ) ) : $cur['voice_speed'],
		'voice_santa'  => isset( $in['voice_sent'] ) ? (int) ! empty( $in['voice_santa'] ) : $cur['voice_santa'],
		'break_intro'  => $txt( 'break_intro', 160 ),
		'break_outro'  => $txt( 'break_outro', 160 ),
		'break_seq'    => isset( $in['break_seq'] ) && '' !== trim( (string) $in['break_seq'] ) ? max( 15, min( 120, round( (float) $in['break_seq'], 2 ) ) ) : $cur['break_seq'],
		'break_music'  => isset( $in['break_music'] ) ? ( ( absint( $in['break_music'] ) && 0 === strpos( (string) get_post_mime_type( absint( $in['break_music'] ) ), 'audio/' ) ) ? absint( $in['break_music'] ) : 0 ) : $cur['break_music'],
		'names_slot'   => isset( $in['names_slot'] ) && '' !== trim( (string) $in['names_slot'] ) ? max( 2.5, min( 10, round( (float) $in['names_slot'], 2 ) ) ) : $cur['names_slot'],
		'names_start'  => isset( $in['names_start'] ) && '' !== trim( (string) $in['names_start'] ) ? max( 0, min( 120, round( (float) $in['names_start'], 2 ) ) ) : $cur['names_start'],
		'vote_hide'  => isset( $in['vote_hide_sent'] ) ? array_values( array_slice( array_unique( array_filter( array_map( 'sanitize_title', isset( $in['vote_hide'] ) ? (array) wp_unslash( $in['vote_hide'] ) : array() ) ) ), 0, 200 ) ) : $cur['vote_hide'],
	);
	if ( isset( $in['art_sent'] ) ) {
		$img = function ( $v ) {
			$id = absint( $v );
			return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
		};
		$new['art_house'] = $img( isset( $in['art_house'] ) ? $in['art_house'] : 0 );
		$pages = isset( $in['art_pages'] ) ? explode( ',', (string) $in['art_pages'] ) : array();
		$new['art_pages'] = array_values( array_slice( array_filter( array_unique( array_map( $img, $pages ) ) ), 0, 12 ) );
		if ( function_exists( 'ridgeroad_std_coloring' ) ) {
			$std                = array_diff( array_keys( ridgeroad_std_coloring() ), array( 'house' ) );
			$new['art_removed'] = array_values( array_intersect( $std, isset( $in['art_removed'] ) ? array_map( 'sanitize_key', (array) $in['art_removed'] ) : array() ) );
			$keep               = array_diff( isset( $in['art_std'] ) ? array_map( 'sanitize_key', (array) $in['art_std'] ) : array(), $new['art_removed'] );
			$new['art_hide']    = array_values( array_diff( $std, $keep ) );
		}
		$aud = function ( $v ) {
			$id = absint( $v );
			return ( $id && 0 === strpos( (string) get_post_mime_type( $id ), 'audio/' ) ) ? $id : 0;
		};
		$songs = isset( $in['lab_songs'] ) ? explode( ',', (string) $in['lab_songs'] ) : array();
		$new['lab_songs'] = array_values( array_slice( array_filter( array_unique( array_map( $aud, $songs ) ) ), 0, 12 ) );
		$new['art_doors'] = array();
		foreach ( array( 'names', 'show', 'coloring', 'games' ) as $k ) {
			$id = $img( isset( $in[ 'art_door_' . $k ] ) ? $in[ 'art_door_' . $k ] : 0 );
			if ( $id ) {
				$new['art_doors'][ $k ] = $id;
			}
		}
	}
	if ( isset( $in['voice_clear'] ) ) {
		$new['voice_key'] = '';
		$new['voice_on']  = 0;
	}
	if ( isset( $in['rf_clear'] ) ) {
		$new['rf_token']  = '';
		$new['rf_secret'] = '';
	}
	update_option( 'marquee', $new, false );
	if ( $new['show_name'] && $new['show_name'] !== get_bloginfo( 'name' ) ) {
		update_option( 'blogname', $new['show_name'] );
	}
	if ( $new['tz'] && $new['tz'] !== get_option( 'timezone_string' ) ) {
		update_option( 'timezone_string', $new['tz'] ); // WordPress's own clock follows the show
	}

	// theme-side settings
	$t = array(
		'radio'           => array( 'text', 20 ),
		'season_start'    => array( 'date' ),
		'season_end'      => array( 'date' ),
		'weeknight_start' => array( 'time' ),
		'weekend_start'   => array( 'time' ),
		'weeknight_close' => array( 'time' ),
		'weekend_close'   => array( 'time' ),
		'radar_station'   => array( 'radar' ),
		'cashapp_url'     => array( 'url' ),
		'facebook_url'    => array( 'url' ),
		'instagram_url'   => array( 'url' ),
		'youtube_url'     => array( 'url' ),
		'tiktok_url'      => array( 'url' ),
	);
	foreach ( $t as $k => $rule ) {
		if ( ! isset( $in[ $k ] ) ) {
			continue;
		}
		$v = trim( (string) wp_unslash( $in[ $k ] ) );
		switch ( $rule[0] ) {
			case 'date':
				$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
				break;
			case 'time':
				if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ) {
					continue 2;
				}
				break;
			case 'radar':
				$v = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $v ), 0, 4 ) );
				break;
			case 'url':
				$v = esc_url_raw( $v );
				break;
			default:
				$v = mb_substr( sanitize_text_field( $v ), 0, $rule[1] );
		}
		set_theme_mod( $k, $v );
	}
	if ( isset( $in['address'] ) ) {
		$a = $new['address'];
		set_theme_mod( 'address', $a );
		set_theme_mod( 'city', trim( $new['city'] . ( $new['region'] ? ', ' . $new['region'] : '' ) ) );
		set_theme_mod( 'zip', $new['zip'] );
	}
	if ( isset( $in['start_mode'] ) ) {
		set_theme_mod( 'use_sunset', 'sunset' === $in['start_mode'] ? 1 : 0 );
	}
	if ( isset( $in['social_sent'] ) && function_exists( 'ridgeroad_social_networks' ) ) {
		$on = isset( $in['social_on'] ) ? (array) $in['social_on'] : array();
		foreach ( array_keys( ridgeroad_social_networks() ) as $net ) {
			set_theme_mod( $net . '_show', in_array( $net, $on, true ) ? 1 : 0 );
		}
	}
	if ( isset( $in['features_sent'] ) ) {
		$f = isset( $in['feat'] ) ? (array) $in['feat'] : array();
		foreach ( array( 'gb_show', 'names_on', 'names_geo', 'kids_show', 'lab_on', 'songs_show', 'contest_on', 'app_auto_on' ) as $k ) {
			set_theme_mod( $k, in_array( $k, $f, true ) ? 1 : 0 );
		}
		if ( isset( $in['names_geo_miles'] ) ) {
			$mi = (float) str_replace( ',', '.', (string) $in['names_geo_miles'] );
			if ( isset( $in['names_geo_unit'] ) && in_array( $in['names_geo_unit'], array( 'km', 'mi' ), true ) ) {
				set_theme_mod( 'names_geo_unit', $in['names_geo_unit'] );
				if ( 'km' === $in['names_geo_unit'] ) {
					$mi = $mi / 1.609344; // entered in kilometres, stored in miles
				}
			}
			set_theme_mod( 'names_geo_miles', (string) round( max( 0.1, min( 50.0, $mi > 0 ? $mi : 0.5 ) ), 2 ) );
		}
		set_theme_mod( 'snow_amount', in_array( 'snow', $f, true ) ? ( 'off' === marquee_theme_get( 'snow_amount' ) ? 'medium' : marquee_theme_get( 'snow_amount' ) ) : 'off' );
	}
	if ( function_exists( 'ridgeroad_flush_cache' ) ) {
		ridgeroad_flush_cache();
	}
	delete_transient( 'ridgeroad_wx_v1' );
	delete_transient( 'marquee_rf_show' );
	return $new;
}

/*
 * Show settings (hours, radio, tips, switches… everything under Appearance → Customize →
 * the show section) live in one Marquee option instead of per-theme "theme mods", so
 * they stay put when a show switches themes. The first time, they are copied from the
 * festive theme (or the active theme).
 */
add_action( 'after_setup_theme', function () {
	if ( ! function_exists( 'ridgeroad_defaults' ) ) {
		return;
	}
	$keys = array_merge( array_keys( ridgeroad_defaults() ), marquee_theme_keys(), array( 'address', 'city', 'zip', 'custom_logo' ) );
	$keys = array_unique( $keys );
	if ( false === get_option( 'marquee_mods' ) ) {
		$src  = get_option( 'theme_mods_ridgeroad' );
		$src  = is_array( $src ) ? $src : (array) get_theme_mods();
		$seed = array();
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $src ) ) {
				$seed[ $k ] = $src[ $k ];
			}
		}
		add_option( 'marquee_mods', $seed, '', true );
	}
	foreach ( $keys as $k ) {
		add_filter( 'theme_mod_' . $k, function ( $v ) use ( $k ) {
			$m = get_option( 'marquee_mods', array() );
			return ( is_array( $m ) && array_key_exists( $k, $m ) ) ? $m[ $k ] : $v;
		}, 5 );
		add_filter( 'pre_set_theme_mod_' . $k, function ( $v ) use ( $k ) {
			$m       = (array) get_option( 'marquee_mods', array() );
			$m[ $k ] = $v;
			update_option( 'marquee_mods', $m, true );
			return $v;
		}, 5 );
	}
}, 20 );

/** Screens for the names script: up to 4 rows of name / FPP address / overlay model. */
function marquee_clean_screens( $rows ) {
	$out = array();
	foreach ( array_slice( $rows, 0, 4 ) as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$host  = trim( preg_replace( '#[^A-Za-z0-9.:/_-]#', '', (string) ( $r['fpp'] ?? '' ) ) );
		$model = mb_substr( sanitize_text_field( (string) ( $r['model'] ?? '' ) ), 0, 60 );
		if ( '' === $host || '' === $model ) {
			continue;
		}
		$out[] = array( 'name' => mb_substr( sanitize_text_field( (string) ( $r['name'] ?? '' ) ), 0, 30 ), 'fpp' => mb_substr( $host, 0, 80 ), 'model' => $model );
	}
	return $out;
}
