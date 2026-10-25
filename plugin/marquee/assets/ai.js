/* Marquee: "Need help?" assistant on the Settings page. */
(function () {
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var C = window.MarqueeAdmin || {};
	var box = document.querySelector('[data-mq-ai]'), fab = document.querySelector('[data-mq-ai-open]');
	if (!box || !fab) { return; }
	var log = box.querySelector('[data-mq-ai-log]'), form = box.querySelector('[data-mq-ai-form]'), input = box.querySelector('[data-mq-ai-in]');
	var keybox = box.querySelector('[data-mq-ai-keybox]'), chips = box.querySelector('[data-mq-ai-chips]');
	var KEY = 'mq-ai-chat', chat = [];
	try { chat = JSON.parse(sessionStorage.getItem(KEY) || '[]') || []; } catch (e) { chat = []; }

	function post(action, data) {
		var fd = new FormData(); fd.append('action', action); fd.append('nonce', C.nonce);
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	/* a little Markdown: bold, code, links, lists, paragraphs */
	function md(t) {
		var h = esc(t)
			.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
			.replace(/`([^`]+)`/g, '<code>$1</code>')
			.replace(/\[([^\]]+)\]\((https:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
		var out = [], list = null;
		h.split('\n').forEach(function (l) {
			var m = /^\s*(?:(\d+)[.)]|[-*•])\s+(.*)$/.exec(l);
			if (m) {
				var tag = m[1] ? 'ol' : 'ul';
				if (!list || list.tag !== tag) { if (list) { out.push('</' + list.tag + '>'); } list = { tag: tag }; out.push('<' + tag + '>'); }
				out.push('<li>' + m[2] + '</li>');
			} else {
				if (list) { out.push('</' + list.tag + '>'); list = null; }
				var hd = /^#{1,4}\s+(.*)$/.exec(l);
				if (hd) { out.push('<p><b>' + hd[1] + '</b></p>'); } else if (l.trim()) { out.push('<p>' + l + '</p>'); }
			}
		});
		if (list) { out.push('</' + list.tag + '>'); }
		return out.join('');
	}
	function add(role, text, cls) {
		var d = document.createElement('div');
		d.className = 'mq-ai__msg ' + (role === 'user' ? 'is-me' : 'is-bot') + (cls ? ' ' + cls : '');
		if (role === 'user') { d.textContent = text; } else { d.innerHTML = md(text); }
		log.appendChild(d); log.scrollTop = log.scrollHeight;
		return d;
	}
	function save() { try { sessionStorage.setItem(KEY, JSON.stringify(chat.slice(-20))); } catch (e) {} }
	chat.forEach(function (m) { add(m.role, m.text); });
	if (chat.length) { chips.hidden = true; }

	function open(on) {
		box.hidden = !on; fab.setAttribute('aria-expanded', on ? 'true' : 'false'); fab.hidden = on;
		if (on) { (box.getAttribute('data-ready') === '1' ? input : box.querySelector('[data-mq-ai-keyin]')).focus(); }
	}
	fab.addEventListener('click', function () { open(true); });
	box.querySelector('[data-mq-ai-close]').addEventListener('click', function () { open(false); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) { open(false); } });

	function ask(q) {
		q = q.trim(); if (!q) { return; }
		if (box.getAttribute('data-ready') !== '1') { keybox.hidden = false; return; }
		chips.hidden = true; input.value = '';
		chat.push({ role: 'user', text: q }); add('user', q); save();
		var wait = add('bot', '…', 'is-wait'), btn = form.querySelector('button'); btn.disabled = true;
		post('marquee_ai_ask', { messages: JSON.stringify(chat) }).then(function (j) {
			btn.disabled = false; wait.remove();
			if (j && j.success) { chat.push({ role: 'assistant', text: j.data.text }); add('bot', j.data.text); save(); return; }
			chat.pop(); save();
			add('bot', (j && j.data && j.data.message) || tr('Something went wrong. Please try again.'), 'is-err');
			if (j && j.data && j.data.need_key) { keybox.hidden = false; box.setAttribute('data-ready', '0'); }
		}).catch(function () { btn.disabled = false; wait.remove(); chat.pop(); save(); add('bot', tr('Couldn’t reach your website. Try again.'), 'is-err'); });
	}
	form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
	input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); } });
	box.querySelectorAll('[data-mq-ai-chip]').forEach(function (b) { b.addEventListener('click', function () { ask(b.textContent); }); });

	/* Which AI answers: ChatGPT or Claude (worked out from the key). */
	var AIS = {}; try { AIS = JSON.parse(box.getAttribute('data-ais') || '{}'); } catch (e) {}
	var saved = (box.getAttribute('data-saved') || '').split(',').filter(Boolean);
	var who = box.querySelector('[data-mq-ai-who]'), kc = box.querySelector('[data-mq-ai-keyclear]');
	function setAI(p) {
		box.setAttribute('data-ai', p || ''); box.setAttribute('data-ready', p ? '1' : '0');
		if (p && saved.indexOf(p) < 0) { saved.push(p); }
		var a = AIS[p];
		box.querySelectorAll('[data-mq-ai-name]').forEach(function (n) { n.textContent = a ? a.name : ''; });
		var to = box.querySelector('[data-mq-ai-to]'); if (to) { to.textContent = a ? a.name + ' (' + a.by + ')' : tr('the AI you pick'); }
		if (who) { who.hidden = !a; }
		if (kc) { kc.hidden = !a; }
		box.querySelectorAll('[data-mq-ai-pick]').forEach(function (c) { c.classList.toggle('is-on', c.getAttribute('data-mq-ai-pick') === p); });
		box.querySelectorAll('[data-mq-ai-use]').forEach(function (b) { var id = b.getAttribute('data-mq-ai-use'); b.hidden = id === p || saved.indexOf(id) < 0; });
	}
	var ch = box.querySelector('[data-mq-ai-change]');
	if (ch) { ch.addEventListener('click', function () { keybox.hidden = !keybox.hidden; if (!keybox.hidden) { box.querySelector('[data-mq-ai-keyin]').focus(); } }); }
	box.querySelectorAll('[data-mq-ai-use]').forEach(function (b) {
		b.addEventListener('click', function () {
			b.disabled = true;
			post('marquee_ai_key', { use: b.getAttribute('data-mq-ai-use') }).then(function (j) {
				b.disabled = false;
				if (j && j.success) { setAI(j.data.provider); keybox.hidden = true; add('bot', tr('OK, %s answers from now on.').replace('%s', function () { return j.data.name; })); input.focus(); }
			});
		});
	});

	var ks = box.querySelector('[data-mq-ai-keysave]'), kin = box.querySelector('[data-mq-ai-keyin]'), kmsg = box.querySelector('[data-mq-ai-keymsg]');
	if (ks) {
		ks.addEventListener('click', function () {
			if (!kin.value.trim()) { kin.focus(); return; }
			ks.disabled = true; kmsg.textContent = tr('Saving…');
			post('marquee_ai_key', { key: kin.value }).then(function (j) {
				ks.disabled = false;
				if (j && j.success && j.data.saved) { kmsg.textContent = ''; keybox.hidden = true; kin.value = ''; setAI(j.data.provider); add('bot', tr('Got it, %s will answer. Ask away!').replace('%s', function () { return j.data.name; })); input.focus(); }
				else { kmsg.textContent = (j && j.data && j.data.message) || tr('Couldn’t save the key.'); }
			}).catch(function () { ks.disabled = false; kmsg.textContent = tr('Couldn’t reach your website.'); });
		});
	}
	if (kc) {
		kc.addEventListener('click', function () {
			var gone = box.getAttribute('data-ai');
			post('marquee_ai_key', { key: '' }).then(function (j) {
				saved = saved.filter(function (x) { return x !== gone; });
				var np = j && j.success && j.data.saved ? j.data.provider : '';
				setAI(np);
				if (!np) { keybox.hidden = false; }
			});
		});
	}
})();
