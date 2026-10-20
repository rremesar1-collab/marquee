/* Marquee sequence maker: turns a show's own names break sequence (.xsq) into themed versions
   (Candy Cane Swirl, Snowfall…) for the "Pick your show" choices, right in the browser.

   What it changes: colors everywhere (palettes), and on the long "main" effects it swaps in an
   effect that fits the theme per kind of prop (spirals on trees, pinwheels on spinners, chases on
   rooflines…). What it never touches: the screens the names go on, Faces (singing props),
   pictures and videos, the music, the timing tracks and when things happen.

   The layout file (xlights_rgbeffects.xml) is optional: it only helps tell what kind of prop each
   model is. It's read in the browser and never uploaded (it has controller addresses in it).

   Works in the browser (DOMParser) and in Node with @xmldom/xmldom (for tests):
     MarqueeSeq.theme(xsqText, rgbText|null, themeSpec, { screens: ['Matrix', 'TV'] }) → { xsq, report }
*/
(function (root) {
	'use strict';

	/* Effects the themes use, with settings xLights understands. */
	var TPL = {
		spirals: ['Spirals', 'E_CHECKBOX_Spirals_3D=0,E_CHECKBOX_Spirals_Blend=0,E_CHECKBOX_Spirals_Grow=0,E_CHECKBOX_Spirals_Shrink=0,E_SLIDER_Spirals_Count=4,E_SLIDER_Spirals_Rotation=20,E_SLIDER_Spirals_Thickness=50,E_TEXTCTRL_Spirals_Movement=2.5'],
		pinwheel: ['Pinwheel', 'E_CHECKBOX_Pinwheel_Rotation=1,E_CHOICE_Pinwheel_3D=none,E_CHOICE_Pinwheel_Style=New Render Method,E_SLIDER_PinwheelXC=0,E_SLIDER_PinwheelYC=0,E_SLIDER_Pinwheel_ArmSize=100,E_SLIDER_Pinwheel_Arms=6,E_SLIDER_Pinwheel_Offset=0,E_SLIDER_Pinwheel_Speed=15,E_SLIDER_Pinwheel_Thickness=50,E_SLIDER_Pinwheel_Twist=60'],
		chase: ['SingleStrand', 'E_CHECKBOX_Chase_Group_All=0,E_CHOICE_Chase_Type1=Left-Right,E_CHOICE_Fade_Type=From Head,E_CHOICE_SingleStrand_Colors=Palette,E_NOTEBOOK_SSEFFECT_TYPE=Chase,E_SLIDER_Color_Mix1=50,E_SLIDER_Number_Chases=8,E_TEXTCTRL_Chase_Rotations=6.0'],
		bars: ['Bars', 'E_CHECKBOX_Bars_3D=0,E_CHECKBOX_Bars_Gradient=0,E_CHECKBOX_Bars_Highlight=0,E_CHOICE_Bars_Direction=Left,E_SLIDER_Bars_BarCount=4,E_TEXTCTRL_Bars_Cycles=6.0'],
		twinkle: ['Twinkle', 'E_CHECKBOX_Twinkle_ReRandom=1,E_CHECKBOX_Twinkle_Strobe=0,E_CHOICE_Twinkle_Style=New Render Method,E_SLIDER_Twinkle_Count=50,E_SLIDER_Twinkle_Steps=25'],
		shimmer: ['Shimmer', 'E_CHECKBOX_Shimmer_Use_All_Colors=1,E_SLIDER_Shimmer_Duty_Factor=50,E_TEXTCTRL_Shimmer_Cycles=12.0'],
		wash: ['Color Wash', 'E_CHECKBOX_ColorWash_CircularPalette=1,E_CHECKBOX_ColorWash_HFade=0,E_CHECKBOX_ColorWash_VFade=0,E_TEXTCTRL_ColorWash_Cycles=4.0'],
		snowfall: ['Meteors', 'E_CHECKBOX_Meteors_UseMusic=0,E_CHOICE_Meteors_Effect=Down,E_CHOICE_Meteors_Type=Palette,E_SLIDER_Meteors_Count=25,E_SLIDER_Meteors_Length=12,E_SLIDER_Meteors_Speed=8,E_SLIDER_Meteors_Swirl_Intensity=0'],
		on: ['On', 'E_TEXTCTRL_Eff_On_End=100,E_TEXTCTRL_Eff_On_Start=100']
	};
	var EFFECTS = Object.keys(TPL);
	var KINDS = ['tree', 'star', 'arch', 'cane', 'spinner', 'flake', 'window', 'line', 'flood', 'present', 'matrix', 'other'];

	/* Ready-made themes (the AI makes the same shape for a theme the owner describes). */
	var THEMES = {
		candy: { name: 'Candy Cane Swirl', colors: ['#FF0000', '#FFFFFF'], swaps: { tree: 'spirals', spinner: 'pinwheel', line: 'chase', window: 'bars', flake: 'twinkle', matrix: 'bars' } },
		snow: { name: 'Snowfall', colors: ['#FFFFFF', '#80C8FF', '#C8E8FF'], swaps: { tree: 'snowfall', line: 'snowfall', flake: 'twinkle', spinner: 'twinkle', arch: 'twinkle', window: 'twinkle', matrix: 'snowfall' } },
		rainbow: { name: 'Rainbow Party', colors: ['#FF0000', '#FF8000', '#FFE000', '#00E000', '#0080FF', '#9000FF'], swaps: { tree: 'spirals', spinner: 'pinwheel', line: 'chase', window: 'wash', arch: 'wash', flood: 'wash', matrix: 'bars' } },
		christmas: { name: 'Santa’s Sleigh', colors: ['#FF0000', '#00C000', '#FFFFFF'], swaps: { tree: 'spirals', spinner: 'pinwheel', line: 'chase', window: 'bars' } },
		aurora: { name: 'Northern Lights', colors: ['#00FF90', '#8040FF', '#00C0FF'], swaps: { tree: 'wash', line: 'wash', window: 'wash', arch: 'wash', flake: 'shimmer', spinner: 'wash', matrix: 'wash' } },
		gold: { name: 'Golden Stars', colors: ['#FFC000', '#FFFFFF', '#FFE080'], swaps: { tree: 'twinkle', line: 'twinkle', flake: 'shimmer', spinner: 'shimmer', star: 'shimmer', window: 'twinkle' } },
		ginger: { name: 'Gingerbread Town', colors: ['#FF8020', '#FFF0D0', '#C05010'], swaps: { tree: 'spirals', line: 'chase', spinner: 'pinwheel' } },
		icy: { name: 'Frozen', colors: ['#40A0FF', '#FFFFFF'], swaps: { tree: 'snowfall', line: 'shimmer', flake: 'twinkle', spinner: 'shimmer', matrix: 'shimmer' } },
		purple: { name: 'Sugar Plum Party', colors: ['#C040FF', '#FF40C0', '#FFFFFF'], swaps: { tree: 'spirals', spinner: 'pinwheel', line: 'chase', window: 'bars' } }
	};

	function xml() {
		if (typeof DOMParser !== 'undefined') { return { P: DOMParser, S: XMLSerializer }; }
		var x = require('@xmldom/xmldom'); return { P: x.DOMParser, S: x.XMLSerializer };
	}
	function parse(text, what) {
		var X = xml(), d = new X.P().parseFromString(text, 'text/xml');
		if (!d || !d.documentElement || d.getElementsByTagName('parsererror').length) { throw new Error(what + ' isn’t a valid file.'); }
		return d;
	}
	function kids(el, tag) { var out = []; for (var n = el.firstChild; n; n = n.nextSibling) { if (n.nodeType === 1 && (!tag || n.nodeName === tag)) { out.push(n); } } return out; }
	function first(d, tag) { return d.getElementsByTagName(tag)[0] || null; }

	/* What kind of prop a model or group is, from its name, its type in the layout, or its members. */
	function byName(n) {
		n = n.toLowerCase();
		if (/star/.test(n)) { return 'star'; }
		if (/spinner|pinwheel|whirl/.test(n)) { return 'spinner'; }
		if (/flake|ice queen|snowstar/.test(n)) { return 'flake'; }
		if (/arch/.test(n)) { return 'arch'; }
		if (/cane/.test(n)) { return 'cane'; }
		if (/tree/.test(n)) { return 'tree'; }
		if (/window/.test(n)) { return 'window'; }
		if (/flood|wash/.test(n)) { return 'flood'; }
		if (/present|gift/.test(n)) { return 'present'; }
		if (/roof|eave|gutter|icicle|outline|line|garage|door|entr|fence|path|driveway/.test(n)) { return 'line'; }
		if (/matrix|panel|p5|p10|screen/.test(n)) { return 'matrix'; }
		return '';
	}
	var AS = { 'Tree': 'tree', 'Star': 'star', 'Arches': 'arch', 'Candy Canes': 'cane', 'Window Frame': 'window', 'Icicles': 'line', 'Single Line': 'line', 'Poly Line': 'line', 'Matrix': 'matrix', 'Circle': 'spinner', 'Spinner': 'spinner', 'Wreath': 'spinner', 'Sphere': 'other', 'Cube': 'other' };
	function layoutKinds(rgb) {
		var kinds = {}, groups = {};
		if (!rgb) { return { kinds: kinds, groups: groups }; }
		var d = parse(rgb, 'The layout file (xlights_rgbeffects.xml)');
		var models = first(d, 'models');
		if (models) {
			kids(models, 'model').forEach(function (m) {
				var name = m.getAttribute('name') || '';
				kinds[name] = byName(name) || AS[m.getAttribute('DisplayAs')] || 'other';
			});
		}
		var mg = first(d, 'modelGroups');
		if (mg) { kids(mg, 'modelGroup').forEach(function (g) { groups[g.getAttribute('name')] = (g.getAttribute('models') || '').split(',').filter(Boolean); }); }
		return { kinds: kinds, groups: groups };
	}
	function kindOf(name, L, depth) {
		var k = byName(name);
		if (k) { return k; }
		if (L.kinds[name]) { return L.kinds[name]; }
		var parent = name.split('/')[0];
		if (parent !== name) { return kindOf(parent, L, (depth || 0) + 1); }
		var mem = L.groups[name];
		if (mem && (depth || 0) < 4) {
			var count = {};
			mem.forEach(function (m) { var mk = kindOf(m, L, (depth || 0) + 1); count[mk] = (count[mk] || 0) + 1; });
			var best = 'other', n = 0;
			Object.keys(count).forEach(function (c) { if (count[c] > n) { best = c; n = count[c]; } });
			return best;
		}
		return 'other';
	}

	/* Palettes: "C_BUTTON_Palette1=#FF0000,C_CHECKBOX_Palette1=1,…" */
	function palColors(s) {
		var on = {}, col = {};
		s.split(',').forEach(function (kv) {
			var m = /^C_(BUTTON|CHECKBOX)_Palette(\d+)=(.*)$/.exec(kv);
			if (!m) { return; }
			if (m[1] === 'BUTTON') { col[m[2]] = m[3]; } else if (m[3] === '1') { on[m[2]] = 1; }
		});
		return Object.keys(col).filter(function (i) { return on[i]; }).sort(function (a, b) { return a - b; }).map(function (i) { return col[i]; });
	}
	function palString(cols, keepFrom) {
		var out = [];
		cols.slice(0, 8).forEach(function (c, i) { out.push('C_BUTTON_Palette' + (i + 1) + '=' + c.toUpperCase()); });
		cols.slice(0, 8).forEach(function (c, i) { out.push('C_CHECKBOX_Palette' + (i + 1) + '=1'); });
		if (keepFrom) { keepFrom.split(',').forEach(function (kv) { if (kv && !/^C_(BUTTON|CHECKBOX)_Palette\d+=/.test(kv)) { out.push(kv); } }); }
		return out.join(',');
	}
	function isWhite(cols) { return cols.length && cols.every(function (c) { return /^#?F{6}$/i.test(c); }); }

	/** Check and tidy a theme (from a preset or from the AI). */
	function cleanTheme(t) {
		if (typeof t === 'string') { t = THEMES[t]; }
		if (!t) { throw new Error('Unknown theme.'); }
		var cols = (t.colors || []).filter(function (c) { return /^#[0-9a-f]{6}$/i.test(c); }).slice(0, 6);
		if (!cols.length) { throw new Error('The theme needs at least one color.'); }
		var sw = {};
		Object.keys(t.swaps || {}).forEach(function (k) { if (KINDS.indexOf(k) >= 0 && EFFECTS.indexOf(t.swaps[k]) >= 0) { sw[k] = t.swaps[k]; } });
		return { name: String(t.name || 'Theme').slice(0, 40), colors: cols, swaps: sw };
	}

	/** Make a themed copy of a names break sequence. */
	function theme(xsqText, rgbText, spec, opts) {
		opts = opts || {};
		var T = cleanTheme(spec), L = layoutKinds(rgbText), d = parse(xsqText, 'The sequence (.xsq)');
		var screens = {}; (opts.screens || []).forEach(function (s) { screens[String(s).trim().toLowerCase()] = 1; });
		var cp = first(d, 'ColorPalettes'), db = first(d, 'EffectDB'), ee = first(d, 'ElementEffects');
		if (!cp || !db || !ee) { throw new Error('That doesn’t look like an xLights sequence.'); }
		var pals = kids(cp, 'ColorPalette'), dbs = kids(db, 'Effect');
		var report = { theme: T.name, recolored: 0, swapped: [], kept: [], screens: [] };

		var palIdx = {}; pals.forEach(function (p, i) { if (palIdx[p.textContent] === undefined) { palIdx[p.textContent] = i; } });
		function addPal(s) {
			if (palIdx[s] !== undefined) { return palIdx[s]; }
			var p = d.createElement('ColorPalette'); p.appendChild(d.createTextNode(s)); cp.appendChild(p); pals.push(p);
			palIdx[s] = pals.length - 1; return palIdx[s];
		}
		var fullPal = addPal(palString(T.colors));
		var single = 0, palMap = {};
		function remap(i) {
			if (palMap[i] !== undefined) { return palMap[i]; }
			var src = pals[i] ? pals[i].textContent : '', cols = palColors(src);
			if (!cols.length || isWhite(cols)) { palMap[i] = i; return i; }   // white flashes stay white
			var next = cols.length === 1 ? [T.colors[single++ % T.colors.length]] : T.colors.slice();
			palMap[i] = addPal(palString(next, src));
			return palMap[i];
		}
		var refMap = {};
		function addRef(key) {
			if (refMap[key] !== undefined) { return refMap[key]; }
			var e = d.createElement('Effect'); e.appendChild(d.createTextNode(TPL[key][1])); db.appendChild(e); dbs.push(e);
			refMap[key] = dbs.length - 1; return refMap[key];
		}

		kids(ee, 'Element').forEach(function (el) {
			if (el.getAttribute('type') !== 'model') { return; }
			var name = el.getAttribute('name') || '';
			if (screens[name.toLowerCase()]) { report.screens.push(name); return; }
			var kind = kindOf(name, L), layers = el.getElementsByTagName('EffectLayer'), did = false;
			for (var li = 0; li < layers.length; li++) {
				var effs = kids(layers[li], 'Effect');
				var pulse = effs.length > 1 && effs.every(function (e) { return e.getAttribute('name') === 'On' && (+e.getAttribute('endTime') - +e.getAttribute('startTime')) < 1000; });
				effs.forEach(function (e) {
					var en = e.getAttribute('name');
					if (/^(Faces|Pictures|Video|Text|Shader|Sketch)$/.test(en)) { if (report.kept.indexOf(name) < 0) { report.kept.push(name); } return; }
					var len = +e.getAttribute('endTime') - +e.getAttribute('startTime'), sw = T.swaps[kind];
					if (!pulse && sw && len >= 6000) {
						e.setAttribute('name', TPL[sw][0]); e.setAttribute('ref', String(addRef(sw))); e.setAttribute('palette', String(fullPal));
						report.swapped.push(name + ': ' + en + ' → ' + TPL[sw][0]);
					} else if (e.hasAttribute('palette')) {
						e.setAttribute('palette', String(remap(+e.getAttribute('palette'))));
					}
					report.recolored++; did = true;
				});
			}
		});

		var head = first(d, 'head'), c = head && first(head, 'comment');
		if (c) { while (c.firstChild) { c.removeChild(c.firstChild); } c.appendChild(d.createTextNode('Marquee themed names break: ' + T.name + '. Made from your names break; names are drawn on the screens by FPP.')); }
		var out = new (xml().S)().serializeToString(d);
		if (out.indexOf('<?xml') !== 0) { out = '<?xml version="1.0" encoding="UTF-8"?>\n' + out; }
		return { xsq: out, report: report };
	}

	/** Prop names and kinds from a layout, for the AI (no addresses, nothing else). */
	function propsSummary(rgbText, xsqText) {
		var L = layoutKinds(rgbText), names = [];
		if (xsqText) {
			var d = parse(xsqText, 'The sequence (.xsq)'), ee = first(d, 'ElementEffects');
			kids(ee, 'Element').forEach(function (el) { if (el.getAttribute('type') === 'model' && el.getElementsByTagName('Effect').length) { names.push(el.getAttribute('name')); } });
		} else { names = Object.keys(L.kinds); }
		var by = {};
		names.forEach(function (n) { var k = kindOf(n, L); (by[k] = by[k] || []).push(n); });
		return by;
	}

	/* What each kind of prop does during the names in a brand-new break (a theme's swaps win). */
	var MAIN = { tree: 'spirals', spinner: 'pinwheel', flake: 'twinkle', arch: 'chase', cane: 'chase', line: 'chase', window: 'bars', flood: 'wash', star: 'shimmer', present: 'wash', matrix: 'bars', other: 'twinkle' };
	var CLASSIC = { name: 'Names in Lights', colors: ['#FF0000', '#00C000', '#FFC000', '#0050FF', '#FFFFFF'], swaps: {} };
	function esc(v) { return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
	function ms(sec) { return Math.round(sec * 40) * 25; }   // on the 25 ms frame grid

	/** Make a names break sequence from scratch from a layout: screens left dark for the names,
	    the rest of the house in gentle effects, a flash as each name goes up. */
	function build(rgbText, opts, spec) {
		opts = opts || {};
		var T = cleanTheme(spec || CLASSIC), d = parse(rgbText, 'The layout file (xlights_rgbeffects.xml)');
		var L = layoutKinds(rgbText), screens = {};
		(opts.screens || []).forEach(function (n) { screens[String(n).trim().toLowerCase()] = 1; });
		var start = Math.max(1, +opts.start || 4), slot = Math.max(2.5, +opts.slot || 3.75), count = Math.max(1, Math.min(6, +opts.names || 6));
		var namesEnd = start + count * slot, dur = Math.max(+opts.duration || 0, namesEnd + 3.5);
		var models = [], seen = {};
		var mEl = first(d, 'models');
		kids(mEl || d.documentElement, 'model').forEach(function (m) {
			var n = m.getAttribute('name');
			if (!n || seen[n] || /^(Image|Label|DmxMovingHead.*|DmxFloodlight|Dmx.*)$/.test(m.getAttribute('DisplayAs') || '')) { return; }
			seen[n] = 1; models.push(n);
		});
		if (!models.length) { throw new Error('No props found in that layout file.'); }
		var pals = [], palIdx = {}, db = [], dbIdx = {};
		function pal(cols) { var s = palString(cols); if (palIdx[s] === undefined) { palIdx[s] = pals.length; pals.push(s); } return palIdx[s]; }
		function ref(key, extra) { var s = TPL[key][1] + (extra ? ',' + extra : ''); if (dbIdx[s] === undefined) { dbIdx[s] = db.length; db.push(s); } return dbIdx[s]; }
		var full = pal(T.colors), white = pal(['#FFFFFF']);
		var report = { theme: T.name, props: 0, screens: [], effects: {} };
		var els = [], elx = [];
		function eff(key, a, b, p, extra) { return '<Effect ref="' + ref(key, extra) + '" name="' + esc(TPL[key][0]) + '" startTime="' + ms(a) + '" endTime="' + ms(b) + '" palette="' + p + '"/>'; }
		models.forEach(function (n, i) {
			els.push('<Element collapsed="false" type="model" name="' + esc(n) + '" visible="true"/>');
			if (screens[n.toLowerCase()]) { report.screens.push(n); elx.push('<Element type="model" name="' + esc(n) + '"><EffectLayer/></Element>'); return; }
			var kind = kindOf(n, L), main = T.swaps[kind] || MAIN[kind] || 'twinkle', one = pal([T.colors[i % T.colors.length]]);
			report.props++; report.effects[TPL[main][0]] = (report.effects[TPL[main][0]] || 0) + 1;
			var l1 = eff('twinkle', 0, start, one, 'T_TEXTCTRL_Fadein=1.00') + eff(main, start, namesEnd, full) + eff('twinkle', namesEnd, dur, full, 'T_TEXTCTRL_Fadeout=1.50');
			var l2 = '';
			if (/^(tree|arch|cane|line|window|flake|star)$/.test(kind)) {
				for (var k = 0; k < count; k++) { l2 += eff('on', start + k * slot, start + k * slot + 0.4, white, 'T_TEXTCTRL_Fadeout=0.35'); }
			}
			elx.push('<Element type="model" name="' + esc(n) + '">' + (l2 ? '<EffectLayer>' + l2 + '</EffectLayer>' : '') + '<EffectLayer>' + l1 + '</EffectLayer></Element>');
		});
		var tim = '<Effect label="Intro" startTime="0" endTime="' + ms(start) + '"/>';
		for (var k2 = 0; k2 < count; k2++) { tim += '<Effect label="Name ' + (k2 + 1) + '" startTime="' + ms(start + k2 * slot) + '" endTime="' + ms(start + (k2 + 1) * slot) + '"/>'; }
		tim += '<Effect label="Outro" startTime="' + ms(namesEnd) + '" endTime="' + ms(dur) + '"/>';
		var x = '<?xml version="1.0" encoding="UTF-8"?>\n<xsequence BaseChannel="0" ChanCtrlBasic="0" ChanCtrlColor="0" FixedPointTiming="1" ModelBlending="true">\n' +
			'<head><version>2024.05</version><author>Marquee</author><author-email/><author-website/><song>' + esc(T.name) + '</song><artist/><album/><MusicURL/>' +
			'<comment>' + esc('Marquee names break: ' + T.name + '. The names are drawn on your screens by FPP; keep them dark here. Render it in xLights, save, and upload the .fseq to FPP.') + '</comment>' +
			'<sequenceTiming>25 ms</sequenceTiming><sequenceType>Media</sequenceType><mediaFile>' + esc(opts.music || '') + '</mediaFile><sequenceDuration>' + (ms(dur) / 1000).toFixed(3) + '</sequenceDuration><imageDir/></head>\n' +
			'<ColorPalettes>' + pals.map(function (p) { return '<ColorPalette>' + esc(p) + '</ColorPalette>'; }).join('') + '</ColorPalettes>\n' +
			'<EffectDB>' + db.map(function (e) { return '<Effect>' + esc(e) + '</Effect>'; }).join('') + '</EffectDB>\n' +
			'<DataLayers><DataLayer lor_params="0" channel_offset="0" num_channels="0" num_frames="0" data="&lt;rendered: erase-mode&gt;" source="&lt;auto-generated&gt;" name="Nutcracker"/></DataLayers>\n' +
			'<DisplayElements><Element collapsed="false" type="timing" name="Names" visible="true" views="" active="true"/>' + els.join('') + '</DisplayElements>\n' +
			'<ElementEffects><Element type="timing" name="Names"><EffectLayer>' + tim + '</EffectLayer></Element>' + elx.join('') + '</ElementEffects>\n' +
			'<lastView>0</lastView><TimingTags/>\n</xsequence>\n';
		parse(x, 'The new sequence');    // make sure it's well formed
		report.duration = ms(dur) / 1000;
		return { xsq: x, report: report };
	}

	var api = { THEMES: THEMES, build: build, EFFECTS: EFFECTS, KINDS: KINDS, theme: theme, cleanTheme: cleanTheme, propsSummary: propsSummary, kindOf: function (n, rgb) { return kindOf(n, layoutKinds(rgb)); } };
	if (typeof module !== 'undefined' && module.exports) { module.exports = api; } else { root.MarqueeSeq = api; }
})(typeof window !== 'undefined' ? window : this);
