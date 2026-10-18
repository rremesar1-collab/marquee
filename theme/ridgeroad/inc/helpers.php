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
		return $t->format( '00' === $t->format( 'i' ) ? 'g A' : 'g:i A' );
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
		$dir = ridgeroad_asset_url( 'coloring/' );
		$std = array(
			'house'           => __( 'Our light show', 'ridgeroad' ),
			'snowman'         => __( 'Snowman', 'ridgeroad' ),
			'reindeer'        => __( 'Reindeer', 'ridgeroad' ),
			'gingerbread'     => __( 'Gingerbread', 'ridgeroad' ),
			'merry-christmas' => __( 'Snowman friends', 'ridgeroad' ),
			'santa-tree'      => __( 'Santa’s tree', 'ridgeroad' ),
			'santa-cookies'   => __( 'Santa’s cookies', 'ridgeroad' ),
		);
		$mq    = ridgeroad_is_marquee();
		$extra = '';
		if ( $mq ) {
			$logo  = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium_large' ) : '';
			$extra = '&n=' . rawurlencode( get_bloginfo( 'name' ) ) . ( $logo ? '&l=' . rawurlencode( $logo ) : '' );
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

if ( ! function_exists( 'ridgeroad_door_url' ) ) {
	/** Kids' Corner door picture: the show's own upload (Marquee → Artwork), else the standard one. */
	function ridgeroad_door_url( $key, $file ) {
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
