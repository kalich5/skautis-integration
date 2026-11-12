/**
 * The "Recruitment", "Map of the units" and "Unit" blocks in the block editor.
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

type SkautisUnitSection =
	| 'showAccounts'
	| 'showContacts'
	| 'showMap'
	| 'showPeople'
	| 'showSeat'
	| 'showUnits';

function skautisUnitToggle(
	props: WPBlockEditProps,
	section: SkautisUnitSection,
	label: string,
	defaultValue: boolean
): WPElement {
	return skautisRecruitmentEl(wp.components.ToggleControl, {
		checked: props.attributes[section] ?? defaultValue,
		label,
		onChange: (checked: boolean): void => {
			props.setAttributes({ [section]: checked });
		},
	});
}

wp.blocks.registerBlockType('skautis-integration/unit', {
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
					skautisRecruitmentEl(wp.components.TextControl, {
						help: wp.i18n.__(
							'Leave empty for the unit set on the "Unit" page in the SkautIS menu.',
							'skautis-integration'
						),
						label: wp.i18n.__(
							'Registration number of the unit',
							'skautis-integration'
						),
						onChange: (unit: string): void => {
							props.setAttributes({ unit });
						},
						placeholder: '112.31',
						value: props.attributes.unit ?? '',
					}),
					skautisRecruitmentAppearanceControl(props)
				),
				skautisRecruitmentEl(
					wp.components.PanelBody,
					{
						title: wp.i18n.__(
							'What to show',
							'skautis-integration'
						),
					},
					skautisUnitToggle(
						props,
						'showSeat',
						wp.i18n.__(
							'Seat, registration number and company ID',
							'skautis-integration'
						),
						true
					),
					skautisUnitToggle(
						props,
						'showMap',
						wp.i18n.__('Map of the seat', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showContacts',
						wp.i18n.__('Public contacts', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showPeople',
						wp.i18n.__(
							'Statutory body and assistant',
							'skautis-integration'
						),
						true
					),
					skautisUnitToggle(
						props,
						'showAccounts',
						wp.i18n.__('Bank account', 'skautis-integration'),
						false
					),
					skautisUnitToggle(
						props,
						'showUnits',
						wp.i18n.__('Child units', 'skautis-integration'),
						false
					),
					props.attributes.showMap === false
						? null
						: skautisRecruitmentEl(wp.components.RangeControl, {
								label: wp.i18n.__(
									'Height of the map (px)',
									'skautis-integration'
								),
								max: 800,
								min: 150,
								onChange: (
									height: number | undefined
								): void => {
									props.setAttributes({
										mapHeight: height ?? 300,
									});
								},
								step: 10,
								value: props.attributes.mapHeight,
							})
				)
			),
			skautisRecruitmentEl(wp.serverSideRender, {
				attributes: props.attributes,
				block: 'skautis-integration/unit',
			})
		),
	save: (): null => null,
});
