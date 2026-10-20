/* Marquee: "See it in action" preview on the Settings page. A phone sends a name (and picks a
   theme), then a matrix / TV shows the names break with the name going up and the spoken line. */
(function () {
	document.querySelectorAll('[data-mq-demo]').forEach(function (box) {
		var themes = []; try { themes = JSON.parse(box.getAttribute('data-themes') || '[]'); } catch (e) {}
		var mode = box.getAttribute('data-mode') || 'names', santa = box.getAttribute('data-santa') === '1';
		var kids = ['EMMA', 'JACOB', 'AVA', 'NOAH', 'MIA'];
		var looks = {
			'Classic': { a: '#ffd45c', b: '#ff6b5b', style: 'twinkle' },
			'Candy Cane': { a: '#ff2b3a', b: '#ffffff', style: 'stripes' },
			'Snow': { a: '#ffffff', b: '#8fd3ff', style: 'snow' },
			'Rainbow': { a: '#ff3b3b', b: '#3bff7a', style: 'rainbow' },
			'Gingerbread': { a: '#c8782f', b: '#fff0d0', style: 'twinkle' },
			'Northern': { a: '#38ffb8', b: '#7a5cff', style: 'wave' },
			'Santa': { a: '#ff2b3a', b: '#2bd46a', style: 'stripes' }
		};
		function look(name) {
			for (var k in looks) { if (name.toLowerCase().indexOf(k.toLowerCase()) >= 0) { return looks[k]; } }
			var h = 0; for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) % 360; }
			return { a: 'hsl(' + h + ',90%,60%)', b: 'hsl(' + ((h + 140) % 360) + ',90%,65%)', style: 'twinkle' };
		}
		var picks = mode === 'themes' && themes.length ? themes.slice(0, 3) : ['Classic'];
		var phone = box.querySelector('[data-demo-phone]'), scr = box.querySelector('canvas'), say = box.querySelector('[data-demo-say]'), step = box.querySelector('[data-demo-step]');
		var ctx = scr.getContext('2d'), W = scr.width, H = scr.height;
		/* the show's house from the Light Lab, with each prop lit in the theme's colors */
		var base = box.getAttribute('data-lab') || '', M = {"tree": {"x": 42, "y": 83, "w": 214, "h": 362}, "mini": {"x": 355, "y": 372, "w": 839, "h": 86}, "arch": {"x": 314, "y": 349, "w": 922, "h": 108}, "spin": {"x": 340, "y": 80, "w": 1106, "h": 150}, "flake": {"x": 265, "y": 167, "w": 1355, "h": 156}, "win": {"x": 306, "y": 234, "w": 815, "h": 102}, "faces": {"x": 936, "y": 166, "w": 107, "h": 61}, "face2": {"x": 1370, "y": 122, "w": 118, "h": 88}, "garage": {"x": 1276, "y": 265, "w": 300, "h": 130}, "gift": {"x": 6, "y": 354, "w": 816, "h": 56}, "roof": {"x": 227, "y": 30, "w": 1435, "h": 230}, "sign": {"x": 723, "y": 410, "w": 100, "h": 54}};
		var house = new Image(), props = [], ready = false, S = W / 1672;
		house.onload = function () { ready = true; };
		house.src = base + 'house.jpg';
		Object.keys(M).forEach(function (k, n) {
			var im = new Image(); im.src = base + 'm-' + k + '.png';
			props.push({ k: k, n: n, im: im, c: null, col: '' });
		});
		var snow = []; for (var s2 = 0; s2 < 40; s2++) { snow.push({ x: Math.random() * W, y: Math.random() * H, v: .3 + Math.random() * .7 }); }
		/* a prop's lights in one color (mask x color), cached per color */
		function tinted(p, col) {
			if (p.col === col && p.c) { return p.c; }
			if (!p.im.complete || !p.im.naturalWidth) { return null; }
			var c = p.c || document.createElement('canvas'); c.width = p.im.naturalWidth; c.height = p.im.naturalHeight;
			var x = c.getContext('2d'); x.globalCompositeOperation = 'source-over'; x.clearRect(0, 0, c.width, c.height);
			x.drawImage(p.im, 0, 0); x.globalCompositeOperation = 'multiply'; x.fillStyle = col; x.fillRect(0, 0, c.width, c.height);
			p.c = c; p.col = col; return c;
		}
		var t0 = performance.now(), scene = 0, name = kids[0], theme = picks[0], text = '';
		function greet(n) { return santa && scene % 2 ? 'HO HO HO, ' + n + '!' : 'MERRY CHRISTMAS, ' + n + '!'; }

		/* the phone: type a name, tap a theme, send */
		function drawPhone(p) {
			var typed = name.slice(0, Math.max(0, Math.min(name.length, Math.floor((p - .05) * 20))));
			var themeHtml = '';
			if (mode === 'themes' && themes.length) {
				themeHtml = '<div class="mq-demo__themes">' + ['Classic'].concat(themes.slice(0, 3)).map(function (n) {
					var l = look(n), on = p > .45 && n === theme;
					return '<span class="mq-demo__th' + (on ? ' is-on' : '') + '" style="--a:' + l.a + ';--b:' + l.b + '">' + n.replace(/</g, '') + '</span>';
				}).join('') + '</div>';
			}
			var sent = p > .75;
			phone.innerHTML = '<div class="mq-demo__app"><b>★ Your Name in Lights!</b>' +
				(sent ? '<div class="mq-demo__ok">✓ Sent!<small>Watch for your name after this song' + (mode === 'themes' ? ' in ' + theme.replace(/</g, '') : '') + '</small></div>' :
					'<span class="mq-demo__in">' + (typed || '<i>Your first name</i>') + (p < .45 ? '<em>|</em>' : '') + '</span>' + themeHtml +
					'<span class="mq-demo__btn' + (p > .65 ? ' is-press' : '') + '">Put My Name in Lights!</span>') + '</div>';
		}
		/* the screen: the house with its props lit in the theme's style; names on the garage-door matrix */
		function drawScreen(now, show, alpha) {
			var l = look(theme), t = now / 1000;
			ctx.globalCompositeOperation = 'source-over'; ctx.globalAlpha = 1;
			ctx.fillStyle = '#05070f'; ctx.fillRect(0, 0, W, H);
			if (!ready) { return; }
			ctx.globalAlpha = .55; ctx.drawImage(house, 0, 0, W, H); ctx.globalAlpha = 1;
			ctx.globalCompositeOperation = 'lighter';
			props.forEach(function (p) {
				if (p.k === 'garage' && show) { return; }            // the matrix shows the name instead
				var m = M[p.k], col, a = 1;
				if (l.style === 'stripes') { col = ((p.n + Math.floor(t * 2)) % 2) ? l.a : l.b; }
				else if (l.style === 'rainbow') { col = 'hsl(' + Math.round((p.n * 40 + t * 90) % 360) + ',100%,60%)'; }
				else if (l.style === 'wave') { col = Math.sin(m.x / 200 + t * 2) > 0 ? l.a : l.b; }
				else if (l.style === 'snow') { col = (p.n + Math.floor(t)) % 3 ? '#ffffff' : l.b; a = .7 + .3 * Math.sin(t * 3 + p.n); }
				else { col = (p.n % 2) ? l.a : l.b; a = .55 + .45 * Math.abs(Math.sin(t * 1.6 + p.n * 1.3)); }
				var c = tinted(p, col); if (!c) { return; }
				ctx.globalAlpha = a; ctx.drawImage(c, m.x * S, m.y * S, m.w * S, m.h * S);
				ctx.globalAlpha = a * .35; ctx.drawImage(c, m.x * S - 1, m.y * S - 1, m.w * S + 2, m.h * S + 2);
			});
			ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
			if (l.style === 'snow') { ctx.fillStyle = 'rgba(255,255,255,.75)'; snow.forEach(function (f) { f.y += f.v; if (f.y > H) { f.y = 0; f.x = Math.random() * W; } ctx.fillRect(f.x, f.y, 1.6, 1.6); }); }
			// the TV by the front door (on a little stand), and the garage-door matrix
			var tv = { x: 676 * S, y: 258 * S, w: 118 * S, h: 70 * S };
			ctx.fillStyle = '#111'; ctx.fillRect(tv.x - 3, tv.y - 3, tv.w + 6, tv.h + 6);
			ctx.fillStyle = '#2a2a2a'; ctx.fillRect(tv.x + tv.w / 2 - 2, tv.y + tv.h + 3, 4, 14 * S * 2); ctx.fillRect(tv.x + tv.w / 2 - 14, tv.y + tv.h + 3 + 28 * S, 28, 3);
			ctx.fillStyle = show ? '#000' : '#0b1430'; ctx.fillRect(tv.x, tv.y, tv.w, tv.h);
			if (show) {
				var g = M.garage;
				ctx.fillStyle = '#000'; ctx.fillRect(g.x * S, g.y * S, g.w * S, g.h * S);
				nameOn(g.x * S, g.y * S, g.w * S, g.h * S, alpha, l);
				nameOn(tv.x, tv.y, tv.w, tv.h, alpha, l);
			} else {
				ctx.fillStyle = 'rgba(255,212,92,.55)'; ctx.font = '700 ' + Math.round(tv.h * .22) + 'px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
				ctx.fillText('★ Names in Lights', tv.x + tv.w / 2, tv.y + tv.h / 2);
			}
		}
		function nameOn(x, y, w, h, alpha, l) {
			ctx.globalAlpha = alpha; ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.shadowColor = l.a; ctx.shadowBlur = 10;
			var parts = text.split(', ');
			ctx.font = '700 ' + Math.round(h * .2) + 'px system-ui, sans-serif'; ctx.fillText(parts[0] + (parts[1] ? ',' : ''), x + w / 2, y + h * .32);
			if (parts[1]) { ctx.font = '800 ' + Math.round(h * .34) + 'px system-ui, sans-serif'; ctx.fillText(parts[1], x + w / 2, y + h * .68); }
			ctx.shadowBlur = 0; ctx.globalAlpha = 1;
		}
		function frame(now) {
			var dt = (now - t0) / 1000, len = 9;
			if (dt > len) { t0 = now; scene++; name = kids[scene % kids.length]; theme = picks[scene % picks.length]; dt = 0; }
			text = greet(name);
			var phase;
			if (dt < 4) { phase = 1; drawPhone(dt / 4); drawScreen(now, false, 0); }
			else { phase = dt < 4.6 ? 2 : 3; drawPhone(1); var a = Math.min(1, (dt - 4.6) / .5); drawScreen(now, dt >= 4.6, Math.max(0, a)); }
			step.textContent = phase === 1 ? (mode === 'themes' ? '1. A kid types their name and picks a show' : '1. A kid types their name on your website') :
				phase === 2 ? '2. After the current song, the names break plays…' : (mode === 'themes' ? '3. …in the show they picked, with their name on your matrix or TV' : '3. …and their name goes up on your matrix or TV');
			say.hidden = !(dt > 4.9 && box.getAttribute('data-voice') === '1');
			say.textContent = '🔊 “' + (santa && scene % 2 ? 'Ho ho ho, ' : 'Merry Christmas, ') + name.charAt(0) + name.slice(1).toLowerCase() + '!”';
			if (box.isConnected) { requestAnimationFrame(frame); }
		}
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { drawPhone(1); text = greet(name); drawScreen(0, true, 1); step.textContent = 'A kid sends their name → it goes up on your matrix or TV between songs'; return; }
		requestAnimationFrame(frame);
	});
})();

