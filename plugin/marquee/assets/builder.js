/* Page builder in the editor: the Show Box (and its ready-made kinds), the Live Camera's settings,
   and "a button for this page at the top of my website". No build step: plain wp.element. */
(function (wp, B) {
	'use strict';
	if (!wp || !wp.blocks || !B) { return; }
	var el = wp.element.createElement, F = wp.element.Fragment;
	var be = wp.blockEditor, C = wp.components;
	var tr = function (s) { return (B.t && B.t[s]) || s; };
	var IMG = B.img;
	var COLORS = { red: ['#7a1016', '#3b0609'], green: ['#135a30', '#062512'], blue: ['#12295a', '#071531'], night: ['#16213f', '#070d1f'] };

	/* ---------- shared settings: color, see-through, snow, lights ---------- */
	function Swatches(p) {
		return el('div', { className: 'mq-sw' }, Object.keys(B.colors).map(function (k) {
			var on = (p.value || 'red') === k;
			return el('button', {
				key: k, type: 'button', className: 'mq-sw__b' + (on ? ' is-on' : ''), 'aria-pressed': on,
				onClick: function () { p.onChange(k); }
			}, el('span', { style: { background: 'linear-gradient(160deg,' + COLORS[k][0] + ',' + COLORS[k][1] + ')' } }), B.colors[k]);
		}));
	}
	function boxPanels(a, set, opts) {
		opts = opts || {};
		var out = [
			el(C.PanelBody, { key: 'c', title: tr('Color'), initialOpen: true },
				el(Swatches, { value: a.color, onChange: function (v) { set({ color: v }); } }),
				B.boxAll ? el('p', { className: 'mq-ed-note' }, tr('Your website’s Box colors setting (Look & colors) shows every box in one color.')) : null),
			el(C.PanelBody, { key: 's', title: tr('See-through'), initialOpen: true },
				el(C.ToggleControl, {
					label: tr('Use my website’s setting'),
					help: a.seeOwn ? tr('Your background shows through this box only. The rest of your site keeps its own setting (Look & colors).') : (B.siteSee ? tr('Your website’s boxes are set to') + ' ' + B.siteSee + '%.' : tr('Your website’s boxes are solid.')),
					checked: !a.seeOwn,
					onChange: function (v) { set({ seeOwn: !v }); }
				}),
				a.seeOwn ? el(C.RangeControl, {
					label: tr('See-through'), value: a.see, min: 0, max: 50, step: 1,
					onChange: function (v) { set({ see: v || 0 }); },
					renderTooltipContent: function (v) { return v ? v + '%' : tr('Solid'); }
				}) : null,
				!B.festive ? el('p', { className: 'mq-ed-note' }, tr('See-through works with the Marquee Festive theme.')) : null)
		];
		if (!opts.noTitle) {
			out.push(el(C.PanelBody, { key: 't', title: tr('Title'), initialOpen: true },
				el(C.ToggleControl, { label: tr('Ribbon title above the box'), checked: !!a.ribbon, onChange: function (v) { set({ ribbon: v }); } }),
				a.ribbon ? el(C.TextControl, { label: tr('Title'), value: a.title, onChange: function (v) { set({ title: v }); } }) : null));
		}
		out.push(el(C.PanelBody, { key: 'd', title: tr('Snow and lights'), initialOpen: false },
			el(C.ToggleControl, { label: tr('Snow on top'), checked: a.snow !== false, onChange: function (v) { set({ snow: v }); } }),
			el(C.ToggleControl, { label: tr('Lights around the edge'), checked: a.lights !== false, onChange: function (v) { set({ lights: v }); } }),
			el('p', { className: 'mq-ed-note' }, tr('The box changes with your look: Classic, Christmas Village or Santa’s North Pole.'))));
		return out;
	}
	function colorToolbar(a, set) {
		return el(be.BlockControls, { group: 'block' }, el(C.ToolbarGroup, null, Object.keys(B.colors).map(function (k) {
			return el(C.ToolbarButton, {
				key: k, label: B.colors[k], isPressed: (a.color || 'red') === k, onClick: function () { set({ color: k }); },
				icon: el('span', { className: 'mq-tbdot', style: { background: COLORS[k][0] } })
			});
		})));
	}
	function boxClass(a, extra) {
		return 'rr-panel rr-panel--' + (a.color || 'red') + ' mq-box' + (extra ? ' ' + extra : '') +
			(a.snow === false ? ' mq-box--nosnow' : '') + (a.lights === false ? ' mq-box--nolights' : '');
	}
	function boxStyle(a) {
		return a.seeOwn ? { '--mq-see': String(1 - (a.see || 0) / 100) } : (B.siteSee ? { '--mq-see': String(1 - B.siteSee / 100) } : {});
	}
	function Ribbon(a) {
		var t = (a.title || '').trim();
		return a.ribbon && t ? el('h2', { className: 'mq-ed-ribbon' }, el('span', null, t)) : null;
	}

	/* ---------- the Show Box ---------- */
	var P = function (t, extra) { return ['core/paragraph', Object.assign({ placeholder: t }, extra || {})]; };
	var person = function (i, name, role) {
		return ['core/column', {}, [['core/image', { url: IMG + 'person-' + i + '.webp', className: 'is-style-mq-round' }], ['core/heading', { level: 4, content: name }], ['core/paragraph', { content: role }]]];
	};
	var KINDS = {
		'': { title: tr('Show Box'), desc: tr('A box in your show’s colors, with snow on top and lights around it. Put anything inside.'), icon: 'align-wide', tpl: [P(tr('Type here, or press / to add a photo, a video, columns, buttons…'))] },
		photo: { title: tr('Photo & Words'), desc: tr('A photo next to a few lines of words.'), icon: 'align-pull-left', tpl: [['core/media-text', { mediaType: 'image', mediaUrl: IMG + 'house.webp' }, [['core/heading', { level: 3, content: tr('How it started') }], P(tr('Tell your story here.'))]]] },
		people: { title: tr('People'), desc: tr('Round photos with a name and a line about each person.'), icon: 'groups', ribbon: tr('Meet the Family'), color: 'green', tpl: [['core/columns', {}, [person(1, tr('Name'), tr('What they do')), person(2, tr('Name'), tr('What they do')), person(3, tr('Name'), tr('What they do')), person(4, tr('Name'), tr('What they do'))]]] },
		then: { title: tr('Then & Now'), desc: tr('Two photos side by side, like your first year and this year.'), icon: 'image-flip-horizontal', ribbon: tr('Then and Now'), color: 'blue', tpl: [['core/columns', {}, [['core/column', {}, [['core/image', { url: IMG + 'then.webp', className: 'is-style-mq-badge', caption: tr('Our first year') }]]], ['core/column', {}, [['core/image', { url: IMG + 'now.webp', className: 'is-style-mq-badge', caption: tr('This year') }]]]]], ['core/paragraph', { align: 'center', content: tr('Year one: a few hundred lights. This year: thousands.') }]] },
		faq: { title: tr('Questions'), desc: tr('Questions visitors can open to read the answer.'), icon: 'editor-help', ribbon: tr('Questions We Get'), color: 'green', tpl: [['core/details', { summary: tr('Where do I park?') }, [P(tr('Type the answer.'))]], ['core/details', { summary: tr('How do I hear the music?') }, [P(tr('Type the answer.'))]], ['core/details', { summary: tr('Can we get out of the car?') }, [P(tr('Type the answer.'))]]] },
		gallery: { title: tr('Photo Gallery'), desc: tr('A grid of your photos. Visitors can tap one to see it big.'), icon: 'format-gallery', ribbon: tr('Photo Gallery'), color: 'blue', tpl: [['core/gallery', { linkTo: 'media' }, ['gallery-1', 'gallery-2', 'gallery-3', 'gallery-4', 'now', 'house'].map(function (g) { return ['core/image', { url: IMG + g + '.webp' }]; })]] }
	};
	var attrs = {
		color: { type: 'string', default: 'red' }, title: { type: 'string', default: '' }, ribbon: { type: 'boolean', default: false },
		snow: { type: 'boolean', default: true }, lights: { type: 'boolean', default: true },
		seeOwn: { type: 'boolean', default: false }, see: { type: 'number', default: 25 }, align: { type: 'string' },
		kind: { type: 'string', default: '' }
	};
	wp.blocks.registerBlockType('marquee/show-box', {
		apiVersion: 2,
		title: KINDS[''].title,
		description: KINDS[''].desc,
		icon: el('svg', { viewBox: '0 0 24 24', width: 24, height: 24 }, el('rect', { x: 3, y: 6, width: 18, height: 14, rx: 2.5, fill: '#b3121b' }), el('path', { d: 'M3 8.5c1.5-2 3-2 4.5 0s3 2 4.5 0 3-2 4.5 0 3 2 4.5 0V8c0-1.4-1.1-2.5-2.5-2.5h-13C4.1 5.5 3 6.6 3 8z', fill: '#fff' })),
		category: 'marquee-boxes',
		keywords: [tr('box'), tr('section'), tr('panel'), 'marquee'],
		supports: { html: false, align: ['wide', 'full'], anchor: true },
		attributes: attrs,
		variations: Object.keys(KINDS).map(function (k) {
			var v = KINDS[k];
			return {
				name: k || 'box', title: v.title, description: v.desc, icon: v.icon, isDefault: !k, scope: ['inserter', 'transform'],
				attributes: Object.assign({ kind: k }, v.ribbon ? { ribbon: true, title: v.ribbon } : {}, v.color ? { color: v.color } : {}),
				innerBlocks: v.tpl,
				isActive: function (a) { return (a.kind || '') === k; }
			};
		}),
		edit: function (props) {
			var a = props.attributes, set = props.setAttributes;
			var bp = be.useBlockProps({ className: 'mq-ed-wrap' + (a.seeOwn ? ' mq-see' : ''), style: boxStyle(a) });
			var inner = be.useInnerBlocksProps({ className: boxClass(a, a.kind ? 'mq-box--' + a.kind : '') }, { template: KINDS[a.kind || ''] ? KINDS[a.kind || ''].tpl : KINDS[''].tpl, templateLock: false });
			return el(F, null,
				colorToolbar(a, set),
				el(be.InspectorControls, null, boxPanels(a, set)),
				el('section', bp, Ribbon(a), el('div', inner)));
		},
		save: function () { return el(be.InnerBlocks.Content); }
	});

	/* ---------- the Live Camera, with its own settings ---------- */
	var D = window.MarqueeBlocks || {};
	var live = (D.sections || []).filter(function (s) { return s.slug === 'live-cam'; })[0];
	if (live && !wp.blocks.getBlockType('marquee/live-cam')) {
		wp.blocks.registerBlockType('marquee/live-cam', {
			apiVersion: 2,
			title: live.title,
			description: tr('Your YouTube live stream in a show box. Visitors tap to watch and listen.'),
			icon: live.icon,
			category: 'marquee',
			keywords: ['youtube', tr('live'), tr('camera'), 'marquee'],
			supports: { html: false, align: ['wide', 'full'] },
			attributes: Object.assign({}, attrs, {
				ownUrl: { type: 'boolean', default: false }, url: { type: 'string', default: '' }, text: { type: 'string', default: '' },
				hoursOnly: { type: 'boolean', default: false }, boxed: { type: 'boolean', default: false }
			}),
			variations: [{ name: 'live', title: live.title, isDefault: true, scope: ['inserter'], attributes: { boxed: true } }],
			edit: function (props) {
				var a = props.attributes, set = function (o) { props.setAttributes(Object.assign({ boxed: true }, o)); };
				var title = (a.title || '').trim() || B.liveT, text = (a.text || '').trim() || B.liveX;
				var url = a.ownUrl ? a.url : B.liveUrl;
				var bp = be.useBlockProps({ className: 'mq-ed-wrap' + (a.seeOwn ? ' mq-see' : ''), style: boxStyle(a) });
				return el(F, null,
					colorToolbar(a, set),
					el(be.InspectorControls, null,
						el(C.PanelBody, { title: tr('YouTube link'), initialOpen: true },
							el(C.RadioControl, {
								selected: a.ownUrl ? 'own' : 'site',
								options: [{ label: tr('Use my live camera (the link in Marquee → Settings → Home page)'), value: 'site' }, { label: tr('Use a different link (a second camera, or last year’s show)'), value: 'own' }],
								onChange: function (v) { set({ ownUrl: v === 'own' }); }
							}),
							a.ownUrl ? el(C.TextControl, { label: tr('YouTube link'), value: a.url, placeholder: 'https://www.youtube.com/watch?v=…', onChange: function (v) { set({ url: v }); } })
								: el('p', { className: 'mq-ed-note' }, B.liveUrl || tr('No live camera link yet. Add it in Marquee → Settings → Home page → Live camera.'))),
						el(C.PanelBody, { title: tr('Words'), initialOpen: true },
							el(C.TextControl, { label: tr('Title'), value: a.title, placeholder: B.liveT, onChange: function (v) { set({ title: v }); } }),
							el(C.TextareaControl, { label: tr('Line under the video'), value: a.text, placeholder: B.liveX, rows: 2, onChange: function (v) { set({ text: v }); } }),
							el(C.ToggleControl, { label: tr('Only during show hours'), help: tr('Hidden when the lights are off.'), checked: !!a.hoursOnly, onChange: function (v) { set({ hoursOnly: v }); } })),
						boxPanels(a, set, { noTitle: true })),
					el('section', bp,
						title ? el('h2', { className: 'mq-ed-ribbon' }, el('span', null, title)) : null,
						el('div', { className: boxClass(a, 'mq-ed-live') },
							el('div', { className: 'mq-ed-live__frame' + (url ? '' : ' is-empty') },
								el('span', { className: 'mq-ed-live__badge' }, tr('LIVE CAM')),
								el('span', { className: 'mq-ed-live__play', 'aria-hidden': true }),
								el('span', { className: 'mq-ed-live__cta' }, url ? tr('Tap to watch & listen') : tr('Add a YouTube link in the block settings'))),
							text ? el('p', { className: 'mq-ed-live__text' }, text) : null)));
			},
			save: function () { return null; }
		});
	}

	/* ---------- a button for this page at the top of the website ---------- */
	if (wp.plugins && (wp.editor && wp.editor.PluginDocumentSettingPanel || wp.editPost && wp.editPost.PluginDocumentSettingPanel)) {
		var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || wp.editPost.PluginDocumentSettingPanel;
		wp.plugins.registerPlugin('marquee-menu-button', {
			render: function () {
				var type = wp.data.useSelect(function (s) { return s('core/editor').getCurrentPostType(); }, []);
				var meta = wp.data.useSelect(function (s) { return s('core/editor').getEditedPostAttribute('meta') || {}; }, []);
				var edit = wp.data.useDispatch('core/editor').editPost;
				if (type !== 'page') { return null; }
				return el(Panel, { name: 'marquee-menu', title: tr('Top of your website'), icon: 'star-filled' },
					el(C.ToggleControl, {
						label: tr('Show a button for this page at the top'),
						help: tr('Next to Kids’ Corner and Show Facts. On phones, extra buttons go under More.'),
						checked: !!meta.mq_menu,
						onChange: function (v) { edit({ meta: { mq_menu: v } }); }
					}));
			}
		});
	}
})(window.wp, window.MarqueeBuilder);
