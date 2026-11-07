<?php
/**
 * Show settings on Marquee → Settings. The list of settings (labels, types, help) is the same one
 * Customize uses (ridgeroad_customize_fields), so each setting is described once and saved the
 * same way (as the theme's settings, kept across themes by Marquee).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every show setting by key: array( section, label, type, extras ). */
function marquee_field_defs() {
	static $defs = null;
	if ( null !== $defs ) {
		return $defs;
	}
	$defs = array();
	if ( ! function_exists( 'ridgeroad_customize_fields' ) ) {
		return $defs;
	}
	$add   = function ( $key, $section, $label, $type = 'text', $extra = array() ) use ( &$defs ) {
		$defs[ $key ] = array( $section, $label, $type, (array) $extra );
	};
	$image = function ( $key, $section, $label, $desc = '' ) use ( &$defs ) {
		$defs[ $key ] = array( $section, $label, 'image', array( 'description' => $desc ) );
	};
	ridgeroad_customize_fields( $add, $image );
	return $defs;
}

/** Can this setting have its own words for each language? */
function marquee_field_langs( $key, $type ) {
	return in_array( $type, array( 'text', 'textarea' ), true ) && function_exists( 'ridgeroad_lang_same' ) && ! ridgeroad_lang_same( $key ) && function_exists( 'ridgeroad_lang_extra' ) && ridgeroad_lang_extra();
}

/**
 * Draw one setting. $o: label (instead of the standard one), help (instead of its description),
 * class (extra class on the row).
 */
