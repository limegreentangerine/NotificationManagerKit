import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { loadScript } from './helpers.js';

let audience;

const visit = ({ url = '/', referrer = '', width = 1280 } = {}) => {
	window.history.replaceState({}, '', url);
	Object.defineProperty(document, 'referrer', { value: referrer, configurable: true });
	window.innerWidth = width;
	audience.reset();
};

beforeAll(() => {
	loadScript('nm-storage.js');
	loadScript('nm-audience.js');
	audience = window.NotificationManager.audience;
});

beforeEach(() => {
	localStorage.clear();
	sessionStorage.clear();
	visit();
});

describe('audience: no rules', () => {
	it('matches everyone', () => {
		expect(audience.matches(null)).toBe(true);
		expect(audience.matches(undefined)).toBe(true);
		expect(audience.matches({})).toBe(true);
	});
});

describe('audience: new vs returning', () => {
	it('treats a first visit as new', () => {
		expect(audience.matches({ visitor: 'new' })).toBe(true);
		expect(audience.matches({ visitor: 'returning' })).toBe(false);
	});

	it('treats a visitor with an earlier visit as returning', () => {
		audience.matches({ visitor: 'new' });
		sessionStorage.clear(); // a later session
		visit();

		expect(audience.matches({ visitor: 'returning' })).toBe(true);
		expect(audience.matches({ visitor: 'new' })).toBe(false);
	});

	it('keeps a new visitor new for the rest of their first session', () => {
		audience.matches({ visitor: 'new' });
		visit({ url: '/second-page' });

		expect(audience.matches({ visitor: 'new' })).toBe(true);
		expect(audience.matches({ visitor: 'returning' })).toBe(false);
	});

	it('gives every notification on one page the same answer', () => {
		expect(audience.matches({ visitor: 'new' })).toBe(true);
		expect(audience.matches({ visitor: 'new' })).toBe(true);
		expect(audience.matches({ visitor: 'returning' })).toBe(false);
	});

	it('does not store anything unless a rule asks about visitors', () => {
		audience.matches({ devices: ['desktop'] });

		expect(localStorage.length).toBe(0);
		expect(sessionStorage.length).toBe(0);
	});
});

describe('audience: device', () => {
	it.each([
		[400, 'mobile'],
		[767, 'mobile'],
		[768, 'tablet'],
		[1023, 'tablet'],
		[1024, 'desktop'],
		[1920, 'desktop']
	])('a %ipx wide window is %s', (width, device) => {
		visit({ width });

		for (const d of ['mobile', 'tablet', 'desktop']) {
			expect(audience.matches({ devices: [d] })).toBe(d === device);
		}
	});

	it('matches when the device is any of those listed', () => {
		visit({ width: 500 });
		expect(audience.matches({ devices: ['mobile', 'tablet'] })).toBe(true);
		expect(audience.matches({ devices: ['desktop', 'tablet'] })).toBe(false);
	});
});

describe('audience: URL parameter', () => {
	it('matches on the key being present', () => {
		visit({ url: '/?utm_campaign=anything' });
		expect(audience.matches({ param: { key: 'utm_campaign', value: '' } })).toBe(true);
		expect(audience.matches({ param: { key: 'other', value: '' } })).toBe(false);
	});

	it('matches the value case-insensitively when one is set', () => {
		visit({ url: '/?utm_campaign=Spring' });
		expect(audience.matches({ param: { key: 'utm_campaign', value: 'spring' } })).toBe(true);
		expect(audience.matches({ param: { key: 'utm_campaign', value: 'summer' } })).toBe(false);
	});

	it('does not match without the parameter', () => {
		expect(audience.matches({ param: { key: 'utm_campaign', value: '' } })).toBe(false);
	});

	it('remembers the parameter for the rest of the session', () => {
		const rule = { param: { key: 'utm_campaign', value: 'spring' } };
		visit({ url: '/landing?utm_campaign=spring' });
		expect(audience.matches(rule)).toBe(true);

		visit({ url: '/pricing' });
		expect(audience.matches(rule)).toBe(true);
	});

	it('lets a newer value replace the remembered one', () => {
		const rule = { param: { key: 'utm_campaign', value: 'spring' } };
		visit({ url: '/?utm_campaign=spring' });
		audience.matches(rule);

		visit({ url: '/?utm_campaign=summer' });
		expect(audience.matches(rule)).toBe(false);
	});

	it('only remembers parameters that a rule asked about', () => {
		visit({ url: '/?secret=1&utm_campaign=spring' });
		audience.matches({ param: { key: 'utm_campaign', value: '' } });

		const stored = JSON.stringify({ ...sessionStorage });
		expect(stored).not.toContain('secret');
	});
});

