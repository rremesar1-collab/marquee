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
		'covers_on'  => 1,        // cover pictures in Tonight's Playlist and song voting
		'covers_auto' => 1,       // find album covers automatically for songs without artwork
		'covers'     => array(),  // song key => attachment ID (your picture) or -1 (no cover)
		'names_mode'   => 'break',           // 'break' = a short names break between songs; 'live' = during songs on a spare screen
		'live_show_for' => 6,                // live mode: seconds each name stays up
		'fpp_playlist' => 'Names in Lights', // FPP playlist holding the names break
		'names_playlists' => array(),        // names go up only while one of these FPP playlists plays (empty = any playlist)
		'fpp_screens'  => array(),           // [ name, fpp address, overlay model ] for the names script
		'names_simple' => 1,                 // no names break playlist on the FPP: the script (3.10+) makes a simple one, no sequence needed
		'break_own'    => 0,                 // the show uses its own names break audio: Marquee doesn't put its break audio in the playlists
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
		'names_per_break' => 6,              // names in one break (fewer = each is up longer, so scrolling can be slower)
		'names_every'  => 1,                 // a names break after every this many songs (names collect in between)
		'break_intro'  => '',                // names break builder: announcer lines (empty = example)
		'break_outro'  => '',
		'break_seq'    => 29.6,              // length of the names sequence (seconds)
		'break_music'  => 0,                 // instrumental MP3 (attachment ID), 0 = the one that comes with Marquee
		'themes_on'    => 0,                 // kids pick a themed sequence for their name
		'classic_img'  => 0,                 // picture on the "Classic" card kids pick from (0 = the animated house)
		'themes'       => array(),           // [ name, playlist, img (attachment ID), start, slot, look ] up to 6
		'name_msgs'    => array(),           // your own messages kids pick from ('Merry Christmas, NAME!'), up to 6; empty = the standard ones
		'photos'       => 'off',             // kids' pictures: off, cartoon, photo or both (inc/photos.php)
		'photos_ack'   => 0,                 // the owner accepted the notice about kids' pictures
		'photos_auto'  => 0,                 // check pictures automatically (Google Cloud Vision) and skip approving the clean ones
		'vision_key'   => '',                // the owner's own Google Cloud Vision API key
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
		'radar_station', 'cashapp_url', 'gb_show', 'names_on', 'names_geo', 'names_geo_miles', 'kids_show', 'lab_on', 'coloring_on', 'games_on', 'songs_show', 'contest_on', 'app_auto_on', 'snow_amount',
		'names_geo_unit', 'count_to', 'count_name', 'count_date', 'rf_viewer_url', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_show', 'instagram_show', 'youtube_show', 'tiktok_show',
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
		'rf_token'   => ( isset( $in['rf_token'] ) && '••••••••' === $in['rf_token'] ) ? $cur['rf_token'] : $txt( 'rf_token', 200 ),
		'rf_secret'  => ( isset( $in['rf_secret'] ) && '' !== $in['rf_secret'] && '••••••••' !== $in['rf_secret'] ) ? $txt( 'rf_secret', 200 ) : $cur['rf_secret'],
		'names_key'  => $cur['names_key'],
		'art_house'  => $cur['art_house'],
		'art_pages'  => $cur['art_pages'],
		'art_hide'   => $cur['art_hide'],
		'art_removed' => $cur['art_removed'],
		'art_doors'  => $cur['art_doors'],
		'lab_songs'  => $cur['lab_songs'],
		'names_mode'   => isset( $in['names_mode'] ) && in_array( $in['names_mode'], array( 'break', 'live' ), true ) ? $in['names_mode'] : $cur['names_mode'],
		'live_show_for' => isset( $in['live_show_for'] ) && '' !== trim( (string) $in['live_show_for'] ) ? max( 3, min( 20, round( (float) $in['live_show_for'], 1 ) ) ) : $cur['live_show_for'],
		'fpp_playlist' => isset( $in['fpp_playlist'] ) && '' !== trim( (string) $in['fpp_playlist'] ) ? mb_substr( sanitize_text_field( wp_unslash( $in['fpp_playlist'] ) ), 0, 80 ) : $cur['fpp_playlist'],
		'names_playlists' => isset( $in['fpp_sent'] ) ? marquee_clean_playlist_list( isset( $in['names_playlists'] ) ? (array) wp_unslash( $in['names_playlists'] ) : array(), isset( $in['names_playlists_more'] ) ? (string) wp_unslash( $in['names_playlists_more'] ) : '' ) : $cur['names_playlists'],
		'fpp_screens'  => isset( $in['fpp_sent'] ) ? marquee_clean_screens( isset( $in['fpp_screens'] ) ? (array) wp_unslash( $in['fpp_screens'] ) : array() ) : $cur['fpp_screens'],
		'names_simple' => isset( $in['fpp_sent'] ) ? (int) ! empty( $in['names_simple'] ) : $cur['names_simple'],
		'break_own'    => isset( $in['break_own_sent'] ) ? (int) ! empty( $in['break_own'] ) : $cur['break_own'],
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
		'themes_on'    => isset( $in['themes_sent'] ) ? (int) ! empty( $in['themes_on'] ) : $cur['themes_on'],
		'classic_img'  => isset( $in['themes_sent'] ) ? ( ( absint( $in['classic_img'] ?? 0 ) && wp_attachment_is_image( absint( $in['classic_img'] ) ) ) ? absint( $in['classic_img'] ) : 0 ) : $cur['classic_img'],
		'themes'       => isset( $in['themes_sent'] ) ? marquee_clean_themes( isset( $in['themes'] ) ? (array) wp_unslash( $in['themes'] ) : array() ) : $cur['themes'],
		'name_msgs'    => isset( $in['msgs_sent'] ) ? marquee_clean_msgs( isset( $in['name_msgs'] ) ? (array) wp_unslash( $in['name_msgs'] ) : array() ) : $cur['name_msgs'],
		'names_slot'   => isset( $in['names_slot'] ) && '' !== trim( (string) $in['names_slot'] ) ? max( 2.5, min( 10, round( (float) $in['names_slot'], 2 ) ) ) : $cur['names_slot'],
		'names_per_break' => isset( $in['names_per_break'] ) ? max( 1, min( 6, (int) $in['names_per_break'] ) ) : $cur['names_per_break'],
		'names_every'  => isset( $in['names_every'] ) ? max( 1, min( 6, (int) $in['names_every'] ) ) : $cur['names_every'],
		'names_start'  => isset( $in['names_start'] ) && '' !== trim( (string) $in['names_start'] ) ? max( 0, min( 120, round( (float) $in['names_start'], 2 ) ) ) : $cur['names_start'],
		'covers_on'  => isset( $in['covers_sent'] ) ? (int) ! empty( $in['covers_on'] ) : $cur['covers_on'],
		'covers_auto' => isset( $in['covers_sent'] ) ? (int) ! empty( $in['covers_auto'] ) : $cur['covers_auto'],
		'covers'     => isset( $in['covers_sent'] ) ? marquee_clean_covers( isset( $in['covers'] ) ? (array) wp_unslash( $in['covers'] ) : array() ) : $cur['covers'],
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
	$new = apply_filters( 'marquee_settings_saved', $new, $in );
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
		'count_to'        => array( 'pick', array( 'auto', 'christmas', 'halloween', 'custom' ) ),
		'count_name'      => array( 'text', 40 ),
		'count_date'      => array( 'date' ),
		'rf_viewer_url'   => array( 'url' ),
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
			case 'pick':
				$v = in_array( $v, $rule[1], true ) ? $v : $rule[1][0];
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
		foreach ( array( 'gb_show', 'names_on', 'names_geo', 'kids_show', 'lab_on', 'coloring_on', 'games_on', 'songs_show', 'contest_on', 'app_auto_on' ) as $k ) {
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
	if ( isset( $in['block_sent'] ) ) {   // Blocked names and words (Names & FPP)
		$words = array();
		foreach ( preg_split( '/[,\n]+/', (string) wp_unslash( $in['names_block'] ?? '' ) ) as $w ) {
			$w = mb_strtolower( trim( preg_replace( "/[^\\p{L}\\p{N}'\\- ]/u", '', sanitize_text_field( $w ) ) ), 'UTF-8' );
			if ( '' !== $w && mb_strlen( $w ) <= 30 ) {
				$words[ $w ] = $w;
			}
		}
		set_theme_mod( 'names_block', implode( ', ', array_slice( array_values( $words ), 0, 500 ) ) );
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
/** Themed sequences from the settings form: only rows with a name and an FPP playlist. */
function marquee_clean_themes( $rows ) {
	$out  = array();
	$seen = array();
	foreach ( array_slice( $rows, 0, 6 ) as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$name = mb_substr( trim( sanitize_text_field( (string) ( $r['name'] ?? '' ) ) ), 0, 30 );
		$pl   = mb_substr( trim( sanitize_text_field( (string) ( $r['playlist'] ?? '' ) ) ), 0, 80 );
		$slug = sanitize_title( $name );
		if ( '' === $name || '' === $pl || '' === $slug || isset( $seen[ $slug ] ) ) {
			continue;
		}
		$seen[ $slug ] = 1;
		$img           = absint( $r['img'] ?? 0 );
		$start         = (float) ( $r['start'] ?? 0 );
		$slot          = (float) ( $r['slot'] ?? 0 );
		$out[]         = array(
			'name'     => $name,
			'playlist' => $pl,
			'img'      => ( $img && wp_attachment_is_image( $img ) ) ? $img : 0,
			'start'    => $start > 0 ? min( 60, round( $start, 2 ) ) : 4,
			'slot'     => $slot >= 2.5 ? min( 10, round( $slot, 2 ) ) : 3.75,
			'look'     => ( function_exists( 'marquee_theme_looks' ) && isset( marquee_theme_looks()[ (string) ( $r['look'] ?? '' ) ] ) ) ? (string) $r['look'] : '',
		);
	}
	return $out;
}

function marquee_clean_screens( $rows ) {
	$out = array();
	foreach ( array_slice( $rows, 0, 4 ) as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$host  = trim( preg_replace( '#[^A-Za-z0-9.:/_-]#', '', (string) ( $r['fpp'] ?? '' ) ) );
		$model = mb_substr( sanitize_text_field( (string) ( $r['model'] ?? '' ) ), 0, 60 );
		if ( '' === $host && '' !== $model ) {
			$host = '127.0.0.1'; // a screen picked with no address: the FPP running the script (it used to be dropped without a word)
		}
		if ( '' === $host || '' === $model ) {
			continue;
		}
		$pick  = function ( $k, $ok ) use ( $r ) {
			$v = sanitize_key( (string) ( $r[ $k ] ?? '' ) );
			return in_array( $v, $ok, true ) ? $v : $ok[0];
		};
		$out[] = array(
			'name'  => mb_substr( sanitize_text_field( (string) ( $r['name'] ?? '' ) ), 0, 30 ),
			'fpp'   => mb_substr( $host, 0, 80 ),
			'model' => $model,
			'style' => $pick( 'style', array( 'auto', 'still', 'scroll' ) ),
			'size'  => $pick( 'size', array( 'auto', 's', 'm', 'l', 'xl', 'custom' ) ),
			'px'    => max( 0, min( 400, (int) ( $r['px'] ?? 0 ) ) ),
			'speed' => $pick( 'speed', array( 'normal', 'slow', 'fast' ) ),
		);
	}
	return $out;
}


/* How names go up: in a names break between songs, or during songs on a spare screen.
   The FPP script reads this on every check-in, so switching needs no new settings file. */
function marquee_names_live() {
	return 'live' === marquee_get()['names_mode'];
}
add_filter( 'ridgeroad_names_live', 'marquee_names_live' );
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	$s                     = marquee_get();
	$data['mode']          = 'live' === $s['names_mode'] ? 'live' : 'break';
	$data['live_show_for'] = (float) $s['live_show_for'];
	$data['config']        = marquee_fpp_config();   // script 3.7+: screens and timing come from here, not the settings file
	return $data;
}, 5 );

