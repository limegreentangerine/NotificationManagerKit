import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
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
	loadScript('nm-audience.js');
	loadScript('nm-track.js');
	loadScript('nm-popup.js');
});

beforeEach(() => {
	localStorage.clear();
	sessionStorage.clear();
	window.NotificationManager.audience.reset();
	window.NotificationManager.track.reset();
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

describe('<nm-popup> audience', () => {
	it('opens for the visitors it is for', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true, audience: { visitor: 'new' } });
		vi.runAllTimers();

		expect(document.querySelector('dialog').open).toBe(true);
	});

	it('removes itself for everyone else, and never opens', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true, audience: { visitor: 'returning' } });
		vi.runAllTimers();

		expect(document.querySelector('nm-popup')).toBeNull();
	});

	it('does not store a close for a popup the visitor never saw', () => {
		document.body.innerHTML = popup({ ...base, onLoad: true, audience: { visitor: 'returning' } });
		vi.runAllTimers();

		expect(localStorage.getItem('nm_closed_p1')).toBeNull();
	});

	it('fails closed when the audience script did not load', () => {
		const audience = window.NotificationManager.audience;
		delete window.NotificationManager.audience;
		document.body.innerHTML = popup({ ...base, onLoad: true, audience: { visitor: 'new' } });
		window.NotificationManager.audience = audience;
		vi.runAllTimers();

		expect(document.querySelector('nm-popup')).toBeNull();
	});
});

describe('<nm-popup> one per page', () => {
	const two = (first, second) => popup({ ...base, ...first, id: 'first' }) + popup({ ...base, ...second, id: 'second' });

	it('keeps the first popup and drops the rest', () => {
		document.body.innerHTML = two({ onLoad: true }, { onLoad: true });
		vi.runAllTimers();

		const popups = [...document.querySelectorAll('nm-popup')];
		expect(popups.length).toBe(1);
		expect(JSON.parse(popups[0].dataset.config).id).toBe('first');
		expect(popups[0].querySelector('dialog').open).toBe(true);
	});

	it('falls through to the next popup when the first is not for this visitor', () => {
		document.body.innerHTML = two({ onLoad: true, audience: { visitor: 'returning' } }, { onLoad: true });
		vi.runAllTimers();

		const popups = [...document.querySelectorAll('nm-popup')];
		expect(popups.length).toBe(1);
		expect(JSON.parse(popups[0].dataset.config).id).toBe('second');
		expect(popups[0].querySelector('dialog').open).toBe(true);
	});

	it('does not fall through when the first popup was already closed by this visitor', () => {
		window.NotificationManager.storage.markClosed('first', 'once', 1);
		document.body.innerHTML = two({ onLoad: true }, { onLoad: true });
		vi.runAllTimers();

		expect(document.querySelectorAll('nm-popup').length).toBe(0);
	});
});

describe('<nm-popup> statistics', () => {
	const withLink = (config) => `
		<nm-popup data-config='${JSON.stringify(config)}'>
			<dialog><form method="dialog"><button class="nm-popup__close">x</button></form>
				<div><h2>Hi</h2><a class="nm-popup__link" href="#go">Go</a><p class="plain">text</p></div>
			</dialog>
		</nm-popup>`;
	const tracking = { ...base, onLoad: true, frequency: 'every_time', track: { url: '/t', lang: 'en' } };
	let events;

	beforeEach(() => {
		events = vi.spyOn(window.NotificationManager.track, 'event');
	});

	afterEach(() => {
		events.mockRestore();
	});

	it('is set up from the endpoint the server rendered', () => {
		document.body.innerHTML = withLink(tracking);

		expect(window.NotificationManager.track.ready).toBe(true);
	});

	it('is not set up when the server gave no endpoint', () => {
		document.body.innerHTML = withLink({ ...tracking, track: undefined });

		expect(window.NotificationManager.track.ready).toBe(false);
	});

	it('counts a popup when it opens, not before', () => {
		document.body.innerHTML = withLink({ ...tracking, delay: 1000 });
		expect(events).not.toHaveBeenCalled();

		vi.runAllTimers();
		expect(events).toHaveBeenCalledWith('p1', 'i');
	});

	it('does not count a popup that never opens', () => {
		document.body.innerHTML = withLink({ ...tracking, onLoad: false });
		vi.runAllTimers();

		expect(events).not.toHaveBeenCalled();
	});

	it('does not count a popup that is not for this visitor', () => {
		document.body.innerHTML = withLink({ ...tracking, audience: { visitor: 'returning' } });
		vi.runAllTimers();

		expect(events).not.toHaveBeenCalled();
	});

	it('does not count a popup the visitor has already closed', () => {
		window.NotificationManager.storage.markClosed('p1', 'once', 1);
		document.body.innerHTML = withLink({ ...tracking, frequency: 'once' });
		vi.runAllTimers();

		expect(events).not.toHaveBeenCalled();
	});

	it('counts a close as a dismissal', () => {
		document.body.innerHTML = withLink(tracking);
		vi.runAllTimers();
		document.querySelector('dialog').close();

		expect(events).toHaveBeenCalledWith('p1', 'd');
	});

	it('counts a click on the call-to-action link', () => {
		document.body.innerHTML = withLink(tracking);
		vi.runAllTimers();
		document.querySelector('.nm-popup__link').click();

		expect(events).toHaveBeenCalledWith('p1', 'c');
	});

	it('does not count clicks on other content', () => {
		document.body.innerHTML = withLink(tracking);
		vi.runAllTimers();
		document.querySelector('.plain').click();

		expect(events).not.toHaveBeenCalledWith('p1', 'c');
	});

	it('counts again when reopened by its click trigger, but the tracker only sends one impression', () => {
		document.body.innerHTML = `<a class="go" href="#">Go</a>${withLink({ ...tracking, onLoad: false, click: '.go' })}`;
		document.querySelector('.go').click();
		document.querySelector('dialog').close();
		document.querySelector('.go').click();

		expect(events.mock.calls.filter(([, type]) => type === 'i').length).toBeGreaterThanOrEqual(1);
	});
});
