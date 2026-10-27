<?php
/**
 * Small helpers the show sections use. The festive theme and the Marquee plugin
 * both ship this file; whichever loads first defines them (identical code).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ridgeroad_part' ) ) {
	/**
	 * Draw one shared section (parts/<name>.php): from Marquee when it is active
	 * and has the part, else from the festive theme.
	 */
	function ridgeroad_part( $name, $args = array() ) {
		$name = preg_replace( '/[^a-z0-9-]/', '', strtolower( $name ) );
		$file = '';
		if ( defined( 'RR_FEAT_DIR' ) && is_file( RR_FEAT_DIR . 'parts/' . $name . '.php' ) ) {
			$file = RR_FEAT_DIR . 'parts/' . $name . '.php';
		} elseif ( is_file( get_template_directory() . '/parts/' . $name . '.php' ) ) {
			$file = get_template_directory() . '/parts/' . $name . '.php';
		}
		if ( ! $file ) {
			return;
		}
		( static function () use ( $file, $args ) {
			include $file;
		} )();
	}
}

if ( ! function_exists( 'ridgeroad_asset_url' ) ) {
	/** URL of a show picture/sound (assets/<path>): Marquee's copy when it has one, else the theme's. */
	function ridgeroad_asset_url( $path ) {
		$clean = rtrim( strtok( $path, '?' ), '/' );
		if ( defined( 'RR_FEAT_DIR' ) && file_exists( RR_FEAT_DIR . 'assets/' . $clean ) ) {
			return RR_FEAT_URL . 'assets/' . $path;
		}
		return get_template_directory_uri() . '/assets/' . $path;
	}
}

if ( ! function_exists( 'ridgeroad_home_ctx' ) ) {
	/** Everything the home page sections share, worked out once per page. */
	function ridgeroad_home_ctx() {
		static $c = null;
		if ( null !== $c ) {
			return $c;
		}
		$status = ridgeroad_status();
		$np     = ridgeroad_now_playing_for( $status );
		$banner = ridgeroad_banner( $status );
		$np_idle = array(
			'running'   => __( 'Between songs — stay tuned!', 'ridgeroad' ),
			'upcoming'  => $status['note'],
			'ended'     => $status['note'],
			'offseason' => $status['note'],
			'canceled'  => __( 'No show tonight', 'ridgeroad' ),
		);
		$np_text = $np['title'] ? $np['title'] : $np_idle[ $status['state'] ];
		if ( ! $np['title'] && empty( $np['connected'] ) && current_user_can( 'manage_options' ) ) {
			$np_text = function_exists( 'marquee_get' )
				? __( 'Not connected — check the Remote Falcon access token and secret key in Marquee → Settings (only admins see this).', 'ridgeroad' )
				: __( 'Not connected — check the Access Token and Secret Key in Settings → Lights on Ridge Road (only admins see this).', 'ridgeroad' );
		}
		$c = array(
			'status'   => $status,
			'np'       => $np,
			'wx'       => ridgeroad_weather(),
			'opts'     => ridgeroad_plugin_options(),
			'b_title'  => $banner['title'],
			'b_text'   => $banner['text'],
			'b_mod'    => $banner['mod'],
			'np_text'  => $np_text,
			'station'  => strtoupper( preg_replace( '/[^A-Za-z]/', '', ridgeroad_mod( 'radar_station' ) ) ),
			'forecast' => sprintf( 'https://forecast.weather.gov/MapClick.php?lat=%s&lon=%s', RIDGEROAD_LAT, RIDGEROAD_LON ),
		);
		return $c;
	}
}

if ( ! function_exists( 'ridgeroad_clock' ) ) {
	/** "22:00" -> "10 PM", "22:30" -> "10:30 PM" (no timezone shifting). */
	function ridgeroad_clock( $hm ) {
		$t = DateTime::createFromFormat( '!H:i', $hm );
		if ( ! $t ) {
			return $hm;
		}
		return $t->format( '00' === $t->format( 'i' ) ? __( 'g A', 'ridgeroad' ) : __( 'g:i A', 'ridgeroad' ) );
	}
}

if ( ! function_exists( 'ridgeroad_guestbook_url' ) ) {
	/** Guestbook link: Customizer setting, else a page at /guestbook/. */
	function ridgeroad_guestbook_url() {
		$u = ridgeroad_mod( 'guestbook_url' );
		if ( $u ) {
			return $u;
		}
		if ( ridgeroad_mod( 'gb_show' ) && ridgeroad_mod( 'gb_site_key' ) ) {
			return home_url( '/#guestbook' );
		}
		$page = get_page_by_path( 'guestbook' );
		return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	}
}

