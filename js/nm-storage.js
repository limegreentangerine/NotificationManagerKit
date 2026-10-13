/**
 * Remembers what a visitor has dismissed. Storage can be blocked or full (private modes, cookie settings),
 * and that must never break a notification, so every access is wrapped.
 */
window.NotificationManager = window.NotificationManager || {};

window.NotificationManager.storage = {
	key(id) {
		return `nm_closed_${id}`;
	},

	read(store, key) {
		try {
			return window[store].getItem(key);
		} catch {
			return null;
		}
	},

	write(store, key, value) {
		try {
			window[store].setItem(key, value);
		} catch {
			// Not remembering is acceptable; breaking the page is not.
		}
	},

	/** Banners: dismissed until the banner is edited (the version changes). */
	isDismissed(id, version) {
		return this.read('localStorage', this.key(id)) === String(version);
	},

	dismiss(id, version) {
		this.write('localStorage', this.key(id), String(version));
	},

	/** Popups: whether the visitor's frequency rule currently suppresses it. */
	isSuppressed(id, frequency, version) {
		if (frequency === 'every_time') return false;

		if (frequency === 'every_time_until_close') {
			return this.read('sessionStorage', this.key(id)) === String(version);
		}

		const raw = this.read('localStorage', this.key(id));
		if (!raw) return false;

		const [closedVersion, closedAt] = raw.split(':');
		if (closedVersion !== String(version)) return false;
		if (frequency === 'once') return true;

		const windowMs = { daily: 864e5, weekly: 7 * 864e5 }[frequency];
		return windowMs ? Date.now() - Number(closedAt) < windowMs : false;
	},

	markClosed(id, frequency, version) {
		if (frequency === 'every_time') return;

		if (frequency === 'every_time_until_close') {
			this.write('sessionStorage', this.key(id), String(version));
			return;
		}

		this.write('localStorage', this.key(id), `${version}:${Date.now()}`);
	}
};
