<?php
/**
 * Weather: now, tonight hourly, radar, forecast links.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast
?>
	<section id="weather" class="rr-panel rr-panel--blue rr-weather" aria-labelledby="rr-wx-h">
		<div class="rr-weather__main">
			<h2 id="rr-wx-h" class="rr-weather__city"><?php echo ridgeroad_cicon( 'snow' ); ?><span><?php echo esc_html( ridgeroad_mod( 'city' ) ); ?></span> <small><?php echo esc_html( ridgeroad_mod( 'zip' ) ); ?></small></h2>
			<?php if ( $wx['ok'] ) : ?>
				<div class="rr-now">
					<div class="rr-now__temp">
						<span class="rr-temp" data-rr="temp"><?php echo esc_html( $wx['temp'] ); ?></span><sup><?php echo esc_html( ridgeroad_units()['t'] ); ?></sup>
						<span class="rr-now__label" data-rr="label"><?php echo esc_html( $wx['label'] ); ?></span>
					</div>
					<?php echo ridgeroad_wx_icon( $wx['code'], $wx['is_day'], 'rr-now__icon' ); ?>
					<dl class="rr-stats">
						<?php if ( null !== $wx['feels'] ) : ?>
							<dt><?php esc_html_e( 'Feels Like', 'ridgeroad' ); ?></dt><dd data-rr="feels"><?php echo esc_html( $wx['feels'] ); ?>°</dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'Humidity', 'ridgeroad' ); ?></dt><dd data-rr="humidity"><?php echo esc_html( $wx['humidity'] ); ?>%</dd>
						<dt><?php esc_html_e( 'Wind', 'ridgeroad' ); ?></dt><dd data-rr="wind"><?php echo esc_html( $wx['wind'] . ' ' . ridgeroad_units()['w'] ); ?></dd>
						<?php if ( null !== $wx['precip'] ) : ?>
							<dt><?php esc_html_e( 'Precipitation', 'ridgeroad' ); ?></dt><dd data-rr="precip"><?php echo esc_html( $wx['precip'] ); ?>%</dd>
						<?php endif; ?>
					</dl>
				</div>
				<?php if ( $wx['hours'] ) : ?>
					<ol class="rr-hourly">
						<?php foreach ( $wx['hours'] as $h ) : ?>
							<li>
								<span class="rr-hourly__t"><?php echo esc_html( $h['label'] ); ?></span>
								<?php echo ridgeroad_wx_icon( $h['code'], $h['is_day'] ); ?>
								<span class="rr-hourly__v"><?php echo esc_html( $h['temp'] ); ?>°</span>
								<?php if ( $h['pop'] ) : ?><span class="rr-hourly__p"><?php echo esc_html( $h['pop'] ); ?>%</span><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			<?php else : ?>
				<p class="rr-muted"><?php esc_html_e( 'Weather is temporarily unavailable. Please use the forecast link.', 'ridgeroad' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="rr-weather__side">
			<?php
			// Forecast links are National Weather Service pages (US only). Marquee shows use their own NWS office.
			$wx_us   = ! function_exists( 'marquee_get' ) || 'US' === marquee_get( 'country' );
			$wx_pin  = (float) RIDGEROAD_LAT || (float) RIDGEROAD_LON;
			?>
			<?php if ( ridgeroad_mod( 'show_radar' ) && $station ) : ?>
				<a class="rr-radar" href="<?php echo esc_url( 'https://radar.weather.gov/station/' . strtolower( $station ) . '/standard' ); ?>" target="_blank" rel="noopener">
					<img src="<?php echo esc_url( 'https://radar.weather.gov/ridge/standard/' . $station . '_loop.gif' ); ?>" alt="<?php esc_attr_e( 'Live weather radar loop', 'ridgeroad' ); ?>" loading="lazy" onerror="this.parentNode.classList.add('is-off')">
					<span class="rr-radar__tag"><?php echo ridgeroad_cicon( 'radar' ); ?><?php esc_html_e( 'Live Radar', 'ridgeroad' ); ?></span>
				</a>
			<?php elseif ( ridgeroad_mod( 'show_radar' ) && ! $wx_us && $wx_pin ) : ?>
				<?php // Outside the US: Windy's live radar map (worldwide). ?>
				<div class="rr-radar rr-radar--map">
					<iframe src="<?php echo esc_url( ridgeroad_windy_url( true ) ); ?>" title="<?php esc_attr_e( 'Live weather radar', 'ridgeroad' ); ?>" loading="lazy" allowfullscreen></iframe>
					<span class="rr-radar__tag"><?php echo ridgeroad_cicon( 'radar' ); ?><?php esc_html_e( 'Live Radar', 'ridgeroad' ); ?></span>
				</div>
			<?php endif; ?>
			<?php
			$wx_office = function_exists( 'marquee_get' ) ? strtolower( preg_replace( '/[^A-Za-z]/', '', (string) ( function_exists( 'marquee_nws_office' ) ? marquee_nws_office() : marquee_get( 'nws_office' ) ) ) ) : 'hun';
			?>
			<?php if ( $wx_us ) : ?>
			<a class="rr-btn rr-btn--blue" href="<?php echo esc_url( $forecast ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Full Weather Forecast', 'ridgeroad' ); ?><?php echo ridgeroad_cicon( 'external' ); ?></a>
			<?php elseif ( $wx_pin ) : ?>
			<a class="rr-btn rr-btn--blue" href="<?php echo esc_url( ridgeroad_windy_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Full Weather Forecast', 'ridgeroad' ); ?><?php echo ridgeroad_cicon( 'external' ); ?></a>
			<?php endif; ?>
			<?php if ( $wx_us && $wx_office ) : ?>
			<a class="rr-link" href="<?php echo esc_url( 'https://www.weather.gov/' . $wx_office . '/' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( function_exists( 'marquee_get' ) ? __( 'National Weather Service', 'ridgeroad' ) : __( 'NWS Huntsville Forecast', 'ridgeroad' ) ); ?><?php echo ridgeroad_cicon( 'external' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
