<?php
/**
 * Themed sequences: kids pick a short themed sequence (Candy Cane Swirl, Snowfall…)
 * when they send their name. The show owner makes the sequences in xLights, puts each
 * in its own FPP playlist, and lists them here. The FPP names script plays the theme the
 * kid who has waited longest picked; other kids who picked the same theme go up with
 * them, and nobody waits more than one extra break.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Themes kids can pick right now: slug => [ name, playlist, img, start, slot ]. Empty when switched off. */
function marquee_themes() {
	$s = marquee_get();
	if ( empty( $s['themes_on'] ) || empty( $s['themes'] ) || 'live' === $s['names_mode'] ) {   // themes need the names break
		return array();
	}
	$out = array();
	foreach ( (array) $s['themes'] as $t ) {
		$out[ sanitize_title( $t['name'] ) ] = $t;
	}
	return $out;
}

/** Picture for a theme card (the show's own, or a plain festive one). */
function marquee_theme_img( $t ) {
	$u = ! empty( $t['img'] ) ? wp_get_attachment_image_url( (int) $t['img'], 'medium_large' ) : '';
	return $u ? $u : '';
}

/** Picture for the Classic card ('' = the animated house). */
function marquee_classic_img() {
	$id = (int) marquee_get()['classic_img'];
	$u  = $id ? wp_get_attachment_image_url( $id, 'medium_large' ) : '';
	return $u ? $u : '';
}

/** Preview looks for the theme cards kids pick from (an animated picture of the house). */
function marquee_theme_looks() {
	return array(
		'classic'   => __( 'Classic: warm multicolor twinkle', 'marquee' ),
		'candy'     => __( 'Candy cane: red and white stripes', 'marquee' ),
		'snow'      => __( 'Snowfall: white and icy blue, falling snow', 'marquee' ),
		'rainbow'   => __( 'Rainbow: every color, moving', 'marquee' ),
		'christmas' => __( 'Christmas: red and green chase', 'marquee' ),
		'aurora'    => __( 'Northern lights: green and purple waves', 'marquee' ),
		'gold'      => __( 'Gold: warm gold sparkle', 'marquee' ),
		'ginger'    => __( 'Gingerbread: warm orange and cream', 'marquee' ),
		'icy'       => __( 'Frozen: blue and white shimmer', 'marquee' ),
		'purple'    => __( 'Purple and pink party', 'marquee' ),
	);
}

/** The look for a theme: the one picked, or a guess from its name. */
function marquee_theme_look( $t ) {
	if ( ! empty( $t['look'] ) && isset( marquee_theme_looks()[ $t['look'] ] ) ) {
		return $t['look'];
	}
	$n = strtolower( (string) ( $t['name'] ?? '' ) );
	foreach ( array(
		'candy'     => array( 'candy', 'peppermint', 'cane' ),
		'snow'      => array( 'snow', 'flake', 'winter' ),
		'rainbow'   => array( 'rainbow', 'party', 'disco' ),
		'aurora'    => array( 'northern', 'aurora', 'magic' ),
		'gold'      => array( 'gold', 'star', 'angel', 'nativity', 'holy' ),
		'ginger'    => array( 'ginger', 'cookie', 'cocoa' ),
		'icy'       => array( 'frozen', 'ice', 'icy', 'elsa', 'blue' ),
		'purple'    => array( 'purple', 'pink', 'sugar plum', 'fairy', 'princess' ),
		'christmas' => array( 'santa', 'sleigh', 'elf', 'elves', 'reindeer', 'rudolph', 'grinch', 'jingle', 'christmas' ),
	) as $look => $words ) {
		foreach ( $words as $w ) {
			if ( false !== strpos( $n, $w ) ) {
				return $look;
			}
		}
	}
	return 'classic';
}

/* The names form asks: is this a theme kids can pick? ('' = the classic break) */
add_filter( 'ridgeroad_name_theme', function ( $out, $slug ) {
	$th = marquee_themes();
	return isset( $th[ $slug ] ) ? $slug : '';
}, 10, 2 );