if ( ! function_exists( 'ridgeroad_gb_token' ) ) {
	/** Guestbook token: from the Lights on Ridge Road plugin settings. */
	function ridgeroad_gb_token() {
		$o = ridgeroad_plugin_options();
		if ( ! empty( $o['gb_token'] ) ) {
			return $o['gb_token'];
		}
		return (string) apply_filters( 'ridgeroad_gb_token_default', '' );
	}
}

if ( ! function_exists( 'ridgeroad_is_marquee' ) ) {
	/** True when the Marquee plugin runs the show (any show), false on Lights On Ridge Road's own site. */
	function ridgeroad_is_marquee() {
		return function_exists( 'marquee_get' );
	}
}

if ( ! function_exists( 'ridgeroad_coloring_pages' ) ) {
	/**
	 * Coloring pages for the Kids' Corner: key => title, thumb, print link, portrait.
	 * Marquee shows: the standard pages (no Lights On Ridge Road house), plus their own
	 * house page and extra pages from Marquee → Settings → Artwork, printed with their name/logo.
	 */
	function ridgeroad_coloring_pages() {
		$dir   = ridgeroad_asset_url( 'coloring/' );
		$std   = ridgeroad_std_coloring();
		$mq    = ridgeroad_is_marquee();
		$hide  = $mq ? (array) marquee_get( 'art_hide' ) : array();
		$extra = '';
		if ( $mq ) {
			$logo  = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium_large' ) : '';
			$extra = '&n=' . rawurlencode( get_bloginfo( 'name' ) ) . ( $logo ? '&l=' . rawurlencode( $logo ) : '' );
		}
		// the page's words in the visitor's language, and the way back to the coloring pages
		$extra .= '&hl=' . rawurlencode( substr( determine_locale(), 0, 2 ) );
		if ( function_exists( 'ridgeroad_kids_url' ) && ridgeroad_kids_url() ) {
			$extra .= '&b=' . rawurlencode( ridgeroad_kids_url() . '#coloring' );
		}
		$out    = array();
		$upload = function ( $id, $title ) use ( &$out, $dir, $extra ) {
			$full = wp_get_attachment_image_url( $id, 'full' );
			if ( ! $full ) {
				return;
			}
			$meta  = wp_get_attachment_metadata( $id );
			$port  = ! empty( $meta['height'] ) && ! empty( $meta['width'] ) && $meta['height'] > $meta['width'];
			$title = '' !== $title ? $title : get_the_title( $id );
			$thumb = wp_get_attachment_image_url( $id, 'medium_large' );
			$out[ 'u' . $id ] = array(
				'title' => $title,
				'thumb' => $thumb ? $thumb : $full,
				'print' => $dir . 'print.html?img=' . rawurlencode( $full ) . '&o=' . ( $port ? 'port' : 'land' ) . '&t=' . rawurlencode( $title ) . $extra,
				'port'  => $port,
			);
		};
		if ( $mq && (int) marquee_get( 'art_house' ) ) {
			$upload( (int) marquee_get( 'art_house' ), __( 'Our light show', 'ridgeroad' ) );
		}
		foreach ( $std as $slug => $title ) {
			if ( $mq && 'house' === $slug ) {
				continue; // that drawing is Lights On Ridge Road's own house
			}
			if ( in_array( $slug, $hide, true ) ) {
				continue; // turned off in Marquee → Settings → Artwork
			}
			$out[ $slug ] = array(
				'title' => $title,
				'thumb' => $dir . $slug . '-thumb.webp?v=' . RIDGEROAD_VERSION,
				'print' => $dir . 'print.html?p=' . $slug . '&v=' . RIDGEROAD_VERSION . $extra,
				'port'  => 0 === strpos( $slug, 'santa' ),
			);
		}
		if ( $mq ) {
			foreach ( (array) marquee_get( 'art_pages' ) as $id ) {
				$upload( (int) $id, '' );
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'ridgeroad_door_words' ) ) {
	/**
	 * Kids' Corner doors: the built-in pictures have English words painted on. On a site in another
	 * language, use the same pictures without words and write the words on top as page text.
	 * (A door picture the owner uploaded is used as is.)
	 */
	function ridgeroad_door_words( $key ) {
		if ( 0 === strpos( determine_locale(), 'en' ) ) {
			return false;
		}
		if ( ridgeroad_is_marquee() ) {
			$d = (array) marquee_get( 'art_doors' );
			if ( ! empty( $d[ $key ] ) && wp_get_attachment_image_url( (int) $d[ $key ], 'large' ) ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'ridgeroad_door_title' ) ) {
	/** A door's title on the picture; long ones get smaller letters so they fit above the button. */
	function ridgeroad_door_title( $text ) {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		printf( '<b%s>%s</b>', $len > 26 ? ' class="is-longer"' : ( $len > 17 ? ' class="is-long"' : '' ), esc_html( $text ) );
	}
}

if ( ! function_exists( 'ridgeroad_door_url' ) ) {
	/** Kids' Corner door picture: the show's own upload (Marquee → Artwork), else the standard one. */
	function ridgeroad_door_url( $key, $file ) {
		if ( ridgeroad_door_words( $key ) ) {
			return ridgeroad_asset_url( 'img/kids/' ) . str_replace( '.jpg', '-blank.jpg', $file ) . '?v=' . RIDGEROAD_VERSION;
		}
		if ( ridgeroad_is_marquee() ) {
			$d = (array) marquee_get( 'art_doors' );
			if ( ! empty( $d[ $key ] ) ) {
				$u = wp_get_attachment_image_url( (int) $d[ $key ], 'large' );
				if ( $u ) {
					return $u;
				}
			}
		}
		return ridgeroad_asset_url( 'img/kids/' ) . $file . '?v=' . RIDGEROAD_VERSION;
	}
}

/** Social networks the show can link to: key => array( label, button class ). */
if ( ! function_exists( 'ridgeroad_social_networks' ) ) {
	function ridgeroad_social_networks() {
		return array(
			'facebook'  => array( 'Facebook', 'fb' ),
			'instagram' => array( 'Instagram', 'ig' ),
			'youtube'   => array( 'YouTube', 'yt' ),
			'tiktok'    => array( 'TikTok', 'tt' ),
		);
	}
}

/** The social links to show: turned on and with a link. */
if ( ! function_exists( 'ridgeroad_social_links' ) ) {
	function ridgeroad_social_links() {
		$out = array();
		foreach ( ridgeroad_social_networks() as $net => $n ) {
			$url = (string) ridgeroad_mod( $net . '_url' );
			if ( '' !== $url && ridgeroad_mod( $net . '_show' ) ) {
				$out[ $net ] = array( 'label' => $n[0], 'class' => $n[1], 'url' => $url );
			}
		}
		return $out;
	}
}

/**
 * Windy.com weather for the show's location, used outside the US (where the National
 * Weather Service radar and forecast pages don't exist). $embed = the radar map for an iframe.
 */
if ( ! function_exists( 'ridgeroad_windy_url' ) ) {
	function ridgeroad_windy_url( $embed = false ) {
		$lat    = round( (float) RIDGEROAD_LAT, 4 );
		$lon    = round( (float) RIDGEROAD_LON, 4 );
		$metric = function_exists( 'ridgeroad_metric' ) ? ridgeroad_metric() : false;
		if ( ! $embed ) {
			return 'https://www.windy.com/' . $lat . '/' . $lon . '?' . $lat . ',' . $lon . ',9';
		}
		return add_query_arg( array(
			'lat'        => $lat,
			'lon'        => $lon,
			'detailLat'  => $lat,
			'detailLon'  => $lon,
			'zoom'       => 8,
			'level'      => 'surface',
			'overlay'    => 'radar',
			'menu'       => '',
			'message'    => 'true',
			'marker'     => 'true',
			'calendar'   => 'now',
			'pressure'   => '',
			'type'       => 'map',
			'location'   => 'coordinates',
			'detail'     => '',
			'metricWind' => $metric ? 'km/h' : 'mph',
			'metricTemp' => $metric ? '°C' : '°F',
			'radarRange' => '-1',
		), 'https://embed.windy.com/embed2.html' );
	}
}

if ( ! function_exists( 'ridgeroad_std_coloring' ) ) {
	/** The coloring pages that come with Marquee: slug => title. */
	function ridgeroad_std_coloring() {
		return array(
			'house'           => __( 'Our light show', 'ridgeroad' ),
			'snowman'         => __( 'Snowman', 'ridgeroad' ),
			'reindeer'        => __( 'Reindeer', 'ridgeroad' ),
			'gingerbread'     => __( 'Gingerbread', 'ridgeroad' ),
			'merry-christmas' => __( 'Snowman friends', 'ridgeroad' ),
			'santa-tree'      => __( 'Santa’s tree', 'ridgeroad' ),
			'santa-cookies'   => __( 'Santa’s cookies', 'ridgeroad' ),
		);
	}
}
