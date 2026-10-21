/* Marquee setup wizard / settings page */
(function () {
	'use strict';
	var root = document.querySelector('[data-mq]');
	if (!root) { return; }
	var C = window.MarqueeAdmin || {};
	var $ = function (s) { return root.querySelector(s); };
	var panes = root.querySelectorAll('[data-pane]'), steps = root.querySelectorAll('[data-step]');
	var wizard = root.classList.contains('mq--wizard'), cur = 0;
	root.classList.add('is-js');

	function go(i) {
		cur = Math.max(0, Math.min(panes.length - 1, i));
		panes.forEach(function (p, k) { p.classList.toggle('is-on', k === cur); });
		steps.forEach(function (b, k) {
			if (k === cur) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
			b.classList.toggle('is-done', wizard && k < cur);
		});
		root.classList.toggle('is-first', cur === 0);
		root.classList.toggle('is-last', cur === panes.length - 1);
		if (steps[cur] && window.innerWidth <= 900 && steps[cur].parentNode.parentNode.scrollWidth > steps[cur].parentNode.parentNode.clientWidth) {
			var bar = steps[cur].parentNode.parentNode; bar.scrollLeft = steps[cur].offsetLeft - bar.clientWidth / 2 + steps[cur].offsetWidth / 2;
		}
	}
	var tabIn = root.querySelector('[data-mq-tab]');
	steps.forEach(function (b) {
		b.addEventListener('click', function () {
			var i = +b.getAttribute('data-step');
			go(i);
			if (!wizard) {
				if (tabIn) { tabIn.value = i; }
				try { history.replaceState(null, '', '#mq-step-' + i); } catch (e) {}
			}
			var top = root.querySelector('.mq__wiz').getBoundingClientRect().top + window.pageYOffset - 40;
			if (window.pageYOffset > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
		});
	});
	$('[data-mq-back]').addEventListener('click', function () { go(cur - 1); window.scrollTo(0, 0); });
	$('[data-mq-next]').addEventListener('click', function () { go(cur + 1); window.scrollTo(0, 0); });
	go(0);
	/* links like #mq-step-3 (from the setup checklist) open that step */
	var hm = /^#mq-step-(\d)$/.exec(location.hash);
	if (hm && panes[+hm[1]]) { go(+hm[1]); if (tabIn) { tabIn.value = +hm[1]; } }

	function post(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', C.nonce);
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function say(el, msg, ok) { el.textContent = msg; el.className = 'mq__msg ' + (ok ? 'is-ok' : 'is-bad'); }

	/* address → pin, time zone, radar */
	var lk = $('[data-mq-lookup]'), lkMsg = $('[data-mq-lookup-msg]');
	lk.addEventListener('click', function () {
		lk.disabled = true; say(lkMsg, 'Looking it up…', true);
		post('marquee_lookup', { address: $('#mq-addr').value }).then(function (j) {
			lk.disabled = false;
			if (!j || !j.success) { say(lkMsg, (j && j.data && j.data.message) || 'Couldn’t look that up.', false); return; }
			var d = j.data;
			$('#mq-lat').value = (+d.lat).toFixed(5); $('#mq-lon').value = (+d.lon).toFixed(5);
			if (d.tz) { var o = $('#mq-tz').querySelector('option[value="' + d.tz + '"]'); if (o) { $('#mq-tz').value = d.tz; } }
			if (d.radar) { $('#mq-radar').value = d.radar; } else if (d.country && d.country !== 'US') { $('#mq-radar').value = ''; }
			if ($('#mq-office')) { $('#mq-office').value = d.office || ''; }
			if (d.city) { $('#mq-city').value = d.city; }
			if (d.region) { $('#mq-region').value = d.region; }
			if (d.zip) { $('#mq-zip').value = d.zip; }
			if (d.country) { $('[data-mq-country]').value = d.country; $('#mq-units').value = d.country === 'US' ? 'us' : 'metric'; }
			say(lkMsg, 'Found it: ' + [d.city, d.region].filter(Boolean).join(', ') + (d.tz ? ' · ' + d.tz : '') + (d.radar ? ' · radar ' + d.radar : '') + '. Check the fields below.', true);
		}).catch(function () { lk.disabled = false; say(lkMsg, 'Couldn’t reach the website. Try again.', false); });
	});

	/* "Only at the show": switching miles/km converts the number in the box */
	var geoUnit = $('[data-mq-geo-unit]'), geoDist = $('[data-mq-geo-dist]');
	if (geoUnit && geoDist) {
		geoUnit.addEventListener('change', function () {
			var v = parseFloat(String(geoDist.value).replace(',', '.'));
			if (!(v > 0)) { return; }
			geoDist.value = Math.round((geoUnit.value === 'km' ? v * 1.609344 : v / 1.609344) * 10) / 10;
		});
	}

	/* Logo: show the picked file right away, before saving */
	var logoFile = $('[data-mq-logo-file]');
	if (logoFile) {
		logoFile.addEventListener('change', function () {
			var f = logoFile.files && logoFile.files[0], box = $('[data-mq-logo-box]'), img = $('[data-mq-logo-img]'), note = $('[data-mq-logo-note]');
			if (!f || !box || !img) { return; }
			img.src = URL.createObjectURL(f);
			box.hidden = false;
			if (note) { note.textContent = 'New logo picked. Click Save settings to use it.'; }
		});
	}

	/* Remote Falcon test */
	var rt = $('[data-mq-rftest]'), rtMsg = $('[data-mq-rfmsg]');
	rt.addEventListener('click', function () {
		rt.disabled = true; say(rtMsg, 'Testing…', true);
		post('marquee_rf_test', { token: $('#mq-rft').value.trim(), secret: $('#mq-rfs').value.trim() }).then(function (j) {
			rt.disabled = false;
			say(rtMsg, (j && j.data && j.data.message) || 'Test failed.', !!(j && j.success));
		}).catch(function () { rt.disabled = false; say(rtMsg, 'Couldn’t reach the website. Try again.', false); });
	});

	/* standard coloring pages: Remove / Restore */
	var stdList = $('[data-mq-std-list]'), stdGone = $('[data-mq-std-gonelist]');
	if (stdList && stdGone) {
		root.addEventListener('click', function (e) {
			var x = e.target.closest('[data-mq-std-x]');
			if (!x) { return; }
			var card = x.closest('[data-mq-std]'), box = card.querySelector('input[type=checkbox]'), flag = card.querySelector('input[name="art_removed[]"]');
			var gone = card.parentNode === stdGone;
			if (gone) {
				stdList.appendChild(card); box.disabled = false; box.checked = true; flag.disabled = true; x.textContent = 'Remove';
			} else {
				stdGone.appendChild(card); box.checked = false; box.disabled = true; flag.disabled = false; x.textContent = 'Restore';
			}
			$('[data-mq-std-gone]').hidden = !stdGone.children.length;
		});
	}

	/* themed sequences: show one more empty row at a time */
	var thMore = $('[data-mq-theme-more]');
	if (thMore) {
		var thLeft = function () { return root.querySelectorAll('[data-mq-theme].is-empty'); };
		thMore.hidden = !thLeft().length;
		thMore.addEventListener('click', function () {
			var e = thLeft()[0]; if (e) { e.classList.remove('is-empty'); var f = e.querySelector('input[type=text]'); if (f) { f.focus(); } }
			thMore.hidden = !thLeft().length;
		});
	}

	/* spoken names (ElevenLabs) */
	var vLoad = $('[data-mq-vload]'), vTest = $('[data-mq-vtest]');
	if (vLoad && vTest) {
		var vPick = $('[data-mq-vpick]'), vId = $('#mq-vid'), vName = $('#mq-vname'), vAudio = $('[data-mq-vaudio]');
		var vKey = function () { return $('#mq-vkey').value.trim(); };
		var keyBad = function (msgEl) {
			var k = vKey();
			if (k && k !== '••••••••' && k.indexOf('sk_') !== 0) {
				say(msgEl, 'That’s the key ID, not the key. The API key starts with sk_ and ElevenLabs shows it only once, when you create the key.', false);
				return true;
			}
			return false;
		};
		vPick.addEventListener('change', function () {
			vId.value = vPick.value;
			vName.value = vPick.value ? vPick.options[vPick.selectedIndex].text : '';
		});
		vLoad.addEventListener('click', function () {
			var lm = $('[data-mq-vlmsg]');
			if (keyBad(lm)) { return; }
			say(lm, 'Loading…', true);
			post('marquee_voice_list', { key: vKey() }).then(function (j) {
				if (!j || !j.success) { say(lm, (j && j.data && j.data.message) || 'Couldn’t load your voices.', false); return; }
				var cur = vId.value, list = j.data.voices || [];
				vPick.innerHTML = '';
				var g1 = document.createElement('optgroup'), g2 = document.createElement('optgroup');
				g1.label = 'My voices'; g2.label = 'ElevenLabs voices';
				list.forEach(function (v) {
					var o = document.createElement('option'); o.value = v.id; o.textContent = v.name;
					if (v.id === cur) { o.selected = true; }
					(v.mine ? g1 : g2).appendChild(o);
				});
				if (g1.children.length) { vPick.appendChild(g1); }
				if (g2.children.length) { vPick.appendChild(g2); }
				if (!cur || !list.some(function (v) { return v.id === cur; })) { vPick.selectedIndex = 0; }
				vPick.dispatchEvent(new Event('change'));
				say(lm, list.length + ' voices. Pick one, then Test voice.', true);
			}).catch(function () { say(lm, 'Couldn’t reach the website. Try again.', false); });
		});
		var loaded = false;
		var autoLoad = function () { if (!loaded && vKey()) { loaded = true; vLoad.click(); } };
		vPick.addEventListener('focus', autoLoad);
		vPick.addEventListener('mousedown', autoLoad);
		$('#mq-vkey').addEventListener('change', function () { loaded = false; if (vKey().indexOf('sk_') === 0) { autoLoad(); } });
		vTest.addEventListener('click', function () {
			var tm = $('[data-mq-vmsg]');
			if (keyBad(tm)) { return; }
			vTest.disabled = true; say(tm, 'Making the clip…', true);
			post('marquee_voice_test', { key: vKey(), voice: vId.value, model: $('#mq-vmodel').value, name: $('#mq-vtest').value, speed: $('#mq-vspeed').value, santa: $('#mq-vsanta').checked ? 1 : 0, greet: $('#mq-vsanta').checked ? 'santa' : 'merry' }).then(function (j) {
				vTest.disabled = false;
				if (!j || !j.success) { say(tm, (j && j.data && j.data.message) || 'Test failed.', false); return; }
				say(tm, j.data.message, true);
				vAudio.hidden = false; vAudio.src = j.data.url; vAudio.play().catch(function () {});
			}).catch(function () { vTest.disabled = false; say(tm, 'Couldn’t reach the website. Try again.', false); });
		});
	}

	/* picture pickers (Media Library) */
	root.querySelectorAll('[data-mq-media]').forEach(function (box) {
		var multi = box.getAttribute('data-multi') === '1', input = box.querySelector('[data-mq-ids]');
		var thumbs = box.querySelector('[data-mq-thumbs]'), pick = box.querySelector('[data-mq-pick]'), clear = box.querySelector('[data-mq-clear]');
		var std = thumbs.getAttribute('data-std'), frame = null, kind = box.getAttribute('data-type') || 'image';
		function ids() { return input.value ? input.value.split(',').filter(Boolean) : []; }
		function thumb(id, url, title) {
			var s = document.createElement('span'); s.className = 'mq__thumb' + (kind === 'audio' ? ' is-audio' : ''); s.setAttribute('data-id', id);
			if (kind === 'audio') { var t = document.createElement('span'); t.className = 'mq__song'; t.textContent = '♪ ' + (title || 'Song'); s.appendChild(t); }
			else { var i = document.createElement('img'); i.src = url; i.alt = ''; s.appendChild(i); }
			if (multi) { var x = document.createElement('button'); x.type = 'button'; x.className = 'mq__x'; x.setAttribute('data-mq-x', ''); x.setAttribute('aria-label', 'Remove'); x.textContent = '×'; s.appendChild(x); }
			return s;
		}
		function showStd() {
			if (!std || ids().length) { return; }
			thumbs.innerHTML = '<span class="mq__thumb is-std"><img alt=""><em>Standard</em></span>';
			thumbs.querySelector('img').src = std;
		}
		thumbs.addEventListener('click', function (e) {
			var x = e.target.closest('[data-mq-x]'); if (!x) { return; }
			var t = x.closest('.mq__thumb'), id = t.getAttribute('data-id');
			input.value = ids().filter(function (v) { return v !== id; }).join(','); t.remove();
		});
		if (clear) {
			clear.addEventListener('click', function () { input.value = ''; thumbs.innerHTML = ''; clear.hidden = true; pick.textContent = 'Choose picture'; showStd(); });
		}
		pick.addEventListener('click', function () {
			if (!window.wp || !wp.media) { return; }
			if (!frame) {
				frame = wp.media({ title: box.getAttribute('data-title'), multiple: multi ? 'add' : false, library: { type: kind }, button: { text: kind === 'audio' ? 'Use these songs' : 'Use ' + (multi ? 'these pictures' : 'this picture') } });
				frame.on('select', function () {
					var sel = frame.state().get('selection').toJSON();
					if (!multi) { thumbs.innerHTML = ''; input.value = ''; }
					var cur = ids();
					thumbs.querySelectorAll('.is-std').forEach(function (n) { n.remove(); });
					sel.forEach(function (a) {
						if (cur.indexOf(String(a.id)) !== -1 || cur.length >= 12) { return; }
						cur.push(String(a.id));
						var url = (a.sizes && (a.sizes.medium || a.sizes.thumbnail || a.sizes.full) || a).url;
						thumbs.appendChild(thumb(a.id, url, a.title));
					});
					input.value = cur.join(',');
					if (clear) { clear.hidden = !cur.length; }
					if (!multi) { pick.textContent = cur.length ? 'Change' : 'Choose picture'; }
				});
			}
			frame.open();
		});
	});
})();

/* Show Manager QR code */
(function () {
	var box = document.querySelector('[data-mq-qr]');
	if (!box || typeof window.qrcode !== 'function') { return; }
	var qr = window.qrcode(0, 'M'); qr.addData(box.getAttribute('data-mq-qr')); qr.make();
	box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
})();

/* Names & FPP: show the parts that go with how names go up (names break / during songs). */
(function () {
	var pane = document.querySelector('[data-names-mode]');
	if (!pane) { return; }
	pane.querySelectorAll('input[name="names_mode"]').forEach(function (r) {
		r.addEventListener('change', function () { if (r.checked) { pane.setAttribute('data-names-mode', r.value); } });
	});
})();


/* Themed sequences: the same animated card previews kids see (copied from kids.js). */
/* (kids.js) Pick your show: each theme card plays a little animated picture of the Light Lab house
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

/* Keep each settings row's card preview in step with its name, look and picture. */
(function () {
	var GUESS = [['candy', /candy|peppermint|cane/], ['snow', /snow|flake|winter/], ['rainbow', /rainbow|party|disco/], ['aurora', /northern|aurora|magic/], ['gold', /gold|star|angel|nativity|holy/], ['ginger', /ginger|cookie|cocoa/], ['icy', /frozen|ice|icy|elsa|blue/], ['purple', /purple|pink|sugar plum|fairy|princess/], ['christmas', /santa|sleigh|elf|elves|reindeer|rudolph|grinch|jingle|christmas/]];
	function guess(n) { n = (n || '').toLowerCase(); for (var i = 0; i < GUESS.length; i++) { if (GUESS[i][1].test(n)) { return GUESS[i][0]; } } return 'classic'; }
	function sync() {
		document.querySelectorAll('.mq__themes [data-mq-theme]').forEach(function (row) {
			var card = row.querySelector('.mq-tcard'); if (!card) { return; }
			var nm = row.querySelector('[data-mq-tname]'), lk = row.querySelector('[data-mq-tlook]');
			var name = nm ? (nm.value || nm.getAttribute('placeholder') || '') : '';
			card.querySelector('[data-mq-tcard-name]').textContent = name;
			card.querySelector('canvas').setAttribute('data-rr-fx', (lk && lk.value) || guess(name));
			var th = row.querySelector('[data-mq-thumbs] img'), img = card.querySelector('img');
			if (th && th.src) { img.src = th.src; img.hidden = false; } else { img.hidden = true; }
			card.classList.toggle('is-empty', !(nm && nm.value) && !row.classList.contains('is-classic'));
		});
	}
	if (document.querySelector('.mq__themes')) { sync(); setInterval(sync, 600); }
})();

/* Settings → Social posts: connect the Page, live preview of each post, "Post it now". */
(function () {
	var box = document.querySelector('[data-mq-social]');
	if (!box) { return; }
	var C = window.MarqueeAdmin || {}, fill = {};
	try { fill = JSON.parse(box.getAttribute('data-fill') || '{}'); } catch (e) {}
	function post(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', C.nonce);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function filled(t) {
		return t.replace(/\r/g, '').split('\n').filter(function (line) {
			var keys = line.match(/\{(show|city|site|radio|tagline|date)\}/g);
			return !keys || keys.some(function (k) { return (fill[k] || '').trim(); });
		}).map(function (line) { return line.replace(/\{(show|city|site|radio|tagline|date)\}/g, function (k) { return fill[k] || ''; }); }).join('\n').trim();
	}
	box.querySelectorAll('.mq__card').forEach(function (card) {
		var ta = card.querySelector('[data-so-text]'), pv = card.querySelector('[data-so-preview]'), im = card.querySelector('[data-so-img]'), pic = card.querySelector('[data-so-pic]');
		if (!ta) { return; }
		function upd() {
			pv.textContent = filled(ta.value);
			var th = pic && pic.querySelector('[data-mq-thumbs] img');
			im.innerHTML = th && th.src ? '<img alt="" src="' + th.src.replace(/"/g, '') + '">' : '';
		}
		ta.addEventListener('input', upd); upd(); setInterval(upd, 1000);
		var ask = card.querySelector('[data-so-ask]'), cf = card.querySelector('[data-so-confirm]'), now = card.querySelector('[data-so-now]'), msg = card.querySelector('[data-so-nowmsg]');
		if (ask) {
			ask.addEventListener('click', function () { cf.hidden = false; ask.hidden = true; });
			card.querySelector('[data-so-no]').addEventListener('click', function () { cf.hidden = true; ask.hidden = false; });
			now.addEventListener('click', function () {
				now.disabled = true; msg.textContent = 'Posting…';
				post('marquee_social_now', { what: now.getAttribute('data-so-now') }).then(function (j) {
					now.disabled = false; cf.hidden = true; ask.hidden = false;
					var log = (j && j.success && j.data.log) || [];
					var nets = { facebook: 'Facebook: ', instagram: 'Instagram: ', threads: 'Threads: ', all: '' };
					msg.textContent = log.length ? log.map(function (l) { return (l.ok ? '✓ ' : '✗ ') + (nets[l.net] || '') + l.msg; }).join('  ') : 'Nothing was posted.';
				}).catch(function () { now.disabled = false; msg.textContent = 'Couldn’t reach your website.'; });
			});
		}
	});
	var chk = box.querySelector('[data-so-check]');
	if (chk) {
		chk.addEventListener('click', function () {
			var m = box.querySelector('[data-so-checkmsg]'); m.textContent = 'Checking…';
			post('marquee_social_check').then(function (j) { m.textContent = (j && j.data && j.data.message) || 'Something went wrong.'; });
		});
	}
	var thc = box.querySelector('[data-so-thconnect]'), thm = box.querySelector('[data-so-thmsg]');
	thc.addEventListener('click', function () {
		thc.disabled = true; thm.textContent = 'Connecting…';
		post('marquee_social_threads', { token: box.querySelector('[data-so-thtoken]').value, secret: box.querySelector('[data-so-thsecret]').value }).then(function (j) {
			thc.disabled = false;
			if (j && j.success) { thm.textContent = 'Connected to @' + j.data.name + '!'; try { location.hash = '#mq-step-7'; } catch (e) {} location.reload(); }
			else { thm.textContent = (j && j.data && j.data.message) || 'Something went wrong.'; }
		}).catch(function () { thc.disabled = false; thm.textContent = 'Couldn’t reach your website.'; });
	});
	var con = box.querySelector('[data-so-connect]'), cmsg = box.querySelector('[data-so-msg]'), pages = box.querySelector('[data-so-pages]');
	con.addEventListener('click', function () {
		con.disabled = true; cmsg.textContent = 'Connecting…'; pages.hidden = true;
		post('marquee_social_connect', { app: box.querySelector('[data-so-app]').value, secret: box.querySelector('[data-so-secret]').value, token: box.querySelector('[data-so-token]').value }).then(function (j) {
			con.disabled = false;
			if (!j || !j.success) { cmsg.textContent = (j && j.data && j.data.message) || 'Something went wrong.'; return; }
			cmsg.textContent = j.data.pages.length === 1 ? '' : 'Which Page should Marquee post on?';
			pages.innerHTML = ''; pages.hidden = false;
			j.data.pages.forEach(function (p) {
				var b = document.createElement('button'); b.type = 'button'; b.className = 'button';
				b.textContent = p.name + (p.ig ? ' + Instagram @' + p.ig : '');
				b.addEventListener('click', function () {
					cmsg.textContent = 'Saving…';
					post('marquee_social_pick', { page: p.id }).then(function (k) {
						if (k && k.success) { cmsg.textContent = 'Connected to ' + k.data.name + '!'; try { location.hash = '#mq-step-7'; } catch (e) {} location.reload(); }
						else { cmsg.textContent = (k && k.data && k.data.message) || 'Something went wrong.'; }
					});
				});
				pages.appendChild(b);
			});
			if (j.data.pages.length === 1) { pages.querySelector('button').click(); }
		}).catch(function () { con.disabled = false; cmsg.textContent = 'Couldn’t reach your website.'; });
	});
})();

/* Names break timeline: where each name goes up, from "When the first name appears" and "Time between names". */
(function () {
	document.querySelectorAll('[data-mq-nt]').forEach(function (nt) {
		var a = document.querySelector(nt.getAttribute('data-start-in')), b = document.querySelector(nt.getAttribute('data-slot-in'));
		if (!a || !b) { return; }
		var len = parseFloat(nt.getAttribute('data-len')) || 29.6, bar = nt.querySelector('.mq-nt__bar'), axis = nt.querySelector('.mq-nt__axis'), say = nt.querySelector('.mq-nt__say');
		function fmt(x) { return (Math.round(x * 10) / 10).toString(); }
		function draw() {
			var st = parseFloat(a.value) || 0, sl = parseFloat(b.value) || 0, end = st + 6 * sl, L = Math.max(len, end + 1), h = '';
			function pc(x) { return Math.max(0, Math.min(100, x / L * 100)); }
			h += '<span class="mq-nt__seg is-intro" style="left:0;width:' + pc(st) + '%">' + (st >= 2 ? 'Intro' : '') + '</span>';
			for (var k = 0; k < 6; k++) { h += '<span class="mq-nt__seg is-name" style="left:' + pc(st + k * sl) + '%;width:' + Math.max(0, pc(sl) - 0.6) + '%">' + (k + 1) + '</span>'; }
			if (end < len) { h += '<span class="mq-nt__seg is-outro" style="left:' + pc(end) + '%;width:' + (pc(len) - pc(end)) + '%">' + (len - end >= 2 ? 'Outro' : '') + '</span>'; }
			bar.innerHTML = h;
			var ax = ''; for (var t = 0; t <= L; t += 5) { ax += '<span style="left:' + pc(t) + '%">' + t + 's</span>'; }
			axis.innerHTML = ax;
			var times = []; for (var j = 0; j < 6; j++) { times.push(fmt(st + j * sl) + ' s'); }
			var txt = 'Names go up at ' + times.join(', ') + '. Each one stays up about ' + fmt(Math.max(2, sl - 0.6)) + ' s.';
			if (end > len + 0.05) { txt += ' ⚠ The 6th name ends at ' + fmt(end) + ' s, after your ' + fmt(len) + ' s break: lower one of the numbers.'; nt.classList.add('is-over'); }
			else { txt += ' The last name is done at ' + fmt(end) + ' s of your ' + fmt(len) + ' s break.'; nt.classList.remove('is-over'); }
			say.textContent = txt;
		}
		a.addEventListener('input', draw); b.addEventListener('input', draw); draw();
	});
})();

/* Messages kids can pick: how each one looks on the screens, and "put back the standard messages". */
(function () {
	var box = document.querySelector('[data-mq-msgs]');
	if (!box) { return; }
	var ins = box.querySelectorAll('[data-mq-msg]');
	function look(inp) {
		var li = inp.parentNode, scr = li.querySelector('[data-mq-msg-scr]'), warn = li.querySelector('[data-mq-msg-warn]');
		var v = inp.value.trim(), say = '';
		if (!v) { scr.textContent = ''; warn.hidden = true; return; }
		var t = v.replace(/\[\s*name\s*\]|\{\s*name\s*\}/ig, 'NAME');
		if (t.indexOf('NAME') < 0 && /\bname\b/i.test(t)) { t = t.replace(/\bname\b/i, 'NAME'); }
		if (t.indexOf('NAME') < 0) { t = t.replace(/[\s,!.]+$/, '') + ', NAME!'; say = 'No NAME in it, so the name goes at the end.'; }
		if (/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}]/u.test(t)) { t = t.replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}️]/gu, ''); say = (say ? say + ' ' : '') + 'Emoji are left out: the screens can’t show them.'; }
		var k = t.indexOf('NAME');
		scr.textContent = '→ ' + t.slice(0, k).toUpperCase() + 'Emma' + t.slice(k + 4).replace(/NAME/g, '').toUpperCase();
		warn.textContent = say; warn.hidden = !say;
	}
	ins.forEach(function (i) { i.addEventListener('input', function () { look(i); }); look(i); });
	var std = box.querySelector('[data-mq-msgs-std]');
	if (std) {
		std.addEventListener('click', function () {
			var list = []; try { list = JSON.parse(std.getAttribute('data-std')) || []; } catch (e) {}
			ins.forEach(function (i, n) { i.value = list[n] || ''; look(i); });
		});
	}
})();