describe('audience: referrer', () => {
	it('matches the referring host', () => {
		visit({ referrer: 'https://google.com/search?q=x' });
		expect(audience.matches({ referrer: 'google.com' })).toBe(true);
		expect(audience.matches({ referrer: 'bing.com' })).toBe(false);
	});

	it('matches subdomains and www', () => {
		visit({ referrer: 'https://www.google.com/' });
		expect(audience.matches({ referrer: 'google.com' })).toBe(true);

		visit({ referrer: 'https://news.ycombinator.com/' });
		sessionStorage.clear();
		visit({ referrer: 'https://news.ycombinator.com/' });
		expect(audience.matches({ referrer: 'ycombinator.com' })).toBe(true);
	});

	it('does not match a host that merely ends with the same text', () => {
		visit({ referrer: 'https://notgoogle.com/' });
		expect(audience.matches({ referrer: 'google.com' })).toBe(false);
	});

	it('does not match when there is no referrer', () => {
		expect(audience.matches({ referrer: 'google.com' })).toBe(false);
	});

	it('ignores navigation from the visitor’s own site', () => {
		visit({ referrer: `${window.location.origin}/other` });
		expect(audience.matches({ referrer: 'localhost' })).toBe(false);
	});

	it('remembers where the visitor arrived from for the session', () => {
		visit({ url: '/landing', referrer: 'https://google.com/' });
		audience.matches({ referrer: 'google.com' });

		visit({ url: '/pricing', referrer: `${window.location.origin}/landing` });
		expect(audience.matches({ referrer: 'google.com' })).toBe(true);
	});

	it('ignores a malformed referrer', () => {
		visit({ referrer: 'not a url' });
		expect(audience.matches({ referrer: 'google.com' })).toBe(false);
	});
});

describe('audience: combining conditions', () => {
	it('needs every condition to match', () => {
		visit({ url: '/?utm_campaign=spring', width: 400 });
		const rules = {
			visitor: 'new',
			devices: ['mobile'],
			param: { key: 'utm_campaign', value: 'spring' }
		};

		expect(audience.matches(rules)).toBe(true);
		expect(audience.matches({ ...rules, devices: ['desktop'] })).toBe(false);
		expect(audience.matches({ ...rules, visitor: 'returning' })).toBe(false);
	});
});

describe('audience: storage is blocked', () => {
	it('still answers, treating the visitor as new', () => {
		const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
		const originalSession = Object.getOwnPropertyDescriptor(window, 'sessionStorage');
		const blocked = {
			get() {
				throw new Error('blocked');
			}
		};
		Object.defineProperty(window, 'localStorage', { ...blocked, configurable: true });
		Object.defineProperty(window, 'sessionStorage', { ...blocked, configurable: true });

		try {
			audience.reset();
			expect(audience.matches({ visitor: 'new' })).toBe(true);
			expect(audience.matches({ visitor: 'returning' })).toBe(false);
		} finally {
			Object.defineProperty(window, 'localStorage', original);
			Object.defineProperty(window, 'sessionStorage', originalSession);
		}
	});
});

describe('audience.matchesJson', () => {
	it('parses the attribute value', () => {
		expect(audience.matchesJson('{"visitor":"new"}')).toBe(true);
		expect(audience.matchesJson('{"visitor":"returning"}')).toBe(false);
	});

	it('fails closed on junk', () => {
		expect(audience.matchesJson('{nope')).toBe(false);
		expect(audience.matchesJson('')).toBe(false);
		expect(audience.matchesJson(null)).toBe(false);
		expect(audience.matchesJson('"string"')).toBe(false);
	});
});
