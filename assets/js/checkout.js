/* global jQuery, BalikovnaWC */
(function ($) {
	'use strict';

	var $row, $btn, $selected, modal, currentService;

	function chosenServiceId() {
		var ids = Object.keys(BalikovnaWC.services || {});
		var chosen = $('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]').map(function () {
			return $(this).val();
		}).get();
		for (var i = 0; i < chosen.length; i++) {
			for (var j = 0; j < ids.length; j++) {
				if (typeof chosen[i] === 'string' && chosen[i].indexOf(ids[j]) === 0) {
					return ids[j];
				}
			}
		}
		return null;
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function renderSelected(point) {
		if (!point || !point.id) {
			$selected.empty();
			$btn.text(BalikovnaWC.i18n.choose);
			return;
		}
		var html = '<strong>' + BalikovnaWC.i18n.selected + '</strong>' +
			escapeHtml(point.name || '') +
			(point.street ? '<br>' + escapeHtml(point.street) : '') +
			((point.zip || point.city) ? '<br>' + escapeHtml((point.zip || '') + ' ' + (point.city || '')) : '');
		$selected.html(html);
		$btn.text(BalikovnaWC.i18n.change);
	}

	function toggleRow() {
		currentService = chosenServiceId();
		if (currentService) {
			$row.show();
		} else {
			$row.hide();
		}
	}

	function openModal() {
		var svc = currentService && BalikovnaWC.services[currentService];
		if (!svc) return;
		closeModal();
		var $m = $(
			'<div class="balikovna-modal" role="dialog" aria-modal="true">' +
			'<div class="balikovna-modal__inner">' +
			'<div class="balikovna-modal__head"><h3>' + escapeHtml(BalikovnaWC.i18n.title) + '</h3>' +
			'<button type="button" class="balikovna-modal__close" aria-label="' + escapeHtml(BalikovnaWC.i18n.close) + '">&times;</button></div>' +
			'<div class="balikovna-modal__body"><iframe src="' + svc.widgetUrl + '" allow="geolocation"></iframe></div>' +
			'</div></div>'
		);
		$('body').append($m);
		modal = $m;
		$m.on('click', function (e) {
			if (e.target === $m[0]) closeModal();
		});
		$m.find('.balikovna-modal__close').on('click', closeModal);
	}

	function closeModal() {
		if (modal) {
			modal.remove();
			modal = null;
		}
	}

	// NOTE: Skutečný formát postMessage z widgetu zatím není veřejně dokumentován.
	// Tato funkce zkouší obvyklé varianty; po obdržení specifikace od ČP se zúží.
	function normalizePoint(data) {
		if (!data || typeof data !== 'object') return null;
		var src = data.point || data.location || data.detail || data;
		var id = src.id || src.code || src.zipCode || src.PSC || src.postCode;
		var name = src.name || src.title || src.NAZEV;
		if (!id || !name) return null;
		return {
			id: String(id),
			name: String(name),
			street: String(src.street || src.address || src.ULICE || ''),
			city: String(src.city || src.OBEC || ''),
			zip: String(src.zip || src.psc || src.PSC || ''),
			country: String(src.country || 'CZ')
		};
	}

	function saveSelection(point) {
		$.post(BalikovnaWC.ajaxUrl, {
			nonce: BalikovnaWC.nonce,
			point: point
		}).done(function (resp) {
			if (resp && resp.success) {
				renderSelected(resp.data);
				closeModal();
				$(document.body).trigger('update_checkout');
			}
		});
	}

	window.addEventListener('message', function (ev) {
		if (!modal) return;
		if (BalikovnaWC.debug) {
			try { console.log('[Balíkovna] postMessage origin=%s data=%o', ev.origin, ev.data); } catch (e) {}
		}
		var p = normalizePoint(ev.data);
		if (p) {
			if (BalikovnaWC.debug) console.log('[Balíkovna] normalized point: %o', p);
			saveSelection(p);
		}
	}, false);

	$(function () {
		$row = $('.balikovna-row');
		if (!$row.length) return;
		$btn = $('#balikovna-open');
		$selected = $('#balikovna-selected');

		if (BalikovnaWC.debug) {
			console.log('[Balíkovna] debug mode ON. Services:', BalikovnaWC.services);
		}

		renderSelected(BalikovnaWC.selected || null);
		toggleRow();

		$btn.on('click', openModal);

		$(document.body).on('updated_checkout updated_shipping_method', function () {
			$row = $('.balikovna-row');
			$btn = $('#balikovna-open');
			$selected = $('#balikovna-selected');
			renderSelected(BalikovnaWC.selected || null);
			toggleRow();
			$btn.off('click').on('click', openModal);
		});
	});
})(jQuery);
