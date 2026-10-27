<?php
/**
 * Appearance → Customize → "Ridge Road Show" settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_defaults( $fresh = false ) {
	static $memo = array();
	// One set per language: the first call can come before the translations are loaded, and a
	// visitor can switch the language (the standard texts then follow it).
	$lk = determine_locale() . ( is_textdomain_loaded( 'ridgeroad' ) ? '+' : '' );
	if ( ! $fresh && isset( $memo[ $lk ] ) && ! is_customize_preview() ) {
		return $memo[ $lk ];
	}
	$d = array(
		// Header.
		'subtitle'          => '',
		'tagline'           => __( 'A Little Christmas Magic', 'ridgeroad' ),
		'intro'             => __( 'Thousands of lights dancing to holiday music. Tune your car radio, sit back, and enjoy the show!', 'ridgeroad' ),
		'bg_image'          => '',
		'snow_amount'       => 'medium',
		'snow_size'         => 'medium',
		'snow_real'         => 1,
		// Show.
		'radio'             => '',
		'season_start'      => '2026-11-27',
		'season_end'        => '2027-01-01',
		'weeknight_label'   => __( 'Sun – Thu', 'ridgeroad' ),
		'weeknight_close'   => '22:00',
		'weekend_label'     => __( 'Fri – Sat', 'ridgeroad' ),
		'weekend_close'     => '23:00',
		'start_offset'      => 0,
		'use_sunset'        => 0,
		'weeknight_start'   => '17:30',
		'weekend_start'     => '17:30',
		'ok_title'          => __( 'Show Planned as Scheduled', 'ridgeroad' ),
		'ok_text'           => __( 'No weather cancellation announced. Please check back before traveling during severe weather.', 'ridgeroad' ),
		'cancel_title'      => __( 'Tonight’s Show Is Canceled', 'ridgeroad' ),
		'cancel_text'       => __( 'Due to weather, the light show will not run tonight. Thank you for understanding — please check back tomorrow!', 'ridgeroad' ),
		'off_title'         => __( 'See You This Christmas Season', 'ridgeroad' ),
		'off_text'          => __( 'The show is resting for now. Check back for opening night!', 'ridgeroad' ),
		// Countdown, playlist, directions.
		'show_countdown'    => 1,
		'ok_hide'           => 0,
		'count_to'          => 'auto',          // countdown holiday: auto, christmas, halloween, custom
		'count_name'        => '',
		'count_date'        => '',
		'rf_viewer_url'     => '',              // your Remote Falcon viewer page (Pick the next song)
		'show_playlist'     => 1,
		'playlist_when'     => 'running',
		'playlist_title'    => __( 'Tonight’s Playlist', 'ridgeroad' ),
		'address'           => '',
		'parking_note'      => __( 'Please park on the shoulder and keep the road clear.', 'ridgeroad' ),
		// Stats.
		'wall_home'         => 0,
		'stats_show'        => 1,
		'stats_title'       => __( 'Behind the Lights', 'ridgeroad' ),
		'stats_text'        => __( 'Every song is programmed by hand, one light at a time.', 'ridgeroad' ),
		'stat1_icon' => 'bulb',  'stat1_num' => '211,265', 'stat1_label' => __( 'LED Pixels', 'ridgeroad' ),
		'stat2_icon' => 'tree',  'stat2_num' => '52',      'stat2_label' => __( 'Light-Up Props', 'ridgeroad' ),
		'stat3_icon' => 'music', 'stat3_num' => '',        'stat3_label' => __( 'Songs in the Show', 'ridgeroad' ),
		'stat4_icon' => 'star',  'stat4_num' => '1.7M',    'stat4_label' => __( 'Light Channels', 'ridgeroad' ),
		'stat5_icon' => 'camera','stat5_num' => '5',       'stat5_label' => __( 'Video Panels', 'ridgeroad' ),
		'stat6_icon' => 'radio', 'stat6_num' => '90.5',    'stat6_label' => __( 'FM — Tune In!', 'ridgeroad' ),
		// Kids' corner.
		'kids_show'         => 1,
		'lab_on'            => 1,
		'coloring_on'       => 1,
		'games_on'          => 1,
		'kids_title'        => __( 'Kids’ Corner', 'ridgeroad' ),
		// Sequence videos.
		'seq_show'          => 1,
		'seq_title'         => __( 'This Year’s Show Videos', 'ridgeroad' ),
		'seq_text'          => __( 'Watch every song from this year’s show, then come see it in person!', 'ridgeroad' ),
		// Favorite song voting.
		'songs_show'        => 1,
		'songs_title'       => __( 'Vote for Your Favorite Song', 'ridgeroad' ),
		'songs_text'        => __( 'Tap the heart on your favorite song in this year’s show. You can change your vote anytime!', 'ridgeroad' ),
		'songs_source'      => 'rf',
		'songs_manual'      => '',
		'songs_friday'      => 1,
		'songs_friday_text' => __( 'We’ll play it to open the show tonight!', 'ridgeroad' ),
		'names_on'          => 1,
		'app_auto_on'       => 1,
		'app_buzz_on'       => 1,
		'names_always'      => 0,
		'names_geo'         => 0,
		'names_geo_miles'   => '0.5',
		'names_block'       => '',
		'names_color'       => '#FFD45C',
		'contest_on'        => 1,
		'contest_deadline'  => '2026-12-20',
		'contest_prize1'    => __( '$25 gift card', 'ridgeroad' ),
		'contest_prize2'    => __( 'Holiday treat bag', 'ridgeroad' ),
		'contest_pickup'    => '2026-12-30',
		'contest_notify'    => '',
		// Weather.
		'city'              => '',
		'zip'               => '',
		'show_radar'        => 1,
		'radar_station'     => '',
		// Live camera.
		'live_url'          => '',
		'live_title'        => __( 'Watch the Show Live', 'ridgeroad' ),
		'live_text'         => __( 'Can’t make it out tonight? Watch the lights from home — turn up the volume!', 'ridgeroad' ),
		// Visitor tips.
		'tips_title'        => __( 'Before You Visit', 'ridgeroad' ),
		'tip1_icon'         => 'car',
		'tip1_title'        => __( 'Park Safely', 'ridgeroad' ),
		'tip1_text'         => __( 'Use a legal parking spot and keep the road open for passing traffic.', 'ridgeroad' ),
		'tip2_icon'         => 'bulb',
		'tip2_title'        => __( 'Headlights Off', 'ridgeroad' ),
		'tip2_text'         => __( 'Once parked, turn off your headlights and leave parking or running lights on.', 'ridgeroad' ),
		'tip3_icon'         => 'home',
		'tip3_title'        => __( 'Respect Our Neighbors', 'ridgeroad' ),
		'tip3_text'         => __( 'Please don’t block driveways or entrances. Keep noise considerate.', 'ridgeroad' ),
		'tip4_icon'         => 'camera',
		'tip4_title'        => __( 'Enjoy the Memories', 'ridgeroad' ),
		'tip4_text'         => __( 'Photos and videos are welcome. Stay in a safe spot while watching the show.', 'ridgeroad' ),
		// Support.
		'donate_title'      => __( 'Keep the Magic Glowing', 'ridgeroad' ),
		'donate_text'       => __( 'Donations help maintain our synchronized Christmas display and add new surprises. They’re never required to enjoy the show.', 'ridgeroad' ),
		'cashapp_url'       => '',
		'cashapp_label'     => __( 'Support via Cash App', 'ridgeroad' ),
		'cashapp_qr'        => '',
		'share_image'       => '',
		'social_title'      => __( 'Stay Connected', 'ridgeroad' ),
		'social_text'       => __( 'Follow us for weather updates, new songs, and show announcements.', 'ridgeroad' ),
		'social_hashtag'    => '',
		'social_share'      => 1,
		'facebook_url'      => '',
		'instagram_url'     => '',
		'youtube_url'       => '',
		'tiktok_url'        => '',
		'facebook_show'     => 1,
		'instagram_show'    => 1,
		'youtube_show'      => 1,
		'tiktok_show'       => 1,
		'guestbook_url'     => '',
		// Guestbook (RF Page Builder).
		'gb_show'           => 1,
		'gb_source'         => 'wordpress',
		'gb_site_key'       => '',
		'gb_eyebrow'        => __( 'A message from your family to ours', 'ridgeroad' ),
		'gb_title'          => __( 'Leave a Little Christmas Cheer', 'ridgeroad' ),
		'gb_intro'          => __( 'Enjoyed the lights? We’d love to hear from you! Sign our guestbook and share a holiday message with our family.', 'ridgeroad' ),
		'gb_count'          => 5,
		'footer_text'       => '',
	);
	// Marquee (any show): no Lights On Ridge Road details as defaults.
	if ( function_exists( 'marquee_get' ) ) {
		$m    = marquee_get();
		$town = trim( $m['city'] . ( $m['region'] ? ', ' . $m['region'] : '' ) );
		$yr   = wp_date( 'n' ) >= 7 ? wp_date( 'Y' ) : (string) ( (int) wp_date( 'Y' ) - 1 );
		$d    = array_merge( $d, array(
			'subtitle'       => trim( ( $town ? $town . ' • ' : '' ) . sprintf( /* translators: year */ __( 'Christmas %s', 'ridgeroad' ), $yr ) ),
			'radio'          => '',
			'city'           => $town,
			'zip'            => $m['zip'],
			'radar_station'  => '',
			'cashapp_url'    => '',
			'cashapp_label'  => __( 'Donate', 'ridgeroad' ),
			'donate_text'    => __( 'Donations help keep the lights on and add new surprises each year. They’re never required to enjoy the show.', 'ridgeroad' ),
			'social_hashtag' => '',
			'facebook_url'   => '',
			'instagram_url'  => '',
			'youtube_url'    => '',
			'tiktok_url'     => '',
			'gb_site_key'    => '',
			'stats_show'     => 0,
			'stat1_num'      => '', 'stat2_num' => '', 'stat4_num' => '', 'stat5_num' => '', 'stat6_num' => '',
		) );
	}
	if ( ! $fresh ) {
		$memo[ $lk ] = $d;
	}
	return $d;
}

