<?php
/**
 * Suggest an idea: show owners send a feature request or a suggestion to Marquee Support by email,
 * and see the ideas they've already sent.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function marquee_idea_kinds() {
	return array(
		'feature' => __( 'A new feature', 'marquee' ),
		'better'  => __( 'Make something better', 'marquee' ),
		'other'   => __( 'Something else', 'marquee' ),
	);
}

add_action( 'admin_menu', function () {
	$hook = add_submenu_page( 'marquee', __( 'Suggest an idea', 'marquee' ), __( 'Suggest an idea', 'marquee' ), 'manage_options', 'marquee-ideas', 'marquee_ideas_page' );
	add_action( 'load-' . $hook, 'marquee_ideas_send' );
}, 21 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'marquee-ideas' ) ) {
		return;
	}
	wp_enqueue_style( 'marquee-support', MARQUEE_URL . 'assets/support.css', array(), MARQUEE_VERSION . '.' . filemtime( MARQUEE_DIR . 'assets/support.css' ) );
} );

/** The form posts back to its own page; send it, then come back to the page so a refresh never sends it twice. */
function marquee_ideas_send() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mq_idea'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'marquee_idea' );
	$kinds = marquee_idea_kinds();
	$kind  = isset( $_POST['mq_idea_kind'] ) && isset( $kinds[ $_POST['mq_idea_kind'] ] ) ? sanitize_key( $_POST['mq_idea_kind'] ) : 'feature';
	$text  = isset( $_POST['mq_idea_text'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['mq_idea_text'] ) ) ) : '';
	$email = isset( $_POST['mq_idea_email'] ) ? sanitize_email( wp_unslash( $_POST['mq_idea_email'] ) ) : '';
	$back  = admin_url( 'admin.php?page=marquee-ideas' );
	if ( mb_strlen( $text ) < 5 ) {
		set_transient( 'mq_idea_draft_' . get_current_user_id(), array( 'kind' => $kind, 'text' => $text, 'email' => $email ), HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'idea', 'empty', $back ) );
		exit;
	}
	$text = mb_substr( $text, 0, 3000 );

	// an optional picture, attached to the email and then deleted
	$files = array();
	if ( ! empty( $_FILES['mq_idea_pic']['tmp_name'] ) && is_uploaded_file( $_FILES['mq_idea_pic']['tmp_name'] ) && (int) $_FILES['mq_idea_pic']['size'] <= 5 * MB_IN_BYTES ) {
		$type = wp_check_filetype_and_ext( $_FILES['mq_idea_pic']['tmp_name'], $_FILES['mq_idea_pic']['name'] );
		if ( in_array( $type['type'], array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ), true ) ) {
			$dest = trailingslashit( get_temp_dir() ) . 'marquee-idea-' . wp_generate_password( 8, false ) . '.' . $type['ext'];
			if ( move_uploaded_file( $_FILES['mq_idea_pic']['tmp_name'], $dest ) ) {
				$files[] = $dest;
			}
		}
	}

	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$theme = wp_get_theme();
	// the email to Marquee Support is always in English (the idea itself is in the owner's words)
	$en   = array( 'feature' => 'A new feature', 'better' => 'Make something better', 'other' => 'Something else' );
	$subj = sprintf( '[Marquee idea] %1$s: %2$s', $site, mb_strimwidth( preg_replace( '/\s+/', ' ', $text ), 0, 60, '…' ) );
	$body = $en[ $kind ] . "\n\n" . $text . "\n\n----\n"
		. home_url( '/' ) . ' (' . determine_locale() . ")\n"
		. 'Marquee ' . MARQUEE_VERSION . ' · ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ' · WordPress ' . get_bloginfo( 'version' ) . "\n"
		. ( $email ? 'Reply to: ' . $email : 'They didn’t leave an email for a reply.' ) . "\n";
	$headers = $email ? array( 'Reply-To: ' . $email ) : array();
	$ok      = wp_mail( MARQUEE_SUPPORT_EMAIL, $subj, $body, $headers, $files );
	foreach ( $files as $f ) {
		wp_delete_file( $f );
	}

	if ( ! $ok ) {
		set_transient( 'mq_idea_draft_' . get_current_user_id(), array( 'kind' => $kind, 'text' => $text, 'email' => $email ), HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'idea', 'failed', $back ) );
		exit;
	}
	$sent = (array) get_option( 'marquee_ideas_sent', array() );
	array_unshift( $sent, array( 't' => time(), 'kind' => $kind, 'text' => $text, 'pic' => (bool) $files ) );
	update_option( 'marquee_ideas_sent', array_slice( $sent, 0, 20 ), false );
	delete_transient( 'mq_idea_draft_' . get_current_user_id() );
	wp_safe_redirect( add_query_arg( 'idea', 'sent', $back ) );
	exit;
}

