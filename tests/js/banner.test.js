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
	loadScript('nm-banner.js');
});

beforeEach(() => {
	localStorage.clear();
	document.body.innerHTML = '';
});

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
