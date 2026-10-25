<?php
/**
 * Song covers for Tonight's Playlist and Vote for Your Favorite Song.
 *
 * Which picture a song gets, first match wins:
 *   1. the owner's own picture for that song (Marquee → Settings → Remote Falcon → Song covers), or "No cover";
 *   2. the artwork set for the song in Remote Falcon;
 *   3. a cover found automatically (Apple's free music search, by song and artist), saved on this site.
 * Finding runs in the background (WP-Cron) and is saved, so pages never wait on it.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Saved finds: key => array( 'url' => '', 't' => time ). '' = looked, found nothing (tried again after a week). */
function marquee_cover_cache() {
	$c = get_option( 'marquee_cover_cache' );
	return is_array( $c ) ? $c : array();
}

function marquee_cover_dir( $url = false ) {
	$u = wp_upload_dir( null, false );
	return trailingslashit( $url ? $u['baseurl'] : $u['basedir'] ) . 'marquee/covers/';
}

add_filter( 'ridgeroad_song_cover', function ( $img, $song ) {
	$s = marquee_get();
	if ( empty( $s['covers_on'] ) || empty( $song['key'] ) ) {
		return '';
	}
	$own = (array) $s['covers'];
	if ( isset( $own[ $song['key'] ] ) ) {
		$id = (int) $own[ $song['key'] ];
		if ( $id < 0 ) {
			return '';                               // "No cover"
		}
		if ( $id > 0 ) {
			$u = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $u ) {
				return $u;
			}
		}
	}
	if ( $img ) {
		return $img;                                 // Remote Falcon artwork
	}
	if ( empty( $s['covers_auto'] ) ) {
		return '';
	}
	$c = marquee_cover_cache();
	if ( isset( $c[ $song['key'] ] ) ) {
		return (string) $c[ $song['key'] ]['url'];
	}
	marquee_covers_soon();
	return '';
}, 10, 2 );

/** Look for missing covers in the background, shortly. */
function marquee_covers_soon() {
	static $asked = false;
	if ( $asked || wp_next_scheduled( 'marquee_find_covers' ) ) {
		return;
	}
	$asked = true;
	wp_schedule_single_event( time() + 5, 'marquee_find_covers' );
}

add_action( 'marquee_find_covers', 'marquee_find_covers' );
/** Find covers for songs that have none yet (up to 25 a run; the rest on the next run). */
function marquee_find_covers() {
	$s = marquee_get();
	if ( empty( $s['covers_on'] ) || empty( $s['covers_auto'] ) || ! function_exists( 'ridgeroad_song_list_all' ) ) {
		return;
	}
	$cache = marquee_cover_cache();
	$own   = (array) $s['covers'];
	$todo  = 0;
	foreach ( ridgeroad_song_list_all() as $song ) {
		$k = $song['key'];
		if ( ! empty( $song['image'] ) || isset( $own[ $k ] ) ) {
			continue;
		}
		if ( isset( $cache[ $k ] ) && ( '' !== $cache[ $k ]['url'] || time() - (int) $cache[ $k ]['t'] < WEEK_IN_SECONDS ) ) {
			continue;
		}
		if ( ++$todo > 25 ) {
			marquee_covers_soon();
			break;
		}
		$cache[ $k ] = array( 'url' => marquee_cover_lookup( $song ), 't' => time() );
		update_option( 'marquee_cover_cache', $cache, false );
		usleep( 300000 ); // gentle with the search
	}
	if ( function_exists( 'ridgeroad_purge_page_cache' ) ) {
		ridgeroad_purge_page_cache();                 // visitors see the new covers right away
	}
}

/** One song: search, pick the best match, save a 300 px copy on this site. Returns its URL or ''. */
function marquee_cover_lookup( $song ) {
	$title  = trim( preg_replace( '/\s*[\(\[].*?[\)\]]\s*/', ' ', (string) $song['title'] ) ); // "(Remix)", "[2023]"…
	$artist = trim( (string) $song['artist'] );
	if ( '' === $title ) {
		return '';
	}
	$r = wp_remote_get( add_query_arg( array(
		'term'   => rawurlencode( trim( $title . ' ' . $artist ) ),
		'entity' => 'song',
		'limit'  => 8,
		'media'  => 'music',
	), 'https://itunes.apple.com/search' ), array( 'timeout' => 8 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return '';
	}
	$j    = json_decode( wp_remote_retrieve_body( $r ), true );
	$best = null;
	$norm = function ( $t ) {
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( remove_accents( (string) $t ) ) ) );
	};
	foreach ( (array) ( $j['results'] ?? array() ) as $hit ) {
		if ( empty( $hit['artworkUrl100'] ) ) {
			continue;
		}
		$score = 0;
		$tn    = $norm( preg_replace( '/\s*[\(\[].*?[\)\]]\s*/', ' ', $hit['trackName'] ?? '' ) );
		if ( $tn === $norm( $title ) ) {
			$score += 2;
		} elseif ( false !== strpos( $tn, $norm( $title ) ) || false !== strpos( $norm( $title ), $tn ) ) {
			$score += 1;
		} else {
			continue;                                 // a different song
		}
		if ( $artist && false !== strpos( $norm( $hit['artistName'] ?? '' ), $norm( $artist ) ) ) {
			$score += 2;
		}
		if ( ! $best || $score > $best[0] ) {
			$best = array( $score, $hit['artworkUrl100'] );
		}
	}
	if ( ! $best || ( $artist && $best[0] < 3 ) ) {
		return '';                                    // not sure enough: no cover beats the wrong one
	}
	$src = preg_replace( '#/\d+x\d+bb\.(jpg|png)$#', '/300x300bb.jpg', $best[1] );
	$img = wp_remote_get( $src, array( 'timeout' => 10 ) );
	$bin = is_wp_error( $img ) ? '' : wp_remote_retrieve_body( $img );
	if ( strlen( $bin ) < 1000 || "\xFF\xD8" !== substr( $bin, 0, 2 ) ) {
		return '';
	}
	wp_mkdir_p( marquee_cover_dir() );
	$file = sanitize_file_name( $song['key'] ) . '-' . substr( md5( $src ), 0, 6 ) . '.jpg';
	if ( false === file_put_contents( marquee_cover_dir() . $file, $bin ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return '';
	}
	return marquee_cover_dir( true ) . $file;
}

