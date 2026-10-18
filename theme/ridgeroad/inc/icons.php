<?php
/**
 * Inline SVG icons (no icon font needed).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_icon( $name, $class = '' ) {
	$p = array(
		'home'     => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h5v-5h3v5h5V10"/>',
		'cloud'    => '<path d="M7 18.5h10.5a4 4 0 0 0 .4-8A6 6 0 0 0 6.6 9.3 4.6 4.6 0 0 0 7 18.5Z"/>',
		'car'      => '<path d="M4 16.5V12l2-5h12l2 5v4.5"/><path d="M3 12h18v4.5H3z"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="16.5" cy="17.5" r="1.8"/>',
		'heart'    => '<path d="M12 20.5s-8-4.8-8-10.7A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 8 2.8c0 5.9-8 10.7-8 10.7Z"/>',
		'bulb'     => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.4 1.1 2.2V16h5v-.1c0-.8.4-1.6 1.1-2.2A6 6 0 0 0 12 3Z"/>',
		'camera'   => '<path d="M3 8.5h4l1.6-2.5h6.8L17 8.5h4V19H3z"/><circle cx="12" cy="13.5" r="3.6"/>',
		'radio'    => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8 17 3.5"/><circle cx="8.5" cy="15.5" r="2.2"/><circle cx="15.5" cy="15.5" r="2.2"/><path d="M13 11h5"/>',
		'tree'     => '<path d="M12 2.5 7.5 9h2.5L6 14.5h3L5 20h14l-4-5.5h3L14 9h2.5Z"/><path d="M12 20v2"/>',
		'star'     => '<path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6L12 16.7 6.6 19.6l1.1-6-4.5-4.2 6.1-.8Z"/>',
		'gift'     => '<rect x="3.5" y="9" width="17" height="11.5" rx="1"/><path d="M2.5 9h19M12 9v11.5"/><path d="M12 9C9 9 7 7.8 7.6 6.1 8.3 4.3 11 5.5 12 9Zm0 0c3 0 5-1.2 4.4-2.9C15.7 4.3 13 5.5 12 9Z"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'music'    => '<path d="M9 18V5.5l11-2.5v12.5"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="15.5" r="2.5"/>',
		'snow'     => '<path d="M12 2.5v19M3.8 7.2l16.4 9.6M3.8 16.8l16.4-9.6"/><path d="m9.5 4 2.5 2 2.5-2M9.5 20l2.5-2 2.5 2M4 10.5l3 .9.4-3.2M20 13.5l-3-.9-.4 3.2M4 13.5l3-.9.4 3.2M20 10.5l-3 .9-.4-3.2"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5.5H4.5V6H10"/>',
		'pin'      => '<path d="M12 21.5s7-6.3 7-12a7 7 0 0 0-14 0c0 5.7 7 12 7 12Z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'mail'     => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
		'radar'    => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><path d="M12 12 18 6"/>',
	);
	$fill = array(
		'facebook' => '<path d="M13.5 21.5v-7.7h2.6l.4-3h-3V8.9c0-.9.3-1.5 1.5-1.5h1.6V4.7a21 21 0 0 0-2.3-.1c-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6v7.7Z"/>',
		'tiktok'   => '<path d="M16.6 3h-3.1v12.3a2.7 2.7 0 1 1-1.9-2.6V9.6a5.8 5.8 0 1 0 5 5.7V9.1a7.4 7.4 0 0 0 4.3 1.4V7.4A4.3 4.3 0 0 1 16.6 3Z"/>',
		'instagram' => '<path fill-rule="evenodd" d="M7.5 2.5h9a5 5 0 0 1 5 5v9a5 5 0 0 1-5 5h-9a5 5 0 0 1-5-5v-9a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h9a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3Zm4.5 3a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm5-3.2a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2Z"/>',
		'youtube'  => '<path fill-rule="evenodd" d="M21.6 7.2a2.6 2.6 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4a2.6 2.6 0 0 0-1.8 1.8A27 27 0 0 0 2 12a27 27 0 0 0 .4 4.8 2.6 2.6 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.6 2.6 0 0 0 1.8-1.8A27 27 0 0 0 22 12a27 27 0 0 0-.4-4.8ZM10 15V9l5.2 3Z"/>',
	);
	$cls = 'rr-icon rr-icon--' . $name . ( $class ? ' ' . $class : '' );
	if ( isset( $fill[ $name ] ) ) {
		return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $fill[ $name ] . '</svg>';
	}
	if ( ! isset( $p[ $name ] ) ) {
		return '';
	}
	return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $name ] . '</svg>';
}

/**
 * Colored weather illustration for an Open-Meteo code.
 */
