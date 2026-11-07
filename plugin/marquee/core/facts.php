<?php
/**
 * Show Facts: a page about the show. Three parts, each with its own switch:
 *  - your show: a photo of the house with a headline, and your numbers (Behind the Lights) counting up
 *  - Did You Know?: fact cards (a few big words and a line)
 *  - Fan Favorites: the most-voted songs year by year, each with a light string as long as its votes
 * Shared: the festive theme draws it on its own page; Marquee draws it as a block/shortcode in other themes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Icons a fact card can use. */
function ridgeroad_fact_icons() {
	return array(
		'star'   => __( 'Star', 'ridgeroad' ),
		'bulb'   => __( 'Light bulb', 'ridgeroad' ),
		'tree'   => __( 'Tree', 'ridgeroad' ),
		'music'  => __( 'Music', 'ridgeroad' ),
		'clock'  => __( 'Clock', 'ridgeroad' ),
		'home'   => __( 'House', 'ridgeroad' ),
		'car'    => __( 'Car', 'ridgeroad' ),
		'heart'  => __( 'Heart', 'ridgeroad' ),
		'gift'   => __( 'Present', 'ridgeroad' ),
		'radio'  => __( 'Radio', 'ridgeroad' ),
		'camera' => __( 'Camera', 'ridgeroad' ),
		'cloud'  => __( 'Weather', 'ridgeroad' ),
	);
}

/** Page styles: Photo (a big photo of the show with the numbers under it) or Boxes (number boxes and small fact cards). */
function ridgeroad_facts_styles() {
	return array(
		'photo' => __( 'Photo', 'ridgeroad' ),
		'boxes' => __( 'Boxes', 'ridgeroad' ),
	);
}
function ridgeroad_facts_style() {
	$s = (string) ridgeroad_mod( 'facts_style' );
	return isset( ridgeroad_facts_styles()[ $s ] ) ? $s : 'photo';
}

/** The fact cards: rows of icon, big words and a line. */
function ridgeroad_facts() {
	$out  = array();
	$lang = function_exists( 'ridgeroad_lang_now' ) ? ridgeroad_lang_now() : ''; // a visitor who picked another language sees its words, when typed
	foreach ( (array) ridgeroad_mod( 'facts' ) as $r ) {
		if ( ! is_array( $r ) || ( '' === trim( (string) ( $r['big'] ?? '' ) ) && '' === trim( (string) ( $r['text'] ?? '' ) ) ) ) {
			continue;
		}
		$big  = (string) ( $r['big'] ?? '' );
		$text = (string) ( $r['text'] ?? '' );
		if ( $lang && '' !== trim( (string) ( $r[ 'big_' . $lang ] ?? '' ) . (string) ( $r[ 'text_' . $lang ] ?? '' ) ) ) {
			$big  = (string) ( $r[ 'big_' . $lang ] ?? '' );
			$text = (string) ( $r[ 'text_' . $lang ] ?? '' );
		}
		$out[] = array(
			'icon' => isset( ridgeroad_fact_icons()[ $r['icon'] ?? '' ] ) ? $r['icon'] : 'star',
			'big'  => $big,
			'text' => $text,
		);
	}
	return $out;
}

/** The numbers in the top box: the same ones as Behind the Lights (set once, shown in both places). */
function ridgeroad_fact_numbers() {
	$np  = function_exists( 'ridgeroad_now_playing' ) ? ridgeroad_now_playing() : array();
	$out = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		$label = ridgeroad_mod( "stat{$i}_label" );
		$num   = trim( (string) ridgeroad_mod( "stat{$i}_num" ) );
		if ( 3 === $i && '' === $num && ! empty( $np['playlist'] ) ) {
			$num = (string) count( $np['playlist'] );
		}
		if ( 6 === $i && '' === $num && preg_match( '/\d+(\.\d+)?/', (string) ridgeroad_mod( 'radio' ), $fm ) ) {
			$num = $fm[0];
		}
		if ( $label && '' !== $num ) {
			$out[] = array( 'num' => $num, 'label' => $label, 'icon' => ridgeroad_mod( "stat{$i}_icon" ) );
		}
	}
	return $out;
}

/**
 * Fan Favorites by year, newest first: year => list of array( title, votes ) (votes 0 = typed in by hand).
 * The years with voting come from the votes themselves (each vote keeps its date) and from the seasons
 * saved when votes were cleared; years before voting are typed in under Show Facts.
 */
