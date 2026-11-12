/**
 * The "Recruitment", "Map of the units" and "Unit" blocks in the block editor.
 *
 * Both blocks are rendered on the server, the editor shows their server rendering. The appearances of the blocks come from the server in skautisRecruitmentAppearances.
 */

const skautisRecruitmentEl = wp.element.createElement;

function skautisRecruitmentAppearanceControl(
	props: WPBlockEditProps,
	unit = false
): WPElement {
	return skautisRecruitmentEl(wp.components.SelectControl, {
		help: unit
			? wp.i18n.__(
					'The appearances are managed in SkautIS → Unit.',
					'skautis-integration'
				)
			: wp.i18n.__(
					'The appearances are managed in SkautIS → Recruitment.',
					'skautis-integration'
				),
		label: wp.i18n.__('Appearance', 'skautis-integration'),
		onChange: (appearance: string): void => {
			props.setAttributes({ appearance });
		},
		options: unit ? skautisUnitAppearances : skautisRecruitmentAppearances,
		value: props.attributes.appearance ?? '',
	});
}

/**
 * A toggle of a part of a block.
 *
 * @param props        - The properties of the block.
 * @param item         - The boolean attribute of the part.
 * @param label        - The label of the toggle.
 * @param defaultValue - Whether the part is shown by default.
 */
function skautisRecruitmentToggle(
	props: WPBlockEditProps,
	item: string,
	label: string,
	defaultValue: boolean
): WPElement {
	return skautisRecruitmentEl(wp.components.ToggleControl, {
		checked:
			(props.attributes[item] as boolean | undefined) ?? defaultValue,
		label,
		onChange: (checked: boolean): void => {
			props.setAttributes({ [item]: checked });
		},
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
				),
				skautisRecruitmentEl(
					wp.components.PanelBody,
					{
						title: wp.i18n.__(
							'What to show',
							'skautis-integration'
						),
					},
					skautisRecruitmentToggle(
						props,
						'showName',
						wp.i18n.__('Name of the unit', 'skautis-integration'),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showRegistrationNumber',
						wp.i18n.__(
							'Registration number',
							'skautis-integration'
						),
						false
					),
					skautisRecruitmentToggle(
						props,
						'showWater',
						wp.i18n.__('Water troop label', 'skautis-integration'),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showCategories',
						wp.i18n.__(
							'For whom (age and boys or girls)',
							'skautis-integration'
						),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showMeetings',
						wp.i18n.__('Meetings', 'skautis-integration'),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showClubrooms',
						wp.i18n.__('Clubrooms', 'skautis-integration'),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showNote',
						wp.i18n.__('Note', 'skautis-integration'),
						true
					)
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

/**
 * The attributes of the first version of the "Unit" block, which turned on or off several items at once, in the form `item: old attribute`.
 */
const skautisUnitLegacyItems: Record<string, string> = {
	showAddress: 'showSeat',
	showAssistant: 'showPeople',
	showContactPerson: 'showPeople',
	showEmail: 'showContacts',
	showIc: 'showSeat',
	showOtherContacts: 'showContacts',
	showPhone: 'showContacts',
	showRegistrationNumber: 'showSeat',
	showStatutory: 'showPeople',
	showWeb: 'showContacts',
};

/**
 * A toggle of an item of the "Unit" block.
 *
 * A block from the first version may have an old attribute turned off, which hides several items. The first change moves it to the new attributes.
 *
 * @param props        - The properties of the block.
 * @param item         - The boolean attribute of the item.
 * @param label        - The label of the toggle.
 * @param defaultValue - Whether the item is shown by default.
 */
function skautisUnitToggle(
	props: WPBlockEditProps,
	item: string,
	label: string,
	defaultValue: boolean
): WPElement {
	const legacy = skautisUnitLegacyItems[item] as string | undefined;
	const hiddenByLegacy =
		legacy !== undefined && props.attributes[legacy] === false;
	return skautisRecruitmentEl(wp.components.ToggleControl, {
		checked: hiddenByLegacy
			? false
			: ((props.attributes[item] as boolean | undefined) ?? defaultValue),
		label,
		onChange: (checked: boolean): void => {
			const attributes: Record<string, boolean | undefined> = {
				[item]: checked,
			};
			if (hiddenByLegacy) {
				for (const [other, otherLegacy] of Object.entries(
					skautisUnitLegacyItems
				)) {
					if (otherLegacy === legacy && other !== item) {
						attributes[other] = false;
					}
				}
				attributes[legacy] = undefined;
			}
			props.setAttributes(attributes);
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
					skautisRecruitmentAppearanceControl(props, true)
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
						'showName',
						wp.i18n.__('Name of the unit', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showAddress',
						wp.i18n.__('Seat', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showRegistrationNumber',
						wp.i18n.__(
							'Registration number',
							'skautis-integration'
						),
						true
					),
					skautisUnitToggle(
						props,
						'showIc',
						wp.i18n.__('Company ID (IČ)', 'skautis-integration'),
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
						'showEmail',
						wp.i18n.__('E-mail', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showPhone',
						wp.i18n.__('Phone', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showWeb',
						wp.i18n.__('Web', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showOtherContacts',
						wp.i18n.__('Other contacts', 'skautis-integration'),
						true
					),
					skautisUnitToggle(
						props,
						'showStatutory',
						wp.i18n.__(
							'Statutory body (leader)',
							'skautis-integration'
						),
						true
					),
					skautisUnitToggle(
						props,
						'showAssistant',
						wp.i18n.__(
							'Assistant of the statutory body',
							'skautis-integration'
						),
						true
					),
					skautisUnitToggle(
						props,
						'showContactPerson',
						wp.i18n.__('Contact person', 'skautis-integration'),
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
