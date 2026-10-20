<?php
/**
 * Sequence maker (Settings → Names & FPP → "Sequence maker"): makes the names break
 * sequence, and themed copies of it, as xLights files (.xsq) from the show's layout.
 * Everything happens in the browser (assets/seqmaker.js): the layout file has controller
 * addresses in it, so it's never uploaded. Only a theme the owner describes in words goes to
 * the AI they picked under "Need help?", with the kinds of props they have (names only).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The card body. */
function marquee_seqmaker_box( $s ) {
	$brk     = function_exists( 'marquee_break_info' ) ? marquee_break_info() : null;
	$screens = array_values( array_filter( array_map( function ( $r ) {
		return trim( (string) ( $r['model'] ?? '' ) );
	}, (array) $s['fpp_screens'] ) ) );
	$music_url  = $brk ? $brk['full'] : ( function_exists( 'marquee_break_music_url' ) ? marquee_break_music_url() : '' );
	$music_name = $brk ? 'marquee-break-full.wav' : basename( (string) wp_parse_url( $music_url, PHP_URL_PATH ) );
	$pl         = $s['fpp_playlist'] ? $s['fpp_playlist'] : 'Names in Lights';
	$themes     = array(
		'candy'     => 'Candy Cane Swirl',
		'snow'      => 'Snowfall',
		'rainbow'   => 'Rainbow Party',
		'christmas' => 'Santa’s Sleigh',
		'aurora'    => 'Northern Lights',
		'gold'      => 'Golden Stars',
		'ginger'    => 'Gingerbread Town',
		'icy'       => 'Frozen',
		'purple'    => 'Sugar Plum Party',
	);
	list( $ai_key ) = function_exists( 'marquee_ai_key' ) ? marquee_ai_key() : array( '' );
	?>
	<div class="mq-sm" data-mq-seqmaker
		data-screens="<?php echo esc_attr( wp_json_encode( $screens ) ); ?>"
		data-start="<?php echo esc_attr( $brk ? $brk['start'] : $s['names_start'] ); ?>"
		data-slot="<?php echo esc_attr( $brk ? $brk['slot'] : $s['names_slot'] ); ?>"
		data-dur="<?php echo esc_attr( $s['break_seq'] ); ?>"
		data-music="<?php echo esc_attr( $music_name ); ?>"
		data-built="<?php echo $brk ? '1' : '0'; ?>"
		data-playlist="<?php echo esc_attr( $pl ); ?>">
		<p class="mq__fine"><?php esc_html_e( 'Don’t want to sequence the names break yourself? Marquee makes it from your xLights layout: your screens stay dark for the names, the rest of your house plays gentle effects, and every prop flashes as each name goes up. It can also make themed versions for kids to pick from (Candy Cane Swirl, Snowfall…). You open them in xLights, render, and upload, like any sequence.', 'marquee' ); ?></p>

		<ol class="mq-sm__steps">
			<li>
				<b><?php esc_html_e( 'Your xLights layout', 'marquee' ); ?></b>
				<span><?php esc_html_e( 'The file xlights_rgbeffects.xml in your xLights show folder. It stays on your computer: Marquee reads it right here in your browser.', 'marquee' ); ?></span>
				<label class="mq-sm__drop" data-sm-drop>
					<input type="file" accept=".xml" data-sm-rgb>
					<span class="mq-sm__dropic" aria-hidden="true">📂</span>
					<span><b><?php esc_html_e( 'Choose or drop your xlights_rgbeffects.xml', 'marquee' ); ?></b><small data-sm-status><?php esc_html_e( 'Not chosen yet. On Windows it’s usually in Documents → xLights (your show folder).', 'marquee' ); ?></small></span>
				</label>
			</li>
			<li>
				<b><?php esc_html_e( 'Your names break sequence (if you have one)', 'marquee' ); ?></b>
				<span><?php esc_html_e( 'Optional. Give it your own names break (.xsq) and the themed versions are made from it: same music, same timing, same singing faces, just new colors and effects. Leave it empty and Marquee makes everything from scratch.', 'marquee' ); ?></span>
				<label class="mq-sm__drop is-small" data-sm-drop>
					<input type="file" accept=".xsq" data-sm-xsq>
					<span class="mq-sm__dropic" aria-hidden="true">🎄</span>
					<span><b><?php esc_html_e( 'Choose or drop your names break .xsq (optional)', 'marquee' ); ?></b><small data-sm-status><?php esc_html_e( 'Not chosen: everything is made from scratch.', 'marquee' ); ?></small></span>
				</label>
			</li>
			<li>
				<b><?php esc_html_e( 'What to make', 'marquee' ); ?></b>
				<label class="mq__chk" data-sm-base-wrap><input type="checkbox" data-sm-base checked> <?php echo esc_html( sprintf( /* translators: playlist */ __( 'My names break (“%s”)', 'marquee' ), $pl ) ); ?></label>
				<span class="mq-sm__count" data-sm-count aria-live="polite"></span>
				<span class="mq-sm__chips">
					<?php foreach ( $themes as $k => $n ) : ?>
						<label class="mq-sm__chip" data-look="<?php echo esc_attr( $k ); ?>"><input type="checkbox" data-sm-theme="<?php echo esc_attr( $k ); ?>"> <?php echo esc_html( $n ); ?></label>
					<?php endforeach; ?>
				</span>
				<span class="mq-sm__own">
					<input type="text" maxlength="200" placeholder="<?php esc_attr_e( 'Or describe your own: purple and gold Mardi Gras…', 'marquee' ); ?>" data-sm-want>
					<button type="button" class="button" data-sm-ask><?php esc_html_e( 'Add it', 'marquee' ); ?></button>
				</span>
				<small data-sm-askmsg aria-live="polite"><?php esc_html_e( 'Add as many ideas as you like, one at a time. ', 'marquee' ); ?><?php echo $ai_key ? esc_html__( 'How the AI helps: Marquee reads your layout (step 1) here in your browser and tells the AI only your prop names and what kind each is (trees, arches, rooflines…). The AI picks the colors and an effect for each kind; Marquee then writes the sequence from your layout. Your layout file itself never leaves your computer.', 'marquee' ) : esc_html__( 'Describing your own theme uses the AI under “Need help?” (add a ChatGPT or Claude key there). The ready-made themes don’t need it.', 'marquee' ); ?></small>
			</li>
		</ol>
		<div class="mq-sm__timing"><span aria-hidden="true">⏱</span><div><b><?php esc_html_e( 'You don’t have to set any timing.', 'marquee' ); ?></b> <?php esc_html_e( 'Your FPP puts each name up on its own clock while the sequence plays, so it needs to know when the names go. Marquee works that out for you: from your names break audio (card 3) if you built it, otherwise from the Names timing track in your sequence, otherwise from your settings. Every sequence it makes uses those times, and “Use it” fills them in. Nothing to measure in xLights.', 'marquee' ); ?></div></div>
		<p><button type="button" class="button button-primary" data-sm-make><?php esc_html_e( 'Make my sequences', 'marquee' ); ?></button> <span class="mq__msg" data-sm-msg aria-live="polite"></span></p>

		<div class="mq-sm__out" data-sm-out hidden></div>

		<div class="mq-sm__render" data-sm-render>
			<b><?php esc_html_e( 'Then: render them in xLights (FPP can only play rendered sequences)', 'marquee' ); ?></b>
			<ol>
				<li><?php esc_html_e( 'Save the .xsq files into your xLights show folder (the folder with xlights_rgbeffects.xml in it).', 'marquee' ); ?></li>
				<li><?php
				/* translators: %s: music file name */
				echo esc_html( sprintf( __( 'Open one in xLights. If it asks for the music, pick %s (download it below and put it in your show folder).', 'marquee' ), $music_name ) );
				?></li>
				<li><?php esc_html_e( 'Click Render All, play it in the house preview, change anything you like, then Save. Saving writes the .fseq file next to it: that’s what FPP plays.', 'marquee' ); ?></li>
				<li><?php esc_html_e( 'Send them to your FPP, either way: in xLights, Tools → FPP Connect, tick your FPP and the new sequences, and click Upload (it sends the .fseq and the music together); or in FPP → Content Setup → File Manager, upload the .fseq (Sequences tab) and the music (Audio tab).', 'marquee' ); ?></li>
				<li><?php echo esc_html( sprintf( /* translators: playlist */ __( 'In FPP → Content Setup → Playlists, make a playlist for each one with just that sequence and its music: “%s” for your names break, “Names - Candy Cane Swirl” and so on for the themes. Don’t add them to your show schedule; Marquee plays them between songs.', 'marquee' ), $pl ) ); ?></li>
				<li><?php esc_html_e( 'Themes: click “Use it” next to each one below so kids can pick it, then Save settings. The setup check in card 1 tells you if FPP can’t find a playlist.', 'marquee' ); ?></li>
			</ol>
			<?php if ( $music_url ) : ?><p><a class="button" href="<?php echo esc_url( $music_url ); ?>" download="<?php echo esc_attr( $music_name ); ?>"><?php echo esc_html( sprintf( /* translators: file */ __( 'Download the music (%s)', 'marquee' ), $music_name ) ); ?></a> <small><?php echo $brk ? esc_html__( 'Your names break audio from card 4: the announcer, the soft music and the outro.', 'marquee' ) : esc_html__( 'Soft music for the break. Build your names break audio in card 3 to get one with your announcer in it.', 'marquee' ); ?></small></p><?php endif; ?>
		</div>
	</div>
	<?php
}
