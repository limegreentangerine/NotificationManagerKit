/**
 * Decides whether a visitor is in a notification's audience. Runs in the browser so pages stay cacheable:
 * the server sends the same markup to everyone, and each banner/popup asks this before it shows itself.
 *
 * Rules (all that are set must match), as written by Support\Audience::toArray():
 *   visitor   'new' | 'returning'
 *   devices   any of 'mobile' | 'tablet' | 'desktop'
 *   param     { key, value }   a query string parameter, optionally with a value
 *   referrer  a host, matched as that host or any subdomain
 *
 * Nothing is stored unless a rule needs it, and storage that is blocked never breaks a page: the visitor
 * is then treated as new, and parameter/referrer rules can only see the current page.
 */
window.NotificationManager = window.NotificationManager || {};

window.NotificationManager.audience = {
	/** Viewport widths (px) at which a window stops being mobile, then tablet. */
	breakpoints: { tablet: 768, desktop: 1024 },

	visitorStatus: null,

	/** Forget what was worked out for this page view. Only needed by tests. */
	reset() {
		this.visitorStatus = null;
	},

	get storage() {
		return window.NotificationManager.storage;
	},

	/**
	 * 'new' for the whole of a visitor's first session, 'returning' once they come back in a later one.
	 * Worked out once per page view, so every notification on the page gets the same answer.
	 */
	visitor() {
		if (this.visitorStatus) return this.visitorStatus;

		const sessionKey = 'nm_visitor_session';
		const seenKey = 'nm_visitor_seen';
		let status = this.storage.read('sessionStorage', sessionKey);

		if (status !== 'new' && status !== 'returning') {
			status = this.storage.read('localStorage', seenKey) ? 'returning' : 'new';
			this.storage.write('sessionStorage', sessionKey, status);
			this.storage.write('localStorage', seenKey, String(Date.now()));
		}

		this.visitorStatus = status;
		return status;
	},

	device() {
		const width = window.innerWidth;
		if (width < this.breakpoints.tablet) return 'mobile';
		return width < this.breakpoints.desktop ? 'tablet' : 'desktop';
	},

	/**
	 * The parameter on this page, else the one remembered from earlier in the session (a campaign link lands on
	 * one page, but the visitor keeps browsing). Only parameters a rule asked about are ever stored.
	 */
	param(key) {
		const storeKey = `nm_param_${key}`;
		const current = new URLSearchParams(window.location.search).get(key);

		if (current !== null) {
			this.storage.write('sessionStorage', storeKey, current);
			return current;
		}

		return this.storage.read('sessionStorage', storeKey);
	},

	/** The host the visitor arrived from, remembered for the session; links within this site don't count. */
	referrerHost() {
		const storeKey = 'nm_referrer';
		const stored = this.storage.read('sessionStorage', storeKey);
		if (stored) return stored;

		let host = '';
		try {
			host = new URL(document.referrer).hostname.toLowerCase().replace(/^www\./, '');
		} catch {
			return '';
		}

		if (!host || host === window.location.hostname.toLowerCase().replace(/^www\./, ''))
			return '';

		this.storage.write('sessionStorage', storeKey, host);
		return host;
	},

	matches(rules) {
		if (rules === null || rules === undefined) return true;
		if (typeof rules !== 'object' || Array.isArray(rules)) return false;

		try {
			if (rules.visitor && this.visitor() !== rules.visitor) return false;

			if (Array.isArray(rules.devices) && rules.devices.length > 0) {
				if (!rules.devices.includes(this.device())) return false;
			}

			if (rules.param && rules.param.key) {
				const value = this.param(rules.param.key);
				if (value === null) return false;
				if (rules.param.value && value.toLowerCase() !== rules.param.value.toLowerCase()) {
					return false;
				}
			}

			if (rules.referrer) {
				const host = this.referrerHost();
				const wanted = String(rules.referrer).toLowerCase();
				if (!host || (host !== wanted && !host.endsWith(`.${wanted}`))) return false;
			}

			return true;
		} catch {
			// Not being able to tell is not a reason to show something to the wrong people.
			return false;
		}
	},

	/** For the data-audience attribute on banners; anything unreadable is treated as "not for this visitor". */
	matchesJson(raw) {
		try {
			const rules = JSON.parse(raw);
			return rules !== null && typeof rules === 'object' && this.matches(rules);
		} catch {
			return false;
		}
	}
};
