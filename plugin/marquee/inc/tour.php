<?php
/**
 * "Show me how": guided walkthroughs on the real Settings page. A pointer moves to each control,
 * a spotlight shows it, and a card says what to do; steps outside WordPress (xLights, FPP) are
 * shown as pictures. Plays by itself like a video; Pause, Back and Next are there too.
 * Engine: assets/tour.js. A step whose control isn't on the page is shown as a centered card.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_tours() {
	$w  = MARQUEE_URL . 'assets/walk/';
	$pl = marquee_get()['fpp_playlist'] ? marquee_get()['fpp_playlist'] : 'Names in Lights';
	return array(
		'seqmaker' => array(
			'title' => __( 'Make your sequences', 'marquee' ),
			'steps' => array(
				array( 'el' => '[data-sm-rgb]', 'up' => 1, 't' => __( 'Choose your layout', 'marquee' ), 'x' => __( 'Click this box (or drag the file onto it) and pick xlights_rgbeffects.xml from your xLights show folder. It stays on your computer: Marquee reads it right here.', 'marquee' ) ),
				array( 'el' => '[data-sm-xsq]', 'up' => 1, 't' => __( 'Your names break (optional)', 'marquee' ), 'x' => __( 'Already have a names break sequence? Drop its .xsq here and the themes are made from it, keeping its music, timing and singing faces. No names break yet? Leave it empty and Marquee makes one.', 'marquee' ) ),
				array( 'el' => '.mq-sm__chips', 't' => __( 'Pick what to make', 'marquee' ), 'x' => __( 'Tick the themes you want. 3 or 4 is plenty: kids pick faster, and each one is a sequence to render.', 'marquee' ) ),
				array( 'el' => '.mq-sm__own', 't' => __( 'Or describe your own', 'marquee' ), 'x' => __( 'Type an idea, like “purple and gold Mardi Gras”, and click Add it. The AI picks the colors and effects; add as many as you like, one at a time.', 'marquee' ) ),
				array( 'el' => '[data-sm-make]', 'click' => 1, 't' => __( 'Make them', 'marquee' ), 'x' => __( 'Click Make my sequences. It takes a second.', 'marquee' ) ),
				array( 'el' => '[data-sm-render]', 't' => __( 'Download each one', 'marquee' ), 'x' => __( 'Each sequence gets a Download .xsq button. Save them into your xLights show folder (the one with xlights_rgbeffects.xml in it). Download the music too, with the button at the bottom here.', 'marquee' ) ),
				array( 'art' => 'xl-open', 't' => __( 'Open it in xLights', 'marquee' ), 'x' => __( 'In xLights: File → Open Sequence and pick the new .xsq. If it asks for the music, choose the music file you downloaded.', 'marquee' ) ),
				array( 'art' => 'xl-render', 't' => __( 'Render it and Save', 'marquee' ), 'x' => __( 'Click Render All, play it in the house preview and change anything you like. Then Save: that writes the .fseq file, which is what FPP plays. FPP can’t play the .xsq itself.', 'marquee' ) ),
				array( 'art' => 'xl-fppconnect', 't' => __( 'Send it to your FPP', 'marquee' ), 'x' => __( 'Easiest: Tools → FPP Connect, tick your FPP and the new sequences, and click Upload. It sends the .fseq and the music together. (Or upload them by hand in FPP → File Manager.)', 'marquee' ) ),
				array( 'img' => $w . 'fpp-seqfiles.webp', 'box' => array( array( 5.4, 55, 88.5, 5 ) ), 't' => __( 'Check it arrived', 'marquee' ), 'x' => __( 'On your FPP: Content Setup → File Manager → Sequences. The new .fseq is in the list.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-playlists.webp', 'box' => array( array( 88, 28.5, 8.2, 6.5 ) ), 't' => __( 'Make a playlist for it', 'marquee' ), 'x' => __( 'Content Setup → Playlists → New Playlist. Name it the way Marquee shows it, for example “Names - Candy Cane Swirl”. Don’t add it to your show schedule: Marquee plays it between songs.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-playlist-edit.webp', 'box' => array( array( 80.5, 39, 14.5, 6.5 ) ), 't' => __( 'Add the sequence', 'marquee' ), 'x' => __( 'In the new playlist, click Add a Sequence/Entry.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-new-entry.webp', 'box' => array( array( 13, 11.5, 22.5, 26.5 ), array( 93.9, 90.2, 3.6, 5 ) ), 't' => __( 'Sequence and its music', 'marquee' ), 'x' => __( 'Type: Sequence and Media. Pick the new sequence; its music is picked for you. Click Add, then Save Playlist.', 'marquee' ) ),
				array( 'el' => '[data-sm-render]', 't' => __( 'Back here: use it', 'marquee' ), 'x' => __( 'For each theme, click “Use it” next to its Download button, then Save settings. Card 1’s setup check tells you within a minute if FPP can’t find a playlist.', 'marquee' ) ),
			),
		),
		'break'    => array(
			'title' => __( 'Names break audio', 'marquee' ),
			'steps' => array(
				array( 'el' => '[data-mq-bdemo]', 't' => __( 'What you’re making', 'marquee' ), 'x' => __( 'The sound your radio plays during the names break: an intro, soft music while the names go up (your FPP says each name live over it), and an outro.', 'marquee' ) ),
				array( 'el' => '#mq-bintro', 't' => __( 'Write the intro', 'marquee' ), 'x' => __( 'What the announcer says before the names. Click one of the ideas under it to start from one. Keep it short: about one breath.', 'marquee' ) ),
				array( 'el' => '#mq-boutro', 't' => __( 'Write the outro', 'marquee' ), 'x' => __( 'What comes after the names, like how to send a name. Write your website the way people say it: “lights on main street dot com”.', 'marquee' ) ),
				array( 'el' => '#mq-bseq', 't' => __( 'Your sequence length', 'marquee' ), 'x' => __( 'How long your names break sequence is, in seconds. Marquee fits the intro, 6 names and the outro into it.', 'marquee' ) ),
				array( 'el' => 'input[name="break_music"]', 'up' => 1, 't' => __( 'Music (optional)', 'marquee' ), 'x' => __( 'Your own soft instrumental: no singing or talking, or it covers the names. Leave it empty for the soft music box that comes with Marquee.', 'marquee' ) ),
				array( 'el' => '[data-mq-break-build]', 'click' => 1, 't' => __( 'Build it', 'marquee' ), 'x' => __( 'Click Build. Your voice reads the lines, and Marquee mixes three versions at song loudness: the full break, and shorter ones for when only 1 or 2 names are waiting.', 'marquee' ) ),
				array( 'el' => '[data-mq-break-prev]:not([hidden]), #mq-break .mq__fppstat, [data-mq-break-build]', 'up' => 0, 't' => __( 'Listen', 'marquee' ), 'x' => __( 'Three players appear. They sound alike on purpose: the names aren’t in them, because your FPP says each name live. The short ones just reach the outro sooner.', 'marquee' ) ),
				array( 'el' => '[data-mq-nt]', 't' => __( 'Timing is set for you', 'marquee' ), 'x' => __( '“When the first name appears” and “Time between names” in card 1 are filled in to match your intro. The timeline shows where each name lands.', 'marquee' ) ),
				array( 'el' => '.mq__fppstat', 't' => __( 'Your FPP does the rest', 'marquee' ), /* translators: %s: playlist name */ 'x' => sprintf( __( 'Within a minute your FPP downloads the three files and sets up the playlists by itself (“%1$s”, “%1$s 2” and “%1$s 1”). The setup check in card 1 shows a green tick when it’s done.', 'marquee' ), $pl ) ),
			),
		),
		'themes'   => array(
			'title' => __( 'Themed sequences', 'marquee' ),
			'steps' => array(
				array( 'el' => '.mq-sm__cta', 't' => __( 'First, the sequences', 'marquee' ), 'x' => __( 'Each theme is its own short sequence on your FPP. Don’t have them yet? “Make them for me” makes them from your layout (card 2), with its own walkthrough.', 'marquee' ) ),
				array( 'el' => 'input[name="themes_on"]', 'up' => 1, 't' => __( 'Turn it on', 'marquee' ), 'x' => __( 'Switch on “Let kids pick a themed sequence”. Your regular names break stays as the “Classic” choice.', 'marquee' ) ),
				array( 'el' => '#mq-th0n', 't' => __( 'The name kids see', 'marquee' ), 'x' => __( 'Short and fun: Candy Cane Swirl, Snowfall…', 'marquee' ) ),
				array( 'el' => '#mq-th0p', 't' => __( 'Its playlist on your FPP', 'marquee' ), 'x' => __( 'Exactly the name of the playlist you made on your FPP, for example “Names - Candy Cane Swirl”. A typo means names for this theme go up in the Classic break instead.', 'marquee' ) ),
				array( 'el' => '.mq__themes [data-mq-nt]', 't' => __( 'When the names appear', 'marquee' ), 'x' => __( 'Same idea as your names break: when the first name appears in this sequence, and the time between names. Made by “Make your sequences” from your names break? Use the same numbers (“Use it” fills them in).', 'marquee' ) ),
				array( 'el' => '.mq__themes .mq__theme:not(.is-classic) [data-mq-media]', 't' => __( 'A picture, or a look', 'marquee' ), 'x' => __( 'A photo of your house during that sequence works great. No picture? Pick a look and kids see a little animated house in those colors.', 'marquee' ) ),
				array( 'el' => '.mq__themes .mq__theme:not(.is-classic) .mq-tcard', 't' => __( 'What kids see', 'marquee' ), 'x' => __( 'This card is exactly what kids tap on your website. It updates as you type.', 'marquee' ) ),
				array( 'el' => '[data-mq-save]', 't' => __( 'Save', 'marquee' ), 'x' => __( 'Click Save settings. The themes show up for kids right away.', 'marquee' ) ),
				array( 'el' => '.mq__fppstat', 't' => __( 'Check your FPP found them', 'marquee' ), 'x' => __( 'Within a minute, the setup check in card 1 lists each theme’s playlist with a green tick, or tells you which one it can’t find.', 'marquee' ) ),
			),
		),
	);
}

/** The "Show me how" button for one walkthrough. */
function marquee_tour_button( $id ) {
	$t = marquee_tours();
	if ( ! isset( $t[ $id ] ) ) {
		return;
	}
	printf(
		'<button type="button" class="button mq-tour__btn" data-mq-tour="%s" data-tour="%s"><span aria-hidden="true">▶</span> %s</button>',
		esc_attr( $id ),
		esc_attr( wp_json_encode( $t[ $id ] ) ),
		esc_html__( 'Show me how', 'marquee' )
	);
}
