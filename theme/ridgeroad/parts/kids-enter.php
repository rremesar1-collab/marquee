<?php
/**
 * Contest step 2: send the show (a grown-up fills this in).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="enter" class="rr-contest rr-kc__enter" aria-labelledby="rr-enter-h">
	<h3 id="rr-enter-h" class="rr-kc__h"><?php esc_html_e( 'Enter your show to win a prize', 'ridgeroad' ); ?></h3>
	<p class="rr-kc__grownup"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'Ask a grown-up to help with this part.', 'ridgeroad' ); ?></p>
	<form data-rr-enter novalidate>
		<div class="rr-entry" data-rr-entry>
			<div class="rr-entry__card" data-rr-entry-card hidden>
				<img data-rr-enter-thumb alt="<?php esc_attr_e( 'Picture of your light show', 'ridgeroad' ); ?>">
				<div class="rr-entry__info">
					<strong data-rr-enter-name></strong>
					<span data-rr-enter-song></span>
					<a class="rr-kc__btn rr-kc__btn--ghost" href="#light-lab"><?php esc_html_e( 'Change my show', 'ridgeroad' ); ?></a>
				</div>
			</div>
			<p class="rr-entry__empty" data-rr-entry-empty hidden><?php esc_html_e( 'You haven’t made a light show yet. Make one in step 1, then come back here to send it!', 'ridgeroad' ); ?></p>
		</div>
		<div class="rr-kc__fields">
			<p class="rr-field"><label for="rr-c-child"><?php esc_html_e( 'Child’s first name', 'ridgeroad' ); ?></label>
				<input id="rr-c-child" name="child" type="text" maxlength="20" autocomplete="off" required></p>
			<p class="rr-field"><label for="rr-c-age"><?php esc_html_e( 'Age', 'ridgeroad' ); ?></label>
				<select id="rr-c-age" name="age" required>
					<option value=""><?php esc_html_e( 'Pick…', 'ridgeroad' ); ?></option>
					<?php for ( $a = 3; $a <= 12; $a++ ) : ?><option value="<?php echo (int) $a; ?>"><?php echo (int) $a; ?></option><?php endfor; ?>
				</select></p>
			<p class="rr-field"><label for="rr-c-mail"><?php esc_html_e( 'Parent email', 'ridgeroad' ); ?></label>
				<input id="rr-c-mail" name="email" type="email" maxlength="100" autocomplete="email" required>
				<small class="rr-field__note"><?php esc_html_e( 'We’ll email you the claim code and let you know if your child wins. Never shown or shared.', 'ridgeroad' ); ?></small></p>
		</div>
		<p class="rr-hp" aria-hidden="true"><label>Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label></p>
		<p class="rr-check"><label><input name="consent" type="checkbox" value="1" required> <?php esc_html_e( 'I’m the parent or guardian, and I give permission to post my child’s light show, first name, and age on this website.', 'ridgeroad' ); ?></label></p>
		<button class="rr-kc__btn rr-kc__btn--red" type="submit"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'Send My Light Show', 'ridgeroad' ); ?></button>
		<p class="rr-contest__msg" data-rr-enter-msg aria-live="polite"></p>
	</form>
	<div class="rr-contest__done" data-rr-enter-done hidden>
		<p class="rr-contest__big"><?php esc_html_e( 'Your show is in!', 'ridgeroad' ); ?></p>
		<p><?php esc_html_e( 'We’ll add it to the Show Wall after we check it. Your claim code is:', 'ridgeroad' ); ?></p>
		<p class="rr-contest__code" data-rr-enter-code></p>
		<p><?php esc_html_e( 'We also emailed it to you. If your child wins, we’ll email you, and you bring this code to the house during show hours to pick up the prize. Keep it secret!', 'ridgeroad' ); ?></p>
	</div>
</section>
