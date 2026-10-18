<?php
/**
 * Home page card: "Get the app" in a phone browser, "Get show alerts" inside the app.
 * Hidden until assets/js/app.js decides which one (if any) this visitor should see.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sw = function ( $k, $label, $sub ) {
	printf(
		'<div class="rr-app__opt"><span id="rr-app-%1$s">%2$s<small>%3$s</small></span><button type="button" class="rr-app__sw" role="switch" aria-checked="true" aria-labelledby="rr-app-%1$s" data-rr-app-topic="%1$s"></button></div>',
		esc_attr( $k ), esc_html( $label ), esc_html( $sub )
	);
};
?>
<section class="rr-panel rr-panel--blue rr-app" id="rr-app" data-rr-app-card hidden aria-label="<?php esc_attr_e( 'App', 'ridgeroad' ); ?>">
	<div class="rr-app__install" data-rr-app-part="install" hidden>
		<img class="rr-app__icon" src="<?php echo esc_url( ridgeroad_app_icon( 'icon-192.png' ) ); ?>" width="72" height="72" alt="">
		<div class="rr-app__text">
			<h2 class="rr-app__h"><?php esc_html_e( 'Get the app', 'ridgeroad' ); ?></h2>
			<p><?php esc_html_e( 'It’s free. Put the show on your home screen and get a buzz when the lights come on.', 'ridgeroad' ); ?></p>
		</div>
		<div class="rr-app__btns">
			<button type="button" class="rr-btn rr-btn--red" data-rr-app-add><?php esc_html_e( 'Add to my phone', 'ridgeroad' ); ?></button>
			<button type="button" class="rr-btn rr-btn--ghost" data-rr-app-later><?php esc_html_e( 'Not now', 'ridgeroad' ); ?></button>
		</div>
	</div>

	<div class="rr-app__alerts" data-rr-app-part="alerts" hidden>
		<h2 class="rr-app__h"><?php esc_html_e( 'Get show alerts', 'ridgeroad' ); ?></h2>
		<p data-rr-app-intro><?php esc_html_e( 'Pick what you want. You can change it any time from the link at the bottom of the page.', 'ridgeroad' ); ?></p>
		<?php
		$sw( 'start', __( 'When the lights come on', 'ridgeroad' ), __( 'Once a night, when the show starts', 'ridgeroad' ) );
		$sw( 'news', __( 'Cancellations and special news', 'ridgeroad' ), __( 'Weather, Santa visits, last night', 'ridgeroad' ) );
		?>
		<p class="rr-app__msg" data-rr-app-msg aria-live="polite"></p>
		<div class="rr-app__btns" data-rr-app-new>
			<button type="button" class="rr-btn rr-btn--red" data-rr-app-on><?php esc_html_e( 'Turn on alerts', 'ridgeroad' ); ?></button>
			<button type="button" class="rr-btn rr-btn--ghost" data-rr-app-later><?php esc_html_e( 'Not now', 'ridgeroad' ); ?></button>
		</div>
		<div class="rr-app__btns" data-rr-app-have hidden>
			<button type="button" class="rr-btn rr-btn--ghost" data-rr-app-done><?php esc_html_e( 'Done', 'ridgeroad' ); ?></button>
			<button type="button" class="rr-app__off" data-rr-app-off><?php esc_html_e( 'Turn off all alerts', 'ridgeroad' ); ?></button>
		</div>
	</div>
</section>
