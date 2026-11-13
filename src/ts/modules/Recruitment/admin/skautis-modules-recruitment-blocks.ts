/**
 * The "Recruitment", "Map of the units" and "Unit" blocks in the block editor.
 *
 * Both blocks are rendered on the server, the editor shows their server rendering. The appearances of the blocks come from the server in skautisRecruitmentAppearances.
 */

const skautisRecruitmentEl = wp.element.createElement;

/**
 * A unit with recruitment information, from the REST route of the plugin.
 */
interface SkautisRecruitmentUnit {
	group: string;
	id: number;
	name: string;
	registration_number: string;
}

/**
 * The list of the units below the unit of the site, from the REST route of the plugin.
 */
interface SkautisUnitList {
	unit: string;
	units: Array<{ label: string; value: string }>;
}

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

/**
 * The panel with the choice of the units shown by a block. No chosen unit means all units.
 *
 * @param root0            - The properties of the component.
 * @param root0.blockProps - The properties of the block.
 */
function SkautisRecruitmentUnitsPanel({
	blockProps,
}: {
	blockProps: WPBlockEditProps;
}): WPElement {
	const [units, setUnits] =
		wp.element.useState<Array<SkautisRecruitmentUnit> | null>(null);
	const unit = blockProps.attributes.unit ?? '';
	wp.element.useEffect(() => {
		let active = true;
		setUnits(null);
		// Wait until the registration number is typed.
		const timer = setTimeout(() => {
			wp.apiFetch<Array<SkautisRecruitmentUnit>>({
				path: wp.url.addQueryArgs(
					'/skautis-integration/v1/recruitment-units',
					{ unit }
				),
			})
				.then((list) => {
					if (active) {
						setUnits(list);
					}
				})
				.catch(() => {
					if (active) {
						setUnits([]);
					}
				});
		}, 500);
		return (): void => {
			active = false;
			clearTimeout(timer);
		};
	}, [unit]);

	const chosen = blockProps.attributes.units ?? [];
	let content: Array<WPElement>;
	if (units === null) {
		content = [
			skautisRecruitmentEl(
				'p',
				null,
				wp.i18n.__('Loading the units…', 'skautis-integration')
			),
		];
	} else if (units.length === 0) {
		content = [
			skautisRecruitmentEl(
				'p',
				null,
				wp.i18n.__(
					'No units with recruitment information were found.',
					'skautis-integration'
				)
			),
		];
	} else {
		content = [
			skautisRecruitmentEl(
				'p',
				{ className: 'components-base-control__help' },
				wp.i18n.__(
					'Choose the units to show. When none is chosen, all units are shown.',
					'skautis-integration'
				)
			),
		];
		let group = '';
		for (const item of units) {
			if (item.group !== group) {
				group = item.group;
				content.push(skautisRecruitmentEl('strong', null, group));
			}
			content.push(
				skautisRecruitmentEl(wp.components.CheckboxControl, {
					checked: chosen.includes(item.id),
					key: item.id,
					label: item.name,
					onChange: (checked: boolean): void => {
						blockProps.setAttributes({
							units: checked
								? [...chosen, item.id]
								: chosen.filter((id) => id !== item.id),
						});
					},
				})
			);
		}
	}
	return skautisRecruitmentEl(
		wp.components.PanelBody,
		{
			initialOpen: false,
			title: wp.i18n.__('Units', 'skautis-integration'),
		},
		...content
	);
}

/**
 * The choice of the unit of a block: a list of the units below the unit set on the page of the module, or a field for the registration number when the list cannot be loaded.
 *
 * @param root0            - The properties of the component.
 * @param root0.blockProps - The properties of the block.
 * @param root0.module     - The module whose unit is used: "recruitment" or "unit".
 */
function SkautisUnitControl({
	blockProps,
	module,
}: {
	blockProps: WPBlockEditProps;
	module: 'recruitment' | 'unit';
}): WPElement {
	const [list, setList] = wp.element.useState<SkautisUnitList | null>(null);
	const [failed, setFailed] = wp.element.useState(false);
	wp.element.useEffect(() => {
		let active = true;
		wp.apiFetch<SkautisUnitList>({
			path: wp.url.addQueryArgs('/skautis-integration/v1/unit-list', {
				module,
			}),
		})
			.then((result) => {
				if (active) {
					setList(result);
				}
			})
			.catch(() => {
				if (active) {
					setFailed(true);
				}
			});
		return (): void => {
			active = false;
		};
	}, [module]);

	const value = blockProps.attributes.unit ?? '';
	const onChange = (unit: string): void => {
		// The chosen units of the recruitment belong to the previous unit.
		blockProps.setAttributes(
			module === 'recruitment' ? { unit, units: [] } : { unit }
		);
	};
	if (list === null && !failed) {
		return skautisRecruitmentEl(
			'p',
			null,
			wp.i18n.__('Loading the units…', 'skautis-integration')
		);
	}
	if (list === null || list.units.length === 0) {
		return skautisRecruitmentEl(wp.components.TextControl, {
			help: wp.i18n.__(
				'Fill in the registration number, or leave it empty for the unit set on the page of the module in the SkautIS menu.',
				'skautis-integration'
			),
			label: wp.i18n.__(
				'Registration number of the unit',
				'skautis-integration'
			),
			onChange,
			placeholder: '112.31',
			value,
		});
	}

	const options = [
		{
			label: wp.i18n.sprintf(
				/* Translators: %s: The registration number of the unit set on the page of the module */
				wp.i18n.__(
					'The unit from the settings (%s)',
					'skautis-integration'
				),
				list.unit
			),
			value: '',
		},
		...list.units,
	];
	// A unit set before, which is not in the list any more, stays in the list, so that it is not lost.
	if (value !== '' && !list.units.some((item) => item.value === value)) {
		options.push({ label: value, value });
	}
	const label = wp.i18n.__('Unit', 'skautis-integration');
	const help =
		module === 'recruitment'
			? wp.i18n.__(
					'The units below the unit set on the "Recruitment" page in the SkautIS menu. For a group or a district, the units below it are shown too.',
					'skautis-integration'
				)
			: wp.i18n.__(
					'The units below the unit set on the "Unit" page in the SkautIS menu.',
					'skautis-integration'
				);

	return skautisRecruitmentEl(wp.components.SelectControl, {
		help,
		label,
		onChange,
		options,
		value,
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
					skautisRecruitmentEl(SkautisUnitControl, {
						blockProps: props,
						module: 'recruitment',
					}),
					skautisRecruitmentAppearanceControl(props)
				),
				skautisRecruitmentEl(SkautisRecruitmentUnitsPanel, {
					blockProps: props,
				}),
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
						'showGroups',
						wp.i18n.__(
							'Group the units by their parent units',
							'skautis-integration'
						),
						true
					),
					skautisRecruitmentToggle(
						props,
						'showFilter',
						wp.i18n.__(
							'Filter for the visitors (unit, age, boys or girls)',
							'skautis-integration'
						),
						false
					),
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
					skautisRecruitmentEl(SkautisUnitControl, {
						blockProps: props,
						module: 'recruitment',
					}),
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
				),
				skautisRecruitmentEl(SkautisRecruitmentUnitsPanel, {
					blockProps: props,
				})
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
					skautisRecruitmentEl(SkautisUnitControl, {
						blockProps: props,
						module: 'unit',
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
					skautisUnitToggle(
						props,
						'showChildCards',
						wp.i18n.__(
							'Cards of the child units (e.g. the groups of a district)',
							'skautis-integration'
						),
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
