<?php
/**
 * "Vote for Your Favorite Song": every song in this year's show, one vote per
 * visitor (changeable anytime), a live leaderboard, and Fan Favorite Friday.
 * The voting week runs Saturday → Friday; that week's leader is the Fan Favorite.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Song list: from Remote Falcon (default) or typed in the Customizer. */
/** Songs people can vote for (Marquee shows can leave some out, like an intro). */
function ridgeroad_song_list() {
	$all = ridgeroad_song_list_all();
	if ( ! function_exists( 'marquee_get' ) ) {
		return $all;
	}
	$hide = (array) marquee_get( 'vote_hide' );
	return $hide ? array_values( array_filter( $all, function ( $s ) use ( $hide ) {
		return ! in_array( $s['key'], $hide, true );
	} ) ) : $all;
}

/** Every song in the show (Remote Falcon or the typed list). */
function ridgeroad_song_list_all() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$out = array();
	if ( 'manual' === ridgeroad_mod( 'songs_source' ) && trim( (string) ridgeroad_mod( 'songs_manual' ) ) ) {
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ridgeroad_mod( 'songs_manual' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$new = false;
			if ( '*' === $line[0] ) {
				$new  = true;
				$line = trim( substr( $line, 1 ) );
			}
			$parts = preg_split( '/\s+[—–\-|]\s+/u', $line, 2 );
			$title = trim( $parts[0] );
			if ( '' !== $title ) {
				$out[] = array( 'key' => sanitize_title( $title ), 'title' => $title, 'artist' => isset( $parts[1] ) ? trim( $parts[1] ) : '', 'new' => $new );
			}
		}
	} else {
		$data = ridgeroad_rf_data();
		if ( is_array( $data ) && ! empty( $data['sequences'] ) && is_array( $data['sequences'] ) ) {
			$seqs = $data['sequences'];
			usort( $seqs, function ( $a, $b ) {
				return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
			} );
			foreach ( $seqs as $q ) {
				if ( ! ridgeroad_rf_seq_shown( $q ) ) {
					continue;
				}
				$title = ! empty( $q['displayName'] ) ? $q['displayName'] : trim( str_replace( '_', ' ', preg_replace( '/\.(fseq|xsq|mp3)$/i', '', $q['name'] ) ) );
				$out[] = array( 'key' => sanitize_title( $title ), 'title' => $title, 'artist' => isset( $q['artist'] ) ? (string) $q['artist'] : '', 'new' => false );
			}
			if ( $out ) {
				update_option( 'ridgeroad_songs_cache', $out, false ); // keep the list for nights Remote Falcon is offline
			}
		}
		if ( ! $out ) {
			$c   = get_option( 'ridgeroad_songs_cache' );
			$out = is_array( $c ) ? $c : array();
		}
	}
	$seen = array();
	$memo = array();
	foreach ( $out as $s ) {
		if ( $s['key'] && ! isset( $seen[ $s['key'] ] ) ) {
			$seen[ $s['key'] ] = 1;
			$memo[]            = $s;
		}
	}
	return $memo;
}

/** Start of the current voting week (Saturday 00:00, show time zone). */
function ridgeroad_vote_week_start() {
	$now  = new DateTime( 'now', ridgeroad_tz() );
	$back = ( (int) $now->format( 'w' ) + 1 ) % 7; // days since Saturday
	return ( clone $now )->setTime( 0, 0 )->modify( '-' . $back . ' days' )->getTimestamp();
}

/** All voting numbers: season + week counts, top 5 lists, Friday info. */
function ridgeroad_votes_public() {
	if ( ! ridgeroad_mod( 'songs_show' ) ) {
		return null;
	}
	$list  = ridgeroad_song_list();
	$title = array();
	$order = array();
	foreach ( $list as $i => $s ) {
		$title[ $s['key'] ] = $s['title'];
		$order[ $s['key'] ] = $i;
	}
	$votes  = get_option( 'ridgeroad_song_votes', array() );
	$week0  = ridgeroad_vote_week_start();
	$season = array();
	$week   = array();
	foreach ( (array) $votes as $v ) {
		if ( is_array( $v ) && isset( $title[ $v[0] ] ) ) {
			$season[ $v[0] ] = ( $season[ $v[0] ] ?? 0 ) + 1;
			if ( (int) $v[1] >= $week0 ) {
				$week[ $v[0] ] = ( $week[ $v[0] ] ?? 0 ) + 1;
			}
		}
	}
	$top = function ( $counts ) use ( $title, $order ) {
		$keys = array_keys( $counts );
		usort( $keys, function ( $a, $b ) use ( $counts, $order ) {
			return ( $counts[ $b ] <=> $counts[ $a ] ) ?: ( $order[ $a ] <=> $order[ $b ] );
		} );
		$o = array();
		foreach ( array_slice( $keys, 0, 5 ) as $k ) {
			$o[] = array( 'key' => $k, 'title' => $title[ $k ], 'votes' => (int) $counts[ $k ] );
		}
		return $o;
	};
	$dow = (int) ( new DateTime( 'now', ridgeroad_tz() ) )->format( 'w' );
	$wk  = $top( $week );
	return array(
		'season'    => $top( $season ),
		'week'      => $wk,
		'counts'    => (object) $season,
		'total'     => array_sum( $season ),
		'weekTotal' => array_sum( $week ),
		'friday'    => ridgeroad_mod( 'songs_friday' ) ? array( 'today' => 5 === $dow, 'days' => ( 5 - $dow + 7 ) % 7, 'leader' => $wk ? $wk[0] : null ) : null,
	);
}