/* Names break sound: a timeline of the break (voice on top, music under it), played back fast
   with a playhead, or in real time with the break you built. */
(function () {
	document.querySelectorAll('[data-mq-bdemo]').forEach(function (box) {
		var d = box.dataset, seq = parseFloat(d.seq) || 29.6, start = parseFloat(d.start) || 4, slot = parseFloat(d.slot) || 3.4;
		var intro = d.intro || '', outro = d.outro || '', santa = d.santa === '1';
		var kids = ['Emma', 'Jacob', 'Ava', 'Noah', 'Mia', 'Liam'];
		var modes = [
			{ label: box.getAttribute('data-l-full'), names: kids.slice(0, 6), slots: 6 },
			{ label: box.getAttribute('data-l-two'), names: ['Emma', 'Jacob'], slots: 4 },
			{ label: box.getAttribute('data-l-one'), names: ['Emma'], slots: 2 }
		];
		var track = box.querySelector('[data-bd-track]'), say = box.querySelector('[data-bd-say]'), head = box.querySelector('[data-bd-head]');
		var lab = box.querySelector('[data-bd-label]'), scr = box.querySelector('[data-bd-screen]'), tabs = box.querySelectorAll('[data-bd-mode]');
		var audioBtn = box.querySelector('[data-bd-listen]'), audio = null;
		var mode = 0, total = seq, events = [], t0 = performance.now(), speed = 2.4, live = false;
		function greet(n, k) { return santa && k % 3 === 2 ? 'Ho ho ho! Hi ' + n + ', Santa loves you too!' : (k % 2 ? 'Happy Holidays, ' + n + '!' : 'Merry Christmas, ' + n + '!'); }
		function build() {
			var m = modes[mode];
			var outroAt = start + m.slots * slot + 0.2;
			total = mode === 0 ? seq : outroAt + Math.max(3, outro.length / 14) + 0.5;
			events = [{ at: 0.15, len: Math.max(2.5, start - 0.5), kind: 'intro', text: intro }];
			for (var k = 0; k < m.slots; k++) {
				var n = m.names[k % m.names.length];
				events.push({ at: start + k * slot, len: Math.min(slot - 0.4, 1.8), kind: 'name', text: greet(n, k), name: n, scr: (k % 2 ? 'HAPPY HOLIDAYS, ' : 'MERRY CHRISTMAS, ') + n.toUpperCase() + '!' });
			}
			events.push({ at: outroAt, len: Math.min(total - outroAt - 0.2, 4.5), kind: 'outro', text: outro });
			var pct = function (s) { return (s / total * 100).toFixed(2) + '%'; };
			var h = '<div class="bd-row bd-row--voice"><span class="bd-rl">🎙 Voice</span><div class="bd-lane">';
			events.forEach(function (e, i) { h += '<span class="bd-blk bd-' + e.kind + '" data-ev="' + i + '" style="left:' + pct(e.at) + ';width:' + pct(e.len) + '">' + (e.kind === 'name' ? e.name : (e.kind === 'intro' ? 'Intro' : 'Outro')) + '</span>'; });
			h += '</div></div><div class="bd-row bd-row--music"><span class="bd-rl">♪ Music</span><div class="bd-lane">' +
				'<span class="bd-mus is-loud" style="left:0;width:' + pct(start) + '"></span>' +
				'<span class="bd-mus is-soft" style="left:' + pct(start) + ';width:' + pct(outroAt - start) + '"></span>' +
				'<span class="bd-mus is-loud" style="left:' + pct(outroAt) + ';width:' + pct(total - outroAt) + '"></span></div></div>' +
				'<div class="bd-row bd-row--time"><span class="bd-rl"></span><div class="bd-lane"><span style="left:0">0 s</span><span style="right:0">' + total.toFixed(1) + ' s</span></div></div>';
			track.innerHTML = h;
			lab.textContent = modes[mode].label;
			tabs.forEach(function (b, k) { b.classList.toggle('is-on', k === mode); });
		}
		function frame(now) {
			var t;
			if (live && audio) { t = audio.currentTime; if (audio.ended) { live = false; audioBtn.classList.remove('is-on'); } }
			else {
				t = (now - t0) / 1000 * speed;
				if (t > total + 1.5) { t0 = now; t = 0; mode = (mode + 1) % modes.length; build(); }
			}
			var lane = track.querySelector('.bd-lane');
			if (lane) {
				var bb = box.getBoundingClientRect(), lb = lane.getBoundingClientRect(), tb = track.getBoundingClientRect();
				head.style.left = (lb.left - bb.left + Math.min(1, t / total) * lb.width) + 'px';
				head.style.top = (tb.top - bb.top - 4) + 'px'; head.style.height = (tb.height + 4) + 'px';
			}
			var cur = null;
			events.forEach(function (e, i) {
				var on = t >= e.at && t < e.at + e.len, el = track.querySelector('[data-ev="' + i + '"]');
				if (el) { el.classList.toggle('is-on', on); el.classList.toggle('is-done', t >= e.at + e.len); }
				if (on) { cur = e; }
			});
			var screenEv = null; events.forEach(function (e) { if (e.kind === 'name' && t >= e.at && t < e.at + slot - 0.3) { screenEv = e; } });
			say.textContent = cur ? (cur.kind === 'name' ? '🔊 “' + cur.text + '”' : '🎙 “' + cur.text + '”') : (t < total ? '♪ …' : '');
			say.className = 'bd-say' + (cur ? ' is-' + cur.kind : '');
			scr.textContent = screenEv ? screenEv.scr : '';
			scr.classList.toggle('is-on', !!screenEv);
			if (box.isConnected) { requestAnimationFrame(frame); }
		}
		tabs.forEach(function (b, k) { b.addEventListener('click', function () { if (audio) { audio.pause(); live = false; audioBtn && audioBtn.classList.remove('is-on'); } mode = k; build(); t0 = performance.now(); }); });
		if (audioBtn) {
			audioBtn.addEventListener('click', function () {
				if (!audio) { audio = new Audio(audioBtn.getAttribute('data-src')); }
				if (live) { audio.pause(); live = false; audioBtn.classList.remove('is-on'); t0 = performance.now(); return; }
				mode = 0; build(); audio.currentTime = 0; audio.play(); live = true; audioBtn.classList.add('is-on');
			});
		}
		build();
		requestAnimationFrame(frame);
	});
})();