/**
 * What the countdown counts down to: the holiday and its timestamp (start of that day, show time zone).
 * Returns array( ts, name, many, one, day, icon ).
 */
function ridgeroad_count_target() {
	$tz   = ridgeroad_tz();
	$kind = (string) ridgeroad_mod( 'count_to' );
	if ( 'custom' === $kind && ( ! preg_match( '/^\d{4}-(\d{2})-(\d{2})$/', (string) ridgeroad_mod( 'count_date' ), $md ) || '' === trim( (string) ridgeroad_mod( 'count_name' ) ) ) ) {
		$kind = 'auto';
	}
	if ( 'auto' === $kind || ! in_array( $kind, array( 'christmas', 'halloween', 'custom' ), true ) ) {
		$end  = (string) ridgeroad_mod( 'season_end' );
		$kind = ( preg_match( '/^\d{4}-(\d{2})-\d{2}$/', $end, $em ) && (int) $em[1] >= 8 && (int) $em[1] <= 10 ) ? 'halloween' : 'christmas'; // a season that ends in Aug–Oct is a Halloween show
	}
	$mmdd = array( 'christmas' => '12-25', 'halloween' => '10-31' );
	$mmdd = 'custom' === $kind ? $md[1] . '-' . $md[2] : $mmdd[ $kind ];
	$t    = new DateTimeImmutable( wp_date( 'Y', null, $tz ) . '-' . $mmdd . ' 00:00', $tz );
	if ( $t->getTimestamp() + DAY_IN_SECONDS < time() ) {
		$t = $t->modify( '+1 year' );
	}
	if ( 'christmas' === $kind ) {
		return array( $t->getTimestamp(), __( 'Christmas', 'ridgeroad' ), __( '%d days until Christmas', 'ridgeroad' ), __( 'Christmas Eve is here!', 'ridgeroad' ), __( 'Merry Christmas!', 'ridgeroad' ), 'gift' );
	}
	if ( 'halloween' === $kind ) {
		return array( $t->getTimestamp(), __( 'Halloween', 'ridgeroad' ), __( '%d days until Halloween', 'ridgeroad' ), __( 'Halloween is tomorrow!', 'ridgeroad' ), __( 'Happy Halloween!', 'ridgeroad' ), 'pumpkin' );
	}
	$n = mb_substr( trim( (string) ridgeroad_mod( 'count_name' ) ), 0, 40 );
	/* translators: %s: the date's name */
	return array( $t->getTimestamp(), $n, sprintf( __( '%%d days until %s', 'ridgeroad' ), $n ), sprintf( __( '%s is tomorrow!', 'ridgeroad' ), $n ), sprintf( __( 'It’s %s!', 'ridgeroad' ), $n ), 'star' );
}

