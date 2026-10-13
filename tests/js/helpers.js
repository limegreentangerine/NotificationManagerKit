import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** The package scripts are plain browser scripts, so run them in the jsdom window rather than import them. */
export function loadScript(name) {
	const source = readFileSync(resolve(__dirname, '../../js', name), 'utf8');
	(0, eval)(source);
}

/** jsdom has no <dialog> behaviour; give it the parts the popup relies on. */
export function polyfillDialog() {
	const proto = window.HTMLDialogElement.prototype;
	proto.showModal = function () {
		this.setAttribute('open', '');
	};
	proto.close = function () {
		if (!this.hasAttribute('open')) return;
		this.removeAttribute('open');
		this.dispatchEvent(new Event('close'));
	};
}
