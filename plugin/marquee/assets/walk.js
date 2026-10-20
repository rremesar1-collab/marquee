/* Marquee: step-by-step screenshot walkthroughs (Settings page). */
(function () {
	document.querySelectorAll('[data-mq-walk]').forEach(function (w) {
		var slides = w.querySelectorAll('[data-walk-slide]'), dots = w.querySelectorAll('[data-walk-dot]'), play = w.querySelector('[data-walk-play]');
		var cur = 0, timer = null, playing = true, hover = false;
		function go(i) {
			cur = (i + slides.length) % slides.length;
			slides.forEach(function (s, k) { s.classList.toggle('is-on', k === cur); s.setAttribute('aria-hidden', k === cur ? 'false' : 'true'); });
			dots.forEach(function (d, k) { d.classList.toggle('is-on', k === cur); });
		}
		function tick() { clearTimeout(timer); if (playing && w.open && !hover) { timer = setTimeout(function () { go(cur + 1); tick(); }, 6500); } }
		w.querySelector('[data-walk-prev]').addEventListener('click', function () { go(cur - 1); tick(); });
		w.querySelector('[data-walk-next]').addEventListener('click', function () { go(cur + 1); tick(); });
		dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.getAttribute('data-walk-dot')); tick(); }); });
		play.addEventListener('click', function () { playing = !playing; play.textContent = playing ? 'Pause' : 'Play'; tick(); });
		w.addEventListener('mouseenter', function () { hover = true; clearTimeout(timer); });
		w.addEventListener('mouseleave', function () { hover = false; tick(); });
		w.addEventListener('toggle', function () { if (w.open) { go(0); } tick(); });
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { playing = false; play.textContent = 'Play'; }
		tick();
	});
})();
