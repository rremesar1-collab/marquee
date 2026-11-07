<?php
/**
 * Show Facts page: your show (photo + numbers), Did You Know? and Fan Favorites.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! ridgeroad_mod( 'facts_show' ) ) {
	return;
}
$sf_nums  = ridgeroad_mod( 'facts_hero' ) ? ridgeroad_fact_numbers() : array();
$sf_photo = ridgeroad_mod( 'facts_hero' ) ? (int) ridgeroad_mod( 'facts_photo' ) : 0;
$sf_img   = $sf_photo ? wp_get_attachment_image_url( $sf_photo, 'full' ) : '';
$sf_head  = trim( (string) ridgeroad_mod( 'facts_tagline' ) );
$sf_line  = trim( (string) ridgeroad_mod( 'facts_line' ) );
$sf_did   = ridgeroad_mod( 'facts_did' ) ? ridgeroad_facts() : array();
$sf_fav   = ridgeroad_mod( 'facts_fav' ) ? ridgeroad_fav_years() : array();
if ( 'boxes' === ridgeroad_facts_style() ) {
	include __DIR__ . '/facts-boxes.php'; // the Boxes style: number boxes, small fact cards, favorites by year side by side
	return;
}
?>
<div class="rr-facts" data-rr-facts>
	<?php if ( $sf_img || $sf_nums ) : ?>
		<section class="rr-facts__sec rr-facts__top" aria-labelledby="rr-facts-h">
			<h1 id="rr-facts-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'facts_title' ) ); ?></span></h1>
			<div class="rr-panel rr-panel--red rr-facts__hero">
				<?php if ( $sf_img ) : ?>
					<figure class="rr-facts__photo">
						<img src="<?php echo esc_url( $sf_img ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" decoding="async">
						<?php if ( '' !== $sf_head || '' !== $sf_line ) : ?>
							<figcaption>
								<?php if ( '' !== $sf_head ) : ?><p class="rr-facts__head"><?php echo esc_html( $sf_head ); ?></p><?php endif; ?>
								<?php if ( '' !== $sf_line ) : ?><p class="rr-facts__line"><?php echo esc_html( $sf_line ); ?></p><?php endif; ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				<?php elseif ( '' !== $sf_head || '' !== $sf_line ) : ?>
					<div class="rr-facts__words">
						<?php if ( '' !== $sf_head ) : ?><p class="rr-facts__head"><?php echo esc_html( $sf_head ); ?></p><?php endif; ?>
						<?php if ( '' !== $sf_line ) : ?><p class="rr-facts__line"><?php echo esc_html( $sf_line ); ?></p><?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $sf_nums ) : ?>
					<div class="rr-count__tiles rr-facts__nums" data-rr-facts-count>
						<?php foreach ( $sf_nums as $n ) : ?>
							<div class="rr-count__tile"><b data-count="<?php echo esc_attr( $n['num'] ); ?>"><?php echo esc_html( $n['num'] ); ?></b><span><?php echo esc_html( $n['label'] ); ?></span></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<h1 class="screen-reader-text"><?php echo esc_html( ridgeroad_mod( 'facts_title' ) ); ?></h1><?php // the photo part is off: its title goes too (kept for screen readers only) ?>
	<?php endif; ?>

	<?php if ( $sf_did ) : ?>
		<section class="rr-facts__sec rr-facts__did" aria-labelledby="rr-did-h">
			<h2 id="rr-did-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'facts_did_title' ) ); ?></span></h2>
			<div class="rr-grid rr-grid--tips rr-facts__cards">
				<?php foreach ( $sf_did as $d ) : ?>
					<article class="rr-panel rr-panel--green rr-tip">
						<span class="rr-tip__icon"><?php echo ridgeroad_cicon( $d['icon'] ); ?></span>
						<?php if ( '' !== $d['big'] ) : ?><h3><?php echo esc_html( $d['big'] ); ?></h3><?php endif; ?>
						<?php if ( '' !== $d['text'] ) : ?><p><?php echo esc_html( $d['text'] ); ?></p><?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $sf_fav ) : ?>
		<section class="rr-facts__sec rr-facts__fav" aria-labelledby="rr-fav-h">
			<h2 id="rr-fav-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'facts_fav_title' ) ); ?></span></h2>
			<div class="rr-panel rr-panel--blue rr-fav">
				<?php if ( count( $sf_fav ) > 1 ) : ?>
					<div class="rr-fav__years" role="tablist" aria-label="<?php esc_attr_e( 'Year', 'ridgeroad' ); ?>">
						<?php $first = true; foreach ( $sf_fav as $y => $l ) : ?>
							<button type="button" role="tab" class="rr-fav__year" id="rr-fav-t<?php echo (int) $y; ?>" aria-controls="rr-fav-<?php echo (int) $y; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" data-rr-fav-year="<?php echo (int) $y; ?>"><?php echo (int) $y; ?></button>
						<?php $first = false; endforeach; ?>
					</div>
				<?php else : ?>
					<p class="rr-fav__one"><?php echo (int) array_key_first( $sf_fav ); ?></p>
				<?php endif; ?>
				<?php
				$first = true;
				foreach ( $sf_fav as $y => $l ) :
					$max = max( 1, (int) $l[0][1] );
					?>
					<ol class="rr-fav__list" id="rr-fav-<?php echo (int) $y; ?>" role="tabpanel" aria-labelledby="rr-fav-t<?php echo (int) $y; ?>" data-rr-fav-list="<?php echo (int) $y; ?>"<?php echo $first ? '' : ' hidden'; ?>>
						<?php foreach ( $l as $i => $s ) : $w = $s[1] ? round( 100 * $s[1] / $max ) : 100 - 16 * $i; ?>
							<li class="rr-fav__song" style="--w:<?php echo (int) $w; ?>">
								<span class="rr-fav__n"><?php echo (int) $i + 1; ?></span>
								<b class="rr-fav__name"><?php echo esc_html( $s[0] ); ?></b>
								<?php if ( $s[1] ) : ?><span class="rr-fav__votes"><?php echo esc_html( sprintf( /* translators: %s: number of votes */ _n( '%s vote', '%s votes', (int) $s[1], 'ridgeroad' ), number_format_i18n( (int) $s[1] ) ) ); ?></span><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php $first = false; endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
