/**
 * Dashboard behaviour for the notification form: show the fields that belong to the chosen type and scope,
 * and run the delete confirmation. Strings and URLs arrive as data attributes, so no PHP is interpolated into script.
 */
(function () {
	'use strict';

	function toggle(root, selector, show) {
		root.querySelectorAll(selector).forEach((el) => {
			el.classList.toggle('d-none', !show);
			el.querySelectorAll('input, select, textarea').forEach((input) => {
				if (input.type !== 'hidden') input.disabled = !show;
			});
		});
	}

	function initForm(form) {
		const type = form.elements.type;
		const scope = form.elements.displayScope;

		const update = () => {
			const current = type ? type.value : 'banner';
			toggle(form, '[data-nm-type="banner"]', current === 'banner');
			toggle(form, '[data-nm-type="popup"]', current === 'popup');
			toggle(form, '[data-nm-scope="specific"]', scope && scope.value === 'specific');
		};

		type?.addEventListener('change', update);
		scope?.addEventListener('change', update);
		update();
	}

	function initDelete(button) {
		const dialog = document.querySelector('[data-nm-delete-dialog]');
		if (!dialog) return;

		button.addEventListener('click', (event) => {
			event.preventDefault();
			jQuery.fn.dialog.open({
				element: jQuery(dialog),
				modal: true,
				width: 420,
				title: button.dataset.dialogTitle,
				height: 'auto'
			});
		});

		ConcreteEvent.subscribe('AjaxFormSubmitSuccess', (event, data) => {
			if (data.form === 'delete-form') window.location.href = button.dataset.redirect;
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-nm-form]').forEach(initForm);
		document.querySelectorAll('[data-nm-delete]').forEach(initDelete);
	});
})();
