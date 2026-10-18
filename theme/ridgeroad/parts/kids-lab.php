<?php
/**
 * Kids' Christmas Light Lab (v3): pick a decoration, a style and a color, then play it.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$base  = ridgeroad_asset_url( 'lightlab/' );
$songs = array();
foreach ( ridgeroad_lab_songs() as $id => $s ) {
	$songs[] = array( 'id' => $id, 'n' => $s['name'], 'src' => isset( $s['src'] ) ? $s['src'] : $base . 'songs/' . $s['file'] . '?v=' . RIDGEROAD_VERSION, 'bpm' => $s['bpm'], 'offset' => $s['offset'], 'len' => $s['len'] );
}
$play_ico = '<svg viewBox="0 0 24 24" aria-hidden="true" data-ico><path d="M8 5v14l11-7z"/></svg>';
?>
<section id="light-lab" class="rr-ll" data-rr-lab data-base="<?php echo esc_url( $base ); ?>" data-v="<?php echo esc_attr( RIDGEROAD_VERSION ); ?>" data-songs="<?php echo esc_attr( wp_json_encode( $songs ) ); ?>"<?php if ( ridgeroad_is_marquee() ) : ?> data-sign="<?php echo esc_attr( ridgeroad_mod( 'radio' ) ); ?>"<?php endif; ?> aria-labelledby="rr-ll-h">
	<h3 id="rr-ll-h" class="screen-reader-text"><?php esc_html_e( 'Christmas Light Lab', 'ridgeroad' ); ?></h3>

	<div class="rr-ll__bar">
		<label for="rr-ll-song"><?php esc_html_e( 'Song', 'ridgeroad' ); ?></label>
		<select id="rr-ll-song" data-ll="song"></select>
		<span class="rr-ll__time" data-ll="time">0:00</span>
		<button type="button" class="rr-ll__play" data-ll="play" aria-pressed="false"><span class="rr-ll__ico"><?php echo $play_ico; // phpcs:ignore ?></span><span data-ll="playtxt"><?php esc_html_e( 'Play my show', 'ridgeroad' ); ?></span></button>
	</div>

	<div class="rr-ll__watching" role="status">
		<?php esc_html_e( 'Now watching the show by', 'ridgeroad' ); ?> <strong data-ll="watchname"></strong>
		<button type="button" class="rr-btn rr-btn--gold" data-ll="unwatch"><?php esc_html_e( 'Back to my show', 'ridgeroad' ); ?></button>
	</div>

	<div class="rr-ll__stage">
		<div class="rr-ll__scroll"><canvas data-ll="canvas" width="1672" height="468" aria-label="<?php echo esc_attr( ridgeroad_is_marquee() ? __( 'A house with Christmas lights. Tap a decoration to pick it.', 'ridgeroad' ) : __( 'Our house with Christmas lights. Tap a decoration to pick it.', 'ridgeroad' ) ); ?>"></canvas></div>
		<p class="rr-ll__loading"><?php esc_html_e( 'Plugging in the lights…', 'ridgeroad' ); ?></p>
		<p class="rr-ll__hint" data-ll="hint"><?php esc_html_e( 'Tap a decoration on the house!', 'ridgeroad' ); ?></p>
		<p class="rr-ll__picked" data-ll="picked" hidden></p>
		<button type="button" class="rr-ll__full" data-ll="full" aria-label="<?php esc_attr_e( 'Full screen', 'ridgeroad' ); ?>">&#x26F6;</button>
	</div>

	<div class="rr-ll__wiz">
		<ol class="rr-ll__steps">
			<li><button type="button" data-ll-goto="1"><i>1</i><span><?php esc_html_e( 'Pick a decoration', 'ridgeroad' ); ?><small data-ll-sum="1"></small></span></button></li>
			<li><button type="button" data-ll-goto="2"><i>2</i><span><?php esc_html_e( 'Pick how it lights up', 'ridgeroad' ); ?><small data-ll-sum="2"></small></span></button></li>
			<li><button type="button" data-ll-goto="3"><i>3</i><span><?php esc_html_e( 'Pick a color', 'ridgeroad' ); ?><small data-ll-sum="3"></small></span></button></li>
		</ol>
		<div class="rr-ll__guide"><img src="<?php echo esc_url( ridgeroad_asset_url( 'img/kids/elf.webp' ) ); ?>" alt="" width="248" height="360" loading="lazy"><p class="rr-ll__say" data-ll="say" aria-live="polite"></p></div>
		<div class="rr-ll__opts rr-ll__opts--props" data-ll-step="1"></div>
		<div class="rr-ll__opts rr-ll__opts--styles" data-ll-step="2" hidden></div>
		<div class="rr-ll__opts rr-ll__opts--colors" data-ll-step="3" hidden></div>
		<div class="rr-ll__done" data-ll-step="4" hidden>
			<button type="button" class="rr-ll__btn rr-ll__btn--again" data-ll="again"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'Light up something else', 'ridgeroad' ); ?></button>
			<button type="button" class="rr-ll__btn rr-ll__btn--go" data-ll="play2"><?php echo $play_ico; // phpcs:ignore ?><?php esc_html_e( 'Play my show', 'ridgeroad' ); ?></button>
		</div>
	</div>

	<details class="rr-ll__bk">
		<summary><?php esc_html_e( 'For big kids: change the lights during the song', 'ridgeroad' ); ?> <small><?php esc_html_e( '(optional)', 'ridgeroad' ); ?></small></summary>
		<div class="rr-ll__bk-in">
			<p><?php esc_html_e( 'The song has three parts. Pick a part, then use steps 1–3 above. Each row shows what a decoration does in each part.', 'ridgeroad' ); ?></p>
			<div class="rr-ll__parts" data-ll="parts"><button type="button" class="rr-ll__whole" data-ll="whole" aria-pressed="true"><?php esc_html_e( 'Whole song', 'ridgeroad' ); ?></button></div>
			<div class="rr-ll__rows" data-ll="rows"><div class="rr-ll__headwrap"><div class="rr-ll__head" data-ll="head" hidden></div></div></div>
		</div>
	</details>

	<div class="rr-ll__acts">
		<label class="rr-ll__name"><span><?php esc_html_e( 'Name your show', 'ridgeroad' ); ?></span><input type="text" maxlength="40" data-ll="name" placeholder="<?php esc_attr_e( 'Sparkle Storm', 'ridgeroad' ); ?>"></label>
		<button type="button" class="rr-ll__btn rr-ll__btn--save" data-ll="save"><?php esc_html_e( 'Save my show', 'ridgeroad' ); ?></button>
		<button type="button" class="rr-ll__btn rr-ll__btn--ghost" data-ll="full" data-play="1"><?php esc_html_e( 'Watch full screen', 'ridgeroad' ); ?></button>
		<?php if ( ridgeroad_contest_open() ) : ?>
			<a class="rr-ll__btn rr-ll__btn--send" href="#enter"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'I’m done! Enter to win', 'ridgeroad' ); ?></a>
		<?php endif; ?>
		<p class="rr-ll__toast" data-ll="toast" aria-live="polite"></p>
	</div>
	<p class="rr-kid__fine"><?php esc_html_e( 'Your show saves on this device automatically. These are pretend lights: they never change the real show.', 'ridgeroad' ); ?></p>
</section>
