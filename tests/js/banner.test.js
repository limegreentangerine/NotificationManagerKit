import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

const markup = (items, attrs = '') => `
	<nm-banner data-transition="fade" data-interval="3000" ${attrs}>
		<div class="nm-banner__track">
			${items
				.map(
					(id, i) => `<div class="nm-banner__item${i === 0 ? ' is-active' : ''}" data-id="${id}" data-version="1">
						<div class="nm-banner__content">Banner ${id}</div>
						<button type="button" data-nm-close></button>
					</div>`
				)
				.join('')}
		</div>
	</nm-banner>`;

beforeAll(() => {
	loadScript('nm-storage.js');
	loadScript('nm-audience.js');
	loadScript('nm-banner.js');
});

beforeEach(() => {
	localStorage.clear();
	sessionStorage.clear();
	window.NotificationManager.audience.reset();
	document.body.innerHTML = '';
});

const withAudience = (rules) =>
	`<nm-banner data-transition="fade" data-interval="3000">
		<div class="nm-banner__track">
			${rules
				.map(
					([id, audience], i) => `<div class="nm-banner__item${i === 0 ? ' is-active' : ''}" data-id="${id}" data-version="1"${audience ? ` data-audience='${JSON.stringify(audience)}' hidden` : ''}>
						<div class="nm-banner__content">Banner ${id}</div>
					</div>`
				)
				.join('')}
		</div>
	</nm-banner>`;

describe('<nm-banner>', () => {
	it('removes banners the visitor already dismissed', () => {
		window.NotificationManager.storage.dismiss('a', 1);
		document.body.innerHTML = markup(['a', 'b']);

		const ids = [...document.querySelectorAll('.nm-banner__item')].map((el) => el.dataset.id);
		expect(ids).toEqual(['b']);
	});

	it('removes itself when everything was dismissed', () => {
		window.NotificationManager.storage.dismiss('a', 1);
		document.body.innerHTML = markup(['a']);

		expect(document.querySelector('nm-banner')).toBeNull();
	});

	it('dismiss remembers the banner and removes only that one', () => {
		document.body.innerHTML = markup(['a', 'b']);
		document.querySelector('[data-id="a"] [data-nm-close]').click();

		expect(window.NotificationManager.storage.isDismissed('a', 1)).toBe(true);
		expect(document.querySelector('[data-id="a"]')).toBeNull();
		expect(document.querySelector('[data-id="b"]')).not.toBeNull();
	});

	it('rotates only when there is more than one banner', () => {
		vi.useFakeTimers();
		window.matchMedia = () => ({ matches: false });
		document.body.innerHTML = markup(['a', 'b']);

		const items = document.querySelectorAll('.nm-banner__item');
		expect(items[0].classList.contains('is-active')).toBe(true);

		vi.advanceTimersByTime(3000);
		expect(items[0].classList.contains('is-active')).toBe(false);
		expect(items[1].classList.contains('is-active')).toBe(true);

		vi.advanceTimersByTime(3000);
		expect(items[0].classList.contains('is-active')).toBe(true);
		vi.useRealTimers();
	});

	it('shows every banner statically for reduced motion', () => {
		vi.useFakeTimers();
		window.matchMedia = () => ({ matches: true });
		document.body.innerHTML = markup(['a', 'b']);

		expect(document.querySelector('nm-banner').hasAttribute('data-static')).toBe(true);
		vi.advanceTimersByTime(10000);
		expect(document.querySelectorAll('.nm-banner__item.is-active').length).toBe(1);
		vi.useRealTimers();
	});
});

describe('<nm-banner> audience', () => {
	it('shows a targeted banner to the visitors it is for', () => {
		document.body.innerHTML = withAudience([['a', { visitor: 'new' }]]);

		expect(document.querySelector('[data-id="a"]').hidden).toBe(false);
	});

	it('removes a targeted banner for everyone else', () => {
		document.body.innerHTML = withAudience([
			['plain', null],
			['returning-only', { visitor: 'returning' }]
		]);

		const ids = [...document.querySelectorAll('.nm-banner__item')].map((el) => el.dataset.id);
		expect(ids).toEqual(['plain']);
	});

	it('removes the whole banner when nothing is for this visitor', () => {
		document.body.innerHTML = withAudience([['a', { visitor: 'returning' }]]);

		expect(document.querySelector('nm-banner')).toBeNull();
	});

	it('makes the next banner active when the first one is not for this visitor', () => {
		document.body.innerHTML = withAudience([
			['first', { devices: ['mobile'] }],
			['second', null]
		]);

		const item = document.querySelector('[data-id="second"]');
		expect(item.classList.contains('is-active')).toBe(true);
		expect(item.hidden).toBe(false);
	});

	it('fails closed when the audience script did not load', () => {
		const audience = window.NotificationManager.audience;
		delete window.NotificationManager.audience;
		document.body.innerHTML = withAudience([['a', { visitor: 'new' }]]);
		window.NotificationManager.audience = audience;

		expect(document.querySelector('nm-banner')).toBeNull();
	});
});
