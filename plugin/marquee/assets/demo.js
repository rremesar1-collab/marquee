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
		/* a little house of lights: roof, walls, a door, and a tree beside it */
		var dots = [], cx = W * 0.42, top = H - 92;
		function line(x1, y1, x2, y2) {
			var n = Math.max(1, Math.round(Math.hypot(x2 - x1, y2 - y1) / 7));
			for (var k = 0; k <= n; k++) { dots.push({ x: cx + x1 + (x2 - x1) * k / n, y: top + y1 + (y2 - y1) * k / n, r: Math.random() }); }
		}
		line(-98, 38, 0, 0); line(0, 0, 98, 38);            // roof
		line(-80, 38, -80, 86); line(80, 38, 80, 86);       // walls
		line(-80, 86, 80, 86);                              // ground
		line(-10, 86, -10, 60); line(-10, 60, 10, 60); line(10, 60, 10, 86);   // door
		[-52, 38].forEach(function (wx) { line(wx, 48, wx + 18, 48); line(wx, 66, wx + 18, 66); line(wx, 48, wx, 66); line(wx + 18, 48, wx + 18, 66); });
		for (var ty = 0; ty < 12; ty++) { for (var tx = -ty / 2.2; tx <= ty / 2.2; tx += 1) { dots.push({ x: W * 0.84 + tx * 7, y: top + 8 + ty * 6.5, r: Math.random() }); } }
		var snow = []; for (var s = 0; s < 40; s++) { snow.push({ x: Math.random() * W, y: Math.random() * H, v: .3 + Math.random() * .7 }); }
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
		/* the screen: the house lights in the theme's style, the name drawn on top */
		function drawScreen(now, show, alpha) {
			var l = look(theme), t = now / 1000;
			ctx.fillStyle = '#05070f'; ctx.fillRect(0, 0, W, H);
			if (l.style === 'snow') { ctx.fillStyle = 'rgba(255,255,255,.8)'; snow.forEach(function (f) { f.y += f.v; if (f.y > H) { f.y = 0; f.x = Math.random() * W; } ctx.fillRect(f.x, f.y, 2, 2); }); }
			dots.forEach(function (d, i) {
				var c, on = true;
				if (l.style === 'stripes') { c = (Math.floor((d.x + t * 60) / 16) % 2) ? l.a : l.b; }
				else if (l.style === 'rainbow') { c = 'hsl(' + ((d.x * 2 + t * 120) % 360) + ',95%,60%)'; }
				else if (l.style === 'wave') { c = Math.sin(d.x / 30 + t * 2) > 0 ? l.a : l.b; }
				else if (l.style === 'snow') { c = (d.r + t * .5) % 1 > .5 ? l.a : l.b; }
				else { c = (d.r + t * .7) % 1 > .3 ? l.a : l.b; on = (d.r * 7 + t) % 1 > .15; }
				if (!on) { return; }
				ctx.fillStyle = c; ctx.beginPath(); ctx.arc(d.x, d.y, 2.2, 0, 6.3); ctx.fill();
			});
			if (show) {
				ctx.globalAlpha = alpha; ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.shadowColor = l.a; ctx.shadowBlur = 14;
				var parts = text.split(', ');
				ctx.font = '700 22px system-ui, sans-serif'; ctx.fillText(parts[0] + (parts[1] ? ',' : ''), W / 2, 48);
				if (parts[1]) { ctx.font = '800 34px system-ui, sans-serif'; ctx.fillText(parts[1], W / 2, 90); }
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
