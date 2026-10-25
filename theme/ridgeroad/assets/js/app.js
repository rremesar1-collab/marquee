/* The website as an app: service worker, "Get the app" card, show alerts, name buzz. */
(function () {
	'use strict';
	var C = window.RRApp || {};
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var ua = navigator.userAgent || '';
	var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var isAndroid = /Android/i.test(ua);
	var phone = isIOS || isAndroid || /Mobi/i.test(ua);
	var standalone = (window.matchMedia && (matchMedia('(display-mode: standalone)').matches || matchMedia('(display-mode: fullscreen)').matches)) || navigator.standalone === true;
	var canPush = !!C.key && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	var ajax = (window.RidgeRoad && window.RidgeRoad.ajax) || '/wp-admin/admin-ajax.php';
	var bip = null;
	var DAY = 864e5;

	var store = {
		get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
		set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
	};
	function stat(ev) { if (window.RRStat) { window.RRStat(ev); } }
	function post(action, data) {
		var fd = new FormData(); fd.append('action', action);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function keyBytes(s) {
		var p = (s + '===='.slice((s.length + 3) % 4 + 1)).replace(/-/g, '+').replace(/_/g, '/'), b = atob(p), u = new Uint8Array(b.length);
		for (var i = 0; i < b.length; i++) { u[i] = b.charCodeAt(i); }
		return u;
	}

	/* ---------- service worker ---------- */
	var swReady = null;
	if ('serviceWorker' in navigator && C.sw && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
		swReady = navigator.serviceWorker.register(C.sw, { scope: C.scope || '/' })
			.then(function () { return navigator.serviceWorker.ready; })
			.catch(function () { return null; });
	} else {
		canPush = false;
	}

	if (standalone) {
		document.documentElement.classList.add('rr-is-app');
		if (!store.get('rrAppInstalled')) { store.set('rrAppInstalled', '1'); stat('app_install'); }
		try { if (!sessionStorage.getItem('rrAppOpen')) { sessionStorage.setItem('rrAppOpen', '1'); stat('app_open'); } } catch (e) {}
	}

	/* ---------- subscription helpers (also used by the name buzz) ---------- */
	function currentSub() {
		if (!canPush || !swReady) { return Promise.resolve(null); }
		return swReady.then(function (reg) { return reg ? reg.pushManager.getSubscription() : null; }).catch(function () { return null; });
	}
	function fields(sub) {
		var j = sub.toJSON();
		return { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth };
	}
	/* Must be called straight from a tap: asks the phone for permission, then subscribes. */
	function getSub() {
		if (!canPush) { return Promise.reject(new Error('unsupported')); }
		var ask = Notification.permission === 'granted' ? Promise.resolve('granted') : new Promise(function (res) {
			var p = Notification.requestPermission(res);
			if (p && p.then) { p.then(res); }
		});
		return ask.then(function (perm) {
			if (perm !== 'granted') { throw new Error('denied'); }
			return swReady;
		}).then(function (reg) {
			if (!reg) { throw new Error('unsupported'); }
			return reg.pushManager.getSubscription().then(function (s) {
				return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(C.key) });
			});
		});
	}
	function whyText(err) {
		if (err && err.message === 'denied') {
			return isIOS ? tr('Alerts are turned off for this app. Turn them on in Settings > Notifications > %s.').replace('%s', function () { return C.short || tr('this app'); }) : tr('Alerts are blocked for this website. Allow notifications in your browser’s site settings, then try again.');
		}
		if (err && err.message === 'unsupported') { return isIOS && !standalone ? tr('On iPhone, add the app to your home screen first, then turn on alerts there.') : tr('This browser can’t get alerts.'); }
		return tr('Something went wrong. Please try again.');
	}

	/* API for the Kids’ Corner name buzz */
	window.RRAppAPI = {
		canBuzz: function () { return !!C.buzz && canPush && (!isIOS || standalone) && Notification.permission !== 'denied'; },
		buzz: function (id, token) {
			return getSub().then(function (sub) {
				var d = fields(sub); d.id = id; d.token = token;
				return post('rr_push_buzz', d);
			}).then(function (j) { if (!j || !j.success) { throw new Error('server'); } return true; });
		},
		why: whyText
	};

	/* ---------- install steps sheet ---------- */
	var sheet = document.querySelector('[data-rr-appsheet]');
	var ICON = {
		share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5v10h14V11h-1"/></svg>',
		add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8v8M8 12h8"/></svg>',
		menu: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>',
		ok: '<b>' + tr('Add') + '</b>'
	};
	function steps() {
		if (isIOS) {
			var chrome = /CriOS/.test(ua), other = /FxiOS|EdgiOS|OPiOS/.test(ua);
			return {
				h: tr('Add it to your iPhone'),
				s: [
					[chrome ? tr('Tap the <b>Share</b> button at the top right, next to the address') : (other ? tr('Tap your browser’s <b>Share</b> button (it may be in the menu)') : tr('Tap the <b>Share</b> button in Safari (or tap <b>•••</b> first, then Share)')), 'share'],
					[tr('Scroll down and tap <b>Add to Home Screen</b>'), 'add'],
					[tr('Tap <b>Add</b> in the top corner'), 'ok']
				]
			};
		}
		return {
			h: phone ? tr('Add it to your phone') : tr('Install it on this computer'),
			s: [
				[tr('Tap your browser’s <b>menu</b> button (usually <b>⋮</b> at the top right)'), 'menu'],
				[tr('Tap <b>Add to Home screen</b> or <b>Install app</b>'), 'add'],
				[tr('Tap <b>Install</b> or <b>Add</b>'), 'ok']
			]
		};
	}
	var lastFocus = null;
	function openSheet() {
		if (!sheet) { return; }
		var d = steps(), ol = sheet.querySelector('[data-rr-appsheet-steps]');
		sheet.querySelector('[data-rr-appsheet-h]').textContent = d.h;
		ol.innerHTML = '';
		d.s.forEach(function (st) {
			var li = document.createElement('li');
			var t = document.createElement('span'); t.innerHTML = st[0];
			var i = document.createElement('span'); i.className = 'rr-appsheet__ic'; i.setAttribute('aria-hidden', 'true'); i.innerHTML = ICON[st[1]];
			li.appendChild(t); li.appendChild(i); ol.appendChild(li);
		});
		lastFocus = document.activeElement;
		sheet.hidden = false;
		document.documentElement.classList.add('rr-sheet-open');
		sheet.querySelector('.rr-appsheet__box').focus();
	}
	function closeSheet() {
		if (!sheet || sheet.hidden) { return; }
		sheet.hidden = true;
		document.documentElement.classList.remove('rr-sheet-open');
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}
	if (sheet) {
		sheet.querySelectorAll('[data-rr-appsheet-close]').forEach(function (b) { b.addEventListener('click', closeSheet); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSheet(); } });
	}
	function install() {
		if (bip) {
			var e = bip; bip = null;
			e.prompt();
			(e.userChoice || Promise.resolve({})).then(function (c) { if (c && c.outcome === 'accepted') { hideCard(); } });
			return;
		}
		openSheet();
	}

	/* ---------- the home page card ---------- */
	var card = document.querySelector('[data-rr-app-card]');
	var link = document.querySelector('[data-rr-app-link]');
	var part = function (n) { return card && card.querySelector('[data-rr-app-part="' + n + '"]'); };
	var $c = function (s) { return card && card.querySelector(s); };
	function msg(t) { var m = $c('[data-rr-app-msg]'); if (m) { m.textContent = t || ''; } }
	function hideCard() { if (card) { card.hidden = true; } }
	function later() { store.set('rrAppLater', String(Date.now())); hideCard(); }
	function snoozed() { var t = +store.get('rrAppLater') || 0; return Date.now() - t < 30 * DAY; }
	function topics() {
		var o = {};
		card.querySelectorAll('[data-rr-app-topic]').forEach(function (b) { o[b.getAttribute('data-rr-app-topic')] = b.getAttribute('aria-checked') === 'true'; });
		return o;
	}
	function setTopics(o) {
		card.querySelectorAll('[data-rr-app-topic]').forEach(function (b) { var k = b.getAttribute('data-rr-app-topic'); if (k in o) { b.setAttribute('aria-checked', String(!!o[k])); } });
	}
	function showInstall() { if (!card) { return; } part('alerts').hidden = true; part('install').hidden = false; card.hidden = false; }
	function showAlerts(have) {
		if (!card) { return; }
		part('install').hidden = true; part('alerts').hidden = false; card.hidden = false;
		$c('[data-rr-app-new]').hidden = !!have;
		$c('[data-rr-app-have]').hidden = !have;
		$c('[data-rr-app-intro]').textContent = have ? tr('Your alerts are on. Change what you get any time:') : tr('Pick what you want. You can change it any time from the link at the bottom of the page.');
	}
	var subbed = false;
	function openAlertsSettings() {
		if (!card) { location.href = (C.scope || '/') + '#rr-app'; return; }
		msg('');
		if (!canPush) { showAlerts(false); msg(whyText(new Error('unsupported'))); }
		else if (Notification.permission === 'denied') { showAlerts(false); msg(whyText(new Error('denied'))); }
		else {
			currentSub().then(function (s) {
				subbed = !!s;
				if (s) { post('rr_push_get', { endpoint: s.endpoint }).then(function (j) { if (j && j.success && j.data.known) { setTopics(j.data); } }).catch(function () {}); }
				showAlerts(subbed);
			});
		}
		card.hidden = false;
		card.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

	function decide() {
		if (standalone) {
			if (link) { link.hidden = !canPush; link.textContent = tr('Show alerts'); }
			if (!card || !canPush || Notification.permission === 'denied') { return; }
			currentSub().then(function (s) {
				subbed = !!s;
				if (!s && !snoozed()) { showAlerts(false); }
			});
			return;
		}
		if (link) { link.hidden = !(phone || bip); }
		if (card && phone && !snoozed()) { showInstall(); }
	}

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault(); bip = e;
		if (link && !standalone) { link.hidden = false; }
	});

	if (card) {
		card.querySelectorAll('[data-rr-app-later]').forEach(function (b) { b.addEventListener('click', later); });
		$c('[data-rr-app-add]').addEventListener('click', install);
		$c('[data-rr-app-done]').addEventListener('click', hideCard);
		card.querySelectorAll('[data-rr-app-topic]').forEach(function (b) {
			b.addEventListener('click', function () {
				b.setAttribute('aria-checked', String(b.getAttribute('aria-checked') !== 'true'));
				if (!subbed) { return; }
				var t = topics();
				if (!t.start && !t.news) { msg(tr('Both are off, so this phone won’t get show alerts. Tap Turn off all alerts to remove it.')); }
				currentSub().then(function (s) {
					if (!s) { return; }
					var d = fields(s); d.start = t.start ? '1' : '0'; d.news = t.news ? '1' : '0';
					return post('rr_push_sub', d).then(function (j) { if (j && j.success && (t.start || t.news)) { msg(tr('Saved.')); } });
				}).catch(function () { msg(tr('Could not save. Please try again.')); });
			});
		});
		$c('[data-rr-app-on]').addEventListener('click', function () {
			var t = topics(), btn = this;
			if (!t.start && !t.news) { msg(tr('Pick at least one.')); return; }
			btn.disabled = true; msg('');
			getSub().then(function (sub) {
				var d = fields(sub); d.start = t.start ? '1' : '0'; d.news = t.news ? '1' : '0'; d.first = '1';
				return post('rr_push_sub', d);
			}).then(function (j) {
				btn.disabled = false;
				if (!j || !j.success) { throw new Error('server'); }
				subbed = true; showAlerts(true);
				msg(tr('Alerts are on! We’ll only buzz you for what you picked.'));
			}).catch(function (err) { btn.disabled = false; msg(whyText(err)); });
		});
		$c('[data-rr-app-off]').addEventListener('click', function () {
			currentSub().then(function (s) {
				if (!s) { return; }
				return post('rr_push_unsub', { endpoint: s.endpoint }).then(function () { return s.unsubscribe(); });
			}).then(function () { subbed = false; setTopics({ start: true, news: true }); showAlerts(false); msg(tr('Alerts are off. This phone won’t get any more.')); })
				.catch(function () { msg(tr('Could not turn them off. Please try again.')); });
		});
	}
	if (link) {
		link.addEventListener('click', function (e) {
			if (standalone) { if (card) { e.preventDefault(); openAlertsSettings(); } return; }
			e.preventDefault(); install();
		});
	}
	decide();
	if (card && location.hash === '#rr-app') {
		if (standalone) { openAlertsSettings(); } else if (phone) { showInstall(); card.scrollIntoView({ block: 'center' }); }
	}
})();