/**
 * The playlists names may go up during: the ticked ones plus any typed (comma separated). Empty = any playlist.
 */
function marquee_clean_playlist_list( $ticked, $typed ) {
	$out = array();
	foreach ( array_merge( (array) $ticked, explode( ',', (string) $typed ) ) as $p ) {
		$p = trim( mb_substr( sanitize_text_field( (string) $p ), 0, 80 ) );
		if ( '' !== $p && ! in_array( mb_strtolower( $p ), array_map( 'mb_strtolower', $out ), true ) ) {
			$out[] = $p;
		}
	}
	return array_slice( $out, 0, 20 );
}

/** What the FPP names script needs from Settings (the screens and the names break timing). No keys. */
function marquee_fpp_config() {
	$s       = marquee_get();
	$screens = array();
	foreach ( (array) $s['fpp_screens'] as $r ) {
		if ( ! empty( $r['fpp'] ) && ! empty( $r['model'] ) ) {
			$screens[] = array(
				'name'  => $r['name'] ? $r['name'] : 'screen ' . ( count( $screens ) + 1 ),
				'fpp'   => ( 0 === strpos( $r['fpp'], 'http' ) ? $r['fpp'] : 'http://' . $r['fpp'] ),
				'model' => $r['model'],
				'style' => $r['style'] ?? 'auto',   // how the text goes up: auto, still, scroll (script 3.8+)
				'size'  => $r['size'] ?? 'auto',    // auto, s, m, l, xl, custom
				'px'    => (int) ( $r['px'] ?? 0 ),  // custom: the font size (about how many pixels tall the letters are)
				'speed' => $r['speed'] ?? 'normal', // scrolling speed: slow, normal, fast
			);
		}
	}
	return array(
		'break_playlist' => $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights',
		'screens'        => $screens,
		'talk_effect'    => (string) $s['voice_effect'],
		'names_start'    => (float) $s['names_start'],
		'slot'           => (float) $s['names_slot'],
		'show_for'       => max( 2.0, round( (float) $s['names_slot'] - 0.6, 2 ) ),
		'only_playlists' => array_values( (array) $s['names_playlists'] ), // script 3.9+: empty = any playlist
		// script 3.10+: make a simple names break when there's none (only once names are switched on)
		'simple_break'   => ! empty( $s['names_simple'] ) && ( ! function_exists( 'marquee_theme_get' ) || marquee_theme_get( 'names_on' ) ),
		'break_own'      => ! empty( $s['break_own'] ), // script 3.10+: leave the playlists' audio alone, use these timings
		'per_break'      => max( 1, min( 6, (int) $s['names_per_break'] ) ), // script 3.11+: fewer names, each up longer
		'every'          => max( 1, min( 6, (int) $s['names_every'] ) ),     // script 3.12+: a names break after every this many songs
	);
}

