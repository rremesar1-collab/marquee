<?php
/**
 * "Add to Home Screen" steps (iPhone, and Android browsers that can't show the install box).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rr-appsheet" data-rr-appsheet hidden>
	<div class="rr-appsheet__back" data-rr-appsheet-close></div>
	<div class="rr-appsheet__box" role="dialog" aria-modal="true" aria-labelledby="rr-appsheet-h" tabindex="-1">
		<h2 id="rr-appsheet-h" data-rr-appsheet-h><?php esc_html_e( 'Add it to your phone', 'ridgeroad' ); ?></h2>
		<p><?php esc_html_e( 'Takes 10 seconds. You only do this once.', 'ridgeroad' ); ?></p>
		<ol class="rr-appsheet__steps" data-rr-appsheet-steps></ol>
		<button type="button" class="rr-btn rr-btn--ghost" data-rr-appsheet-close><?php esc_html_e( 'Got it', 'ridgeroad' ); ?></button>
	</div>
</div>
