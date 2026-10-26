<?php
/**
 * Links to the Songs and Kids' Corner pages.
 * Shared section: drawn by the festive theme and by the Marquee block/shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
extract( ridgeroad_home_ctx(), EXTR_SKIP ); // $status, $np, $wx, $opts, $b_title, $b_text, $b_mod, $np_text, $station, $forecast

$rr_kids  = ridgeroad_mod( 'kids_show' ) && ridgeroad_kids_url();
$rr_songs = ridgeroad_songs_page_on() && ridgeroad_songs_url();
if ( $rr_kids || $rr_songs ) : ?>
<div class="rr-explore<?php echo ( $rr_kids && $rr_songs ) ? ' rr-explore--two' : ''; ?>">
	<?php if ( $rr_songs ) : ?>
	<a class="rr-kids-teaser rr-kids-teaser--songs" href="<?php echo esc_url( ridgeroad_songs_url() ); ?>">
		<span class="rr-kids-teaser__icon"><?php echo ridgeroad_cicon( 'music' ); ?></span>
		<span class="rr-kids-teaser__text"><b><?php esc_html_e( 'Show Videos & Voting', 'ridgeroad' ); ?></b> <?php echo ridgeroad_mod( 'songs_show' ) ? esc_html__( 'Watch videos of this year’s songs and vote for Fan Favorite Friday!', 'ridgeroad' ) : esc_html__( 'Watch the videos of this year’s songs!', 'ridgeroad' ); ?></span>
		<span class="rr-kids-teaser__go"><?php esc_html_e( 'Watch', 'ridgeroad' ); ?> &rarr;</span>
	</a>
	<?php endif; ?>
	<?php if ( $rr_kids ) : ?>
	<a class="rr-kids-teaser" href="<?php echo esc_url( ridgeroad_kids_url() ); ?>">
		<span class="rr-kids-teaser__art" aria-hidden="true"></span>
		<span class="rr-kids-teaser__icon"><?php echo ridgeroad_cicon( 'gift' ); ?></span>
		<span class="rr-kids-teaser__text"><b><?php echo esc_html( ridgeroad_mod( 'kids_title' ) ); ?></b> <?php
			// what's on in the Kids' Corner (each part can be switched off)
			$rr_lab   = ridgeroad_mod( 'lab_on' );
			$rr_vote  = $rr_lab && ridgeroad_mod( 'contest_on' ) && ridgeroad_contest_open();
			$rr_parts = array();
			if ( ridgeroad_mod( 'names_on' ) ) {
				$rr_parts[] = __( 'your name in lights', 'ridgeroad' );
			}
			if ( ridgeroad_mod( 'coloring_on' ) && ridgeroad_coloring_pages() ) {
				$rr_parts[] = __( 'coloring pages', 'ridgeroad' );
			}
			if ( ridgeroad_mod( 'games_on' ) ) {
				$rr_parts[] = __( 'elf names & Christmas trivia', 'ridgeroad' );
			}
			$rr_lead = $rr_vote ? __( 'Make your own light show to win a prize, and vote for your favorite kids’ shows on the Show Wall!', 'ridgeroad' ) : ( $rr_lab ? __( 'Design your own light show!', 'ridgeroad' ) : '' );
			$rr_more = $rr_parts ? wp_sprintf( '%l', $rr_parts ) : '';
			if ( $rr_lead && $rr_more ) {
				/* translators: %s: a list like "coloring pages and elf names & Christmas trivia" */
				$rr_lead .= ' ' . sprintf( __( 'Plus %s!', 'ridgeroad' ), $rr_more );
			} elseif ( $rr_more ) {
				/* translators: %s: a list like "your name in lights, coloring pages and elf names & Christmas trivia" */
				$rr_lead = sprintf( __( 'Come play: %s!', 'ridgeroad' ), $rr_more );
			}
			echo esc_html( $rr_lead );
		?></span>
		<span class="rr-kids-teaser__go"><?php esc_html_e( 'Let’s Play', 'ridgeroad' ); ?> &rarr;</span>
	</a>
	<?php endif; ?>
</div>
<?php endif; ?>
