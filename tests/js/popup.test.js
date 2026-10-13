import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript, polyfillDialog } from './helpers.js';

const popup = (config) => `
	<nm-popup data-config='${JSON.stringify(config)}'>
		<dialog><form method="dialog"><button>x</button></form><h2>Hi</h2></dialog>
	</nm-popup>`;

const base = {
	id: 'p1',
	version: 1,
	frequency: 'once',
	onLoad: false,
	delay: 0,
	onScroll: false,
	scrollValue: 0,
	scrollUnit: 'percent',
	scrollElement: '',
	click: ''
};

beforeAll(() => {
	polyfillDialog();
	loadScript('nm-storage.js');
	loadScript('nm-popup.js');
});

beforeEach(() => {
	localStorage.clear();
	sessionStorage.clear();
	document.body.innerHTML = '';
	vi.useFakeTimers();
});

describe('<nm-popup>', () => {
	it('opens on load after the configured delay', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true, delay: 2000 });
		const dialog = document.querySelector('dialog');

		vi.advanceTimersByTime(1999);
		expect(dialog.open).toBe(false);
		vi.advanceTimersByTime(1);
		expect(dialog.open).toBe(true);
	});

	it('remembers a close and stays away afterwards (frequency: once)', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true });
		vi.runAllTimers();
		document.querySelector('dialog').close();

		document.body.innerHTML = popup({ ...base, onLoad: true });
		vi.runAllTimers();
		expect(document.querySelector('nm-popup')).toBeNull();
	});

	it('closes when the backdrop (the dialog itself) is clicked, but not its content', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true });
		vi.runAllTimers();
		const dialog = document.querySelector('dialog');

		dialog.querySelector('h2').click();
		expect(dialog.open).toBe(true);
		dialog.click();
		expect(dialog.open).toBe(false);
	});

	it('opens on a click trigger, and reopens after being closed even when frequency suppresses it', () => {
		document.body.innerHTML = `<a class="open-popup" href="#">Open</a>${popup({ ...base, click: '.open-popup' })}`;
		const dialog = document.querySelector('dialog');
		const trigger = document.querySelector('.open-popup');

		trigger.click();
		expect(dialog.open).toBe(true);
		dialog.close();
		expect(localStorage.getItem('nm_closed_p1')).not.toBeNull();

		trigger.click();
		expect(dialog.open).toBe(true);
	});

	it('does not open automatically when suppressed, but keeps its click trigger', () => {
		window.NotificationManager.storage.markClosed('p1', 'once', 1);
		document.body.innerHTML = `<a class="open-popup" href="#">Open</a>${popup({ ...base, onLoad: true, click: '.open-popup' })}`;
		vi.runAllTimers();

		expect(document.querySelector('dialog').open).toBe(false);
		document.querySelector('.open-popup').click();
		expect(document.querySelector('dialog').open).toBe(true);
	});

	it('opens when the scroll threshold is reached', () => {
		Object.defineProperty(document.documentElement, 'scrollHeight', { value: 2000, configurable: true });
		Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true });
		vi.stubGlobal('requestAnimationFrame', (cb) => cb());
		document.body.innerHTML = popup({ ...base, onScroll: true, scrollValue: 50 });
		const dialog = document.querySelector('dialog');

		Object.defineProperty(window, 'scrollY', { value: 400, configurable: true });
		window.dispatchEvent(new Event('scroll'));
		expect(dialog.open).toBe(false);

		Object.defineProperty(window, 'scrollY', { value: 600, configurable: true });
		window.dispatchEvent(new Event('scroll'));
		expect(dialog.open).toBe(true);
		vi.unstubAllGlobals();
	});

	it('survives an invalid selector and a malformed config', () => {
		expect(() => {
			document.body.innerHTML = popup({ ...base, onLoad: true, scrollElement: '<<bad', click: '[[' });
			document.body.click();
			vi.runAllTimers();
		}).not.toThrow();

		expect(() => {
			document.body.innerHTML = '<nm-popup data-config="{not json"><dialog></dialog></nm-popup>';
		}).not.toThrow();
	});

	it('still opens when storage is blocked', () => {
		vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
			throw new Error('blocked');
		});
		vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
			throw new Error('blocked');
		});
		document.body.innerHTML = popup({ ...base, onLoad: true });
		vi.runAllTimers();

		expect(document.querySelector('dialog').open).toBe(true);
		expect(() => document.querySelector('dialog').close()).not.toThrow();
		vi.restoreAllMocks();
	});
});
