/**
 * Saito Swatches — front-end behaviour.
 * The hidden <select> remains the source of truth; swatches only drive it.
 */
/* global jQuery, saitoSwatches */
(function ($) {
	'use strict';

	var cfg = window.saitoSwatches || {};

	function selectFor($group) {
		return $group.closest('.saito-swatches-wrap').find('select').first();
	}

	function syncSelected($group) {
		var value = String(selectFor($group).val() || '');
		$group.find('.saito-swatch').each(function () {
			var on = String($(this).data('value')) === value;
			$(this).toggleClass('is-selected', on).attr('aria-checked', on ? 'true' : 'false');
		});
	}

	// WooCommerce removes options that cannot form a valid combination.
	function syncAvailability($group) {
		var $select = selectFor($group);
		var available = {};
		$select.find('option').each(function () {
			if (this.value !== '' && !this.disabled && !$(this).hasClass('disabled')) {
				available[this.value] = true;
			}
		});
		$group.find('.saito-swatch').each(function () {
			var $btn = $(this);
			var ok = available[String($btn.data('value'))] === true;
			$btn.toggleClass('is-unavailable', !ok)
				.prop('disabled', !ok)
				.attr('aria-disabled', ok ? null : 'true')
				.attr('title', !ok && cfg.unavailableText ? cfg.unavailableText : null);
		});
	}

	function syncAll($form) {
		$form.find('.saito-swatches').each(function () {
			syncAvailability($(this));
			syncSelected($(this));
		});
	}

	function formatPrice(amount) {
		var c = cfg.currency || {};
		var decimals = parseInt(c.decimals, 10);
		if (isNaN(decimals)) {
			decimals = 2;
		}
		var parts = amount.toFixed(decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand || '');
		var number = parts.join(c.decimal || '.');
		var format = c.format || '%1$s%2$s';
		return format.replace('%1$s', c.symbol || '').replace('%2$s', number);
	}

	function quantityFor($form, variation) {
		var qty = 0;
		$form.find('.saito-swatch.is-selected[data-quantity]').each(function () {
			var q = parseFloat($(this).attr('data-quantity'));
			if (q > 0) {
				qty = q;
				return false;
			}
		});
		if (qty > 0) {
			return qty;
		}
		var attrs = (variation && variation.attributes) || {};
		for (var key in attrs) {
			if (Object.prototype.hasOwnProperty.call(attrs, key)) {
				var match = String(attrs[key] || '').match(/(\d+(?:[.,]\d+)?)/);
				if (match) {
					return parseFloat(match[1].replace(',', '.'));
				}
			}
		}
		return 0;
	}

	function removeUnitPrice($form) {
		$form.find('.saito-unit-price').remove();
	}

	function renderUnitPrice($form, variation) {
		removeUnitPrice($form);
		if (!variation || !variation.display_price) {
			return;
		}
		var qty = quantityFor($form, variation);
		if (!(qty > 0)) {
			return;
		}
		var reference = parseFloat(cfg.unitReference) || 100;
		var perUnit = (parseFloat(variation.display_price) / qty) * reference;
		var text = String(cfg.unitTemplate || '{price}').replace('{price}', formatPrice(perUnit));
		var $el = $('<span class="saito-unit-price"></span>').text(text);

		var $price = $form.find('.woocommerce-variation-price').first();
		if (cfg.unitPosition === 'in_price' && $price.length) {
			$price.append($el);
		} else {
			$el.insertAfter($form.find('.single_variation_wrap').first());
		}
	}

	function initForm($form) {
		if ($form.data('saitoSwatches')) {
			return;
		}
		if (!$form.find('.saito-swatches').length) {
			return;
		}
		$form.data('saitoSwatches', true);

		if (cfg.hideSelectors) {
			try {
				$(cfg.hideSelectors).hide();
			} catch (e) {
				// Invalid selector typed in the settings: ignore it.
			}
		}

		$form.on('click', '.saito-swatch', function (event) {
			event.preventDefault();
			var $btn = $(this);
			if ($btn.prop('disabled')) {
				return;
			}
			var $group = $btn.closest('.saito-swatches');
			selectFor($group).val(String($btn.data('value'))).trigger('change');
			syncSelected($group);
		});

		// Arrow keys move through a group, like native radio buttons.
		$form.on('keydown', '.saito-swatch', function (event) {
			var step = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[event.key];
			if (!step) {
				return;
			}
			event.preventDefault();
			var $all = $(this).closest('.saito-swatches').find('.saito-swatch:not(:disabled)');
			var next = ($all.index(this) + step + $all.length) % $all.length;
			$all.eq(next).trigger('focus').trigger('click');
		});

		$form.on('woocommerce_update_variation_values reset_data check_variations', function () {
			syncAll($form);
		});

		if (cfg.pricePerUnit) {
			$form.on('show_variation', function (event, variation) {
				renderUnitPrice($form, variation);
			});
			$form.on('hide_variation reset_data', function () {
				removeUnitPrice($form);
			});
		}

		syncAll($form);
	}

	$(function () {
		$('form.variations_form').each(function () {
			initForm($(this));
		});
	});

	// Forms loaded later (quick view, AJAX).
	$(document.body).on('wc_variation_form', 'form.variations_form', function () {
		initForm($(this));
	});

	/* ---------- Saito Navi bridge: images in the sticky add-to-cart swatches ---------- */

	var images = cfg.images || {};

	// Same normalisation as Saito_Swatches_Settings::key(): "100ml", "100 ml", "100-ML" match.
	function toKey(value) {
		return String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[̀-ͯ]/g, '')
			.replace(/[^a-z0-9]+/g, '');
	}

	function paint(el, image) {
		if (!image.url && image.color) {
			el.style.backgroundColor = image.color;
			if (el.classList.contains('navi-sticky-shade-swatch--empty')) {
				el.classList.remove('navi-sticky-shade-swatch--empty');
			}
			return;
		}
		if (!image.url) {
			return;
		}
		var url = 'url("' + String(image.url).replace(/"/g, '%22') + '")';
		if (el.style.backgroundImage !== url) {
			el.style.backgroundImage = url;
		}
		if (el.style.backgroundSize !== 'contain') {
			el.style.backgroundSize = 'contain';
			el.style.backgroundRepeat = 'no-repeat';
			el.style.backgroundPosition = 'center';
		}
		if (el.classList.contains('navi-sticky-shade-swatch--empty')) {
			el.classList.remove('navi-sticky-shade-swatch--empty');
		}
		if (!el.classList.contains('saito-swatches-image')) {
			el.classList.add('saito-swatches-image');
		}
	}

	function bridgeNavi() {
		// One swatch per option of the listbox.
		$('.navi-sticky-shade-option[data-value]').each(function () {
			var image = images[toKey(this.getAttribute('data-value'))];
			var swatch = this.querySelector('.navi-sticky-shade-option-swatch');
			if (image && swatch) {
				paint(swatch, image);
			}
		});

		// Swatch of the closed picker (trigger button): follows the selected option.
		$('.navi-sticky-shade-trigger-swatch').each(function () {
			var $picker = $(this).closest('.navi-sticky-shade-picker');
			var selected = $picker.find('.navi-sticky-shade-option--selected[data-value]').first().attr('data-value');
			var image = images[toKey(selected)];
			if (image) {
				paint(this, image);
			}
		});
	}

	if (cfg.naviBridge && !$.isEmptyObject(images) && window.MutationObserver) {
		var queued = false;
		var observer = new MutationObserver(function () {
			if (queued) {
				return;
			}
			queued = true;
			window.requestAnimationFrame(function () {
				queued = false;
				// Pause while painting so our own style changes do not loop.
				observer.disconnect();
				bridgeNavi();
				observe();
			});
		});
		var observe = function () {
			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class', 'style', 'aria-selected', 'data-value']
			});
		};
		$(function () {
			bridgeNavi();
			observe();
		});
	}
})(jQuery);