/* Settings: "Find covers again" clears what was found (your own pictures stay) and looks again. */
add_action( 'admin_post_marquee_covers_again', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t change these settings.', 'marquee' ) );
	}
	check_admin_referer( 'marquee_covers_again' );
	foreach ( glob( marquee_cover_dir() . '*.jpg' ) ?: array() as $f ) {
		wp_delete_file( $f );
	}
	delete_option( 'marquee_cover_cache' );
	wp_clear_scheduled_hook( 'marquee_find_covers' );
	wp_schedule_single_event( time(), 'marquee_find_covers' );
	spawn_cron();
	wp_safe_redirect( admin_url( 'admin.php?page=marquee&covers=again#mq-step-3' ) );
	exit;
} );

/** Settings box: every song with its cover, and Change / No cover / Automatic. */
function marquee_covers_box( $s ) {
	$songs = function_exists( 'ridgeroad_song_list_all' ) ? ridgeroad_song_list_all() : array();
	$own   = (array) $s['covers'];
	?>
	<div class="mq-covers">
		<h3><?php esc_html_e( 'Song covers', 'marquee' ); ?></h3>
		<input type="hidden" name="covers_sent" value="1">
		<label class="mq__feat"><span><b><?php esc_html_e( 'Show cover pictures', 'marquee' ); ?></b><small><?php esc_html_e( 'A small cover next to each song in Tonight’s Playlist and Vote for Your Favorite Song. It uses the artwork you set in Remote Falcon, or your own picture below.', 'marquee' ); ?></small></span><input type="checkbox" name="covers_on" value="1" <?php checked( ! empty( $s['covers_on'] ) ); ?>></label>
		<label class="mq__feat"><span><b><?php esc_html_e( 'Find album covers automatically', 'marquee' ); ?></b><small><?php esc_html_e( 'For songs without artwork, Marquee looks up the album cover by song and artist (Apple’s free music search) and saves a copy on your site. It only uses a cover when the song matches; change any that are wrong below.', 'marquee' ); ?></small></span><input type="checkbox" name="covers_auto" value="1" <?php checked( ! empty( $s['covers_auto'] ) ); ?>></label>
		<?php if ( ! $songs ) : ?>
			<p class="mq__fine"><?php esc_html_e( 'Your songs show up here once Remote Falcon is connected (save this page after a successful test).', 'marquee' ); ?></p>
		<?php else : ?>
			<?php if ( isset( $_GET['covers'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<p class="mq__note"><?php esc_html_e( 'Looking for covers again. It takes a minute or two; reload this page to see them.', 'marquee' ); ?></p>
			<?php endif; ?>
			<ul class="mq-covers__list">
				<?php
				foreach ( $songs as $song ) :
					$k    = $song['key'];
					$cur  = isset( $own[ $k ] ) ? (int) $own[ $k ] : 0;
					$auto = $cur ? '' : ( ! empty( $song['image'] ) ? $song['image'] : ( marquee_cover_cache()[ $k ]['url'] ?? '' ) );
					$pic  = $cur > 0 ? wp_get_attachment_image_url( $cur, 'thumbnail' ) : $auto;
					$from = $cur > 0 ? __( 'Your picture', 'marquee' ) : ( $cur < 0 ? __( 'No cover', 'marquee' ) : ( ! empty( $song['image'] ) ? __( 'From Remote Falcon', 'marquee' ) : ( $auto ? __( 'Found automatically', 'marquee' ) : __( 'No cover found', 'marquee' ) ) ) );
					?>
					<li class="mq-covers__song" data-mq-cover>
						<span class="mq-covers__pic"><?php if ( $pic && $cur >= 0 ) : ?><img src="<?php echo esc_url( $pic ); ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?></span>
						<span class="mq-covers__t"><b><?php echo esc_html( $song['title'] ); ?></b><small><?php echo esc_html( trim( $song['artist'] . ' · ' . $from, ' ·' ) ); ?></small></span>
						<input type="hidden" name="covers[<?php echo esc_attr( $k ); ?>]" value="<?php echo (int) $cur; ?>" data-mq-cover-id>
						<span class="mq-covers__btns">
							<button type="button" class="button-link" data-mq-cover-pick><?php esc_html_e( 'Change', 'marquee' ); ?></button>
							<button type="button" class="button-link" data-mq-cover-set="-1"><?php esc_html_e( 'No cover', 'marquee' ); ?></button>
							<?php if ( $cur ) : ?><button type="button" class="button-link" data-mq-cover-set="0"><?php esc_html_e( 'Automatic', 'marquee' ); ?></button><?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="mq__fine"><?php esc_html_e( 'Changes are kept when you click Save.', 'marquee' ); ?>
				<?php if ( ! empty( $s['covers_auto'] ) ) : ?><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=marquee_covers_again' ), 'marquee_covers_again' ) ); ?>"><?php esc_html_e( 'Find covers again', 'marquee' ); ?></a><?php endif; ?></p>
		<?php endif; ?>
	</div>
	<?php
}
