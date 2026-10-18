</main>
<footer class="rr-footer">
	<p>
		<?php
		$ft = ridgeroad_mod( 'footer_text' );
		echo $ft ? esc_html( $ft ) : '&copy; ' . esc_html( wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) );
		?>
	</p>
	<p class="rr-footer__app"><a href="<?php echo esc_url( home_url( '/#rr-app' ) ); ?>" data-rr-app-link hidden><?php esc_html_e( 'Get the app', 'ridgeroad' ); ?></a></p>
	<p class="rr-footer__links">
		<?php foreach ( function_exists( 'ridgeroad_social_links' ) ? ridgeroad_social_links() : array() as $net => $l ) : ?>
			<a href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_icon( $net ); ?><span class="screen-reader-text"><?php echo esc_html( $l['label'] ); ?></span></a>
		<?php endforeach; ?>
	</p>
</footer>
</div><!-- .rr-scroll -->
<?php ridgeroad_part( 'app-sheet' ); ?>
<?php wp_footer(); ?>
</body>
</html>