/** Song covers from Settings: key => attachment ID (an image) or -1 (no cover); 0 = automatic, not stored. */
function marquee_clean_covers( $in ) {
	$out = array();
	foreach ( array_slice( $in, 0, 300, true ) as $k => $v ) {
		$k = sanitize_title( (string) $k );
		$v = (int) $v;
		if ( '' === $k || 0 === $v ) {
			continue;
		}
		if ( $v > 0 && ! wp_attachment_is_image( $v ) ) {
			continue;
		}
		$out[ $k ] = $v > 0 ? $v : -1;
	}
	return $out;
}

/** The connection code for the Marquee FPP plugin: your website address and private key in one line. */
function marquee_fpp_code() {
	$key = function_exists( 'ridgeroad_names_key' ) ? ridgeroad_names_key() : '';
	return 'MQ1-' . rtrim( strtr( base64_encode( wp_json_encode( array( 'site' => home_url( '/' ), 'key' => $key ) ) ), '+/', '-_' ), '=' );
}

/**
 * The messages kids pick from, as typed in Settings: up to 6, each with NAME where the name goes.
 * Empty (or exactly the standard four) = the standard messages.
 */
function marquee_clean_msgs( $raw ) {
	$out = array();
	foreach ( array_slice( (array) $raw, 0, 6 ) as $m ) {
		$m = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $m ) ) );
		$m = preg_replace( '/[^\p{L}\p{N}\p{P}\p{Zs}+=~^$]/u', '', $m );        // no emoji: the screens can't draw them
		$m = preg_replace( '/\[\s*name\s*\]|\{\s*name\s*\}/iu', 'NAME', $m );            // [name] or {name} mean NAME
		if ( false === strpos( $m, 'NAME' ) ) {
			$m = preg_replace( '/\bname\b/iu', 'NAME', $m, 1 );                        // "Merry Christmas, Name!"
		}
		$k = strpos( $m, 'NAME' );
		if ( false !== $k ) {
			$m = substr( $m, 0, $k + 4 ) . str_replace( 'NAME', '', substr( $m, $k + 4 ) );  // the name goes in once
		}
		$m = trim( preg_replace( '/\s+/u', ' ', $m ) );
		if ( '' === trim( str_replace( 'NAME', '', $m ), " \t,.!?-" ) ) {
			continue;                                                             // empty, or just NAME
		}
		if ( false === strpos( $m, 'NAME' ) ) {
			$m = rtrim( mb_substr( $m, 0, 27 ), ' ,!.' ) . ', NAME!';             // forgot NAME: it goes at the end
		} elseif ( mb_strlen( $m ) > 34 ) {
			continue;                                                             // too long for the screens (the box stops at 34)
		}
		$out[] = $m;
	}
	$out = array_values( array_unique( $out ) );
	return function_exists( 'ridgeroad_name_greetings_std' ) && array_values( ridgeroad_name_greetings_std() ) === $out ? array() : $out;
}

/* Your own messages replace the standard ones on the names form. */
add_filter( 'ridgeroad_name_greetings', function ( $std ) {
	$own = (array) marquee_get( 'name_msgs' );
	if ( ! $own ) {
		return $std;
	}
	$out = array();
	foreach ( array_values( $own ) as $i => $m ) {
		$out[ 'm' . ( $i + 1 ) ] = $m;
	}
	return $out;
} );
