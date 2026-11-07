<?php
/**
 * Show Facts, Boxes style: the title and a line, your numbers in boxes like Behind the Lights,
 * small Did You Know? cards in one box, and Fan Favorites with each year side by side.
 * Uses the variables set up in parts/facts.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sf_intro = '' !== $sf_head || '' !== $sf_line;
?>
<div class="rr-facts rr-facts--boxes" data-rr-facts>
	<?php if ( $sf_nums || ( ridgeroad_mod( 'facts_hero' ) && $sf_intro ) ) : ?>
		<section class="rr-facts__sec rr-facts__top" aria-labelledby="rr-facts-h">
			<h1 id="rr-facts-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'facts_title' ) ); ?></span></h1>
			<?php if ( $sf_intro ) : ?><p class="rr-facts__intro"><?php if ( '' !== $sf_head ) : ?><b><?php echo esc_html( $sf_head ); ?></b><?php endif; ?><?php if ( '' !== $sf_head && '' !== $sf_line ) : ?><br><?php endif; ?><?php echo esc_html( $sf_line ); ?></p><?php endif; ?>
			<?php if ( $sf_nums ) : ?>
				<div class="rr-statgrid">
					<?php foreach ( $sf_nums as $n ) : ?>
						<div class="rr-stat">
							<span class="rr-stat__icon"><?php echo ridgeroad_cicon( $n['icon'] ? $n['icon'] : 'star' ); ?></span>
							<b class="rr-stat__num" data-count="<?php echo esc_attr( $n['num'] ); ?>"><?php echo esc_html( $n['num'] ); ?></b>
							<span class="rr-stat__label"><?php echo esc_html( $n['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<h1 class="screen-reader-text"><?php echo esc_html( ridgeroad_mod( 'facts_title' ) ); ?></h1>
	<?php endif; ?>

	<?php if ( $sf_did ) : ?>
		<section class="rr-facts__sec rr-facts__did" aria-labelledby="rr-did-h">
			<h2 id="rr-did-h" class="rr-ribbon"><span><?php esc_html_e( 'Did You Know?', 'ridgeroad' ); ?></span></h2>
			<div class="rr-panel rr-panel--green">
				<ul class="rr-mfacts">
					<?php foreach ( $sf_did as $d ) : ?>
						<li class="rr-mfact"><?php echo ridgeroad_cicon( $d['icon'] ); ?><p><?php if ( '' !== $d['big'] ) : ?><b><?php echo esc_html( $d['big'] ); ?></b> <?php endif; ?><?php echo esc_html( $d['text'] ); ?></p></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $sf_fav ) : ?>
		<section class="rr-facts__sec rr-facts__fav" aria-labelledby="rr-fav-h">
			<h2 id="rr-fav-h" class="rr-ribbon"><span><?php esc_html_e( 'Fan Favorites', 'ridgeroad' ); ?></span></h2>
			<div class="rr-panel rr-panel--red">
				<div class="rr-fyears">
					<?php foreach ( $sf_fav as $y => $l ) : ?>
						<div class="rr-fyear">
							<h3><?php echo (int) $y; ?></h3>
							<ol>
								<?php foreach ( array_slice( $l, 0, 3 ) as $i => $s ) : ?>
									<li><span class="rr-fyear__medal"><?php echo (int) $i + 1; ?></span><span class="rr-fyear__name"><?php echo esc_html( $s[0] ); ?></span><?php if ( $s[1] ) : ?><small><?php echo esc_html( sprintf( /* translators: %s: number of votes */ _n( '%s vote', '%s votes', (int) $s[1], 'ridgeroad' ), number_format_i18n( (int) $s[1] ) ) ); ?></small><?php endif; ?></li>
								<?php endforeach; ?>
							</ol>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
