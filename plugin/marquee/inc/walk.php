<?php
/**
 * Step-by-step walkthroughs on the Settings page: real screenshots (Remote Falcon,
 * FPP, ElevenLabs) with the spot to click highlighted, playing like a slideshow.
 * Screenshots live in assets/walk/ (secrets blurred).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each slide: image, its crop box in the original screenshot (x1, y1, x2, y2),
 * highlight boxes in original coordinates, caption.
 */
function marquee_walks() {
	return array(
		'rf'    => array(
			'title'  => __( 'Connect Remote Falcon, step by step', 'marquee' ),
			'slides' => array(
				array( 'rf-dashboard', array( 0, 0, 1372, 800 ), array( array( 10, 258, 178, 281 ) ), __( 'Log in at remotefalcon.com and click Account Settings.', 'marquee' ) ),
				array( 'rf-account', array( 0, 0, 1351, 480 ), array( array( 277, 116, 340, 145 ) ), __( 'Open the Account tab.', 'marquee' ) ),
				array( 'rf-account', array( 0, 0, 1351, 480 ), array( array( 205, 285, 1200, 350 ) ), __( 'Click Request API Access (only the first time). Once it’s approved, the API Access Token shows up here.', 'marquee' ) ),
				array( 'rf-account', array( 0, 0, 1351, 480 ), array( array( 950, 330, 1052, 378 ), array( 1088, 288, 1198, 320 ) ), __( 'Copy the API Access Token (the copy icon). Then click Refresh Secret and copy the secret key it shows you.', 'marquee' ) ),
				array( 'mq-rf', array( 118, 25, 980, 540 ), array( array( 338, 222, 945, 312 ) ), __( 'Paste both here, in Marquee → Remote Falcon, click Test connection, then Save settings.', 'marquee' ) ),
				array( 'fpp-plugins', array( 0, 0, 1372, 470 ), array( array( 55, 235, 465, 410 ) ), __( 'On your FPP: Content Setup → Plugin Manager. Install the Remote Falcon plugin.', 'marquee' ) ),
				array( 'fpp-rfplugin', array( 0, 0, 1372, 720 ), array( array( 61, 288, 1299, 400 ) ), __( 'Open the plugin, paste your Show Token (Remote Falcon → Account Settings → Account → Show Token) and pick the playlist with your show.', 'marquee' ) ),
				array( 'rf-dashboard', array( 0, 0, 1372, 800 ), array( array( 1133, 141, 1354, 219 ) ), __( 'Back on the Remote Falcon dashboard, FPP Plugin says Connected. Now Playing and voting work on your site.', 'marquee' ) ),
			),
		),
		'fpp'   => array(
			'title'  => __( 'Connect your FPP, step by step', 'marquee' ),
			'slides' => array(
				array( 'fpp-overlays', array( 0, 0, 1372, 800 ), array( array( 50, 748, 1310, 773 ) ), __( 'Find your screen’s name: FPP → Input/Output Setup → Pixel Overlay Models (a matrix like GARAGE_DOOR, or the HDMI model for a projector or TV).', 'marquee' ) ),
				array( 'mq-fpp', array( 118, 25, 980, 600 ), array( array( 385, 350, 925, 560 ) ), __( 'Type each screen here with its FPP’s address and that model name. Save settings, then download both files.', 'marquee' ) ),
				array( 'fpp-files', array( 0, 0, 1372, 860 ), array( array( 52, 575, 1310, 698 ) ), __( 'On your FPP: Content Setup → File Manager → Select Files, and upload marquee_names.py and marquee_names.json.', 'marquee' ) ),
				array( 'fpp-scripts', array( 0, 0, 1372, 860 ), array( array( 75, 292, 1287, 314 ) ), __( 'The script shows under Scripts. (The .json goes under Uploads; that’s fine, the script finds it.)', 'marquee' ) ),
				array( 'fpp-presets', array( 0, 0, 1372, 520 ), array( array( 55, 240, 1317, 300 ) ), __( 'Status/Control → Command Presets → Add: name it FPPD_STARTED, pick Run Script (in the Events group) and marquee_names.py. Save. No Run Script in the list? Set Settings → UI → User Interface Level to Advanced first.', 'marquee' ) ),
				array( 'fpp-presets', array( 0, 0, 1372, 520 ), array( array( 627, 480, 706, 507 ) ), __( 'Click Reboot. Within a minute this card says Connected and shows the setup check with green ticks.', 'marquee' ) ),
				array( 'fpp-playlists', array( 0, 0, 1351, 480 ), array( array( 55, 190, 453, 322 ) ), __( 'Start your show as a playlist (like this one). Names go up only while your show playlist plays on this FPP, the one running the script: a single sequence or Play Media won’t bring the names break. More than one FPP? Put the script on the one that plays the show.', 'marquee' ) ),
			),
		),
		'voice' => array(
			'title'  => __( 'Get your ElevenLabs key, step by step', 'marquee' ),
			'slides' => array(
				array( 'el-keys', array( 188, 0, 1372, 420 ), array( array( 1113, 214, 1206, 243 ) ), __( 'At elevenlabs.io: Developers → API Keys → Create Key.', 'marquee' ) ),
				array( 'el-create', array( 600, 0, 1372, 869 ), array( array( 1010, 175, 1352, 197 ), array( 1010, 315, 1352, 343 ), array( 1010, 737, 1352, 765 ) ), __( 'Turn on Restrict Key, set Text to Speech to Access and Voices to Read, then Create Key. Copy the key right away: it starts with sk_ and is only shown once.', 'marquee' ) ),
				array( 'mq-voice', array( 118, 25, 980, 700 ), array( array( 385, 415, 930, 575 ) ), __( 'Paste it here, click Load my voices, pick a voice and click Test voice. Turn on Speak the names and Save settings.', 'marquee' ) ),
			),
		),
	);
}

