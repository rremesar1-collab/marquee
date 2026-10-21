<?php
/**
 * Appearance → Customize → "Ridge Road Show" settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_defaults() {
	static $memo = null;
	if ( null !== $memo && ! is_customize_preview() ) {
		return $memo;
	}
	$d = array(
		// Header.
		'subtitle'          => '',
		'tagline'           => 'A Little Christmas Magic',
		'intro'             => 'Thousands of lights dancing to holiday music. Tune your car radio, sit back, and enjoy the show!',
		'bg_image'          => '',
		'snow_amount'       => 'medium',
		'snow_size'         => 'medium',
		'snow_real'         => 1,
		// Show.
		'radio'             => '',
		'season_start'      => '2026-11-27',
		'season_end'        => '2027-01-01',
		'weeknight_label'   => 'Sun – Thu',
		'weeknight_close'   => '22:00',
		'weekend_label'     => 'Fri – Sat',
		'weekend_close'     => '23:00',
		'start_offset'      => 0,
		'use_sunset'        => 0,
		'weeknight_start'   => '17:30',
		'weekend_start'     => '17:30',
		'ok_title'          => 'Show Planned as Scheduled',
		'ok_text'           => 'No weather cancellation announced. Please check back before traveling during severe weather.',
		'cancel_title'      => 'Tonight’s Show Is Canceled',
		'cancel_text'       => 'Due to weather, the light show will not run tonight. Thank you for understanding — please check back tomorrow!',
		'off_title'         => 'See You This Christmas Season',
		'off_text'          => 'The show is resting for now. Check back for opening night!',
		// Countdown, playlist, directions.
		'show_countdown'    => 1,
		'show_playlist'     => 1,
		'playlist_when'     => 'running',
		'playlist_title'    => 'Tonight’s Playlist',
		'address'           => '',
		'parking_note'      => 'Please park on the shoulder and keep the road clear.',
		// Stats.
		'wall_home'         => 1,
		'stats_show'        => 1,
		'stats_title'       => 'Behind the Lights',
		'stats_text'        => 'Every song is programmed by hand, one light at a time.',
		'stat1_icon' => 'bulb',  'stat1_num' => '211,265', 'stat1_label' => 'LED Pixels',
		'stat2_icon' => 'tree',  'stat2_num' => '52',      'stat2_label' => 'Light-Up Props',
		'stat3_icon' => 'music', 'stat3_num' => '',        'stat3_label' => 'Songs in the Show',
		'stat4_icon' => 'star',  'stat4_num' => '1.7M',    'stat4_label' => 'Light Channels',
		'stat5_icon' => 'camera','stat5_num' => '5',       'stat5_label' => 'Video Panels',
		'stat6_icon' => 'radio', 'stat6_num' => '90.5',    'stat6_label' => 'FM — Tune In!',
		// Kids' corner.
		'kids_show'         => 1,
		'lab_on'            => 1,
		'kids_title'        => 'Kids’ Corner',
		// Sequence videos.
		'seq_show'          => 1,
		'seq_title'         => 'This Year’s Show Videos',
		'seq_text'          => 'Watch every song from this year’s show, then come see it in person!',
		// Favorite song voting.
		'songs_show'        => 1,
		'songs_title'       => 'Vote for Your Favorite Song',
		'songs_text'        => 'Tap the heart on your favorite song in this year’s show. You can change your vote anytime!',
		'songs_source'      => 'rf',
		'songs_manual'      => '',
		'songs_friday'      => 1,
		'songs_friday_text' => 'We’ll play it to open the show tonight!',
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
		'contest_prize1'    => '$25 gift card',
		'contest_prize2'    => 'Holiday treat bag',
		'contest_pickup'    => '2026-12-30',
		'contest_notify'    => '',
		// Weather.
		'city'              => '',
		'zip'               => '',
		'show_radar'        => 1,
		'radar_station'     => '',
		// Live camera.
		'live_url'          => '',
		'live_title'        => 'Watch the Show Live',
		'live_text'         => 'Can’t make it out tonight? Watch the lights from home — turn up the volume!',
		// Visitor tips.
		'tips_title'        => 'Before You Visit',
		'tip1_icon'         => 'car',
		'tip1_title'        => 'Park Safely',
		'tip1_text'         => 'Use a legal parking spot and keep the road open for passing traffic.',
		'tip2_icon'         => 'bulb',
		'tip2_title'        => 'Headlights Off',
		'tip2_text'         => 'Once parked, turn off your headlights and leave parking or running lights on.',
		'tip3_icon'         => 'home',
		'tip3_title'        => 'Respect Our Neighbors',
		'tip3_text'         => 'Please don’t block driveways or entrances. Keep noise considerate.',
		'tip4_icon'         => 'camera',
		'tip4_title'        => 'Enjoy the Memories',
		'tip4_text'         => 'Photos and videos are welcome. Stay in a safe spot while watching the show.',
		// Support.
		'donate_title'      => 'Keep the Magic Glowing',
		'donate_text'       => 'Donations help maintain our synchronized Christmas display and add new surprises. They’re never required to enjoy the show.',
		'cashapp_url'       => '',
		'cashapp_label'     => 'Support via Cash App',
		'cashapp_qr'        => '',
		'share_image'       => '',
		'social_title'      => 'Stay Connected',
		'social_text'       => 'Follow us for weather updates, new songs, and show announcements.',
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
		'gb_eyebrow'        => 'A message from your family to ours',
		'gb_title'          => 'Leave a Little Christmas Cheer',
		'gb_intro'          => 'Enjoyed the lights? We’d love to hear from you! Sign our guestbook and share a holiday message with our family.',
		'gb_count'          => 5,
		'footer_text'       => '',
	);
	// Marquee (any show): no Lights On Ridge Road details as defaults.
	if ( function_exists( 'marquee_get' ) ) {
		$m    = marquee_get();
		$town = trim( $m['city'] . ( $m['region'] ? ', ' . $m['region'] : '' ) );
		$yr   = wp_date( 'n' ) >= 7 ? wp_date( 'Y' ) : (string) ( (int) wp_date( 'Y' ) - 1 );
		$d    = array_merge( $d, array(
			'subtitle'       => trim( ( $town ? $town . ' • ' : '' ) . 'Christmas ' . $yr ),
			'radio'          => '',
			'city'           => $town,
			'zip'            => $m['zip'],
			'radar_station'  => '',
			'cashapp_url'    => '',
			'cashapp_label'  => 'Donate',
			'donate_text'    => 'Donations help keep the lights on and add new surprises each year. They’re never required to enjoy the show.',
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
	$memo = $d;
	return $d;
}

function ridgeroad_mod( $key ) {
	$d = ridgeroad_defaults();
	return get_theme_mod( $key, isset( $d[ $key ] ) ? $d[ $key ] : '' );
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
	$image( 'bg_image', 'rr_header', __( 'Background photo (optional)', 'ridgeroad' ), __( 'Replaces the built-in night forest. A wide night photo works best (2400px+).', 'ridgeroad' ) );

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
	$add( 'cancel_title', 'rr_show', __( 'Banner title — canceled', 'ridgeroad' ), 'text', array( 'description' => __( 'Cancel the show from Show Control (Cancel tonight’s show). Its message replaces the text below when filled in.', 'ridgeroad' ) ) );
	$add( 'cancel_text', 'rr_show', __( 'Banner text — canceled', 'ridgeroad' ), 'textarea' );
	$add( 'off_title', 'rr_show', __( 'Banner title — off season', 'ridgeroad' ) );
	$add( 'off_text', 'rr_show', __( 'Banner text — off season', 'ridgeroad' ), 'textarea' );

	$add( 'show_countdown', 'rr_show', __( 'Show the countdown clocks', 'ridgeroad' ), 'checkbox' );
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
	$add( 'kids_show', 'rr_kids', __( 'Show the Kids’ Corner (elf names, coloring page, trivia)', 'ridgeroad' ), 'checkbox' );
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
