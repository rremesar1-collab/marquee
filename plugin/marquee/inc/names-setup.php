<?php
/**
 * Easier Your Name in Lights setup.
 *
 * - The FPP names script (3.10+) reports its Pixel Overlay Models and the playlists its scheduler runs;
 *   Settings uses them for a screen picker and a "names only during your show playlist" suggestion.
 * - "Check again" and "Send a test name" buttons: the website asks, the script answers at its next check-in.
 * - A step-by-step panel at the top of Names & FPP that turns green as the FPP reports in.
 *
 * Works with older scripts too: anything they don't report simply isn't shown.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * What the FPP reported (stored at each check-in, after the key was checked)
 * ---------------------------------------------------------------------- */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	// phpcs:disable WordPress.Security.NonceVerification -- the FPP's private key was checked before this filter runs
	$x = marquee_fpp_extra();
	if ( ! empty( $_POST['fpp'] ) ) {
		$f = json_decode( wp_unslash( $_POST['fpp'] ), true ); // phpcs:ignore
		if ( is_array( $f ) ) {
			if ( isset( $f['script'] ) ) {
				$x['script']      = sanitize_text_field( mb_substr( (string) $f['script'], 0, 12 ) );
				$x['fpp_version'] = sanitize_text_field( mb_substr( (string) ( $f['fpp_version'] ?? '' ), 0, 20 ) );
			}
			if ( isset( $f['models'] ) && is_array( $f['models'] ) ) {
				$x['models'] = array();
				foreach ( array_slice( $f['models'], 0, 150 ) as $m ) {
					if ( is_array( $m ) && '' !== trim( (string) ( $m['name'] ?? '' ) ) ) {
						$x['models'][] = array(
							'name'   => sanitize_text_field( mb_substr( (string) $m['name'], 0, 60 ) ),
							'w'      => max( 0, (int) ( $m['w'] ?? 0 ) ),
							'h'      => max( 0, (int) ( $m['h'] ?? 0 ) ),
							'screen' => ! empty( $m['screen'] ),
						);
					}
				}
				$x['models_t'] = time();
			}
			if ( isset( $f['scheduled'] ) && is_array( $f['scheduled'] ) ) {
				$x['scheduled'] = array();
				foreach ( array_slice( $f['scheduled'], 0, 12 ) as $p ) {
					if ( is_array( $p ) && '' !== trim( (string) ( $p['name'] ?? '' ) ) ) {
						$x['scheduled'][] = array( 'name' => sanitize_text_field( mb_substr( (string) $p['name'], 0, 80 ) ), 'items' => max( 0, (int) ( $p['items'] ?? 0 ) ) );
					}
				}
			}
			if ( isset( $f['tested'] ) && is_array( $f['tested'] ) ) {
				foreach ( array_slice( $f['tested'], 0, 5 ) as $t ) {
					if ( is_array( $t ) && ! empty( $t['id'] ) ) {
						$x['tested'] = array(
							'id'      => sanitize_key( (string) $t['id'] ),
							'screens' => max( 0, (int) ( $t['screens'] ?? 0 ) ),
							'of'      => max( 0, (int) ( $t['of'] ?? 0 ) ),
							't'       => time(),
						);
					}
				}
			}
		}
	}
	if ( ! empty( $_POST['report'] ) ) {
		$r = json_decode( wp_unslash( $_POST['report'] ), true ); // phpcs:ignore
		if ( is_array( $r ) && ! empty( $r['checks'] ) && is_array( $r['checks'] ) ) {
			$checks = array();
			foreach ( array_slice( $r['checks'], 0, 20 ) as $c ) {
				if ( is_array( $c ) ) {
					$checks[] = array(
						'ok'   => ! empty( $c['ok'] ),
						'what' => sanitize_key( (string) ( $c['what'] ?? '' ) ),
						'msg'  => sanitize_text_field( mb_substr( (string) ( $c['msg'] ?? '' ), 0, 300 ) ),
					);
				}
			}
			$x['checks']   = $checks;
			$x['checks_t'] = time();
			if ( ! empty( $r['req'] ) ) {
				$x['check_done'] = sanitize_key( (string) $r['req'] );
			}
			if ( ! empty( $r['v'] ) ) {
				$x['script'] = sanitize_text_field( mb_substr( (string) $r['v'], 0, 12 ) );
			}
		}
	}
	// phpcs:enable
	update_option( 'marquee_fpp_extra', $x, false );

	// what the website wants from the FPP right now
	$req = get_option( 'marquee_names_req' );
	$req = is_array( $req ) ? $req : array();
	if ( ! empty( $req['check'] ) && ( $x['check_done'] ?? '' ) !== $req['check'] && time() - (int) $req['check_t'] < HOUR_IN_SECONDS ) {
		$data['check'] = $req['check'];
	}
	if ( ! empty( $req['test'] ) && time() - (int) $req['test_t'] < 10 * MINUTE_IN_SECONDS && ( $x['tested']['id'] ?? '' ) !== $req['test'] ) {
		$data['test'] = array( 'id' => $req['test'], 'text' => $req['test_text'] );
	}
	$last = max( (int) ( $req['check_t'] ?? 0 ), (int) ( $req['test_t'] ?? 0 ), (int) ( $req['open_t'] ?? 0 ) );
	if ( time() - $last < 3 * MINUTE_IN_SECONDS ) {
		$data['fast'] = 1; // someone has Names & FPP open: the FPP checks in every few seconds for a bit
	}
	return $data;
}, 6 );

