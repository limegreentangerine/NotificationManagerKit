import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

let storage;

beforeAll(() => {
	loadScript('nm-storage.js');
	storage = window.NotificationManager.storage;
});

beforeEach(() => {
	localStorage.clear();
	sessionStorage.clear();
});

describe('banner dismissal', () => {
	it('stays dismissed until the version changes', () => {
		expect(storage.isDismissed('a', 1)).toBe(false);
		storage.dismiss('a', 1);
		expect(storage.isDismissed('a', 1)).toBe(true);
		expect(storage.isDismissed('a', 2)).toBe(false);
	});
});

describe('popup frequency', () => {
	it('every_time is never suppressed', () => {
		storage.markClosed('p', 'every_time', 1);
		expect(storage.isSuppressed('p', 'every_time', 1)).toBe(false);
	});

	it('every_time_until_close lasts for the session only', () => {
		storage.markClosed('p', 'every_time_until_close', 1);
		expect(storage.isSuppressed('p', 'every_time_until_close', 1)).toBe(true);
		expect(storage.isSuppressed('p', 'every_time_until_close', 2)).toBe(false);
		expect(localStorage.length).toBe(0);
	});

	it('once is suppressed for good, but an edit (new version) shows it again', () => {
		storage.markClosed('p', 'once', 1);
		expect(storage.isSuppressed('p', 'once', 1)).toBe(true);
		expect(storage.isSuppressed('p', 'once', 2)).toBe(false);
	});

	it('daily and weekly expire', () => {
		vi.useFakeTimers();
		vi.setSystemTime(new Date('2026-03-01T10:00:00Z'));
		storage.markClosed('p', 'daily', 1);
		storage.markClosed('w', 'weekly', 1);

		vi.setSystemTime(new Date('2026-03-01T22:00:00Z'));
		expect(storage.isSuppressed('p', 'daily', 1)).toBe(true);

		vi.setSystemTime(new Date('2026-03-02T11:00:00Z'));
		expect(storage.isSuppressed('p', 'daily', 1)).toBe(false);
		expect(storage.isSuppressed('w', 'weekly', 1)).toBe(true);

		vi.setSystemTime(new Date('2026-03-09T11:00:00Z'));
		expect(storage.isSuppressed('w', 'weekly', 1)).toBe(false);
		vi.useRealTimers();
	});
});

describe('blocked storage', () => {
	it('never throws when storage is unavailable', () => {
		const blocked = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
			throw new DOMException('blocked', 'SecurityError');
		});
		const blockedSet = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
			throw new DOMException('quota', 'QuotaExceededError');
		});

		expect(() => storage.dismiss('a', 1)).not.toThrow();
		expect(storage.isDismissed('a', 1)).toBe(false);
		expect(() => storage.markClosed('p', 'once', 1)).not.toThrow();
		expect(storage.isSuppressed('p', 'once', 1)).toBe(false);

		blocked.mockRestore();
		blockedSet.mockRestore();
	});
});
