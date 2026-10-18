<?php
/**
 * Address → map pin, time zone and weather radar, for the setup wizard.
 * Accepts "lat, lon", a Google Maps link with a pin, or a place ("Springfield, IL 62701").
 * Uses Open-Meteo's free geocoder and, in the US, the National Weather Service.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_lookup( $address ) {
	$a   = trim( (string) $address );
	$out = array( 'lat' => '', 'lon' => '', 'city' => '', 'region' => '', 'zip' => '', 'country' => '', 'tz' => '', 'radar' => '', 'office' => '' );
	if ( '' === $a ) {
		return new WP_Error( 'marquee_lookup_empty', __( 'Type your show’s address first.', 'marquee' ) );
	}
	$m = array();
	if ( preg_match( '#^(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)$#', $a, $m ) || preg_match( '#!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)#', $a, $m ) || preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)#', $a, $m ) ) {
		$out['lat'] = (float) $m[1];
		$out['lon'] = (float) $m[2];
	} else {
		// The free geocoder knows places and postal codes, not street numbers: try the postcode, then the town.
		$zip   = preg_match( '/\b(\d{5})(?:-\d{4})?\b/', $a, $zm ) ? $zm[1] : '';
		$parts = array_map( 'trim', explode( ',', $a ) );
		$tries = array_filter( array( $zip, count( $parts ) >= 2 ? $parts[ count( $parts ) - 2 ] : '', $parts[0] ) );
		foreach ( array_unique( $tries ) as $q ) {
			$q = preg_replace( '/\b[A-Z]{2}\b\s*\d{5}.*$/', '', $q );
			$r = wp_remote_get( add_query_arg( array( 'name' => trim( $q ), 'count' => 5, 'language' => 'en', 'format' => 'json' ), 'https://geocoding-api.open-meteo.com/v1/search' ), array( 'timeout' => 8 ) );
			if ( is_wp_error( $r ) ) {
				continue;
			}
			$j = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( empty( $j['results'] ) ) {
				continue;
			}
			$best = $j['results'][0];
			foreach ( $j['results'] as $res ) { // prefer a result in the state the address names
				if ( ! empty( $res['admin1'] ) && false !== stripos( $a, $res['admin1'] ) ) {
					$best = $res;
					break;
				}
			}
			$out['lat']     = (float) $best['latitude'];
			$out['lon']     = (float) $best['longitude'];
			$out['city']    = isset( $best['name'] ) ? $best['name'] : '';
			$out['region']  = isset( $best['admin1'] ) ? $best['admin1'] : '';
			$out['country'] = isset( $best['country_code'] ) ? $best['country_code'] : '';
			$out['tz']      = isset( $best['timezone'] ) ? $best['timezone'] : '';
			$out['zip']     = $zip ? $zip : ( isset( $best['postcodes'][0] ) ? $best['postcodes'][0] : '' );
			break;
		}
		if ( '' === $out['lat'] ) {
			return new WP_Error( 'marquee_lookup_none', __( 'Couldn’t find that place. Try “Town, State ZIP”, or paste a Google Maps link to your house.', 'marquee' ) );
		}
	}
	// US: National Weather Service gives the radar, the time zone and the town for a pin.
	$r = wp_remote_get( sprintf( 'https://api.weather.gov/points/%.4f,%.4f', $out['lat'], $out['lon'] ), array( 'timeout' => 8, 'headers' => array( 'User-Agent' => 'Marquee/' . MARQUEE_VERSION . ' (' . home_url() . ')', 'Accept' => 'application/geo+json' ) ) );
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		$p = isset( $j['properties'] ) ? $j['properties'] : array();
		if ( ! empty( $p['radarStation'] ) ) {
			$out['radar'] = $p['radarStation'];
		}
		if ( ! empty( $p['cwa'] ) ) {
			$out['office'] = $p['cwa'];
		}
		if ( ! empty( $p['timeZone'] ) ) {
			$out['tz'] = $p['timeZone'];
		}
		if ( ! empty( $p['relativeLocation']['properties']['city'] ) && '' === $out['city'] ) {
			$out['city']   = $p['relativeLocation']['properties']['city'];
			$out['region'] = $p['relativeLocation']['properties']['state'];
		}
		$out['country'] = $out['country'] ? $out['country'] : 'US';
	}
	if ( '' === $out['tz'] ) { // outside the US, or the pin came from a link: ask Open-Meteo for the zone
		$r = wp_remote_get( add_query_arg( array( 'latitude' => $out['lat'], 'longitude' => $out['lon'], 'current' => 'temperature_2m', 'timezone' => 'auto' ), 'https://api.open-meteo.com/v1/forecast' ), array( 'timeout' => 8 ) );
		if ( ! is_wp_error( $r ) ) {
			$j         = json_decode( wp_remote_retrieve_body( $r ), true );
			$out['tz'] = isset( $j['timezone'] ) ? $j['timezone'] : '';
		}
	}
	return $out;
}

/**
 * The show's National Weather Service office (e.g. "HUN"), for the forecast link.
 * Saved by the address lookup; for shows set up before that, looked up once from the pin.
 */
function marquee_nws_office() {
	$s = marquee_get();
	if ( '' !== $s['nws_office'] || 'US' !== $s['country'] || '' === $s['lat'] || '' === $s['lon'] || get_transient( 'marquee_nws_try' ) ) {
		return $s['nws_office'];
	}
	set_transient( 'marquee_nws_try', 1, DAY_IN_SECONDS ); // one try a day at most
	$r = wp_remote_get( sprintf( 'https://api.weather.gov/points/%.4f,%.4f', $s['lat'], $s['lon'] ), array( 'timeout' => 4, 'headers' => array( 'User-Agent' => 'Marquee/' . MARQUEE_VERSION . ' (' . home_url() . ')', 'Accept' => 'application/geo+json' ) ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return '';
	}
	$j   = json_decode( wp_remote_retrieve_body( $r ), true );
	$cwa = isset( $j['properties']['cwa'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', $j['properties']['cwa'] ) ) : '';
	if ( $cwa ) {
		$s['nws_office'] = substr( $cwa, 0, 3 );
		update_option( 'marquee', $s, false );
	}
	return $s['nws_office'];
}
