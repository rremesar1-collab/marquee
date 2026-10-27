<?php
/**
 * "Your Name in Lights" form.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$open   = ridgeroad_names_open();
$status = ridgeroad_status();
?>
<div class="rr-panel rr-panel--blue rr-kbox rr-names" data-rr-names data-open="<?php echo $open ? '1' : '0'; ?>">
	<h2 class="rr-h"><span class="rr-h__star"><?php echo ridgeroad_cicon( 'star' ); ?></span><?php esc_html_e( 'Your Name in Lights!', 'ridgeroad' ); ?></h2>
	<p class="rr-kid__text"><?php echo esc_html( ridgeroad_is_marquee() ? __( 'Type your first name and watch for it in our light show between songs!', 'ridgeroad' ) : __( 'Type your first name and watch for it on our garage door and TV between songs!', 'ridgeroad' ) ); ?></p>

	<form class="rr-names__form" data-rr-names-form autocomplete="off" <?php echo $open ? '' : 'hidden'; ?>>
		<p class="rr-field"><label for="rr-n-kid"><?php esc_html_e( 'Your first name', 'ridgeroad' ); ?></label>
			<input id="rr-n-kid" name="kid" type="text" required autocapitalize="words" enterkeyhint="send" data-max="14"></p>
		<fieldset class="rr-names__greet">
			<legend><?php esc_html_e( 'Pick your message', 'ridgeroad' ); ?></legend>
			<?php
			$labels = ridgeroad_name_greetings();
			$first  = true;
			foreach ( $labels as $k => $lab ) :
				?>
				<label><input type="radio" name="greet" value="<?php echo esc_attr( $k ); ?>" <?php checked( $first ); ?>> <span data-greet="<?php echo esc_attr( $lab ); ?>"><?php echo esc_html( $lab ); ?></span></label>
				<?php
				$first = false;
			endforeach;
			?>
		</fieldset>
		<?php
		$rr_themes = function_exists( 'marquee_themes' ) ? marquee_themes() : array();
		if ( $rr_themes ) :
			$rr_hue = array( 350, 200, 280, 140, 30, 175 );
			$rr_i   = 0;
			?>
			<fieldset class="rr-names__themes" data-rr-fx-lab="<?php echo esc_url( function_exists( 'ridgeroad_asset_url' ) ? ridgeroad_asset_url( 'lightlab/' ) : '' ); ?>">
				<legend><?php esc_html_e( 'Pick your show', 'ridgeroad' ); ?></legend>
				<div class="rr-themes">
					<label class="rr-theme"><input type="radio" name="theme" value="" checked><span class="rr-theme__card" style="--h:45"><?php $rr_cimg = function_exists( 'marquee_classic_img' ) ? marquee_classic_img() : ''; ?><?php if ( $rr_cimg ) : ?><img class="rr-theme__pic" src="<?php echo esc_url( $rr_cimg ); ?>" alt="" loading="lazy"><?php else : ?><canvas class="rr-theme__pic rr-theme__fx" data-rr-fx="classic" width="560" height="157" aria-hidden="true"></canvas><?php endif; ?><b><?php esc_html_e( 'Classic', 'ridgeroad' ); ?></b></span></label>
					<?php
					foreach ( $rr_themes as $rr_slug => $rr_t ) :
						$rr_img = function_exists( 'marquee_theme_img' ) ? marquee_theme_img( $rr_t ) : '';
						?>
						<label class="rr-theme"><input type="radio" name="theme" value="<?php echo esc_attr( $rr_slug ); ?>"><span class="rr-theme__card" style="--h:<?php echo (int) $rr_hue[ $rr_i++ % 6 ]; ?>">
							<?php if ( $rr_img ) : ?><img class="rr-theme__pic" src="<?php echo esc_url( $rr_img ); ?>" alt="" loading="lazy"><?php else : ?><canvas class="rr-theme__pic rr-theme__fx" data-rr-fx="<?php echo esc_attr( function_exists( 'marquee_theme_look' ) ? marquee_theme_look( $rr_t ) : 'classic' ); ?>" width="560" height="157" aria-hidden="true"></canvas><?php endif; ?>
							<b><?php echo esc_html( $rr_t['name'] ); ?></b></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>
		<?php
		$rr_pic = (string) apply_filters( 'ridgeroad_names_photo_mode', '' ); // Marquee: kids' pictures ('' = off)
		if ( $rr_pic ) :
			?>
			<fieldset class="rr-names__pic" data-rr-pic data-mode="<?php echo esc_attr( $rr_pic ); ?>" data-screen="<?php echo esc_attr( (string) apply_filters( 'ridgeroad_names_photo_screen', '' ) ); ?>">
				<legend><?php echo esc_html( 'photo' === $rr_pic ? __( 'Add your picture (optional)', 'ridgeroad' ) : __( 'Add a cartoon of you (optional)', 'ridgeroad' ) ); ?></legend>
				<input type="file" accept="image/*" id="rr-n-pic" class="rr-names__pic-file" data-rr-pic-file>
				<span class="rr-names__pic-btns">
					<label class="rr-btn rr-btn--gold" for="rr-n-pic" data-rr-pic-take><?php echo ridgeroad_cicon( 'camera' ); ?><span><?php esc_html_e( 'Take or pick a picture', 'ridgeroad' ); ?></span></label>
					<button type="button" class="rr-btn rr-btn--ghost" data-rr-pic-clear hidden><?php esc_html_e( 'No picture', 'ridgeroad' ); ?></button>
				</span>
				<div class="rr-pic" data-rr-pic-edit hidden>
					<div class="rr-pic__frame">
						<p class="rr-pic__help"><?php esc_html_e( 'Move and zoom so your face fills the circle.', 'ridgeroad' ); ?></p>
						<canvas class="rr-pic__stage" width="600" height="600" data-rr-pic-stage aria-label="<?php esc_attr_e( 'Your picture: drag to move it, pinch to zoom', 'ridgeroad' ); ?>"></canvas>
						<label class="rr-pic__zoom"><span><?php esc_html_e( 'Zoom', 'ridgeroad' ); ?></span><input type="range" min="1" max="4" step="0.01" value="1" data-rr-pic-zoom></label>
					</div>
					<?php if ( 'photo' !== $rr_pic ) : ?>
						<div class="rr-pic__styles" role="radiogroup" aria-label="<?php esc_attr_e( 'Pick your look', 'ridgeroad' ); ?>">
							<?php
							$rr_styles = array(
								'cartoon' => array( __( 'Cartoon Me', 'ridgeroad' ), '🎨' ),
								'elf'     => array( __( 'Make Me an Elf', 'ridgeroad' ), '🧝' ),
								'snowman' => array( __( 'Snowman', 'ridgeroad' ), '⛄' ),
								'helper'  => array( __( 'Santa’s Helper', 'ridgeroad' ), '🎅' ),
							);
							if ( 'both' === $rr_pic ) {
								$rr_styles['photo'] = array( __( 'Real photo', 'ridgeroad' ), '📷' );
							}
							$rr_first = true;
							foreach ( $rr_styles as $rr_k => $rr_s ) :
								?>
								<label class="rr-pic__style"><input type="radio" name="photo_style" value="<?php echo esc_attr( $rr_k ); ?>" <?php checked( $rr_first ); ?>><span><i aria-hidden="true"><?php echo esc_html( $rr_s[1] ); ?></i><?php echo esc_html( $rr_s[0] ); ?></span></label>
								<?php
								$rr_first = false;
							endforeach;
							?>
						</div>
					<?php endif; ?>
					<div class="rr-pic__result">
						<figure class="rr-pic__out"><canvas width="320" height="320" data-rr-pic-out></canvas><figcaption><?php esc_html_e( 'Your picture', 'ridgeroad' ); ?></figcaption><span class="rr-pic__wait" data-rr-pic-wait hidden><?php esc_html_e( 'Making your picture…', 'ridgeroad' ); ?></span></figure>
						<figure class="rr-pic__screen" data-rr-pic-screenbox hidden><canvas width="64" height="64" data-rr-pic-screen></canvas><figcaption><?php esc_html_e( 'On our screen', 'ridgeroad' ); ?></figcaption></figure>
					</div>
				</div>
				<input type="hidden" name="photo_kind" value="<?php echo esc_attr( 'photo' === $rr_pic ? 'photo' : 'cartoon' ); ?>" data-rr-pic-kind>
				<label class="rr-names__pic-ok" data-rr-pic-okwrap hidden><input type="checkbox" name="photo_ok" value="1" data-rr-pic-ok> <span><?php esc_html_e( 'A parent or guardian says it’s OK to show this picture on our screens at the show.', 'ridgeroad' ); ?></span></label>
				<input type="hidden" name="photo" value="" data-rr-pic-data>
				<p class="rr-kid__fine"><?php echo esc_html( 'photo' === $rr_pic ? __( 'Your picture is only shown at the show. Our elves check every picture first, and it’s deleted right after.', 'ridgeroad' ) : __( 'Your phone makes the cartoon, and only the finished picture is sent, never your photo. Our elves check it first, and it’s deleted right after the show uses it.', 'ridgeroad' ) ); ?></p>
			</fieldset>
		<?php endif; ?>
		<p class="rr-hp" aria-hidden="true"><label>Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label></p>
		<button class="rr-btn rr-btn--red" type="submit"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'Put My Name in Lights!', 'ridgeroad' ); ?></button>
		<p class="rr-contest__msg" data-rr-names-msg aria-live="polite"></p>
		<p class="rr-kid__fine"><?php esc_html_e( 'First names only. Our elves check every name before it goes on the screens.', 'ridgeroad' ); ?></p>
	<?php if ( function_exists( 'marquee_voice_on' ) && marquee_voice_on() ) : ?>	<p class="rr-kid__fine"><?php esc_html_e( 'Turn up your radio: we’ll say your name out loud too!', 'ridgeroad' ); ?></p>
	<?php endif; ?>	<?php if ( function_exists( 'ridgeroad_names_geo' ) && ridgeroad_names_geo() ) : ?>	<p class="rr-kid__fine" data-rr-names-geo><?php esc_html_e( 'Names can only be sent from the show. Your phone will ask to share its location: it’s only used to check you’re here, and it isn’t saved.', 'ridgeroad' ); ?></p>
	<?php endif; ?></form>

	<div class="rr-names__done" data-rr-names-done hidden>
		<p class="rr-names__preview" data-rr-names-text></p>
		<p class="rr-names__place" data-rr-names-place></p>
		<p><?php echo esc_html( apply_filters( 'ridgeroad_names_live', false ) ? __( 'Watch for your name on our screens in a minute!', 'ridgeroad' ) : ( ridgeroad_is_marquee() ? __( 'Watch for your name in the show after the next song!', 'ridgeroad' ) : __( 'Watch for your name after the next song on the garage door and the TV by the front door!', 'ridgeroad' ) ) ); ?></p>
		<div class="rr-names__buzz" data-rr-buzz hidden>
			<span class="rr-names__bell" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span>
			<span data-rr-buzz-text><?php esc_html_e( 'Want a buzz on this phone right before your name shows?', 'ridgeroad' ); ?></span>
			<button type="button" class="rr-btn rr-btn--gold" data-rr-buzz-btn><?php esc_html_e( 'Buzz me!', 'ridgeroad' ); ?></button>
		</div>
		<button type="button" class="rr-btn rr-btn--ghost" data-rr-names-again><?php esc_html_e( 'Send another name', 'ridgeroad' ); ?></button>
	</div>

	<p class="rr-names__closed" data-rr-names-closed <?php echo $open ? 'hidden' : ''; ?>>
		<?php esc_html_e( 'Names go up in lights while the show is on.', 'ridgeroad' ); ?>
		<?php if ( ! empty( $status['note'] ) ) : ?><br><strong><?php echo esc_html( $status['note'] ); ?></strong><?php endif; ?>
	</p>
</div>