function marquee_field( $key, $o = array() ) {
	$defs = marquee_field_defs();
	if ( ! isset( $defs[ $key ] ) ) {
		return;
	}
	list( , $label, $type, $extra ) = $defs[ $key ];
	$label = isset( $o['label'] ) ? $o['label'] : $label;
	$help  = isset( $o['help'] ) ? $o['help'] : ( $extra['description'] ?? '' );
	$id    = 'mq-f-' . $key;
	$name  = 'tm[' . $key . ']';
	$raw   = get_theme_mod( $key, null );
	$val   = function_exists( 'ridgeroad_mod' ) ? ridgeroad_mod( $key ) : $raw;
	$cls   = 'mq-f mq-f--' . $type . ( isset( $o['class'] ) ? ' ' . $o['class'] : '' );
	if ( 'checkbox' === $type ) {
		printf(
			'<p class="%1$s"><input type="hidden" name="%2$s" value="0"><label class="mq-f__check"><input type="checkbox" id="%3$s" name="%2$s" value="1" %4$s data-mq-sync> <span>%5$s</span></label>%6$s</p>',
			esc_attr( $cls ), esc_attr( $name ), esc_attr( $id ), checked( (bool) $val, true, false ), esc_html( $label ), $help ? '<small>' . esc_html( $help ) . '</small>' : ''
		);
		return;
	}
	echo '<p class="' . esc_attr( $cls ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	if ( 'image' === $type ) {
		echo '</p>';
		$aid = $val ? attachment_url_to_postid( (string) $val ) : 0;
		echo '<input type="hidden" name="tm_img[]" value="' . esc_attr( $key ) . '">';
		marquee_media_field( 'tm_img_' . $key, $label, $help, $aid ? array( $aid ) : array(), false );
		return;
	}
	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" data-mq-sync>' . esc_textarea( (string) $val ) . '</textarea>';
	} elseif ( 'select' === $type ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-mq-sync>';
		foreach ( (array) ( $extra['choices'] ?? array() ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	} else {
		$t     = in_array( $type, array( 'url', 'email', 'date', 'time', 'number' ), true ) ? $type : ( 'miles' === $type ? 'number' : 'text' );
		$attrs = '';
		foreach ( (array) ( $extra['input_attrs'] ?? array() ) as $a => $v ) {
			$attrs .= ' ' . esc_attr( $a ) . '="' . esc_attr( $v ) . '"';
		}
		echo '<input type="' . esc_attr( $t ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"' . $attrs . ' data-mq-sync>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr above
	}
	if ( $help ) {
		echo '<small>' . esc_html( $help ) . '</small>';
	}
	echo '</p>';
	// the language button is on: the same words in each other language, right under it
	if ( marquee_field_langs( $key, $type ) ) {
		$all   = function_exists( 'marquee_langs_all' ) ? marquee_langs_all() : array();
		$names = array();
		$any   = false;
		foreach ( ridgeroad_lang_extra() as $code ) {
			$names[] = isset( $all[ $code ] ) ? $all[ $code ][0] : $code;
			$any     = $any || '' !== (string) get_theme_mod( $key . '__' . $code, '' );
		}
		/* translators: %s: language names, like "Español, Français" */
		echo '<details class="mq-f__langs"' . ( $any ? ' open' : '' ) . '><summary>' . esc_html( sprintf( __( 'Other languages: %s', 'marquee' ), implode( ', ', $names ) ) ) . '</summary>';
		foreach ( ridgeroad_lang_extra() as $code ) {
			$lid = $id . '-' . $code;
			$lv  = (string) get_theme_mod( $key . '__' . $code, '' );
			$ll  = isset( $all[ $code ] ) ? $all[ $code ][0] : $code;
			echo '<p class="mq-f mq-f--lang"><label for="' . esc_attr( $lid ) . '">' . esc_html( $label . ' · ' . $ll ) . '</label>';
			if ( 'textarea' === $type ) {
				echo '<textarea id="' . esc_attr( $lid ) . '" name="' . esc_attr( 'tm[' . $key . '__' . $code . ']' ) . '" rows="2" data-mq-sync>' . esc_textarea( $lv ) . '</textarea>';
			} else {
				echo '<input type="text" id="' . esc_attr( $lid ) . '" name="' . esc_attr( 'tm[' . $key . '__' . $code . ']' ) . '" value="' . esc_attr( $lv ) . '" data-mq-sync>';
			}
			echo '<small>' . esc_html__( 'Leave empty to use the words above.', 'marquee' ) . '</small></p>';
		}
		echo '</details>';
	}
}

/** Draw several settings. */
function marquee_fields( $keys ) {
	foreach ( (array) $keys as $k ) {
		marquee_field( $k );
	}
}

/**
 * Forget a show setting so it goes back to its standard value. Marquee keeps its own copy of the
 * show settings (so they survive a theme change), so that copy is cleared too.
 */
function marquee_unset_mod( $key ) {
	remove_theme_mod( $key );
	$m = get_option( 'marquee_mods', null );
	if ( is_array( $m ) && array_key_exists( $key, $m ) ) {
		unset( $m[ $key ] );
		update_option( 'marquee_mods', $m, true );
	}
}

/** Save the show settings posted from Marquee → Settings. */
function marquee_save_fields( $in ) {
	$defs = marquee_field_defs();
	$d    = function_exists( 'ridgeroad_defaults' ) ? ridgeroad_defaults() : array();
	$en   = function_exists( 'ridgeroad_defaults_en' ) ? ridgeroad_defaults_en() : $d;
	$extra_langs = function_exists( 'ridgeroad_lang_extra' ) ? ridgeroad_lang_extra() : array();
	foreach ( (array) ( $in['tm'] ?? array() ) as $key => $raw ) {
		$key = (string) $key;
		$raw = is_array( $raw ) ? '' : wp_unslash( (string) $raw );
		// words in another language
		if ( preg_match( '/^([a-z0-9_]+)__([a-z]{2,3})$/', $key, $m ) ) {
			if ( isset( $defs[ $m[1] ] ) && in_array( $m[2], $extra_langs, true ) ) {
				$v = 'textarea' === $defs[ $m[1] ][2] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
				if ( '' === trim( $v ) ) {
					marquee_unset_mod( $key );
				} else {
					set_theme_mod( $key, $v );
				}
			}
			continue;
		}
		if ( ! isset( $defs[ $key ] ) || 'image' === $defs[ $key ][2] ) {
			continue;
		}
		$type = $defs[ $key ][2];
		switch ( $type ) {
			case 'textarea':
				$v = sanitize_textarea_field( $raw );
				break;
			case 'url':
				$v = esc_url_raw( trim( $raw ) );
				break;
			case 'checkbox':
				$v = '1' === $raw ? 1 : 0;
				break;
			case 'time':
				$v = function_exists( 'ridgeroad_sanitize_time' ) ? ridgeroad_sanitize_time( $raw ) : sanitize_text_field( $raw );
				break;
			case 'date':
				$v = function_exists( 'ridgeroad_sanitize_date' ) ? ridgeroad_sanitize_date( $raw ) : sanitize_text_field( $raw );
				break;
			case 'email':
				$v = sanitize_email( $raw );
				break;
			case 'number':
				$v = '' === trim( $raw ) ? '' : (int) $raw;
				break;
			case 'miles':
				$v = function_exists( 'ridgeroad_sanitize_miles' ) ? ridgeroad_sanitize_miles( $raw ) : (string) (float) $raw;
				break;
			case 'select':
				$ch = (array) ( $defs[ $key ][3]['choices'] ?? array() );
				$v  = isset( $ch[ $raw ] ) ? $raw : ( $d[ $key ] ?? '' );
				break;
			default:
				$v = sanitize_text_field( $raw );
		}
		// Unchanged: leave it alone (a standard value stays standard, so it can follow updates).
		$was = function_exists( 'ridgeroad_mod' ) ? ridgeroad_mod( $key ) : get_theme_mod( $key );
		if ( 'checkbox' === $type ? (int) (bool) $was === $v : (string) $was === (string) $v ) {
			continue;
		}
		// The standard words (in English or the admin's language) aren't saved, so they keep following
		// the visitor's language and any wording updates.
		if ( is_string( $v ) && ( ( isset( $d[ $key ] ) && $v === $d[ $key ] ) || ( isset( $en[ $key ] ) && $v === $en[ $key ] ) ) && 'select' !== $type ) {
			marquee_unset_mod( $key );
			continue;
		}
		set_theme_mod( $key, $v );
	}
	// pictures picked in the media library: saved as their address, like Customize does
	foreach ( (array) ( $in['tm_img'] ?? array() ) as $key ) {
		$key = sanitize_key( $key );
		if ( ! isset( $defs[ $key ] ) || 'image' !== $defs[ $key ][2] ) {
			continue;
		}
		$id = absint( strtok( (string) ( $in[ 'tm_img_' . $key ] ?? '' ), ',' ) );
		$u  = $id && wp_attachment_is_image( $id ) ? wp_get_attachment_url( $id ) : '';
		if ( $u ) {
			set_theme_mod( $key, esc_url_raw( $u ) );
		} else {
			marquee_unset_mod( $key );
		}
	}
}

/*
 * With Marquee on, the show's words and settings are edited in Marquee → Settings (each page with its
 * boxes). Customize keeps only the look: Look & colors, plus falling snow and your background photo.
 * Without Marquee, the festive theme keeps everything in Customize.
 */
add_action( 'customize_register', function ( WP_Customize_Manager $wp ) {
	if ( ! function_exists( 'ridgeroad_customize_fields' ) || ! $wp->get_panel( 'ridgeroad' ) ) {
		return;
	}
	$look = $wp->get_section( 'rr_look' ) ? 'rr_look' : '';
	$keep = array( 'snow_amount', 'snow_size', 'snow_real', 'bg_image' );
	$p    = 200;
	foreach ( $keep as $k ) {
		$c = $wp->get_control( $k );
		if ( $c && $look ) {
			$c->section  = $look;
			$c->priority = $p++;
		}
	}
	foreach ( array( 'rr_show', 'rr_weather', 'rr_live', 'rr_tips', 'rr_seq', 'rr_songs', 'rr_kids', 'rr_stats', 'rr_support', 'rr_guestbook', 'rr_sponsors', 'rr_facts' ) as $sec ) {
		$wp->remove_section( $sec );
	}
	if ( $look ) {
		$wp->remove_section( 'rr_header' );
	} else { // no Look section with this theme: the snow and background stay under Header, without the words
		$wp->get_section( 'rr_header' )->title = __( 'Snow & background', 'marquee' );
		foreach ( array_keys( marquee_field_defs() ) as $k ) {
			$c = $wp->get_control( $k );
			if ( $c && 'rr_header' === $c->section && ! in_array( $k, $keep, true ) ) {
				$wp->remove_control( $k );
			}
		}
	}
	$wp->add_section( 'rr_moved', array(
		'title'       => __( 'Words & page settings', 'marquee' ),
		'panel'       => 'ridgeroad',
		'priority'    => 1,
		'description' => '<p>' . esc_html__( 'Your show’s words, boxes and switches are in Marquee → Settings now, one page at a time: Home page, Songs & Videos, Kids’ Corner, Names in Lights and Show Facts.', 'marquee' ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=marquee#mq-step-10' ) ) . '" target="_top">' . esc_html__( 'Open Marquee Settings', 'marquee' ) . '</a></p>',
	) );
	// a section only shows when it has a control
	$wp->add_setting( 'rr_moved_note', array( 'sanitize_callback' => '__return_empty_string' ) );
	$wp->add_control( 'rr_moved_note', array( 'section' => 'rr_moved', 'type' => 'hidden' ) );
}, 1000 );