function ridgeroad_mod( $key ) {
	// The visitor picked another language with the language button, and the show typed this
	// text for that language too (its own box in Customize): use that.
	$lang = ridgeroad_lang_now();
	if ( $lang ) {
		$alt = get_theme_mod( $key . '__' . $lang, '' );
		if ( is_string( $alt ) && '' !== trim( $alt ) ) {
			return $alt;
		}
	}
	$d = ridgeroad_defaults();
	$v = get_theme_mod( $key, null );
	if ( null === $v ) {
		return isset( $d[ $key ] ) ? $d[ $key ] : '';
	}
	// A text that is still the standard English wording (saved as-is in Customize) shows in the
	// site's or the visitor's language, like the unsaved ones. Words the show typed itself stay.
	if ( is_string( $v ) && isset( $d[ $key ] ) && $v !== $d[ $key ] && 0 !== strpos( determine_locale(), 'en' ) ) {
		$en = ridgeroad_defaults_en();
		if ( isset( $en[ $key ] ) && $v === $en[ $key ] ) {
			return $d[ $key ];
		}
	}
	return $v;
}

/** The languages a show can type its own words in besides its Site Language ([] = language button off). */
function ridgeroad_lang_extra() {
	if ( ! function_exists( 'marquee_langs_on' ) || ! function_exists( 'marquee_lang_site' ) ) {
		return array();
	}
	return array_values( array_diff( marquee_langs_on(), array( marquee_lang_site() ) ) );
}

/** The language a visitor picked, when it isn't the Site Language ('' otherwise, and always in WP Admin). */
function ridgeroad_lang_now() {
	if ( ! function_exists( 'marquee_lang_pick' ) ) {
		return '';
	}
	$p = marquee_lang_pick();
	return ( $p && in_array( $p, ridgeroad_lang_extra(), true ) ) ? $p : '';
}

