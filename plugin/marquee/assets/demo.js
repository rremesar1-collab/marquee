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
			if (show) {
				var g = M.garage, gx = g.x * S, gy = g.y * S, gw = g.w * S, gh = g.h * S;
				ctx.fillStyle = '#000'; ctx.fillRect(gx, gy, gw, gh);
				ctx.globalAlpha = alpha; ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.shadowColor = l.a; ctx.shadowBlur = 10;
				var parts = text.split(', ');
				ctx.font = '700 ' + Math.round(gh * .2) + 'px system-ui, sans-serif'; ctx.fillText(parts[0] + (parts[1] ? ',' : ''), gx + gw / 2, gy + gh * .32);
				if (parts[1]) { ctx.font = '800 ' + Math.round(gh * .34) + 'px system-ui, sans-serif'; ctx.fillText(parts[1], gx + gw / 2, gy + gh * .68); }
				ctx.shadowBlur = 0; ctx.globalAlpha = 1;
			}
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
