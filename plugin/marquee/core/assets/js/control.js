/* Show Control (WP Admin): live status, switches, name queue, contest review, stats. */
(function () {
	'use strict';
	var cfg = window.RRCtl || {};
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
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
	function ago(s) { if (s === null || s === undefined) { return tr('has not checked in yet'); } if (s < 90) { return tr('checked in %s sec ago').replace('%s', s); } if (s < 5400) { return tr('checked in %s min ago').replace('%s', Math.round(s / 60)); } return tr('last checked in %s h ago').replace('%s', Math.round(s / 3600)); }

	function render(d) {
		state = d;
		/* status strip */
		var sc = { running: 'good', canceled: 'bad', upcoming: 'off', ended: 'off', offseason: 'off' }[d.show.state] || 'off';
		pill('show', 'is-' + sc, d.show.label); set('show-sub', d.show.sub);
		var hero = root.querySelector('.rrc__hero'); if (hero) { hero.setAttribute('data-state', d.show.state); }
		set('qa-cancel', sw0(d).cancel ? tr('Undo: the show is on') : tr('Cancel tonight'));
		set('qa-pause', sw0(d).paused ? tr('Resume names') : tr('Pause names'));
		lastAt = Date.now();
		var f = d.fpp, fresh = f.seen !== null && f.seen < 120;
		if (!fresh) { set('fpp', f.seen === null ? tr('Not connected') : tr('Not checking in')); stCls('fpp', f.seen === null ? '' : 'warn'); }
		else if (f.status === 'playing') { set('fpp', f.sequence || tr('Playing')); stCls('fpp', 'good'); }
		else { set('fpp', f.status ? f.status.charAt(0).toUpperCase() + f.status.slice(1) : tr('Idle')); stCls('fpp', ''); }
		set('fpp-sub', (fresh && f.playlist ? f.playlist + ' · ' : '') + ago(f.seen));
		var sw = d.switches, ncls = !sw.names_on ? 'bad' : (sw.paused ? 'warn' : (d.names.open ? 'good' : 'off'));
		var nb = $('[data-rrc="names"]'); nb.innerHTML = '';
		nb.appendChild(el('span', 'rrc__pill is-' + ncls, !sw.names_on ? tr('Off') : (sw.paused ? tr('Paused') : (d.names.open ? tr('Open') : tr('Closed')))));
		nb.appendChild(document.createTextNode(' · ' + tr('%s waiting').replace('%s', d.names.waiting.length)));
		set('names-sub', sw.paused ? tr('Names wait until you un-pause') : (d.names.open ? (d.names.songs > 1 ? tr('Next break in about %s songs').replace('%s', d.names.songs) : tr('Next break after the current song')) : tr('Opens when the show starts')));
		stCls('names', ncls === 'off' ? '' : ncls);
		set('people', (d.people.now === 1 ? tr('%s person') : tr('%s people')).replace('%s', d.people.now));
		set('people-sub', tr('%s in the Kids’ Corner').replace('%s', d.people.kids));

		/* switches */
		if (!busy) {
			$$('[data-rrc-tog]').forEach(function (b) { b.setAttribute('aria-checked', String(!!sw[b.getAttribute('data-rrc-tog')])); });
			var sn = $('[data-rrc-set="snow_amount"]'); if (document.activeElement !== sn) { sn.value = sw.snow_amount; }
			var ne = $('[data-rrc-set="names_every"]'); if (ne && sw.names_every && document.activeElement !== ne) { ne.value = sw.names_every; }
			var sz = $('[data-rrc-set="snow_size"]'); if (document.activeElement !== sz) { sz.value = sw.snow_size || 'medium'; }
			var nc = $('[data-rrc-set="names_color"]'); if (document.activeElement !== nc && /^#[0-9a-f]{6}$/i.test(sw.names_color || '')) { nc.value = sw.names_color.toLowerCase(); }
		}

		/* name queue */
		set('shown-n', d.names.shown_tonight);
		var w = $('[data-rrc-list="waiting"]'); w.innerHTML = '';
		if (!d.names.waiting.length) { w.appendChild(el('li', 'empty', tr('No names waiting.'))); }
		d.names.waiting.forEach(function (n, i) {
			var li = el('li'); li.appendChild(el('span', 'n', i + 1));
			if (n.photo) { var im = el('img', 'rrc__pic'); im.src = n.photo; im.alt = ''; li.appendChild(im); }
			var t = el('span', 't'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', tr('sent %s').replace('%s', function () { return n.time; })));
			if (n.photo) { t.appendChild(el('small', 'rrc__picst' + (n.photo_state === 'ok' ? ' is-ok' : ''), n.photo_state === 'ok' ? (n.photo_why || tr('Picture approved')) : (n.photo_why ? n.photo_why + ' · ' : '') + tr('Picture waiting for you: the name waits too (up to 10 minutes)'))); }
			li.appendChild(t);
			if (n.photo && n.photo_state !== 'ok') {
				var ap = el('button', 'rrc__btn pri', tr('Show picture')); ap.type = 'button'; ap.addEventListener('click', function () { act('rr_ctl_photo', { id: n.id, approve: 1 }, tr('Picture approved')); });
				var np = el('button', 'rrc__btn', tr('No picture')); np.type = 'button'; np.addEventListener('click', function () { act('rr_ctl_photo', { id: n.id }, tr('Picture removed: the name goes up without it')); });
				li.appendChild(ap); li.appendChild(np);
			}
			var rm = el('button', 'rrc__btn del', tr('Remove')); rm.type = 'button'; rm.addEventListener('click', function () { act('rr_ctl_name', { id: n.id }, tr('Removed')); });
			var bl = el('button', 'rrc__btn', tr('Block word')); bl.type = 'button'; bl.addEventListener('click', function () { act('rr_ctl_name', { id: n.id, block: 1 }, tr('Removed and blocked')); });
			li.appendChild(rm); li.appendChild(bl); w.appendChild(li);
		});
		var sh = $('[data-rrc-list="shown"]'); sh.innerHTML = '';
		if (!d.names.shown.length) { sh.appendChild(el('li', 'empty', tr('Nothing shown yet.'))); }
		d.names.shown.forEach(function (n) { var li = el('li'), t = el('span', 't'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', n.time)); li.appendChild(t); sh.appendChild(li); });

		/* contest */
		var c = d.contest;
		set('contest-sub', (c.closes ? tr('Closes %s').replace('%s', function () { return c.closes; }) + ' · ' : '') + (c.total === 1 ? tr('%s entry') : tr('%s entries')).replace('%s', c.total));
		set('prizes', c.prizes);
		var pd = $('[data-rrc-list="pending"]'); pd.innerHTML = '';
		if (!c.pending.length) { pd.appendChild(el('li', 'empty', tr('All caught up.'))); }
		c.pending.forEach(function (e) {
			var li = el('li');
			if (e.thumb) { var im = el('img'); im.src = e.thumb; im.alt = ''; li.appendChild(im); }
			var t = el('span', 't'); t.appendChild(el('b', '', e.title || tr('My Light Show'))); t.appendChild(el('small', '', tr('%1$s, age %2$s').replace('%1$s', function () { return e.name; }).replace('%2$s', function () { return e.age; }))); li.appendChild(t);
			var wa = el('a', 'rrc__btn', tr('Watch')); wa.href = e.watch; wa.target = '_blank'; wa.rel = 'noopener'; li.appendChild(wa);
			var ok = el('button', 'rrc__btn pri', tr('Approve')); ok.type = 'button'; ok.addEventListener('click', function () { act('rr_ctl_entry', { id: e.id, approve: 1 }, tr('Approved: now on the Show Wall')); });
			var no = el('button', 'rrc__btn del', tr('Decline')); no.type = 'button'; no.addEventListener('click', function () { act('rr_ctl_entry', { id: e.id }, tr('Declined')); });
			li.appendChild(ok); li.appendChild(no); pd.appendChild(li);
		});
		var tp = $('[data-rrc-list="top"]'); tp.innerHTML = '';
		if (!c.top.length) { tp.appendChild(el('li', '', tr('No shows on the Show Wall yet.'))); }
		c.top.forEach(function (e) {
			var li = el('li'); li.appendChild(el('b', '', e.title || tr('My Light Show'))); li.appendChild(el('span', '', ' · ' + e.name + ', ' + e.age));
			li.appendChild(el('em', '', e.votes + ' ♥')); tp.appendChild(li);
		});

		tiles(); chart(); app(); extra(d);
	}
	function sw0(d) { return d.switches || {}; }

	/* weather, social, phones, traffic: the newer parts of the page */
	var WX = function (c) { return c === 0 ? '☀️' : c <= 3 ? '⛅' : c <= 48 ? '🌫️' : c <= 67 ? '🌧️' : c <= 77 ? '❄️' : c <= 82 ? '🌦️' : c <= 86 ? '🌨️' : '⛈️'; };
	function extra(d) {
		var w = d.weather;
		if (w) {
			set('wx', w.temp + w.unit + ' · ' + w.label); set('wx-icon', WX(w.code));
			set('wx-sub', w.rain ? tr('Up to %s% chance of rain during the show').replace('%s', w.rain) : tr('No rain expected during the show'));
			stCls('wx', w.rain >= 60 ? 'warn' : '');
		} else { set('wx', '—'); set('wx-sub', tr('Weather isn’t available')); }
		var so = d.social || {}, sea = (d.stats.season || {}), tn = (d.stats.tonight || {});
		if (so.ready) {
			set('social', (so.nets || []).join(', ') || tr('Connected')); set('social-sub', tr('%s posted this season').replace('%s', sea.social_post || 0));
			stCls('social', so.last && so.last.lines && so.last.lines.some(function (l) { return !l.ok; }) ? 'warn' : 'good');
		} else { set('social', tr('Not set up')); set('social-sub', tr('Post cancellations for you')); stCls('social', ''); }
		var a = d.app || {};
		set('phones', (a.subs || 0).toLocaleString()); set('phones-sub', tr('%1$s get news · %2$s get “lights on”').replace('%1$s', a.news || 0).replace('%2$s', a.start || 0));

		/* social card */
		set('social-card-sub', so.ready ? tr('Posting to %s').replace('%s', function () { return (so.nets || []).join(', '); }) : tr('Not set up yet'));
		var st = $('[data-rrc-social-tiles]');
		if (st) {
			st.innerHTML = '';
			[[tr('Posts this season'), sea.social_post, ''], [tr('Posted tonight'), tn.social_post, ''], [tr('Connected'), (so.nets || []).length, (so.nets || []).join(', ') || tr('none yet')]].forEach(function (t) {
				var x = el('div', 'rrc__tile'); x.appendChild(el('small', '', t[0])); x.appendChild(el('b', '', (t[1] || 0).toLocaleString())); x.appendChild(el('span', '', t[2] || ' ')); st.appendChild(x);
			});
		}
		var sl = $('[data-rrc-list="social"]');
		if (sl) {
			sl.innerHTML = '';
			var hist = so.history || [];
			if (!hist.length) { sl.appendChild(el('li', 'empty', so.ready ? tr('Nothing posted yet. Posts go out when you cancel the show.') : tr('Nothing posted yet.'))); }
			var nets = { facebook: 'Facebook', instagram: 'Instagram', threads: 'Threads', all: '' };
			hist.forEach(function (h) {
				var li = el('li'), t = el('span', 't');
				t.appendChild(el('b', h.ok ? '' : 'bad', (h.ok ? '✓ ' : '✗ ') + (nets[h.net] ? nets[h.net] + ' · ' : '') + (h.what === 'resume' ? tr('Back on') : tr('Canceled'))));
				t.appendChild(el('small', '', h.when + ' · ' + h.msg)); li.appendChild(t);
				if (h.link) { var v = el('a', 'rrc__btn', tr('View')); v.href = h.link; v.target = '_blank'; v.rel = 'noopener'; li.appendChild(v); }
				sl.appendChild(li);
			});
		}
		var help = $('[data-rrc="social-help"]');
		if (help) { help.innerHTML = ''; if (!so.ready && so.setup) { help.appendChild(document.createTextNode(tr('When you cancel, Marquee can post it on Facebook, Instagram and Threads.') + ' ')); var l = el('a', '', tr('Set it up')); l.href = so.setup; help.appendChild(l); } }

		/* traffic */
		var trf = d.traffic || {}, now = trf.now, box = $('[data-rrc-traffic-now]');
		if (box) {
			box.hidden = !now; box.innerHTML = '';
			if (now) {
				box.appendChild(el('span', 'ic', now.icon));
				var tx = el('span', 'tx'); tx.appendChild(el('b', '', now.title)); tx.appendChild(el('small', '', tr('On your website until %1$s (%2$s left)').replace('%1$s', function () { return now.until; }).replace('%2$s', function () { return now.left; }) + (now.screens ? ' · ' + tr('on your screens too') : ''))); box.appendChild(tx);
				var cl = el('button', 'rrc__btn', tr('Turn it off')); cl.type = 'button'; cl.addEventListener('click', function () { act('rr_ctl_traffic', { kind: 'clear' }, tr('Traffic alert off')); }); box.appendChild(cl);
			}
		}
		set('traffic-sub', now ? tr('On now: %s').replace('%s', function () { return now.title; }) : tr('Nothing posted'));
		var tb = $('[data-rrc-tbtns]');
		if (tb && !tb.children.length && trf.presets) {
			Object.keys(trf.presets).forEach(function (k) {
				var p = trf.presets[k], b = el('button', 'rrc__tbtn'); b.type = 'button'; b.setAttribute('data-rrc-tsend', k);
				b.appendChild(el('span', 'ic', p.icon)); b.appendChild(el('span', '', p.label)); tb.appendChild(b);
			});
		}
		$$('[data-rrc-tsend]').forEach(function (b) { b.classList.toggle('is-on', !!now && now.kind === b.getAttribute('data-rrc-tsend')); });
		var ar = $('[data-rrc-talert-row]'); if (ar) { ar.hidden = !a.ready || !a.news; set('talert-label', ((a.news === 1) ? tr('Also send a phone alert to %s phone') : tr('Also send a phone alert to %s phones')).replace('%s', a.news || 0)); }
	}
	var lastAt = 0;
	setInterval(function () {
		var c = $('[data-rrc="clock"]'); if (c) { c.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); }
		var u = $('[data-rrc="updated"]'); if (u && lastAt) { var s2 = Math.round((Date.now() - lastAt) / 1000); u.textContent = s2 < 5 ? tr('Updated just now') : tr('Updated %s sec ago').replace('%s', s2); }
	}, 1000);

	/* Social posts beside the cancel switch: a hint to set it up, or what was posted tonight. */
	function socialBox(box, so, canceled, confirmOpen) {
		if (!box) { return; }
		so = so || {};
		var h = '';
		function esc(t) { return String(t || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
		if (!so.ready) {
			if (confirmOpen || canceled) { h = '<p class="so-hint">' + esc(so.hint || '') + ' <a href="' + esc(so.setup || '#') + '">' + tr('Set it up') + '</a></p>'; }
		} else if (canceled) {
			if (so.posting) { h += '<p class="so-wait">' + tr('Posting on social media…') + '</p>'; }
			if (so.last && so.last.what === 'cancel' && so.last.lines && so.last.lines.length) {
				h += '<ul class="so-lines">' + so.last.lines.map(function (l) {
					return '<li class="' + (l.ok ? 'is-ok' : 'is-bad') + '">' + (l.ok ? '✓ ' : '✗ ') + (l.net ? esc(l.net) + ': ' : '') + esc(l.msg) + (l.link ? ' <a href="' + esc(l.link) + '" target="_blank" rel="noopener">' + tr('View') + '</a>' : '') + '</li>';
				}).join('') + '</ul>';
			}
		}
		if (canceled && so.text) { h += '<p><button type="button" class="so-copy" data-so-copy>' + tr('Copy the post for other sites (Nextdoor, groups…)') + '</button> <span class="so-copied" aria-live="polite"></span></p>'; }
		if (box.getAttribute('data-h') !== h) { box.innerHTML = h; box.setAttribute('data-h', h); }
		box.hidden = !h;
		var cp = box.querySelector('[data-so-copy]');
		if (cp && !cp.dataset.on) {
			cp.dataset.on = '1';
			cp.addEventListener('click', function () {
				var done = function () { box.querySelector('.so-copied').textContent = tr('Copied!'); };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(so.text).then(done, function () {}); }
				else { var t = document.createElement('textarea'); t.value = so.text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} t.remove(); }
			});
		}
	}

	function app() {
		var a = state.app, box = $('[data-rrc-app-tiles]'); box.innerHTML = '';
		[[tr('App installs'), a.installs, tr('this season')], [tr('Getting alerts'), a.subs, tr('%1$s lights-on · %2$s news').replace('%1$s', a.start).replace('%2$s', a.news)], [tr('Name buzzes'), a.buzz, tr('tonight')]].forEach(function (t) {
			var d = el('div', 'rrc__tile'); d.appendChild(el('small', '', t[0])); d.appendChild(el('b', '', (t[1] || 0).toLocaleString())); d.appendChild(el('span', '', t[2])); box.appendChild(d);
		});
		set('app-sub', a.ready ? (a.opens === 1 ? tr('%s app open tonight') : tr('%s app opens tonight')).replace('%s', a.opens) : tr('Alerts can’t be sent: this server’s PHP is missing OpenSSL features'));
		set('auto-sub', a.sent_on ? tr('Sent tonight when the show started') : tr('Sent automatically once a night when FPP starts the show playlist'));
		set('app-to', a.news === 1 ? tr('Goes to 1 phone that chose “special news”.') : tr('Goes to the %s phones that chose “special news”.').replace('%s', a.news));
		set('cancel-alert-label', (a.news === 1 ? tr('Also send a “No show tonight” alert to %s phone') : tr('Also send a “No show tonight” alert to %s phones')).replace('%s', a.news));
		$('[data-rrc-cancel-alert-row]').hidden = !a.ready || !a.news;
		socialBox($('[data-rrc-social]'), state.social, !!(state.switches && state.switches.cancel), !$('[data-rrc-cancel-confirm]').hidden);
		var so = state.social || {}, sr = $('[data-rrc-cancel-social-row]');
		if (sr) { sr.hidden = !so.ready; set('cancel-social-label', so.label || ''); if (!sr.dataset.touched) { $('[data-rrc-cancel-social]').checked = !!so.auto; } }
		var kinds = { auto: tr('Automatic'), cancel: tr('From “Cancel tonight’s show”'), you: tr('Sent by you'), traffic: tr('Traffic alert') };
		var ul = $('[data-rrc-list="alerts"]'); ul.innerHTML = '';
		if (!a.log.length) { ul.appendChild(el('li', 'empty', tr('No alerts sent yet.'))); }
		a.log.forEach(function (l) {
			var li = el('li'), t = el('span', 't'); t.appendChild(el('b', '', l.kind === 'you' ? l.body : l.title)); t.appendChild(el('small', '', (kinds[l.kind] || '') + ' · ' + l.when)); li.appendChild(t);
			li.appendChild(el('small', 'rrc__sent', tr('%s sent').replace('%s', l.sent) + (l.failed ? ' · ' + tr('%s failed').replace('%s', l.failed) : ''))); ul.appendChild(li);
		});
	}

	function tiles() {
		var s = state.stats[range] || {}, box = $('[data-rrc-tiles]'); box.innerHTML = '';
		var avg = s.trivia ? ' · ' + tr('avg %s of 5 right').replace('%s', (s.trivia_sum / s.trivia).toFixed(1)) : '';
		[[tr('Names sent'), s.name_sent, tr('%s shown').replace('%s', s.name_shown || 0)], [tr('Light shows played'), s.lab_play, tr('Play pressed in the Light Lab')], [tr('Contest entries'), s.entry, ''],
			[tr('Hearts given'), s.heart, ''], [tr('Song votes'), s.song_vote, state.stats.top_song ? tr('Top: %s').replace('%s', function () { return state.stats.top_song; }) : ''],
			[tr('Elf names'), s.elf, ''], [tr('Trivia games'), s.trivia, avg.replace(' · ', '')], [tr('Coloring pages'), s.coloring, tr('opened to print')],
			[tr('Kids’ Corner visits'), s.kids, ''], [tr('Website visits'), s.visit, ''],
			[tr('Names said out loud'), s.voice_made, tr('new voice clips')], [tr('Themed picks'), s.name_theme, tr('names sent with a theme')], [tr('Social posts'), s.social_post, 'Facebook, Instagram, Threads'],
			[tr('Traffic alerts'), s.traffic, ''], [tr('Phone alerts on'), s.alerts_on, tr('people who turned alerts on')], [tr('App installs'), s.app_install, '']].forEach(function (t) {
			var d = el('div', 'rrc__tile'); d.appendChild(el('small', '', t[0])); d.appendChild(el('b', '', (t[1] || 0).toLocaleString())); d.appendChild(el('span', '', t[2] || ' '));
			box.appendChild(d);
		});
	}
	function chart() {
		var plot = $('[data-rrc-plot]'), tip = $('[data-rrc-tip]'), old = plot.querySelector('svg');
		if (old) { old.remove(); }
		var data = state.stats.nights;
		if (!data.length) { data = [[tr('No show nights yet'), 0]]; }
		var max = Math.max(10, Math.ceil(Math.max.apply(null, data.map(function (d) { return d[1]; })) / 10) * 10);
		var W = 760, H = 230, ml = 34, mr = 8, mt = 10, mb = 26, pw = W - ml - mr, ph = H - mt - mb, bw = pw / Math.max(data.length, 7);
		var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', tr('Names sent each night'));
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
				tip.hidden = false; tip.textContent = tr('%1$s: %2$s names').replace('%1$s', function () { return d[0]; }).replace('%2$s', d[1]);
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
			if (j && j.success) { render(j.data); saved(j.data.pushed ? (j.data.pushed.sent === 1 ? tr('Canceled. Alert sent to %s phone') : tr('Canceled. Alert sent to %s phones')).replace('%s', j.data.pushed.sent) : (msg || tr('Saved'))); } else { saved(tr('Could not save. Please reload the page.')); refresh(); }
		}).catch(function () { busy--; saved(tr('Could not reach the website.')); });
	}
	$$('[data-rrc-tog]').forEach(function (b) {
		b.addEventListener('click', function () {
			var on = b.getAttribute('aria-checked') !== 'true', k = b.getAttribute('data-rrc-tog');
			if (k === 'cancel' && on) { cc.hidden = false; socialBox($('[data-rrc-social]'), state.social, false, true); $('[data-rrc-cancel-yes]').focus(); return; }
			b.setAttribute('aria-checked', String(on));
			act('rr_ctl_set', { key: k, value: on ? '1' : '0' });
		});
	});
	var cc = $('[data-rrc-cancel-confirm]');
	var srow = $('[data-rrc-cancel-social-row]'); if (srow) { srow.addEventListener('change', function () { srow.dataset.touched = '1'; }); }
	$('[data-rrc-cancel-no]').addEventListener('click', function () { cc.hidden = true; socialBox($('[data-rrc-social]'), state.social, false, false); });
	$('[data-rrc-cancel-yes]').addEventListener('click', function () {
		var alertToo = !$('[data-rrc-cancel-alert-row]').hidden && $('[data-rrc-cancel-alert]').checked;
		cc.hidden = true; $('[data-rrc-tog="cancel"]').setAttribute('aria-checked', 'true');
		var sr = $('[data-rrc-cancel-social-row]'), socialToo = sr && !sr.hidden ? ($('[data-rrc-cancel-social]').checked ? '1' : '0') : null;
		var args = { key: 'cancel', value: '1', alert: alertToo ? '1' : '' }; if (socialToo !== null) { args.social = socialToo; }
		act('rr_ctl_set', args, tr('Canceled for tonight'));
	});

	/* quick actions in the header */
	$$('[data-rrc-qa]').forEach(function (b) {
		b.addEventListener('click', function () {
			var k = b.getAttribute('data-rrc-qa');
			if (k === 'cancel') { var t = $('[data-rrc-tog="cancel"]'); t.scrollIntoView({ behavior: 'smooth', block: 'center' }); t.click(); }
			else if (k === 'pause') { $('[data-rrc-tog="paused"]').click(); }
			else if (k === 'traffic') { $('[data-rrc-traffic]').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			else if (k === 'alert') { var m = $('[data-rrc-msg]'); m.scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(function () { m.focus(); }, 400); }
		});
	});
	/* traffic & parking: one tap */
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-rrc-tsend]'); if (!b) { return; }
		var k = b.getAttribute('data-rrc-tsend'), txt = k === 'custom' ? $('[data-rrc-tcustom]').value.trim() : '';
		if (k === 'custom' && !txt) { $('[data-rrc-tcustom]').focus(); return; }
		var ar = $('[data-rrc-talert-row]');
		act('rr_ctl_traffic', { kind: k, text: txt, minutes: $('[data-rrc-tmin]').value, alert: ar && !ar.hidden && $('[data-rrc-talert]').checked ? '1' : '', screens: $('[data-rrc-tscreens]').checked ? '1' : '' }, tr('Traffic alert is on'));
		if (k === 'custom') { $('[data-rrc-tcustom]').value = ''; }
	});

	/* send an alert */
	var msgBox = $('[data-rrc-msg]'), sendRow = $('[data-rrc-send-row]'), sc = $('[data-rrc-send-confirm]');
	msgBox.addEventListener('input', function () { set('msg-count', msgBox.value.length + ' / 180'); sc.hidden = true; sendRow.hidden = false; });
	$('[data-rrc-send]').addEventListener('click', function () {
		var m = msgBox.value.trim();
		if (!m) { saved(tr('Type a message first.')); msgBox.focus(); return; }
		var n = state ? state.app.news : 0;
		set('send-q', (n === 1 ? tr('Send “%1$s” to %2$s phone?') : tr('Send “%1$s” to %2$s phones?')).replace('%1$s', function () { return m; }).replace('%2$s', n));
		sendRow.hidden = true; sc.hidden = false; $('[data-rrc-send-yes]').focus();
	});
	$('[data-rrc-send-no]').addEventListener('click', function () { sc.hidden = true; sendRow.hidden = false; });
	$('[data-rrc-send-yes]').addEventListener('click', function () {
		var b = this; b.disabled = true; saved(tr('Sending…'));
		busy++;
		call('rr_ctl_push', { message: msgBox.value.trim() }).then(function (j) {
			busy--; b.disabled = false; sc.hidden = true; sendRow.hidden = false;
			if (j && j.success) {
				render(j.data); msgBox.value = ''; set('msg-count', '0 / 180');
				var p = j.data.pushed; saved((p.sent === 1 ? tr('Sent to %s phone') : tr('Sent to %s phones')).replace('%s', p.sent) + (p.failed ? ' ' + tr('(%s did not go through)').replace('%s', p.failed) : ''));
			} else { saved((j && j.data && j.data.message) || tr('Could not send. Please reload the page.')); }
		}).catch(function () { busy--; b.disabled = false; saved(tr('Could not reach the website.')); });
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