/** Texts that are the same in every language (numbers, links, names), so they get no language boxes. */
function ridgeroad_lang_same( $key ) {
	return in_array( $key, array( 'radio', 'city', 'zip', 'radar_station', 'live_url', 'address', 'names_color', 'names_block', 'gb_site_key', 'social_hashtag', 'songs_manual', 'rf_viewer_url' ), true )
		|| preg_match( '/^stat\d_num$/', $key );
}

/** The standard texts in English (as written in the code), to recognise ones saved unchanged. */
function ridgeroad_defaults_en() {
	static $en = null;
	if ( null === $en ) {
		$raw = function ( $tr, $text ) {
			return $text;
		};
		$raw_ctx = function ( $tr, $text ) {
			return $text;
		};
		add_filter( 'gettext_ridgeroad', $raw, 999, 2 );
		add_filter( 'gettext_with_context_ridgeroad', $raw_ctx, 999, 2 );
		$en = ridgeroad_defaults( true );
		remove_filter( 'gettext_ridgeroad', $raw, 999 );
		remove_filter( 'gettext_with_context_ridgeroad', $raw_ctx, 999 );
	}
	return $en;
}

function ridgeroad_sanitize_time( $v, $setting = null ) {
	if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ) {
		return $v;
	}
	return $setting ? $setting->default : '22:00';
}
function ridgeroad_sanitize_date( $v ) {
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
}
function ridgeroad_sanitize_check( $v ) {
	return $v ? 1 : 0;
}
function ridgeroad_icon_choices() {
	return array(
		'car'    => 'Car',
		'bulb'   => 'Light bulb',
		'home'   => 'House',
		'camera' => 'Camera',
		'radio'  => 'Radio',
		'music'  => 'Musical note',
		'tree'   => 'Christmas tree',
		'star'   => 'Star',
		'heart'  => 'Heart',
		'gift'   => 'Gift',
		'clock'  => 'Clock',
	);
}

