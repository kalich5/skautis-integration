/**
 * The filter of the "Recruitment" block for the visitors: by the unit, the age of the child and boys or girls.
 *
 * Each card has its recruitment categories in data-skautis-categories in the form "7-11-male 11-15-any". A card is shown when one of its categories matches the age and the sex; a card without categories is shown only without the age and the sex.
 */

function skautisRecruitmentCardMatches(
	card: HTMLElement,
	age: number | null,
	sex: string
): boolean {
	if (age === null && sex === '') {
		return true;
	}
	const categories = (card.dataset['skautisCategories'] ?? '')
		.split(' ')
		.filter((category) => category !== '');
	return categories.some((category) => {
		const [from, to, categorySex] = category.split('-');
		const ageFrom = parseInt(from, 10);
		const ageTo = parseInt(to, 10);
		const ageMatches =
			age === null ||
			((isNaN(ageFrom) || ageFrom === 0 || age >= ageFrom) &&
				(isNaN(ageTo) || ageTo === 0 || age <= ageTo));
		const sexMatches =
			sex === '' || categorySex === 'any' || categorySex === sex;
		return ageMatches && sexMatches;
	});
}

function skautisRecruitmentFilter(form: HTMLFormElement): void {
	const block = form.parentElement;
	if (block === null) {
		return;
	}
	const noMatch = block.querySelector<HTMLElement>(
		'.skautis-recruitment-no-match'
	);
	const apply = (): void => {
		const group = skautisRecruitmentFilterValue(form, 'group');
		const ageValue = parseInt(
			skautisRecruitmentFilterValue(form, 'age'),
			10
		);
		const age = isNaN(ageValue) ? null : ageValue;
		const sex = skautisRecruitmentFilterValue(form, 'sex');
		let shown = 0;
		block
			.querySelectorAll<HTMLElement>('.skautis-recruitment-group')
			.forEach((groupElement) => {
				let groupShown = 0;
				const groupMatches =
					group === '' ||
					groupElement.dataset['skautisGroup'] === group;
				groupElement
					.querySelectorAll<HTMLElement>('.skautis-recruitment-unit')
					.forEach((card) => {
						const visible =
							groupMatches &&
							skautisRecruitmentCardMatches(card, age, sex);
						card.hidden = !visible;
						if (visible) {
							groupShown++;
						}
					});
				groupElement.hidden = groupShown === 0;
				shown += groupShown;
			});
		if (noMatch !== null) {
			noMatch.hidden = shown > 0;
		}
	};
	form.addEventListener('input', apply);
	form.addEventListener('change', apply);
	form.addEventListener('submit', (event) => {
		event.preventDefault();
	});
	form.hidden = false;
}

function skautisRecruitmentFilterValue(
	form: HTMLFormElement,
	name: string
): string {
	const field = form.querySelector<HTMLInputElement | HTMLSelectElement>(
		`[name="${name}"]`
	);
	return field?.value ?? '';
}

wp.domReady(() => {
	document
		.querySelectorAll<HTMLFormElement>('[data-skautis-recruitment-filter]')
		.forEach(skautisRecruitmentFilter);
});
