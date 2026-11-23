/**
 * The redirect rules on the Login tab of the settings: adds and removes the rows and orders them by dragging, like the registration rules.
 *
 * The rows are saved in their order on the page; the indexes in the names only keep the fields of a row together.
 */

function skautisLoginRedirectRules(): void {
	const list = document.querySelector<HTMLElement>(
		'.skautis-login-redirect-rules'
	);
	const template = document.querySelector<HTMLTemplateElement>(
		'#skautis-login-redirect-rule'
	);
	const add = document.querySelector<HTMLButtonElement>(
		'#skautis-login-redirect-add'
	);
	if (!list || !template || !add) {
		return;
	}
	const initSelect = (row: Element): void => {
		jQuery(row).find('select.select2').select2({ width: '100%' });
	};
	jQuery(list)
		.children()
		.each((_, row) => {
			initSelect(row);
		});
	jQuery(list).sortable({ handle: '.handle' });

	let next = list.children.length;
	add.addEventListener('click', () => {
		const html = template.innerHTML.replace(/__index__/gu, String(next++));
		list.insertAdjacentHTML('beforeend', html);
		const row = list.lastElementChild;
		if (row) {
			initSelect(row);
		}
	});
	list.addEventListener('click', (event) => {
		const button = (event.target as HTMLElement).closest(
			'[data-skautis-remove]'
		);
		button?.closest('.skautis-rule-row')?.remove();
	});
	add.hidden = false;
}

skautisLoginRedirectRules();
