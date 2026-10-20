/* Marquee Show Manager (yourshow.com/manage): show-night controls on a phone. */
(function () {
	'use strict';
	var cfg = window.MQMgr || {};
	var root = document.querySelector('[data-mm]');
	if (!root) { return; }
	var $ = function (s) { return document.querySelector(s); };
	var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
	var state = null, busy = 0, toastT;

	function call(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
			if (r.status === 403 || r.status === 400) { return { success: false, gone: true }; }
			return r.json();
		});
	}
	function toast(m) { var t = $('[data-mm-toast]'); t.textContent = m; t.classList.add('is-on'); clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove('is-on'); }, 2600); }
	function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt !== undefined && txt !== null) { e.textContent = txt; } return e; }
	function set(k, txt) { var e = $('[data-mm="' + k + '"]'); if (e) { e.textContent = txt; } return e; }
	function stCls(k, cls) { var e = $('[data-mm-st="' + k + '"]'); if (e) { e.className = 'mm-st' + (cls ? ' is-' + cls : ''); } }
	function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
	function btn(txt, cls, fn) { var b = el('button', 'mm-btn ' + (cls || ''), txt); b.type = 'button'; b.addEventListener('click', fn); return b; }
	function empty(ul, txt) { ul.appendChild(el('li', 'empty', txt)); }
	function ago(s) {
		if (s === null || s === undefined) { return 'FPP hasn’t checked in yet'; }
		if (s < 90) { return 'FPP checked in ' + s + ' sec ago'; }
		if (s < 5400) { return 'FPP checked in ' + Math.round(s / 60) + ' min ago'; }
		return 'FPP last checked in ' + Math.round(s / 3600) + ' h ago';
	}

	/* Social posts beside the cancel switch: a hint to set it up, or what was posted tonight. */
	function socialBox(box, so, canceled, confirmOpen) {
		if (!box) { return; }
		so = so || {};
		var h = '';
		function esc(t) { return String(t || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
		if (!so.ready) {
			if (confirmOpen || canceled) { h = '<p class="so-hint">' + esc(so.hint || '') + ' <a href="' + esc(so.setup || '#') + '">Set it up</a></p>'; }
		} else if (canceled) {
			if (so.posting) { h += '<p class="so-wait">Posting on social media…</p>'; }
			if (so.last && so.last.what === 'cancel' && so.last.lines && so.last.lines.length) {
				h += '<ul class="so-lines">' + so.last.lines.map(function (l) {
					return '<li class="' + (l.ok ? 'is-ok' : 'is-bad') + '">' + (l.ok ? '✓ ' : '✗ ') + (l.net ? esc(l.net) + ': ' : '') + esc(l.msg) + (l.link ? ' <a href="' + esc(l.link) + '" target="_blank" rel="noopener">View</a>' : '') + '</li>';
				}).join('') + '</ul>';
			}
		}
		if (canceled && so.text) { h += '<p><button type="button" class="so-copy" data-so-copy>Copy the post for other sites (Nextdoor, groups…)</button> <span class="so-copied" aria-live="polite"></span></p>'; }
		if (box.getAttribute('data-h') !== h) { box.innerHTML = h; box.setAttribute('data-h', h); }
		box.hidden = !h;
		var cp = box.querySelector('[data-so-copy]');
		if (cp && !cp.dataset.on) {
			cp.dataset.on = '1';
			cp.addEventListener('click', function () {
				var done = function () { box.querySelector('.so-copied').textContent = 'Copied!'; };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(so.text).then(done, function () {}); }
				else { var t = document.createElement('textarea'); t.value = so.text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} t.remove(); }
			});
		}
	}

	function render(d) {
		state = d;
		var sw = d.switches;

		/* status */
		var sc = { running: 'good', canceled: 'bad' }[d.show.state] || '';
		set('show', d.show.label); set('show-sub', d.show.sub); stCls('show', sc);
		var f = d.fpp, fresh = f.seen !== null && f.seen < 120;
		if (!fresh) { set('fpp', f.seen === null ? 'Not connected' : 'Not checking in'); stCls('fpp', f.seen === null ? '' : 'warn'); }
		else if (f.status === 'playing') { set('fpp', f.sequence || 'Playing'); stCls('fpp', 'good'); }
		else { set('fpp', f.status ? f.status.charAt(0).toUpperCase() + f.status.slice(1) : 'Idle'); stCls('fpp', ''); }
		set('fpp-sub', (fresh && f.playlist ? f.playlist + ' · ' : '') + ago(f.seen));
		var ncls = !sw.names_on ? '' : (sw.paused ? 'warn' : (d.names.open ? 'good' : ''));
		set('names', !sw.names_on ? 'Off' : (sw.paused ? 'Paused' : (d.names.open ? 'Open' : 'Closed')));
		set('names-sub', plural(d.names.waiting.length, 'name waiting', 'names waiting'));
		stCls('names', ncls);
		set('people', plural(d.people.now, 'person', 'people'));
		set('people-sub', d.people.kids + ' in the Kids’ Corner');

		/* cancel card */
		$('[data-mm-cancel-off]').hidden = !!sw.cancel;
		$('[data-mm-cancel-on]').hidden = !sw.cancel;
		$('[data-mm-cancel]').classList.toggle('is-canceled', !!sw.cancel);

		/* switches */
		if (!busy) {
			$$('[data-mm-tog]').forEach(function (b) { b.setAttribute('aria-checked', String(!!sw[b.getAttribute('data-mm-tog')])); });
			var gt = $('[data-mm-tog="names_geo"]'), gs = gt && gt.parentNode.querySelector('small');
			if (gs) { var gm = parseFloat(sw.names_geo_mi || '0.5'); gs.textContent = 'Names only from phones within ' + (sw.names_geo_km ? (Math.round(gm * 16.09344) / 10) + ' km' : gm + ' mi') + ' of your show. You can still send test names from anywhere.'; }
			var sn = $('[data-mm-set="snow_amount"]'); if (document.activeElement !== sn) { sn.value = sw.snow_amount || 'off'; }
		}

		/* tonight tiles */
		var s = d.stats.tonight || {}, box = $('[data-mm-tiles]'); box.innerHTML = '';
		[['Website visits', s.visit], ['Names sent', s.name_sent], ['Names shown', s.name_shown], ['Light Lab plays', s.lab_play], ['Song votes', s.song_vote], ['Hearts', s.heart]].forEach(function (t) {
			var x = el('div', 'mm-tile'); x.appendChild(el('b', '', (t[1] || 0).toLocaleString())); x.appendChild(el('small', '', t[0])); box.appendChild(x);
		});

		/* names */
		var w = $('[data-mm-list="waiting"]'); w.innerHTML = '';
		if (!d.names.waiting.length) { empty(w, 'No names waiting.'); }
		d.names.waiting.forEach(function (n, i) {
			var li = el('li'); li.appendChild(el('span', 'mm-n', i + 1));
			var t = el('span', 'mm-t'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', 'sent ' + n.time)); li.appendChild(t);
			var a = el('span', 'mm-act');
			a.appendChild(btn('Remove', '', function () { act('rr_ctl_name', { id: n.id }, 'Removed'); }));
			a.appendChild(btn('Block', 'del', function () { act('rr_ctl_name', { id: n.id, block: 1 }, 'Removed and blocked'); }));
			li.appendChild(a); w.appendChild(li);
		});
		set('shown-n', d.names.shown_tonight ? d.names.shown_tonight + ' tonight' : '');
		var sh = $('[data-mm-list="shown"]'); sh.innerHTML = '';
		if (!d.names.shown.length) { empty(sh, 'Nothing shown yet.'); }
		d.names.shown.forEach(function (n) { var li = el('li'), t = el('span', 'mm-t'); t.appendChild(el('b', '', n.text)); t.appendChild(el('small', '', n.time)); li.appendChild(t); sh.appendChild(li); });

		/* approve: contest */
		var c = d.contest;
		set('contest-sub', c.total ? plural(c.total, 'entry', 'entries') : '');
		var pd = $('[data-mm-list="pending"]'); pd.innerHTML = '';
		if (!c.pending.length) { empty(pd, sw.contest_on ? 'All caught up.' : 'The contest is off.'); }
		c.pending.forEach(function (e) {
			var li = el('li');
			if (e.thumb) { var im = el('img'); im.src = e.thumb; im.alt = ''; im.loading = 'lazy'; li.appendChild(im); }
			var t = el('span', 'mm-t'); t.appendChild(el('b', '', e.title || 'My Light Show')); t.appendChild(el('small', '', e.name + ', age ' + e.age)); li.appendChild(t);
			var a = el('span', 'mm-act');
			var wa = el('a', 'mm-btn', 'Watch'); wa.href = e.watch; wa.target = '_blank'; wa.rel = 'noopener'; a.appendChild(wa);
			a.appendChild(btn('Approve', 'pri', function () { act('rr_ctl_entry', { id: e.id, approve: 1 }, 'Approved: now on the Show Wall'); }));
			a.appendChild(btn('Decline', 'del', function () { act('rr_ctl_entry', { id: e.id }, 'Declined'); }));
			li.appendChild(a); pd.appendChild(li);
		});

		/* approve: guestbook */
		var gb = d.guestbook || [], gl = $('[data-mm-list="gb"]'); gl.innerHTML = '';
		if (!gb.length) { empty(gl, 'No new messages.'); }
		gb.forEach(function (m) {
			var li = el('li'), t = el('span', 'mm-t');
			t.appendChild(el('b', '', m.name)); t.appendChild(el('q', '', m.text)); t.appendChild(el('small', '', m.when)); li.appendChild(t);
			var a = el('span', 'mm-act');
			a.appendChild(btn('Approve', 'pri', function () { act('rr_ctl_gb', { id: m.id, approve: 1 }, 'Approved: now in the guestbook'); }));
			a.appendChild(btn('Delete', 'del', function () { act('rr_ctl_gb', { id: m.id }, 'Deleted'); }));
			li.appendChild(a); gl.appendChild(li);
		});

		/* badges */
		badge('names', d.names.waiting.length);
		badge('approve', c.pending.length + gb.length);

		/* alerts */
		var ap = d.app;
		$('[data-mm-send-card]').hidden = !ap.ready;
		$('[data-mm-noapp]').hidden = !!ap.ready;
		set('app-to', 'Goes to ' + plural(ap.news, 'phone', 'phones') + ' that chose “special news”.');
		set('cancel-alert-label', 'Also send a “No show tonight” alert to ' + plural(ap.news, 'phone', 'phones'));
		$('[data-mm-cancel-alert-row]').hidden = !ap.ready || !ap.news;
		/* traffic & parking */
		var tr = d.traffic || {}, tn = tr.now, tb = $('[data-mm-tbtns]'), tbox = $('[data-mm-tnow]');
		if (tb && !tb.children.length && tr.presets) {
			Object.keys(tr.presets).forEach(function (k) {
				var b = document.createElement('button'); b.type = 'button'; b.className = 'mm-tbtn'; b.setAttribute('data-mm-tsend', k);
				b.innerHTML = '<span class="ic"></span><span class="lb"></span>'; b.querySelector('.ic').textContent = tr.presets[k].icon; b.querySelector('.lb').textContent = tr.presets[k].label; tb.appendChild(b);
			});
		}
		document.querySelectorAll('[data-mm-tsend]').forEach(function (b) { b.classList.toggle('is-on', !!tn && tn.kind === b.getAttribute('data-mm-tsend')); });
		if (tbox) {
			tbox.hidden = !tn; tbox.innerHTML = '';
			if (tn) {
				var tt = document.createElement('div'); tt.innerHTML = '<b></b><small></small>'; tt.querySelector('b').textContent = tn.icon + ' ' + tn.title;
				tt.querySelector('small').textContent = 'On your website until ' + tn.until + ' (' + tn.left + ' left)';
				var off = document.createElement('button'); off.type = 'button'; off.className = 'mm-btn'; off.textContent = 'Turn it off';
				off.addEventListener('click', function () { act('rr_ctl_traffic', { kind: 'clear' }, 'Traffic alert off'); });
				tbox.appendChild(tt); tbox.appendChild(off);
			}
		}
		var tar = $('[data-mm-talert-row]'); if (tar) { tar.hidden = !(d.app && d.app.ready && d.app.news); set('talert-label', 'Also send a phone alert to ' + plural((d.app && d.app.news) || 0, 'phone', 'phones')); }
		socialBox($('[data-mm-social-hint]'), d.social, false, true);
		socialBox($('[data-mm-social-result]'), d.social, !!sw.cancel, false);
		var so = d.social || {}, sr = $('[data-mm-cancel-social-row]');
		if (sr) { sr.hidden = !so.ready; set('cancel-social-label', so.label || ''); if (!sr.dataset.touched) { $('[data-mm-cancel-social]').checked = !!so.auto; } }
		var kinds = { auto: 'Automatic', cancel: 'Show canceled', you: 'Sent by you' };
		var ul = $('[data-mm-list="alerts"]'); ul.innerHTML = '';
		if (!ap.log.length) { empty(ul, 'No alerts sent yet.'); }
		ap.log.forEach(function (l) {
			var li = el('li'), t = el('span', 'mm-t'); t.appendChild(el('b', '', l.kind === 'you' ? l.body : l.title)); t.appendChild(el('small', '', (kinds[l.kind] || '') + ' · ' + l.when + ' · ' + l.sent + ' sent')); li.appendChild(t); ul.appendChild(li);
		});
	}
	function badge(k, n) { var b = $('[data-mm-badge="' + k + '"]'); b.hidden = !n; b.textContent = n > 99 ? '99+' : n; }

	function act(action, data, msg) {
		busy++;
		return call(action, data).then(function (j) {
			busy--;
			if (j && j.success) {
				render(j.data);
				toast(j.data.pushed ? 'Done. Alert sent to ' + plural(j.data.pushed.sent, 'phone', 'phones') : (msg || 'Saved'));
			} else if (j && j.gone) { toast('You’ve been signed out. Reloading…'); setTimeout(function () { location.reload(); }, 1200); }
			else { toast('Couldn’t save. Try again.'); refresh(); }
		}).catch(function () { busy--; toast('No connection. Check your phone’s signal.'); });
	}

	/* tabs */
	function show(k, focus) {
		$$('[data-mm-tab]').forEach(function (b) { var on = b.getAttribute('data-mm-tab') === k; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; if (on && focus) { b.focus(); } });
		$$('[data-mm-pane]').forEach(function (p) { p.classList.toggle('is-on', p.getAttribute('data-mm-pane') === k); });
		try { sessionStorage.setItem('mm-tab', k); } catch (e) {}
		window.scrollTo(0, 0);
	}
	$$('[data-mm-tab]').forEach(function (b, i, all) {
		b.addEventListener('click', function () { show(b.getAttribute('data-mm-tab')); });
		b.addEventListener('keydown', function (e) {
			var d = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
			if (d) { show(all[(i + d + all.length) % all.length].getAttribute('data-mm-tab'), true); }
		});
	});
	try { var last = sessionStorage.getItem('mm-tab'); if (last && $('[data-mm-tab="' + last + '"]')) { show(last); } } catch (e) {}

	/* switches */
	$$('[data-mm-tog]').forEach(function (b) {
		b.addEventListener('click', function () {
			var on = b.getAttribute('aria-checked') !== 'true';
			b.setAttribute('aria-checked', String(on));
			act('rr_ctl_set', { key: b.getAttribute('data-mm-tog'), value: on ? '1' : '0' }, on ? 'Turned on' : 'Turned off');
		});
	});
	$$('[data-mm-set]').forEach(function (i) { i.addEventListener('change', function () { act('rr_ctl_set', { key: i.getAttribute('data-mm-set'), value: i.value }); }); });

	/* traffic & parking: one tap */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-mm-tsend]'); if (!b) { return; }
		var k = b.getAttribute('data-mm-tsend'), inp = $('[data-mm-tcustom]'), txt = k === 'custom' ? inp.value.trim() : '';
		if (k === 'custom' && !txt) { inp.focus(); return; }
		var tar = $('[data-mm-talert-row]');
		act('rr_ctl_traffic', { kind: k, text: txt, minutes: $('[data-mm-tmin]').value, alert: tar && !tar.hidden && $('[data-mm-talert]').checked ? '1' : '', screens: $('[data-mm-tscreens]').checked ? '1' : '' }, 'Traffic alert is on');
		if (k === 'custom') { inp.value = ''; }
	});

	/* cancel */
	var cc = $('[data-mm-cancel-confirm]'), ask = $('[data-mm-cancel-ask]');
	var msr = $('[data-mm-cancel-social-row]'); if (msr) { msr.addEventListener('change', function () { msr.dataset.touched = '1'; }); }
	ask.addEventListener('click', function () { cc.hidden = false; ask.hidden = true; $('[data-mm-cancel-yes]').focus(); });
	$('[data-mm-cancel-no]').addEventListener('click', function () { cc.hidden = true; ask.hidden = false; });
	$('[data-mm-cancel-yes]').addEventListener('click', function () {
		var alertToo = !$('[data-mm-cancel-alert-row]').hidden && $('[data-mm-cancel-alert]').checked;
		cc.hidden = true; ask.hidden = false;
		var sr = $('[data-mm-cancel-social-row]'), socialToo = sr && !sr.hidden ? ($('[data-mm-cancel-social]').checked ? '1' : '0') : null;
		var args = { key: 'cancel', value: '1', alert: alertToo ? '1' : '' }; if (socialToo !== null) { args.social = socialToo; }
		act('rr_ctl_set', args, 'Tonight’s show is canceled');
	});
	$('[data-mm-cancel-undo]').addEventListener('click', function () { act('rr_ctl_set', { key: 'cancel', value: '0' }, 'The show is back on'); });

	/* send an alert */
	var msgBox = $('[data-mm-msg]'), sendRow = $('[data-mm-send-row]'), sc = $('[data-mm-send-confirm]');
	msgBox.addEventListener('input', function () { set('msg-count', msgBox.value.length + ' / 180'); sc.hidden = true; sendRow.hidden = false; });
	$('[data-mm-send]').addEventListener('click', function () {
		var m = msgBox.value.trim();
		if (!m) { toast('Type a message first.'); msgBox.focus(); return; }
		set('send-q', 'Send “' + m + '” to ' + plural(state ? state.app.news : 0, 'phone', 'phones') + '?');
		sendRow.hidden = true; sc.hidden = false; $('[data-mm-send-yes]').focus();
	});
	$('[data-mm-send-no]').addEventListener('click', function () { sc.hidden = true; sendRow.hidden = false; });
	$('[data-mm-send-yes]').addEventListener('click', function () {
		var b = this; b.disabled = true; toast('Sending…'); busy++;
		call('rr_ctl_push', { message: msgBox.value.trim() }).then(function (j) {
			busy--; b.disabled = false; sc.hidden = true; sendRow.hidden = false;
			if (j && j.success) {
				render(j.data); msgBox.value = ''; set('msg-count', '0 / 180');
				var p = j.data.pushed; toast('Sent to ' + plural(p.sent, 'phone', 'phones') + (p.failed ? ' (' + p.failed + ' didn’t go through)' : ''));
			} else { toast((j && j.data && j.data.message) || 'Couldn’t send. Try again.'); }
		}).catch(function () { busy--; b.disabled = false; toast('No connection. Check your phone’s signal.'); });
	});

	function refresh() {
		if (busy || document.hidden) { return; }
		call('rr_ctl_state').then(function (j) {
			if (j && j.success) { render(j.data); } else if (j && j.gone) { location.reload(); }
		}).catch(function () {});
	}
	refresh(); setInterval(refresh, 15000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
})();
