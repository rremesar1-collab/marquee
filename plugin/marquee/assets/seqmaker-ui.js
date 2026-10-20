/* Marquee: "Make your sequences" card. Reads the layout (and the show's own names break) in the
   browser, makes the .xsq files with MarqueeSeq, and offers each one as a download. */
(function () {
	var box = document.querySelector('[data-mq-seqmaker]');
	if (!box || !window.MarqueeSeq) { return; }
	var C = window.MarqueeAdmin || {}, S = window.MarqueeSeq, d = box.dataset;
	var rgbIn = box.querySelector('[data-sm-rgb]'), xsqIn = box.querySelector('[data-sm-xsq]');
	var msg = box.querySelector('[data-sm-msg]'), out = box.querySelector('[data-sm-out]'), render = box.querySelector('[data-sm-render]');
	var baseWrap = box.querySelector('[data-sm-base-wrap]'), chips = box.querySelector('.mq-sm__chips');
	var screens = []; try { screens = JSON.parse(d.screens || '[]'); } catch (e) {}
	var own = {};          // themes described in words: key -> theme from the AI
	var urls = [];

	function read(input) {
		return new Promise(function (ok, no) {
			var f = input.files && input.files[0];
			if (!f) { ok(null); return; }
			var r = new FileReader(); r.onload = function () { ok({ name: f.name, text: String(r.result) }); }; r.onerror = function () { no(new Error('Couldn’t read ' + f.name)); }; r.readAsText(f);
		});
	}
	function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function file(name) { return name.replace(/[\\/:*?"<>|’']/g, '').replace(/\s+/g, ' ').trim() + '.xsq'; }

	/* The file boxes: drag and drop, and a line saying what was found in the file. */
	box.querySelectorAll('[data-sm-drop]').forEach(function (drop) {
		var inp = drop.querySelector('input');
		['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('is-over'); }); });
		drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer && e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; inp.dispatchEvent(new Event('change')); } });
	});
	function status(inp, ok, txt) {
		var drop = inp.closest('[data-sm-drop]'), st = drop.querySelector('[data-sm-status]');
		drop.classList.toggle('is-ok', ok === true); drop.classList.toggle('is-bad', ok === false); st.textContent = txt;
	}
	var NAMES = { tree: 'trees', star: 'stars', arch: 'arches', cane: 'candy canes', spinner: 'spinners', flake: 'snowflakes', window: 'windows', line: 'lines and outlines', flood: 'floods', present: 'presents', matrix: 'matrices', other: 'other props' };
	rgbIn.addEventListener('change', function () {
		read(rgbIn).then(function (f) {
			if (!f) { status(rgbIn, null, 'Not chosen yet.'); return; }
			try {
				var by = S.propsSummary(f.text, null), n = 0, parts = [];
				Object.keys(by).forEach(function (k) { n += by[k].length; if (k !== 'other') { parts.push(by[k].length + ' ' + NAMES[k]); } });
				if (!n) { throw new Error('no props'); }
				status(rgbIn, true, '✓ ' + f.name + ': ' + n + ' props found (' + parts.slice(0, 5).join(', ') + (parts.length > 5 ? '…' : '') + ')');
			} catch (e) { status(rgbIn, false, '✗ That doesn’t look like xlights_rgbeffects.xml. Pick the file with that name from your xLights show folder.'); }
		});
	});
	/* With their own names break given, it's already made: only themes are new. */
	xsqIn.addEventListener('change', function () {
		var have = xsqIn.files && xsqIn.files.length;
		baseWrap.hidden = !!have;
		read(xsqIn).then(function (f) {
			if (!f) { status(xsqIn, null, 'Not chosen: everything is made from scratch.'); return; }
			try {
				var by = S.propsSummary(null, f.text), n = 0, tm2 = S.timing(f.text); Object.keys(by).forEach(function (k) { n += by[k].length; });
				status(xsqIn, true, '✓ ' + f.name + ': themes keep its look, timing and ' + n + ' props with effects. ' + (d.built === '1' ? 'Name times come from your names break audio, so they match the music.' : (tm2 ? 'Names found at ' + tm2.start + ' s, every ' + tm2.slot + ' s (its Names timing track).' : 'No Names timing track in it, so your names break settings are used.')));
			}
			catch (e) { status(xsqIn, false, '✗ That isn’t an xLights sequence (.xsq).'); }
		});
	});
	/* "Make them for me" in Themed sequences opens this card. */
	document.querySelectorAll('[data-mq-goto-maker]').forEach(function (b) {
		b.addEventListener('click', function () {
			var card = box.closest('details'); if (card) { card.open = true; }
			box.scrollIntoView({ behavior: 'smooth', block: 'start' });
			setTimeout(function () { var d = box.querySelector('[data-sm-drop]'); if (d) { d.classList.add('is-pulse'); setTimeout(function () { d.classList.remove('is-pulse'); }, 1600); } }, 500);
		});
	});

	/* Up to 6 themes (kids pick from 6 plus Classic); 3 or 4 is the sweet spot. */
	var MAX = 6, cnt = box.querySelector('[data-sm-count]');
	function picked() { return box.querySelectorAll('[data-sm-theme]:checked, [data-sm-own]:checked').length; }
	function already() {
		var n = 0; document.querySelectorAll('.mq__themes [data-mq-theme]:not(.is-classic) [data-mq-tname]').forEach(function (i) { if (i.value.trim()) { n++; } });
		return n;
	}
	function count() {
		var n = picked(), full = n >= MAX, have = already();
		box.querySelectorAll('[data-sm-theme], [data-sm-own]').forEach(function (c) { if (!c.checked) { c.disabled = full; c.closest('label').classList.toggle('is-off', full); } else { c.disabled = false; } });
		var t = n + ' of ' + MAX + ' themes picked. 3 or 4 is plenty: kids pick faster, and each one is a sequence to render.';
		if (full) { t = MAX + ' of ' + MAX + ' themes picked, the most kids can choose from. Untick one to pick another.'; }
		if (have) { t += ' You already have ' + have + (have === 1 ? ' theme' : ' themes') + ' set up under Themed sequences' + (have + n > MAX ? ', so “Use it” only has room for ' + Math.max(0, MAX - have) + ' more.' : '.'); }
		cnt.textContent = t; cnt.classList.toggle('is-full', full || have + n > MAX);
	}
	box.addEventListener('change', function (e) { if (e.target.matches('[data-sm-theme], [data-sm-own]')) { count(); } });
	count(); setInterval(function () { if (cnt.isConnected) { count(); } }, 2000);

	/* "Describe your own": the AI turns words into colors + effects. */
	var ask = box.querySelector('[data-sm-ask]'), want = box.querySelector('[data-sm-want]'), amsg = box.querySelector('[data-sm-askmsg]');
	ask.addEventListener('click', function () {
		var w = want.value.trim(); if (!w) { want.focus(); return; }
		if (!(rgbIn.files && rgbIn.files.length) && !(xsqIn.files && xsqIn.files.length)) {
			amsg.textContent = 'Choose your xlights_rgbeffects.xml first (step 1), so the AI knows which props you have.';
			var d = box.querySelector('[data-sm-drop]'); d.scrollIntoView({ behavior: 'smooth', block: 'center' }); d.classList.add('is-pulse'); setTimeout(function () { d.classList.remove('is-pulse'); }, 1600);
			return;
		}
		ask.disabled = true; amsg.textContent = 'Asking the AI…';
		read(rgbIn).then(function (rgb) {
			return read(xsqIn).then(function (xsq) {
				var props = {};
				try { props = S.propsSummary(rgb ? rgb.text : null, xsq ? xsq.text : null); } catch (e) {}
				var fd = new FormData(); fd.append('action', 'marquee_ai_theme'); fd.append('nonce', C.nonce); fd.append('want', w); fd.append('props', JSON.stringify(props));
				return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
			});
		}).then(function (j) {
			ask.disabled = false;
			if (!j || !j.success) { amsg.textContent = (j && j.data && j.data.message) || 'Something went wrong.'; return; }
			var t = j.data.theme, key = 'own' + Object.keys(own).length;
			try { t = S.cleanTheme(t); } catch (e) { amsg.textContent = e.message; return; }
			own[key] = t;
			var l = document.createElement('label'); l.className = 'mq-sm__chip is-own';
			var room = picked() < MAX;
			l.innerHTML = '<input type="checkbox"' + (room ? ' checked' : '') + ' data-sm-own="' + key + '"> ' + esc(t.name) + ' <span class="mq-sm__sw">' + t.colors.map(function (c) { return '<i style="background:' + c + '"></i>'; }).join('') + '</span>';
			chips.appendChild(l);
			want.value = ''; count();
			amsg.textContent = room ? 'Added “' + t.name + '”. Add another, or make your sequences when you’re ready.' : 'Added “' + t.name + '”, but not ticked: 6 themes are already picked. Untick one to use it.';
		}).catch(function () { ask.disabled = false; amsg.textContent = 'Couldn’t reach your website.'; });
	});

	box.querySelector('[data-sm-make]').addEventListener('click', function () {
		msg.textContent = '';
		Promise.all([read(rgbIn), read(xsqIn)]).then(function (f) {
			var rgb = f[0], xsq = f[1];
			if (!rgb && !xsq) { msg.textContent = 'Choose your xlights_rgbeffects.xml first (step 1).'; return; }
			var jobs = [];
			if (!xsq && box.querySelector('[data-sm-base]').checked) { jobs.push({ base: true, name: d.playlist || 'Names in Lights' }); }
			box.querySelectorAll('[data-sm-theme]:checked').forEach(function (c) { jobs.push({ look: c.getAttribute('data-sm-theme'), theme: c.getAttribute('data-sm-theme') }); });
			box.querySelectorAll('[data-sm-own]:checked').forEach(function (c) { jobs.push({ look: '', theme: own[c.getAttribute('data-sm-own')] }); });
			if (!jobs.length) { msg.textContent = 'Tick at least one thing to make (step 3).'; return; }
			if (!rgb && jobs.some(function (j) { return j.base; })) { msg.textContent = 'Making your names break needs your layout file (step 1).'; return; }
			urls.forEach(function (u) { URL.revokeObjectURL(u); }); urls = [];
			var tm = null; if (xsq) { try { tm = S.timing(xsq.text); } catch (e) { tm = null; } }
			// The music decides when names can go up: with names break audio built (card 4), the sequences use
			// that audio, so its timing wins; otherwise the sequence's own Names track; otherwise the settings.
			var built = d.built === '1', useTrack = tm && !built;
			var useStart = useTrack ? tm.start : +d.start, useSlot = useTrack ? tm.slot : +d.slot;
			var why = built ? 'to match your names break audio' : (useTrack ? 'read from the Names timing track in your sequence' : (xsq ? 'the same as your names break settings' : 'Marquee placed them'));
			var tmNote = 'Names at ' + useStart + ' s, then every ' + useSlot + ' s (' + why + ')';
			var opts = { screens: screens, start: useStart, slot: useSlot, names: 6, duration: +d.dur, music: d.music };
			var rows = jobs.map(function (j) {
				try {
					var r = j.base ? S.build(rgb.text, opts) :
						(xsq ? S.theme(xsq.text, rgb ? rgb.text : null, j.theme, { screens: screens, start: useStart, slot: useSlot }) : S.build(rgb.text, opts, j.theme));
					var name = j.base ? j.name : 'Names - ' + r.report.theme;
					var url = URL.createObjectURL(new Blob([r.xsq], { type: 'application/xml' })); urls.push(url);
					var sum = r.report.swapped ? (r.report.swapped.length + ' main effects changed, ' + r.report.recolored + ' recolored') :
						(r.report.props + ' props, ' + Object.keys(r.report.effects).map(function (k) { return r.report.effects[k] + ' ' + k; }).join(', '));
					if (r.report.screens && r.report.screens.length) { sum += '; left dark for the names: ' + r.report.screens.join(', '); }
					return '<li><b>' + esc(name) + '</b><small>' + esc(sum) + '</small><small class="mq-sm__tm">⏱ ' + esc(tmNote) + ': filled in for you.</small><span><a class="button" href="' + url + '" download="' + esc(file(name)) + '">Download .xsq</a>' +
						(j.base ? '' : ' <button type="button" class="button-link" data-sm-use="' + esc(r.report.theme) + '" data-look="' + esc(j.look || '') + '" data-start="' + useStart + '" data-slot="' + useSlot + '">Use it: add to the themes kids pick from</button>') + '</span></li>';
				} catch (e) {
					return '<li class="is-bad"><b>' + esc(j.base ? j.name : (j.theme.name || j.theme)) + '</b><small>' + esc(e.message) + '</small></li>';
				}
			});
			if (!screens.length) { rows.unshift('<li class="is-warn"><small>No screens are filled in under card 1 yet, so nothing was left dark for the names. Add your screens, Save, and make them again.</small></li>'); }
			out.innerHTML = '<ul>' + rows.join('') + '</ul>'; out.hidden = false;
			render.classList.add('is-next'); render.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			msg.textContent = 'Done. Download them, then follow the steps below.';
		}).catch(function (e) { msg.textContent = e.message; });
	});

	/* "Use it": fill the next empty row under Themed sequences. */
	out.addEventListener('click', function (e) {
		var b = e.target.closest('[data-sm-use]'); if (!b) { return; }
		var name = b.getAttribute('data-sm-use'), rows = document.querySelectorAll('.mq__themes [data-mq-theme]:not(.is-classic)'), row = null;
		rows.forEach(function (r) { var n = r.querySelector('[data-mq-tname]'); if (!row && n && n.value.trim().toLowerCase() === name.toLowerCase()) { row = r; } });
		if (!row) { rows.forEach(function (r) { var n = r.querySelector('[data-mq-tname]'); if (!row && n && !n.value.trim()) { row = r; } }); }
		if (!row) { b.textContent = 'All 6 theme spots are used'; return; }
		row.classList.remove('is-empty');
		row.querySelector('[data-mq-tname]').value = name.slice(0, 30);
		var pl = row.querySelector('input[name$="[playlist]"]'); if (pl) { pl.value = 'Names - ' + name; }
		var st = row.querySelector('input[name$="[start]"]'); if (st) { st.value = b.getAttribute('data-start') || d.start; st.dispatchEvent(new Event('input')); }
		var sl = row.querySelector('input[name$="[slot]"]'); if (sl) { sl.value = b.getAttribute('data-slot') || d.slot; sl.dispatchEvent(new Event('input')); }
		row.classList.add('is-auto');
		var lk = row.querySelector('[data-mq-tlook]'); if (lk && b.getAttribute('data-look')) { lk.value = b.getAttribute('data-look'); }
		var on = document.querySelector('input[name="themes_on"]'); if (on) { on.checked = true; }
		var card = row.closest('details'); if (card) { card.open = true; }
		b.outerHTML = '<span class="mq-sm__ok">✓ Added under Themed sequences. Save settings to keep it.</span>';
	});
})();
