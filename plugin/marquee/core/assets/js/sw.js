/* Service worker: offline screen and show alerts. CFG is printed above by the theme. */
'use strict';
var CACHE = 'rr-app-' + CFG.v;

self.addEventListener('install', function (e) {
	e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll([CFG.offline, CFG.icon]); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (ks) {
		return Promise.all(ks.filter(function (k) { return k.indexOf('rr-app-') === 0 && k !== CACHE; }).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

/* Pages always come from the website; only when there is no connection does the offline screen show. */
self.addEventListener('fetch', function (e) {
	var r = e.request;
	if (r.method !== 'GET' || r.mode !== 'navigate' || /\/wp-(admin|login)/.test(r.url)) { return; }
	e.respondWith(fetch(r).catch(function () {
		return caches.match(CFG.offline).then(function (m) { return m || new Response('You are offline.', { headers: { 'Content-Type': 'text/plain' } }); });
	}));
});

self.addEventListener('push', function (e) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch (x) { d = { body: e.data ? e.data.text() : '' }; }
	e.waitUntil(self.registration.showNotification(d.title || 'Light show', {
		body: d.body || '',
		icon: CFG.icon,
		badge: CFG.badge,
		tag: d.tag || 'rr',
		renotify: true,
		data: { url: d.url || CFG.scope }
	}));
});

self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var url = (e.notification.data && e.notification.data.url) || CFG.scope;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
		for (var i = 0; i < list.length; i++) {
			var c = list[i];
			if ('focus' in c) {
				return c.focus().then(function (w) { return (w && w.url !== url && 'navigate' in w) ? w.navigate(url).catch(function () {}) : w; });
			}
		}
		return self.clients.openWindow(url);
	}));
});

/* The browser can replace its push address; tell the website so the phone keeps its alerts. */
function keyBytes(s) {
	var p = (s + '===='.slice((s.length + 3) % 4 + 1)).replace(/-/g, '+').replace(/_/g, '/'), b = atob(p), u = new Uint8Array(b.length);
	for (var i = 0; i < b.length; i++) { u[i] = b.charCodeAt(i); }
	return u;
}
self.addEventListener('pushsubscriptionchange', function (e) {
	if (!CFG.key) { return; }
	e.waitUntil(self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(CFG.key) }).then(function (s) {
		var j = s.toJSON(), fd = new FormData();
		fd.append('action', 'rr_push_sub'); fd.append('endpoint', j.endpoint); fd.append('p256dh', j.keys.p256dh); fd.append('auth', j.keys.auth);
		if (e.oldSubscription) { fd.append('old', e.oldSubscription.endpoint); }
		return fetch(CFG.ajax, { method: 'POST', body: fd });
	}));
});