function ridgeroad_fav_years() {
	$years = array();
	// typed in by hand
	foreach ( array_filter( (array) ridgeroad_mod( 'fav_past' ), 'is_array' ) as $r ) {
		$y = (int) ( $r['year'] ?? 0 );
		if ( $y > 1990 && ! empty( $r['songs'] ) ) {
			$years[ $y ] = array_map( function ( $t ) {
				return array( (string) $t, 0 );
			}, array_slice( (array) $r['songs'], 0, 5 ) );
		}
	}
	// seasons saved when the votes were cleared
	foreach ( (array) get_option( 'ridgeroad_fav_archive', array() ) as $y => $list ) {
		if ( is_array( $list ) && $list ) {
			$years[ (int) $y ] = $list;
		}
	}
	// this season's votes, by the year each vote was made
	foreach ( ridgeroad_fav_from_votes() as $y => $list ) {
		$years[ $y ] = $list;
	}
	krsort( $years );
	return array_slice( $years, 0, 8, true );
}

/** The current votes counted per year: year => top 5 array( title, votes ). */
function ridgeroad_fav_from_votes() {
	$votes = get_option( 'ridgeroad_song_votes', array() );
	if ( ! is_array( $votes ) || ! $votes || ! function_exists( 'ridgeroad_song_list_all' ) ) {
		return array();
	}
	$title = array();
	foreach ( ridgeroad_song_list_all() as $s ) {
		$title[ $s['key'] ] = $s['title'];
	}
	$tz    = function_exists( 'ridgeroad_tz' ) ? ridgeroad_tz() : wp_timezone();
	$count = array();
	foreach ( $votes as $v ) {
		if ( is_array( $v ) && isset( $title[ $v[0] ] ) ) {
			$y = (int) ( new DateTime( '@' . (int) $v[1] ) )->setTimezone( $tz )->format( 'Y' );
			// a season runs into January: votes from early January count for the year before
			if ( (int) ( new DateTime( '@' . (int) $v[1] ) )->setTimezone( $tz )->format( 'n' ) < 3 ) {
				$y--;
			}
			$count[ $y ][ $v[0] ] = ( $count[ $y ][ $v[0] ] ?? 0 ) + 1;
		}
	}
	$out = array();
	foreach ( $count as $y => $c ) {
		arsort( $c );
		$out[ $y ] = array();
		foreach ( array_slice( $c, 0, 5, true ) as $k => $n ) {
			$out[ $y ][] = array( $title[ $k ], (int) $n );
		}
	}
	return $out;
}

/* Before the song votes are cleared for a new season, keep each year's top 5 for Fan Favorites. */
add_action( 'admin_post_rr_reset_song_votes', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$arch = (array) get_option( 'ridgeroad_fav_archive', array() );
	foreach ( ridgeroad_fav_from_votes() as $y => $list ) {
		$arch[ $y ] = $list;
	}
	update_option( 'ridgeroad_fav_archive', $arch, false );
}, 1 );

/** Whether the Show Facts page has anything to show. */
function ridgeroad_facts_on() {
	if ( ! ridgeroad_mod( 'facts_show' ) ) {
		return false;
	}
	return ( ridgeroad_mod( 'facts_hero' ) && ( ridgeroad_fact_numbers() || ridgeroad_mod( 'facts_photo' ) ) )
		|| ( ridgeroad_mod( 'facts_did' ) && ridgeroad_facts() )
		|| ( ridgeroad_mod( 'facts_fav' ) && ridgeroad_fav_years() );
}

/** The Show Facts page (made the first time it's needed). */
function ridgeroad_facts_url() {
	$id = (int) get_option( 'ridgeroad_facts_page' );
	if ( ! $id || 'page' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
		$found = get_page_by_path( 'facts' );
		$id    = $found ? (int) $found->ID : (int) wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'Show Facts',
			'post_name'      => 'facts',
			'post_content'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( $id ) {
			update_option( 'ridgeroad_facts_page', $id, true );
		}
	}
	return $id ? get_permalink( $id ) : '';
}
/* The festive theme draws that page with its own template, even if its address is changed. */
add_filter( 'template_include', function ( $t ) {
	$id = (int) get_option( 'ridgeroad_facts_page' );
	if ( $id && is_page( $id ) ) {
		$k = locate_template( 'page-facts.php' );
		return $k ? $k : $t;
	}
	return $t;
} );
