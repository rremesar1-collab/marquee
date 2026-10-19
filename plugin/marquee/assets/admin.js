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
	}
	steps.forEach(function (b) {
		b.addEventListener('click', function () {
			var i = +b.getAttribute('data-step');
			if (wizard) { go(i); } else { go(i); panes[i].scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		});
	});
	$('[data-mq-back]').addEventListener('click', function () { go(cur - 1); window.scrollTo(0, 0); });
	$('[data-mq-next]').addEventListener('click', function () { go(cur + 1); window.scrollTo(0, 0); });
	go(0);
	/* links like #mq-step-3 (from the setup checklist) open that step */
	var hm = /^#mq-step-(\d)$/.exec(location.hash);
	if (hm && panes[+hm[1]]) { go(+hm[1]); setTimeout(function () { panes[+hm[1]].scrollIntoView({ block: 'start' }); }, 50); }

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
