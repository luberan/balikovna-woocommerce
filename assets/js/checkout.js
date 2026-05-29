/* global jQuery, BalikovnaWC */
(function ($) {
	'use strict';

	var $row, $btn, $selected, modal, currentService, currentPoint;

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

	// Oficiální payload widgetu ČP (dle podporaobchodu@cpost.cz, 2026-05):
	// { message: 'pickResult', id: 'B15033', point: { id, zip, name, street,
	//   municipality_name, municipality_district_name, country, type, subtype,
	//   coor_x_wgs84, coor_y_wgs84, ... } }
	function normalizePoint(data) {
		if (!data) return null;
		if (typeof data === 'string') {
			try { data = JSON.parse(data); } catch (e) { return null; }
		}
		if (typeof data !== 'object') return null;
		var p = data.point || (data.id && data.name ? data : null);
		if (!p || !p.id || !p.name) return null;
		var city = String(p.municipality_name || p.city || '').trim();
		if (p.municipality_district_name && p.municipality_district_name !== city) {
			city = city + ' - ' + p.municipality_district_name;
		}
		return {
			id: String(p.id),
			zip: String(p.zip || ''),
			name: String(p.name),
			street: String(p.street || p.address || '').trim(),
			city: city.trim(),
			country: String(p.country || 'CZ'),
			type: String(p.type || ''),
			subtype: String(p.subtype || ''),
			lat: p.coor_y_wgs84 ? String(p.coor_y_wgs84) : '',
			lng: p.coor_x_wgs84 ? String(p.coor_x_wgs84) : ''
		};
	}

	function saveSelection(point) {
		$.post(BalikovnaWC.ajaxUrl, {
			nonce: BalikovnaWC.nonce,
			point: point
		}).done(function (resp) {
			if (resp && resp.success) {
				currentPoint = resp.data;
				BalikovnaWC.selected = resp.data;
				renderSelected(resp.data);
				closeModal();
				$(document.body).trigger('update_checkout');
			}
		});
	}

	window.addEventListener('message', function (ev) {
		// V debug módu logujeme VŠECHNY zprávy (i ty bez otevřeného modalu)
		// aby šlo identifikovat formát payloadu od widgetu.
		if (BalikovnaWC.debug) {
			try { console.log('[Balíkovna] postMessage origin=%s data=%o', ev.origin, ev.data); } catch (e) {}
		}
		if (!modal) return;
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
		currentPoint = BalikovnaWC.selected || null;
		toggleRow();

		$btn.on('click', openModal);

		$(document.body).on('updated_checkout updated_shipping_method', function () {
			$row = $('.balikovna-row');
			$btn = $('#balikovna-open');
			$selected = $('#balikovna-selected');
			renderSelected(currentPoint || BalikovnaWC.selected || null);
			toggleRow();
			$btn.off('click').on('click', openModal);
		});
	});
})(jQuery);
