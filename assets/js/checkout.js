/* global jQuery, BalikovnaWC */
(function ($) {
	'use strict';

	var modal = null;
	var selections = indexSelections(BalikovnaWC.selected || []);

	function indexSelections(items) {
		var indexed = {};
		if (!Array.isArray(items)) return indexed;
		items.forEach(function (selection) {
			if (selection && selection.packageKey !== undefined && selection.rateId) {
				indexed[String(selection.packageKey)] = selection;
			}
		});
		return indexed;
	}

	function chosenServices() {
		var serviceIds = Object.keys(BalikovnaWC.services || {});
		var chosen = [];
		$('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]').each(function (fallbackIndex) {
			var $input = $(this);
			var rateId = String($input.val() || '');
			var methodId = rateId.split(':')[0];
			if (serviceIds.indexOf(methodId) === -1) return;
			var match = String($input.attr('name') || '').match(/^shipping_method\[([^\]]+)\]/);
			var packageKey = match ? match[1] : String(fallbackIndex);
			if (chosen.some(function (item) { return item.packageKey === packageKey; })) return;
			chosen.push({
				packageKey: packageKey,
				rateId: rateId,
				serviceId: methodId,
				service: BalikovnaWC.services[methodId]
			});
		});
		return chosen;
	}

	function appendPoint($target, point) {
		$target.empty();
		if (!point || !point.id) return;
		$target.append($('<strong>').text(BalikovnaWC.i18n.selected));
		$target.append(document.createTextNode(point.name || ''));
		if (point.street) $target.append($('<br>'), document.createTextNode(point.street));
		if (point.zip || point.city) {
			$target.append($('<br>'), document.createTextNode(((point.zip || '') + ' ' + (point.city || '')).trim()));
		}
	}

	function renderPickers() {
		var $row = $('.balikovna-row');
		var $container = $row.find('#balikovna-pickers');
		if (!$row.length || !$container.length) return;
		var chosen = chosenServices();
		$container.empty();
		$row.toggle(chosen.length > 0);

		chosen.forEach(function (context) {
			var selection = selections[context.packageKey];
			var point = selection && selection.rateId === context.rateId ? selection.point : null;
			var $wrap = $('<div>', { 'class': 'balikovna-picker', 'data-package-key': context.packageKey });
			var $label = $('<div>', { 'class': 'balikovna-picker__label' }).text(context.service.label || context.serviceId);
			var $button = $('<button>', { type: 'button', 'class': 'button balikovna-open' })
				.text(point ? BalikovnaWC.i18n.change : BalikovnaWC.i18n.choose);
			var $selected = $('<div>', { 'class': 'balikovna-selected', 'aria-live': 'polite' });
			var $error = $('<div>', { 'class': 'balikovna-error', role: 'alert', hidden: true });
			appendPoint($selected, point);
			$button.on('click.balikovna', function () {
				openModal(context, $button[0], $error);
			});
			$wrap.append($label, $button, $selected, $error);
			$container.append($wrap);
		});
	}

	function openModal(context, trigger, $rowError) {
		if (!context.service || !context.service.widgetUrl) return;
		if (modal && modal.saving) return;
		closeModal(false);
		var expectedOrigin;
		try { expectedOrigin = new URL(context.service.widgetUrl, window.location.href).origin; } catch (error) { return; }

		var titleId = 'balikovna-dialog-title';
		var $wrap = $('<div>', {
			'class': 'balikovna-modal',
			role: 'dialog',
			'aria-modal': 'true',
			'aria-labelledby': titleId
		});
		var $inner = $('<div>', { 'class': 'balikovna-modal__inner' });
		var $head = $('<div>', { 'class': 'balikovna-modal__head' });
		var $title = $('<h3>', { id: titleId }).text(BalikovnaWC.i18n.title);
		var $close = $('<button>', {
			type: 'button',
			'class': 'balikovna-modal__close',
			'aria-label': BalikovnaWC.i18n.close
		}).html('&times;');
		var $message = $('<div>', { 'class': 'balikovna-modal__message', role: 'alert', hidden: true });
		var $startSentinel = $('<span>', { 'class': 'balikovna-modal__sentinel', tabindex: '0' });
		var $endSentinel = $('<span>', { 'class': 'balikovna-modal__sentinel', tabindex: '0' });
		var $iframe = $('<iframe>', {
			src: context.service.widgetUrl,
			title: BalikovnaWC.i18n.title,
			allow: 'geolocation'
		});
		$head.append($title, $close);
		$inner.append($head, $message, $('<div>', { 'class': 'balikovna-modal__body' }).append($iframe));
		$wrap.append($startSentinel, $inner, $endSentinel);
		$('body').append($wrap).addClass('balikovna-modal-open');

		modal = {
			$wrap: $wrap,
			$iframe: $iframe,
			$close: $close,
			$message: $message,
			context: context,
			trigger: trigger,
			$rowError: $rowError,
			expectedOrigin: expectedOrigin,
			inertElements: setPageInert($wrap[0]),
			saving: false
		};

		$wrap.on('click.balikovna', function (event) {
			if (event.target === $wrap[0]) closeModal(true);
		});
		$close.on('click.balikovna', function () { closeModal(true); });
		$startSentinel.on('focus.balikovna', function () { $iframe.trigger('focus'); });
		$endSentinel.on('focus.balikovna', function () {
			(modal && modal.saving ? $iframe : $close).trigger('focus');
		});
		$(document).on('keydown.balikovnaModal', handleModalKeydown);
		$close.trigger('focus');
	}

	function setPageInert(modalElement) {
		return Array.prototype.map.call(document.body.children, function (element) {
			if (element === modalElement) return null;
			var state = { element: element, hadInert: element.hasAttribute('inert') };
			element.setAttribute('inert', '');
			return state;
		}).filter(Boolean);
	}

	function restorePageInert(elements) {
		(elements || []).forEach(function (state) {
			if (!state.hadInert) state.element.removeAttribute('inert');
		});
	}

	function handleModalKeydown(event) {
		if (!modal) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal(true);
			return;
		}
		if (event.key !== 'Tab') return;
		var focusable = modal.$wrap.find('button:not(:disabled), iframe').toArray();
		if (!focusable.length) return;
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function closeModal(restoreFocus, force) {
		if (!modal) return;
		if (modal.saving && !force) return;
		var closing = modal;
		var trigger = closing.trigger;
		closing.$wrap.remove();
		modal = null;
		restorePageInert(closing.inertElements);
		$('body').removeClass('balikovna-modal-open');
		$(document).off('keydown.balikovnaModal');
		if (restoreFocus && trigger && document.contains(trigger)) trigger.focus();
	}

	function normalizePoint(data) {
		if (!data) return null;
		if (typeof data === 'string') {
			try { data = JSON.parse(data); } catch (error) { return null; }
		}
		if (typeof data !== 'object' || ['pickerResult', 'pickResult'].indexOf(data.message) === -1 || !data.point) return null;
		var point = data.point;
		if (!point.id || !point.name) return null;
		var city = String(point.municipality_name || point.city || '').trim();
		if (point.municipality_district_name && point.municipality_district_name !== city) {
			city += ' - ' + point.municipality_district_name;
		}
		return {
			id: String(point.id),
			zip: String(point.zip || ''),
			name: String(point.name),
			street: String(point.street || point.address || '').trim(),
			city: city.trim(),
			country: String(point.country || 'CZ'),
			type: String(point.type || ''),
			subtype: String(point.subtype || ''),
			lat: point.coor_y_wgs84 ? String(point.coor_y_wgs84) : '',
			lng: point.coor_x_wgs84 ? String(point.coor_x_wgs84) : '',
			phone: String(data.phone || '').trim()
		};
	}

	function responseMessage(response, fallback) {
		return response && response.data && response.data.message ? response.data.message : fallback;
	}

	function saveSelection(point) {
		if (!modal || modal.saving) return;
		var active = modal;
		active.saving = true;
		active.$close.prop('disabled', true);
		active.$message.prop('hidden', false).removeClass('balikovna-error').text(BalikovnaWC.i18n.saving);
		$.ajax({
			url: BalikovnaWC.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				nonce: BalikovnaWC.nonce,
				package_key: active.context.packageKey,
				rate_id: active.context.rateId,
				point: point
			}
		}).done(function (response) {
			if (!response || !response.success) {
				if (modal === active) {
					active.saving = false;
					active.$close.prop('disabled', false);
					active.$message.addClass('balikovna-error').text(responseMessage(response, BalikovnaWC.i18n.saveError));
				}
				return;
			}
			selections[active.context.packageKey] = response.data;
			active.$rowError.prop('hidden', true).empty();
			if (modal === active) closeModal(false, true);
			renderPickers();
			var nextTrigger = $('.balikovna-picker[data-package-key="' + active.context.packageKey + '"] .balikovna-open')[0];
			if (nextTrigger) nextTrigger.focus();
			$(document.body).trigger('update_checkout');
		}).fail(function (xhr) {
			var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			var message = responseMessage(response, BalikovnaWC.i18n.saveError);
			if (modal === active) {
				active.saving = false;
				active.$close.prop('disabled', false);
				active.$message.addClass('balikovna-error').text(message);
			} else if (active.$rowError[0] && document.contains(active.$rowError[0])) {
				active.$rowError.prop('hidden', false).text(message);
			}
		});
	}

	window.addEventListener('message', function (event) {
		if (BalikovnaWC.debug) {
			try { console.log('[Balíkovna] postMessage origin=%s data=%o', event.origin, event.data); } catch (error) {}
		}
		if (!modal || !modal.$iframe[0].contentWindow) return;
		if (event.origin !== modal.expectedOrigin || event.source !== modal.$iframe[0].contentWindow) return;
		var point = normalizePoint(event.data);
		if (point) saveSelection(point);
	}, false);

	$(function () {
		if (BalikovnaWC.debug) console.log('[Balíkovna] debug mode ON. Services:', BalikovnaWC.services);
		renderPickers();
		$(document.body)
			.off('updated_checkout.balikovna updated_shipping_method.balikovna')
			.on('updated_checkout.balikovna updated_shipping_method.balikovna', renderPickers);
	});
})(jQuery);