add_action( 'customize_register', function ( WP_Customize_Manager $wp ) {
	$d = ridgeroad_defaults();

	$wp->add_panel( 'ridgeroad', array(
		'title'    => __( 'Show Website', 'ridgeroad' ),
		'priority' => 25,
	) );

	$sections = array(
		'rr_header'  => __( 'Header & Intro', 'ridgeroad' ),
		'rr_show'    => __( 'Show Hours & Status', 'ridgeroad' ),
		'rr_weather' => __( 'Weather', 'ridgeroad' ),
		'rr_live'    => __( 'Live Camera (YouTube)', 'ridgeroad' ),
		'rr_tips'    => __( 'Visitor Tips', 'ridgeroad' ),
		'rr_seq'     => __( 'Songs Page: Show Videos', 'ridgeroad' ),
		'rr_songs'   => __( 'Songs Page: Favorite Song Voting', 'ridgeroad' ),
		'rr_kids'    => __( 'Kids’ Corner', 'ridgeroad' ),
		'rr_stats'   => __( 'Behind the Lights (Stats)', 'ridgeroad' ),
		'rr_support' => __( 'Donations & Social', 'ridgeroad' ),
		'rr_guestbook' => __( 'Guestbook', 'ridgeroad' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp->add_section( $id, array( 'title' => $title, 'panel' => 'ridgeroad' ) );
	}

	$add = function ( $key, $section, $label, $type = 'text', $extra = array() ) use ( $wp, $d ) {
		$san = 'sanitize_text_field';
		if ( 'textarea' === $type ) {
			$san = 'sanitize_textarea_field';
		} elseif ( 'url' === $type ) {
			$san = 'esc_url_raw';
		} elseif ( 'checkbox' === $type ) {
			$san = 'ridgeroad_sanitize_check';
		} elseif ( 'time' === $type ) {
			$san = 'ridgeroad_sanitize_time';
		} elseif ( 'date' === $type ) {
			$san = 'ridgeroad_sanitize_date';
		} elseif ( 'email' === $type ) {
			$san = 'sanitize_email';
		} elseif ( 'number' === $type ) {
			$san = 'intval';
		} elseif ( 'miles' === $type ) {
			$san = 'ridgeroad_sanitize_miles';
		}
		$wp->add_setting( $key, array(
			'default'           => isset( $d[ $key ] ) ? $d[ $key ] : '',
			'sanitize_callback' => $san,
			'transport'         => 'refresh',
		) );
		$wp->add_control( $key, array_merge( array(
			'label'   => $label,
			'section' => $section,
			'type'    => $type,
		), $extra ) );
		// The language button is on: a box for each other language, right under this one.
		if ( ( 'text' === $type || 'textarea' === $type ) && ! ridgeroad_lang_same( $key ) ) {
			$all = function_exists( 'marquee_langs_all' ) ? marquee_langs_all() : array();
			foreach ( ridgeroad_lang_extra() as $code ) {
				$wp->add_setting( $key . '__' . $code, array(
					'default'           => '',
					'sanitize_callback' => 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field',
					'transport'         => 'refresh',
				) );
				$wp->add_control( $key . '__' . $code, array(
					'label'       => $label . ' · ' . $all[ $code ][0],
					/* translators: %s: a language, like Français */
					'description' => sprintf( __( 'For visitors who pick %s. Leave it empty to use the box above.', 'ridgeroad' ), $all[ $code ][0] ),
					'section'     => $section,
					'type'        => $type,
				) );
			}
		}
	};

	$image = function ( $key, $section, $label, $desc = '' ) use ( $wp ) {
		$wp->add_setting( $key, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp->add_control( new WP_Customize_Image_Control( $wp, $key, array(
			'label'       => $label,
			'description' => $desc,
			'section'     => $section,
		) ) );
	};

	// Header.
	$add( 'subtitle', 'rr_header', __( 'Line under the logo', 'ridgeroad' ) );
	$add( 'tagline', 'rr_header', __( 'Script tagline', 'ridgeroad' ) );
	$add( 'intro', 'rr_header', __( 'Intro sentence', 'ridgeroad' ), 'textarea' );
	$add( 'snow_amount', 'rr_header', __( 'Falling snow', 'ridgeroad' ), 'select', array( 'choices' => array( 'off' => __( 'Off', 'ridgeroad' ), 'light' => __( 'Light flurries', 'ridgeroad' ), 'medium' => __( 'Gentle snowfall', 'ridgeroad' ), 'heavy' => __( 'Snowstorm', 'ridgeroad' ) ) ) );
	$add( 'snow_size', 'rr_header', __( 'Snowflake size', 'ridgeroad' ), 'select', array( 'choices' => array( 'small' => __( 'Small', 'ridgeroad' ), 'medium' => __( 'Medium', 'ridgeroad' ), 'large' => __( 'Large', 'ridgeroad' ) ) ) );
	$add( 'snow_real', 'rr_header', __( 'Snow harder when it is really snowing at the show', 'ridgeroad' ), 'checkbox' );
	$image( 'bg_image', 'rr_header', __( 'Background photo (optional)', 'ridgeroad' ), __( 'Replaces the picture behind your website, with any look. A wide night photo works best (2400px+).', 'ridgeroad' ) );

	// Show.
	$add( 'radio', 'rr_show', __( 'Radio frequency', 'ridgeroad' ) );
	$image( 'share_image', 'rr_show', __( 'Share picture (optional)', 'ridgeroad' ), __( 'Shown when someone shares the website on Facebook or in a text. Best size 1200 × 630. Leave empty to use the built-in picture.', 'ridgeroad' ) );
	$add( 'season_start', 'rr_show', __( 'Season opening night', 'ridgeroad' ), 'date' );
	$add( 'season_end', 'rr_show', __( 'Season last night', 'ridgeroad' ), 'date' );
	$add( 'weeknight_label', 'rr_show', __( 'Weeknight label', 'ridgeroad' ) );
	$add( 'weeknight_close', 'rr_show', __( 'Sun–Thu end time', 'ridgeroad' ), 'time' );
	$add( 'weekend_label', 'rr_show', __( 'Weekend label', 'ridgeroad' ) );
	$add( 'weekend_close', 'rr_show', __( 'Fri–Sat end time', 'ridgeroad' ), 'time' );
	$add( 'weeknight_start', 'rr_show', __( 'Sun–Thu start time', 'ridgeroad' ), 'time' );
	$add( 'weekend_start', 'rr_show', __( 'Fri–Sat start time', 'ridgeroad' ), 'time' );
	$add( 'use_sunset', 'rr_show', __( 'Start at sunset instead of the times above', 'ridgeroad' ), 'checkbox' );
	$add( 'start_offset', 'rr_show', __( 'Minutes after sunset the show starts (only when starting at sunset)', 'ridgeroad' ), 'number', array( 'input_attrs' => array( 'min' => -30, 'max' => 120 ) ) );
	$add( 'ok_title', 'rr_show', __( 'Banner title — show on', 'ridgeroad' ) );
	$add( 'ok_text', 'rr_show', __( 'Banner text — show on', 'ridgeroad' ), 'textarea' );
	$add( 'ok_hide', 'rr_show', __( 'Hide the “show on” banner', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'The banner then only shows when you cancel a night (and in the off season).', 'ridgeroad' ) ) );
	$add( 'cancel_title', 'rr_show', __( 'Banner title — canceled', 'ridgeroad' ), 'text', array( 'description' => __( 'Cancel the show from Show Control (Cancel tonight’s show). Its message replaces the text below when filled in.', 'ridgeroad' ) ) );
	$add( 'cancel_text', 'rr_show', __( 'Banner text — canceled', 'ridgeroad' ), 'textarea' );
	$add( 'off_title', 'rr_show', __( 'Banner title — off season', 'ridgeroad' ) );
	$add( 'off_text', 'rr_show', __( 'Banner text — off season', 'ridgeroad' ), 'textarea' );

	$add( 'show_countdown', 'rr_show', __( 'Show the countdown clocks', 'ridgeroad' ), 'checkbox' );
	$add( 'count_to', 'rr_show', __( 'Count down to', 'ridgeroad' ), 'select', array( 'choices' => array( 'auto' => __( 'Automatic (Halloween if your last night is in August to October, otherwise Christmas)', 'ridgeroad' ), 'christmas' => __( 'Christmas', 'ridgeroad' ), 'halloween' => __( 'Halloween', 'ridgeroad' ), 'custom' => __( 'A date I pick', 'ridgeroad' ) ) ) );
	$add( 'count_name', 'rr_show', __( 'Your date’s name (like New Year’s Eve)', 'ridgeroad' ), 'text', array( 'description' => __( 'For “A date I pick”.', 'ridgeroad' ) ) );
	$add( 'count_date', 'rr_show', __( 'Your date (the year doesn’t matter; it repeats every year)', 'ridgeroad' ), 'date', array( 'description' => __( 'For “A date I pick”.', 'ridgeroad' ) ) );
	$add( 'rf_viewer_url', 'rr_show', __( 'Remote Falcon viewer page (adds “Pick the next song” to Tonight’s Show)', 'ridgeroad' ), 'url', array( 'description' => __( 'Like https://yourshow.remotefalcon.com. Visitors pick songs right on your website while the show is running (your Remote Falcon rules still apply). Leave empty to hide it.', 'ridgeroad' ) ) );
	$add( 'show_playlist', 'rr_show', __( 'Show the playlist section', 'ridgeroad' ), 'checkbox' );
	$add( 'playlist_when', 'rr_show', __( 'When to show the playlist', 'ridgeroad' ), 'select', array( 'choices' => array( 'running' => __( 'Only while the show is running', 'ridgeroad' ), 'season' => __( 'All season (hide off-season and canceled nights)', 'ridgeroad' ), 'always' => __( 'Always', 'ridgeroad' ) ) ) );
	$add( 'playlist_title', 'rr_show', __( 'Playlist title', 'ridgeroad' ) );
	$add( 'address', 'rr_show', __( 'Show address (for the Get Directions button)', 'ridgeroad' ), 'text', array( 'description' => __( 'A street address (123 Main St, Springfield, IL 62701), map coordinates (39.7817, -89.6501) or a Google Maps link. Coordinates take visitors to the exact spot. Leave empty to hide the button.', 'ridgeroad' ) ) );
	$add( 'parking_note', 'rr_show', __( 'Parking note next to the button', 'ridgeroad' ) );

	// Weather.
	$add( 'city', 'rr_weather', __( 'City name', 'ridgeroad' ) );
	$add( 'zip', 'rr_weather', __( 'ZIP code', 'ridgeroad' ) );
	$add( 'show_radar', 'rr_weather', __( 'Show live radar loop', 'ridgeroad' ), 'checkbox' );
	$add( 'radar_station', 'rr_weather', __( 'NWS radar station', 'ridgeroad' ), 'text', array( 'description' => __( 'KHTX = Huntsville. Use KBMX for Birmingham.', 'ridgeroad' ) ) );

	// Live camera.
	$add( 'live_url', 'rr_live', __( 'YouTube channel or live video link', 'ridgeroad' ), 'text', array( 'description' => __( 'Paste your channel link (youtube.com/channel/UC…) or channel ID — the section then always shows whatever you are streaming. A single live video link also works. Leave empty to hide the section.', 'ridgeroad' ) ) );
	$add( 'live_title', 'rr_live', __( 'Section title', 'ridgeroad' ) );
	$add( 'live_text', 'rr_live', __( 'Text under the title', 'ridgeroad' ), 'textarea' );

	// Sequence videos.
	$add( 'seq_show', 'rr_seq', __( 'Show the show videos (on the Songs & Videos page)', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'Add your MP4 videos in WP Admin → My Sequences. They appear on the Songs & Videos page (yoursite.com/songs/), not the home page.', 'ridgeroad' ) ) );
	$add( 'seq_title', 'rr_seq', __( 'Section title', 'ridgeroad' ) );
	$add( 'seq_text', 'rr_seq', __( 'Line under the title', 'ridgeroad' ), 'textarea' );

	// Favorite song voting.
	$add( 'songs_show', 'rr_songs', __( 'Show favorite song voting (on the Songs & Videos page)', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'See the votes on your WordPress Dashboard. You can clear all votes there to start a new season.', 'ridgeroad' ) ) );
	$add( 'songs_title', 'rr_songs', __( 'Section title', 'ridgeroad' ) );
	$add( 'songs_text', 'rr_songs', __( 'Line under the title', 'ridgeroad' ), 'textarea' );
	$add( 'songs_source', 'rr_songs', __( 'Where the song list comes from', 'ridgeroad' ), 'select', array( 'choices' => array(
		'rf'     => __( 'Remote Falcon (automatic, uses your sequence display names)', 'ridgeroad' ),
		'manual' => __( 'My own list (below)', 'ridgeroad' ),
	) ) );
	$add( 'songs_manual', 'rr_songs', __( 'My song list', 'ridgeroad' ), 'textarea', array( 'description' => __( 'One song per line, like: Wizards in Winter — Trans-Siberian Orchestra. Start a line with * to mark it NEW this year.', 'ridgeroad' ) ) );
	$add( 'songs_friday', 'rr_songs', __( 'Fan Favorite Friday (this week’s top song is featured every Friday)', 'ridgeroad' ), 'checkbox' );
	$add( 'songs_friday_text', 'rr_songs', __( 'Fan Favorite Friday message', 'ridgeroad' ) );

	// Kids.
	$add( 'kids_show', 'rr_kids', __( 'Show the Kids’ Corner', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'Turn each part on or off below.', 'ridgeroad' ) ) );
	$add( 'coloring_on', 'rr_kids', __( 'Coloring Pages', 'ridgeroad' ), 'checkbox' );
	$add( 'games_on', 'rr_kids', __( 'Games (elf names and Christmas trivia)', 'ridgeroad' ), 'checkbox' );
	$add( 'lab_on', 'rr_kids', __( 'Make a Light Show (the Light Lab) in the Kids’ Corner', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'Off hides it and the light show contest; the rest of the Kids’ Corner stays.', 'ridgeroad' ) ) );
	$add( 'kids_title', 'rr_kids', __( 'Section title', 'ridgeroad' ) );
	$add( 'names_on', 'rr_kids', __( '“Your Name in Lights”: kids send their name to your show’s screens (needs the FPP script)', 'ridgeroad' ), 'checkbox', array(
		'description' => sprintf(
			/* translators: 1: site address, 2: key */
			__( 'Put these two lines at the top of the FPP script: SITE = “%1$s” and KEY = “%2$s”. Keep the key private.', 'ridgeroad' ),
			home_url( '/' ),
			ridgeroad_names_key()
		),
	) );
	$add( 'names_always', 'rr_kids', __( 'Accept names any time (for testing). Normally names are only accepted while the show is running.', 'ridgeroad' ), 'checkbox' );
	$add( 'names_geo', 'rr_kids', __( 'Only at the show: names can only be sent from phones near your show (the phone shares its location; it is never saved)', 'ridgeroad' ), 'checkbox' );
	$add( 'names_geo_miles', 'rr_kids', __( 'How close they must be (miles from the show)', 'ridgeroad' ), 'miles', array( 'type' => 'number', 'input_attrs' => array( 'min' => 0.1, 'max' => 50, 'step' => 0.1 ), 'description' => __( '0.5 works for most shows. Phones can be off by a few hundred feet, so don’t go much smaller.', 'ridgeroad' ) ) );
	$add( 'names_color', 'rr_kids', __( 'Name text color on the screens (like #FFD45C)', 'ridgeroad' ) );
	$add( 'names_block', 'rr_kids', __( 'Extra names or words to block (comma separated)', 'ridgeroad' ), 'textarea' );
	$add( 'contest_on', 'rr_kids', __( 'Run the light show contest (entries, Show Wall and hearts)', 'ridgeroad' ), 'checkbox', array( 'description' => __( 'Review entries under Kids’ Light Shows in the WordPress menu. Nothing shows on the Show Wall until you publish it.', 'ridgeroad' ) ) );
	$add( 'wall_home', 'rr_kids', __( 'Show the newest kids’ shows on the home page (when the contest is on)', 'ridgeroad' ), 'checkbox' );
	$add( 'contest_deadline', 'rr_kids', __( 'Last day to enter and vote', 'ridgeroad' ), 'date' );
	$add( 'contest_prize1', 'rr_kids', __( 'Judges’ Pick prize', 'ridgeroad' ) );
	$add( 'contest_prize2', 'rr_kids', __( 'People’s Choice prize', 'ridgeroad' ) );
	$add( 'contest_pickup', 'rr_kids', __( 'Winners pick up their prize by', 'ridgeroad' ), 'date' );
	$add( 'contest_notify', 'rr_kids', __( 'Email new entries to (empty = site admin email)', 'ridgeroad' ), 'email' );

	// Stats.
	$add( 'stats_show', 'rr_stats', __( 'Show the Behind the Lights section', 'ridgeroad' ), 'checkbox' );
	$add( 'stats_title', 'rr_stats', __( 'Section title', 'ridgeroad' ) );
	$add( 'stats_text', 'rr_stats', __( 'Line under the title', 'ridgeroad' ) );
	for ( $i = 1; $i <= 6; $i++ ) {
		/* translators: %d: stat number */
		$add( "stat{$i}_num", 'rr_stats', sprintf( __( 'Stat %d number', 'ridgeroad' ), $i ), 'text', 3 === $i ? array( 'description' => __( 'Leave empty to count the songs from Remote Falcon automatically.', 'ridgeroad' ) ) : array() );
		/* translators: %d: stat number */
		$add( "stat{$i}_label", 'rr_stats', sprintf( __( 'Stat %d label (empty hides it)', 'ridgeroad' ), $i ) );
		/* translators: %d: stat number */
		$add( "stat{$i}_icon", 'rr_stats', sprintf( __( 'Stat %d icon', 'ridgeroad' ), $i ), 'select', array( 'choices' => ridgeroad_icon_choices() ) );
	}

	// Tips.
	$add( 'tips_title', 'rr_tips', __( 'Section title', 'ridgeroad' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: tip number */
		$add( "tip{$i}_icon", 'rr_tips', sprintf( __( 'Tip %d icon', 'ridgeroad' ), $i ), 'select', array( 'choices' => ridgeroad_icon_choices() ) );
		/* translators: %d: tip number */
		$add( "tip{$i}_title", 'rr_tips', sprintf( __( 'Tip %d title', 'ridgeroad' ), $i ) );
		/* translators: %d: tip number */
		$add( "tip{$i}_text", 'rr_tips', sprintf( __( 'Tip %d text', 'ridgeroad' ), $i ), 'textarea' );
	}

	// Support.
	$add( 'donate_title', 'rr_support', __( 'Donation title', 'ridgeroad' ) );
	$add( 'donate_text', 'rr_support', __( 'Donation text', 'ridgeroad' ), 'textarea' );
	$add( 'cashapp_url', 'rr_support', __( 'Cash App link', 'ridgeroad' ), 'url', array( 'description' => __( 'e.g. https://cash.app/$YourCashtag — leave empty to hide the donation box.', 'ridgeroad' ) ) );
	$add( 'cashapp_label', 'rr_support', __( 'Button text', 'ridgeroad' ) );
	$image( 'cashapp_qr', 'rr_support', __( 'Cash App QR image (optional)', 'ridgeroad' ), __( 'If empty, a QR code is generated from the link automatically.', 'ridgeroad' ) );
	$add( 'social_title', 'rr_support', __( 'Social title', 'ridgeroad' ) );
	$add( 'social_text', 'rr_support', __( 'Social text', 'ridgeroad' ), 'textarea' );
	$add( 'social_hashtag', 'rr_support', __( 'Hashtag for visitor photos (leave empty to hide)', 'ridgeroad' ) );
	$add( 'social_share', 'rr_support', __( 'Show a “Share the Show” button', 'ridgeroad' ), 'checkbox' );
	foreach ( ridgeroad_social_networks() as $net => $n ) {
		$add( $net . '_show', 'rr_support', sprintf( __( 'Show the %s button', 'ridgeroad' ), $n[0] ), 'checkbox' );
		$add( $net . '_url', 'rr_support', sprintf( __( '%s link', 'ridgeroad' ), $n[0] ), 'url' );
	}
	$add( 'guestbook_url', 'rr_support', __( 'Guestbook link (menu)', 'ridgeroad' ), 'url', array( 'description' => __( 'Leave empty to jump to the guestbook section on the home page.', 'ridgeroad' ) ) );
	$add( 'gb_show', 'rr_guestbook', __( 'Show the guestbook section', 'ridgeroad' ), 'checkbox' );
	$add( 'gb_source', 'rr_guestbook', __( 'Where messages are kept', 'ridgeroad' ), 'select', array(
		'choices'     => array( 'wordpress' => __( 'WordPress (I approve every message first)', 'ridgeroad' ), 'rfpb' => __( 'RF Page Builder (posts right away)', 'ridgeroad' ) ),
		'description' => __( 'With WordPress, new messages wait in Comments → Pending until you approve them.', 'ridgeroad' ),
	) );
	$add( 'gb_eyebrow', 'rr_guestbook', __( 'Small line above the title', 'ridgeroad' ) );
	$add( 'gb_title', 'rr_guestbook', __( 'Title', 'ridgeroad' ) );
	$add( 'gb_intro', 'rr_guestbook', __( 'Intro text', 'ridgeroad' ), 'textarea' );
	$add( 'gb_count', 'rr_guestbook', __( 'Messages to show', 'ridgeroad' ), 'number', array( 'input_attrs' => array( 'min' => 1, 'max' => 20 ) ) );
	$add( 'gb_site_key', 'rr_guestbook', __( 'RF Page Builder site key', 'ridgeroad' ), 'text', array( 'description' => __( 'From RF Page Builder: a key with dashes, like 1a2b3c4d-…. Leave empty to turn the guestbook off.', 'ridgeroad' ) ) );
	$add( 'footer_text', 'rr_support', __( 'Footer text (optional)', 'ridgeroad' ) );
} );

/** Miles for "Only at the show" (0.1 to 50, one decimal). */
if ( ! function_exists( 'ridgeroad_sanitize_miles' ) ) {
	function ridgeroad_sanitize_miles( $v ) {
		$v = (float) str_replace( ',', '.', (string) $v );
		return (string) round( max( 0.1, min( 50.0, $v > 0 ? $v : 0.5 ) ), 1 );
	}
}