/** What the names script reported beyond the basics (3.10+), with safe defaults. */
function marquee_fpp_extra() {
	$x = get_option( 'marquee_fpp_extra' );
	return array_merge( array( 'script' => '', 'fpp_version' => '', 'models' => null, 'models_t' => 0, 'scheduled' => array(), 'tested' => array(), 'checks' => array(), 'checks_t' => 0, 'check_done' => '' ), is_array( $x ) ? $x : array() );
}

/** The setup check: the newest one, from this file's copy (has 'what') or the older one. */
function marquee_names_checks() {
	$x   = marquee_fpp_extra();
	$rep = get_option( 'ridgeroad_names_report' );
	$old = is_array( $rep ) && ! empty( $rep['checks'] ) ? $rep : null;
	if ( $x['checks'] && ( ! $old || (int) $x['checks_t'] >= (int) $old['t'] - 5 ) ) {
		return array( 't' => (int) $x['checks_t'], 'checks' => $x['checks'] );
	}
	if ( $old ) {
		foreach ( $old['checks'] as &$c ) {
			$c['what'] = marquee_guess_what( $c['msg'] );
		}
		return array( 't' => (int) $old['t'], 'checks' => $old['checks'] );
	}
	return array( 't' => 0, 'checks' => array() );
}

/** Older scripts don't say what a check is about: tell from its wording. */
function marquee_guess_what( $msg ) {
	$m = strtolower( (string) $msg );
	if ( false !== strpos( $m, 'overlay model' ) || false !== strpos( $m, 'screens set' ) || false !== strpos( $m, 'could not reach the fpp' ) ) {
		return 'screen';
	}
	if ( false !== strpos( $m, 'playlist' ) && false === strpos( $m, 'fpp status' ) ) {
		return 'playlist';
	}
	if ( false !== strpos( $m, 'website' ) || false !== strpos( $m, 'private key' ) ) {
		return 'website';
	}
	return '';
}

/** The FPP's overlay models (for the screen picker), or null when the script hasn't reported them. */
function marquee_fpp_models() {
	$x = marquee_fpp_extra();
	return is_array( $x['models'] ) && time() - (int) $x['models_t'] < 14 * DAY_IN_SECONDS ? $x['models'] : null;
}

/** A screen row is on the FPP that runs the script (so the reported models apply to it). */
function marquee_screen_is_local( $fpp ) {
	$h = strtolower( trim( preg_replace( '#^https?://#', '', (string) $fpp ), '/' ) );
	$h = preg_replace( '/:\d+$/', '', $h );
	return '' === $h || in_array( $h, array( '127.0.0.1', 'localhost' ), true );
}

/**
 * The show playlist to suggest for "Only during these playlists": when the scheduler runs two or more
 * playlists (a show plus something like a static display), the one with the most items. Null = no suggestion.
 */
