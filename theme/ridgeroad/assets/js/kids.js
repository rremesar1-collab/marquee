/* Kids' Corner (2.6): doors, one activity at a time, contest form, Show Wall, names. */
(function () {
	'use strict';
	var cfg = window.RRShow || {};
	var lab = function () { return window.RRLab; };
	var kc = document.querySelector('[data-rr-kc]');
	if (!kc) { return; }
	var $ = function (s, r) { return (r || kc).querySelector(s); };
	var $$ = function (s, r) { return Array.prototype.slice.call((r || kc).querySelectorAll(s)); };
	function stat(ev, v) { try { if (window.RRStat) { window.RRStat(ev, v); } } catch (e) {} }

	/* ---------- Doors and activities ---------- */
	var ACT = { 'light-show': 'light-show', names: 'names', coloring: 'coloring', games: 'games' };
	var ALIAS = { 'light-lab': ['light-show', 'make'], enter: ['light-show', 'enter'], 'show-wall': ['', 'wall'], contest: ['', 'wall'], elf: ['games', 'elf'], trivia: ['games', 'trivia'] };
	function route(h) {
		h = (h || '').replace('#', '');
		if (ACT[h]) { return [h, null]; }
		if (ALIAS[h]) { return ALIAS[h]; }
		return ['', null];
	}
	function open(id, sub, scroll) {
		if (id && !$('[data-kc-act="' + id + '"]')) { id = ''; }
		$('[data-kc-home]').hidden = !!id;
		$$('[data-kc-act]').forEach(function (a) { a.hidden = a.getAttribute('data-kc-act') !== id; });
		if (id !== 'light-show' && lab() && lab().stop) { lab().stop(); }
		if (id === 'light-show') { step(sub || curStep || 'make'); if (lab() && lab().resize) { setTimeout(lab().resize, 30); } }
		if (id === 'games' && sub) { game(sub); }
		if (id === 'games' && !gamesSeen) { gamesSeen = true; }
		if (!id && sub === 'wall') {   // the Show Wall is on the Kids' Corner page itself, under the activities
			var w = $('#show-wall');
			if (w) { setTimeout(function () { w.scrollIntoView({ behavior: scroll === false ? 'auto' : 'smooth', block: 'start' }); }, 30); }
			return;
		}
		if (scroll !== false) { kc.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	}
	var curStep = null, gamesSeen = false;
	function step(s) {
		if (!$('[data-kc-pane="' + s + '"]')) { s = 'make'; }
		curStep = s;
		$$('[data-kc-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-kc-pane') !== s; });
		$$('[data-kc-step]').forEach(function (a) {
			var on = a.getAttribute('data-kc-step') === s;
			a.classList.toggle('is-on', on);
			if (on) { a.setAttribute('aria-current', 'step'); } else { a.removeAttribute('aria-current'); }
		});
		if (s !== 'make' && lab() && lab().stop) { lab().stop(); }
		if (s === 'enter') { refreshEntry(); }
		if (s === 'make' && lab() && lab().resize) { setTimeout(lab().resize, 30); }
	}
	function setHash(h) { try { history.pushState(null, '', h ? '#' + h : location.pathname + location.search); } catch (e) {} }
	document.addEventListener('click', function (e) {
		var home = e.target.closest('[data-kc-home-btn]');
		if (home && kc.contains(home)) { e.preventDefault(); setHash(''); open('', null); return; }
		var a = e.target.closest('a[href^="#"]');
		if (!a || !kc.contains(a)) { return; }
		var h = a.getAttribute('href').slice(1), r = route(h);
		if (!r[0] && r[1] !== 'wall') { return; }
		e.preventDefault(); setHash(h);
		var sameAct = r[0] ? !$('[data-kc-act="' + r[0] + '"]').hidden : false;
		open(r[0], r[1], !sameAct || !!r[1]);
	});
	window.addEventListener('popstate', function () { var r = route(location.hash); open(r[0], r[1], false); });
	window.addEventListener('hashchange', function () { var r = route(location.hash); open(r[0], r[1], false); });
	(function () { var r = route(location.hash); open(r[0], r[1], false); })();

	/* ---------- Games ---------- */
	function game(id) {
		$$('[data-kc-game]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-kc-game') === id)); });
		$$('[data-kc-g]').forEach(function (g) { g.hidden = g.getAttribute('data-kc-g') !== id; });
	}
	$$('[data-kc-game]').forEach(function (b) { b.addEventListener('click', function () { game(b.getAttribute('data-kc-game')); }); });
	$$('[data-kc-stat]').forEach(function (a) { a.addEventListener('click', function () { stat(a.getAttribute('data-kc-stat')); }); });

	/* ---------- Helpers ---------- */
	function nonce() {
		return fetch(cfg.ajax + '?action=rr_art_nonce&_=' + Date.now(), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (!j.success) { throw new Error('nonce'); } return j.data.nonce; });
	}
	function post(data) {
		return nonce().then(function (n) {
			data.append('nonce', n);
			return fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' });
		}).then(function (r) { return r.json(); });
	}
	function store(k, v) {
		try { if (v === undefined) { return JSON.parse(localStorage.getItem(k) || 'null'); } localStorage.setItem(k, JSON.stringify(v)); } catch (e) {}
		return null;
	}

	/* ---------- Contest form (step 2) ---------- */
	var form = document.querySelector('[data-rr-enter]');
	function refreshEntry() {
		if (!form || !lab() || !lab().filled) { return; }
		var card = form.querySelector('[data-rr-entry-card]'), empty = form.querySelector('[data-rr-entry-empty]');
		var s = lab().get(), has = s && lab().filled(s);
		card.hidden = !has; empty.hidden = !!has;
		if (!has) { return; }
		form.querySelector('[data-rr-enter-thumb]').src = lab().snapshot(s);
		form.querySelector('[data-rr-enter-name]').textContent = s.name ? '“' + s.name + '”' : 'My Light Show';
		form.querySelector('[data-rr-enter-song]').textContent = 'Song: ' + lab().songName(s.song);
	}
	if (form) {
		var msg = form.querySelector('[data-rr-enter-msg]'), done = document.querySelector('[data-rr-enter-done]');
		lab() && lab().ready(function () { if (curStep === 'enter') { refreshEntry(); } });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			msg.textContent = '';
			var s = lab() ? lab().get() : null;
			if (!s || !lab().filled(s)) { msg.textContent = 'First make your light show in step 1.'; return; }
			if (!form.child.value.trim()) { msg.textContent = 'Please type your child’s first name.'; form.child.focus(); return; }
			if (!form.age.value) { msg.textContent = 'Please pick an age.'; form.age.focus(); return; }
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.email.value.trim())) { msg.textContent = 'Please enter a parent email so we can send your claim code.'; form.email.focus(); return; }
			if (!form.consent.checked) { msg.textContent = 'A parent or guardian needs to check the permission box.'; return; }
			var btn = form.querySelector('button[type=submit]');
			btn.disabled = true; msg.textContent = 'Sending your show…';
			var fd = new FormData(form);
			fd.append('action', 'rr_art_submit');
			fd.append('show', JSON.stringify(s));
			fd.append('showname', s.name || '');
			fd.append('thumb', lab().snapshot(s));
			post(fd).then(function (j) {
				if (j && j.success) {
					form.hidden = true; done.hidden = false;
					done.querySelector('[data-rr-enter-code]').textContent = j.data.code;
					store('rrShowCode', j.data.code);
					done.scrollIntoView({ behavior: 'smooth', block: 'center' });
				} else {
					msg.textContent = (j && j.data && j.data.message) || 'Something went wrong. Please try again.';
					btn.disabled = false;
				}
			}).catch(function () { msg.textContent = 'We couldn’t reach the website. Please try again.'; btn.disabled = false; });
		});
	}

	/* ---------- Pop-up player (Show Wall + admin Watch link) ---------- */
	var sm = document.querySelector('[data-rr-showmodal]'), smTimer = null, smHome = null;
	function smSync() {
		if (!sm || !lab()) { return; }
		var on = lab().playing(), b = sm.querySelector('[data-rr-sm-play]');
		b.querySelector('b').innerHTML = on ? '&#9632;' : '&#9654;';
		b.querySelector('span').textContent = on ? 'Stop' : 'Play';
	}
	function openShow(seq, label, title, noAuto) {
		if (!lab() || !sm) { return; }
		var stage = document.querySelector('.rr-ll__stage');
		if (!stage) { return; }
		smHome = { parent: stage.parentNode, next: stage.nextSibling };
		sm.querySelector('[data-rr-sm-stage]').appendChild(stage);
		sm.querySelector('[data-rr-sm-title]').textContent = title || 'Light Show';
		sm.querySelector('[data-rr-sm-by]').textContent = label ? 'by ' + label : '';
		if (sm.showModal) { sm.showModal(); } else { sm.setAttribute('open', ''); }
		if (!lab().watch(seq, label, noAuto)) { closeShow(); return; }
		smSync(); clearInterval(smTimer); smTimer = setInterval(smSync, 300);
	}
	function closeShow() {
		if (!sm) { return; }
		clearInterval(smTimer);
		if (lab()) { lab().unwatch(); }
		var stage = sm.querySelector('.rr-ll__stage');
		if (stage && smHome) { smHome.parent.insertBefore(stage, smHome.next); }
		if (sm.open) { sm.close(); }
		if (lab() && lab().resize) { setTimeout(lab().resize, 30); }
	}
	if (sm) {
		sm.querySelectorAll('[data-rr-sm-close]').forEach(function (b) { b.addEventListener('click', closeShow); });
		sm.addEventListener('cancel', function (e) { e.preventDefault(); closeShow(); });
		sm.addEventListener('click', function (e) { if (e.target === sm) { closeShow(); } });
		sm.querySelector('[data-rr-sm-play]').addEventListener('click', function () {
			if (lab().playing()) { lab().stop(); } else { lab().play(); }
			setTimeout(smSync, 50);
		});
	}

	/* ---------- Show Wall (step 3) ---------- */
	var wall = document.querySelector('[data-rr-wall]');
	if (wall) {
		var voted = store('rrShowVotes') || [];
		wall.querySelectorAll('.rr-wall__heart').forEach(function (h) {
			if (voted.indexOf(+h.getAttribute('data-id')) >= 0) { h.classList.add('is-on'); }
		});
		wall.addEventListener('click', function (e) {
			var w = e.target.closest('.rr-wall__watch');
			if (w && lab()) {
				try { openShow(JSON.parse(w.getAttribute('data-show')), w.getAttribute('data-label'), w.getAttribute('data-name')); } catch (err) {}
				return;
			}
			var h = e.target.closest('.rr-wall__heart');
			if (!h || h.disabled) { return; }
			var id = +h.getAttribute('data-id');
			if (h.classList.contains('is-on')) { h.classList.remove('is-pop'); void h.offsetWidth; h.classList.add('is-pop'); return; }
			h.classList.add('is-on', 'is-pop');
			var fd = new FormData(); fd.append('action', 'rr_art_vote'); fd.append('id', id);
			post(fd).then(function (j) {
				if (j && j.data && typeof j.data.votes === 'number') { h.querySelector('span').textContent = j.data.votes; }
				if (j && j.success) { voted.push(id); store('rrShowVotes', voted); } else { h.classList.remove('is-on'); }
			}).catch(function () { h.classList.remove('is-on'); });
		});
	}

	/* ---------- Your Name in Lights ---------- */
	var nb = document.querySelector('[data-rr-names]');
	var nonceN = function () {
		return fetch(cfg.ajax + '?action=rr_names_nonce&_=' + Date.now(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
	};
	/* the page may be cached: ask the site whether names are open now, and again every minute */
	function namesOpen(open) {
		var door = $('[data-kc-door="names"]'), doors = $('[data-kc-doors]'), sign = $('[data-kc-names-sign]');
		if (door && doors) {
			if (open) { doors.insertBefore(door, doors.firstChild); } else { doors.insertBefore(door, $('[data-kc-door="coloring"]')); }
			if (sign) { sign.hidden = !!open; }
		}
		if (nb) {
			var nf = nb.querySelector('[data-rr-names-form]'), ndone = nb.querySelector('[data-rr-names-done]'), nclosed = nb.querySelector('[data-rr-names-closed]');
			if (ndone.hidden) { nf.hidden = !open; nclosed.hidden = !!open; }
		}
	}
	if ($('[data-kc-door="names"]') || nb) {
		var check = function () { nonceN().then(function (j) { if (j && j.success) { namesOpen(j.data.open); } }).catch(function () {}); };
		check(); setInterval(check, 60000);
	}
	if (nb) {
		var nf = nb.querySelector('[data-rr-names-form]'), nmsg = nb.querySelector('[data-rr-names-msg]'), ndone = nb.querySelector('[data-rr-names-done]');
		/* No maxlength on the name box (on phones it stops a picked spelling suggestion from replacing the word);
		   the 14-letter limit is checked here and on send instead. Never change the box while the kid types. */
		var preview = function () {
			var raw = nf.kid.value.trim().split(/\s+/)[0] || '';
			var nm = (raw || 'NAME').toUpperCase();
			nb.querySelectorAll('[data-greet]').forEach(function (sp) { sp.textContent = sp.getAttribute('data-greet').replace('NAME', nm); });
			nmsg.textContent = raw.length > 14 ? 'That’s a long one! Names can have up to 14 letters.' : '';
		};
		nf.kid.addEventListener('input', preview);
		nf.addEventListener('submit', function (e) {
			e.preventDefault();
			nmsg.textContent = '';
			nf.kid.value = nf.kid.value.trim();
			if (!/^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'\-]{1,13}$/.test(nf.kid.value)) {
				nmsg.textContent = nf.kid.value.length > 14 ? 'That’s a long one! Names can have up to 14 letters.' : 'Please type just your first name (letters only).'; nf.kid.focus(); return;
			}
			var btn = nf.querySelector('button[type=submit]'); btn.disabled = true;
			var geo = !!nb.querySelector('[data-rr-names-geo]'), pos = null;
			nmsg.textContent = geo ? 'Checking you’re at the show…' : 'Sending to Santa’s elves…';
			/* "Only at the show": ask the phone where it is (compared on the website, never saved) */
			var where = !geo ? Promise.resolve() : new Promise(function (ok, no) {
				if (!navigator.geolocation || !window.isSecureContext) { no('unsupported'); return; }
				navigator.geolocation.getCurrentPosition(function (p) { pos = p.coords; ok(); }, function (er) { no(er && er.code === 1 ? 'denied' : 'unavailable'); }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
			});
			where.then(function () {
				nmsg.textContent = 'Sending to Santa’s elves…';
				return nonceN();
			}).then(function (j) {
				var fd = new FormData(nf);
				fd.append('action', 'rr_name_send'); fd.append('nonce', j.data.nonce);
				if (pos) { fd.append('lat', pos.latitude); fd.append('lon', pos.longitude); fd.append('acc', Math.round(pos.accuracy || 0)); }
				return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
			}, function (why) {
				throw typeof why === 'string' ? { geo: why } : why;
			}).then(function (r) { return r.json(); }).then(function (j) {
				btn.disabled = false;
				if (j && j.success) {
					nmsg.textContent = '';
					nf.hidden = true; ndone.hidden = false;
					nb.querySelector('[data-rr-names-text]').textContent = j.data.text;
					var p = j.data.place;
					nb.querySelector('[data-rr-names-place]').textContent = j.data.again ? 'Your name is already in line!' :
						(j.data.live ? (p <= 1 ? 'You’re next! Watch our screens: your name goes up in a minute.' : 'You’re #' + p + ' in line. Watch our screens: your name goes up in a few minutes.') :
						(p <= 6 ? (p <= 1 ? 'You’re next! Watch for your name after this song.' : 'You’re #' + p + ' in line. Watch for your name after this song.') : 'You’re #' + p + ' in line. Your name will show after a few songs.'));
					if (j.data.theme && !j.data.again) {
						nb.querySelector('[data-rr-names-place]').textContent += ' It goes up in ' + j.data.theme + ' (if lots of kids picked other shows, it may be one song later).';
					}
					buzzSetup(j.data);
				} else {
					nmsg.textContent = (j && j.data && j.data.message) || 'Something went wrong. Please try again.';
				}
			}).catch(function (e) {
				btn.disabled = false;
				if (e && e.geo === 'denied') { nmsg.textContent = 'Names can only be sent from the show, so we need to see where your phone is. Allow location for this website in your phone’s settings, then try again.'; return; }
				if (e && e.geo) { nmsg.textContent = 'We couldn’t find your location. Make sure Location is turned on, then try again.'; return; }
				nmsg.textContent = 'We couldn’t reach the website. Please try again.';
			});
		});
		/* "Buzz me when my name is up" (only where this phone can get alerts) */
		var bz = nb.querySelector('[data-rr-buzz]'), bzBtn = bz && bz.querySelector('[data-rr-buzz-btn]'), bzTxt = bz && bz.querySelector('[data-rr-buzz-text]'), bzFor = null;
		var buzzSetup = function (d) {
			if (!bz) { return; }
			var api = window.RRAppAPI;
			bzFor = d && d.id && d.buzz ? d : null;
			bz.hidden = !(bzFor && api && api.canBuzz());
			bz.classList.remove('is-done');
			bzBtn.hidden = false; bzBtn.disabled = false;
			bzTxt.textContent = 'Want a buzz on this phone right before your name shows?';
		};
		if (bzBtn) {
			bzBtn.addEventListener('click', function () {
				if (!bzFor) { return; }
				bzBtn.disabled = true;
				window.RRAppAPI.buzz(bzFor.id, bzFor.buzz).then(function () {
					bz.classList.add('is-done'); bzBtn.hidden = true;
					bzTxt.textContent = 'Okay! This phone will buzz when your name is up next.';
				}).catch(function (err) {
					bzBtn.disabled = false;
					bzTxt.textContent = window.RRAppAPI.why(err);
				});
			});
		}
		nb.querySelector('[data-rr-names-again]').addEventListener('click', function () {
			ndone.hidden = true; nf.hidden = false; nf.reset(); preview(); nf.kid.focus();
		});
	}

	/* ---------- ?rr_watch=ID link (site owner reviewing an entry) ---------- */
	if (cfg.watch && cfg.watch.show && lab()) {
		lab().ready(function () {
			open('', 'wall', false);
			try { openShow(JSON.parse(cfg.watch.show), cfg.watch.label, cfg.watch.title, true); } catch (e) {}
		});
	}
})();

/* Pick your show: each theme card plays a little animated picture of the Light Lab house
   lit in that theme's style (when the show didn't upload its own picture). */
(function () {
	var set = document.querySelector('[data-rr-fx-lab]');
	if (!set) { return; }
	var cards = [].slice.call(set.querySelectorAll('canvas[data-rr-fx]'));
	var base = set.getAttribute('data-rr-fx-lab') || '';
	if (!cards.length || !base) { return; }
	var M = { tree: [42, 83, 214, 362], mini: [355, 372, 839, 86], arch: [314, 349, 922, 108], spin: [340, 80, 1106, 150], flake: [265, 167, 1355, 156], win: [306, 234, 815, 102], faces: [936, 166, 107, 61], face2: [1370, 122, 118, 88], garage: [1276, 265, 300, 130], gift: [6, 354, 816, 56], roof: [227, 30, 1435, 230], sign: [723, 410, 100, 54] };
	var LOOKS = {
		classic: ['#ffd45c', '#ff6b5b', '#4fd27a', '#5aa8ff'], candy: ['#ff2b3a', '#ffffff'], snow: ['#ffffff', '#8fd3ff'],
		rainbow: [], christmas: ['#ff2b3a', '#22d46a'], aurora: ['#38ffb8', '#7a5cff'], gold: ['#ffcf4a', '#fff2c0'],
		ginger: ['#e0893a', '#fff0d0'], icy: ['#5ab8ff', '#ffffff'], purple: ['#c45cff', '#ff6fc8']
	};
	var house = new Image(), props = [], left = 1;
	house.onload = function () { left--; };
	house.src = base + 'house.jpg';
	Object.keys(M).forEach(function (k, n) { var im = new Image(); left++; im.onload = im.onerror = function () { left--; }; im.src = base + 'm-' + k + '.png'; props.push({ k: k, n: n, im: im, c: {} }); });
	function tint(p, col) {
		if (p.c[col]) { return p.c[col]; }
		if (!p.im.naturalWidth) { return null; }
		var c = document.createElement('canvas'); c.width = p.im.naturalWidth; c.height = p.im.naturalHeight;
		var x = c.getContext('2d'); x.drawImage(p.im, 0, 0); x.globalCompositeOperation = 'multiply'; x.fillStyle = col; x.fillRect(0, 0, c.width, c.height);
		x.globalCompositeOperation = 'destination-in'; x.drawImage(p.im, 0, 0);
		p.c[col] = c; return c;
	}
	/* candy cane: every prop red, with white diagonal stripes sliding across the house */
	var layer = document.createElement('canvas');
	function candy(ctx, W, H, S, t) {
		layer.width = W; layer.height = H;
		var x = layer.getContext('2d');
		props.forEach(function (p) { var m = M[p.k], c = tint(p, '#ffffff'); if (c) { x.drawImage(c, m[0] * S, m[1] * S, m[2] * S, m[3] * S); } });
		x.globalCompositeOperation = 'destination-in'; x.fillStyle = '#000';
		var w = W / 14, off = (t * W / 9) % (w * 2);
		x.beginPath();
		for (var s = -H - w * 2; s < W + H; s += w * 2) { var a = s + off; x.moveTo(a, H); x.lineTo(a + w, H); x.lineTo(a + w + H, 0); x.lineTo(a + H, 0); x.closePath(); }
		x.fill();
		props.forEach(function (p) {
			var m = M[p.k], c = tint(p, '#ff2b3a'); if (!c) { return; }
			ctx.globalAlpha = 1; ctx.drawImage(c, m[0] * S, m[1] * S, m[2] * S, m[3] * S);
			ctx.globalAlpha = .5; ctx.drawImage(c, m[0] * S - 1.5, m[1] * S - 1.5, m[2] * S + 3, m[3] * S + 3);
		});
		ctx.globalCompositeOperation = 'lighter'; ctx.globalAlpha = 1; ctx.drawImage(layer, 0, 0);   // the masks are light on black, so add them
		ctx.globalAlpha = .4; ctx.drawImage(layer, -1, -1, W + 2, H + 2);
	}
	var flakes = []; for (var i = 0; i < 40; i++) { flakes.push([Math.random(), Math.random(), .15 + Math.random() * .35]); }
	function draw(cv, t) {
		var look = cv.getAttribute('data-rr-fx'), cols = LOOKS[look] || LOOKS.classic, ctx = cv.getContext('2d'), W = cv.width, H = cv.height, S = W / 1672;
		ctx.globalCompositeOperation = 'source-over'; ctx.globalAlpha = 1; ctx.fillStyle = '#04060d'; ctx.fillRect(0, 0, W, H);
		ctx.globalAlpha = .5; ctx.drawImage(house, 0, 0, W, H); ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'lighter';
		if (look === 'candy') { candy(ctx, W, H, S, t); }
		else props.forEach(function (p) {
			var m = M[p.k], col, a = 1, step = Math.floor(t * 2);
			if (look === 'rainbow') { col = 'hsl(' + Math.round((p.n * 37 + t * 110) % 360) + ',100%,60%)'; }
			else if (look === 'candy') { col = cols[(p.n + step) % 2]; }
			else if (look === 'christmas') { col = cols[(p.n + step) % 2]; a = .75 + .25 * Math.sin(t * 4 + p.n); }
			else if (look === 'aurora') { col = Math.sin(m[0] / 260 + t * 1.6) > 0 ? cols[0] : cols[1]; a = .6 + .4 * Math.abs(Math.sin(m[0] / 300 + t)); }
			else if (look === 'snow' || look === 'icy') { col = (p.n + Math.floor(t * 1.2)) % 3 ? cols[1] : cols[0]; a = .65 + .35 * Math.sin(t * 3 + p.n * 2); }
			else if (look === 'purple') { col = cols[(p.n + Math.floor(t * 3)) % 2]; a = .55 + .45 * Math.abs(Math.sin(t * 5 + p.n)); }
			else { col = cols[p.n % cols.length]; a = .5 + .5 * Math.abs(Math.sin(t * 1.7 + p.n * 1.3)); }
			var c = tint(p, col); if (!c) { return; }
			ctx.globalAlpha = a; ctx.drawImage(c, m[0] * S, m[1] * S, m[2] * S, m[3] * S);
			ctx.globalAlpha = a * .55; ctx.drawImage(c, m[0] * S - 1.5, m[1] * S - 1.5, m[2] * S + 3, m[3] * S + 3);
		});
		ctx.globalCompositeOperation = 'source-over'; ctx.globalAlpha = 1;
		if (look === 'snow') {
			ctx.fillStyle = 'rgba(255,255,255,.85)';
			flakes.forEach(function (f) { var y = ((f[1] + t * f[2] * .25) % 1) * H, x = (f[0] * W + Math.sin(t + f[1] * 9) * 6); ctx.beginPath(); ctx.arc(x, y, 1.2 + f[2] * 3, 0, 7); ctx.fill(); });
		}
		if (look === 'gold' || look === 'icy') {
			ctx.fillStyle = '#fff';
			for (var k = 0; k < 14; k++) { var r = Math.sin(k * 91.7 + Math.floor(t * 6) * 3.1) * 43758.5; r -= Math.floor(r); var r2 = Math.sin(k * 12.3 + Math.floor(t * 6)) * 9631.2; r2 -= Math.floor(r2); ctx.globalAlpha = .9; ctx.fillRect(r * W, (.1 + r2 * .75) * H, 2, 2); }
			ctx.globalAlpha = 1;
		}
	}
	var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	function onScreen(c) {                     // only animate cards you can see (and that are open)
		if (!c.offsetParent) { return false; }
		var r = c.getBoundingClientRect();
		return r.width > 0 && r.bottom > 0 && r.top < (window.innerHeight || 800);
	}
	var last = 0;
	function frame(now) {
		if (!left && now - last > 66) {           // about 15 frames a second is plenty for twinkling lights
			last = now;
			cards.forEach(function (c) { if (onScreen(c)) { draw(c, now / 1000); } });
			if (still) { return; }
		}
		requestAnimationFrame(frame);
	}
	requestAnimationFrame(frame);
})();
