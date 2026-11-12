/**
 * The "Recruitment" and "Map of the units" blocks in the block editor.
 *
 * Both blocks are rendered on the server, the editor shows their server rendering. The appearances of the blocks come from the server in skautisRecruitmentAppearances.
 */

const skautisRecruitmentEl = wp.element.createElement;

function skautisRecruitmentAppearanceControl(
	props: WPBlockEditProps
): WPElement {
	return skautisRecruitmentEl(wp.components.SelectControl, {
		help: wp.i18n.__(
			'The appearances are managed in SkautIS → Recruitment.',
			'skautis-integration'
		),
		label: wp.i18n.__('Appearance', 'skautis-integration'),
		onChange: (appearance: string): void => {
			props.setAttributes({ appearance });
		},
		options: skautisRecruitmentAppearances,
		value: props.attributes.appearance ?? '',
	});
}

wp.blocks.registerBlockType('skautis-integration/recruitment', {
	apiVersion: 3,
	edit: (props: WPBlockEditProps): WPElement =>
		skautisRecruitmentEl(
			'div',
			wp.blockEditor.useBlockProps(),
			skautisRecruitmentEl(
				wp.blockEditor.InspectorControls,
				null,
				skautisRecruitmentEl(
					wp.components.PanelBody,
					{
						title: wp.i18n.__('Settings', 'skautis-integration'),
					},
					skautisRecruitmentAppearanceControl(props)
				)
			),
			skautisRecruitmentEl(wp.serverSideRender, {
				attributes: props.attributes,
				block: 'skautis-integration/recruitment',
			})
		),
	save: (): null => null,
});

wp.blocks.registerBlockType('skautis-integration/unit-map', {
	apiVersion: 3,
	edit: (props: WPBlockEditProps): WPElement =>
		skautisRecruitmentEl(
			'div',
			wp.blockEditor.useBlockProps(),
			skautisRecruitmentEl(
				wp.blockEditor.InspectorControls,
				null,
				skautisRecruitmentEl(
					wp.components.PanelBody,
					{
						title: wp.i18n.__('Settings', 'skautis-integration'),
					},
					skautisRecruitmentAppearanceControl(props),
					skautisRecruitmentEl(wp.components.RangeControl, {
						label: wp.i18n.__(
							'Height of the map (px)',
							'skautis-integration'
						),
						max: 1000,
						min: 200,
						onChange: (height: number | undefined): void => {
							props.setAttributes({ height: height ?? 400 });
						},
						step: 10,
						value: props.attributes.height,
					})
				)
			),
			skautisRecruitmentEl(wp.serverSideRender, {
				attributes: props.attributes,
				block: 'skautis-integration/unit-map',
			})
		),
	save: (): null => null,
});