function marquee_show_playlist_suggestion() {
	$s = marquee_get();
	if ( ! empty( $s['names_playlists'] ) ) {
		return null;
	}
	$bp    = mb_strtolower( $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights' );
	$sched = array_values( array_filter( marquee_fpp_extra()['scheduled'], function ( $p ) use ( $bp ) {
		return 0 !== strpos( mb_strtolower( $p['name'] ), $bp );
	} ) );
	if ( count( $sched ) < 2 ) {
		return null;
	}
	usort( $sched, function ( $a, $b ) {
		return $b['items'] <=> $a['items'];
	} );
	return $sched[0]['items'] > $sched[1]['items'] ? $sched[0]['name'] : null;
}

/* -------------------------------------------------------------------------
 * What each names break did (names script 3.16+ reports it with its check-in,
 * after the key is checked), for the setup panel: no FPP logs needed.
 * ---------------------------------------------------------------------- */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	if ( empty( $_POST['breaks'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the names key was checked
		return $data;
	}
	$in = json_decode( wp_unslash( $_POST['breaks'] ), true ); // phpcs:ignore
	if ( ! is_array( $in ) ) {
		return $data;
	}
	$keep = get_option( 'marquee_names_breaks' );
	$keep = is_array( $keep ) ? $keep : array();
	foreach ( array_slice( $in, 0, 10 ) as $b ) {
		if ( ! is_array( $b ) || empty( $b['t'] ) ) {
			continue;
		}
		$rows = array();
		foreach ( array_slice( (array) ( $b['names'] ?? array() ), 0, 12 ) as $n ) {
			if ( is_array( $n ) ) {
				$rows[] = array(
					'text'  => sanitize_text_field( mb_substr( (string) ( $n['text'] ?? '' ), 0, 60 ) ),
					'shown' => ! empty( $n['shown'] ),
					'voice' => sanitize_text_field( mb_substr( (string) ( $n['voice'] ?? '' ), 0, 140 ) ),
				);
			}
		}
		$keep[] = array(
			't'     => min( time(), (int) $b['t'] ),
			'kind'  => sanitize_text_field( mb_substr( (string) ( $b['kind'] ?? '' ), 0, 60 ) ),
			'names' => $rows,
		);
	}
	usort( $keep, function ( $a, $b ) {
		return $b['t'] <=> $a['t'];
	} );
	update_option( 'marquee_names_breaks', array_slice( $keep, 0, 6 ), false );
	return $data;
}, 5 );

/** The last names breaks, newest first, for the setup panel. */
function marquee_names_breaks_html() {
	$list = get_option( 'marquee_names_breaks' );
	if ( ! is_array( $list ) || ! $list ) {
		return '';
	}
	ob_start();
	?>
	<details class="mq-nbreaks"<?php echo time() - (int) $list[0]['t'] < 2 * HOUR_IN_SECONDS ? ' open' : ''; ?>>
		<summary><?php esc_html_e( 'Last names breaks', 'marquee' ); ?></summary>
		<ul>
			<?php foreach ( array_slice( $list, 0, 4 ) as $b ) : ?>
				<?php
				$kind = (string) $b['kind'];
				if ( 'flow' === $kind ) {
					$kind = __( 'each name for as long as it needs', 'marquee' );
				} elseif ( 'timed' === $kind ) {
					$kind = __( 'timed break', 'marquee' );
				} elseif ( 0 === strpos( $kind, 'theme: ' ) ) {
					/* translators: %s: theme name */
					$kind = sprintf( __( 'theme: %s', 'marquee' ), substr( $kind, 7 ) );
				}
				?>
				<li>
					<b><?php /* translators: %s: time ago */ echo esc_html( sprintf( __( '%s ago', 'marquee' ), human_time_diff( (int) $b['t'] ) ) ); ?></b> <small><?php echo esc_html( $kind ); ?></small>
					<?php if ( ! $b['names'] ) : ?>
						<span class="mq-nbreaks__none"><?php esc_html_e( 'No names were waiting.', 'marquee' ); ?></span>
					<?php endif; ?>
					<?php foreach ( $b['names'] as $n ) : ?>
						<?php
						$said = 'said' === $n['voice'];
						$bad  = $n['shown'] && '' !== $n['voice'] && ! $said;
						?>
						<span class="mq-nbreaks__n<?php echo $bad ? ' is-bad' : ( $n['shown'] ? ' is-ok' : '' ); ?>">
							<?php echo esc_html( $n['text'] ); ?>
							<em>
							<?php
							if ( ! $n['shown'] ) {
								echo esc_html( 'waits for the next break' === $n['voice'] ? __( 'waits for the next break', 'marquee' ) : ( 'no screen took it' === $n['voice'] ? __( 'no screen took it', 'marquee' ) : $n['voice'] ) );
							} elseif ( $said ) {
								esc_html_e( 'on the screens and said', 'marquee' );
							} elseif ( '' === $n['voice'] ) {
								esc_html_e( 'on the screens', 'marquee' );
							} else {
								/* translators: %s: why the name wasn't said */
								echo esc_html( sprintf( __( 'on the screens, %s', 'marquee' ), marquee_names_voice_why( $n['voice'] ) ) );
							}
							?>
							</em>
						</span>
					<?php endforeach; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
	<?php
	return ob_get_clean();
}

/** The names script's reason a name wasn't said, in the owner's words. */
function marquee_names_voice_why( $v ) {
	$v = preg_replace( '/^not said: /', '', (string) $v );
	if ( 0 === strpos( $v, 'no voice clip from the website' ) ) {
		return __( 'not said: your website had no voice clip for it (Voice step: is there an error at the top?)', 'marquee' );
	}
	if ( 0 === strpos( $v, "couldn't download the voice clip" ) ) {
		return __( 'not said: the FPP couldn’t download its voice clip from your website', 'marquee' );
	}
	if ( 0 === strpos( $v, "FPP didn't take the play command" ) ) {
		return __( 'not said: FPP didn’t take the play command', 'marquee' );
	}
	/* translators: %s: reason, from the FPP */
	return sprintf( __( 'not said: %s', 'marquee' ), $v );
}

/* -------------------------------------------------------------------------
 * Buttons on the Settings page (admins only)
 * ---------------------------------------------------------------------- */
function marquee_names_req_update( $add ) {
	$req = get_option( 'marquee_names_req' );
	update_option( 'marquee_names_req', array_merge( is_array( $req ) ? $req : array(), $add ), false );
}

add_action( 'wp_ajax_marquee_names_check', function () {
	check_ajax_referer( 'marquee_names_setup', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	marquee_names_req_update( array( 'check' => strtolower( wp_generate_password( 10, false ) ), 'check_t' => time() ) );
	wp_send_json_success( array( 'html' => marquee_names_setup_panel_html() ) );
} );

add_action( 'wp_ajax_marquee_names_test', function () {
	check_ajax_referer( 'marquee_names_setup', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$text = isset( $_POST['text'] ) ? mb_strtoupper( mb_substr( sanitize_text_field( wp_unslash( $_POST['text'] ) ), 0, 20 ) ) : '';
	marquee_names_req_update( array( 'test' => strtolower( wp_generate_password( 10, false ) ), 'test_t' => time(), 'test_text' => '' !== $text ? $text : 'MERRY CHRISTMAS!' ) );
	wp_send_json_success( array( 'html' => marquee_names_setup_panel_html() ) );
} );

add_action( 'wp_ajax_marquee_names_panel', function () {
	check_ajax_referer( 'marquee_names_setup', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	marquee_names_req_update( array( 'open_t' => time() ) );
	wp_send_json_success( array( 'html' => marquee_names_setup_panel_html() ) );
} );

/* "Put it in line": a real test name in the queue, as if a kid sent it (voice and all), for testing the names break. */
add_action( 'wp_ajax_marquee_names_queue_test', function () {
	check_ajax_referer( 'marquee_names_setup', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'ridgeroad_clean_kid_name' ) ) {
		wp_send_json_error( null, 403 );
	}
	$raw  = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
	$raw  = preg_replace( '/^(merry christmas|happy holidays|hi santa!?)[,!]?\s*(love,)?\s*/i', '', $raw );
	$name = ridgeroad_clean_kid_name( '' !== trim( $raw, ' !,' ) ? trim( $raw, ' !,' ) : 'Rudolph' );
	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => __( 'Type a first name to put in line.', 'marquee' ) ) );
	}
	$msgs = ridgeroad_name_greetings();
	$id   = wp_insert_post( array( 'post_type' => 'rr_shoutout', 'post_status' => 'publish', 'post_title' => $name ), true );
	if ( is_wp_error( $id ) ) {
		wp_send_json_error( array( 'message' => $id->get_error_message() ) );
	}
	$text = ridgeroad_name_message( reset( $msgs ), $name );
	update_post_meta( $id, '_rr_text', $text );
	update_post_meta( $id, '_rr_shown', 0 );
	do_action( 'ridgeroad_name_added', $id, $text ); // the spoken version, like a kid's name
	marquee_names_req_update( array( 'open_t' => time() ) );
	/* translators: %s: the message */
	wp_send_json_success( array( 'message' => sprintf( __( '“%s” is in line. It goes up at the next names break (start your Names in Lights playlist to see it now).', 'marquee' ), $text ), 'html' => marquee_names_setup_panel_html() ) );
} );

