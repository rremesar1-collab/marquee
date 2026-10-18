<?php
/**
 * "This Year's Sequences": MP4 videos of the season's sequences.
 * Add them in WP Admin → My Sequences (song title, artist, MP4 from the
 * Media Library, optional cover picture). No voting here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'rr_sequence', array(
		'labels'              => array(
			'name'          => __( 'My Sequences', 'ridgeroad' ),
			'singular_name' => __( 'Sequence Video', 'ridgeroad' ),
			'add_new'       => __( 'Add Sequence Video', 'ridgeroad' ),
			'add_new_item'  => __( 'Add Sequence Video', 'ridgeroad' ),
			'edit_item'     => __( 'Edit Sequence Video', 'ridgeroad' ),
			'all_items'     => __( 'All Sequence Videos', 'ridgeroad' ),
			'not_found'     => __( 'No sequence videos yet.', 'ridgeroad' ),
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => function_exists( 'marquee_get' ) ? 'marquee' : true, // under the Marquee menu on Marquee sites
		'menu_icon'           => 'dashicons-format-video',
		'menu_position'       => 25,
		'supports'            => array( 'title', 'page-attributes', 'thumbnail' ),
		'exclude_from_search' => true,
	) );
} );

add_filter( 'enter_title_here', function ( $t, $post ) {
	return 'rr_sequence' === $post->post_type ? __( 'Song title', 'ridgeroad' ) : $t;
}, 10, 2 );

/* Featured image box → "Cover picture". */
add_action( 'do_meta_boxes', function () {
	remove_meta_box( 'postimagediv', 'rr_sequence', 'side' );
	add_meta_box( 'postimagediv', __( 'Cover picture (optional)', 'ridgeroad' ), 'post_thumbnail_meta_box', 'rr_sequence', 'side', 'low' );
} );

add_action( 'admin_enqueue_scripts', function () {
	$s = get_current_screen();
	if ( $s && 'rr_sequence' === $s->post_type ) {
		wp_enqueue_media();
	}
} );

add_action( 'add_meta_boxes_rr_sequence', function () {
	add_meta_box( 'rr_seq_box', __( 'Video', 'ridgeroad' ), function ( $post ) {
		wp_nonce_field( 'rr_seq_box', 'rr_seq_nonce' );
		$vid = (int) get_post_meta( $post->ID, '_rr_video', true );
		$url = $vid ? wp_get_attachment_url( $vid ) : get_post_meta( $post->ID, '_rr_video_url', true );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf( '<tr><th><label for="rr_artist">%s</label></th><td><input class="regular-text" id="rr_artist" name="rr_artist" value="%s" placeholder="Trans-Siberian Orchestra"></td></tr>', esc_html__( 'Artist', 'ridgeroad' ), esc_attr( get_post_meta( $post->ID, '_rr_artist', true ) ) );
		echo '<tr><th>' . esc_html__( 'MP4 video', 'ridgeroad' ) . '</th><td>';
		printf( '<input type="hidden" id="rr_video" name="rr_video" value="%d">', $vid );
		printf( '<input class="large-text" id="rr_video_url" name="rr_video_url" value="%s" placeholder="https://…/my-sequence.mp4" style="margin-bottom:8px">', esc_attr( $url ) );
		echo '<button type="button" class="button button-primary" id="rr_pick">' . esc_html__( 'Upload or choose MP4', 'ridgeroad' ) . '</button> ';
		echo '<button type="button" class="button" id="rr_clear">' . esc_html__( 'Remove', 'ridgeroad' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Upload the MP4 to your Media Library (or paste a link to an MP4). Tip: export at 1080p, H.264, and keep it under about 150 MB so it loads fast on phones.', 'ridgeroad' ) . '</p>';
		printf( '<video id="rr_prev" src="%s" controls preload="metadata" style="max-width:420px;margin-top:10px;border-radius:8px;%s"></video>', esc_url( $url ), $url ? '' : 'display:none' );
		echo '</td></tr>';
		printf( '<tr><th>%s</th><td><label><input type="checkbox" name="rr_new" value="1" %s> %s</label></td></tr>', esc_html__( 'Badge', 'ridgeroad' ), checked( (bool) get_post_meta( $post->ID, '_rr_new', true ), true, false ), esc_html__( 'New this year', 'ridgeroad' ) );
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Use “Order” in the box on the right to arrange the videos (lower numbers first). Add a cover picture on the right, or the first frame of the video is used.', 'ridgeroad' ) . '</p>';
		?>
		<script>
		jQuery(function ($) {
			var frame;
			$('#rr_pick').on('click', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({ title: 'Choose the sequence video (MP4)', library: { type: 'video' }, button: { text: 'Use this video' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					$('#rr_video').val(a.id); $('#rr_video_url').val(a.url);
					$('#rr_prev').attr('src', a.url).show();
				});
				frame.open();
			});
			$('#rr_clear').on('click', function () { $('#rr_video').val(''); $('#rr_video_url').val(''); $('#rr_prev').hide().attr('src', ''); });
			$('#rr_video_url').on('change', function () { $('#rr_video').val(''); var u = $(this).val(); $('#rr_prev').attr('src', u).toggle(!!u); });
		});
		</script>
		<?php
	}, 'rr_sequence', 'normal', 'high' );
} );

add_action( 'save_post_rr_sequence', function ( $id ) {
	if ( ! isset( $_POST['rr_seq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_seq_nonce'] ) ), 'rr_seq_box' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, '_rr_artist', sanitize_text_field( wp_unslash( $_POST['rr_artist'] ?? '' ) ) );
	update_post_meta( $id, '_rr_video', (int) ( $_POST['rr_video'] ?? 0 ) );
	update_post_meta( $id, '_rr_video_url', esc_url_raw( trim( wp_unslash( $_POST['rr_video_url'] ?? '' ) ) ) );
	update_post_meta( $id, '_rr_new', empty( $_POST['rr_new'] ) ? 0 : 1 );
} );

