/* Marquee "Show me how": a walkthrough that plays on the real Settings page. A pointer glides to
   each control, a spotlight shows it, and a card explains it. Steps outside WordPress (xLights,
   FPP) show a picture instead. Plays by itself; Pause / Back / Next / Esc. */
(function () {
	'use strict';
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var PER_STEP = 7000;
	var ui = null, tour = null, i = 0, playing = true, timer = null, t0 = 0, raf = 0, target = null, ptr = { x: 0, y: 0 };

	function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	/* Pictures of the steps in xLights (drawn here, not screenshots). */
	var ART = {
		'xl-open': '<div class="mqt-xl"><div class="mqt-xl__bar"><span class="is-hl">File</span><span>Edit</span><span>View</span><span>Audio</span><span>Tools</span></div>' +
			'<div class="mqt-xl__menu"><span>New Sequence</span><span class="is-hl">Open Sequence…</span><span>Save</span><span>Render All</span></div>' +
			'<div class="mqt-xl__files"><b>Show folder</b><span>xlights_rgbeffects.xml</span><span class="is-hl">Names - Candy Cane Swirl.xsq</span><span>Names in Lights.xsq</span><span>marquee-break-full.wav</span></div></div>',
		'xl-render': '<div class="mqt-xl"><div class="mqt-xl__bar"><span>File</span><span>Edit</span><span>View</span><span>Audio</span><span>Tools</span><span class="mqt-xl__icons"><i>▶</i><i>■</i><i class="is-hl" title="Render All">⟳ Render All</i><i class="is-hl2">💾 Save</i></span></div>' +
			'<div class="mqt-xl__seq"><div class="mqt-xl__rows"><span>Mega Tree</span><span>Arches</span><span>Roofline</span><span>Matrix</span><span>TV</span></div><div class="mqt-xl__fx"><i style="left:8%;width:70%" class="r"></i><i style="left:8%;width:70%;top:22%" class="w"></i><i style="left:8%;width:70%;top:42%" class="r"></i><i style="left:0;width:100%;top:62%;opacity:.15"></i><i style="left:0;width:100%;top:82%;opacity:.15"></i><em></em></div></div>' +
			'<p class="mqt-xl__note">' + tr('Render All, play it, then Save → <b>.fseq</b>') + '</p></div>',
		'xl-fppconnect': '<div class="mqt-xl mqt-xl--dlg"><div class="mqt-xl__title">FPP Connect</div><div class="mqt-xl__dlg"><div><b>FPP instances</b><label class="is-hl"><i>☑</i> FPP-Master</label><label><i>☐</i> FPP-TV</label></div>' +
			'<div><b>Sequences</b><label class="is-hl"><i>☑</i> Names - Candy Cane Swirl</label><label class="is-hl"><i>☑</i> Names - Snowfall</label><label><i>☐</i> 2026 Intro</label></div></div><div class="mqt-xl__btns"><span>Cancel</span><span class="is-hl">Upload</span></div></div>'
	};

	function build() {
		ui = document.createElement('div'); ui.className = 'mqt'; ui.setAttribute('role', 'dialog'); ui.setAttribute('aria-modal', 'false');
		ui.innerHTML = '<div class="mqt__shade"></div><div class="mqt__spot"></div><div class="mqt__ptr" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M4 2l15 9-7 1.5L9 20z" fill="#fff" stroke="#1d2327" stroke-width="1.6" stroke-linejoin="round"/></svg><span class="mqt__ring"></span></div>' +
			'<div class="mqt__card" tabindex="-1"><div class="mqt__top"><span class="mqt__count"></span><b class="mqt__name"></b><button type="button" class="mqt__x" aria-label="' + tr('Close') + '">×</button></div>' +
			'<div class="mqt__pic"></div><h3 class="mqt__t"></h3><p class="mqt__x2"></p><div class="mqt__prog"><i></i></div>' +
			'<div class="mqt__btns"><button type="button" class="button" data-t="back">◀ ' + tr('Back') + '</button><button type="button" class="button" data-t="play">❚❚ ' + tr('Pause') + '</button><button type="button" class="button button-primary" data-t="next">' + tr('Next') + ' ▶</button></div></div>';
		document.body.appendChild(ui);
		ui.querySelector('.mqt__x').addEventListener('click', stop);
		ui.querySelector('[data-t="back"]').addEventListener('click', function () { go(i - 1); });
		ui.querySelector('[data-t="next"]').addEventListener('click', function () { if (i >= tour.steps.length - 1) { stop(); } else { go(i + 1); } });
		ui.querySelector('[data-t="play"]').addEventListener('click', function () { playing = !playing; label(); if (playing) { arm(); } else { clearTimeout(timer); } });
		document.addEventListener('keydown', function (e) {
			if (!tour) { return; }
			if (e.key === 'Escape') { stop(); } else if (e.key === 'ArrowRight') { go(Math.min(tour.steps.length - 1, i + 1)); } else if (e.key === 'ArrowLeft') { go(i - 1); }
		});
		window.addEventListener('resize', place); window.addEventListener('scroll', place, true);
	}
	function label() { ui.querySelector('[data-t="play"]').textContent = playing ? '❚❚ ' + tr('Pause') : '▶ ' + tr('Play'); }

	/* Find the control: open its card (and the settings section it's in) first. */
	function find(step) {
		if (!step.el) { return null; }
		var el = null;
		step.el.split(',').some(function (s) { var e = document.querySelector(s.trim()); if (e) { el = e; return true; } return false; });
		if (!el) { return null; }
		for (var u = 0; u < (step.up || 0) && el.parentElement; u++) { el = el.parentElement; }
		var pane = el.closest('[data-pane]');
		if (pane && pane.hidden !== undefined) {
			var n = pane.getAttribute('data-pane'), b = document.querySelector('[data-step="' + n + '"]');
			if (b && !pane.classList.contains('is-on') && getComputedStyle(pane).display === 'none') { b.click(); }
		}
		var d = el.closest('details'); while (d) { d.open = true; d = d.parentElement && d.parentElement.closest('details'); }
		if (!el.getClientRects().length) { return null; }
		return el;
	}
	function rect(el) { var r = el.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height }; }

	function place() {
		if (!tour) { return; }
		var spot = ui.querySelector('.mqt__spot'), card = ui.querySelector('.mqt__card'), vw = window.innerWidth, vh = window.innerHeight;
		if (target && target.isConnected) {
			var r = rect(target), pad = 8;
			spot.style.cssText = 'display:block;left:' + (r.x - pad) + 'px;top:' + (r.y - pad) + 'px;width:' + (r.w + pad * 2) + 'px;height:' + (r.h + pad * 2) + 'px';
			ui.classList.remove('is-center');
			var cw = Math.min(380, vw - 24), ch = card.offsetHeight || 220, below = r.y + r.h + pad + 14, above = r.y - pad - 14 - ch;
			var top = below + ch < vh ? below : (above > 8 ? above : Math.max(8, vh - ch - 8));
			var left = Math.min(Math.max(12, r.x), vw - cw - 12);
			card.style.cssText = 'left:' + left + 'px;top:' + top + 'px;width:' + cw + 'px';
		} else {
			spot.style.display = 'none'; ui.classList.add('is-center');
			card.style.cssText = '';
		}
	}
	function glide(toX, toY, click) {
		var p = ui.querySelector('.mqt__ptr'), fx = ptr.x, fy = ptr.y, start = performance.now(), dur = 900;
		cancelAnimationFrame(raf);
		(function step(now) {
			var k = Math.min(1, (now - start) / dur), e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
			ptr.x = fx + (toX - fx) * e; ptr.y = fy + (toY - fy) * e;
			p.style.transform = 'translate(' + ptr.x + 'px,' + ptr.y + 'px)';
			if (k < 1) { raf = requestAnimationFrame(step); } else if (click) { p.classList.remove('is-click'); void p.offsetWidth; p.classList.add('is-click'); }
		})(performance.now());
	}
	function arm() {
		clearTimeout(timer); t0 = Date.now();
		var bar = ui.querySelector('.mqt__prog i'); bar.style.transition = 'none'; bar.style.width = '0';
		if (!playing) { return; }
		void bar.offsetWidth; bar.style.transition = 'width ' + PER_STEP + 'ms linear'; bar.style.width = '100%';
		timer = setTimeout(function () { if (i < tour.steps.length - 1) { go(i + 1); } else { playing = false; label(); } }, PER_STEP);
	}

	function go(n) {
		if (!tour) { return; }
		i = Math.max(0, Math.min(tour.steps.length - 1, n));
		var s = tour.steps[i], card = ui.querySelector('.mqt__card'), pic = ui.querySelector('.mqt__pic');
		target = find(s);
		ui.querySelector('.mqt__count').textContent = (i + 1) + ' / ' + tour.steps.length;
		ui.querySelector('.mqt__name').textContent = tour.title;
		ui.querySelector('.mqt__t').textContent = s.t;
		ui.querySelector('.mqt__x2').textContent = s.x;
		ui.querySelector('[data-t="back"]').disabled = i === 0;
		ui.querySelector('[data-t="next"]').textContent = i === tour.steps.length - 1 ? tr('Done') + ' ✓' : tr('Next') + ' ▶';
		pic.innerHTML = '';
		if (s.img) {
			pic.innerHTML = '<div class="mqt__shot"><img alt="" src="' + esc(s.img) + '">' + (s.box || []).map(function (b) { return '<span class="mqt__box" style="left:' + b[0] + '%;top:' + b[1] + '%;width:' + b[2] + '%;height:' + b[3] + '%"></span>'; }).join('') + '</div><small>' + ({ rf: tr('On remotefalcon.com'), el: tr('On elevenlabs.io') }[s.where] || tr('On your FPP')) + '</small>';
		} else if (s.art && ART[s.art]) {
			pic.innerHTML = ART[s.art] + '<small>' + tr('In xLights (picture)') + '</small>';
		}
		ui.classList.toggle('has-pic', !!(s.img || s.art));
		if (target) {
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(function () { place(); var r = rect(target); glide(r.x + Math.min(r.w * 0.5, 160), r.y + Math.min(r.h * 0.5, 22), !!s.click); }, 450);
		} else {
			place();
			setTimeout(function () {   // point at the highlighted button in the picture
				var hl = pic.querySelector('.mqt__box, .is-hl'), c = (hl || card).getBoundingClientRect();
				glide(c.left + Math.min(c.width * 0.5, 120), c.top + Math.min(c.height * 0.5, 20), !!hl);
			}, 350);
		}
		place(); arm(); card.focus({ preventScroll: true });
	}
	function start(def) {
		if (!ui) { build(); }
		var pane = document.querySelector('[data-names-mode]'), mode = pane ? pane.getAttribute('data-names-mode') : '';
		def.steps = def.steps.filter(function (s) { return !s.mode || !mode || s.mode === mode; });   // only the steps for the names mode picked
		tour = def; playing = true; label();
		ptr.x = window.innerWidth - 80; ptr.y = window.innerHeight - 80;
		ui.hidden = false; document.documentElement.classList.add('mqt-on');
		go(0);
	}
	function stop() {
		clearTimeout(timer); cancelAnimationFrame(raf); tour = null; target = null;
		if (ui) { ui.hidden = true; } document.documentElement.classList.remove('mqt-on');
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-mq-tour]');
		if (!b) { return; }
		e.preventDefault();
		try { start(JSON.parse(b.getAttribute('data-tour'))); } catch (err) {}
	});
})();
