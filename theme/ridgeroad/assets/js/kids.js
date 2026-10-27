/* Kids' Corner (2.6): doors, one activity at a time, contest form, Show Wall, names. */
(function () {
	'use strict';
	var cfg = window.RRShow || {};
	var t = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
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
		form.querySelector('[data-rr-enter-name]').textContent = s.name ? t('“%s”').replace('%s', function () { return s.name; }) : t('My Light Show');
		form.querySelector('[data-rr-enter-song]').textContent = t('Song: %s').replace('%s', function () { return lab().songName(s.song); });
	}
	if (form) {
		var msg = form.querySelector('[data-rr-enter-msg]'), done = document.querySelector('[data-rr-enter-done]');
		lab() && lab().ready(function () { if (curStep === 'enter') { refreshEntry(); } });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			msg.textContent = '';
			var s = lab() ? lab().get() : null;
			if (!s || !lab().filled(s)) { msg.textContent = t('First make your light show in step 1.'); return; }
			if (!form.child.value.trim()) { msg.textContent = t('Please type your child’s first name.'); form.child.focus(); return; }
			if (!form.age.value) { msg.textContent = t('Please pick an age.'); form.age.focus(); return; }
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.email.value.trim())) { msg.textContent = t('Please enter a parent email so we can send your claim code.'); form.email.focus(); return; }
			if (!form.consent.checked) { msg.textContent = t('A parent or guardian needs to check the permission box.'); return; }
			var btn = form.querySelector('button[type=submit]');
			btn.disabled = true; msg.textContent = t('Sending your show…');
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
					msg.textContent = (j && j.data && j.data.message) || t('Something went wrong. Please try again.');
					btn.disabled = false;
				}
			}).catch(function () { msg.textContent = t('We couldn’t reach the website. Please try again.'); btn.disabled = false; });
		});
	}

	/* ---------- Pop-up player (Show Wall + admin Watch link) ---------- */
	var sm = document.querySelector('[data-rr-showmodal]'), smTimer = null, smHome = null;
	function smSync() {
		if (!sm || !lab()) { return; }
		var on = lab().playing(), b = sm.querySelector('[data-rr-sm-play]');
		b.querySelector('b').innerHTML = on ? '&#9632;' : '&#9654;';
		b.querySelector('span').textContent = on ? t('Stop') : t('Play');
	}
	function openShow(seq, label, title, noAuto) {
		if (!lab() || !sm) { return; }
		var stage = document.querySelector('.rr-ll__stage');
		if (!stage) { return; }
		smHome = { parent: stage.parentNode, next: stage.nextSibling };
		sm.querySelector('[data-rr-sm-stage]').appendChild(stage);
		sm.querySelector('[data-rr-sm-title]').textContent = title || t('Light Show');
		sm.querySelector('[data-rr-sm-by]').textContent = label ? t('by %s').replace('%s', function () { return label; }) : '';
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
			var on = $('[data-kc-names-on]'); if (on) { on.hidden = !open; }
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
			var nm = (raw || t('NAME')).toUpperCase();
			nb.querySelectorAll('[data-greet]').forEach(function (sp) { sp.textContent = sp.getAttribute('data-greet').replace('NAME', nm); });
			nmsg.textContent = raw.length > 14 ? t('That’s a long one! Names can have up to 14 letters.') : '';
		};
		nf.kid.addEventListener('input', preview);
		var pic = picture(nf);
		nf.addEventListener('submit', function (e) {
			e.preventDefault();
			nmsg.textContent = '';
			nf.kid.value = nf.kid.value.trim();
			if (!/^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'\-]{1,13}$/.test(nf.kid.value)) {
				nmsg.textContent = nf.kid.value.length > 14 ? t('That’s a long one! Names can have up to 14 letters.') : t('Please type just your first name (letters only).'); nf.kid.focus(); return;
			}
			if (pic && !pic.ready()) { nmsg.textContent = t('A parent or guardian needs to tick the box to say your picture can go on our screens.'); return; }
			var btn = nf.querySelector('button[type=submit]'); btn.disabled = true;
			var geo = !!nb.querySelector('[data-rr-names-geo]'), pos = null;
			nmsg.textContent = geo ? t('Checking you’re at the show…') : t('Sending to Santa’s elves…');
			/* "Only at the show": ask the phone where it is (compared on the website, never saved) */
			var where = !geo ? Promise.resolve() : new Promise(function (ok, no) {
				if (!navigator.geolocation || !window.isSecureContext) { no('unsupported'); return; }
				navigator.geolocation.getCurrentPosition(function (p) { pos = p.coords; ok(); }, function (er) { no(er && er.code === 1 ? 'denied' : 'unavailable'); }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
			});
			where.then(function () {
				nmsg.textContent = t('Sending to Santa’s elves…');
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
					nb.querySelector('[data-rr-names-place]').textContent = j.data.again ? t('Your name is already in line!') :
						(j.data.live ? (p <= 1 ? t('You’re next! Watch our screens: your name goes up in a minute.') : t('You’re #%s in line. Watch our screens: your name goes up in a few minutes.').replace('%s', p)) :
						(p <= 6 ? (j.data.songs > 1 ?
							(p <= 1 ? t('You’re next! Watch for your name in about %s songs.').replace('%s', j.data.songs) : t('You’re #%1$s in line. Watch for your name in about %2$s songs.').replace('%1$s', p).replace('%2$s', j.data.songs)) :
							(p <= 1 ? t('You’re next! Watch for your name after this song.') : t('You’re #%s in line. Watch for your name after this song.').replace('%s', p))) :
							t('You’re #%s in line. Your name will show after a few songs.').replace('%s', p)));
					if (j.data.theme && !j.data.again) {
						nb.querySelector('[data-rr-names-place]').textContent += ' ' + t('It goes up in %s (if lots of kids picked other shows, it may be one song later).').replace('%s', function () { return j.data.theme; });
					}
					buzzSetup(j.data);
				} else {
					nmsg.textContent = (j && j.data && j.data.message) || t('Something went wrong. Please try again.');
				}
			}).catch(function (e) {
				btn.disabled = false;
				if (e && e.geo === 'denied') { nmsg.textContent = t('Names can only be sent from the show, so we need to see where your phone is. Allow location for this website in your phone’s settings, then try again.'); return; }
				if (e && e.geo) { nmsg.textContent = t('We couldn’t find your location. Make sure Location is turned on, then try again.'); return; }
				nmsg.textContent = t('We couldn’t reach the website. Please try again.');
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
			bzTxt.textContent = t('Want a buzz on this phone right before your name shows?');
		};
		if (bzBtn) {
			bzBtn.addEventListener('click', function () {
				if (!bzFor) { return; }
				bzBtn.disabled = true;
				window.RRAppAPI.buzz(bzFor.id, bzFor.buzz).then(function () {
					bz.classList.add('is-done'); bzBtn.hidden = true;
					bzTxt.textContent = t('Okay! This phone will buzz when your name is up next.');
				}).catch(function (err) {
					bzBtn.disabled = false;
					bzTxt.textContent = window.RRAppAPI.why(err);
				});
			});
		}
		nb.querySelector('[data-rr-names-again]').addEventListener('click', function () {
			ndone.hidden = true; nf.hidden = false; nf.reset(); preview(); if (pic) { pic.clear(); } nf.kid.focus();
		});
	}

	/* ---------- Kids' pictures (when the show turned them on) ----------
	   Everything happens on the phone: the kid moves and zooms the picture so their face fills the
	   circle, the background is cut away (a small free tool the phone downloads; the picture itself
	   is never sent anywhere), and it becomes a cartoon, an elf, a snowman or one of Santa's helpers.
	   Only the finished 320-pixel picture is sent with the name. */
	var PIC_OUT = 320, PIC_CROP = 512;
	var RING = { x: 0.5, y: 0.46, r: 0.31 };                 // the circle the face goes in (parts of the square)

	function picture(form) {
		var box = form.querySelector('[data-rr-pic]');
		if (!box) { return null; }
		var $p = function (s) { return box.querySelector(s); };
		var file = $p('[data-rr-pic-file]'), data = $p('[data-rr-pic-data]'), kindIn = $p('[data-rr-pic-kind]');
		var clr = $p('[data-rr-pic-clear]'), okWrap = $p('[data-rr-pic-okwrap]'), ok = $p('[data-rr-pic-ok]'), take = $p('[data-rr-pic-take] span');
		var edit = $p('[data-rr-pic-edit]'), stage = $p('[data-rr-pic-stage]'), zoom = $p('[data-rr-pic-zoom]');
		var out = $p('[data-rr-pic-out]'), scr = $p('[data-rr-pic-screen]'), wait = $p('[data-rr-pic-wait]'), scrBox = $p('[data-rr-pic-screenbox]');
		var mode = box.getAttribute('data-mode') || 'cartoon';
		var img = null, view = { s: 1, x: 0, y: 0 }, busy = 0, timer = 0, cut = null, cutKey = '';

		var style = function () { var r = box.querySelector('input[name=photo_style]:checked'); return r ? r.value : (mode === 'photo' ? 'photo' : 'cartoon'); };

		/* the stage: the photo under a dark cover with a hole for the face */
		var S = stage.width;
		var cover = function () { return Math.max(S / img.naturalWidth, S / img.naturalHeight); };
		var clamp = function () {
			var w = img.naturalWidth * view.s, h = img.naturalHeight * view.s;
			view.x = Math.min(0, Math.max(S - w, view.x)); view.y = Math.min(0, Math.max(S - h, view.y));
		};
		var paint = function () {
			var x = stage.getContext('2d');
			x.clearRect(0, 0, S, S);
			x.drawImage(img, view.x, view.y, img.naturalWidth * view.s, img.naturalHeight * view.s);
			x.save(); x.beginPath(); x.rect(0, 0, S, S); x.arc(RING.x * S, RING.y * S, RING.r * S, 0, Math.PI * 2, true);
			x.fillStyle = 'rgba(5,10,30,.55)'; x.fill('evenodd'); x.restore();
			x.beginPath(); x.arc(RING.x * S, RING.y * S, RING.r * S, 0, Math.PI * 2);
			x.setLineDash([S / 40, S / 60]); x.lineWidth = S / 120; x.strokeStyle = '#fff'; x.stroke(); x.setLineDash([]);
		};
		var zoomTo = function (s, cx, cy) {
			var base = cover(), ns = Math.max(base, Math.min(base * 4, s));
			cx = cx === undefined ? RING.x * S : cx; cy = cy === undefined ? RING.y * S : cy;
			view.x = cx - (cx - view.x) * ns / view.s; view.y = cy - (cy - view.y) * ns / view.s; view.s = ns;
			clamp(); zoom.value = String(view.s / base); paint();
		};
		var later = function () { clearTimeout(timer); timer = setTimeout(render, 380); };

		/* drag to move, pinch (or the slider, or the mouse wheel) to zoom */
		var pts = {}, pinch = null;
		var pos = function (e) { var r = stage.getBoundingClientRect(); return { x: (e.clientX - r.left) * S / r.width, y: (e.clientY - r.top) * S / r.height }; };
		stage.addEventListener('pointerdown', function (e) { if (!img) { return; } stage.setPointerCapture(e.pointerId); pts[e.pointerId] = pos(e); pinch = null; });
		stage.addEventListener('pointermove', function (e) {
			if (!img || !pts[e.pointerId]) { return; }
			var p = pos(e), ids = Object.keys(pts);
			if (ids.length === 1) {
				view.x += p.x - pts[e.pointerId].x; view.y += p.y - pts[e.pointerId].y; pts[e.pointerId] = p; clamp(); paint();
			} else if (ids.length === 2) {
				pts[e.pointerId] = p;
				var a = pts[ids[0]], b = pts[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
				if (pinch) { zoomTo(view.s * d / pinch, (a.x + b.x) / 2, (a.y + b.y) / 2); }
				pinch = d;
			}
			e.preventDefault();
		});
		var up = function (e) { if (pts[e.pointerId]) { delete pts[e.pointerId]; pinch = null; later(); } };
		stage.addEventListener('pointerup', up); stage.addEventListener('pointercancel', up);
		stage.addEventListener('wheel', function (e) { if (!img) { return; } e.preventDefault(); var p = pos(e); zoomTo(view.s * (e.deltaY < 0 ? 1.08 : 1 / 1.08), p.x, p.y); later(); }, { passive: false });
		zoom.addEventListener('input', function () { if (img) { zoomTo(cover() * parseFloat(zoom.value)); later(); } });
		box.querySelectorAll('input[name=photo_style]').forEach(function (r) { r.addEventListener('change', render); });

		/* the square the kid framed, 512 pixels */
		var crop = function () {
			var c = document.createElement('canvas'); c.width = c.height = PIC_CROP;
			var k = PIC_CROP / S, x = c.getContext('2d');
			x.drawImage(img, view.x * k, view.y * k, img.naturalWidth * view.s * k, img.naturalHeight * view.s * k);
			return c;
		};

		/* make the picture: cut out the kid, cartoon, then the festive scene */
		function render() {
			if (!img) { return; }
			var job = ++busy, st = style(), c = crop();
			kindIn.value = st === 'photo' ? 'photo' : 'cartoon';
			var key = [view.s.toFixed(3), Math.round(view.x), Math.round(view.y)].join(',');
			var finish = function (mask) {
				if (job !== busy) { return; }
				if (st !== 'photo') { cut = mask; cutKey = key; }
				var o = compose(c, mask, st);
				out.getContext('2d').drawImage(o, 0, 0, out.width, out.height);
				data.value = o.toDataURL('image/jpeg', 0.88);
				screenPreview(o);
				wait.hidden = true; out.parentNode.classList.remove('is-busy');
			};
			if (st === 'photo') { finish(null); return; }
			if (cut && cutKey === key) { finish(cut); return; }
			wait.hidden = false; out.parentNode.classList.add('is-busy');
			cutout(c).then(finish, function () { finish(ovalMask()); });
		}

		/* how the picture looks on the show's screen (its real number of pixels) */
		function screenPreview(o) {
			if (!scr) { return; }
			var m = (box.getAttribute('data-screen') || '').split('x'), W = parseInt(m[0], 10) || 0, H = parseInt(m[1], 10) || 0;
			if (!W || !H) { scrBox.hidden = true; return; }
			var f = Math.min(1, 240 / Math.max(W, H));            // TVs and projectors: drawn smaller, same shape
			var w = Math.max(1, Math.round(W * f)), h = Math.max(1, Math.round(H * f));
			scr.width = w; scr.height = h;
			var x = scr.getContext('2d'); x.fillStyle = '#000'; x.fillRect(0, 0, w, h);
			var k = Math.min(w / o.width, h / o.height), dw = o.width * k, dh = o.height * k;
			x.imageSmoothingEnabled = true; x.imageSmoothingQuality = 'high';
			x.drawImage(o, (w - dw) / 2, (h - dh) / 2, dw, dh);
			scr.style.aspectRatio = W + ' / ' + H;
			scrBox.hidden = false;
		}

		var clear = function () {
			img = null; cut = null; data.value = ''; file.value = ''; edit.hidden = true; clr.hidden = true; okWrap.hidden = true; ok.checked = false;
			take.textContent = t('Take or pick a picture');
		};
		file.addEventListener('change', function () {
			var f = file.files && file.files[0];
			if (!f || !/^image\//.test(f.type)) { return; }
			var im = new Image(), url = URL.createObjectURL(f);
			im.onload = function () {
				img = im; cut = null;
				view.s = cover(); view.x = (S - im.naturalWidth * view.s) / 2; view.y = (S - im.naturalHeight * view.s) / 2;
				zoom.value = '1'; paint();
				edit.hidden = false; clr.hidden = false; okWrap.hidden = false;
				take.textContent = t('Use a different picture');
				render();
				if (mode !== 'photo') { cutout.load(); }       // start fetching the cut-out tool right away
			};
			im.onerror = function () { URL.revokeObjectURL(url); };
			im.src = url;
		});
		clr.addEventListener('click', clear);
		return { ready: function () { return !img || ok.checked; }, clear: clear };
	}

	/* ---- cutting the kid out of the background, on the phone ----
	   MediaPipe Selfie Segmentation (Google, free): its code is downloaded from the jsDelivr library the
	   first time; the picture is only ever handed to it inside the phone. If it can't load, an oval around
	   the face is used instead. */
	var SEG = 'https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation@0.1.1675465747/';
	var segP = null;
	function cutout(c) {
		return cutout.load().then(function (seg) {
			return new Promise(function (ok, no) {
				var tm = setTimeout(no, 15000);
				seg.onResults(function (r) {
					clearTimeout(tm);
					var m = document.createElement('canvas'); m.width = m.height = PIC_CROP;
					var x = m.getContext('2d'); x.drawImage(r.segmentationMask, 0, 0, PIC_CROP, PIC_CROP);
					var d = x.getImageData(0, 0, PIC_CROP, PIC_CROP).data, a = new Float32Array(PIC_CROP * PIC_CROP);
					for (var i = 0; i < a.length; i++) {
						var v = Math.max(d[i * 4], d[i * 4 + 3] < 255 ? d[i * 4 + 3] : 0) / 255;
						a[i] = v < 0.3 ? 0 : v > 0.7 ? 1 : (v - 0.3) / 0.4;
					}
					ok(soften(a, PIC_CROP, 2));
				});
				seg.send({ image: c }).catch(no);
			});
		});
	}
	cutout.load = function () {
		if (segP) { return segP; }
		segP = new Promise(function (ok, no) {
			var go = function () {
				try {
					var seg = new window.SelfieSegmentation({ locateFile: function (f) { return SEG + f; } });
					seg.setOptions({ modelSelection: 0 });
					seg.initialize().then(function () { ok(seg); }, no);
				} catch (e) { no(e); }
			};
			if (window.SelfieSegmentation) { go(); return; }
			var s = document.createElement('script'); s.src = SEG + 'selfie_segmentation.js'; s.crossOrigin = 'anonymous';
			s.onload = go; s.onerror = no; document.head.appendChild(s);
			setTimeout(no, 20000);
		});
		segP.catch(function () { segP = null; });
		return segP;
	};
	/* no cut-out tool: a soft oval around the face circle */
	function ovalMask() {
		var n = PIC_CROP, a = new Float32Array(n * n), cx = RING.x * n, cy = RING.y * n + RING.r * n * 0.25, rx = RING.r * n * 1.25, ry = RING.r * n * 1.6;
		for (var y = 0; y < n; y++) { for (var x = 0; x < n; x++) { var d = Math.hypot((x - cx) / rx, (y - cy) / ry); a[y * n + x] = d < 0.92 ? 1 : d > 1 ? 0 : (1 - d) / 0.08; } }
		return a;
	}
	function soften(a, n, r) {
		var t = new Float32Array(n * n), o = new Float32Array(n * n), x, y, j, s;
		for (y = 0; y < n; y++) { for (x = 0; x < n; x++) { s = 0; for (j = -r; j <= r; j++) { s += a[y * n + Math.min(n - 1, Math.max(0, x + j))]; } t[y * n + x] = s / (2 * r + 1); } }
		for (y = 0; y < n; y++) { for (x = 0; x < n; x++) { s = 0; for (j = -r; j <= r; j++) { s += t[Math.min(n - 1, Math.max(0, y + j)) * n + x]; } o[y * n + x] = s / (2 * r + 1); } }
		return o;
	}

	/* ---- the finished picture ---- */
	function compose(c, mask, st) {
		var N = PIC_OUT, o = document.createElement('canvas'); o.width = o.height = N;
		var x = o.getContext('2d');
		if (st === 'photo') { x.drawImage(c, 0, 0, N, N); return o; }
		// the cartoon of the framed square, at the finished size
		var sm = document.createElement('canvas'); sm.width = sm.height = N;
		var sx = sm.getContext('2d'); sx.drawImage(c, 0, 0, N, N);
		var toon = cartoon(sx.getImageData(0, 0, N, N));
		var head = st !== 'cartoon';
		// the kid's layer: the cartoon where the mask says "kid" (just the head for the costumes)
		var al = new Float32Array(N * N), k = PIC_CROP / N, i, px, py;
		for (py = 0; py < N; py++) {
			for (px = 0; px < N; px++) {
				var m = mask[Math.min(PIC_CROP - 1, Math.round(py * k)) * PIC_CROP + Math.min(PIC_CROP - 1, Math.round(px * k))];
				if (head) {                                 // head and a bit of neck: the costume covers the rest
					var dx = (px / N - RING.x) / (RING.r * 1.3), dy = (py / N - RING.y) / (RING.r * (py / N < RING.y ? 1.6 : 1.02));
					var d = Math.hypot(dx, dy); m *= d < 0.9 ? 1 : d > 1 ? 0 : (1 - d) / 0.1;
				}
				al[py * N + px] = m;
			}
		}
		var kid = document.createElement('canvas'); kid.width = kid.height = N;
		var kd = toon.data;
		for (i = 0; i < N * N; i++) { kd[i * 4 + 3] = Math.round(al[i] * 255); }
		kid.getContext('2d').putImageData(toon, 0, 0);
		var skin = skinTone(toon, al, N);
		background(x, N, st);
		if (st === 'cartoon') {
			x.drawImage(sticker(al, N, 7), 0, 0);            // white sticker edge round the kid
			x.drawImage(kid, 0, 0);
			return o;
		}
		// costumes: the head goes in a set place, smaller, with the body drawn under it
		var R = 0.26, T = { x: 0.5, y: 0.5 }, f = R / RING.r;
		var place = function (img) { x.drawImage(img, (T.x - RING.x * f) * N, (T.y - RING.y * f) * N, N * f, N * f); };
		var g = { x: T.x * N, y: T.y * N, r: R * N, N: N, skin: skin };
		if (st === 'elf') { elfEars(x, g); }
		place(sticker(al, N, 5));
		place(kid);
		body(x, g, st);                                  // the collar goes over the neck
		hat(x, g, st);
		return o;
	}

	/* the average colour of the middle of the face (for the elf's ears) */
	function skinTone(img, al, N) {
		var r = 0, g = 0, b = 0, n = 0, d = img.data;
		for (var y = Math.round((RING.y - RING.r * 0.15) * N); y < (RING.y + RING.r * 0.35) * N; y++) {
			for (var x = Math.round((RING.x - RING.r * 0.35) * N); x < (RING.x + RING.r * 0.35) * N; x++) {
				var i = y * N + x; if (al[i] < 0.8) { continue; }
				r += d[i * 4]; g += d[i * 4 + 1]; b += d[i * 4 + 2]; n++;
			}
		}
		return n ? 'rgb(' + Math.round(r / n) + ',' + Math.round(g / n) + ',' + Math.round(b / n) + ')' : '#f1c7a5';
	}

	/* a white edge round the cut-out, like a sticker */
	function sticker(al, N, r) {
		var c = document.createElement('canvas'); c.width = c.height = N;
		var x = c.getContext('2d'), im = x.createImageData(N, N), d = im.data, i;
		var grow = new Float32Array(N * N), xx, yy, j;
		var tmp = new Float32Array(N * N);
		for (yy = 0; yy < N; yy++) { for (xx = 0; xx < N; xx++) { var m = 0; for (j = -r; j <= r; j++) { var v = al[yy * N + Math.min(N - 1, Math.max(0, xx + j))]; if (v > m) { m = v; } } tmp[yy * N + xx] = m; } }
		for (yy = 0; yy < N; yy++) { for (xx = 0; xx < N; xx++) { var m2 = 0; for (j = -r; j <= r; j++) { var v2 = tmp[Math.min(N - 1, Math.max(0, yy + j)) * N + xx]; if (v2 > m2) { m2 = v2; } } grow[yy * N + xx] = m2; } }
		for (i = 0; i < N * N; i++) { d[i * 4] = d[i * 4 + 1] = d[i * 4 + 2] = 255; d[i * 4 + 3] = Math.round(Math.min(1, grow[i] * 1.4) * 255); }
		x.putImageData(im, 0, 0);
		return c;
	}

	/* the festive scene behind the kid */
	function background(x, N, st) {
		var col = { cartoon: ['#d1203d', '#5a0718'], elf: ['#2e9a52', '#0a3a1c'], snowman: ['#3d74c9', '#0d2150'], helper: ['#c8102e', '#4a0510'] }[st];
		var g = x.createRadialGradient(N / 2, N * 0.42, N * 0.05, N / 2, N / 2, N * 0.75);
		g.addColorStop(0, col[0]); g.addColorStop(1, col[1]);
		x.fillStyle = g; x.fillRect(0, 0, N, N);
		var seed = 7, rnd = function () { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
		// soft lights
		for (var i = 0; i < 14; i++) {
			var bx = rnd() * N, by = rnd() * N * 0.8, br = N * (0.02 + rnd() * 0.04);
			var lg = x.createRadialGradient(bx, by, 0, bx, by, br);
			lg.addColorStop(0, st === 'snowman' ? 'rgba(255,255,255,.55)' : 'rgba(255,220,130,.6)'); lg.addColorStop(1, 'rgba(255,220,130,0)');
			x.fillStyle = lg; x.beginPath(); x.arc(bx, by, br, 0, Math.PI * 2); x.fill();
		}
		// snowflakes / sparkles
		x.strokeStyle = 'rgba(255,255,255,.85)'; x.lineWidth = N / 220;
		for (i = 0; i < 18; i++) {
			var sx = rnd() * N, sy = rnd() * N, ss = N * (0.008 + rnd() * 0.014);
			for (var a = 0; a < 3; a++) { var an = a * Math.PI / 3; x.beginPath(); x.moveTo(sx - Math.cos(an) * ss, sy - Math.sin(an) * ss); x.lineTo(sx + Math.cos(an) * ss, sy + Math.sin(an) * ss); x.stroke(); }
		}
		if (st === 'snowman') { // snowy ground
			x.fillStyle = '#eef6ff'; x.beginPath(); x.moveTo(0, N * 0.86); x.quadraticCurveTo(N * 0.3, N * 0.8, N * 0.6, N * 0.86); x.quadraticCurveTo(N * 0.85, N * 0.9, N, N * 0.84); x.lineTo(N, N); x.lineTo(0, N); x.fill();
		}
	}

	/* ---- costumes, drawn in code: g = head centre x, y and radius ---- */
	function line(x, w, c) { x.lineWidth = w; x.strokeStyle = c || '#1b1020'; x.lineJoin = 'round'; x.stroke(); }
	function elfEars(x, g) {
		[-1, 1].forEach(function (s) {
			x.beginPath();
			x.moveTo(g.x + s * g.r * 0.62, g.y - g.r * 0.1);
			x.quadraticCurveTo(g.x + s * g.r * 1.1, g.y - g.r * 0.28, g.x + s * g.r * 1.38, g.y - g.r * 0.6);
			x.quadraticCurveTo(g.x + s * g.r * 1.18, g.y + g.r * 0.1, g.x + s * g.r * 0.66, g.y + g.r * 0.26);
			x.closePath(); x.fillStyle = g.skin; x.fill(); line(x, g.N / 110);
		});
	}
	function body(x, g, st) {
		var N = g.N, top = g.y + g.r * 0.9;
		if (st === 'snowman') {
			x.beginPath(); x.arc(g.x, N * 1.2, N * 0.46, 0, Math.PI * 2); x.fillStyle = '#fbfdff'; x.fill(); line(x, N / 110, '#9fb5d6');
			[0.86, 0.95].forEach(function (yy) { x.beginPath(); x.arc(g.x, N * yy, N * 0.022, 0, Math.PI * 2); x.fillStyle = '#1b1b24'; x.fill(); });
			// scarf
			x.fillStyle = '#d4202f'; x.beginPath(); x.ellipse(g.x, top + g.r * 0.12, g.r * 0.82, g.r * 0.2, 0, 0, Math.PI * 2); x.fill(); line(x, N / 140);
			x.beginPath(); x.moveTo(g.x + g.r * 0.35, top + g.r * 0.1); x.lineTo(g.x + g.r * 0.7, top + g.r * 0.12); x.lineTo(g.x + g.r * 0.62, top + g.r * 1.15); x.lineTo(g.x + g.r * 0.3, top + g.r * 1.1); x.closePath(); x.fill(); line(x, N / 140);
			x.fillStyle = '#2f9a4f'; [0.35, 0.7].forEach(function (k) { x.fillRect(g.x + g.r * 0.32, top + g.r * (0.1 + k), g.r * 0.36, g.r * 0.12); });
			return;
		}
		var main = st === 'elf' ? '#2a9a46' : '#d1162d', dark = st === 'elf' ? '#1b6b30' : '#8f0d1e';
		// shoulders and tunic
		x.beginPath(); x.moveTo(g.x - g.r * 0.45, top - g.r * 0.1);
		x.quadraticCurveTo(g.x - g.r * 1.9, top + g.r * 0.05, g.x - g.r * 2.15, N + 2); x.lineTo(g.x + g.r * 2.15, N + 2);
		x.quadraticCurveTo(g.x + g.r * 1.9, top + g.r * 0.05, g.x + g.r * 0.45, top - g.r * 0.1); x.closePath();
		var bg = x.createLinearGradient(0, top, 0, N); bg.addColorStop(0, main); bg.addColorStop(1, dark); x.fillStyle = bg; x.fill(); line(x, N / 110);
		if (st === 'elf') {
			// pointed collar, red and white
			for (var i = -3; i <= 3; i++) {
				var cx = g.x + i * g.r * 0.36;
				x.beginPath(); x.moveTo(cx - g.r * 0.2, top - g.r * 0.05); x.lineTo(cx + g.r * 0.2, top - g.r * 0.05); x.lineTo(cx, top + g.r * (0.55 - Math.abs(i) * 0.05)); x.closePath();
				x.fillStyle = i % 2 ? '#fff' : '#d4202f'; x.fill(); line(x, N / 160);
			}
			// belt
			x.fillStyle = '#2a1408'; x.fillRect(0, N * 0.93, N, N * 0.07);
			x.strokeStyle = '#f4c542'; x.lineWidth = N / 70; x.strokeRect(g.x - N * 0.05, N * 0.925, N * 0.1, N * 0.08);
		} else {
			// white fur collar and front, black belt with a gold buckle
			x.fillStyle = '#fff';
			x.beginPath(); x.ellipse(g.x, top + g.r * 0.2, g.r * 1.0, g.r * 0.22, 0, 0, Math.PI * 2); x.fill(); line(x, N / 160, '#c9ced8');
			x.fillRect(g.x - g.r * 0.16, top + g.r * 0.38, g.r * 0.32, N);
			x.fillStyle = '#16141a'; x.fillRect(0, N * 0.93, N, N * 0.07);
			x.strokeStyle = '#f4c542'; x.lineWidth = N / 70; x.strokeRect(g.x - N * 0.055, N * 0.925, N * 0.11, N * 0.08);
			x.fillStyle = '#16141a'; [0.84].forEach(function (yy) { x.beginPath(); x.arc(g.x, N * yy, N * 0.014, 0, Math.PI * 2); x.fill(); });
		}
	}
	function hat(x, g, st) {
		var N = g.N, top = g.y - g.r * 0.82;
		if (st === 'snowman') { // black top hat with a red band
			x.fillStyle = '#18161d';
			x.beginPath(); x.ellipse(g.x, top + g.r * 0.08, g.r * 1.15, g.r * 0.2, -0.08, 0, Math.PI * 2); x.fill(); line(x, N / 160, '#000');
			x.beginPath(); x.moveTo(g.x - g.r * 0.72, top + g.r * 0.05); x.lineTo(g.x - g.r * 0.62, top - g.r * 1.05); x.lineTo(g.x + g.r * 0.62, top - g.r * 1.12); x.lineTo(g.x + g.r * 0.72, top + g.r * 0.0); x.closePath(); x.fill();
			x.fillStyle = '#d4202f'; x.beginPath(); x.moveTo(g.x - g.r * 0.71, top - g.r * 0.12); x.lineTo(g.x + g.r * 0.71, top - g.r * 0.17); x.lineTo(g.x + g.r * 0.7, top - g.r * 0.36); x.lineTo(g.x - g.r * 0.69, top - g.r * 0.31); x.closePath(); x.fill();
			x.fillStyle = '#2f9a4f'; x.beginPath(); x.arc(g.x + g.r * 0.45, top - g.r * 0.26, g.r * 0.12, 0, Math.PI * 2); x.fill();
			return;
		}
		var main = st === 'elf' ? '#2a9a46' : '#d1162d', dark = st === 'elf' ? '#1b6b30' : '#8f0d1e';
		// the floppy cone, flopping to the right
		x.beginPath(); x.moveTo(g.x - g.r * 1.02, top + g.r * 0.05);
		x.quadraticCurveTo(g.x - g.r * 0.6, top - g.r * 1.55, g.x + g.r * 0.55, top - g.r * 1.45);
		x.quadraticCurveTo(g.x + g.r * 1.35, top - g.r * 1.3, g.x + g.r * 1.6, top - g.r * 0.35);
		x.quadraticCurveTo(g.x + g.r * 1.1, top - g.r * 0.9, g.x + g.r * 1.02, top + g.r * 0.05); x.closePath();
		var hg = x.createLinearGradient(g.x - g.r, top - g.r * 1.5, g.x + g.r, top); hg.addColorStop(0, main); hg.addColorStop(1, dark); x.fillStyle = hg; x.fill(); line(x, N / 110);
		// pompom
		x.beginPath(); x.arc(g.x + g.r * 1.6, top - g.r * 0.28, g.r * 0.24, 0, Math.PI * 2); x.fillStyle = '#fff'; x.fill(); line(x, N / 160, '#c9ced8');
		// brim: white fur for the helper, red and white stripes for the elf
		x.beginPath(); x.moveTo(g.x - g.r * 1.12, top + g.r * 0.22); x.quadraticCurveTo(g.x, top - g.r * 0.2, g.x + g.r * 1.12, top + g.r * 0.22);
		x.lineTo(g.x + g.r * 1.08, top - g.r * 0.12); x.quadraticCurveTo(g.x, top - g.r * 0.55, g.x - g.r * 1.08, top - g.r * 0.12); x.closePath();
		if (st === 'elf') {
			x.save(); x.clip(); for (var i = -6; i < 7; i++) { x.fillStyle = i % 2 ? '#fff' : '#d4202f'; x.beginPath(); x.moveTo(g.x + i * g.r * 0.25, top - g.r); x.lineTo(g.x + (i + 1) * g.r * 0.25, top - g.r); x.lineTo(g.x + (i + 0.4) * g.r * 0.25, top + g.r); x.lineTo(g.x + (i - 0.6) * g.r * 0.25, top + g.r); x.closePath(); x.fill(); } x.restore();
		} else { x.fillStyle = '#fff'; x.fill(); }
		x.beginPath(); x.moveTo(g.x - g.r * 1.12, top + g.r * 0.22); x.quadraticCurveTo(g.x, top - g.r * 0.2, g.x + g.r * 1.12, top + g.r * 0.22);
		x.lineTo(g.x + g.r * 1.08, top - g.r * 0.12); x.quadraticCurveTo(g.x, top - g.r * 0.55, g.x - g.r * 1.08, top - g.r * 0.12); x.closePath(); line(x, N / 140);
	}

	/* A cartoon from a photo, made on the phone: flat areas of colour (a Kuwahara filter, twice),
	   fewer, brighter colours, and bold dark outlines where the picture changes (difference of two blurs). */
	function cartoon(img) {
		var w = img.width, h = img.height, n = w * h, s = img.data, i, x, y, c;
		var rgb = new Float32Array(n * 3);
		for (i = 0; i < n; i++) { rgb[i * 3] = s[i * 4]; rgb[i * 3 + 1] = s[i * 4 + 1]; rgb[i * 3 + 2] = s[i * 4 + 2]; }
		var gray = function (a) { var g = new Float32Array(n); for (var j = 0; j < n; j++) { g[j] = 0.3 * a[j * 3] + 0.59 * a[j * 3 + 1] + 0.11 * a[j * 3 + 2]; } return g; };
		var kuwahara = function (a, r) {
			var W = w + 1, ia = new Float64Array(W * (h + 1) * 3), ig = new Float64Array(W * (h + 1)), ig2 = new Float64Array(W * (h + 1)), g = gray(a), out = new Float32Array(n * 3);
			for (y = 0; y < h; y++) {
				for (x = 0; x < w; x++) {
					var p = (y + 1) * W + x + 1, q = y * w + x;
					ig[p] = g[q] + ig[p - 1] + ig[p - W] - ig[p - W - 1];
					ig2[p] = g[q] * g[q] + ig2[p - 1] + ig2[p - W] - ig2[p - W - 1];
					for (c = 0; c < 3; c++) { ia[p * 3 + c] = a[q * 3 + c] + ia[(p - 1) * 3 + c] + ia[(p - W) * 3 + c] - ia[(p - W - 1) * 3 + c]; }
				}
			}
			var sum = function (I, y0, x0, y1, x1, k, cc) { return I[(y1 * W + x1) * k + cc] - I[(y0 * W + x1) * k + cc] - I[(y1 * W + x0) * k + cc] + I[(y0 * W + x0) * k + cc]; };
			var quads = [[-r, -r], [-r, 0], [0, -r], [0, 0]];
			for (y = 0; y < h; y++) {
				for (x = 0; x < w; x++) {
					var best = -1, bv = Infinity;
					for (var k = 0; k < 4; k++) {
						var y0 = Math.max(0, y + quads[k][0]), x0 = Math.max(0, x + quads[k][1]), y1 = Math.min(h, y + quads[k][0] + r + 1), x1 = Math.min(w, x + quads[k][1] + r + 1);
						var cnt = (y1 - y0) * (x1 - x0), mg = sum(ig, y0, x0, y1, x1, 1, 0) / cnt, v = sum(ig2, y0, x0, y1, x1, 1, 0) / cnt - mg * mg;
						if (v < bv) { bv = v; best = [y0, x0, y1, x1, cnt]; }
					}
					for (c = 0; c < 3; c++) { out[(y * w + x) * 3 + c] = sum(ia, best[0], best[1], best[2], best[3], 3, c) / best[4]; }
				}
			}
			return out;
		};
		var blur = function (g, sig) {
			var r = Math.round(sig * 3), k = [], tot = 0, j, t = new Float32Array(n), o = new Float32Array(n);
			for (j = -r; j <= r; j++) { k.push(Math.exp(-j * j / (2 * sig * sig))); tot += k[k.length - 1]; }
			for (j = 0; j < k.length; j++) { k[j] /= tot; }
			for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { var v = 0; for (j = -r; j <= r; j++) { v += k[j + r] * g[y * w + Math.min(w - 1, Math.max(0, x + j))]; } t[y * w + x] = v; } }
			for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { var u = 0; for (j = -r; j <= r; j++) { u += k[j + r] * t[Math.min(h - 1, Math.max(0, y + j)) * w + x]; } o[y * w + x] = u; } }
			return o;
		};
		var sc = w / 256;                                     // the same look at any size
		var flat = kuwahara(kuwahara(rgb, Math.round(4 * sc)), Math.round(3 * sc));
		var g0 = gray(rgb), b1 = blur(g0, 1.1 * sc), b2 = blur(g0, 2 * sc);
		var e = new Uint8Array(n), out = new ImageData(w, h), o = out.data, step = 255 / 7;
		for (i = 0; i < n; i++) { e[i] = b1[i] - b2[i] < -6 ? 1 : 0; }
		for (y = 1; y < h - 1; y++) { for (x = 1; x < w - 1; x++) { i = y * w + x; if (e[i] && e[i - 1] + e[i + 1] + e[i - w] + e[i + w] + e[i - w - 1] + e[i - w + 1] + e[i + w - 1] + e[i + w + 1] < 2) { e[i] = 0; } } } // no specks
		for (i = 0; i < n; i++) {
			x = i % w; y = (i - x) / w;
			var edge = e[i] || (x > 0 && e[i - 1]) || (y > 0 && e[i - w]);   // a little thicker
			var r0 = flat[i * 3], g1 = flat[i * 3 + 1], bl = flat[i * 3 + 2], m = (r0 + g1 + bl) / 3;
			var col = [m + (r0 - m) * 1.35 + 12, m + (g1 - m) * 1.35 + 12, m + (bl - m) * 1.35 + 12];
			for (c = 0; c < 3; c++) { o[i * 4 + c] = edge ? [30, 18, 28][c] : Math.max(0, Math.min(255, Math.round(col[c] / step) * step)); }
			o[i * 4 + 3] = 255;
		}
		return out;
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