function marquee_ideas_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$kinds = marquee_idea_kinds();
	$state = isset( $_GET['idea'] ) ? sanitize_key( $_GET['idea'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$draft = (array) get_transient( 'mq_idea_draft_' . get_current_user_id() );
	$kind  = isset( $draft['kind'] ) && isset( $kinds[ $draft['kind'] ] ) ? $draft['kind'] : 'feature';
	$user  = wp_get_current_user();
	$email = isset( $draft['email'] ) ? $draft['email'] : $user->user_email;
	$sent  = (array) get_option( 'marquee_ideas_sent', array() );
	?>
	<div class="wrap mq-sup mq-idea">
		<h1><?php esc_html_e( 'Suggest an idea', 'marquee' ); ?></h1>

		<?php if ( 'sent' === $state ) : ?>
			<div class="notice notice-success"><p><b><?php esc_html_e( 'Thanks! Your idea was sent.', 'marquee' ); ?></b> <?php esc_html_e( 'Every idea gets read. Ideas that help a lot of shows get built first.', 'marquee' ); ?></p></div>
		<?php elseif ( 'failed' === $state ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: email address */ __( 'Your website couldn’t send the email. Your idea is still in the box below. You can try again, or email it to %s.', 'marquee' ), MARQUEE_SUPPORT_EMAIL ) ); ?></p></div>
		<?php elseif ( 'empty' === $state ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Write your idea in the box first.', 'marquee' ); ?></p></div>
		<?php endif; ?>

		<form class="mq-sup__start" method="post" enctype="multipart/form-data">
			<p class="mq-sup__lead"><?php esc_html_e( 'Got an idea for Marquee? A feature you wish it had, something that could work better, or anything else? Tell us here. Many of Marquee’s features started as a show owner’s idea.', 'marquee' ); ?></p>
			<?php wp_nonce_field( 'marquee_idea' ); ?>
			<input type="hidden" name="mq_idea" value="1">
			<fieldset class="mq-idea__kinds">
				<legend><b><?php esc_html_e( 'What kind of idea?', 'marquee' ); ?></b></legend>
				<?php foreach ( $kinds as $k => $label ) : ?>
					<label><input type="radio" name="mq_idea_kind" value="<?php echo esc_attr( $k ); ?>" <?php checked( $kind, $k ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<label for="mq-idea-text"><b><?php esc_html_e( 'Your idea', 'marquee' ); ?></b></label>
			<textarea id="mq-idea-text" name="mq_idea_text" rows="6" maxlength="3000" required placeholder="<?php esc_attr_e( 'For example: let visitors pick a color for their name on the matrix.', 'marquee' ); ?>"><?php echo esc_textarea( $draft['text'] ?? '' ); ?></textarea>
			<p class="mq-idea__row">
				<label for="mq-idea-pic"><b><?php esc_html_e( 'A picture (optional)', 'marquee' ); ?></b><small><?php esc_html_e( 'A screenshot or a photo that shows what you mean. Up to 5 MB.', 'marquee' ); ?></small></label>
				<input type="file" id="mq-idea-pic" name="mq_idea_pic" accept="image/png,image/jpeg,image/gif,image/webp">
			</p>
			<p class="mq-idea__row">
				<label for="mq-idea-email"><b><?php esc_html_e( 'Your email, if you’d like an answer', 'marquee' ); ?></b><small><?php esc_html_e( 'Leave it empty if you don’t need one.', 'marquee' ); ?></small></label>
				<input type="email" id="mq-idea-email" name="mq_idea_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>">
			</p>
			<p class="description"><?php esc_html_e( 'Along with your idea, we get your website’s address and which versions of Marquee, your theme and WordPress you have. Nothing else.', 'marquee' ); ?></p>
			<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Send my idea', 'marquee' ); ?></button></p>
			<p class="description"><?php echo wp_kses( sprintf( /* translators: %s: link to the Get help page */ __( 'Something not working? Use %s instead, so Marquee Support can see your setup.', 'marquee' ), '<a href="' . esc_url( admin_url( 'admin.php?page=marquee-support' ) ) . '">' . esc_html__( 'Get help', 'marquee' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) ); ?></p>
		</form>

		<?php if ( $sent ) : ?>
			<section class="mq-idea__sent">
				<h2><?php esc_html_e( 'Ideas you’ve sent', 'marquee' ); ?></h2>
				<ul>
					<?php foreach ( $sent as $i ) : ?>
						<li>
							<span class="mq-idea__meta"><?php echo esc_html( wp_date( __( 'M j, Y', 'marquee' ), (int) $i['t'] ) . ' · ' . ( $kinds[ $i['kind'] ] ?? '' ) . ( ! empty( $i['pic'] ) ? ' · ' . __( 'with a picture', 'marquee' ) : '' ) ); ?></span>
							<p><?php echo nl2br( esc_html( $i['text'] ) ); ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</div>
	<?php
}
