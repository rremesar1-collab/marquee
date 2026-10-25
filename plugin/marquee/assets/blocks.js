/* Marquee blocks in the editor: a labeled box for each show section (the real section is drawn on the site). */
(function (wp, D) {
	'use strict';
	if (!wp || !wp.blocks || !D) { return; }
	var tr = function (s) { var d = window.rrI18n; return (d && d[s]) || s; };
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;
	D.sections.forEach(function (s) {
		wp.blocks.registerBlockType('marquee/' + s.slug, {
			apiVersion: 2,
			title: s.title,
			description: s.desc,
			icon: s.icon,
			category: 'marquee',
			keywords: ['marquee', tr('light show'), tr('christmas')],
			supports: { html: false, align: ['wide', 'full'] },
			attributes: { align: { type: 'string' } },
			edit: function () {
				var props = useBlockProps ? useBlockProps({ className: 'mq-editor-box' }) : { className: 'mq-editor-box' };
				return el('div', props, el(wp.components.Placeholder, {
					icon: s.icon,
					label: s.title,
					instructions: s.desc + ' ' + D.note
				}));
			},
			save: function () { return null; }
		});
	});
})(window.wp, window.MarqueeBlocks);
