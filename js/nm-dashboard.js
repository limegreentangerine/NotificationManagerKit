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

		form.querySelectorAll('[data-nm-link]').forEach(initLink);
	}

	/** Show only the link field that belongs to the chosen link type (and the link text unless there is no link). */
	function initLink(group) {
		const select = group.querySelector('[data-nm-link-type]');
		if (!select) return;

		const update = () => {
			group.querySelectorAll('[data-nm-link-field]').forEach((field) => {
				const kind = field.dataset.nmLinkField;
				const show = kind === 'text' ? select.value !== 'none' : kind === select.value;

				field.classList.toggle('d-none', !show);
				// A hidden typed field must not post: the three typed link fields share one name.
				field.querySelectorAll('input, select, textarea').forEach((input) => {
					if (input.type !== 'hidden') input.disabled = !show;
				});
			});
		};

		select.addEventListener('change', update);
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
