/* Kids' Christmas Light Lab (v3)
 * Kids pick a decoration, a light style and a color, then play their show
 * with a real song. The house is a painted picture; every light is drawn on
 * top of the picture's own bulbs. Nothing here ever talks to the real show.
 *
 * Public API (used by kids.js): window.RRLab = { ready, get, filled, snapshot,
 * songName, watch, unwatch, clean, playing, play, stop } */
(function () {
	'use strict';
	var root = document.querySelector('[data-rr-lab]');
	if (!root) { return; }
	var $ = function (s, r) { return (r || root).querySelector(s); };
	var $$ = function (s, r) { return Array.prototype.slice.call((r || root).querySelectorAll(s)); };
	var BASE = root.getAttribute('data-base'), VER = root.getAttribute('data-v') || '1';
	/* the stage can be moved into the Show Wall pop-up, so look things up inside it directly */
	var STAGE = root.querySelector('.rr-ll__stage');
	var $s = function (s) { return STAGE.querySelector(s); };
	var SONGLIST = [];
	try { SONGLIST = JSON.parse(root.getAttribute('data-songs') || '[]'); } catch (e) {}
	var SONGMAP = {}; SONGLIST.forEach(function (s) { SONGMAP[s.id] = s; });
	/* Marquee shows: their own radio station is written on the yard sign (null = the sign is part of the picture) */
	var SIGN = root.getAttribute('data-sign');
	var BW = 1672, BH = 468, CELL = 6;
	function stat(ev) { try { if (window.RRStat) { window.RRStat(ev); } } catch (e) {} }

	/* ---------------- decorations, styles, colors ---------------- */
	var PROPS = [
		{ id: 'tree', n: 'Mega Tree', parts: ['tree'] },
		{ id: 'roof', n: 'Rooflines', parts: ['roof'] },
		{ id: 'win', n: 'Windows', parts: ['win'] },
		{ id: 'flake', n: 'Snowflakes', parts: ['flake'] },
		{ id: 'spin', n: 'Spinners', parts: ['spin'] },
		{ id: 'faces', n: 'Singing Faces', parts: ['faces', 'face2'] },
		{ id: 'arch', n: 'Arches', parts: ['arch'] },
		{ id: 'mini', n: 'Mini Trees', parts: ['mini'] },
		{ id: 'gift', n: 'Gifts', parts: ['gift'] },
		{ id: 'garage', n: 'Garage Matrix', parts: ['garage'] }
	];
	var PIDS = PROPS.map(function (p) { return p.id; });
	var THUMB = { tree: [42, 83, 214, 362], roof: [1203, 31, 457, 209], win: [306, 234, 104, 102], flake: [1296, 167, 62, 68], spin: [339, 188, 38, 38],
		faces: [936, 166, 106, 60], arch: [314, 349, 136, 108], mini: [350, 372, 66, 86], gift: [250, 354, 60, 54], garage: [1276, 265, 301, 129] };
	var STYLES = [
		{ id: 'steady', n: 'Steady', d: 'Always on' }, { id: 'twinkle', n: 'Twinkle', d: 'Sparkles' }, { id: 'chase', n: 'Chase', d: 'Lights run' },
		{ id: 'pulse', n: 'Pulse', d: 'Blinks to the beat' }, { id: 'rainbow', n: 'Rainbow', d: 'All the colors' }, { id: 'candy', n: 'Candy Cane', d: 'Red and white' },
		{ id: 'off', n: 'Off', d: 'Lights off' }
	];
	var SMAP = {}; STYLES.forEach(function (s) { SMAP[s.id] = s; });
	var COLORS = [
		{ id: 'red', n: 'Red', c: [255, 40, 40] }, { id: 'green', n: 'Green', c: [30, 255, 90] }, { id: 'blue', n: 'Blue', c: [40, 110, 255] },
		{ id: 'gold', n: 'Gold', c: [255, 200, 30] }, { id: 'purple', n: 'Purple', c: [190, 70, 255] }, { id: 'ice', n: 'Icy Blue', c: [90, 230, 255] },
		{ id: 'white', n: 'Warm White', c: [255, 240, 205] }, { id: 'orange', n: 'Orange', c: [255, 130, 25] }, { id: 'pink', n: 'Pink', c: [255, 80, 190] },
		{ id: 'mix', n: 'Mixed Colors', mix: true }
	];
	var CMAP = {}; COLORS.forEach(function (c) { CMAP[c.id] = c; });
	var MIX = [[255, 40, 40], [30, 255, 90], [40, 110, 255], [255, 200, 30], [190, 70, 255], [255, 80, 190]];
	var NO_COLOR = { rainbow: 1, candy: 1, off: 1 };
	var PART_N = ['Start', 'Middle', 'Big Finish'];

	function preset() {
		var P = {
			tree: [['twinkle', 'green'], ['chase', 'mix'], ['rainbow', 'mix']], roof: [['steady', 'white'], ['chase', 'red'], ['candy', 'red']],
			win: [['steady', 'blue'], ['pulse', 'blue'], ['rainbow', 'mix']], flake: [['twinkle', 'ice'], ['twinkle', 'white'], ['pulse', 'ice']],
			spin: [['chase', 'gold'], ['chase', 'gold'], ['rainbow', 'mix']], faces: [['steady', 'green'], ['pulse', 'red'], ['pulse', 'mix']],
			arch: [['candy', 'red'], ['chase', 'red'], ['chase', 'mix']], mini: [['steady', 'green'], ['twinkle', 'gold'], ['pulse', 'mix']],
			gift: [['steady', 'pink'], ['twinkle', 'purple'], ['pulse', 'mix']], garage: [['twinkle', 'white'], ['chase', 'mix'], ['rainbow', 'mix']]
		}, c = {};
		Object.keys(P).forEach(function (k) { c[k] = P[k].map(function (p) { return { s: p[0], c: p[1] }; }); });
		return c;
	}
	/* A show from anywhere (saved, a file, the Show Wall) made safe, or null. */
	function clean(o) {
		if (!o || typeof o !== 'object' || o.v !== 3 || !o.cells) { return null; }
		var out = { v: 3, song: SONGMAP[o.song] ? o.song : (SONGLIST[0] ? SONGLIST[0].id : ''), name: typeof o.name === 'string' ? o.name.slice(0, 40) : '', cells: {} };
		PIDS.forEach(function (p) {
			var row = Array.isArray(o.cells[p]) ? o.cells[p] : [];
			out.cells[p] = [0, 1, 2].map(function (i) {
				var c = row[i] || {};
				return { s: SMAP[c.s] ? c.s : 'steady', c: CMAP[c.c] ? c.c : 'white' };
			});
		});
		return out;
	}
	function filled(s) { s = clean(s); return !!s && PIDS.some(function (p) { return s.cells[p].some(function (c) { return c.s !== 'off'; }); }); }

	var STORE = 'rrLightLab3';
	var mine = null;
	try { mine = clean(JSON.parse(localStorage.getItem(STORE) || 'null')); } catch (e) {}
	if (!mine) { mine = { v: 3, song: SONGLIST[0] ? SONGLIST[0].id : '', name: '', cells: preset() }; }
	var show = mine, watching = null;
	var sel = { prop: 'all', part: 'all' };
	function saveLocal() { try { localStorage.setItem(STORE, JSON.stringify(mine)); return true; } catch (e) { return false; } }

	/* ---------------- loading the picture and the light maps ---------------- */
	var bg = new Image(), MASKS = null, maskImg = {}, readyCbs = [], isReady = false;
	function whenReady(cb) { if (isReady) { cb(); } else { readyCbs.push(cb); } }
	fetch(BASE + 'masks.json?v=' + VER).then(function (r) { return r.json(); }).then(function (m) {
		MASKS = m;
		var keys = Object.keys(MASKS), need = keys.length + 1, got = 0;
		var one = function () { if (++got === need) { build(); } };
		bg.onload = one; bg.src = BASE + 'house.jpg?v=' + VER;
		keys.forEach(function (k) { var i = new Image(); i.onload = one; i.src = BASE + 'm-' + k + '.png?v=' + VER; maskImg[k] = i; });
	}).catch(function () { var l = $s('.rr-ll__loading'); if (l) { l.textContent = 'The Light Lab could not load. Please refresh the page.'; } });

	var FX = {}, lights, lctx, glowA, glowB, cv, ctx, hitMap, HW = Math.ceil(BW / 4), HH = Math.ceil(BH / 4);
	function build() {
		cv = $s('[data-ll="canvas"]'); ctx = cv.getContext('2d');
		lights = document.createElement('canvas'); lights.width = BW; lights.height = BH; lctx = lights.getContext('2d');
		glowA = document.createElement('canvas'); glowA.width = Math.round(BW / 5); glowA.height = Math.round(BH / 5);
		glowB = document.createElement('canvas'); glowB.width = Math.round(BW / 14); glowB.height = Math.round(BH / 14);
		hitMap = new Int8Array(HW * HH).fill(-1);
		Object.keys(MASKS).forEach(function (k) {
			var m = MASKS[k];
			var mc = document.createElement('canvas'); mc.width = m.w; mc.height = m.h;
			var mx = mc.getContext('2d'); mx.drawImage(maskImg[k], 0, 0);
			var id = mx.getImageData(0, 0, m.w, m.h), d = id.data, cells = [], seen = {};
			var pi = PROPS.findIndex(function (p) { return p.parts.indexOf(k) > -1; });
			for (var i = 0, n = 0; i < d.length; i += 4, n++) {
				var v = d[i];
				d[i] = d[i + 1] = d[i + 2] = 255; d[i + 3] = v;
				if (v > 50) {
					var px = n % m.w, py = (n / m.w) | 0, cx = (px / CELL) | 0, cy = (py / CELL) | 0, key = cx + ',' + cy;
					if (!seen[key]) { seen[key] = 1; cells.push([cx * CELL, cy * CELL]); }
					if (pi >= 0) { hitMap[(((m.y + py) / 4) | 0) * HW + (((m.x + px) / 4) | 0)] = pi; }
				}
			}
			mx.putImageData(id, 0, 0);
			cells.forEach(function (c) { c.push(Math.random(), 0.6 + Math.random() * 0.9, (Math.random() * MIX.length) | 0); });
			var fx = document.createElement('canvas'); fx.width = m.w; fx.height = m.h;
			FX[k] = { m: m, mask: mc, fx: fx, fctx: fx.getContext('2d'), cells: cells };
		});
		var grown = Int8Array.from(hitMap);
		for (var y = 0; y < HH; y++) { for (var x = 0; x < HW; x++) { if (hitMap[y * HW + x] < 0) {
			outer: for (var dy = -2; dy <= 2; dy++) { for (var dx = -2; dx <= 2; dx++) {
				var yy = y + dy, xx = x + dx;
				if (yy >= 0 && yy < HH && xx >= 0 && xx < HW && hitMap[yy * HW + xx] >= 0) { grown[y * HW + x] = hitMap[yy * HW + xx]; break outer; }
			} } } } }
		hitMap = grown;
		ui(); resize(); root.classList.add('is-ready');
		isReady = true; readyCbs.splice(0).forEach(function (cb) { try { cb(); } catch (e) {} });
		requestAnimationFrame(frame);
	}

	/* ---------------- drawing ---------------- */
	function rgb(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a.toFixed(3) + ')'; }
	function hsl(h) { var l = .55, a = Math.min(l, 1 - l), k = function (n) { return (n + h * 12) % 12; };
		var f = function (n) { return Math.round(255 * (l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1))))); }; return [f(0), f(8), f(4)]; }
	function paint(k, cell, t, beat, flash) {
		var F = FX[k], g = F.fctx, m = F.m, s = cell.s, col = CMAP[cell.c] || CMAP.white;
		g.globalCompositeOperation = 'source-over'; g.clearRect(0, 0, m.w, m.h);
		if (s === 'off') { return false; }
		var bp = beat - Math.floor(beat), pulse = 0.18 + 0.82 * Math.exp(-bp * 3.2);
		var cells = F.cells, n = cells.length, i, c, a = 1, cc, u, ph;
		for (i = 0; i < n; i++) {
			c = cells[i];
			cc = col.mix ? MIX[c[4]] : col.c;
			u = (m.x + c[0]) / BW + (k === 'tree' ? (1 - (m.y + c[1]) / BH) * .6 : 0);
			if (s === 'steady') { a = 1; }
			else if (s === 'twinkle') { a = 0.12 + 0.88 * Math.pow(Math.max(0, Math.sin((t * c[3] * 1.6 + c[2]) * 6.283)), 4); }
			else if (s === 'chase') { ph = (u * 9 - beat * 0.5) % 1; ph = ph < 0 ? ph + 1 : ph; a = 0.08 + 0.92 * Math.max(0, 1 - ph * 3); }
			else if (s === 'pulse') { a = pulse; }
			else if (s === 'rainbow') { cc = hsl(((u * 2.2 - t * 0.35) % 1 + 1) % 1); a = 1; }
			else if (s === 'candy') { ph = (((m.x + c[0]) + (m.y + c[1])) / 26 - t * 0.9) % 1; ph = ph < 0 ? ph + 1 : ph; cc = ph < 0.5 ? [255, 36, 36] : [255, 245, 235]; a = 1; }
			if (flash) { a = Math.min(1, a + flash); }
			g.fillStyle = rgb(cc, a); g.fillRect(c[0], c[1], CELL, CELL);
		}
		g.globalCompositeOperation = 'destination-in'; g.drawImage(F.mask, 0, 0);
		return true;
	}
	/* Draws one frame of a show into a 2D context of any size. */
	function render(c2, W, H, s, part, t, beat, dimOthers, flashFor, flashAmt, lightA, dimA) {
		lctx.clearRect(0, 0, BW, BH);
		PROPS.forEach(function (p) {
			var cell = s.cells[p.id][part];
			p.parts.forEach(function (k) {
				if (!FX[k]) { return; }
				var f = (flashFor === p.id || flashFor === 'all') ? flashAmt : 0;
				if (paint(k, cell, t, beat, f)) {
					lctx.globalAlpha = (dimOthers && dimOthers !== p.id) ? 0.22 : 1;
					lctx.drawImage(FX[k].fx, FX[k].m.x, FX[k].m.y); lctx.globalAlpha = 1;
				}
			});
		});
		if (FX.sign) { paint('sign', { s: 'steady', c: 'white' }, t, beat, 0); lctx.drawImage(FX.sign.fx, FX.sign.m.x, FX.sign.m.y); }
		c2.globalCompositeOperation = 'source-over'; c2.globalAlpha = 1;
		c2.drawImage(bg, 0, 0, W, H);
		c2.fillStyle = 'rgba(0,4,20,' + dimA.toFixed(3) + ')'; c2.fillRect(0, 0, W, H);
		var ga = glowA.getContext('2d'), gb = glowB.getContext('2d');
		ga.clearRect(0, 0, glowA.width, glowA.height); ga.drawImage(lights, 0, 0, glowA.width, glowA.height);
		gb.clearRect(0, 0, glowB.width, glowB.height); gb.drawImage(glowA, 0, 0, glowB.width, glowB.height);
		c2.globalCompositeOperation = 'lighter'; c2.imageSmoothingEnabled = true;
		c2.globalAlpha = 0.9 * lightA; c2.drawImage(glowB, 0, 0, W, H);
		c2.globalAlpha = lightA; c2.drawImage(glowA, 0, 0, W, H);
		c2.drawImage(lights, 0, 0, W, H); c2.drawImage(lights, 0, 0, W, H);
		c2.globalAlpha = 1; c2.globalCompositeOperation = 'source-over';
		if (SIGN) { drawSign(c2, W / BW, H / BH); }
	}
	function drawSign(c2, sx, sy) {
		var lines = ['TUNE TO', SIGN.toUpperCase()], cx = 771, top = 419, boxW = 72, lh = 18;
		c2.save(); c2.scale(sx, sy); c2.fillStyle = '#fbf8f0'; c2.textAlign = 'center'; c2.textBaseline = 'top';
		c2.shadowColor = 'rgba(255, 250, 235, 0.85)'; c2.shadowBlur = 5 * Math.max(sx, sy);
		lines.forEach(function (l, i) {
			var fs = 16; c2.font = '600 ' + fs + 'px Arial, Helvetica, sans-serif';
			while (fs > 8 && c2.measureText(l).width > boxW) { fs -= 1; c2.font = '600 ' + fs + 'px Arial, Helvetica, sans-serif'; }
			c2.fillText(l, cx, top + i * lh + (16 - fs) / 2);
		});
		c2.restore();
	}

	/* show moments: building, lights out before the song, playing, lights out after */
	var mode = 'edit', tMode = 0, EDIT_DIM = 0.22, PLAY_DIM = 0.5, DARK_DIM = 0.62;
	function lerp(a, b, k) { k = Math.max(0, Math.min(1, k)); return a + (b - a) * k; }
	function look(now) {
		var e = now - tMode;
		if (mode === 'intro') { return [Math.max(0, 1 - e / 250), lerp(EDIT_DIM, DARK_DIM, e / 400)]; }
		if (mode === 'play') { return [Math.min(1, e / 180), lerp(DARK_DIM, PLAY_DIM, e / 600)]; }
		if (mode === 'outro') {
			if (e < 550) { return [1 - e / 550, lerp(PLAY_DIM, DARK_DIM, e / 550)]; }
			if (e > 1500) { mode = 'edit'; }
			return [lerp(0, 1, (e - 750) / 600), lerp(DARK_DIM, EDIT_DIM, (e - 750) / 600)];
		}
		return [1, EDIT_DIM];
	}
	function songInfo(id) { var s = SONGMAP[id] || SONGLIST[0] || { bpm: 120, offset: 0, len: 60 }; return { spb: 60 / s.bpm, off: s.offset || 0, len: s.len || 60 }; }
	var audio = new Audio(); audio.preload = 'none';
	audio.addEventListener('ended', function () { stopAll(); });
	var silent0 = null, SILENT_LEN = 60;   // no songs at all: the lights play on their own for a minute
	function songNow() {
		if (silent0 !== null) { var e = (performance.now() - silent0) / 1000; if (e >= SILENT_LEN) { silent0 = null; stopAll(); return null; } return e; }
		return (!audio.paused && audio.getAttribute('data-id')) ? audio.currentTime : null;
	}

	var clock0 = performance.now(), flashT = 0, flashProp = null, lastPart = -1;
	function frame(now) {
		requestAnimationFrame(frame);
		if (!cv.offsetParent && !document.fullscreenElement) { return; }   // hidden: skip the work
		var si = songInfo(show.song), st = songNow(), t, part;
		if (st !== null) { t = st; part = Math.min(2, Math.floor(st / (si.len / 3))); }
		else { t = (now - clock0) / 1000; part = sel.part === 'all' ? 0 : sel.part; }
		var beat = (t - si.off) / si.spb;
		if (part !== lastPart) { lastPart = part; markPart(part, st !== null); }
		head(st, si.len);
		var fl = Math.max(0, 1 - (now - flashT) / 700) * 0.9, lk = look(now);
		var dim = (st === null && mode === 'edit' && !watching && sel.prop !== 'all') ? sel.prop : null;
		render(ctx, cv.width, cv.height, show, part, t, beat, dim, watching ? null : flashProp, fl, lk[0], lk[1]);
	}
	function resize() {
		if (!cv) { return; }
		var r = cv.getBoundingClientRect(), dpr = Math.min(2, window.devicePixelRatio || 1);
		cv.width = Math.max(600, Math.round((r.width || 900) * dpr)); cv.height = Math.round(cv.width * BH / BW);
	}
	window.addEventListener('resize', resize);
	document.addEventListener('fullscreenchange', function () { setTimeout(resize, 100); });

	/* ---------------- playing ---------------- */
	var introT = null;
	function startAll() {
		if (mode === 'intro') { return; }
		var s = SONGMAP[show.song] || SONGLIST[0];
		if (!s) {
			mode = 'intro'; tMode = performance.now(); playing(true);
			introT = setTimeout(function () { introT = null; silent0 = performance.now(); mode = 'play'; tMode = performance.now(); }, 950);
			if (!watching) { stat('lab_play'); }
			return;
		}
		if (songNow() !== null) { audio.pause(); }
		mode = 'intro'; tMode = performance.now(); playing(true);
		if (audio.getAttribute('data-id') !== s.id) { audio.src = s.src; audio.setAttribute('data-id', s.id); }
		audio.preload = 'auto'; try { audio.load(); } catch (e) {}
		introT = setTimeout(function () {
			introT = null;
			try { audio.currentTime = 0; } catch (e) {}
			var pr = audio.play();
			if (pr && pr.catch) { pr.catch(function () { mode = 'edit'; playing(false); toast('Tap Play again to start the music.'); }); }
			mode = 'play'; tMode = performance.now();
		}, 950);
		if (!watching) { stat('lab_play'); }
	}
	function stopAll() {
		var was = mode === 'play' || mode === 'intro';
		if (introT) { clearTimeout(introT); introT = null; }
		silent0 = null;
		if (!audio.paused) { audio.pause(); }
		playing(false);
		if (was) { mode = 'outro'; tMode = performance.now(); }
	}
	function isPlaying() { return mode === 'intro' || songNow() !== null; }
	function playing(on) {
		$$('[data-ll="play"]').forEach(function (b) {
			b.setAttribute('aria-pressed', String(on));
			var ico = b.querySelector('[data-ico]'); if (ico) { ico.innerHTML = on ? '<path d="M6 6h12v12H6z"/>' : '<path d="M8 5v14l11-7z"/>'; }
			var tx = b.querySelector('[data-ll="playtxt"]'); if (tx) { tx.textContent = on ? 'Stop' : (watching ? 'Play' : 'Play my show'); }
		});
		if (on) { var h = $s('[data-ll="hint"]'); if (h) { h.hidden = true; } }
	}

	/* ---------------- the three steps ---------------- */
	var step = 1;
	function fmt(s) { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2); }
	function propName(id) { return id === 'all' ? 'whole house' : PROPS[PIDS.indexOf(id)].n; }
	function the(id) { return id === 'all' ? 'the whole house' : 'the ' + propName(id); }
	function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt) { e.textContent = txt; } return e; }
	function thumb(e, id, w, h) {
		var r = THUMB[id], sc = Math.min(w / r[2], h / r[3]);
		e.style.backgroundImage = 'url(' + bg.src + ')'; e.style.backgroundSize = (BW * sc) + 'px ' + (BH * sc) + 'px';
		e.style.backgroundPosition = (-r[0] * sc + (w - r[2] * sc) / 2) + 'px ' + (-r[1] * sc + (h - r[3] * sc) / 2) + 'px';
	}
	function styleIcon(cnv, id) {
		var g = cnv.getContext('2d'), w = cnv.width = 132, h = cnv.height = 88; g.clearRect(0, 0, w, h);
		for (var i = 0; i < 7; i++) { for (var j = 0; j < 4; j++) {
			var x = 14 + i * 17.5, y = 14 + j * 20, c = [255, 210, 70], a = 1;
			if (id === 'twinkle') { a = (i * 7 + j * 3) % 5 === 0 ? 1 : 0.25; c = [255, 240, 200]; }
			if (id === 'chase') { a = (i % 3 === 0) ? 1 : 0.2; c = [255, 70, 50]; }
			if (id === 'pulse') { a = j > 1 ? 1 : 0.35; c = [80, 230, 255]; }
			if (id === 'rainbow') { c = hsl(i / 7); }
			if (id === 'candy') { c = ((i + j) % 2) ? [255, 255, 255] : [255, 40, 40]; }
			if (id === 'off') { c = [120, 130, 160]; a = 0.35; }
			g.fillStyle = rgb(c, a); g.shadowColor = rgb(c, a); g.shadowBlur = id === 'off' ? 0 : 10;
			g.beginPath(); g.arc(x, y, 6, 0, 6.283); g.fill();
		} }
	}
	function ui() {
		var s = $('[data-ll="song"]');
		SONGLIST.forEach(function (x) { var o = el('option', '', x.n); o.value = x.id; s.appendChild(o); });
		if (!SONGLIST.length) { s.hidden = true; var lb = root.querySelector('label[for="' + s.id + '"]'); if (lb) { lb.hidden = true; } }
		s.value = show.song;
		s.addEventListener('change', function () { mine.song = s.value; if (!watching) { show = mine; } stopAll(); renderParts(); saveLocal(); });

		var pBox = $('[data-ll-step="1"]');
		[{ id: 'all', n: 'Whole House' }].concat(PROPS).forEach(function (p) {
			var b = el('button', 'rr-ll__tile' + (p.id === 'all' ? ' is-all' : '')); b.type = 'button'; b.setAttribute('data-prop', p.id); b.setAttribute('aria-pressed', 'false');
			var th = el('span', 'rr-ll__th'); if (p.id === 'all') { th.textContent = '★'; } else { thumb(th, p.id, 54, 54); }
			b.appendChild(th); b.appendChild(el('span', '', p.n));
			b.addEventListener('click', function () { pick(p.id, true); go(2); }); pBox.appendChild(b);
		});
		var sBox = $('[data-ll-step="2"]');
		STYLES.forEach(function (x) {
			var b = el('button', 'rr-ll__tile rr-ll__tile--style'); b.type = 'button'; b.setAttribute('data-style', x.id); b.setAttribute('aria-pressed', 'false');
			var c = document.createElement('canvas'); styleIcon(c, x.id); b.appendChild(c);
			b.appendChild(el('span', '', x.n)); b.appendChild(el('small', '', x.d));
			b.addEventListener('click', function () { apply('s', x.id); go(NO_COLOR[x.id] ? 4 : 3); }); sBox.appendChild(b);
		});
		var cBox = $('[data-ll-step="3"]');
		COLORS.forEach(function (x) {
			var b = el('button', 'rr-ll__tile rr-ll__tile--color'); b.type = 'button'; b.setAttribute('data-color', x.id); b.setAttribute('aria-pressed', 'false');
			var ball = el('span', 'rr-ll__ball');
			ball.style.background = x.mix ? 'conic-gradient(#ff2828,#ffc81e,#1eff5a,#286eff,#be46ff,#ff50be,#ff2828)' : 'radial-gradient(circle at 35% 30%, #fff 0, ' + rgb(x.c, 1) + ' 45%)';
			ball.style.setProperty('--glow', x.mix ? 'rgba(255,255,255,.3)' : rgb(x.c, .55));
			b.appendChild(ball); b.appendChild(el('span', '', x.n));
			b.addEventListener('click', function () { apply('c', x.id); go(4); }); cBox.appendChild(b);
		});
		$$('[data-ll-goto]').forEach(function (b) { b.addEventListener('click', function () { go(+b.getAttribute('data-ll-goto')); }); });
		$('[data-ll="again"]').addEventListener('click', function () { go(1); });

		var rows = $('[data-ll="rows"]');
		PROPS.forEach(function (p) {
			var r = el('div', 'rr-ll__row'); r.setAttribute('data-row', p.id);
			var rn = el('span', 'rr-ll__rn'), rt = el('span', 'rr-ll__rt'); thumb(rt, p.id, 22, 15); rn.appendChild(rt); rn.appendChild(el('span', '', p.n)); r.appendChild(rn);
			[0, 1, 2].forEach(function (i) {
				var b = el('button', 'rr-ll__seg'); b.type = 'button'; b.setAttribute('aria-label', p.n + ', ' + PART_N[i]);
				b.addEventListener('click', function () { setPart(i); pick(p.id, true); go(2); });
				r.appendChild(b);
			});
			rows.appendChild(r);
		});
		$('[data-ll="whole"]').addEventListener('click', function () { setPart('all'); });

		$$('[data-ll="play"]').forEach(function (b) { b.addEventListener('click', function () { if (isPlaying()) { stopAll(); } else { startAll(); } }); });
		$('[data-ll="play2"]').addEventListener('click', function () { if (!isPlaying()) { startAll(); } STAGE.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
		$$('[data-ll="full"]').forEach(function (b) { b.addEventListener('click', function () { full(); if (b.getAttribute('data-play') && !isPlaying()) { startAll(); } }); });
		var nm = $('[data-ll="name"]');
		nm.value = mine.name || '';
		nm.addEventListener('input', function () { mine.name = nm.value.slice(0, 40); saveLocal(); });
		$('[data-ll="save"]').addEventListener('click', function () { toast(saveLocal() ? 'Saved on this device!' : 'Could not save on this device.'); stat('lab_save'); });
		$('[data-ll="unwatch"]').addEventListener('click', unwatch);
		cv.addEventListener('click', function (e) {
			if (watching) { return; }
			var r = cv.getBoundingClientRect(), x = (e.clientX - r.left) / r.width * BW, y = (e.clientY - r.top) / r.height * BH;
			var i = hitMap[((y / 4) | 0) * HW + ((x / 4) | 0)];
			if (i >= 0) { pick(PROPS[i].id, false); go(2); }
		});
		renderParts(); pick('all', false); go(1);
	}
	function say() {
		var n = the(sel.prop), part = sel.part === 'all' ? '' : ' in the ' + PART_N[sel.part].toLowerCase() + ' of the song';
		$('[data-ll="say"]').textContent = {
			1: 'What should we light up? Tap a decoration on the house, or pick one here!',
			2: 'How should ' + n + ' light up' + part + '?',
			3: 'What color should ' + n + ' be?',
			4: (sel.prop === 'all' ? 'The whole house looks great!' : 'Your ' + propName(sel.prop) + ' looks great!') + ' Light up something else, or play your show!'
		}[step];
	}
	function cur1() { return mine.cells[sel.prop === 'all' ? 'tree' : sel.prop][sel.part === 'all' ? 0 : sel.part]; }
	function go(n) {
		step = n;
		$$('[data-ll-step]').forEach(function (d) { d.hidden = +d.getAttribute('data-ll-step') !== n; });
		$$('[data-ll-goto]').forEach(function (b) {
			var k = +b.getAttribute('data-ll-goto');
			if (k === n) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
			b.classList.toggle('is-done', k < n);
		});
		var c = cur1();
		$('[data-ll-sum="1"]').textContent = n > 1 ? propName(sel.prop) : 'Tap it on the house';
		$('[data-ll-sum="2"]').textContent = n > 2 ? SMAP[c.s].n : 'Twinkle, chase and more';
		$('[data-ll-sum="3"]').textContent = n > 3 ? (NO_COLOR[c.s] ? 'Not needed' : CMAP[c.c].n) : 'Red, green, blue…';
		say(); refresh();
	}
	function renderParts() {
		var len = songInfo(show.song).len, box = $('[data-ll="parts"]');
		$$('[data-part]', box).forEach(function (b) { b.remove(); });
		PART_N.forEach(function (n, i) {
			var b = el('button', 'rr-ll__part', n); b.type = 'button'; b.setAttribute('data-part', i); b.setAttribute('aria-pressed', String(sel.part === i));
			b.appendChild(el('small', '', fmt(len / 3 * i) + ' – ' + fmt(len / 3 * (i + 1))));
			b.addEventListener('click', function () { setPart(i); go(1); }); box.appendChild(b);
		});
		var tm = $('[data-ll="time"]'); if (tm) { tm.textContent = '0:00 / ' + fmt(len); }
	}
	function pick(id, scroll) {
		sel.prop = id; flashProp = id; flashT = performance.now();
		$$('[data-prop]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-prop') === id)); });
		var pk = $s('[data-ll="picked"]'); pk.hidden = id === 'all'; pk.textContent = id === 'all' ? '' : 'Changing: ' + propName(id);
		if (id !== 'all') { $s('[data-ll="hint"]').hidden = true; }
		if (id !== 'all' && scroll !== false) {
			var r = THUMB[id], sc = $s('.rr-ll__scroll');
			if (sc && sc.scrollWidth > sc.clientWidth) { sc.scrollTo({ left: (r[0] + r[2] / 2) / BW * sc.scrollWidth - sc.clientWidth / 2, behavior: 'smooth' }); }
		}
	}
	function setPart(p) {
		sel.part = p; $('[data-ll="whole"]').setAttribute('aria-pressed', String(p === 'all'));
		$$('[data-part]').forEach(function (b) { b.setAttribute('aria-pressed', String(+b.getAttribute('data-part') === p)); });
		lastPart = -1; refresh();
	}
	function apply(key, val) {
		var props = sel.prop === 'all' ? PIDS : [sel.prop], parts = sel.part === 'all' ? [0, 1, 2] : [sel.part];
		props.forEach(function (p) { parts.forEach(function (i) {
			mine.cells[p][i][key] = val;
			if (key === 'c' && NO_COLOR[mine.cells[p][i].s]) { mine.cells[p][i].s = 'steady'; }
		}); });
		flashProp = sel.prop; flashT = performance.now(); saveLocal(); refresh();
	}
	function segBg(cell) {
		if (cell.s === 'off') { return ''; }
		var c = CMAP[cell.c] || CMAP.white, base = c.mix ? 'linear-gradient(90deg,#ff2828,#ffc81e,#1eff5a,#286eff,#be46ff,#ff50be)' : rgb(c.c, 1);
		if (cell.s === 'rainbow') { return 'linear-gradient(90deg,#ff2828,#ffc81e,#1eff5a,#28c8ff,#286eff,#be46ff)'; }
		if (cell.s === 'candy') { return 'repeating-linear-gradient(135deg,#ff2424 0 7px,#fff 7px 14px)'; }
		if (cell.s === 'twinkle') { return 'radial-gradient(circle,rgba(255,255,255,.95) 0 1.6px,transparent 2.2px) 0 0/9px 9px,' + base; }
		if (cell.s === 'chase') { return 'repeating-linear-gradient(90deg,rgba(0,0,0,0) 0 10px,rgba(0,0,0,.45) 10px 16px),' + base; }
		if (cell.s === 'pulse') { return 'linear-gradient(90deg,rgba(0,0,0,.45),rgba(0,0,0,0) 30%,rgba(0,0,0,.45) 34%,rgba(0,0,0,0) 64%,rgba(0,0,0,.45) 68%,rgba(0,0,0,0)),' + base; }
		return base;
	}
	function refresh() {
		PROPS.forEach(function (p) {
			var r = $('[data-row="' + p.id + '"]');
			mine.cells[p.id].forEach(function (cell, i) {
				var b = r.children[i + 1]; b.style.background = segBg(cell); b.classList.toggle('is-off', cell.s === 'off');
				b.classList.toggle('is-sel', (sel.prop === 'all' || sel.prop === p.id) && (sel.part === 'all' || sel.part === i));
			});
		});
		var props = sel.prop === 'all' ? PIDS : [sel.prop], parts = sel.part === 'all' ? [0, 1, 2] : [sel.part], ss = {}, cs = {};
		props.forEach(function (p) { parts.forEach(function (i) { ss[mine.cells[p][i].s] = 1; cs[mine.cells[p][i].c] = 1; }); });
		var s1 = Object.keys(ss).length === 1 ? Object.keys(ss)[0] : null, c1 = Object.keys(cs).length === 1 ? Object.keys(cs)[0] : null;
		$$('[data-style]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-style') === s1)); });
		$$('[data-color]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-color') === c1)); });
	}
	function markPart(p, on) { $$('[data-part]').forEach(function (b) { b.classList.toggle('is-now', on && +b.getAttribute('data-part') === p); }); }
	function head(st, len) {
		var h = $('[data-ll="head"]'), tm = $('[data-ll="time"]');
		if (st === null) { h.hidden = true; if (tm) { tm.textContent = '0:00 / ' + fmt(len); } return; }
		h.hidden = false; h.style.left = (Math.min(1, st / len) * 100) + '%';
		if (tm) { tm.textContent = fmt(st) + ' / ' + fmt(len); }
	}
	function full() {
		var e = STAGE;
		try { if (document.fullscreenElement) { document.exitFullscreen(); } else if (e && e.requestFullscreen) { e.requestFullscreen().catch(function () {}); } } catch (x) {}
		setTimeout(resize, 300);
	}
	var tT;
	function toast(m) { var t = $('[data-ll="toast"]'); if (!t) { return; } t.textContent = m; clearTimeout(tT); tT = setTimeout(function () { t.textContent = ''; }, 3200); }

	/* ---------------- watching someone's show (Show Wall) ---------------- */
	function watch(s, label, noAuto) {
		s = clean(s); if (!s) { return false; }
		stopAll(); watching = s; show = s; lastPart = -1;
		root.classList.add('is-watching');
		var w = $('[data-ll="watchname"]'); if (w) { w.textContent = label || 'a young light designer'; }
		var pk = $s('[data-ll="picked"]'); if (pk) { pk.hidden = true; }
		playing(false);
		if (!noAuto) { setTimeout(startAll, 150); }
		setTimeout(resize, 60);
		return true;
	}
	function unwatch() {
		stopAll(); watching = null; show = mine; lastPart = -1;
		root.classList.remove('is-watching'); playing(false); pick(sel.prop, false);
		setTimeout(resize, 60);
	}

	/* Still picture of a show for the Show Wall (JPEG data URL). */
	function snapshot(s) {
		s = clean(s) || mine;
		var c = document.createElement('canvas'); c.width = 1200; c.height = Math.round(1200 * BH / BW);
		var si = songInfo(s.song), t = 7.3;
		render(c.getContext('2d'), c.width, c.height, s, 0, t, (t - si.off) / si.spb, null, null, 0, 1, EDIT_DIM);
		return c.toDataURL('image/jpeg', 0.85);
	}

	window.RRLab = {
		ready: whenReady,
		get: function () { return clean(mine); },
		filled: filled,
		clean: clean,
		snapshot: snapshot,
		songName: function (id) { return SONGMAP[id] ? SONGMAP[id].n : ''; },
		watch: watch,
		unwatch: unwatch,
		playing: isPlaying,
		play: startAll,
		stop: stopAll,
		resize: resize
	};
})();