/** One walkthrough: a slideshow of screenshots with the spot to click highlighted. */
function marquee_walk( $id, $open = false ) {
	$w = marquee_walks()[ $id ] ?? null;
	if ( ! $w ) {
		return;
	}
	$n = count( $w['slides'] );
	?>
	<details class="mq-walk" data-mq-walk <?php echo $open ? 'open' : ''; ?>>
		<summary><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span> <?php echo esc_html( $w['title'] ); ?> <small><?php /* translators: %d: number of steps */ echo esc_html( sprintf( _n( '%d step', '%d steps', $n, 'marquee' ), $n ) ); ?></small></summary>
		<div class="mq-walk__body">
			<div class="mq-walk__stage">
				<?php
				foreach ( $w['slides'] as $i => $sl ) :
					list( $img, $crop, $boxes, $cap ) = $sl;
					$cw = $crop[2] - $crop[0];
					$ch = $crop[3] - $crop[1];
					?>
					<figure class="mq-walk__slide<?php echo 0 === $i ? ' is-on' : ''; ?>" data-walk-slide aria-hidden="<?php echo 0 === $i ? 'false' : 'true'; ?>">
						<div class="mq-walk__shot" style="aspect-ratio: <?php echo (int) $cw; ?> / <?php echo (int) $ch; ?>">
							<img src="<?php echo esc_url( MARQUEE_URL . 'assets/walk/' . $img . '.webp' ); ?>" alt="" loading="lazy" decoding="async">
							<?php foreach ( $boxes as $b ) : ?>
								<span class="mq-walk__hl" style="left:<?php echo esc_attr( round( ( $b[0] - $crop[0] ) / $cw * 100, 2 ) ); ?>%;top:<?php echo esc_attr( round( ( $b[1] - $crop[1] ) / $ch * 100, 2 ) ); ?>%;width:<?php echo esc_attr( round( ( $b[2] - $b[0] ) / $cw * 100, 2 ) ); ?>%;height:<?php echo esc_attr( round( ( $b[3] - $b[1] ) / $ch * 100, 2 ) ); ?>%"></span>
							<?php endforeach; ?>
						</div>
						<figcaption><b><?php echo (int) $i + 1; ?></b> <?php echo esc_html( $cap ); ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
			<div class="mq-walk__nav">
				<button type="button" class="button" data-walk-prev aria-label="<?php esc_attr_e( 'Previous step', 'marquee' ); ?>">‹</button>
				<span class="mq-walk__dots"><?php for ( $i = 0; $i < $n; $i++ ) : ?><button type="button" data-walk-dot="<?php echo (int) $i; ?>" class="<?php echo 0 === $i ? 'is-on' : ''; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Step %d', 'marquee' ), $i + 1 ) ); ?>"></button><?php endfor; ?></span>
				<button type="button" class="button" data-walk-next aria-label="<?php esc_attr_e( 'Next step', 'marquee' ); ?>">›</button>
				<button type="button" class="button-link" data-walk-play><?php esc_html_e( 'Pause', 'marquee' ); ?></button>
			</div>
		</div>
	</details>
	<?php
}
