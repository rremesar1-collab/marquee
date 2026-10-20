/* Marquee: "Need help?" assistant on the Settings page. */
(function () {
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
			add('bot', (j && j.data && j.data.message) || 'Something went wrong. Please try again.', 'is-err');
			if (j && j.data && j.data.need_key) { keybox.hidden = false; box.setAttribute('data-ready', '0'); }
		}).catch(function () { btn.disabled = false; wait.remove(); chat.pop(); save(); add('bot', 'Couldn’t reach your website. Try again.', 'is-err'); });
	}
	form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
	input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); } });
	box.querySelectorAll('[data-mq-ai-chip]').forEach(function (b) { b.addEventListener('click', function () { ask(b.textContent); }); });

	var ks = box.querySelector('[data-mq-ai-keysave]'), kin = box.querySelector('[data-mq-ai-keyin]'), kmsg = box.querySelector('[data-mq-ai-keymsg]');
	if (ks) {
		ks.addEventListener('click', function () {
			ks.disabled = true; kmsg.textContent = 'Saving…';
			post('marquee_ai_key', { key: kin.value }).then(function (j) {
				ks.disabled = false;
				if (j && j.success && j.data.saved) { kmsg.textContent = ''; keybox.hidden = true; box.setAttribute('data-ready', '1'); kin.value = ''; input.focus(); }
				else { kmsg.textContent = (j && j.data && j.data.message) || 'Couldn’t save the key.'; }
			}).catch(function () { ks.disabled = false; kmsg.textContent = 'Couldn’t reach your website.'; });
		});
	}
	var kc = box.querySelector('[data-mq-ai-keyclear]');
	if (kc) { kc.addEventListener('click', function () { post('marquee_ai_key', { key: '' }).then(function () { box.setAttribute('data-ready', '0'); keybox.hidden = false; kc.hidden = true; }); }); }
})();