/* Each queued name tells the FPP which theme it picked. */
add_filter( 'ridgeroad_names_pull_item', function ( $item, $post ) {
	$t = (string) get_post_meta( $post->ID, '_rr_theme', true );
	if ( $t && isset( marquee_themes()[ $t ] ) ) {
		$item['theme'] = $t;
	}
	return $item;
}, 20, 2 );

/* …and the list of themes with their playlists and timing. */
add_filter( 'ridgeroad_names_pull_data', function ( $data ) {
	$out = array();
	foreach ( marquee_themes() as $slug => $t ) {
		$out[] = array( 'slug' => $slug, 'name' => $t['name'], 'playlist' => $t['playlist'], 'start' => (float) $t['start'], 'slot' => (float) $t['slot'] );
	}
	if ( $out ) {
		$data['themes'] = $out;
	}
	return $data;
} );

/* Kids' Names list in WP Admin: which theme each name picked. */
add_filter( 'manage_rr_shoutout_posts_columns', function ( $cols ) {
	if ( marquee_themes() ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'rr_text' === $k ) {
				$new['rr_theme'] = __( 'Theme', 'marquee' );
			}
		}
		return $new;
	}
	return $cols;
}, 20 );
add_action( 'manage_rr_shoutout_posts_custom_column', function ( $col, $id ) {
	if ( 'rr_theme' === $col ) {
		$t  = (string) get_post_meta( $id, '_rr_theme', true );
		$th = marquee_themes();
		echo esc_html( $t && isset( $th[ $t ] ) ? $th[ $t ]['name'] : __( 'Classic', 'marquee' ) );
	}
}, 10, 2 );

