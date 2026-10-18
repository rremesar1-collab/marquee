/* Show Control (WP Admin): live status, switches, name queue, contest review, stats. */
(function () {
	'use strict';
	var cfg = window.RRCtl || {};
	var root = document.querySelector('[data-rrc]');
	if (!root) { return; }
	var $ = function (s) { return root.querySelector(s); };
	var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };
	var state = null, range = 'tonight', busy = 0, savedT;

	function call(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function saved(m) { var s = $('[data-rrc-saved]'); s.textContent = m; clearTimeout(savedT); savedT = setTimeout(function () { s.textContent = ''; }, 2000); }
	function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt !== undefined && txt !== null) { e.textContent = txt; } return e; }
	function set(k, txt) { var e = $('[data-rrc="' + k + '"]'); if (e) { e.textContent = txt; } return e; }
	function pill(k, cls, txt) { var e = $('[data-rrc="' + k + '"]'); e.innerHTML = ''; e.appendChild(el('span', 'rrc__pill ' + cls, txt)); }
	function stCls(k, cls) { var e = $('[data-rrc-st="' + k + '"]'); e.className = 'rrc__st ' + (cls ? 'is-' + cls : ''); }
	function ago(s) { if (s === null || s === undefined) { return 'has not checked in yet'; } if (s < 90) { return 'checked in ' + s + ' sec ago'; } if (s < 5400) { return 'checked in ' + Math.round(s / 60) + ' min ago'; } return 'last checked in ' + Math.round(s / 3600) + ' h ago'; }

	function render(d) {
		state = d;
		/* status strip */
		var sc = { running: 'good', canceled: 'bad', upcoming: 'off', ended: 'off', offseason: 'off' }[d.show.state] || 'off';
		pill('show', 'is-' + sc, d.show.label); set('show-sub', d.show.sub); stCls('show', sc === 'off' ? '' : sc);
		var f = d.fpp, fresh = f.seen !== null && f.seen < 120;
		if (!fresh) { set('fpp', f.seen === null ? 'Not connected' : 'Not checking in'); stCls('fpp', f.seen === null ? '' : 'warn'); }
		else if (f.status === 'playing') { set('fpp', f.sequence || 'Playing'); stCls('fpp', 'good'); }
		else { set('fpp', f.status ? f.status.charAt(0).toUpperCase() + f.status.slice(1) : 'Idle'); stCls('fpp', ''); }
		set('fpp-sub', (fresh && f.playlist ? f.playlist + ' · ' : '') + ago(f.seen));
		var sw = d.switches, ncls = !sw.names_on ? 'bad' : (sw.paused ? 'warn' : (d.names.open ? 'good' : 'off'));
		var nb = $('[data-rrc="names"]'); nb.innerHTML = '';
		nb.appendChild(el('span', 'rrc__pill is-' + ncls, !sw.names_on ? 'Off' : (sw.paused ? 'Paused' : (d.names.open ? 'Open' : 'Closed'))));
		nb.appendChild(document.createTextNode(' · ' + d.names.waiting.length + ' waiting'));
		set('names-sub', sw.paused ? 'Names wait until you un-pause' : (d.names.open ? 'Next break after the current song' : 'Opens when the show starts'));
		stCls('names', ncls === 'off' ? '' : ncls);
		set('people', d.people.now + (d.people.now === 1 ? ' person' : ' people'));
		set('people-sub', d.people.kids + ' in the Kids’ Corner');

		/* switches */
		if (!busy) {
			$$('[data-rrc-tog]').forEach(function (b) { b.setAttribute('aria-checked', String(!!sw[b.getAttribute('data-rrc-tog')])); });
			var sn = $('[data-rrc-set="snow_amount"]'); if (document.activeElement !== sn) { sn.value = sw.snow_amount; }
			var sz = $('[data-rrc-set="snow_size"]'); if (document.activeElement !== sz) { sz.value = sw.snow_size || 'medium'; }
			var nc = $('[data-rrc-set="names_color"]'); if (document.activeElement !== nc && /^#[0-9a-f]{6}$/i.test(sw.names_color || '')) { nc.value = sw.names_color.toLowerCase(); }
		}

		/* name queue */
		set('shown-n', d.names.shown_tonight);
		var w = $('[data-rrc-list="waiting"]'); w.innerHTML = '';
		if (!d.names.waiting.length) { w.appendChild(el('li', 'empty', 'No names waiting.')); }
		d.names.waiting.forEach(function (n, i) {
			var li = el('li'); li.appendChild(el('span', 'n', i + 1));
			var t = el('span', 't'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', 'sent ' + n.time)); li.appendChild(t);
			var rm = el('button', 'rrc__btn del', 'Remove'); rm.type = 'button'; rm.addEventListener('click', function () { act('rr_ctl_name', { id: n.id }, 'Removed'); });
			var bl = el('button', 'rrc__btn', 'Block word'); bl.type = 'button'; bl.addEventListener('click', function () { act('rr_ctl_name', { id: n.id, block: 1 }, 'Removed and blocked'); });
			li.appendChild(rm); li.appendChild(bl); w.appendChild(li);
		});
		var sh = $('[data-rrc-list="shown"]'); sh.innerHTML = '';
		if (!d.names.shown.length) { sh.appendChild(el('li', 'empty', 'Nothing shown yet.')); }
		d.names.shown.forEach(function (n) { var li = el('li'), t = el('span', 't'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', n.time)); li.appendChild(t); sh.appendChild(li); });

		/* contest */
		var c = d.contest;
		set('contest-sub', (c.closes ? 'Closes ' + c.closes + ' · ' : '') + c.total + (c.total === 1 ? ' entry' : ' entries'));
		set('prizes', c.prizes);
		var pd = $('[data-rrc-list="pending"]'); pd.innerHTML = '';
		if (!c.pending.length) { pd.appendChild(el('li', 'empty', 'All caught up.')); }
		c.pending.forEach(function (e) {
			var li = el('li');
			if (e.thumb) { var im = el('img'); im.src = e.thumb; im.alt = ''; li.appendChild(im); }
			var t = el('span', 't'); t.appendChild(el('b', '', e.title || 'My Light Show')); t.appendChild(el('small', '', e.name + ', age ' + e.age)); li.appendChild(t);
			var wa = el('a', 'rrc__btn', 'Watch'); wa.href = e.watch; wa.target = '_blank'; wa.rel = 'noopener'; li.appendChild(wa);
			var ok = el('button', 'rrc__btn pri', 'Approve'); ok.type = 'button'; ok.addEventListener('click', function () { act('rr_ctl_entry', { id: e.id, approve: 1 }, 'Approved: now on the Show Wall'); });
			var no = el('button', 'rrc__btn del', 'Decline'); no.type = 'button'; no.addEventListener('click', function () { act('rr_ctl_entry', { id: e.id }, 'Declined'); });
			li.appendChild(ok); li.appendChild(no); pd.appendChild(li);
		});
		var tp = $('[data-rrc-list="top"]'); tp.innerHTML = '';
		if (!c.top.length) { tp.appendChild(el('li', '', 'No shows on the Show Wall yet.')); }
		c.top.forEach(function (e) {
			var li = el('li'); li.appendChild(el('b', '', e.title || 'My Light Show')); li.appendChild(el('span', '', ' · ' + e.name + ', ' + e.age));
			li.appendChild(el('em', '', e.votes + ' ♥')); tp.appendChild(li);
		});

		tiles(); chart(); app();
	}

	function app() {
		var a = state.app, box = $('[data-rrc-app-tiles]'); box.innerHTML = '';
		[['App installs', a.installs, 'this season'], ['Getting alerts', a.subs, a.start + ' lights-on · ' + a.news + ' news'], ['Name buzzes', a.buzz, 'tonight']].forEach(function (t) {
			var d = el('div', 'rrc__tile'); d.appendChild(el('small', '', t[0])); d.appendChild(el('b', '', (t[1] || 0).toLocaleString())); d.appendChild(el('span', '', t[2])); box.appendChild(d);
		});
		set('app-sub', a.ready ? (a.opens + (a.opens === 1 ? ' app open tonight' : ' app opens tonight')) : 'Alerts can’t be sent: this server’s PHP is missing OpenSSL features');
		set('auto-sub', a.sent_on ? 'Sent tonight when the show started' : 'Sent automatically once a night when FPP starts the show playlist');
		set('app-to', a.news === 1 ? 'Goes to 1 phone that chose “special news”.' : 'Goes to the ' + a.news + ' phones that chose “special news”.');
		set('cancel-alert-label', 'Also send a “No show tonight” alert to ' + a.news + (a.news === 1 ? ' phone' : ' phones'));
		$('[data-rrc-cancel-alert-row]').hidden = !a.ready || !a.news;
		var kinds = { auto: 'Automatic', cancel: 'From “Cancel tonight’s show”', you: 'Sent by you' };
		var ul = $('[data-rrc-list="alerts"]'); ul.innerHTML = '';
		if (!a.log.length) { ul.appendChild(el('li', 'empty', 'No alerts sent yet.')); }
		a.log.forEach(function (l) {
			var li = el('li'), t = el('span', 't'); t.appendChild(el('b', '', l.kind === 'you' ? l.body : l.title)); t.appendChild(el('small', '', (kinds[l.kind] || '') + ' · ' + l.when)); li.appendChild(t);
			li.appendChild(el('small', 'rrc__sent', l.sent + ' sent' + (l.failed ? ' · ' + l.failed + ' failed' : ''))); ul.appendChild(li);
		});
	}

	function tiles() {
		var s = state.stats[range] || {}, box = $('[data-rrc-tiles]'); box.innerHTML = '';
		var avg = s.trivia ? ' · avg ' + (s.trivia_sum / s.trivia).toFixed(1) + ' of 5 right' : '';
		[['Names sent', s.name_sent, (s.name_shown || 0) + ' shown'], ['Light shows played', s.lab_play, 'Play pressed in the Light Lab'], ['Contest entries', s.entry, ''],
			['Hearts given', s.heart, ''], ['Song votes', s.song_vote, state.stats.top_song ? 'Top: ' + state.stats.top_song : ''],
			['Elf names', s.elf, ''], ['Trivia games', s.trivia, avg.replace(' · ', '')], ['Coloring pages', s.coloring, 'opened to print'],
			['Kids’ Corner visits', s.kids, ''], ['Website visits', s.visit, '']].forEach(function (t) {
			var d = el('div', 'rrc__tile'); d.appendChild(el('small', '', t[0])); d.appendChild(el('b', '', (t[1] || 0).toLocaleString())); d.appendChild(el('span', '', t[2] || ' '));
			box.appendChild(d);
		});
	}
	function chart() {
		var plot = $('[data-rrc-plot]'), tip = $('[data-rrc-tip]'), old = plot.querySelector('svg');
		if (old) { old.remove(); }
		var data = state.stats.nights;
		if (!data.length) { data = [['No show nights yet', 0]]; }
		var max = Math.max(10, Math.ceil(Math.max.apply(null, data.map(function (d) { return d[1]; })) / 10) * 10);
		var W = 760, H = 230, ml = 34, mr = 8, mt = 10, mb = 26, pw = W - ml - mr, ph = H - mt - mb, bw = pw / Math.max(data.length, 7);
		var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'Names sent each night');
		function mk(n, a) { var e = document.createElementNS(ns, n); for (var k in a) { e.setAttribute(k, a[k]); } return e; }
		[0, max / 2, max].forEach(function (v) {
			var y = mt + ph - v / max * ph;
			svg.appendChild(mk('line', { x1: ml, x2: W - mr, y1: y, y2: y, stroke: v ? '#e6e6e8' : '#8c8f94', 'stroke-width': 1 }));
			var t = mk('text', { x: ml - 6, y: y + 4, 'text-anchor': 'end', 'font-size': 11, fill: '#787c82' }); t.textContent = Math.round(v); svg.appendChild(t);
		});
		data.forEach(function (d, i) {
			var h = d[1] / max * ph, x = ml + i * bw + 3, w = Math.max(4, bw - 6), y = mt + ph - h, r = Math.min(4, w / 2, h);
			var p = mk('path', { d: h > 0 ? 'M' + x + ',' + (mt + ph) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y + 'H' + (x + w - r) + 'Q' + (x + w) + ',' + y + ' ' + (x + w) + ',' + (y + r) + 'V' + (mt + ph) + 'Z' : 'M0,0', fill: '#2271b1' });
			svg.appendChild(p);
			if (data.length <= 8 || i % 2 === 0 || i === data.length - 1) { var t = mk('text', { x: x + w / 2, y: H - 8, 'text-anchor': 'middle', 'font-size': 11, fill: '#787c82' }); t.textContent = d[0]; svg.appendChild(t); }
			var hit = mk('rect', { x: ml + i * bw, y: mt, width: bw, height: ph, fill: 'transparent' });
			hit.addEventListener('mouseenter', function () {
				p.setAttribute('fill', '#135e96');
				var sx = svg.getBoundingClientRect().width / W;
				tip.hidden = false; tip.textContent = d[0] + ': ' + d[1] + ' names';
				tip.style.left = ((x + w / 2) * sx - plot.scrollLeft) + 'px'; tip.style.top = (Math.min(y, mt + ph - 10) * sx) + 'px';
			});
			hit.addEventListener('mouseleave', function () { p.setAttribute('fill', '#2271b1'); tip.hidden = true; });
			svg.appendChild(hit);
		});
		plot.insertBefore(svg, tip);
	}

	function act(action, data, msg) {
		busy++;
		return call(action, data).then(function (j) {
			busy--;
			if (j && j.success) { render(j.data); saved(j.data.pushed ? 'Canceled. Alert sent to ' + j.data.pushed.sent + (j.data.pushed.sent === 1 ? ' phone' : ' phones') : (msg || 'Saved')); } else { saved('Could not save. Please reload the page.'); refresh(); }
		}).catch(function () { busy--; saved('Could not reach the website.'); });
	}
	$$('[data-rrc-tog]').forEach(function (b) {
		b.addEventListener('click', function () {
			var on = b.getAttribute('aria-checked') !== 'true', k = b.getAttribute('data-rrc-tog');
			if (k === 'cancel' && on) { cc.hidden = false; $('[data-rrc-cancel-yes]').focus(); return; }
			b.setAttribute('aria-checked', String(on));
			act('rr_ctl_set', { key: k, value: on ? '1' : '0' });
		});
	});
	var cc = $('[data-rrc-cancel-confirm]');
	$('[data-rrc-cancel-no]').addEventListener('click', function () { cc.hidden = true; });
	$('[data-rrc-cancel-yes]').addEventListener('click', function () {
		var alertToo = !$('[data-rrc-cancel-alert-row]').hidden && $('[data-rrc-cancel-alert]').checked;
		cc.hidden = true; $('[data-rrc-tog="cancel"]').setAttribute('aria-checked', 'true');
		act('rr_ctl_set', { key: 'cancel', value: '1', alert: alertToo ? '1' : '' }, 'Canceled for tonight');
	});

	/* send an alert */
	var msgBox = $('[data-rrc-msg]'), sendRow = $('[data-rrc-send-row]'), sc = $('[data-rrc-send-confirm]');
	msgBox.addEventListener('input', function () { set('msg-count', msgBox.value.length + ' / 180'); sc.hidden = true; sendRow.hidden = false; });
	$('[data-rrc-send]').addEventListener('click', function () {
		var m = msgBox.value.trim();
		if (!m) { saved('Type a message first.'); msgBox.focus(); return; }
		var n = state ? state.app.news : 0;
		set('send-q', 'Send “' + m + '” to ' + n + (n === 1 ? ' phone?' : ' phones?'));
		sendRow.hidden = true; sc.hidden = false; $('[data-rrc-send-yes]').focus();
	});
	$('[data-rrc-send-no]').addEventListener('click', function () { sc.hidden = true; sendRow.hidden = false; });
	$('[data-rrc-send-yes]').addEventListener('click', function () {
		var b = this; b.disabled = true; saved('Sending…');
		busy++;
		call('rr_ctl_push', { message: msgBox.value.trim() }).then(function (j) {
			busy--; b.disabled = false; sc.hidden = true; sendRow.hidden = false;
			if (j && j.success) {
				render(j.data); msgBox.value = ''; set('msg-count', '0 / 180');
				var p = j.data.pushed; saved('Sent to ' + p.sent + (p.sent === 1 ? ' phone' : ' phones') + (p.failed ? ' (' + p.failed + ' did not go through)' : ''));
			} else { saved((j && j.data && j.data.message) || 'Could not send. Please reload the page.'); }
		}).catch(function () { busy--; b.disabled = false; saved('Could not reach the website.'); });
	});

	$$('[data-rrc-set]').forEach(function (i) { i.addEventListener('change', function () { act('rr_ctl_set', { key: i.getAttribute('data-rrc-set'), value: i.value }); }); });
	$$('[data-rrc-range]').forEach(function (b) { b.addEventListener('click', function () {
		range = b.getAttribute('data-rrc-range');
		$$('[data-rrc-range]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
		if (state) { tiles(); }
	}); });

	function refresh() {
		if (busy || document.hidden) { return; }
		call('rr_ctl_state').then(function (j) { if (j && j.success) { render(j.data); } }).catch(function () {});
	}
	refresh(); setInterval(refresh, 15000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
})();
