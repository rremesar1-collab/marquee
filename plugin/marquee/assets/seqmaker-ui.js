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

	/* With their own names break given, it's already made: only themes are new. */
	xsqIn.addEventListener('change', function () {
		var have = xsqIn.files && xsqIn.files.length;
		baseWrap.hidden = !!have;
	});

	/* "Describe your own": the AI turns words into colors + effects. */
	var ask = box.querySelector('[data-sm-ask]'), want = box.querySelector('[data-sm-want]'), amsg = box.querySelector('[data-sm-askmsg]');
	ask.addEventListener('click', function () {
		var w = want.value.trim(); if (!w) { want.focus(); return; }
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
			l.innerHTML = '<input type="checkbox" checked data-sm-own="' + key + '"> ' + esc(t.name) + ' <span class="mq-sm__sw">' + t.colors.map(function (c) { return '<i style="background:' + c + '"></i>'; }).join('') + '</span>';
			chips.appendChild(l);
			want.value = ''; amsg.textContent = 'Added “' + t.name + '”. Make your sequences when you’re ready.';
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
			var opts = { screens: screens, start: +d.start, slot: +d.slot, names: 6, duration: +d.dur, music: d.music };
			var rows = jobs.map(function (j) {
				try {
					var r = j.base ? S.build(rgb.text, opts) :
						(xsq ? S.theme(xsq.text, rgb ? rgb.text : null, j.theme, { screens: screens }) : S.build(rgb.text, opts, j.theme));
					var name = j.base ? j.name : 'Names - ' + r.report.theme;
					var url = URL.createObjectURL(new Blob([r.xsq], { type: 'application/xml' })); urls.push(url);
					var sum = r.report.swapped ? (r.report.swapped.length + ' main effects changed, ' + r.report.recolored + ' recolored') :
						(r.report.props + ' props, ' + Object.keys(r.report.effects).map(function (k) { return r.report.effects[k] + ' ' + k; }).join(', '));
					if (r.report.screens && r.report.screens.length) { sum += '; left dark for the names: ' + r.report.screens.join(', '); }
					return '<li><b>' + esc(name) + '</b><small>' + esc(sum) + '</small><span><a class="button" href="' + url + '" download="' + esc(file(name)) + '">Download .xsq</a>' +
						(j.base ? '' : ' <button type="button" class="button-link" data-sm-use="' + esc(r.report.theme) + '" data-look="' + esc(j.look || '') + '">Use it: add to the themes kids pick from</button>') + '</span></li>';
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
		var st = row.querySelector('input[name$="[start]"]'); if (st) { st.value = d.start; }
		var sl = row.querySelector('input[name$="[slot]"]'); if (sl) { sl.value = d.slot; }
		var lk = row.querySelector('[data-mq-tlook]'); if (lk && b.getAttribute('data-look')) { lk.value = b.getAttribute('data-look'); }
		var on = document.querySelector('input[name="themes_on"]'); if (on) { on.checked = true; }
		var card = row.closest('details'); if (card) { card.open = true; }
		b.outerHTML = '<span class="mq-sm__ok">✓ Added under Themed sequences. Save settings to keep it.</span>';
	});
})();