/* Blocked names and words: chips with ×, and "Block it". Saved with the settings. */
(function () {
	var box = document.querySelector('[data-mq-block]');
	if (!box) { return; }
	var val = box.querySelector('[data-mq-block-val]'), list = box.querySelector('[data-mq-block-list]'), none = box.querySelector('[data-mq-block-none]');
	var inp = box.querySelector('[data-mq-block-new]'), add = box.querySelector('[data-mq-block-add]'), pill = box.querySelector('[data-mq-block-pill]');
	var words = val.value.split(',').map(function (w) { return w.trim(); }).filter(Boolean);
	function draw() {
		list.innerHTML = '';
		words.forEach(function (w, i) {
			var li = document.createElement('li'), b = document.createElement('button');
			li.textContent = w;
			b.type = 'button'; b.textContent = '×'; b.setAttribute('aria-label', 'Unblock ' + w);
			b.addEventListener('click', function () { words.splice(i, 1); draw(); });
			li.appendChild(b); list.appendChild(li);
		});
		none.hidden = words.length > 0;
		val.value = words.join(', ');
		pill.textContent = words.length + ' blocked';
	}
	function addWord() {
		var w = inp.value.trim().toLowerCase().replace(/[,]/g, '');
		if (w && words.indexOf(w) < 0) { words.push(w); }
		inp.value = ''; draw(); inp.focus();
	}
	add.addEventListener('click', addWord);
	inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addWord(); } });
	draw();
})();

/* Copy buttons (FPP plugin address, connection code). */
document.addEventListener('click', function (e) {
	var b = e.target.closest && e.target.closest('[data-mq-copy]');
	if (!b) { return; }
	var inp = b.parentNode.querySelector('[data-mq-copysrc]');
	if (!inp) { return; }
	inp.select();
	var done = function () { var t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = t; }, 1500); };
	if (navigator.clipboard) { navigator.clipboard.writeText(inp.value).then(done, function () { document.execCommand('copy'); done(); }); }
	else { document.execCommand('copy'); done(); }
});

/* Your screens: the Font size box shows when Size is Custom. */
document.addEventListener('change', function (e) {
	var sel = e.target;
	if (!sel.name || !/^fpp_screens\[\d+\]\[size\]$/.test(sel.name)) { return; }
	var box = sel.closest('td') && sel.closest('td').querySelector('[data-mq-px]');
	if (!box) { return; }
	box.hidden = sel.value !== 'custom';
	if (!box.hidden) { var inp = box.querySelector('input'); if (!inp.value) { inp.focus(); } }
});
