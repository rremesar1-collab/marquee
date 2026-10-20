<?php
/**
 * Share previews (Facebook, texts, X) and Google event details.
 *
 * - Share tags are skipped when an SEO plugin (Yoast, Rank Math, All in One SEO,
 *   SEOPress) is active, so nothing is printed twice.
 * - The event details are always printed on the home page during the season.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/** The picture shown when someone shares a link (Customizer override, else the theme's card). */
function ridgeroad_share_image() {
	$custom = ridgeroad_mod( 'share_image' );
	if ( $custom ) {
		return $custom;
	}
	if ( function_exists( 'marquee_get' ) ) { // another show: their logo, never ours
		$logo = get_theme_mod( 'custom_logo' );
		return $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : '';
	}
	return RR_FEAT_URL . 'assets/img/share-card.jpg?v=' . RIDGEROAD_VERSION;
}

/** "Nightly 5:30 PM – 10 PM (Fri & Sat until 11 PM)" from the Customizer hours. */
function ridgeroad_hours_text() {
	$fmt = function ( $hm ) {
		list( $h, $m ) = array_map( 'intval', explode( ':', $hm ) );
		$t = mktime( $h, $m, 0, 1, 1, 2000 );
		return $m ? date( 'g:i A', $t ) : date( 'g A', $t ); // phpcs:ignore
	};
	$start = ridgeroad_mod( 'use_sunset' ) ? __( 'sunset', 'ridgeroad' ) : $fmt( ridgeroad_mod( 'weeknight_start' ) );
	$wk    = $fmt( ridgeroad_mod( 'weeknight_close' ) );
	$we    = $fmt( ridgeroad_mod( 'weekend_close' ) );
	/* translators: 1: start time, 2: weeknight close, 3: weekend close */
	return $wk === $we ? sprintf( __( 'Nightly %1$s – %2$s', 'ridgeroad' ), $start, $wk ) : sprintf( __( 'Nightly %1$s – %2$s (Fri & Sat until %3$s)', 'ridgeroad' ), $start, $wk, $we );
}

function ridgeroad_seo_description() {
	if ( function_exists( 'ridgeroad_kids_url' ) && is_page( (int) get_option( 'ridgeroad_kids_page' ) ) ) {
		/* translators: %s: show name */
		return sprintf( __( 'Kids’ Corner at %s: put your name in lights, design your own light show, print coloring pages and play Christmas games.', 'ridgeroad' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}
	$s = ridgeroad_mod( 'season_start' );
	$e = ridgeroad_mod( 'season_end' );
	$d = '';
	if ( $s && $e ) {
		/* translators: 1: opening date, 2: closing date */
		$d = sprintf( __( '%1$s – %2$s.', 'ridgeroad' ), wp_date( 'M j', strtotime( $s . ' 12:00' ), ridgeroad_tz() ), wp_date( 'M j', strtotime( $e . ' 12:00' ), ridgeroad_tz() ) ) . ' ';
	}
	/* translators: %s: radio frequency */
	return trim( ridgeroad_mod( 'intro' ) . ' ' . $d . ridgeroad_hours_text() . '. ' . sprintf( __( 'Tune to %s.', 'ridgeroad' ), ridgeroad_mod( 'radio' ) ) );
}

add_action( 'wp_head', function () {
	if ( ridgeroad_seo_plugin_active() || is_admin() ) {
		return;
	}
	$name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$title = is_front_page() ? $name . ' – ' . ridgeroad_mod( 'subtitle' ) : wp_get_document_title();
	$desc  = ridgeroad_seo_description();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$img   = ridgeroad_share_image();
	$tags  = array(
		array( 'name', 'description', $desc ),
		array( 'property', 'og:type', 'website' ),
		array( 'property', 'og:site_name', $name ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $desc ),
		array( 'property', 'og:url', $url ),
		array( 'property', 'og:image', $img ),
		array( 'property', 'og:image:width', '1200' ),
		array( 'property', 'og:image:height', '630' ),
		array( 'property', 'og:image:alt', $name . ' – ' . __( 'free drive-by Christmas light show', 'ridgeroad' ) ),
		array( 'property', 'og:locale', 'en_US' ),
		array( 'name', 'twitter:card', 'summary_large_image' ),
		array( 'name', 'twitter:title', $title ),
		array( 'name', 'twitter:description', $desc ),
		array( 'name', 'twitter:image', $img ),
	);
	if ( '' === $img ) {
		$tags = array_values( array_filter( $tags, function ( $t ) {
			return false === strpos( $t[1], 'image' );
		} ) );
		$tags[] = array( 'name', 'twitter:card', 'summary' );
	} elseif ( ridgeroad_mod( 'share_image' ) || function_exists( 'marquee_get' ) ) { // a custom picture may not be 1200×630
		$tags = array_values( array_filter( $tags, function ( $t ) {
			return ! in_array( $t[1], array( 'og:image:width', 'og:image:height' ), true );
		} ) );
	}
	foreach ( $tags as $t ) {
		printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $t[0] ), esc_attr( $t[1] ), esc_attr( $t[2] ) );
	}
}, 4 );

/* Google: the show as an event (home page, until the season ends). */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$s = ridgeroad_mod( 'season_start' );
	$e = ridgeroad_mod( 'season_end' );
	if ( ! $s || ! $e ) {
		return;
	}
	$tz = ridgeroad_tz();
	try {
		$first = ridgeroad_night( new DateTimeImmutable( $s . ' 00:00', $tz ) );
		$last  = ridgeroad_night( new DateTimeImmutable( $e . ' 00:00', $tz ) );
	} catch ( \Exception $ex ) {
		return;
	}
	if ( time() > $last['end']->getTimestamp() ) {
		return; // season is over
	}
	$name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$mq      = function_exists( 'marquee_get' ) && marquee_get( 'city' ) ? marquee_get() : null;
	$address = array(
		'@type'           => 'PostalAddress',
		'addressLocality' => $mq && $mq['city'] ? $mq['city'] : '',
		'addressRegion'   => $mq && $mq['region'] ? $mq['region'] : '',
		'postalCode'      => $mq && $mq['zip'] ? $mq['zip'] : '',
		'addressCountry'  => $mq && $mq['country'] ? $mq['country'] : 'US',
	);
	$a = trim( (string) ridgeroad_mod( 'address' ) );
	if ( $a && ! preg_match( '#^https?://#i', $a ) && ! preg_match( '#^-?\d{1,2}\.\d+\s*,#', $a ) ) {
		$address['streetAddress'] = $a;
	}
	$event = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => $name,
		'description'         => ridgeroad_seo_description(),
		'image'               => array_values( array_filter( array( ridgeroad_share_image() ) ) ),
		'startDate'           => $first['start']->format( 'c' ),
		'endDate'             => $last['end']->format( 'c' ),
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'isAccessibleForFree' => true,
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $name,
			'address' => $address,
			'geo'     => array( '@type' => 'GeoCoordinates', 'latitude' => RIDGEROAD_LAT, 'longitude' => RIDGEROAD_LON ),
		),
		'offers'              => array(
			'@type'         => 'Offer',
			'price'         => '0',
			'priceCurrency' => 'USD',
			'availability'  => 'https://schema.org/InStock',
			'url'           => home_url( '/' ),
			'validFrom'     => $first['start']->modify( '-60 days' )->format( 'c' ),
		),
		'organizer'           => array( '@type' => 'Organization', 'name' => $name, 'url' => home_url( '/' ) ),
		'performer'           => array( '@type' => 'PerformingGroup', 'name' => $name, 'url' => home_url( '/' ) ), // a light show performs itself (Google asks for a performer)
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 5 );
