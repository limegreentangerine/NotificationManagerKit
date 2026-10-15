/**
 * <nm-banner>: one or more banners rendered by the server in the page flow.
 * The script only adds behaviour (dismiss, rotation); the markup is already correct and already takes up
 * its space, so nothing shifts when this loads.
 */
class NMBanner extends HTMLElement {
	connectedCallback() {
		this.storage = window.NotificationManager.storage;
		this.items = [...this.querySelectorAll('.nm-banner__item')];

		// Targeted banners arrive hidden and are only revealed for the visitors they are for.
		this.items.forEach((item) => {
			if (item.dataset.audience === undefined) return;

			const audience = window.NotificationManager.audience;
			if (audience && audience.matchesJson(item.dataset.audience)) {
				item.hidden = false;
			} else {
				item.remove();
			}
		});
		this.items = this.items.filter((item) => item.isConnected);

		this.items.forEach((item) => {
			if (this.storage.isDismissed(item.dataset.id, item.dataset.version)) {
				item.remove();
			}
		});
		this.items = this.items.filter((item) => item.isConnected);

		if (this.items.length === 0) {
			this.remove();
			return;
		}

		this.initTrack();
		this.addEventListener('click', this.onClick);
		this.sync();
	}

	/** The server renders the statistics endpoint into the markup; without it nothing is reported. */
	initTrack() {
		const track = window.NotificationManager.track;
		if (!track || !this.dataset.track) return;

		try {
			track.init(JSON.parse(this.dataset.track));
		} catch {
			// A malformed config means no statistics, never a broken banner.
		}
	}

	report(id, type) {
		window.NotificationManager.track?.event(id, type);
	}

	/**
	 * Banners on screen right now count as shown: the active one while several take turns, otherwise all of them
	 * (a ticker, reduced motion, or just one). The tracker ignores a banner it has already counted.
	 */
	trackShown() {
		const taking =
			this.hasAttribute('data-rotating') &&
			!this.hasAttribute('data-static') &&
			this.dataset.transition !== 'ticker';
		const shown = taking
			? this.items.filter((item) => item.classList.contains('is-active'))
			: this.items;

		shown.forEach((item) => this.report(item.dataset.id, 'i'));
	}

	disconnectedCallback() {
		this.removeEventListener('click', this.onClick);
		this.stop();
	}

	onClick = (event) => {
		const link = event.target.closest('.nm-banner__link');
		if (link && this.contains(link)) {
			this.report(link.closest('.nm-banner__item')?.dataset.id, 'c');
			return;
		}

		const button = event.target.closest('[data-nm-close]');
		if (!button || !this.contains(button)) return;

		const item = button.closest('.nm-banner__item');
		this.report(item.dataset.id, 'd');
		this.storage.dismiss(item.dataset.id, item.dataset.version);
		item.remove();
		this.items = this.items.filter((i) => i !== item);

		if (this.items.length === 0) {
			this.remove();
			return;
		}

		this.sync();
	};

	/** Re-apply rotation after the set of banners changes, then count whatever is now on screen. */
	sync() {
		this.layout();
		this.trackShown();
	}

	layout() {
		this.stop();

		const rotating = this.items.length > 1;
		this.toggleAttribute('data-rotating', rotating);

		if (!this.items.some((i) => i.classList.contains('is-active'))) {
			this.items[0].classList.add('is-active');
		}

		const reduceMotion =
			window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
		const transition = this.dataset.transition;

		// The ticker is pure CSS; reduced motion shows every banner statically instead of cycling.
		if (!rotating || transition === 'ticker' || reduceMotion) {
			this.toggleAttribute('data-static', reduceMotion && rotating);
			return;
		}

		this.removeAttribute('data-static');
		const interval = Math.max(2000, Number(this.dataset.interval) || 6000);
		this.timer = setInterval(() => this.advance(), interval);

		// No point animating a tab nobody is looking at.
		this.onVisibility = () => {
			if (document.hidden) {
				clearInterval(this.timer);
			} else {
				this.timer = setInterval(() => this.advance(), interval);
			}
		};
		document.addEventListener('visibilitychange', this.onVisibility);
	}

	advance() {
		const current = this.items.findIndex((i) => i.classList.contains('is-active'));
		const next = (current + 1) % this.items.length;

		this.items[current]?.classList.remove('is-active');
		this.items[next].classList.add('is-active');
		this.style.setProperty('--nm-index', String(next));
		this.trackShown();
	}

	stop() {
		clearInterval(this.timer);
		if (this.onVisibility) {
			document.removeEventListener('visibilitychange', this.onVisibility);
			this.onVisibility = null;
		}
	}
}

if (!customElements.get('nm-banner')) {
	customElements.define('nm-banner', NMBanner);
}