/* -------------------------------------------------------------------------
 * Voting endpoints
 * ---------------------------------------------------------------------- */
add_action( 'wp_ajax_rr_song_nonce', 'ridgeroad_song_nonce' );
add_action( 'wp_ajax_nopriv_rr_song_nonce', 'ridgeroad_song_nonce' );
function ridgeroad_song_nonce() {
	nocache_headers();
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'rr_song_vote' ) ) );
}

add_action( 'wp_ajax_rr_song_vote', 'ridgeroad_song_vote' );
add_action( 'wp_ajax_nopriv_rr_song_vote', 'ridgeroad_song_vote' );
function ridgeroad_song_vote() {
	nocache_headers();
	if ( ! check_ajax_referer( 'rr_song_vote', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Please refresh the page and try again.', 'ridgeroad' ) ), 400 );
	}
	if ( ! ridgeroad_mod( 'songs_show' ) ) {
		wp_send_json_error( array( 'message' => __( 'Voting is closed right now.', 'ridgeroad' ) ), 400 );
	}
	$key   = isset( $_POST['song'] ) ? sanitize_title( wp_unslash( $_POST['song'] ) ) : '';
	$token = isset( $_POST['voter'] ) ? preg_replace( '/[^a-z0-9]/i', '', wp_unslash( $_POST['voter'] ) ) : '';
	$valid = wp_list_pluck( ridgeroad_song_list(), 'key' );
	if ( ! in_array( $key, $valid, true ) ) {
		wp_send_json_error( array( 'message' => __( 'That song isn’t in this year’s show.', 'ridgeroad' ) ), 400 );
	}
	if ( strlen( $token ) < 12 ) {
		wp_send_json_error( array( 'message' => __( 'Please refresh the page and try again.', 'ridgeroad' ) ), 400 );
	}
	$ip    = ridgeroad_ip_hash();
	$voter = substr( hash_hmac( 'sha256', $ip . '|' . substr( $token, 0, 40 ), wp_salt( 'nonce' ) ), 0, 20 );
	$votes = get_option( 'ridgeroad_song_votes', array() );
	$votes = is_array( $votes ) ? $votes : array();
	if ( ! isset( $votes[ $voter ] ) ) {
		// A household can have a few voters (one per phone), but not hundreds.
		$k = 'rr_sv_' . $ip;
		$n = (int) get_transient( $k );
		if ( $n >= 8 ) {
			wp_send_json_error( array( 'message' => __( 'Lots of votes from here today! Try again tomorrow.', 'ridgeroad' ) ), 429 );
		}
		set_transient( $k, $n + 1, DAY_IN_SECONDS );
	}
	$votes[ $voter ] = array( $key, time() );
	update_option( 'ridgeroad_song_votes', $votes, false );
	if ( function_exists( 'ridgeroad_stat_add' ) ) {
		ridgeroad_stat_add( 'song_vote' );
	}
	wp_send_json_success( array( 'mine' => $key, 'votes' => ridgeroad_votes_public() ) );
}

/* -------------------------------------------------------------------------
 * Dashboard: leaderboard at a glance + clear votes for a new season
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_rr_reset_song_votes', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'rr_reset_votes' ) ) {
		wp_die( esc_html__( 'Sorry, you can’t do that.', 'ridgeroad' ) );
	}
	delete_option( 'ridgeroad_song_votes' );
	wp_safe_redirect( add_query_arg( 'rr_votes_reset', 1, admin_url( 'index.php' ) ) );
	exit;
} );
add_action( 'admin_notices', function () {
	if ( ! empty( $_GET['rr_votes_reset'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All song votes were cleared.', 'ridgeroad' ) . '</p></div>';
	}
} );
add_action( 'wp_dashboard_setup', function () {
	if ( ! ridgeroad_mod( 'songs_show' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'rr_song_votes', __( 'Favorite Song Votes', 'ridgeroad' ), function () {
		$v = ridgeroad_votes_public();
		echo '<p><strong>' . esc_html__( 'This week (Fan Favorite Friday)', 'ridgeroad' ) . '</strong> — ' . (int) $v['weekTotal'] . ' ' . esc_html__( 'votes', 'ridgeroad' ) . '</p><ol>';
		foreach ( $v['week'] as $s ) {
			echo '<li>' . esc_html( $s['title'] ) . ' — ' . (int) $s['votes'] . '</li>';
		}
		echo '</ol><p><strong>' . esc_html__( 'All season', 'ridgeroad' ) . '</strong> — ' . (int) $v['total'] . ' ' . esc_html__( 'votes', 'ridgeroad' ) . '</p><ol>';
		foreach ( $v['season'] as $s ) {
			echo '<li>' . esc_html( $s['title'] ) . ' — ' . (int) $s['votes'] . '</li>';
		}
		echo '</ol>';
		printf(
			'<p><a href="%s" onclick="return confirm(%s);">%s</a></p>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rr_reset_song_votes' ), 'rr_reset_votes' ) ),
			esc_attr( wp_json_encode( __( 'Clear ALL song votes? This can’t be undone.', 'ridgeroad' ) ) ),
			esc_html__( 'Clear all votes (start a new season)', 'ridgeroad' )
		);
	} );
} );
