<?php
/**
 * Kids' Corner (2.6): four big doors, one activity at a time.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$radio = ridgeroad_mod( 'radio' );
$quiz  = array(
	array( 'What makes Rudolph special?', array( 'A shiny red nose', 'Golden antlers', 'He can talk', 'A blue tail' ), 0 ),
	array( 'How many reindeer pull Santa’s sleigh, counting Rudolph?', array( '6', '8', '9', '12' ), 2 ),
	array( 'What do many families leave out for Santa?', array( 'Pizza', 'Cookies and milk', 'Popcorn', 'Ice cream' ), 1 ),
	array( 'Where does Santa live?', array( 'The South Pole', 'The North Pole', 'The Moon', 'Hawaii' ), 1 ),
	array( 'What brought Frosty the Snowman to life?', array( 'A magic scarf', 'An old silk hat', 'A carrot nose', 'A snow globe' ), 1 ),
	array( 'What is a baby reindeer called?', array( 'A pup', 'A cub', 'A calf', 'A kit' ), 2 ),
	array( 'What do you tune your car radio to for our show?', array( '88.1 FM', '90.5 FM', '101.3 FM', '1050 AM' ), 1 ),
	array( 'Who helps Santa make toys in his workshop?', array( 'Elves', 'Penguins', 'Snowmen', 'Gingerbread men' ), 0 ),
);
if ( $radio && '90.5 FM' !== $radio ) {
	$quiz[6][1][1] = $radio;
}
$lab        = (bool) ridgeroad_mod( 'lab_on' );
$contest    = $lab && (bool) ridgeroad_mod( 'contest_on' );
$open       = $contest && ridgeroad_contest_open();
$names_on   = (bool) ridgeroad_mod( 'names_on' );
$names_open = $names_on && ridgeroad_names_open();
$color_pgs  = ridgeroad_coloring_pages();
$coloring   = ridgeroad_mod( 'coloring_on' ) && $color_pgs; // each Kids' Corner part can be switched off on its own
$games      = (bool) ridgeroad_mod( 'games_on' );
$mq         = ridgeroad_is_marquee();
$back       = '<button type="button" class="rr-kc__back" data-kc-home-btn><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#f7c95a"/><path d="M14 6l-6 6 6 6" fill="none" stroke="#2a1600" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>' . esc_html__( 'All activities', 'ridgeroad' ) . '</button>';
$entries    = $contest ? ridgeroad_art_entries() : array();
$winners    = array_filter( $entries, function ( $e ) {
	return $e['judges'] || $e['people'];
} );
?>
<section id="kids" class="rr-kc" data-rr-kc aria-labelledby="rr-kids-h">
	<h1 id="rr-kids-h" class="rr-ribbon"><span><?php echo esc_html( ridgeroad_mod( 'kids_title' ) ); ?></span></h1>

	<div class="rr-kc__home" data-kc-home>
		<p class="rr-kc__ask"><?php esc_html_e( 'What do you want to do?', 'ridgeroad' ); ?></p>
		<?php $door_n = ( $names_on ? 1 : 0 ) + ( $lab ? 1 : 0 ) + ( $coloring ? 1 : 0 ) + ( $games ? 1 : 0 ); ?>
		<?php if ( ! $door_n ) : ?><p class="rr-kc__none"><?php esc_html_e( 'Nothing here right now. Check back soon!', 'ridgeroad' ); ?></p><?php endif; ?>
		<div class="rr-kc__doors<?php echo array( 0 => ' is-one', 1 => ' is-one', 2 => ' is-two', 3 => ' is-three', 4 => '' )[ $door_n ]; ?>" data-kc-doors>
			<?php if ( $names_on ) : ?>
				<a class="rr-kc__door" href="#names" data-kc-door="names" data-first="<?php echo $names_open ? '1' : '0'; ?>">
					<img src="<?php echo esc_url( ridgeroad_door_url( 'names', 'door-1.jpg' ) ); ?>" width="490" height="685" alt="<?php echo esc_attr( $mq ? __( 'Your Name in Lights. See your name in our light show.', 'ridgeroad' ) : __( 'Your Name in Lights. See your name on our garage door.', 'ridgeroad' ) ); ?>">
					<span class="rr-kc__sign" data-kc-names-sign <?php echo $names_open ? 'hidden' : ''; ?>><?php esc_html_e( 'At showtime', 'ridgeroad' ); ?></span>
					<?php if ( ridgeroad_door_words( 'names' ) ) : ?>
						<span class="rr-kc__sign" data-kc-names-on <?php echo $names_open ? '' : 'hidden'; ?>><?php esc_html_e( 'On now!', 'ridgeroad' ); ?></span>
						<span class="rr-kc__words" aria-hidden="true"><?php ridgeroad_door_title( __( 'Your Name in Lights', 'ridgeroad' ) ); ?><small><?php echo esc_html( $mq ? __( 'See your name in our light show', 'ridgeroad' ) : __( 'See your name on our garage door', 'ridgeroad' ) ); ?></small></span>
						<span class="rr-kc__go" aria-hidden="true"><?php esc_html_e( 'Let’s go!', 'ridgeroad' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>
			<?php if ( $lab ) : ?>
			<a class="rr-kc__door" href="#light-show" data-kc-door="light-show">
				<img src="<?php echo esc_url( ridgeroad_door_url( 'show', 'door-2.jpg' ) ); ?>" width="490" height="685" alt="<?php echo esc_attr( ( $mq ? __( 'Make a Light Show. Light up a house your way.', 'ridgeroad' ) : __( 'Make a Light Show. Light up our house your way.', 'ridgeroad' ) ) . ( $open ? ' ' . __( 'Win a prize!', 'ridgeroad' ) : '' ) ); ?>">
				<?php if ( ! $open ) : ?><span class="rr-kc__sign rr-kc__sign--show"><?php esc_html_e( 'Just for fun', 'ridgeroad' ); ?></span><?php endif; ?>
				<?php if ( ridgeroad_door_words( 'show' ) ) : ?>
					<?php if ( $open ) : ?><span class="rr-kc__sign rr-kc__sign--show"><?php esc_html_e( 'Win a prize!', 'ridgeroad' ); ?></span><?php endif; ?>
					<span class="rr-kc__words" aria-hidden="true"><?php ridgeroad_door_title( __( 'Make a Light Show', 'ridgeroad' ) ); ?><small><?php echo esc_html( $mq ? __( 'Light up a house your way', 'ridgeroad' ) : __( 'Light up our house your way', 'ridgeroad' ) ); ?></small></span>
					<span class="rr-kc__go" aria-hidden="true"><?php esc_html_e( 'Let’s go!', 'ridgeroad' ); ?></span>
				<?php endif; ?>
			</a>
			<?php endif; ?>
			<?php if ( $coloring ) : ?>
			<a class="rr-kc__door" href="#coloring" data-kc-door="coloring">
				<img src="<?php echo esc_url( ridgeroad_door_url( 'coloring', 'door-coloring.jpg' ) ); ?>" width="490" height="685" alt="<?php esc_attr_e( 'Coloring Pages. Pick a picture to print and color.', 'ridgeroad' ); ?>">
				<?php if ( ridgeroad_door_words( 'coloring' ) ) : ?>
					<span class="rr-kc__words" aria-hidden="true"><?php ridgeroad_door_title( __( 'Coloring Pages', 'ridgeroad' ) ); ?><small><?php esc_html_e( 'Pick a picture to print and color it!', 'ridgeroad' ); ?></small></span>
					<span class="rr-kc__go" aria-hidden="true"><?php esc_html_e( 'Let’s go!', 'ridgeroad' ); ?></span>
				<?php endif; ?>
			</a>
			<?php endif; ?>
			<?php if ( $games ) : ?>
			<a class="rr-kc__door" href="#games" data-kc-door="games">
				<img src="<?php echo esc_url( ridgeroad_door_url( 'games', 'door-4.jpg' ) ); ?>" width="490" height="685" alt="<?php esc_attr_e( 'Games. Elf names and Christmas trivia.', 'ridgeroad' ); ?>">
				<?php if ( ridgeroad_door_words( 'games' ) ) : ?>
					<span class="rr-kc__words rr-kc__words--games" aria-hidden="true"><?php ridgeroad_door_title( __( 'Games', 'ridgeroad' ) ); ?><small><?php esc_html_e( 'Elf names and Christmas trivia', 'ridgeroad' ); ?></small></span>
					<span class="rr-kc__go" aria-hidden="true"><?php esc_html_e( 'Let’s go!', 'ridgeroad' ); ?></span>
				<?php endif; ?>
			</a>
			<?php endif; ?>
		</div>
		<?php if ( $contest && ( $entries || $open ) ) : ?>
			<div class="rr-panel rr-panel--red rr-kc__wallbox">
				<?php ridgeroad_part( 'kids-wall', array( 'entries' => $entries, 'open' => $open ) ); ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $lab ) : ?>
	<!-- Make a Light Show (+ contest) -->
	<section class="rr-kc__act" id="light-show" data-kc-act="light-show" hidden>
		<div class="rr-kc__top"><?php echo $back; // phpcs:ignore ?><h2><?php echo ridgeroad_cicon( 'bulb' ); ?><span><?php esc_html_e( 'Make a Light Show', 'ridgeroad' ); ?><?php if ( $open ) : ?> <em><?php esc_html_e( '& Win a Prize!', 'ridgeroad' ); ?></em><?php endif; ?></span></h2></div>

		<?php if ( $winners ) : ?>
			<div class="rr-panel rr-panel--gold rr-contest__winners">
				<h3 class="rr-h"><span class="rr-h__star"><?php echo ridgeroad_cicon( 'star' ); ?></span><?php esc_html_e( 'And the winners are…', 'ridgeroad' ); ?></h3>
				<ul>
					<?php foreach ( $winners as $w ) : ?>
						<li>
							<?php if ( $w['judges'] ) : ?><strong><?php esc_html_e( 'Judges’ Pick:', 'ridgeroad' ); ?></strong> <?php endif; ?>
							<?php if ( $w['people'] ) : ?><strong><?php esc_html_e( 'People’s Choice:', 'ridgeroad' ); ?></strong> <?php endif; ?>
							<?php echo esc_html( sprintf( '%s, age %d', $w['name'], $w['age'] ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><?php echo esc_html( sprintf( __( 'Congratulations! Bring a parent and your claim code to the house during show hours by %s to pick up your prize.', 'ridgeroad' ), ridgeroad_contest_date( 'contest_pickup' ) ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $open ) : ?>
			<?php ridgeroad_part( 'kids-prize' ); ?>
			<nav class="rr-kc__steps rr-kc__steps--two" aria-label="<?php esc_attr_e( 'Light show steps', 'ridgeroad' ); ?>">
				<a href="#light-lab" data-kc-step="make"><i>1</i><?php esc_html_e( 'Make it', 'ridgeroad' ); ?></a>
				<a href="#enter" data-kc-step="enter"><i>2</i><?php esc_html_e( 'Enter to win', 'ridgeroad' ); ?></a>
			</nav>
		<?php endif; ?>
		<?php if ( $contest && $entries ) : ?>
			<p class="rr-kc__wall-link"><a href="#show-wall"><?php echo $open ? esc_html__( 'Watch the other kids’ shows and give your favorites a heart', 'ridgeroad' ) : esc_html__( 'Watch the shows other kids made', 'ridgeroad' ); ?> &rarr;</a></p>
		<?php endif; ?>

		<div class="rr-kc__pane rr-panel rr-panel--blue" data-kc-pane="make">
			<?php ridgeroad_part( 'kids-lab' ); ?>
		</div>
		<?php if ( $open ) : ?>
			<div class="rr-kc__pane rr-panel rr-panel--green" data-kc-pane="enter" hidden>
				<?php ridgeroad_part( 'kids-enter' ); ?>
			</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $names_on ) : ?>
		<section class="rr-kc__act" id="names" data-kc-act="names" hidden>
			<div class="rr-kc__top"><?php echo $back; // phpcs:ignore ?><h2><?php echo ridgeroad_cicon( 'star' ); ?><span><?php esc_html_e( 'Your Name in Lights', 'ridgeroad' ); ?></span></h2></div>
			<?php ridgeroad_part( 'kids-names' ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $coloring ) : ?>
	<section class="rr-kc__act" id="coloring" data-kc-act="coloring" hidden>
		<div class="rr-kc__top"><?php echo $back; // phpcs:ignore ?><h2><?php echo ridgeroad_cicon( 'tree' ); ?><span><?php esc_html_e( 'Coloring Pages', 'ridgeroad' ); ?></span></h2></div>
		<div class="rr-panel rr-panel--green rr-kc__color">
			<p class="rr-kid__text"><?php esc_html_e( 'Pick a picture, print it out and grab your crayons!', 'ridgeroad' ); ?></p>
			<ul class="rr-kc__cgrid">
				<?php foreach ( $color_pgs as $pg ) : ?>
					<li>
						<a class="rr-kc__cpage" href="<?php echo esc_url( $pg['print'] ); ?>" data-kc-stat="coloring">
							<span class="rr-kc__cpic<?php echo $pg['port'] ? ' rr-kc__cpic--port' : ''; ?>"><img src="<?php echo esc_url( $pg['thumb'] ); ?>" alt="" loading="lazy" width="640" height="427"></span>
							<span class="rr-kc__cname"><?php echo esc_html( $pg['title'] ); ?></span>
							<span class="rr-kc__cprint"><?php echo ridgeroad_cicon( 'star' ); ?><?php esc_html_e( 'Print', 'ridgeroad' ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<?php endif; ?>

	<?php if ( $games ) : ?>
	<section class="rr-kc__act" id="games" data-kc-act="games" hidden>
		<div class="rr-kc__top"><?php echo $back; // phpcs:ignore ?><h2><?php echo ridgeroad_cicon( 'gift' ); ?><span><?php esc_html_e( 'Games', 'ridgeroad' ); ?></span></h2></div>
		<div class="rr-kc__picker" role="group" aria-label="<?php esc_attr_e( 'Pick a game', 'ridgeroad' ); ?>">
			<button type="button" class="rr-kc__pick" data-kc-game="elf" aria-pressed="true"><?php echo ridgeroad_cicon( 'gift' ); ?><span><b><?php esc_html_e( 'Elf Name', 'ridgeroad' ); ?></b><small><?php esc_html_e( 'What do Santa’s elves call you?', 'ridgeroad' ); ?></small></span></button>
			<button type="button" class="rr-kc__pick" data-kc-game="trivia" aria-pressed="false"><?php echo ridgeroad_cicon( 'music' ); ?><span><b><?php esc_html_e( 'Christmas Trivia', 'ridgeroad' ); ?></b><small><?php esc_html_e( '5 quick questions', 'ridgeroad' ); ?></small></span></button>
		</div>
		<div class="rr-panel rr-panel--green rr-kid rr-kbox rr-elf" data-kc-g="elf">
			<p class="rr-kid__text"><?php esc_html_e( 'Type your first name and find out what Santa’s elves call you!', 'ridgeroad' ); ?></p>
			<form class="rr-elf__form" data-rr-elf autocomplete="off">
				<label class="screen-reader-text" for="rr-elf-name"><?php esc_html_e( 'Your first name', 'ridgeroad' ); ?></label>
				<input id="rr-elf-name" type="text" maxlength="20" placeholder="<?php esc_attr_e( 'Your first name', 'ridgeroad' ); ?>">
				<button class="rr-btn rr-btn--red" type="submit"><?php esc_html_e( 'Elf Me!', 'ridgeroad' ); ?></button>
			</form>
			<p class="rr-elf__result" data-rr="elf" aria-live="polite" hidden></p>
			<p class="rr-kid__fine"><?php esc_html_e( 'Just for fun — names are never saved or sent anywhere.', 'ridgeroad' ); ?></p>
		</div>
		<div class="rr-panel rr-panel--blue rr-kid rr-kbox rr-quiz" data-kc-g="trivia" data-rr-quiz='<?php echo esc_attr( wp_json_encode( $quiz ) ); ?>' hidden>
			<p class="rr-quiz__progress" data-rr="quiz-progress"></p>
			<p class="rr-quiz__q" data-rr="quiz-q"><?php esc_html_e( 'Loading…', 'ridgeroad' ); ?></p>
			<div class="rr-quiz__opts" data-rr="quiz-opts"></div>
			<p class="rr-quiz__msg" data-rr="quiz-msg" aria-live="polite"></p>
		</div>
	</section>
	<?php endif; ?>
</section>
