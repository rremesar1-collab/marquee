/* Marquee → Get help: the support chat, session controls and the live FPP view. */
(function () {
	'use strict';
	var C = window.MQSupport, root = document.querySelector('[data-mq-sup]');
	if (!C || !root) { return; }
	var $ = function (s) { return root.querySelector(s); };
	var next = 0, timer = null, busy = false, active = root.getAttribute('data-active') === '1';
	var msgs = $('[data-mq-sup-msgs]');

	function post(action, data, file) {
		var f = new FormData();
		f.append('action', action); f.append('nonce', C.nonce); f.append('since', next);
		Object.keys(data || {}).forEach(function (k) { f.append(k, data[k]); });
		if (file) { f.append('img', file); }
		return fetch(C.ajax, { method: 'POST', credentials: 'same-origin', body: f }).then(function (r) { return r.json(); });
	}
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function who(w) {
		if (w === 'system') { return ''; }
		if (w === C.who) { return 'You'; }
		return w === 'support' ? 'Marquee Support' : 'Show owner';
	}
	function add(list) {
		var atBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 60;
		(list || []).forEach(function (m) {
			if (msgs.querySelector('[data-i="' + m.i + '"]')) { return; }
			var li = document.createElement('li');
			li.setAttribute('data-i', m.i);
			li.className = 'mq-sup__m mq-sup__m--' + (m.who === 'system' ? 'sys' : (m.who === C.who ? 'me' : 'them'));
			li.innerHTML = (who(m.who) ? '<b>' + esc(who(m.who)) + '</b>' : '') +
				(m.text ? '<p>' + esc(m.text).replace(/\n/g, '<br>') + '</p>' : '') +
				(m.img ? '<a href="' + esc(m.full) + '" target="_blank" rel="noopener"><img src="' + esc(m.img) + '" alt="Picture"></a>' : '') +
				'<time>' + esc(m.time) + '</time>';
			msgs.appendChild(li);
		});
		if (atBottom || (list && list.length)) { msgs.scrollTop = msgs.scrollHeight; }
	}
	function fpp(v) {
		var box = $('[data-mq-sup-fpp]');
		if (!box || !v) { return; }
		var rows = [
			['Status', v.status + (v.status && v.status !== 'playing' ? ' (names only go up while a playlist is playing)' : '')],
			['Playlist', v.playlist], ['Sequence', v.sequence], ['Song', v.song],
			['Names', v.mode === 'live' ? 'during songs' : 'between songs' + (v.break_playlist ? ' · break playlist: ' + v.break_playlist : '')],
			['FPP', v.fpp_version], ['Names script', v.v + (v.plugin ? ' (Marquee plugin)' : ' (by hand)')], ['Checked in', v.ago + ' ago']
		];
		var h = '<dl>' + rows.filter(function (r) { return r[1]; }).map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('') + '</dl>';
		if (v.screens && v.screens.length) {
			h += '<p><b>Screens</b></p><ul class="mq-sup__scr">' + v.screens.map(function (s) {
				return '<li class="' + (s.ok ? 'is-ok' : 'is-bad') + '">' + (s.ok ? '✓ ' : '✗ ') + esc(s.name || s.model) + ' <small>' + esc([s.fpp, s.model, s.size].filter(Boolean).join(' · ')) + (s.error ? ' — ' + esc(s.error) : '') + '</small></li>';
			}).join('') + '</ul>';
		}
		if (v.sequences && v.sequences.length) { h += '<details><summary>Sequences on the FPP (' + v.sequences.length + ')</summary><p>' + v.sequences.map(esc).join('<br>') + '</p></details>'; }
		if (v.playlists && v.playlists.length) { h += '<details><summary>Playlists on the FPP (' + v.playlists.length + ')</summary><p>' + v.playlists.map(esc).join('<br>') + '</p></details>'; }
		if (v.log && v.log.length) { h += '<details' + (C.who === 'support' ? ' open' : '') + '><summary>Names script log</summary><pre>' + v.log.map(esc).join('\n') + '</pre></details>'; }
		var open = Array.prototype.map.call(box.querySelectorAll('details'), function (d) { return d.open; });
		box.innerHTML = h;
		box.querySelectorAll('details').forEach(function (d, k) { if (open[k] !== undefined) { d.open = open[k]; } });
	}
	function apply(d) {
		if (!d) { return; }
		add(d.msgs); next = d.next;
		var ends = $('[data-mq-sup-ends]'); if (ends) { ends.textContent = d.ends; }
		var here = $('[data-mq-sup-here]'); if (here) { here.hidden = !(C.who === 'owner' ? d.support_here : d.active); }
		if (d.fpp) { fpp(d.fpp); }
		if (active && !d.active) { ended(); }
		active = d.active;
	}
	function ended() {
		['[data-mq-sup-form]', '[data-mq-sup-session]', '[data-mq-sup-fppcard]'].forEach(function (s) { var e = $(s); if (e) { e.hidden = true; } });
		var e = $('[data-mq-sup-ended]'); if (e) { e.hidden = false; }
		document.querySelectorAll('.mq-sup-banner').forEach(function (b) { b.remove(); });
		clearTimeout(timer);
	}
	function poll() {
		clearTimeout(timer);
		post('marquee_support_poll').then(function (r) { if (r && r.success) { apply(r.data); } }).catch(function () {}).then(function () {
			if (active) { timer = setTimeout(poll, document.hidden ? 20000 : 4000); }
		});
	}

	/* Start a request. */
	var go = $('[data-mq-sup-go]');
	if (go) {
		go.addEventListener('click', function () {
			var t = $('[data-mq-sup-first]').value.trim(), err = $('[data-mq-sup-err]');
			if (t.length < 5) { err.textContent = 'Please tell Marquee Support what’s wrong.'; return; }
			go.disabled = true; err.textContent = 'Sending…';
			post('marquee_support_start', { message: t, access: $('[data-mq-sup-access]').checked ? 1 : '' }).then(function (r) {
				if (!r.success) { go.disabled = false; err.textContent = (r.data && r.data.message) || 'Something went wrong. Please try again.'; return; }
				$('[data-mq-sup-start]').hidden = true;
				msgs.innerHTML = ''; next = 0;
				['[data-mq-sup-main]', '[data-mq-sup-form]', '[data-mq-sup-session]', '[data-mq-sup-fppcard]'].forEach(function (s) { var e = $(s); if (e) { e.hidden = false; } });
				$('[data-mq-sup-ended]').hidden = true;
				active = true; apply(r.data); poll();
			}).catch(function () { go.disabled = false; err.textContent = 'Something went wrong. Please try again.'; });
		});
	}

	/* Send a message (and maybe a picture). */
	var form = $('[data-mq-sup-form]'), text = $('[data-mq-sup-text]'), pic = $('[data-mq-sup-img]'), picname = $('[data-mq-sup-picname]');
	if (pic) { pic.addEventListener('change', function () { picname.textContent = pic.files[0] ? pic.files[0].name + ' ✕' : ''; }); }
	if (picname) { picname.addEventListener('click', function () { pic.value = ''; picname.textContent = ''; }); }
	if (text) {
		text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
		text.addEventListener('paste', function (e) {   // paste a screenshot
			var it = Array.prototype.find.call((e.clipboardData || {}).items || [], function (x) { return x.type && x.type.indexOf('image/') === 0; });
			if (!it) { return; }
			var f = it.getAsFile(), dt = new DataTransfer();
			dt.items.add(new File([f], 'screenshot.png', { type: f.type }));
			pic.files = dt.files; picname.textContent = 'screenshot.png ✕';
		});
	}
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (busy) { return; }
			var t = text.value.trim(), f = pic.files[0];
			if (!t && !f) { return; }
			busy = true; form.classList.add('is-busy');
			post('marquee_support_send', { text: t }, f).then(function (r) {
				if (r.success) { text.value = ''; pic.value = ''; picname.textContent = ''; apply(r.data); }
				else { window.alert((r.data && r.data.message) || 'That didn’t send. Please try again.'); }
			}).catch(function () { window.alert('That didn’t send. Please try again.'); }).then(function () { busy = false; form.classList.remove('is-busy'); text.focus(); });
		});
	}

	/* End, extend, FPP tools. */
	var end = $('[data-mq-sup-end]');
	if (end) {
		end.addEventListener('click', function () {
			if (C.who === 'owner' && !window.confirm(C.confirm)) { return; }
			end.disabled = true;
			post('marquee_support_end').then(function (r) {
				if (r.data && r.data.support) { window.location.href = C.site; return; }
				ended(); post('marquee_support_poll').then(function (p) { if (p.success) { apply(p.data); } });
			});
		});
	}
	var ext = $('[data-mq-sup-extend]');
	if (ext) { ext.addEventListener('click', function () { post('marquee_support_extend').then(function (r) { if (r.success) { apply(r.data); } }); }); }
	var ref = $('[data-mq-sup-refresh]');
	if (ref) { ref.addEventListener('click', function () { post('marquee_support_fpp_ask').then(poll); }); }
	var test = $('[data-mq-sup-test]');
	if (test) {
		test.addEventListener('click', function () {
			var t = $('[data-mq-sup-testtext]').value.trim() || 'TEST';
			test.disabled = true; setTimeout(function () { test.disabled = false; }, 8000);
			post('marquee_support_fpp_ask', { test: t }).then(poll);
		});
	}

	root.querySelectorAll('[data-mq-sup-do]').forEach(function (b) {
		b.addEventListener('click', function () {
			b.disabled = true; setTimeout(function () { b.disabled = false; }, 8000);
			post('marquee_support_fpp_ask', { 'do': b.getAttribute('data-mq-sup-do') }).then(function (r) { if (r && !r.success && r.data && r.data.message) { window.alert(r.data.message); } poll(); });
		});
	});
	if (location.hash === '#end' && end) { end.scrollIntoView({ block: 'center' }); end.focus(); }
	document.addEventListener('visibilitychange', function () { if (!document.hidden && active) { poll(); } });
	post('marquee_support_poll').then(function (r) { if (r && r.success) { apply(r.data); } if (active) { timer = setTimeout(poll, 4000); } });
})();
