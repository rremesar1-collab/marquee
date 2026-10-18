<?php
/**
 * Full-color illustrated icons (matching the mockup's glowing gold / red / green icons).
 * Falls back to the line icons in icons.php for names not drawn here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ridgeroad_cicon( $name, $class = '' ) {
	static $n = 0;
	$n++;
	$g = 'rrg' . $n; // unique gradient id prefix per icon.

	$gold = '<linearGradient id="' . $g . 'go" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff4b0"/><stop offset=".45" stop-color="#ffcc33"/><stop offset="1" stop-color="#d4860f"/></linearGradient>';
	$red  = '<linearGradient id="' . $g . 're" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff6a5a"/><stop offset=".5" stop-color="#e5121e"/><stop offset="1" stop-color="#9a0710"/></linearGradient>';
	$grn  = '<linearGradient id="' . $g . 'gr" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5fe36d"/><stop offset=".5" stop-color="#1f9e35"/><stop offset="1" stop-color="#0d5e1e"/></linearGradient>';
	$G = 'url(#' . $g . 'go)';
	$R = 'url(#' . $g . 're)';
	$N = 'url(#' . $g . 'gr)';
	$dark = '#2a0a04';

	$icons = array(
		'radio'  => $gold . '<line x1="22" y1="21" x2="44" y2="7" stroke="' . $G . '" stroke-width="3.2" stroke-linecap="round"/><circle cx="44" cy="7" r="3" fill="' . $G . '"/>'
			. '<rect x="6" y="20" width="52" height="36" rx="7" fill="' . $G . '" stroke="#7a4a05" stroke-width="1.2"/>'
			. '<rect x="12" y="25" width="40" height="7" rx="2" fill="' . $dark . '"/><rect x="15" y="27" width="22" height="3" rx="1" fill="#ffcc33" opacity=".7"/>'
			. '<circle cx="21" cy="44" r="8" fill="' . $dark . '"/><circle cx="21" cy="44" r="4.5" fill="none" stroke="#ffcc33" stroke-width="1.8"/><circle cx="21" cy="44" r="1.6" fill="#ffcc33"/>'
			. '<circle cx="43" cy="44" r="8" fill="' . $dark . '"/><circle cx="43" cy="44" r="4.5" fill="none" stroke="#ffcc33" stroke-width="1.8"/><circle cx="43" cy="44" r="1.6" fill="#ffcc33"/>',
		'clock'  => $gold . '<circle cx="32" cy="32" r="26" fill="' . $G . '"/><circle cx="32" cy="32" r="20.5" fill="' . $dark . '"/>'
			. '<path d="M32 18v15l10 6" fill="none" stroke="' . $G . '" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="32" cy="33" r="2.6" fill="#ffcc33"/>',
		'music'  => '<path d="M24 46V14l28-6v32" fill="none" stroke="#ff3b30" stroke-width="5" stroke-linejoin="round"/>'
			. '<path d="M24 46V14l28-6v32" fill="none" stroke="#ffe0d6" stroke-width="1.6" stroke-linejoin="round"/>'
			. '<ellipse cx="17" cy="47" rx="8.5" ry="6.5" fill="#ff3b30"/><ellipse cx="17" cy="47" rx="4" ry="2.6" fill="#ffd8cc"/>'
			. '<ellipse cx="45" cy="41" rx="8.5" ry="6.5" fill="#ff3b30"/><ellipse cx="45" cy="41" rx="4" ry="2.6" fill="#ffd8cc"/>',
		'car'    => $red . '<path d="M6 40c0-4 2-6 6-7l6-11c1-2 3-3 5-3h18c2 0 4 1 5 3l6 11c4 1 6 3 6 7v7c0 2-1 3-3 3H9c-2 0-3-1-3-3z" fill="' . $R . '"/>'
			. '<path d="M20 23h10v10H15zM34 23h10l5 10H34z" fill="#bfe6ff"/><path d="M20 23h10v4H18z" fill="#fff" opacity=".5"/>'
			. '<rect x="8" y="38" width="7" height="4" rx="2" fill="#ffe680"/><rect x="49" y="38" width="7" height="4" rx="2" fill="#ffe680"/>'
			. '<circle cx="18" cy="50" r="6" fill="#1a1a1a"/><circle cx="18" cy="50" r="2.4" fill="#aaa"/><circle cx="46" cy="50" r="6" fill="#1a1a1a"/><circle cx="46" cy="50" r="2.4" fill="#aaa"/>',
		'bulb'   => '<radialGradient id="' . $g . 'bu" cx=".45" cy=".4" r=".6"><stop offset="0" stop-color="#ffffff"/><stop offset=".35" stop-color="#fff59a"/><stop offset="1" stop-color="#ffbf1f"/></radialGradient>'
			. '<path d="M32 4C21 4 13 12 13 23c0 7 4 12 8 16 2 2 3 4 3 7h16c0-3 1-5 3-7 4-4 8-9 8-16C51 12 43 4 32 4z" fill="url(#' . $g . 'bu)"/>'
			. '<path d="M27 30l5 6 5-6M32 36v10" fill="none" stroke="#e08a00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<rect x="23" y="47" width="18" height="5" rx="2" fill="#c9c9c9"/><rect x="24" y="52" width="16" height="4" rx="2" fill="#a8a8a8"/><path d="M27 56h10l-2 4h-6z" fill="#8a8a8a"/>',
		'home'   => $gold . '<path d="M32 6L4 30h8v26h40V30h8z" fill="' . $G . '"/><path d="M32 6L4 30h8L32 13l20 17h8z" fill="#fff4b0" opacity=".45"/>'
			. '<rect x="26" y="38" width="12" height="18" rx="1.5" fill="' . $dark . '"/><rect x="15" y="34" width="8" height="8" rx="1" fill="#3a1500"/><rect x="41" y="34" width="8" height="8" rx="1" fill="#3a1500"/>'
			. '<rect x="44" y="10" width="6" height="12" fill="' . $G . '"/>',
		'camera' => $gold . '<path d="M8 20h12l4-7h16l4 7h12c2 0 4 2 4 4v26c0 2-2 4-4 4H8c-2 0-4-2-4-4V24c0-2 2-4 4-4z" fill="' . $G . '"/>'
			. '<circle cx="32" cy="36" r="13" fill="' . $dark . '"/><circle cx="32" cy="36" r="9" fill="none" stroke="#ffcc33" stroke-width="2.4"/><circle cx="29" cy="33" r="3" fill="#fff" opacity=".7"/>'
			. '<rect x="48" y="24" width="6" height="4" rx="1" fill="#fff4b0"/>',
		'tree'   => $grn . $gold . '<path d="M32 8L14 32h8L10 48h44L42 32h8z" fill="' . $N . '"/><rect x="28" y="48" width="8" height="8" fill="#6b3a12"/>'
			. '<path d="M32 2l2.2 4.6 5 .7-3.6 3.5.9 5L32 13.4 27.5 15.8l.9-5-3.6-3.5 5-.7z" fill="' . $G . '"/>'
			. '<circle cx="26" cy="30" r="2.2" fill="#ff3b30"/><circle cx="37" cy="27" r="2.2" fill="#ffd23c"/><circle cx="22" cy="43" r="2.2" fill="#4aa8ff"/><circle cx="33" cy="40" r="2.2" fill="#ff3b30"/><circle cx="43" cy="44" r="2.2" fill="#ffd23c"/>',
		'heart'  => $red . '<path d="M32 56S6 41 6 22C6 13 13 7 21 7c5 0 9 3 11 7 2-4 6-7 11-7 8 0 15 6 15 15 0 19-26 34-26 34z" fill="' . $R . '"/>'
			. '<path d="M15 16c3-4 9-4 11 0" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".55"/>',
		'star'   => $gold . '<path d="M32 4l8 17 18 2.5-13 12.5 3.3 18L32 45l-16.3 9 3.3-18L6 23.5 24 21z" fill="' . $G . '" stroke="#9a5a05" stroke-width="1.2" stroke-linejoin="round"/>',
		'gift'   => $red . $gold . '<rect x="8" y="26" width="48" height="30" rx="2" fill="' . $R . '"/><rect x="5" y="18" width="54" height="10" rx="2" fill="' . $R . '"/><rect x="28" y="18" width="8" height="38" fill="' . $G . '"/>'
			. '<path d="M32 18c-6-10-18-10-16-2 1 3 8 3 16 2zm0 0c6-10 18-10 16-2-1 3-8 3-16 2z" fill="' . $G . '"/>',
		'cloud'  => $gold . '<circle cx="42" cy="22" r="11" fill="' . $G . '"/><g stroke="#ffcc33" stroke-width="3" stroke-linecap="round"><path d="M42 3v4M57 10l-3 3M61 22h-4"/></g>'
			. '<path d="M16 52h32a10 10 0 0 0 1-20 14 14 0 0 0-27-3 11 11 0 0 0-6 23z" fill="#fff8e6" stroke="#ffcc33" stroke-width="2.4"/>',
		'pin'    => $red . '<path d="M32 60S12 38 12 24a20 20 0 0 1 40 0c0 14-20 36-20 36z" fill="' . $R . '"/><circle cx="32" cy="24" r="8" fill="#fff4e0"/>',
		'mail'   => $gold . '<rect x="5" y="14" width="54" height="38" rx="5" fill="' . $G . '"/><path d="M7 17l25 19 25-19" fill="none" stroke="#7a4a05" stroke-width="3" stroke-linejoin="round"/>',
	);

	if ( ! isset( $icons[ $name ] ) ) {
		return ridgeroad_icon( $name, $class );
	}
	$cls = 'rr-icon rr-cicon rr-icon--' . $name . ( $class ? ' ' . $class : '' );
	return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 64 64" aria-hidden="true"><defs></defs>' . $icons[ $name ] . '</svg>';
}
