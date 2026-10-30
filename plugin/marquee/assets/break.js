/* Marquee: names break builder (Settings → Names & FPP). Mixes the announcer lines and the
   music in the browser (Web Audio) and saves the WAV files: full, 2 names, 1 name, and the
   intro, music and closing for the break with no fixed timing. */
(function () {
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var C = window.MarqueeAdmin || {};
	var btn = document.querySelector('[data-mq-break-build]');
	if (!btn) { return; }
	var msg = document.querySelector('[data-mq-break-msg]'), prev = document.querySelector('[data-mq-break-prev]');
	var RATE = 44100;

	function say(t, ok) { msg.textContent = t; msg.className = 'mq__msg ' + (ok === false ? 'is-bad' : 'is-ok'); }
	function post(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', C.nonce);
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function load(url) {
		return fetch(url, { credentials: 'same-origin' }).then(function (r) {
			if (!r.ok) { throw new Error(tr('Couldn’t load %s').replace('%s', function () { return url.split('/').pop(); })); }
			return r.arrayBuffer();
		}).then(function (b) { return new OfflineAudioContext(2, RATE, RATE).decodeAudioData(b); });
	}
	/* loudness estimate: RMS of the louder 50 ms windows (above -40 dBFS), in dBFS */
	function level(buf) {
		var n = buf.length, ch = buf.numberOfChannels, win = Math.round(buf.sampleRate * 0.05), sum = 0, cnt = 0;
		for (var i = 0; i + win <= n; i += win) {
			var e = 0;
			for (var c = 0; c < ch; c++) { var d = buf.getChannelData(c); for (var j = i; j < i + win; j++) { e += d[j] * d[j]; } }
			e /= win * ch;
			if (e > 1e-4) { sum += e; cnt++; }
		}
		return cnt ? 10 * Math.log10(sum / cnt) : -60;
	}
	function db(x) { return Math.pow(10, x / 20); }

	function render(p, slots, total) {
		var ctx = new OfflineAudioContext(2, Math.ceil(total * RATE), RATE);
		var m = ctx.createBufferSource(); m.buffer = p.music; m.loop = p.music.duration < total;
		var g = ctx.createGain(); m.connect(g); g.connect(ctx.destination);
		var outroAt = p.start + slots * p.slot + 0.2, G = g.gain;
		G.setValueAtTime(0, 0);
		G.linearRampToValueAtTime(p.gTalk, 0.4);
		G.setValueAtTime(p.gTalk, Math.max(0.5, p.start - 0.45));
		G.linearRampToValueAtTime(p.gNames, p.start);
		G.setValueAtTime(p.gNames, outroAt - 0.3);
		G.linearRampToValueAtTime(p.gTalk, outroAt + 0.1);
		G.setValueAtTime(p.gTalk, total - 0.8);
		G.linearRampToValueAtTime(0, total);
		m.start(0);
		var i = ctx.createBufferSource(); i.buffer = p.intro; i.connect(ctx.destination); i.start(0.15);
		var o = ctx.createBufferSource(); o.buffer = p.outro; o.connect(ctx.destination); o.start(outroAt);
		return ctx.startRendering().then(wav);
	}
	/* the parts for a break with no fixed timing (the FPP plays them itself, around the names):
	   the intro (music comes in, the announcer's first line, music settles down for the names),
	   soft music for under the names, and the closing (the announcer's last line, music out) */
	function renderPart(p, part) {
		var len = part === 'intro' ? p.start : (part === 'bed' ? BED : 0.35 + p.outro.duration + 1.4);
		var ctx = new OfflineAudioContext(2, Math.ceil(len * RATE), RATE);
		var m = ctx.createBufferSource(); m.buffer = p.music; m.loop = true;
		var g = ctx.createGain(); m.connect(g); g.connect(ctx.destination);
		var G = g.gain, off = part === 'intro' ? 0 : (part === 'bed' ? p.start : (p.start + 20) % Math.max(1, p.music.duration - 1));
		if (part === 'intro') {
			G.setValueAtTime(0, 0); G.linearRampToValueAtTime(p.gTalk, 0.4);
			G.setValueAtTime(p.gTalk, Math.max(0.5, len - 0.45)); G.linearRampToValueAtTime(p.gNames, len);
			var i = ctx.createBufferSource(); i.buffer = p.intro; i.connect(ctx.destination); i.start(0.15);
		} else if (part === 'bed') {
			G.setValueAtTime(p.gNames, 0); G.setValueAtTime(p.gNames, len - 1.5); G.linearRampToValueAtTime(0, len);
		} else {
			G.setValueAtTime(p.gNames, 0); G.linearRampToValueAtTime(p.gTalk, 0.4);
			G.setValueAtTime(p.gTalk, len - 1.0); G.linearRampToValueAtTime(0, len);
			var o = ctx.createBufferSource(); o.buffer = p.outro; o.connect(ctx.destination); o.start(0.35);
		}
		m.start(0, off % p.music.duration);
		return ctx.startRendering().then(function (b) { p[part + '_secs'] = Math.round(len * 100) / 100; return wav(b); });
	}
	var BED = 60;

	/* 16-bit stereo WAV, with a soft limit instead of clipping */
	function wav(buf) {
		var n = buf.length, L = buf.getChannelData(0), R = buf.getChannelData(1);
		var dv = new DataView(new ArrayBuffer(44 + n * 4));
		function w(o, s) { for (var k = 0; k < s.length; k++) { dv.setUint8(o + k, s.charCodeAt(k)); } }
		function lim(v) { var a = Math.abs(v); if (a > 0.85) { a = 0.85 + 0.12 * Math.tanh((a - 0.85) / 0.12); } return (v < 0 ? -a : a) * 32767 | 0; }
		w(0, 'RIFF'); dv.setUint32(4, 36 + n * 4, true); w(8, 'WAVE'); w(12, 'fmt '); dv.setUint32(16, 16, true);
		dv.setUint16(20, 1, true); dv.setUint16(22, 2, true); dv.setUint32(24, RATE, true); dv.setUint32(28, RATE * 4, true);
		dv.setUint16(32, 4, true); dv.setUint16(34, 16, true); w(36, 'data'); dv.setUint32(40, n * 4, true);
		for (var k = 0; k < n; k++) { dv.setInt16(44 + k * 4, lim(L[k]), true); dv.setInt16(46 + k * 4, lim(R[k]), true); }
		return new Blob([dv.buffer], { type: 'audio/wav' });
	}

	/* example lines and the live preview of what the voice will say */
	document.querySelectorAll('[data-mq-idea]').forEach(function (b) {
		b.addEventListener('click', function () { var f = document.getElementById(b.getAttribute('data-mq-idea')); f.value = b.textContent; f.dispatchEvent(new Event('input')); });
	});
	[['mq-bintro', '[data-mq-tl-intro]'], ['mq-boutro', '[data-mq-tl-outro]']].forEach(function (x) {
		var f = document.getElementById(x[0]), t = document.querySelector(x[1]);
		if (f && t) { f.addEventListener('input', function () { t.textContent = '“' + (f.value.trim() || f.placeholder) + '”'; }); }
	});

	btn.addEventListener('click', function () {
		var seq = parseFloat((document.getElementById('mq-bseq') || {}).value) || 29.6;
		var musicIn = document.querySelector('input[name="break_music"]');
		btn.disabled = true; prev.hidden = true; prev.innerHTML = '';
		say(tr('Making the intro and outro with your voice…'));
		var lines, p = {};
		post('marquee_break_lines', {
			intro: document.getElementById('mq-bintro').value, outro: document.getElementById('mq-boutro').value,
			music: musicIn ? musicIn.value : ''
		}).then(function (j) {
			if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || tr('Couldn’t make the lines.')); }
			lines = j.data; say(tr('Mixing…'));
			return Promise.all([load(lines.intro.url), load(lines.outro.url), load(lines.music)]);
		}).then(function (b) {
			p.intro = b[0]; p.outro = b[1]; p.music = b[2];
			// music: about -20 LUFS under the names, -17 under the voice (speech is made at about -14)
			var est = level(p.music) + 1;
			p.gNames = db(-20 - est); p.gTalk = db(-17 - est);
			p.start = Math.max(2.5, Math.round((0.15 + p.intro.duration + 0.3) * 10) / 10);
			var room = seq - p.start - (p.outro.duration + 0.4) - 0.2;
			p.slot = Math.min(4.5, Math.floor(room / 6 * 20) / 20);
			if (p.slot < 2.6) {
				throw new Error(tr('The intro and outro are too long for a %s second sequence (each name needs about 3 seconds). Shorten the lines and build again.').replace('%s', seq));
			}
			say(tr('Mixing the full break…'));
			return render(p, 6, seq);
		}).then(function (full) {
			p.full = full; say(tr('Mixing the short breaks…'));
			var s2 = p.start + 4 * p.slot + 0.2 + p.outro.duration + 0.5, s1 = p.start + 2 * p.slot + 0.2 + p.outro.duration + 0.5;
			return Promise.all([render(p, 4, s2), render(p, 2, s1)]);
		}).then(function (sh) {
			p.short2 = sh[0]; p.short1 = sh[1];
			return Promise.all([renderPart(p, 'intro'), renderPart(p, 'bed'), renderPart(p, 'outro')]);
		}).then(function (parts) {
			p.intro_w = parts[0]; p.bed = parts[1]; p.outro_w = parts[2];
			// the parts go first: the break is put together once the three full mixes are in
			var v = String(Math.floor(Date.now() / 1000)), kinds = ['intro_w', 'bed', 'outro_w', 'full', 'short2', 'short1'], i = 0;
			say(tr('Saving…'));
			function next() {
				if (i >= kinds.length) { return null; }
				var k = kinds[i++], blob = p[k], size = 1000000, parts = Math.ceil(blob.size / size), part = 0;
				function send() {
					if (part >= parts) { return next(); }
					var kind = k.replace('_w', '');
					var fd = { kind: kind, v: v, part: part, parts: parts, audio: new File([blob.slice(part * size, (part + 1) * size)], kind + '.wav', { type: 'application/octet-stream' }) };
					if (k === 'full') { fd.start = p.start; fd.slot = p.slot; fd.secs = seq; fd.seq = seq; }
					if (k !== 'full' && k !== 'short1' && k !== 'short2') { fd.part_secs = p[kind + '_secs']; }
					say(tr('Saving…') + ' ' + Math.round(((i - 1) * parts + part + 1) / (kinds.length * parts) * 100) + '%');
					part++;
					return post('marquee_break_save', fd).then(function (j) {
						if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || tr('Couldn’t save the break audio.')); }
						return send();
					});
				}
				return send();
			}
			return next();
		}).then(function () {
			btn.disabled = false;
			say(tr('Done! Names start at %1$s s, one every %2$s s. Your FPP picks it up within a minute.').replace('%1$s', p.start).replace('%2$s', p.slot));
			var nst = document.getElementById('mq-nst'), nsl = document.getElementById('mq-nsl');
			if (nst) { nst.value = p.start; } if (nsl) { nsl.value = p.slot; }
			var note = document.createElement('p'); note.className = 'mq__fine';
			note.textContent = tr('Why do they sound alike? The names aren’t in these files: your FPP says each name live, over the soft music, the moment it goes up on your screens. The three only differ in how long the soft music lasts before the outro. Your FPP picks the right one by itself, depending on how many names are waiting.');
			prev.appendChild(note);
			function secs(blob) { return Math.round((blob.size - 44) / (RATE * 4) * 10) / 10; }
			[[tr('Full break'), p.full, tr('3 to 6 names waiting: room for 6 names')], [tr('2 names'), p.short2, tr('2 names waiting: each said twice')], [tr('1 name'), p.short1, tr('1 name waiting: said twice')]].forEach(function (x) {
				var d = document.createElement('p'), b = document.createElement('b'), sm = document.createElement('small'), a = document.createElement('audio');
				b.textContent = x[0] + ' · ' + secs(x[1]) + ' s'; sm.textContent = ' ' + x[2]; a.controls = true; a.src = URL.createObjectURL(x[1]);
				d.appendChild(b); d.appendChild(sm); d.appendChild(document.createElement('br')); d.appendChild(a); prev.appendChild(d);
			});
			prev.hidden = false;
		}).catch(function (e) {
			btn.disabled = false; say(e.message || String(e), false);
		});
	});
})();
