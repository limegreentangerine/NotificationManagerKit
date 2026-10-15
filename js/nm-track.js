/**
 * Reports what a visitor's browser showed, so the dashboard can count it: a notification appeared ('i'), its
 * call-to-action was clicked ('c') or it was dismissed ('d'). Events are queued and sent as one beacon when the
 * page is hidden or left; each event is sent once per notification per page view. Nothing identifies the visitor
 * and nothing is stored in the browser. A failure here must never affect the page, so every send is wrapped.
 */
window.NotificationManager = window.NotificationManager || {};

window.NotificationManager.track = {
	url: '',
	lang: '',
	ready: false,
	listening: false,
	queue: [],
	sent: new Set(),

	/** Start over. Only needed by tests. */
	reset() {
		this.url = '';
		this.lang = '';
		this.ready = false;
		this.queue = [];
		this.sent = new Set();
	},

	/** Called by each banner/popup with the endpoint the server gave it; the first config wins. */
	init(config) {
		if (
			this.ready ||
			!config ||
			typeof config.url !== 'string' ||
			typeof config.lang !== 'string'
		)
			return;
		if (!config.url || !config.lang) return;

		// Automated browsers (tests, monitoring) are not visitors.
		if (navigator.webdriver) return;

		this.url = config.url;
		this.lang = config.lang;
		this.ready = true;

		if (!this.listening) {
			this.listening = true;
			document.addEventListener('visibilitychange', () => {
				if (document.visibilityState === 'hidden') this.flush();
			});
			window.addEventListener('pagehide', () => this.flush());
		}
	},

	/** type: 'i' shown, 'c' clicked, 'd' dismissed. */
	event(id, type) {
		if (!this.ready || !id) return;

		const key = `${id}:${type}`;
		if (this.sent.has(key)) return;
		this.sent.add(key);

		this.queue.push([String(id), type]);
		if (this.queue.length >= 10) this.flush();
	},

	flush() {
		if (!this.ready || this.queue.length === 0) return;

		const body = JSON.stringify({ lang: this.lang, events: this.queue.splice(0) });

		// text/plain keeps this a "simple" request, so the browser sends it without a preflight.
		try {
			if (
				navigator.sendBeacon &&
				navigator.sendBeacon(this.url, new Blob([body], { type: 'text/plain' }))
			) {
				return;
			}
		} catch {
			// Fall through to fetch.
		}

		try {
			fetch(this.url, {
				method: 'POST',
				body,
				keepalive: true,
				credentials: 'same-origin',
				headers: { 'Content-Type': 'text/plain' }
			}).catch(() => {});
		} catch {
			// Not counting is acceptable; breaking the page is not.
		}
	}
};