function ridgeroad_wx_icon( $code, $is_day = 0, $class = '' ) {
	$code  = (int) $code;
	$moon  = '<path d="M30 8a14 14 0 1 0 15.5 19A11 11 0 0 1 30 8Z" fill="url(#rrMoon)"/>';
	$sun   = '<g fill="url(#rrSun)"><circle cx="27" cy="22" r="9"/></g><g stroke="#ffd36b" stroke-width="2.4" stroke-linecap="round"><path d="M27 5v4M27 35v4M10 22h4M40 22h4M15 10l3 3M36 31l3 3M15 34l3-3M36 13l3-3"/></g>';
	$cloud = '<path d="M17 50h30a9 9 0 0 0 1.2-17.9A13 13 0 0 0 23.4 29 10.5 10.5 0 0 0 17 50Z" fill="url(#rrCloud)"/>';
	$body  = $is_day ? $sun : $moon;
	$extra = '';

	if ( 0 === $code ) {
		$cloud = '';
	} elseif ( 1 === $code ) {
		$cloud = '<path d="M27 52h22a7 7 0 0 0 .9-13.9A10 10 0 0 0 31 37.4 8 8 0 0 0 27 52Z" fill="url(#rrCloud)"/>';
	} elseif ( 3 === $code || 45 === $code || 48 === $code ) {
		$body = '<path d="M28 42h22a8 8 0 0 0 .5-16A11 11 0 0 0 30 25a8.5 8.5 0 0 0-2 17Z" fill="#8fa3c6" opacity=".8"/>';
	}
	if ( ( $code >= 51 && $code <= 67 ) || ( $code >= 80 && $code <= 82 ) ) {
		$extra = '<g stroke="#7cc3ff" stroke-width="2.6" stroke-linecap="round"><path d="M22 54l-3 6M32 54l-3 6M42 54l-3 6"/></g>';
	} elseif ( ( $code >= 71 && $code <= 77 ) || 85 === $code || 86 === $code ) {
		$extra = '<g fill="#fff"><circle cx="21" cy="57" r="2.2"/><circle cx="31" cy="60" r="2.2"/><circle cx="41" cy="57" r="2.2"/></g>';
	} elseif ( $code >= 95 ) {
		$extra = '<path d="M33 50l-6 9h5l-3 7 9-11h-5l3-5Z" fill="#ffd34d"/>';
	} elseif ( 45 === $code || 48 === $code ) {
		$extra = '<g stroke="#cfd8ea" stroke-width="2.4" stroke-linecap="round"><path d="M14 55h34M18 60h30"/></g>';
	}
	$defs = '<defs><radialGradient id="rrMoon" cx=".35" cy=".35" r=".8"><stop offset="0" stop-color="#fff3a0"/><stop offset="1" stop-color="#ffbe1f"/></radialGradient>'
		. '<radialGradient id="rrSun" cx=".4" cy=".4" r=".7"><stop offset="0" stop-color="#fff3b0"/><stop offset="1" stop-color="#ffb020"/></radialGradient>'
		. '<linearGradient id="rrCloud" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#b8c4de"/></linearGradient></defs>';
	return '<svg class="rr-wx ' . esc_attr( $class ) . '" viewBox="0 0 64 66" aria-hidden="true">' . $defs . $body . $cloud . $extra . '</svg>';
}