/** Settings card body. */
function marquee_themes_box( $s ) {
	$rows = array_values( (array) $s['themes'] );
	$pl   = $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights';
	?>
	<input type="hidden" name="themes_sent" value="1">
	<?php marquee_demo( 'themes', $s ); ?>
	<div class="mq-sm__cta">
		<span aria-hidden="true">✨</span>
		<span><b><?php esc_html_e( 'Don’t have themed sequences yet?', 'marquee' ); ?></b> <?php esc_html_e( 'Marquee can make them from your xLights layout: pick Candy Cane Swirl, Snowfall… or describe your own and the AI designs it.', 'marquee' ); ?></span>
		<button type="button" class="button button-primary" data-mq-goto-maker><?php esc_html_e( 'Make them for me', 'marquee' ); ?></button>
	</div>
	<p><?php esc_html_e( 'Give kids a choice: when they send their name, they also pick the show it goes up in. Between songs, your FPP plays the sequence they picked, with their name on your screens (and said out loud, if that’s on).', 'marquee' ); ?></p>
	<div class="mq__tl mq__tl--themes">
		<div class="mq__tl-part is-voice"><b><?php esc_html_e( '1. You make them', 'marquee' ); ?></b><small><?php esc_html_e( 'A few short sequences in xLights (about 30 seconds), like your names break: screens dark where the names go. Each one in its own FPP playlist.', 'marquee' ); ?></small></div>
		<div class="mq__tl-part is-names"><b><?php esc_html_e( '2. Kids pick one', 'marquee' ); ?></b><small><?php esc_html_e( 'On your website they type their name and tap a picture: Candy Cane Swirl, Snowfall, Rainbow Party…', 'marquee' ); ?></small></div>
		<div class="mq__tl-part is-voice"><b><?php esc_html_e( '3. It plays', 'marquee' ); ?></b><small><?php esc_html_e( 'After the current song, the theme the longest-waiting kid picked plays. Kids who picked the same one go up together; nobody waits more than one extra break.', 'marquee' ); ?></small></div>
	</div>
	<label class="mq__feat"><span><b><?php esc_html_e( 'Let kids pick a themed sequence', 'marquee' ); ?></b><small><?php echo esc_html( sprintf( __( 'Your regular “%s” break stays as the “Classic” choice.', 'marquee' ), $pl ) ); ?></small></span><input type="checkbox" name="themes_on" value="1" <?php checked( (bool) $s['themes_on'] ); ?>></label>
	<div class="mq__themes" data-rr-fx-lab="<?php echo esc_url( function_exists( 'ridgeroad_asset_url' ) ? ridgeroad_asset_url( 'lightlab/' ) : MARQUEE_URL . 'core/assets/lightlab/' ); ?>">
		<p class="mq__fine"><?php esc_html_e( 'Each card on the right is what kids see when they pick. Without a picture it’s a little animated house in that theme’s colors; add a picture (a photo of your house during that sequence works great) to use it instead.', 'marquee' ); ?></p>
		<div class="mq__theme is-classic" data-mq-theme>
			<div class="mq__theme-n">★</div>
			<div class="mq__theme-f">
				<p class="mq__theme-classic"><b><?php esc_html_e( 'Classic', 'marquee' ); ?></b> <?php echo esc_html( sprintf( /* translators: playlist */ __( 'Your regular names break (“%s”). Always there for kids to pick.', 'marquee' ), $pl ) ); ?></p>
				<input type="hidden" data-mq-tname value="<?php esc_attr_e( 'Classic', 'marquee' ); ?>"><input type="hidden" data-mq-tlook value="classic">
				<div><?php marquee_media_field( 'classic_img', __( 'Picture', 'marquee' ), '', $s['classic_img'] ? array( (int) $s['classic_img'] ) : array(), false ); ?></div>
			</div>
			<?php marquee_theme_card_preview(); ?>
		</div>
		<?php
		for ( $i = 0; $i < 6; $i++ ) :
			$t   = isset( $rows[ $i ] ) ? $rows[ $i ] : array( 'name' => '', 'playlist' => '', 'img' => 0, 'start' => 4, 'slot' => 3.75, 'look' => '' );
			$ex  = array( 'Candy Cane Swirl', 'Snowfall', 'Rainbow Party', 'Santa’s Sleigh', 'Gingerbread Town', 'Northern Lights' );
			$pre = 'themes[' . $i . ']';
			?>
			<div class="mq__theme<?php echo ( $t['name'] || 0 === $i ) ? '' : ' is-empty'; ?>" data-mq-theme>
				<div class="mq__theme-n"><?php echo (int) $i + 1; ?></div>
				<div class="mq__theme-f">
					<div class="mq__row">
						<p><label for="mq-th<?php echo (int) $i; ?>n"><?php esc_html_e( 'Name kids see', 'marquee' ); ?></label><input id="mq-th<?php echo (int) $i; ?>n" data-mq-tname name="<?php echo esc_attr( $pre ); ?>[name]" type="text" maxlength="30" value="<?php echo esc_attr( $t['name'] ); ?>" placeholder="<?php echo esc_attr( $ex[ $i ] ); ?>"></p>
						<p><label for="mq-th<?php echo (int) $i; ?>p"><?php esc_html_e( 'FPP playlist', 'marquee' ); ?></label><input id="mq-th<?php echo (int) $i; ?>p" name="<?php echo esc_attr( $pre ); ?>[playlist]" type="text" maxlength="80" value="<?php echo esc_attr( $t['playlist'] ); ?>" placeholder="<?php echo esc_attr( 'Names - ' . $ex[ $i ] ); ?>"></p>
					</div>
					<div class="mq__row mq__row--3">
						<p><label for="mq-th<?php echo (int) $i; ?>s"><?php esc_html_e( 'First name appears at', 'marquee' ); ?> <span class="mq__unit"><?php esc_html_e( 'seconds', 'marquee' ); ?></span></label><input id="mq-th<?php echo (int) $i; ?>s" name="<?php echo esc_attr( $pre ); ?>[start]" type="number" min="0" max="60" step="0.1" value="<?php echo esc_attr( $t['start'] ); ?>"></p>
						<p><label for="mq-th<?php echo (int) $i; ?>l"><?php esc_html_e( 'Time between names', 'marquee' ); ?> <span class="mq__unit"><?php esc_html_e( 'seconds', 'marquee' ); ?></span></label><input id="mq-th<?php echo (int) $i; ?>l" name="<?php echo esc_attr( $pre ); ?>[slot]" type="number" min="2.5" max="10" step="0.05" value="<?php echo esc_attr( $t['slot'] ); ?>"></p>
						<div><?php marquee_media_field( $pre . '[img]', __( 'Picture', 'marquee' ), '', $t['img'] ? array( (int) $t['img'] ) : array(), false ); ?></div>
					</div>
					<?php marquee_name_timeline( '#mq-th' . (int) $i . 's', '#mq-th' . (int) $i . 'l', (float) $s['break_seq'], true ); ?>
					<p class="mq__theme-look"><label for="mq-th<?php echo (int) $i; ?>k"><?php esc_html_e( 'Preview kids see (when there’s no picture)', 'marquee' ); ?></label>
						<select id="mq-th<?php echo (int) $i; ?>k" data-mq-tlook name="<?php echo esc_attr( $pre ); ?>[look]">
							<option value=""><?php esc_html_e( 'Pick for me from the name', 'marquee' ); ?></option>
							<?php foreach ( marquee_theme_looks() as $lk => $ll ) : ?><option value="<?php echo esc_attr( $lk ); ?>" <?php selected( $t['look'] ?? '', $lk ); ?>><?php echo esc_html( $ll ); ?></option><?php endforeach; ?>
						</select>
					</p>
				</div>
				<?php marquee_theme_card_preview(); ?>
			</div>
		<?php endfor; ?>
		<p><button type="button" class="button" data-mq-theme-more><?php esc_html_e( '+ Add another theme', 'marquee' ); ?></button></p>
	</div>
	<details class="mq__faq">
		<summary><?php esc_html_e( 'Questions', 'marquee' ); ?></summary>
		<p><b><?php esc_html_e( 'How do I make a themed sequence?', 'marquee' ); ?></b> <?php esc_html_e( 'Copy your names break sequence in xLights and change the effects on your house to fit the theme (red and white swirls for Candy Cane, white twinkles for Snowfall…). Keep your screens dark where the names go: FPP draws the names on top. Give it soft music with no singing so the spoken names can be heard. Upload it to FPP and put it in its own playlist, named like the examples.', 'marquee' ); ?></p>
		<p><b><?php esc_html_e( 'What are “First name appears at” and “Time between names”?', 'marquee' ); ?></b> <?php esc_html_e( 'Your FPP puts the names up on its own clock while the themed sequence plays: the first one this many seconds in, then one every “time between names” seconds, up to 6. The little timeline under each theme shows exactly where they land. If the theme was made by “Make your sequences” from your names break, use the same numbers as your names break (“Use it” fills them in).', 'marquee' ); ?></p>
		<p><b><?php esc_html_e( 'What if a playlist is missing on my FPP?', 'marquee' ); ?></b> <?php esc_html_e( 'Names for that theme go up in your Classic break instead, and the setup check on this page tells you which playlist it couldn’t find.', 'marquee' ); ?></p>
		<p><b><?php esc_html_e( 'Do I need pictures?', 'marquee' ); ?></b> <?php esc_html_e( 'No. Without one, kids see a little animated picture of a lit-up house in that theme’s colors (pick the look under each theme). A photo or short clip of your own house during that sequence is even better.', 'marquee' ); ?></p>
	</details>
	<?php
}


/** A kid's-eye preview of one "Pick your show" card (kept in step with the row by admin.js). */
function marquee_theme_card_preview() {
	?>
	<div class="mq-tcard" aria-hidden="true">
		<span class="mq-tcard__pic"><canvas data-rr-fx="classic" width="560" height="157"></canvas><img alt="" hidden></span>
		<b data-mq-tcard-name></b>
	</div>
	<?php
}
