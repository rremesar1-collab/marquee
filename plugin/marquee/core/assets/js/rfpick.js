/* Pick the Next Song: Remote Falcon song picking right on the show's website.
   The visitor's browser talks to Remote Falcon's viewer service directly (the same one the
   Remote Falcon viewer page uses), so Remote Falcon's own rules still apply to each visitor:
   one vote / one request each, queue length, and the "be at the show" location check. */
(function () {
	'use strict';
	var box = document.querySelector('[data-rr-rfpick]');
	var dlg = document.querySelector('[data-rr-rfdlg]');
	if (!box || !dlg) { return; }
	var API = box.getAttribute('data-api') || 'https://remotefalcon.com/remote-falcon-viewer/graphql';
	var SUB = box.getAttribute('data-sub') || '';
	if (!SUB) { return; }   // no show name: the button stays a plain link to the Remote Falcon page
	var list = dlg.querySelector('[data-rf-list]'), msg = dlg.querySelector('[data-rf-msg]'), head = dlg.querySelector('[data-rf-head]');
	var show = null, timer = null, busy = false, pos = null;

	var SAY = {
		ALREADY_VOTED: 'You already voted. Votes start over after the winning song plays.',
		ALREADY_REQUESTED: 'You already picked a song. You can pick another after yours plays.',
		QUEUE_FULL: 'The line is full right now. Try again in a few minutes.',
		INVALID_LOCATION: 'You need to be at the show to pick songs.',
		SEQUENCE_REQUESTED: 'That song is playing or already in line. Pick another one!',
		NAUGHTY: 'Song picking isn’t available from this device.'
	};
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function gql(query, vars) {
		return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ query: query, variables: vars || {} }) })
			.then(function (r) { return r.json(); });
	}
	var Q = 'query($s:String){getShow(showSubdomain:$s){playingNow playingNext preferences{viewerControlEnabled viewerControlMode locationCheckMethod} sequences{name displayName artist visible active order group imageUrl} requests{sequence{name displayName} position} votes{sequence{name} sequenceGroup{name} votes}}}';

	function say(t, kind) { msg.textContent = t || ''; msg.className = 'rr-rf__msg' + (kind ? ' is-' + kind : ''); }
	function items() {
		var seen = {}, out = [];
		(show.sequences || []).filter(function (q) { return q.visible && q.active; })
			.sort(function (a, b) { return (a.order || 0) - (b.order || 0); })
			.forEach(function (q) {
				var key = q.group ? 'g:' + q.group : q.name;
				if (seen[key]) { return; }
				seen[key] = 1;
				out.push(q.group ? { name: q.group, title: q.group, artist: '', img: q.imageUrl } : { name: q.name, title: q.displayName || q.name, artist: q.artist, img: q.imageUrl });
			});
		return out;
	}
	function votesFor(name) {
		var n = 0;
		(show.votes || []).forEach(function (v) {
			if ((v.sequence && v.sequence.name === name) || (v.sequenceGroup && v.sequenceGroup.name === name)) { n += v.votes || 0; }
		});
		return n;
	}
	function draw() {
		var p = show.preferences || {}, voting = p.viewerControlMode === 'VOTING';
		var now = show.playingNow ? '<p><b>Now playing:</b> ' + esc(show.playingNow) + '</p>' : '';
		var q = (show.requests || []).slice().sort(function (a, b) { return a.position - b.position; })
			.map(function (r) { return r.sequence ? esc(r.sequence.displayName || r.sequence.name) : ''; }).filter(Boolean);
		var line = !voting && q.length ? '<p><b>In line:</b> ' + q.join(', ') + '</p>' : (show.playingNext ? '<p><b>Up next:</b> ' + esc(show.playingNext) + '</p>' : '');
		head.innerHTML = now + line + '<p class="rr-rf__how">' + (voting ? 'Vote for the song you want next. The one with the most votes plays after this song.' : 'Pick a song and it gets in line to play.') + '</p>';
		if (!p.viewerControlEnabled) {
			list.innerHTML = '';
			say('Song picking is off right now. Check back during the show!', 'warn');
			return;
		}
		list.innerHTML = items().map(function (it) {
			var v = voting ? votesFor(it.name) : 0;
			return '<li><button type="button" class="rr-rf__song" data-rf-pick="' + esc(it.name) + '">' +
				(it.img ? '<img src="' + esc(it.img) + '" alt="" loading="lazy">' : '<span class="rr-rf__note" aria-hidden="true">&#9835;</span>') +
				'<span class="rr-rf__t"><b>' + esc(it.title) + '</b>' + (it.artist ? '<small>' + esc(it.artist) + '</small>' : '') + '</span>' +
				(voting ? '<span class="rr-rf__votes">' + v + (v === 1 ? ' vote' : ' votes') + '</span>' : '') +
				'<span class="rr-rf__go">' + (voting ? 'Vote' : 'Pick') + '</span></button></li>';
		}).join('') || '<li class="rr-rf__empty">No songs to pick yet.</li>';
	}
	function load(first) {
		return gql(Q, { s: SUB }).then(function (j) {
			if (!j || !j.data || !j.data.getShow) { throw new Error('no show'); }
			show = j.data.getShow; draw();
			if (first) { say(''); }
		}).catch(function () { if (first) { list.innerHTML = ''; say('We couldn’t reach Remote Falcon. Try again in a minute.', 'bad'); } });
	}
	function where() {
		var m = show && show.preferences && show.preferences.locationCheckMethod;
		if (m !== 'GEO') { return Promise.resolve(null); }
		if (pos) { return Promise.resolve(pos); }
		return new Promise(function (ok) {
			if (!navigator.geolocation) { ok(null); return; }
			say('Checking that you’re at the show…');
			navigator.geolocation.getCurrentPosition(function (p) { pos = { lat: p.coords.latitude, lon: p.coords.longitude }; ok(pos); }, function () { ok(null); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
		});
	}
	list.addEventListener('click', function (e) {
		var b = e.target.closest('[data-rf-pick]');
		if (!b || busy) { return; }
		busy = true; b.classList.add('is-busy');
		var voting = show.preferences.viewerControlMode === 'VOTING', name = b.getAttribute('data-rf-pick');
		where().then(function (p) {
			if (show.preferences.locationCheckMethod === 'GEO' && !p) { throw { code: 'INVALID_LOCATION' }; }
			var m = voting ? 'voteForSequence' : 'addSequenceToQueue';
			return gql('mutation($s:String,$n:String,$la:Float,$lo:Float){' + m + '(showSubdomain:$s,name:$n,latitude:$la,longitude:$lo)}', { s: SUB, n: name, la: p ? p.lat : null, lo: p ? p.lon : null });
		}).then(function (j) {
			if (j && j.errors && j.errors.length) {
				var code = (j.errors[0].extensions && j.errors[0].extensions.message) || j.errors[0].message;
				throw { code: code };
			}
			say(voting ? 'Thanks! Your vote is in.' : 'Got it! Your song is in line.', 'ok');
			return load(false);
		}).catch(function (err) {
			say((err && SAY[err.code]) || 'Something went wrong. Try again.', 'bad');
		}).then(function () { busy = false; b.classList.remove('is-busy'); });
	});
	function open() {
		say('Loading the songs…');
		if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
		load(true);
		clearInterval(timer); timer = setInterval(function () { if (!document.hidden) { load(false); } }, 8000);
	}
	function close() { clearInterval(timer); if (dlg.open) { dlg.close(); } }
	box.querySelector('[data-rf-open]').addEventListener('click', function (e) { e.preventDefault(); open(); });
	dlg.querySelectorAll('[data-rf-close]').forEach(function (b) { b.addEventListener('click', close); });
	dlg.addEventListener('click', function (e) { if (e.target === dlg) { close(); } });
	dlg.addEventListener('close', function () { clearInterval(timer); });
})();
