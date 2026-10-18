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
		<?php if ( ridgeroad_mod( 'facebook_url' ) ) : ?>
			<a href="<?php echo esc_url( ridgeroad_mod( 'facebook_url' ) ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_icon( 'facebook' ); ?><span class="screen-reader-text">Facebook</span></a>
		<?php endif; ?>
		<?php if ( ridgeroad_mod( 'tiktok_url' ) ) : ?>
			<a href="<?php echo esc_url( ridgeroad_mod( 'tiktok_url' ) ); ?>" target="_blank" rel="noopener"><?php echo ridgeroad_icon( 'tiktok' ); ?><span class="screen-reader-text">TikTok</span></a>
		<?php endif; ?>
	</p>
</footer>
</div><!-- .rr-scroll -->
<?php ridgeroad_part( 'app-sheet' ); ?>
<?php wp_footer(); ?>
</body>
</html>
