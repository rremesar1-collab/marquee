/* Live refresh + QR code */
(function () {
	'use strict';
	var cfg = window.RidgeRoad || {};
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var $ = function (sel) { return document.querySelector(sel); };
	var set = function (key, text) {
		var el = $('[data-rr="' + key + '"]');
		if (el && text !== undefined && text !== null && el.textContent !== String(text)) {
			el.textContent = text;
		}
		return el;
	};

	/* Stats: totals only (what was used, never who). See Show Control in WP Admin. */
	function statSend(ev, v) {
		if (!cfg.ajax) { return; }
		var fd = new FormData(); fd.append('action', 'rr_stat'); fd.append('ev', ev);
		if (v !== undefined) { fd.append('v', String(v)); }
		try { if (navigator.sendBeacon && navigator.sendBeacon(cfg.ajax, fd)) { return; } } catch (e) {}
		try { fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }); } catch (e) {}
	}
	window.RRStat = statSend;
	function presence() {
		var kids = !!document.querySelector('[data-rr-kc]');
		try { if (!sessionStorage.getItem('rrVisit')) { sessionStorage.setItem('rrVisit', '1'); statSend('visit'); } } catch (e) {}
		if (kids) { statSend('kids'); }
		var ping = function () { if (!document.hidden) { statSend('ping', kids ? 'kids' : 'site'); } };
		ping(); setInterval(ping, 60000);
	}

	/* QR codes made from a link: Cash App (when no image was uploaded) and Listen on Your Phone */
	function makeQR() {
		if (typeof window.qrcode !== 'function') { return; }
		Array.prototype.forEach.call(document.querySelectorAll('.rr-qr__gen'), function (box) {
			if (box.firstChild) { return; }
			var qr = window.qrcode(0, 'H');
			qr.addData(box.getAttribute('data-qr'));
			qr.make();
			box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
		});
	}

	/* Live data */
	var idle = {
		running: tr('Between songs — stay tuned!'),
		canceled: tr('No show tonight')
	};
	/* Traffic & parking alert from Show Control: a strip at the top of every page while it's on. */
	function advisory(a) {
		var bar = document.querySelector('[data-rr-advisory]');
		if (!a) { if (bar) { bar.remove(); } return; }
		if (!bar) {
			bar = document.createElement('div'); bar.setAttribute('data-rr-advisory', ''); bar.setAttribute('role', 'status');
			bar.style.cssText = 'position:sticky;top:0;z-index:9999;display:flex;gap:10px;align-items:center;justify-content:center;padding:10px 16px;background:#ffcc33;color:#1d1200;font:600 15px/1.35 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.25)';
			document.body.insertBefore(bar, document.body.firstChild);
		}
		var key = a.kind + '|' + a.title + '|' + a.text;
		if (bar.getAttribute('data-k') === key) { return; }
		bar.setAttribute('data-k', key); bar.innerHTML = '';
		var i = document.createElement('span'); i.setAttribute('aria-hidden', 'true'); i.style.fontSize = '20px'; i.textContent = a.icon || '📣';
		var t = document.createElement('span'); var b = document.createElement('b'); b.textContent = a.title; t.appendChild(b);
		if (a.text) { t.appendChild(document.createTextNode(' ' + a.text)); }
		bar.appendChild(i); bar.appendChild(t);
	}
	function apply(d) {
		if (!d || !d.status) { return; }
		advisory(d.advisory || null);
		var s = d.status, np = d.nowPlaying || {};
		var badge = $('[data-rr="badge"]');
		if (badge) {
			badge.className = 'rr-badge rr-badge--' + s.state;
			var t = badge.querySelector('span');
			if (t) { t.textContent = s.badge; }
		}
		set('note', s.note);
		if (d.banner) {
			var bn = $('[data-rr-banner]');
			if (bn && bn.getAttribute('data-mod') !== d.banner.mod) {
				bn.classList.remove('rr-banner--ok', 'rr-banner--off', 'rr-banner--canceled');
				bn.classList.add('rr-banner--' + d.banner.mod);
				bn.setAttribute('data-mod', d.banner.mod);
			}
			if (bn && bn.hasAttribute('data-ok-hide')) bn.hidden = d.banner.mod === 'ok'; // only when canceled or off season
			set('banner-title', d.banner.title);
			set('banner-text', d.banner.text);
		}
		playlist(np, s.state);
		var pick = $('[data-rr-rfpick]');
		if (pick && !pick.hasAttribute('data-preview')) { pick.hidden = s.state !== 'running'; }
		voteNow(np.title || '');
		if (d.votes) { voteData(d.votes); }
		if (cd && s.start_ts) {
			cd.setAttribute('data-start', s.start_ts); cd.setAttribute('data-end', s.end_ts);
			cd.setAttribute('data-opening', s.opening_ts || 0);
		}
		var title = np.title || idle[s.state] || s.note;
		set('np-title', title);
		var box = $('[data-rr="np"]');
		if (box) { box.classList.toggle('is-playing', !!np.title); }
		var next = $('[data-rr="np-next"]');
		if (next) {
			next.hidden = !np.next;
			var ns = next.querySelector('span');
			if (ns) { ns.textContent = np.next || ''; }
		}
		if (d.weather && cfg.snowReal) {
			var c = +d.weather.code;
			snowBoost = ((c >= 71 && c <= 77) || c === 85 || c === 86) ? 2.4 : 1;
		}
		if (d.weather) {
			set('temp', d.weather.temp);
			set('label', d.weather.label);
			if (d.weather.feels !== null) { set('feels', d.weather.feels + '°'); }
			set('humidity', d.weather.humidity + '%');
			set('wind', d.weather.wind + ' ' + (cfg.windUnit || 'mph'));
			if (d.weather.precip !== null) { set('precip', d.weather.precip + '%'); }
		}
	}

	var timer = null;
	function bust(u) { return u + (u.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(); }
	function get(u) {
		return fetch(bust(u), { credentials: 'omit', cache: 'no-store' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); });
	}
	function poll() {
		if (!cfg.endpoint || (!$('[data-rr="badge"]') && !$('[data-rr-vote]'))) { return; }
		get(cfg.endpoint)
			.catch(function () { return cfg.fallback ? get(cfg.fallback) : null; })
			.then(apply)
			.catch(function () {});
	}
	function start() {
		stop();
		poll();
		timer = setInterval(poll, Math.max(5, cfg.interval || 5) * 1000);
	}
	function stop() { if (timer) { clearInterval(timer); timer = null; } }

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { stop(); } else { start(); }
	});

	/* Highlight the menu item for the section in view */
	function spy() {
		var links = document.querySelectorAll('.rr-menu a[href*="#"]');
		if (!links.length || !('IntersectionObserver' in window)) { return; }
		var map = {};
		links.forEach(function (a) {
			var id = a.getAttribute('href').split('#')[1];
			var el = id && document.getElementById(id);
			if (el) { map[id] = a; }
		});
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting && map[e.target.id]) {
					links.forEach(function (l) { l.classList.remove('is-active'); });
					map[e.target.id].classList.add('is-active');
				}
			});
		}, { rootMargin: '-45% 0px -50% 0px' });
		Object.keys(map).forEach(function (id) { io.observe(document.getElementById(id)); });
	}

	/* Desktop: one fixed scene picture; only the boxes scroll (inside .rr-scroll) */
	var scroller = null;
	function fixHead() {
		var head = $('.rr-header');
		scroller = $('.rr-scroll');
		if (!head || !scroller) { return; }
		var on = window.innerWidth >= 861 && window.innerHeight >= 700;
		document.body.classList.toggle('rr-scene', on);
		if (on) {
			document.documentElement.style.setProperty('--rr-head-h', head.offsetHeight + 'px');
		}
	}
	/* Mouse wheel over the logo/menu still scrolls the boxes */
	function forwardWheel() {
		var head = $('.rr-header');
		if (!head) { return; }
		head.addEventListener('wheel', function (e) {
			if (document.body.classList.contains('rr-scene') && scroller) {
				scroller.scrollBy({ top: e.deltaY, left: 0 });
				e.preventDefault();
			}
		}, { passive: false });
	}

	/* "Share the Show": phone share sheet, or copy the link */
	function share() {
		var btn = $('[data-rr-share]');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var url = btn.getAttribute('data-url'), title = btn.getAttribute('data-title');
			var label = btn.querySelector('span'), orig = label ? label.textContent : '';
			var done = function (msg) { if (label) { label.textContent = msg; setTimeout(function () { label.textContent = orig; }, 2200); } };
			if (navigator.share) {
				navigator.share({ title: title, text: tr('Come see the Christmas lights!'), url: url }).catch(function () {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(function () { done(tr('Link copied!')); }, function () { done(url); });
			} else {
				done(url);
			}
		});
	}

	/* Live camera: load YouTube only when tapped (keeps the page fast) */
	function live() {
		var box = $('[data-rr-live]');
		if (!box) { return; }
		var btn = box.querySelector('.rr-live__poster');
		btn.addEventListener('click', function () {
			var f = document.createElement('iframe');
			f.src = box.getAttribute('data-rr-live');
			f.title = tr('Live camera');
			f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
			f.allowFullscreen = true;
			box.innerHTML = '';
			box.appendChild(f);
		});
	}

	/* Countdown clocks (server time offset so visitors' wrong clocks don't matter) */
	var cd = null, skew = 0;
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function countdown() {
		cd = $('[data-rr-count]');
		if (!cd) { return; }
		skew = parseInt(cd.getAttribute('data-now'), 10) * 1000 - Date.now();
		tick();
		setInterval(tick, 1000);
	}
	function tick() {
		if (!cd) { return; }
		var now = (Date.now() + skew) / 1000;
		var opening = +cd.getAttribute('data-opening'), start = +cd.getAttribute('data-start'), end = +cd.getAttribute('data-end');
		var label = $('[data-rr="count-label"]'), tiles = $('[data-rr="count-tiles"]'), target = 0, text = '';
		if (opening && now < opening) { target = opening; text = tr('Opening Night In'); }
		else if (now < start) { target = start; text = tr('Lights On In'); }
		else if (now < end) {
			var rEl = document.querySelector('.rr-radio'), radio = rEl ? rEl.textContent.trim() : '';   // the show's own station, from Tonight's Show
			text = radio ? tr('The Show Is On Right Now — Tune to %s!').replace('%s', function () { return radio; }) : tr('The Show Is On Right Now!');
		}
		else { target = start; text = tr('Next Show In'); }
		if (label && label.textContent !== text) { label.textContent = text; }
		cd.classList.toggle('is-live', !target);
		if (target) {
			var t = Math.max(0, Math.floor(target - now));
			var u = { d: Math.floor(t / 86400), h: Math.floor(t % 86400 / 3600), m: Math.floor(t % 3600 / 60), s: t % 60 };
			if (tiles) { Object.keys(u).forEach(function (k) { var b = tiles.querySelector('[data-u="' + k + '"]'); if (b) { b.textContent = k === 'd' ? u[k] : pad(u[k]); } }); }
		}
		var x = +cd.getAttribute('data-xmas'), dx = Math.ceil((x - now) / 86400), xe = $('[data-rr="xmas"]');
		if (xe) {
			var many = cd.getAttribute('data-hol-many') || tr('%d days until Christmas');
			xe.textContent = dx > 1 ? many.replace('%d', dx) : (dx === 1 ? (cd.getAttribute('data-hol-one') || tr('Christmas Eve is here!')) : (cd.getAttribute('data-hol-day') || tr('Merry Christmas!')));
		}
	}

	/* Playlist: highlight the song playing now, refresh "recently played" */
	function playlist(np, state) {
		var sec = $('[data-rr-pl-when]');
		if (sec && state) {
			var w = sec.getAttribute('data-rr-pl-when');
			var show = w === 'always' || (w === 'season' ? (state !== 'offseason' && state !== 'canceled') : state === 'running');
			sec.hidden = !show;
		}
		var items = document.querySelectorAll('[data-rr="playlist"] [data-song]');
		items.forEach(function (li) { li.classList.toggle('is-now', !!np.title && li.getAttribute('data-song') === np.title); });
		var rec = $('[data-rr="recent"]'), rl = $('[data-rr="recent-list"]');
		if (rec && rl && np.recent) {
			rec.hidden = !np.recent.length;
			var txt = np.recent.join(' · ');
			if (rl.textContent !== txt) { rl.textContent = txt; }
		}
	}

	/* Stats: count up when scrolled into view */
	function stats() {
		var nums = document.querySelectorAll('.rr-stat__num[data-count]');
		if (!nums.length || !('IntersectionObserver' in window)) { return; }
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) {
				if (!e.isIntersecting) { return; }
				io.unobserve(e.target);
				var el = e.target, raw = el.getAttribute('data-count'), m = raw.match(/^([\d,]*\.?\d+)(.*)$/);
				if (!m) { return; }
				var target = parseFloat(m[1].replace(/,/g, '')), dec = (m[1].split('.')[1] || '').length, comma = m[1].indexOf(',') > -1, suf = m[2], t0 = null;
				var fmt = function (v) { var s = v.toFixed(dec); return comma ? Number(s).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : s; };
				(function step(ts) {
					if (!t0) { t0 = ts; }
					var k = Math.min(1, (ts - t0) / 1600), ease = 1 - Math.pow(1 - k, 3);
					el.textContent = fmt(target * ease) + suf;
					if (k < 1) { requestAnimationFrame(step); } else { el.textContent = raw; }
				})(performance.now());
			});
		}, { root: document.body.classList.contains('rr-scene') ? $('.rr-scroll') : null, threshold: .4 });
		nums.forEach(function (n) { io.observe(n); });
	}

	/* Kids: elf name generator (nothing saved or sent) */
	function elf() {
		var f = $('[data-rr-elf]');
		if (!f) { return; }
		var A = ['Twinkle', 'Jingles', 'Sparkle', 'Snowball', 'Cocoa', 'Peppermint', 'Tinsel', 'Sugarplum', 'Buttons', 'Candy Cane', 'Frosty', 'Glitter', 'Pudding', 'Snickerdoodle', 'Mittens', 'Jolly'];
		var B = ['McSnowflake', 'Sparkletoes', 'Gumdrop', 'Jinglebottom', 'Merrypants', 'Cookiecrumb', 'Twinklebell', 'Hollyberry', 'Sugarsocks', 'Tinseltoes', 'Mistletoe', 'Starshine', 'Fruitcake', 'Snowboots', 'Candlewick', 'Figgypudding'];
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var name = f.querySelector('input').value.trim().toLowerCase().replace(/[^a-z]/g, '');
			var out = $('[data-rr="elf"]');
			if (!name) { out.hidden = false; out.textContent = tr('Type your name first!'); return; }
			var h = 0; for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) >>> 0; }
			out.hidden = false;
			out.innerHTML = '';
			var s1 = document.createElement('span'); s1.textContent = tr('Your elf name is');
			var s2 = document.createElement('strong'); s2.textContent = A[h % A.length] + ' ' + B[Math.floor(h / A.length) % B.length] + '!';
			out.appendChild(s1); out.appendChild(s2);
			out.classList.remove('pop'); void out.offsetWidth; out.classList.add('pop');
			statSend('elf');
		});
	}

	/* Kids: 5-question Christmas trivia */
	function quiz() {
		var box = $('[data-rr-quiz]');
		if (!box) { return; }
		var all = JSON.parse(box.getAttribute('data-rr-quiz')), qs, n, score;
		var qEl = $('[data-rr="quiz-q"]'), oEl = $('[data-rr="quiz-opts"]'), mEl = $('[data-rr="quiz-msg"]'), pEl = $('[data-rr="quiz-progress"]');
		function shuffle(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
		function start() { qs = shuffle(all).slice(0, 5); n = 0; score = 0; show(); }
		function show() {
			mEl.textContent = ''; oEl.innerHTML = '';
			if (n >= qs.length) {
				statSend('trivia', score);
				pEl.textContent = tr('All done!');
				qEl.textContent = score === 5 ? tr('Perfect! You got 5 out of 5 — you’re on the Nice List!') : tr('You got %s out of 5! Great job!').replace('%s', score);
				var again = document.createElement('button'); again.className = 'rr-btn rr-btn--gold'; again.type = 'button'; again.textContent = tr('Play Again');
				again.addEventListener('click', start); oEl.appendChild(again); return;
			}
			var q = qs[n];
			pEl.textContent = tr('Question %s of 5').replace('%s', n + 1);
			qEl.textContent = q[0];
			q[1].forEach(function (opt, i) {
				var b = document.createElement('button'); b.type = 'button'; b.className = 'rr-quiz__opt'; b.textContent = opt;
				b.addEventListener('click', function () {
					var btns = oEl.querySelectorAll('button');
					btns.forEach(function (x) { x.disabled = true; });
					btns[q[2]].classList.add('is-right');
					if (i === q[2]) { score++; mEl.textContent = tr('That’s right!'); } else { b.classList.add('is-wrong'); mEl.textContent = tr('Nice try! It’s “%s”.').replace('%s', function () { return q[1][q[2]]; }); }
					setTimeout(function () { n++; show(); }, 1500);
				});
				oEl.appendChild(b);
			});
		}
		start();
	}

	/* Guestbook: show more messages */
	function gbMore() {
		var b = $('[data-rr-gbl-more]');
		if (!b) { return; }
		b.addEventListener('click', function () {
			var hid = document.querySelectorAll('[data-rr-gbl] li[hidden]');
			for (var i = 0; i < hid.length && i < 5; i++) { hid[i].hidden = false; }
			if (hid.length <= 5) { b.hidden = true; }
		});
	}

	/* Falling snow (canvas): near flakes are six-pointed crystals, far ones soft specks.
	   Pauses in background tabs; off for reduced-motion users. */
	var snowBoost = 1;
/* Snowflake sprites: six-armed crystals in a few designs, drawn once, then stamped. */
	function rrFlakeSprites(dpr) {
	var designs = [
		function (g, R) { arm(g, R, [[0.55, 0.32], [0.8, 0.22]]); },          // fern branches
		function (g, R) { arm(g, R, [[0.4, 0.28], [0.65, 0.3], [0.85, 0.16]]); }, // dense
		function (g, R) { arm(g, R, [[0.62, 0.38]]); tip(g, R); },            // simple + tips
		function (g, R) { arm(g, R, [[0.5, 0.25]]); hex(g, R * 0.32); }       // plate centre
	];
	function arm(g, R, br) {
		for (var k = 0; k < 6; k++) {
			g.save(); g.rotate(k * Math.PI / 3);
			g.beginPath(); g.moveTo(0, 0); g.lineTo(0, -R);
			br.forEach(function (b) { var y = -R * b[0], l = R * b[1]; g.moveTo(0, y); g.lineTo(-l * 0.85, y - l * 0.6); g.moveTo(0, y); g.lineTo(l * 0.85, y - l * 0.6); });
			g.stroke(); g.restore();
		}
	}
	function tip(g, R) { for (var k = 0; k < 6; k++) { var a = k * Math.PI / 3 - Math.PI / 2; g.beginPath(); g.arc(Math.cos(a) * R, Math.sin(a) * R, R * 0.09, 0, 6.283); g.fill(); } }
	function hex(g, r) { g.beginPath(); for (var k = 0; k < 6; k++) { var a = k * Math.PI / 3; g[k ? 'lineTo' : 'moveTo'](Math.cos(a) * r, Math.sin(a) * r); } g.closePath(); g.stroke(); }
	var out = [];
	designs.forEach(function (draw) {
		var R = 24, pad = 8, S = (R + pad) * 2, c = document.createElement('canvas');
		c.width = c.height = S * dpr; var g = c.getContext('2d');
		g.scale(dpr, dpr); g.translate(S / 2, S / 2);
		g.strokeStyle = '#fff'; g.fillStyle = '#fff'; g.lineCap = 'round'; g.lineJoin = 'round';
		g.shadowColor = 'rgba(200,225,255,.9)'; g.shadowBlur = 5;
		g.lineWidth = 2.6; draw(g, R);
		g.shadowBlur = 0; g.beginPath(); g.arc(0, 0, 2.6, 0, 6.283); g.fill();
		out.push({ img: c, half: S / 2, R: R });
	});
	return out;
}

	function snow() {
		var cv = $('.rr-snow');
		var level = { off: 0, light: 0.45, medium: 1, heavy: 2.2 }[cfg.snow || 'medium'];
		var big = { small: 0.7, medium: 1, large: 1.45 }[cfg.snowSize || 'medium'] || 1;
		if (!cv || !level || !cv.getContext) { if (cv) { cv.remove(); } return; }
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { cv.remove(); return; }
		var ctx = cv.getContext('2d'), W = 0, H = 0, dpr = Math.min(2, window.devicePixelRatio || 1), flakes = [], wind = 0, t = 0, running = true;
		var sprites = rrFlakeSprites(dpr);
		function size() { W = window.innerWidth; H = window.innerHeight; cv.width = W * dpr; cv.height = H * dpr; }
		function want() { return Math.round(Math.min(260, W * H / 9000) * level * snowBoost); }
		function make(top) {
			var z = Math.random(), f = { x: Math.random() * W, y: top ? -14 : Math.random() * H, s: 0.35 + z * 1.1, d: Math.random() * 6.28, a: 0.35 + z * 0.6 };
			if (z > 0.42) {
				f.k = sprites[(Math.random() * sprites.length) | 0];
				f.size = (3.2 + (z - 0.42) / 0.58 * 8.5) * big;
				f.rot = Math.random() * 6.28; f.spin = (Math.random() - 0.5) * 0.02; f.a = 0.55 + z * 0.4;
			} else { f.r = (0.7 + z * 1.6) * Math.sqrt(big); }
			return f;
		}
		function frame() {
			if (!running) { return; }
			t += 0.01; wind = Math.sin(t * 0.7) * 0.6;
			var n = want(), first = !flakes.length;
			while (flakes.length < n) { flakes.push(make(!first)); }
			if (flakes.length > n) { flakes.length = n; }
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, W, H); ctx.fillStyle = '#fff';
			for (var i = 0; i < flakes.length; i++) {
				var f = flakes[i];
				f.d += 0.012; f.y += f.s; f.x += wind * f.s + Math.sin(f.d) * 0.35;
				if (f.y > H + 14) { flakes[i] = make(true); continue; }
				if (f.x > W + 14) { f.x = -14; } else if (f.x < -14) { f.x = W + 14; }
				ctx.globalAlpha = f.a;
				if (f.k) {
					f.rot += f.spin;
					var sc = f.size / f.k.R, c = Math.cos(f.rot) * sc * dpr, sn = Math.sin(f.rot) * sc * dpr;
					ctx.setTransform(c, sn, -sn, c, f.x * dpr, f.y * dpr);
					ctx.drawImage(f.k.img, -f.k.half, -f.k.half, f.k.half * 2, f.k.half * 2);
					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				} else { ctx.beginPath(); ctx.arc(f.x, f.y, f.r, 0, 6.283); ctx.fill(); }
			}
			ctx.globalAlpha = 1;
			requestAnimationFrame(frame);
		}
		size(); window.addEventListener('resize', size);
		document.addEventListener('visibilitychange', function () { running = !document.hidden; if (running) { requestAnimationFrame(frame); } });
		requestAnimationFrame(frame);
	}

	/* ---------- Favorite song voting ---------- */
	var VOTE = { el: null, data: null, board: 'week', mine: '' };
	function vstore(k, v) {
		try { if (v === undefined) { return localStorage.getItem(k) || ''; } localStorage.setItem(k, v); } catch (e) {}
		return '';
	}
	function voterId() {
		var id = vstore('rrVoter');
		if (!id) {
			id = '';
			for (var i = 0; i < 24; i++) { id += 'abcdefghijklmnopqrstuvwxyz0123456789'[Math.floor(Math.random() * 36)]; }
			vstore('rrVoter', id);
		}
		return id;
	}
	function voteNow(title) {
		if (!VOTE.el) { return; }
		VOTE.el.querySelectorAll('.rr-vlist__item').forEach(function (li) { li.classList.toggle('is-now', !!title && li.getAttribute('data-title') === title); });
	}
	function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
	function voteData(v) {
		if (!VOTE.el || !v) { return; }
		VOTE.data = v;
		var counts = v.counts || {};
		VOTE.el.querySelectorAll('.rr-vlist__item').forEach(function (li) {
			var k = li.getAttribute('data-key');
			li.querySelector('.rr-vlist__vote span').textContent = counts[k] || 0;
			li.classList.toggle('is-mine', k === VOTE.mine);
		});
		var list = v[VOTE.board] || [], ol = VOTE.el.querySelector('[data-rr-lb]'), max = list.length ? list[0].votes : 1;
		ol.innerHTML = list.map(function (s, i) {
			return '<li' + (s.key === VOTE.mine ? ' class="is-mine"' : '') + '><span class="rr-lb__rank">' + (i + 1) + '</span><span class="rr-lb__main"><span class="rr-lb__name">' + esc(s.title) +
				'</span><span class="rr-lb__bar"><i style="width:' + Math.max(6, Math.round(s.votes / max * 100)) + '%"></i></span></span><span class="rr-lb__n">' + s.votes + '</span></li>';
		}).join('');
		VOTE.el.querySelector('[data-rr-lb-empty]').hidden = list.length > 0;
		var tot = VOTE.board === 'week' ? v.weekTotal : v.total;
		VOTE.el.querySelector('[data-rr-lb-total]').textContent = tot ? (VOTE.board === 'week' ? (tot === 1 ? tr('%s vote this week') : tr('%s votes this week')) : (tot === 1 ? tr('%s vote this season') : tr('%s votes this season'))).replace('%s', tot) : '';
		var mine = VOTE.el.querySelector('[data-rr-mine]'), li = VOTE.mine && VOTE.el.querySelector('.rr-vlist__item[data-key="' + VOTE.mine + '"]');
		mine.hidden = !li;
		if (li) { mine.innerHTML = tr('Your vote: <b>%s</b>. Tap another song to change it.').replace('%s', function () { return esc(li.getAttribute('data-title')); }); }
		var ff = v.friday, body = VOTE.el.querySelector('[data-rr-ff-body]');
		if (ff && body) {
			var t = VOTE.el.getAttribute('data-ff-text') || '', L = ff.leader;
			if (ff.today) {
				body.innerHTML = '<b>' + tr('It’s Fan Favorite Friday!') + '</b><span>' + (L ? tr('This week’s winner is <strong>%s</strong>.').replace('%s', function () { return esc(L.title); }) + ' ' + esc(t) : tr('No votes yet this week. Vote now to pick next Friday’s favorite!')) + '</span>';
			} else {
				body.innerHTML = '<b>' + (ff.days === 1 ? tr('Fan Favorite Friday is tomorrow!') : tr('Fan Favorite Friday is in %s days').replace('%s', ff.days)) + '</b><span>' +
					(L ? tr('Leading this week: <strong>%s</strong>.').replace('%s', function () { return esc(L.title); }) + ' ' : '') + tr('The song with the most votes this week gets featured on Friday!') + '</span>';
			}
		}
	}
	function votes() {
		VOTE.el = $('[data-rr-vote]');
		if (!VOTE.el) { return; }
		VOTE.mine = vstore('rrSongVote');
		try { voteData(JSON.parse(VOTE.el.querySelector('[data-rr-votes]').textContent)); } catch (e) {}
		VOTE.el.querySelectorAll('[data-board]').forEach(function (b) {
			b.addEventListener('click', function () {
				VOTE.board = b.getAttribute('data-board');
				VOTE.el.querySelectorAll('[data-board]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
				voteData(VOTE.data);
			});
		});
		var msg = VOTE.el.querySelector('[data-rr-vote-msg]'), ajax = RidgeRoad.endpoint.split('?')[0];
		VOTE.el.querySelector('.rr-vlist').addEventListener('click', function (e) {
			var btn = e.target.closest('.rr-vlist__vote');
			if (!btn) { return; }
			var li = btn.closest('.rr-vlist__item'), key = li.getAttribute('data-key');
			if (key === VOTE.mine) { msg.textContent = tr('That’s already your favorite!'); return; }
			btn.classList.add('is-pop'); msg.textContent = '';
			fetch(ajax + '?action=rr_song_nonce&_=' + Date.now(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				var fd = new FormData();
				fd.append('action', 'rr_song_vote'); fd.append('nonce', j.data.nonce); fd.append('song', key); fd.append('voter', voterId());
				return fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
			}).then(function (r) { return r.json(); }).then(function (j) {
				btn.classList.remove('is-pop');
				if (j && j.success) {
					VOTE.mine = j.data.mine; vstore('rrSongVote', VOTE.mine);
					msg.textContent = tr('Thanks for voting!');
					voteData(j.data.votes);
				} else { msg.textContent = (j && j.data && j.data.message) || tr('Something went wrong. Please try again.'); }
			}).catch(function () { btn.classList.remove('is-pop'); msg.textContent = tr('We couldn’t reach the website. Please try again.'); });
		});
	}

	/* ---------- Sequence videos: pop-up player ---------- */
	function seqVideos() {
		var dlg = $('[data-rr-vmodal]');
		if (!dlg) { return; }
		var player = dlg.querySelector('[data-rr-vplayer]'), ttl = dlg.querySelector('[data-rr-vtitle]'), yt = dlg.querySelector('[data-rr-vyt]');
		function stopYt() { if (yt) { yt.innerHTML = ''; yt.hidden = true; } }
		function close() { player.pause(); player.removeAttribute('src'); player.load(); stopYt(); if (dlg.open) { dlg.close(); } }
		document.querySelectorAll('.rr-seq__card').forEach(function (c) {
			c.addEventListener('click', function () {
				var id = c.getAttribute('data-yt');
				ttl.textContent = c.getAttribute('data-title');
				if (id && yt) { /* YouTube link: YouTube's own player in the pop-up */
					player.hidden = true;
					var f = document.createElement('iframe');
					f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0&playsinline=1';
					f.title = c.getAttribute('data-title') || 'YouTube';
					f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
					f.allowFullscreen = true;
					yt.innerHTML = ''; yt.appendChild(f); yt.hidden = false;
					if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
					return;
				}
				stopYt(); player.hidden = false;
				player.src = c.getAttribute('data-video');
				if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
				var p = player.play(); if (p && p.catch) { p.catch(function () {}); }
			});
		});
		dlg.querySelector('[data-rr-vclose]').addEventListener('click', close);
		dlg.addEventListener('click', function (e) { if (e.target === dlg) { close(); } });
		dlg.addEventListener('cancel', function () { player.pause(); stopYt(); });
		dlg.addEventListener('close', function () { player.pause(); stopYt(); });

		/* card previews (videos without a cover picture) load only when they come near the screen */
		var lazy = [].slice.call(document.querySelectorAll('.rr-seq__media video[data-src]'));
		function load(v) { if (v.dataset.src) { v.preload = 'metadata'; v.src = v.dataset.src; delete v.dataset.src; } }
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting && e.target.offsetParent !== null) { load(e.target); io.unobserve(e.target); } }); }, { rootMargin: '300px 0px' });
			lazy.forEach(function (v) { io.observe(v); });
		} else { lazy.forEach(load); }

		/* phones: first 6 videos, then "Show all" */
		var more = document.querySelector('[data-rr-seq-more]');
		if (more) {
			more.addEventListener('click', function () {
				var sec = document.getElementById('sequences');
				sec.classList.remove('is-short'); more.setAttribute('aria-expanded', 'true'); more.parentNode.hidden = true;
				var seventh = sec.querySelectorAll('.rr-seq__card')[4]; if (seventh) { seventh.focus({ preventScroll: true }); }
			});
		}
	}

	function init() {
		presence();
		votes();
		seqVideos();
		snow();
		gbMore();
		elf();
		quiz();
		countdown();
		stats();
		share();
		live();
		fixHead();
		forwardWheel();
		window.addEventListener('resize', fixHead);
		window.addEventListener('load', fixHead);
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fixHead); }
		start();
		spy();
		makeQR();
		window.addEventListener('load', makeQR);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
