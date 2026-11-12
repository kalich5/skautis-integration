/**
 * The live preview in the editor of an appearance of the recruitment blocks.
 *
 * Each field has the CSS custom property it sets in its data-skautis-property attribute; the checkbox "Like the theme of the site" leaves the property out.
 */

function skautisAppearanceInit(): void {
	const preview = document.querySelector<HTMLElement>(
		'.skautis-appearance-editor-preview .skautis-appearance-preview'
	);
	if (preview === null) {
		return;
	}

	document
		.querySelectorAll<HTMLInputElement>('[data-skautis-property]')
		.forEach((input) => {
			input.addEventListener('input', () => {
				skautisAppearanceUpdate(preview, input);
			});
		});

	document
		.querySelectorAll<HTMLInputElement>('[data-skautis-inherit]')
		.forEach((checkbox) => {
			const input = document.getElementById(
				checkbox.dataset['skautisInherit'] ?? ''
			);
			if (!(input instanceof HTMLInputElement)) {
				return;
			}
			const sync = (): void => {
				input.disabled = checkbox.checked;
				skautisAppearanceUpdate(preview, input);
			};
			checkbox.addEventListener('change', sync);
			sync();
		});
}

function skautisAppearanceUpdate(
	preview: HTMLElement,
	input: HTMLInputElement
): void {
	const property = input.dataset['skautisProperty'] ?? '';
	if (property === '') {
		return;
	}
	if (input.disabled || input.value === '') {
		preview.style.removeProperty(property);
	} else {
		preview.style.setProperty(
			property,
			input.value + (input.dataset['skautisUnit'] ?? '')
		);
	}
}

wp.domReady(skautisAppearanceInit);