add_filter( 'manage_rr_sequence_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox" />',
		'rr_cover'  => __( 'Video', 'ridgeroad' ),
		'title'     => __( 'Song', 'ridgeroad' ),
		'rr_artist' => __( 'Artist', 'ridgeroad' ),
		'rr_order'  => __( 'Order', 'ridgeroad' ),
	);
} );
add_action( 'manage_rr_sequence_posts_custom_column', function ( $col, $id ) {
	if ( 'rr_cover' === $col ) {
		$v = ridgeroad_sequence_video_url( $id );
		$c = get_the_post_thumbnail_url( $id, 'medium' );
		if ( $c ) {
			echo '<img src="' . esc_url( $c ) . '" style="width:130px;border-radius:6px" alt="">';
		} elseif ( $v ) {
			echo '<video src="' . esc_url( $v ) . '#t=0.5" preload="metadata" muted style="width:130px;border-radius:6px"></video>';
		} else {
			echo '<em>' . esc_html__( 'No video yet', 'ridgeroad' ) . '</em>';
		}
	} elseif ( 'rr_artist' === $col ) {
		echo esc_html( get_post_meta( $id, '_rr_artist', true ) );
	} elseif ( 'rr_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $id );
	}
}, 10, 2 );

function ridgeroad_sequence_video_url( $id ) {
	$vid = (int) get_post_meta( $id, '_rr_video', true );
	$url = $vid ? wp_get_attachment_url( $vid ) : '';
	return $url ? $url : (string) get_post_meta( $id, '_rr_video_url', true );
}

/** Published sequence videos, in your order. */
function ridgeroad_sequence_videos() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$memo = array();
	$q    = get_posts( array(
		'post_type'      => 'rr_sequence',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
	) );
	foreach ( $q as $p ) {
		$url = ridgeroad_sequence_video_url( $p->ID );
		if ( ! $url ) {
			continue;
		}
		$memo[] = array(
			'title'  => $p->post_title,
			'artist' => get_post_meta( $p->ID, '_rr_artist', true ),
			'new'    => (bool) get_post_meta( $p->ID, '_rr_new', true ),
			'video'  => $url,
			'cover'  => get_the_post_thumbnail_url( $p, 'large' ),
		);
	}
	return $memo;
}
