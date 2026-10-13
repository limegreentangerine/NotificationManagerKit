/**
 * <nm-popup>: a modal built on the native <dialog> element, which supplies the focus trap, Esc to close,
 * an inert background and focus restoration, and which is out of the accessibility tree while closed.
 * Opens on the first of the configured triggers to fire (load, scroll, element in view, click).
 */
class NMPopup extends HTMLElement {
	connectedCallback() {
		this.dialog = this.querySelector('dialog');
		this.config = this.readConfig();

		if (!this.dialog || !this.config || typeof this.dialog.showModal !== 'function') return;

		this.storage = window.NotificationManager.storage;
		this.fired = false;

		// The frequency rule limits popups that open by themselves; a visitor clicking a trigger asked for it.
		this.suppressed = this.storage.isSuppressed(
			this.config.id,
			this.config.frequency,
			this.config.version
		);

		if (this.suppressed && !this.config.click) {
			this.remove();
			return;
		}

		this.dialog.addEventListener('close', this.onClose);
		this.dialog.addEventListener('click', this.onBackdropClick);
		this.listen();
	}

	disconnectedCallback() {
		this.cleanup();
		this.dialog?.removeEventListener('close', this.onClose);
		this.dialog?.removeEventListener('click', this.onBackdropClick);
	}

	readConfig() {
		try {
			const config = JSON.parse(this.dataset.config);
			return config && typeof config === 'object' ? config : null;
		} catch {
			return null;
		}
	}

	listen() {
		const c = this.config;
		this.cleanups = [];
		this.automatic = [];

		if (c.onLoad && !this.suppressed) {
			const open = () => this.fire();
			const id = setTimeout(open, Math.max(0, c.delay || 0));
			this.automatic.push(() => clearTimeout(id));
		}

		if (c.onScroll && !this.suppressed) {
			let queued = false;
			const check = () => {
				queued = false;
				const max = document.documentElement.scrollHeight - window.innerHeight;
				const scrolled =
					c.scrollUnit === 'px'
						? window.scrollY
						: max > 0
							? (window.scrollY / max) * 100
							: 0;
				if (scrolled >= c.scrollValue) this.fire();
			};
			const onScroll = () => {
				if (queued) return;
				queued = true;
				requestAnimationFrame(check);
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			this.automatic.push(() => window.removeEventListener('scroll', onScroll));
		}

		// Selectors come from the dashboard and are validated on save, but a bad one must still never throw.
		const target = this.query(() => document.querySelector(c.scrollElement), c.scrollElement);
		if (target && !this.suppressed && 'IntersectionObserver' in window) {
			const observer = new IntersectionObserver(
				(entries) => {
					if (entries.some((entry) => entry.isIntersecting)) this.fire();
				},
				{ threshold: 0.1 }
			);
			observer.observe(target);
			this.automatic.push(() => observer.disconnect());
		}

		if (c.click) {
			const onClick = (event) => {
				const hit = this.query(() => event.target.closest(c.click), c.click);
				if (hit) {
					event.preventDefault();
					this.open();
				}
			};
			document.addEventListener('click', onClick);
			this.cleanups.push(() => document.removeEventListener('click', onClick));
		}
	}

	query(run, selector) {
		if (!selector) return null;
		try {
			return run();
		} catch {
			return null;
		}
	}

	/** An automatic trigger: opens once, then stops watching for the others. */
	fire() {
		if (this.fired) return;
		this.fired = true;
		this.stopAutomatic();
		this.open();
	}

	open() {
		if (!this.dialog.open) this.dialog.showModal();
	}

	/** Stop the automatic triggers; the click trigger stays so the popup can be reopened on request. */
	stopAutomatic() {
		(this.automatic || []).forEach((undo) => undo());
		this.automatic = [];
	}

	cleanup() {
		this.stopAutomatic();
		(this.cleanups || []).forEach((undo) => undo());
		this.cleanups = [];
	}

	onClose = () => {
		this.storage.markClosed(this.config.id, this.config.frequency, this.config.version);
	};

	/** A click on the backdrop lands on the <dialog> itself, not on anything inside it. */
	onBackdropClick = (event) => {
		if (event.target === this.dialog) this.dialog.close();
	};
}

if (!customElements.get('nm-popup')) {
	customElements.define('nm-popup', NMPopup);
}