/* "Only during this playlist": take the suggestion with one click. */
add_action( 'wp_ajax_marquee_names_onlypl', function () {
	check_ajax_referer( 'marquee_names_setup', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$pl  = isset( $_POST['playlist'] ) ? sanitize_text_field( wp_unslash( $_POST['playlist'] ) ) : '';
	$all = array_merge( wp_list_pluck( marquee_fpp_extra()['scheduled'], 'name' ), (array) ( get_option( 'ridgeroad_fpp' )['playlists'] ?? array() ) );
	if ( '' === $pl || ! in_array( $pl, $all, true ) ) {
		wp_send_json_error( array( 'message' => __( 'That playlist isn’t on your FPP.', 'marquee' ) ) );
	}
	$o                    = get_option( 'marquee' );
	$o                    = is_array( $o ) ? $o : array();
	$o['names_playlists'] = array( $pl );
	update_option( 'marquee', $o );
	wp_send_json_success( array( 'html' => marquee_names_setup_panel_html() ) );
} );

/* -------------------------------------------------------------------------
 * The step-by-step panel
 * ---------------------------------------------------------------------- */
function marquee_names_setup_panel() {
	echo '<div class="mq-nsetup" data-mq-nsetup data-nonce="' . esc_attr( wp_create_nonce( 'marquee_names_setup' ) ) . '">';
	echo marquee_names_setup_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping below
	echo '</div>';
}

/** The steps, each with its state (ok / bad / wait / tip) and the one thing to do next. */
function marquee_names_setup_steps() {
	$s      = marquee_get();
	$x      = marquee_fpp_extra();
	$seen   = (int) get_option( 'ridgeroad_names_seen' );
	$on     = $seen && time() - $seen < 15 * MINUTE_IN_SECONDS;
	$live   = 'live' === $s['names_mode'];
	$rep    = marquee_names_checks();
	$checks = $rep['checks'];
	$new    = $x['script'] && version_compare( $x['script'], '3.10', '>=' );
	$bp     = $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights';
	$req    = get_option( 'marquee_names_req' );
	$req    = is_array( $req ) ? $req : array();
	$steps  = array();

	// 1. the FPP
	if ( $on ) {
		$steps[] = array( 'ok', __( 'Your FPP is connected', 'marquee' ), sprintf( /* translators: %s: time ago */ __( 'It last checked in %s ago.', 'marquee' ), human_time_diff( $seen ) ) . ( $x['script'] && ! $new ? ' ' . __( 'Update the Marquee plugin on your FPP (Content Setup → Plugin Manager) for the screen picker and the simple names break.', 'marquee' ) : '' ), 'fpp' );
	} elseif ( $seen ) {
		$steps[] = array( 'bad', __( 'Your FPP hasn’t checked in lately', 'marquee' ), sprintf( /* translators: %s: time ago */ __( 'Last time was %s ago. Is the FPP on, and is the Marquee plugin running on it?', 'marquee' ), human_time_diff( $seen ) ), 'fpp' );
	} else {
		$steps[] = array( 'bad', __( 'Connect your FPP', 'marquee' ), __( 'Install the Marquee plugin on the FPP that plays your show and paste your connection code. The steps are in step 2 below (Connect your FPP).', 'marquee' ), 'fpp' );
	}

	// 2. the screens
	$scr_bad = array_values( array_filter( $checks, function ( $c ) {
		return 'screen' === $c['what'] && ! $c['ok'];
	} ) );
	$scr_ok  = array_values( array_filter( $checks, function ( $c ) {
		return 'screen' === $c['what'] && $c['ok'];
	} ) );
	if ( empty( $s['fpp_screens'] ) ) {
		$steps[] = array( $on ? 'bad' : 'wait', __( 'Pick your screen', 'marquee' ), marquee_fpp_models() ? __( 'Pick the matrix, TV or projector the names go on from the list in step 3 below (Screens), then Save.', 'marquee' ) : __( 'Type the overlay model name of your matrix, TV or projector in step 3 below (Screens), then Save.', 'marquee' ), 'screens' );
	} elseif ( $scr_bad ) {
		$off  = false !== stripos( $scr_bad[0]['msg'], 'could not reach' );
		$who  = array();
		foreach ( $scr_bad as $c ) {
			if ( $off === ( false !== stripos( $c['msg'], 'could not reach' ) ) ) {
				$who[] = trim( preg_replace( '/\s*[(:].*$/s', '', $c['msg'] ) );
			}
		}
		$who = wp_sprintf_l( '%l', array_unique( $who ) );
		/* translators: %s: screen name */
		$head = $off ? sprintf( __( 'Can’t reach the FPP for %s', 'marquee' ), $who ) : sprintf( __( '%s: screen not found', 'marquee' ), $who );
		$more = $scr_ok ? ' ' . sprintf( /* translators: %d: number of screens */ _n( 'Your other screen is ready.', 'Your other %d screens are ready.', count( $scr_ok ), 'marquee' ), count( $scr_ok ) ) : '';
		$tip  = $off ? ' ' . __( 'If it’s just switched off, that’s fine: names go up on it once it’s on. This updates by itself every couple of minutes.', 'marquee' ) : '';
		$steps[] = array( $off && $scr_ok ? 'wait' : 'bad', $head, $scr_bad[0]['msg'] . $tip . $more, 'screens' );
	} elseif ( $scr_ok ) {
		$steps[] = array( 'ok', __( 'Your screen is ready', 'marquee' ), $scr_ok[0]['msg'], 'screens' );
	} else {
		$steps[] = array( 'wait', __( 'Your screen', 'marquee' ), __( 'Saved. The FPP checks it at its next setup check.', 'marquee' ), 'screens' );
	}

	// 3. the names break
	if ( ! $live ) {
		$pl_bad = null;
		$pl_ok  = null;
		foreach ( $checks as $c ) {
			if ( 'playlist' === $c['what'] && false !== strpos( $c['msg'], "'" . $bp . "'" ) ) {
				if ( $c['ok'] ) {
					$pl_ok = $c;
				} else {
					$pl_bad = $c;
				}
			}
		}
		if ( $pl_ok ) {
			$simple  = false !== strpos( $pl_ok['msg'], 'simple names break' );
			$steps[] = array( 'ok', __( 'Names break is ready', 'marquee' ), $simple ? __( 'Marquee made a simple names break on your FPP: the show pauses and the names go up on your screens. Want music, a voice or a light show with it? The Names break and Voice steps add them.', 'marquee' ) : sprintf( /* translators: %s: playlist */ __( 'Your FPP has the “%s” playlist.', 'marquee' ), $bp ), 'break' );
		} elseif ( $pl_bad ) {
			if ( ! empty( $s['names_simple'] ) && $new && marquee_theme_get( 'names_on' ) ) {
				$steps[] = array( 'wait', __( 'Names break', 'marquee' ), __( 'Marquee makes a simple names break on your FPP for you. Click Check again.', 'marquee' ), 'break' );
			} elseif ( ! empty( $s['names_simple'] ) && $new ) {
				$steps[] = array( 'bad', __( 'Names break', 'marquee' ), __( 'Turn on Your Name in Lights (Features & pages) and Marquee makes a simple names break on your FPP for you.', 'marquee' ), 'break' );
			} elseif ( ! empty( $s['names_simple'] ) && $x['script'] ) {
				$steps[] = array( 'bad', __( 'Names break', 'marquee' ), sprintf( /* translators: %s: playlist */ __( 'Your FPP has no “%s” playlist. Update the Marquee plugin on your FPP and it makes a simple one for you, or make one with your names sequence (the Sequence maker can make it).', 'marquee' ), $bp ), 'break' );
			} else {
				$steps[] = array( 'bad', __( 'Names break', 'marquee' ), sprintf( /* translators: %s: playlist */ __( 'Your FPP has no “%s” playlist. Make one with your names sequence in it (the Sequence maker can make it).', 'marquee' ), $bp ), 'break' );
			}
		} else {
			$steps[] = array( 'wait', __( 'Names break', 'marquee' ), __( 'Shows here after the FPP’s next setup check.', 'marquee' ), 'break' );
		}
	}

	// 4. which playlist names go up during
	$sug = marquee_show_playlist_suggestion();
	if ( ! empty( $s['names_playlists'] ) ) {
		$steps[] = array( 'ok', __( 'Names wait for your show', 'marquee' ), sprintf( /* translators: %s: playlists */ __( 'Names go up only while %s plays.', 'marquee' ), implode( ', ', $s['names_playlists'] ) ), 'only' );
	} elseif ( $sug ) {
		$steps[] = array( 'tip', __( 'Names wait for your show?', 'marquee' ), sprintf( /* translators: %s: playlist */ __( 'Your FPP runs more than one playlist. “%s” looks like your show: names can wait for it, so they don’t go up during the others.', 'marquee' ), $sug ), 'only', $sug );
	} else {
		$steps[] = array( 'ok', __( 'Names go up during any playlist', 'marquee' ), __( 'Running something like a static display too? Pick your show playlist under “Only during these playlists” in step 1.', 'marquee' ), 'only' );
	}

	// 5. a test name
	$tested = $x['tested'];
	if ( ! empty( $req['test'] ) && ( $tested['id'] ?? '' ) !== $req['test'] && time() - (int) $req['test_t'] < 10 * MINUTE_IN_SECONDS ) {
		$steps[] = array( 'wait', __( 'Test name sent', 'marquee' ), __( 'It goes up on your screens within a minute (when the FPP next checks in).', 'marquee' ), 'test' );
	} elseif ( ! empty( $tested['id'] ) && time() - (int) $tested['t'] < DAY_IN_SECONDS ) {
		$steps[] = $tested['screens'] > 0
			? array( 'ok', __( 'Test name worked', 'marquee' ), sprintf( /* translators: 1: screens, 2: of how many */ __( 'It went up on %1$d of %2$d screen(s). Did you see it?', 'marquee' ), $tested['screens'], $tested['of'] ), 'test' )
			: array( 'bad', __( 'Test name didn’t show', 'marquee' ), __( 'No screen took it. Check the screen step above, then try again.', 'marquee' ), 'test' );
	} else {
		$steps[] = array( 'tip', __( 'Try a test name', 'marquee' ), ! $on ? __( 'Once your FPP is connected.', 'marquee' ) : ( $new ? __( 'Puts a name on your screens right now, to see how it looks.', 'marquee' ) : __( 'Update the Marquee plugin on your FPP for the test button here, or send your name from your website while the show plays.', 'marquee' ) ), 'test' );
	}
	return $steps;
}

function marquee_names_setup_panel_html() {
	$steps = marquee_names_setup_steps();
	$rep   = marquee_names_checks();
	$req   = get_option( 'marquee_names_req' );
	$req   = is_array( $req ) ? $req : array();
	$x     = marquee_fpp_extra();
	$seen  = (int) get_option( 'ridgeroad_names_seen' );
	$new   = $x['script'] && version_compare( $x['script'], '3.10', '>=' );
	$need  = array_filter( array_slice( $steps, 0, 3 ), function ( $st ) {
		return 'ok' !== $st[0];
	} );
	$checking = ! empty( $req['check'] ) && $x['check_done'] !== $req['check'] && time() - (int) $req['check_t'] < 10 * MINUTE_IN_SECONDS;
	$icons    = array( 'ok' => '✓', 'bad' => '✗', 'wait' => '…', 'tip' => '→' );
	$open     = array( 'fpp' => 'fpp', 'screens' => 'screens', 'break' => 'seq', 'only' => 'only' );
	ob_start();
	?>
	<div class="mq-nsetup__head">
		<h3><?php echo $need ? esc_html__( 'Setting up Your Name in Lights', 'marquee' ) : esc_html__( 'Your Name in Lights is ready', 'marquee' ); ?></h3>
		<span class="mq-nsetup__when">
			<?php
			if ( $checking ) {
				esc_html_e( 'Checking… (the FPP answers within a minute)', 'marquee' );
			} elseif ( $rep['t'] ) {
				/* translators: %s: time ago */
				echo esc_html( sprintf( __( 'Setup check from %s ago', 'marquee' ), human_time_diff( $rep['t'] ) ) );
			}
			?>
		</span>
		<?php if ( $seen && $new ) : ?><button type="button" class="button" data-mq-ncheck <?php disabled( $checking ); ?>><?php esc_html_e( 'Check again', 'marquee' ); ?></button><?php endif; ?>
	</div>
	<ol class="mq-nsetup__steps">
		<?php foreach ( $steps as $st ) : ?>
			<li class="is-<?php echo esc_attr( $st[0] ); ?>">
				<span class="mq-nsetup__icon" aria-hidden="true"><?php echo esc_html( $icons[ $st[0] ] ); ?></span>
				<span class="mq-nsetup__txt"><b><?php echo esc_html( $st[1] ); ?></b> <small><?php echo esc_html( $st[2] ); ?></small></span>
				<span class="mq-nsetup__act">
					<?php if ( 'only' === $st[3] && ! empty( $st[4] ) ) : ?>
						<button type="button" class="button button-primary" data-mq-nonly="<?php echo esc_attr( $st[4] ); ?>"><?php /* translators: %s: playlist */ echo esc_html( sprintf( __( 'Only during %s', 'marquee' ), $st[4] ) ); ?></button>
					<?php elseif ( 'test' === $st[3] && $seen && $new ) : ?>
						<input type="text" maxlength="20" value="MERRY CHRISTMAS!" data-mq-ntesttext aria-label="<?php esc_attr_e( 'Test name', 'marquee' ); ?>">
						<button type="button" class="button" data-mq-ntest title="<?php esc_attr_e( 'Shows it on your screens right now', 'marquee' ); ?>"><?php esc_html_e( 'Send a test name', 'marquee' ); ?></button>
						<button type="button" class="button" data-mq-nqueue title="<?php esc_attr_e( 'Adds it to the line like a kid’s name, with the voice; it goes up at the next names break', 'marquee' ); ?>"><?php esc_html_e( 'Put it in line', 'marquee' ); ?></button>
					<?php elseif ( 'screens' === $st[3] && 'ok' !== $st[0] && $seen && $new && marquee_get( 'fpp_screens' ) ) : ?>
						<button type="button" class="button" data-mq-ncheck <?php disabled( $checking ); ?> title="<?php esc_attr_e( 'Asks your FPP to look for your screens again right now', 'marquee' ); ?>"><?php esc_html_e( 'Check again', 'marquee' ); ?></button>
						<button type="button" class="button-link" data-mq-ngo="screens"><?php esc_html_e( 'Show me', 'marquee' ); ?></button>
					<?php elseif ( 'ok' !== $st[0] && isset( $open[ $st[3] ] ) ) : ?>
						<button type="button" class="button-link" data-mq-ngo="<?php echo esc_attr( $open[ $st[3] ] ); ?>"><?php esc_html_e( 'Show me', 'marquee' ); ?></button>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php echo marquee_names_breaks_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
	<?php
	return ob_get_clean();
}
