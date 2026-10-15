import { beforeAll, beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

let track;
const config = { url: '/ccm/notification_manager_kit/track', lang: 'en' };
const ID = '441a281e-c719-11f1-bd37-cfbc4319b53f';
const OTHER = '60149570-c71b-11f1-bd37-cfbc4319b53f';

const readBlob = (blob) =>
	new Promise((resolve) => {
		const reader = new FileReader();
		reader.onload = () => resolve(reader.result);
		reader.readAsText(blob);
	});

beforeAll(() => {
	loadScript('nm-track.js');
	track = window.NotificationManager.track;
});

beforeEach(() => {
	track.reset();
	navigator.sendBeacon = vi.fn(() => true);
	Object.defineProperty(navigator, 'webdriver', { value: false, configurable: true });
});

afterEach(() => {
	vi.unstubAllGlobals();
	delete navigator.sendBeacon;
});

describe('track', () => {
	it('records nothing until it has been given an endpoint', () => {
		track.event(ID, 'i');
		track.flush();

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});

	it('ignores a config without a url or language', () => {
		track.init({});
		track.init(null);
		track.init({ url: '/x' });
		track.event(ID, 'i');
		track.flush();

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});

	it('sends queued events in one beacon', async () => {
		track.init(config);
		track.event(ID, 'i');
		track.event(OTHER, 'i');
		track.event(ID, 'c');
		track.flush();

		expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
		const [url, blob] = navigator.sendBeacon.mock.calls[0];
		expect(url).toBe(config.url);
		expect(JSON.parse(await readBlob(blob))).toEqual({
			lang: 'en',
			events: [
				[ID, 'i'],
				[OTHER, 'i'],
				[ID, 'c']
			]
		});
	});

	it('sends plain text so the browser makes no preflight request', () => {
		track.init(config);
		track.event(ID, 'i');
		track.flush();

		expect(navigator.sendBeacon.mock.calls[0][1].type).toBe('text/plain');
	});

	it('reports each event once per notification per page view', () => {
		track.init(config);
		track.event(ID, 'i');
		track.event(ID, 'i');
		track.event(ID, 'c');
		track.event(ID, 'c');
		track.flush();

		return readBlob(navigator.sendBeacon.mock.calls[0][1]).then((text) => {
			expect(JSON.parse(text).events).toEqual([
				[ID, 'i'],
				[ID, 'c']
			]);
		});
	});

	it('sends nothing when nothing happened', () => {
		track.init(config);
		track.flush();

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});

	it('does not send the same events twice', () => {
		track.init(config);
		track.event(ID, 'i');
		track.flush();
		track.flush();

		expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
	});

	it('flushes when the page is hidden', () => {
		track.init(config);
		track.event(ID, 'i');
		Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
		document.dispatchEvent(new Event('visibilitychange'));
		Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });

		expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
	});

	it('does not flush on visibilitychange while the page is visible', () => {
		track.init(config);
		track.event(ID, 'i');
		document.dispatchEvent(new Event('visibilitychange'));

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});

	it('flushes when the page is left', () => {
		track.init(config);
		track.event(ID, 'c');
		window.dispatchEvent(new Event('pagehide'));

		expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
	});

	it('flushes early once ten events are waiting', () => {
		track.init(config);
		for (let i = 0; i < 10; i++) {
			track.event(`00000000-0000-0000-0000-${String(i).padStart(12, '0')}`, 'i');
		}

		expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);
	});

	it('falls back to fetch when sendBeacon is missing', () => {
		delete navigator.sendBeacon;
		const fetchMock = vi.fn(() => Promise.resolve({}));
		vi.stubGlobal('fetch', fetchMock);

		track.init(config);
		track.event(ID, 'i');
		track.flush();

		expect(fetchMock).toHaveBeenCalledTimes(1);
		const [url, options] = fetchMock.mock.calls[0];
		expect(url).toBe(config.url);
		expect(options.method).toBe('POST');
		expect(options.keepalive).toBe(true);
		expect(options.credentials).toBe('same-origin');
	});

	it('falls back to fetch when the browser refuses the beacon', () => {
		navigator.sendBeacon = vi.fn(() => false);
		const fetchMock = vi.fn(() => Promise.resolve({}));
		vi.stubGlobal('fetch', fetchMock);

		track.init(config);
		track.event(ID, 'i');
		track.flush();

		expect(fetchMock).toHaveBeenCalledTimes(1);
	});

	it('never throws, whatever the network does', () => {
		navigator.sendBeacon = vi.fn(() => {
			throw new Error('blocked');
		});
		vi.stubGlobal('fetch', () => {
			throw new Error('blocked');
		});

		track.init(config);
		track.event(ID, 'i');

		expect(() => track.flush()).not.toThrow();
	});

	it('does not count automated browsers', () => {
		Object.defineProperty(navigator, 'webdriver', { value: true, configurable: true });
		track.init(config);
		track.event(ID, 'i');
		track.flush();

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});

	it('keeps the first configuration it was given', () => {
		track.init(config);
		track.init({ url: '/elsewhere', lang: 'fr' });
		track.event(ID, 'i');
		track.flush();

		expect(navigator.sendBeacon.mock.calls[0][0]).toBe(config.url);
	});

	it('ignores events without an id', () => {
		track.init(config);
		track.event('', 'i');
		track.event(undefined, 'i');
		track.flush();

		expect(navigator.sendBeacon).not.toHaveBeenCalled();
	});
});
