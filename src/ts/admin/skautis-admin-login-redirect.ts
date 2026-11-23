/**
 * The redirect rules on the Login tab of the settings: adds, removes and moves the rows.
 *
 * The rows are saved in their order in the table; the indexes in the names only keep the fields of a row together.
 */

function skautisLoginRedirectRules(): void {
	const table = document.querySelector<HTMLTableElement>(
		'.skautis-login-redirect-rules'
	);
	const template = document.querySelector<HTMLTemplateElement>(
		'#skautis-login-redirect-rule'
	);
	const add = document.querySelector<HTMLButtonElement>(
		'#skautis-login-redirect-add'
	);
	const body = table?.tBodies[0];
	if (!body || !template || !add) {
		return;
	}
	let next = body.rows.length;
	add.addEventListener('click', () => {
		const html = template.innerHTML.replace(/__index__/gu, String(next++));
		body.insertAdjacentHTML('beforeend', html);
		body.lastElementChild?.querySelector('select')?.focus();
	});
	body.addEventListener('click', (event) => {
		const button = (event.target as HTMLElement).closest('button');
		const row = button?.closest('tr');
		if (!button || !row) {
			return;
		}
		if (button.hasAttribute('data-skautis-remove')) {
			row.remove();
		} else if (button.dataset['skautisMove'] === '-1') {
			row.previousElementSibling?.before(row);
		} else if (button.dataset['skautisMove'] === '1') {
			row.nextElementSibling?.after(row);
		}
	});
	add.hidden = false;
}

skautisLoginRedirectRules();
