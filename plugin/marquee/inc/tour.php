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
		'rf'       => array(
			'title' => __( 'Connect Remote Falcon', 'marquee' ),
			'steps' => array(
				array( 'img' => $w . 'rf-dashboard.webp', 'box' => array( array( 0.7, 32.3, 12.2, 2.9 ) ), 'where' => 'rf', 't' => __( 'Open Account Settings', 'marquee' ), 'x' => __( 'Log in at remotefalcon.com and click Account Settings.', 'marquee' ) ),
				array( 'img' => $w . 'rf-account.webp', 'box' => array( array( 20.5, 24.2, 4.7, 6 ) ), 'where' => 'rf', 't' => __( 'The Account tab', 'marquee' ), 'x' => __( 'Open the Account tab.', 'marquee' ) ),
				array( 'img' => $w . 'rf-account.webp', 'box' => array( array( 15.2, 59.4, 73.7, 13.5 ) ), 'where' => 'rf', 't' => __( 'Ask for API access', 'marquee' ), 'x' => __( 'Click Request API Access (only the first time). Once it’s approved, the API Access Token shows up here.', 'marquee' ) ),
				array( 'img' => $w . 'rf-account.webp', 'box' => array( array( 70.3, 68.8, 7.6, 10 ), array( 80.5, 60, 8.1, 6.7 ) ), 'where' => 'rf', 't' => __( 'Copy both keys', 'marquee' ), 'x' => __( 'Copy the API Access Token (the copy icon). Then click Refresh Secret and copy the secret key it shows you.', 'marquee' ) ),
				array( 'el' => '#mq-rft', 'up' => 2, 't' => __( 'Paste them here', 'marquee' ), 'x' => __( 'Paste the token and the secret key into these two boxes.', 'marquee' ) ),
				array( 'el' => '[data-mq-rftest]', 'click' => 1, 't' => __( 'Test it, then Save', 'marquee' ), 'x' => __( 'Click Test connection. When it says it works, click Save settings.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-plugins.webp', 'box' => array( array( 4, 50, 29.9, 37.2 ) ), 't' => __( 'The plugin on your FPP', 'marquee' ), 'x' => __( 'On your FPP: Content Setup → Plugin Manager. Install the Remote Falcon plugin. Without it, Now Playing stays empty.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-rfplugin.webp', 'box' => array( array( 4.5, 40, 90.2, 15.6 ) ), 't' => __( 'Set up the plugin', 'marquee' ), 'x' => __( 'Open the plugin, paste your Show Token (Remote Falcon → Account Settings → Account → Show Token) and pick the playlist with your show.', 'marquee' ) ),
				array( 'img' => $w . 'rf-dashboard.webp', 'box' => array( array( 82.6, 17.6, 16.1, 9.8 ) ), 'where' => 'rf', 't' => __( 'Check it’s connected', 'marquee' ), 'x' => __( 'Back on the Remote Falcon dashboard, FPP Plugin says Connected. Now Playing and voting work on your site.', 'marquee' ) ),
			),
		),
		'voice'    => array(
			'title' => __( 'Say each name out loud', 'marquee' ),
			'steps' => array(
				array( 'img' => $w . 'el-keys.webp', 'box' => array( array( 78.1, 51, 7.9, 6.9 ) ), 'where' => 'el', 't' => __( 'Make an API key', 'marquee' ), 'x' => __( 'At elevenlabs.io: Developers → API Keys → Create Key.', 'marquee' ) ),
				array( 'img' => $w . 'el-create.webp', 'box' => array( array( 53.1, 20.1, 44.3, 2.5 ), array( 53.1, 36.3, 44.3, 3.2 ), array( 53.1, 84.8, 44.3, 3.2 ) ), 'where' => 'el', 't' => __( 'Only what Marquee needs', 'marquee' ), 'x' => __( 'Turn on Restrict Key, set Text to Speech to Access and Voices to Read, then Create Key. Copy the key right away: it starts with sk_ and is only shown once.', 'marquee' ) ),
				array( 'el' => '#mq-vkey', 'up' => 1, 't' => __( 'Paste the key', 'marquee' ), 'x' => __( 'Paste it here. It stays on your website’s server.', 'marquee' ) ),
				array( 'el' => '[data-mq-vload]', 'click' => 1, 't' => __( 'Load your voices', 'marquee' ), 'x' => __( 'Click Load my voices, then pick one in the list. Using a Santa voice? Tick “My voice is Santa” too.', 'marquee' ) ),
				array( 'el' => '[data-mq-vtest]', 'click' => 1, 't' => __( 'Hear it', 'marquee' ), 'x' => __( 'Type a name and click Test voice. Too fast? Change the speaking speed.', 'marquee' ) ),
				array( 'el' => 'input[name="voice_on"]', 'up' => 1, 't' => __( 'Turn it on and Save', 'marquee' ), 'x' => __( 'Switch on Speak the names and click Save settings. Your FPP plays each name over the radio as it goes up (names script 3.1 or newer).', 'marquee' ) ),
			),
		),
		'fpp'      => array(
			'title' => __( 'Connect your FPP', 'marquee' ),
			'steps' => array(
				array( 'el' => '.mq-how__modes', 't' => __( 'How names go up', 'marquee' ), 'x' => __( 'First pick one: between songs in a short names break (most shows), or during songs on a spare TV or projector your show doesn’t use. The steps below follow your choice.', 'marquee' ) ),
				array( 'el' => 'input[name="feat[]"][value="names_on"]', 'up' => 1, 't' => __( 'Turn names on', 'marquee' ), 'x' => __( 'Under Features, switch on Your Name in Lights. Kids can send names only while your show is on (your hours in Schedule).', 'marquee' ) ),
				array( 'el' => '#mq-fpl', 'up' => 1, 'mode' => 'break', 't' => __( 'Your names break playlist', 'marquee' ), 'x' => __( 'The FPP playlist that plays your names break. It must exist on your FPP with your names break sequence in it. Don’t have one yet? Card 4 (Sequence maker) makes the sequence, and its Show me how covers making the playlist.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] [data-mq-nt]', 'mode' => 'break', 't' => __( 'When the names appear', 'marquee' ), 'x' => __( 'Usually nothing to do: the Sequence maker and the names break audio (card 3) fill these in. Change them only if you made your own break audio.', 'marquee' ) ),
				array( 'el' => '#mq-lsf', 'up' => 1, 'mode' => 'live', 't' => __( 'How long each name stays up', 'marquee' ), 'x' => __( 'Names go up on your spare screen one after another while your show plays. 6 seconds is a good start.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-overlays.webp', 'box' => array( array( 3.6, 93.5, 91.8, 3.1 ) ), 't' => __( 'Find your screen’s name', 'marquee' ), 'x' => __( 'On your FPP: Input/Output Setup → Pixel Overlay Models. Note the name of each screen that shows names: a matrix (like GARAGE_DOOR), or the HDMI model for a projector or TV.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] .mq__screens', 't' => __( 'Your screens', 'marquee' ), 'x' => __( 'One row per screen: any name, the address of the FPP that drives it (127.0.0.1 = the FPP running the script), and the overlay model name exactly as FPP shows it.', 'marquee' ) ),
				array( 'el' => '[data-mq-save]', 't' => __( 'Save', 'marquee' ), 'x' => __( 'Click Save settings now: the settings file you download next is made from what’s saved.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] .mq__card .mq__media-btns', 't' => __( 'Download both files', 'marquee' ), 'x' => __( 'Download marquee_names.json (your settings and private key: keep it private) and marquee_names.py (the script). Get the newest script here whenever Marquee updates.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-files.webp', 'box' => array( array( 3.8, 66.9, 91.7, 14.3 ) ), 't' => __( 'Upload them to your FPP', 'marquee' ), 'x' => __( 'Put them on the FPP that plays your show playlist: Content Setup → File Manager → Select Files, and upload both. More than one FPP? Use the one that plays the show, not the one with the screen.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-scripts.webp', 'box' => array( array( 5.5, 34, 88.3, 2.6 ) ), 't' => __( 'The script is in Scripts', 'marquee' ), 'x' => __( 'marquee_names.py shows under Scripts. The .json goes under Uploads; that’s fine, the script finds it.', 'marquee' ) ),
				array( 't' => __( 'FPP 10: show all commands', 'marquee' ), 'x' => __( 'On your FPP: Status/Control → Settings → UI → User Interface Level: Advanced. On Basic, FPP hides the Run Script command you need next. You can set it back afterwards.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-presets.webp', 'box' => array( array( 4, 46.2, 92, 11.5 ) ), 't' => __( 'Start it with FPP', 'marquee' ), 'x' => __( 'Status/Control → Command Presets → Add. Preset name: FPPD_STARTED. Command: Run Script (in the Events group). Script: marquee_names.py. Save.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-presets.webp', 'box' => array( array( 45.7, 92.3, 5.8, 5.2 ) ), 't' => __( 'Restart FPP', 'marquee' ), 'x' => __( 'Reboot (or Status page → Restart FPPD). From now on the script starts by itself every time FPP starts.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] .mq__fppstat', 't' => __( 'Check it’s connected', 'marquee' ), 'x' => __( 'Within a minute this says “FPP last checked in” with a setup check: a green tick for each part, or a red line that says what to fix. Fix it and restart FPPD again.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-showplaylist.webp', 'box' => array( array( 11.8, 57.6, 85.4, 40 ) ), 'mode' => 'break', 't' => __( 'Play your show as a playlist', 'marquee' ), 'x' => __( 'Start your own show playlist (whatever you named it; this one is “2026 Playlist”) from the Status page or your schedule. Names go up only while it plays on this FPP: after the current song, the names break slips in, then your playlist carries on. A single sequence or Play Media won’t bring the names break.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-showplaylist.webp', 'box' => array( array( 11.8, 57.6, 85.4, 40 ) ), 'mode' => 'live', 't' => __( 'Play your show as a playlist', 'marquee' ), 'x' => __( 'Start your own show playlist (whatever you named it) from the Status page or your schedule. Names go up on your spare screen only while it plays on this FPP, within about 20 seconds of being sent.', 'marquee' ) ),
				array( 't' => __( 'Send a test name', 'marquee' ), 'x' => __( 'During show hours, open your website’s Kids’ Corner and send your own first name. It goes up after the current song (or right away during songs). Nothing? FPP → File Manager → Logs → marquee_names.log says why. Using Remote Falcon jukebox or voting? It also picks what plays next, so test once with viewer control off.', 'marquee' ) ),
			),
		),
		'seqmaker' => array(
			'title' => __( 'Sequence maker', 'marquee' ),
			'steps' => array(
				array( 'el' => '[data-sm-rgb]', 'up' => 1, 't' => __( 'Choose your layout', 'marquee' ), 'x' => __( 'Click this box (or drag the file onto it) and pick xlights_rgbeffects.xml from your xLights show folder. It stays on your computer: Marquee reads it right here.', 'marquee' ) ),
				array( 'el' => '[data-sm-xsq]', 'up' => 1, 't' => __( 'Your names break (optional)', 'marquee' ), 'x' => __( 'Already have a names break sequence? Drop its .xsq here and the themes are made from it, keeping its music, timing and singing faces. No names break yet? Leave it empty and Marquee makes one.', 'marquee' ) ),
				array( 'el' => '.mq-sm__chips', 't' => __( 'Pick what to make', 'marquee' ), 'x' => __( 'Tick the themes you want. 3 or 4 is plenty: kids pick faster, and each one is a sequence to render.', 'marquee' ) ),
				array( 'el' => '.mq-sm__own', 't' => __( 'Or describe your own', 'marquee' ), 'x' => __( 'Type an idea, like “purple and gold Mardi Gras”, and click Add it. The AI picks the colors and effects; add as many as you like, one at a time.', 'marquee' ) ),
				array( 'el' => '.mq-sm__timing', 't' => __( 'No timing to set', 'marquee' ), 'x' => __( 'The tricky part is done for you: Marquee makes sure each name shows up right after the intro and then every few seconds, lined up with the music. “Use it” fills in the numbers later.', 'marquee' ) ),
				array( 'el' => '[data-sm-make]', 'click' => 1, 't' => __( 'Make them', 'marquee' ), 'x' => __( 'Click Make my sequences. It takes a second.', 'marquee' ) ),
				array( 'el' => '[data-sm-render]', 't' => __( 'Download each one', 'marquee' ), 'x' => __( 'Each sequence gets a Download .xsq button. Save them into your xLights show folder (the one with xlights_rgbeffects.xml in it). Download the music too, with the button at the bottom here.', 'marquee' ) ),
				array( 'art' => 'xl-open', 't' => __( 'Open it in xLights', 'marquee' ), 'x' => __( 'In xLights: File → Open Sequence and pick the new .xsq. If it asks for the music, choose the music file you downloaded.', 'marquee' ) ),
				array( 'art' => 'xl-render', 't' => __( 'Render it and Save', 'marquee' ), 'x' => __( 'Click Render All, play it in the house preview and change anything you like. Then Save: that writes the .fseq file, which is what FPP plays. FPP can’t play the .xsq itself.', 'marquee' ) ),
				array( 'art' => 'xl-fppconnect', 't' => __( 'Send it to your FPP', 'marquee' ), 'x' => __( 'Easiest: Tools → FPP Connect, tick your FPP and the new sequences, and click Upload. It sends the .fseq and the music together. (Or upload them by hand in FPP → File Manager.)', 'marquee' ) ),
				array( 'img' => $w . 'fpp-seqfiles.webp', 'box' => array( array( 5.4, 55, 88.5, 5 ) ), 't' => __( 'Check it arrived', 'marquee' ), 'x' => __( 'On your FPP: Content Setup → File Manager → Sequences. The new .fseq is in the list.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-playlists.webp', 'box' => array( array( 88, 28.5, 8.2, 6.5 ) ), 't' => __( 'Make a playlist for each one', 'marquee' ), /* translators: %s: names break playlist */ 'x' => sprintf( __( 'Content Setup → Playlists → New Playlist, one per sequence. Your names break goes in a playlist named exactly “%s” (the name in card 1). Each theme gets its own, named the way Marquee shows it, for example “Names - Candy Cane Swirl”. Don’t add them to your show schedule: Marquee plays them between songs.', 'marquee' ), $pl ) ),
				array( 'img' => $w . 'fpp-playlist-edit.webp', 'box' => array( array( 80.5, 39, 14.5, 6.5 ) ), 't' => __( 'Add the sequence', 'marquee' ), 'x' => __( 'In the new playlist, click Add a Sequence/Entry.', 'marquee' ) ),
				array( 'img' => $w . 'fpp-new-entry.webp', 'box' => array( array( 13, 11.5, 22.5, 26.5 ), array( 93.9, 90.2, 3.6, 5 ) ), 't' => __( 'Sequence and its music', 'marquee' ), 'x' => __( 'Type: Sequence and Media. Pick the new sequence; its music is picked for you. Click Add, then Save Playlist.', 'marquee' ) ),
				array( 'el' => '[data-sm-render]', 't' => __( 'Back here: use it', 'marquee' ), 'x' => __( 'For each theme, click “Use it” next to its Download button, then Save settings. Card 1’s setup check tells you within a minute if FPP can’t find a playlist.', 'marquee' ) ),
			),
		),
		'break'    => array(
			'title' => __( 'Names break audio', 'marquee' ),
			'steps' => array(
				array( 't' => __( 'Before you start', 'marquee' ), /* translators: %s: names break playlist */ 'x' => sprintf( __( 'You need three things first: a voice set up in card 2, the names script running on your FPP (card 1, Connect your FPP), and a playlist on your FPP named “%s” with your names break sequence in it (card 4 makes the sequence). Marquee puts the new sound into that playlist.', 'marquee' ), $pl ) ),
				array( 'el' => '[data-mq-bdemo]', 't' => __( 'What you’re making', 'marquee' ), 'x' => __( 'The sound your radio plays during the names break: an intro, soft music while the names go up (your FPP says each name live over it), and an outro.', 'marquee' ) ),
				array( 'el' => '#mq-bintro', 't' => __( 'Write the intro', 'marquee' ), 'x' => __( 'What the announcer says before the names. Click one of the ideas under it to start from one. Keep it short: about one breath.', 'marquee' ) ),
				array( 'el' => '#mq-boutro', 't' => __( 'Write the outro', 'marquee' ), 'x' => __( 'What comes after the names, like how to send a name. Write your website the way people say it: “lights on main street dot com”.', 'marquee' ) ),
				array( 'el' => '#mq-bseq', 't' => __( 'Your sequence length', 'marquee' ), 'x' => __( 'How long your names break sequence is, in seconds. Marquee fits the intro, 6 names and the outro into it.', 'marquee' ) ),
				array( 'el' => 'input[name="break_music"]', 'up' => 1, 't' => __( 'Music (optional)', 'marquee' ), 'x' => __( 'Your own soft instrumental: no singing or talking, or it covers the names. Leave it empty for the soft music box that comes with Marquee.', 'marquee' ) ),
				array( 'el' => '[data-mq-break-build]', 'click' => 1, 't' => __( 'Build it', 'marquee' ), 'x' => __( 'Click Build. Your voice reads the lines, and Marquee mixes three versions at song loudness: the full break, and shorter ones for when only 1 or 2 names are waiting.', 'marquee' ) ),
				array( 'el' => '[data-mq-break-prev]:not([hidden]), #mq-break .mq__fppstat, [data-mq-break-build]', 'up' => 0, 't' => __( 'Listen', 'marquee' ), 'x' => __( 'Three players appear. They sound alike on purpose: the names aren’t in them, because your FPP says each name live. The short ones just reach the outro sooner.', 'marquee' ) ),
				array( 'el' => '[data-mq-nt]', 't' => __( 'Timing is set for you', 'marquee' ), 'x' => __( '“When the first name appears” and “Time between names” in card 1 are filled in to match your intro. The timeline shows where each name lands.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] .mq__fppstat', 't' => __( 'Your FPP does the rest', 'marquee' ), /* translators: %s: playlist name */ 'x' => sprintf( __( 'Within a minute your FPP downloads the three files and sets up the playlists by itself (“%1$s”, “%1$s 2” and “%1$s 1”). The setup check in card 1 shows a green tick when it’s done.', 'marquee' ), $pl ) ),
			),
		),
		'themes'   => array(
			'title' => __( 'Themes kids can pick', 'marquee' ),
			'steps' => array(
				array( 'el' => '.mq-sm__cta', 't' => __( 'First, the sequences', 'marquee' ), 'x' => __( 'Each theme is its own short sequence on your FPP. Don’t have them yet? “Make them for me” makes them from your layout (card 4), with its own walkthrough.', 'marquee' ) ),
				array( 'el' => 'input[name="themes_on"]', 'up' => 1, 't' => __( 'Turn it on', 'marquee' ), 'x' => __( 'Switch on “Let kids pick a themed sequence”. Your regular names break stays as the “Classic” choice.', 'marquee' ) ),
				array( 'el' => '#mq-th0n', 't' => __( 'The name kids see', 'marquee' ), 'x' => __( 'Short and fun: Candy Cane Swirl, Snowfall…', 'marquee' ) ),
				array( 'el' => '#mq-th0p', 't' => __( 'Its playlist on your FPP', 'marquee' ), 'x' => __( 'Exactly the name of the playlist you made on your FPP, for example “Names - Candy Cane Swirl”. A typo means names for this theme go up in the Classic break instead.', 'marquee' ) ),
				array( 'el' => '.mq__themes [data-mq-nt]', 't' => __( 'When the names appear', 'marquee' ), 'x' => __( 'When the names appear in this sequence. If it was made by the Sequence maker, “Use it” already filled these in to match: leave them. The bar shows where each name lands.', 'marquee' ) ),
				array( 'el' => '.mq__themes .mq__theme:not(.is-classic) [data-mq-media]', 't' => __( 'A picture, or a look', 'marquee' ), 'x' => __( 'A photo of your house during that sequence works great. No picture? Pick a look and kids see a little animated house in those colors.', 'marquee' ) ),
				array( 'el' => '.mq__themes .mq__theme:not(.is-classic) .mq-tcard', 't' => __( 'What kids see', 'marquee' ), 'x' => __( 'This card is exactly what kids tap on your website. It updates as you type.', 'marquee' ) ),
				array( 'el' => '[data-mq-save]', 't' => __( 'Save', 'marquee' ), 'x' => __( 'Click Save settings. The themes show up for kids right away.', 'marquee' ) ),
				array( 'el' => '[data-pane="4"] .mq__fppstat', 't' => __( 'Check your FPP found them', 'marquee' ), 'x' => __( 'Within a minute, the setup check in card 1 lists each theme’s playlist with a green tick, or tells you which one it can’t find.', 'marquee' ) ),
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
