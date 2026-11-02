/**
 * Saito Swatches — settings page.
 */
(function ($) {
	'use strict';

	var data = window.saitoSwatchesAdmin || {};
	var i18n = data.i18n || {};

	$(function () {
		var $form = $('#saito-swatches-form');
		if (!$form.length) {
			return;
		}

		var $preview = $('.saito-preview');
		var $status = $('.saito-savebar__status');
		var previewSelected = 1;
		var dirty = false;
		var submitting = false;
		var termCounter = 0;

		/* ---------- Helpers ---------- */

		function setting(key) {
			var $el = $form.find('[data-setting="' + key + '"]');
			if ($el.is(':checkbox')) {
				return $el.is(':checked');
			}
			return $el.length ? String($el.val() || '') : '';
		}

		function markDirty() {
			if (!dirty) {
				dirty = true;
				$status.text(i18n.unsaved || '');
				$form.addClass('is-dirty');
			}
			schedulePreview();
		}

		/* ---------- Tabs ---------- */

		function activateTab(tab) {
			var $tab = $('.saito-tab[data-tab="' + tab + '"]');
			if (!$tab.length) {
				return;
			}
			$('.saito-tab').removeClass('is-active').attr('aria-selected', 'false');
			$tab.addClass('is-active').attr('aria-selected', 'true');
			$('.saito-panel').attr('hidden', true);
			$('#saito-panel-' + tab).removeAttr('hidden');

			// Keep the tab after saving: options.php redirects to _wp_http_referer.
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tab);
			url.searchParams.delete('settings-updated');
			window.history.replaceState(null, '', url.toString());
			$form.find('input[name="_wp_http_referer"]').val(url.pathname + url.search);
			renderPreview();
		}

		$('.saito-tab, .saito-goto').on('click', function (event) {
			event.preventDefault();
			activateTab($(this).data('tab'));
		});

		$('.saito-tabs').on('keydown', '.saito-tab', function (event) {
			var step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];
			if (!step) {
				return;
			}
			event.preventDefault();
			var $tabs = $('.saito-tab');
			var next = ($tabs.index(this) + step + $tabs.length) % $tabs.length;
			$tabs.eq(next).trigger('focus').trigger('click');
		});

		/* ---------- Settings colors ---------- */

		if ($.fn.wpColorPicker) {
			$('.saito-color').wpColorPicker({
				change: function () {
					window.setTimeout(markDirty, 0);
				},
				clear: function () {
					window.setTimeout(markDirty, 0);
				}
			});
		}

		/* ---------- Attributes tab: term visuals, texts and new terms ---------- */

		function initTermColors($scope) {
			if ($.fn.wpColorPicker) {
				$scope.find('.saito-tv__color').not('.wp-color-picker').wpColorPicker({
					change: function () {
						window.setTimeout(markDirty, 0);
					},
					clear: function () {
						window.setTimeout(markDirty, 0);
					}
				});
			}
		}

		function setTermImage($row, id, url) {
			$row.find('.saito-tv__image').val(id || '');
			var $thumb = $row.find('.saito-tv__thumb').empty();
			if (id && url) {
				$('<img alt="">').attr('src', url).appendTo($thumb);
			} else {
				$('<span class="dashicons dashicons-format-image" aria-hidden="true"></span>').appendTo($thumb);
			}
			$row.toggleClass('has-image', !!(id && url));
			markDirty();
		}

		$form.on('click', '.saito-tv__thumb', function (event) {
			event.preventDefault();
			var $row = $(this).closest('.saito-tv');
			var frame = wp.media({
				title: i18n.chooseImage,
				button: { text: i18n.useImage },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('open', function () {
				var id = parseInt($row.find('.saito-tv__image').val(), 10);
				if (id) {
					var attachment = wp.media.attachment(id);
					attachment.fetch();
					frame.state().get('selection').reset([attachment]);
				}
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var sizes = attachment.sizes || {};
				setTermImage($row, attachment.id, sizes.thumbnail ? sizes.thumbnail.url : attachment.url);
			});
			frame.open();
		});

		$form.on('click', '.saito-tv__remove', function (event) {
			event.preventDefault();
			setTermImage($(this).closest('.saito-tv'), '', '');
		});

		$form.on('change', '.saito-tv__icon', function () {
			var icon = $(this).val();
			$(this).siblings('.saito-tv__icon-preview')
				.attr('class', 'saito-tv__icon-preview dashicons' + (icon ? ' dashicons-' + icon : ''));
		});

		$form.on('click', '.saito-attr__add', function () {
			var $card = $(this).closest('.saito-attr');
			var tpl = $card.find('template.saito-attr__template')[0];
			if (!tpl) {
				return;
			}
			var html = tpl.innerHTML.replace(/__N__/g, 'n' + termCounter++);
			var $row = $($.parseHTML('<table><tbody>' + $.trim(html) + '</tbody></table>')).find('tr').first();
			$card.find('.saito-tv-body').append($row);
			initTermColors($row);
			$row.find('input[type="text"]').first().trigger('focus');
			markDirty();
		});

		$form.on('click', '.saito-tv__delete', function () {
			$(this).closest('.saito-tv').remove();
			markDirty();
		});

		initTermColors($form);

		/* ---------- Live preview ---------- */

		function formatPrice(amount) {
			var c = data.currency || {};
			var decimals = parseInt(c.decimals, 10);
			if (isNaN(decimals)) {
				decimals = 2;
			}
			var parts = amount.toFixed(decimals).split('.');
			parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand || '');
			return String(c.format || '%1$s%2$s').replace('%1$s', c.symbol || '').replace('%2$s', parts.join(c.decimal || '.'));
		}

		// The attribute shown in the preview: the one being edited, else the first with swatches.
		function previewAttribute() {
			var $focused = $('.saito-attr').filter(function () {
				return $.contains(this, document.activeElement);
			}).first();
			if ($focused.length) {
				return $focused;
			}
			var $withSwatches = $('.saito-attr').filter(function () {
				var type = $(this).find('.saito-attr__type select').val();
				if (type === 'select') {
					return false;
				}
				if (type && type !== 'auto') {
					return true;
				}
				return $(this).find('.saito-tv.has-image, .saito-tv__color').filter(function () {
					return $(this).hasClass('has-image') || !!$(this).val();
				}).length > 0 || $(this).find('.saito-tv__icon').filter(function () {
					return !!$(this).val();
				}).length > 0;
			}).first();
			return $withSwatches.length ? $withSwatches : $('.saito-attr').first();
		}

		function previewItems($attr) {
			var items = [];
			$attr.find('.saito-tv').each(function () {
				var $row = $(this);
				var name = $.trim($row.find('.saito-tv__name strong').text()) || $.trim($row.find('.saito-tv__name input').val());
				if (!name) {
					return;
				}
				items.push({
					label: $.trim($row.find('.saito-tv__label').val()) || name,
					subtitle: $.trim($row.find('.saito-tv__subtitle').val()),
					image: $row.find('.saito-tv__thumb img').attr('src') || '',
					color: $row.find('.saito-tv__color').val() || '',
					icon: $row.find('.saito-tv__icon').val() || ''
				});
			});
			if (!items.length) {
				var sample = i18n.sampleLabel || 'Sample';
				items = [{ label: sample + ' 1' }, { label: sample + ' 2' }, { label: sample + ' 3' }];
			}
			return items.slice(0, 6);
		}

		function renderPreview() {
			var mobile = $preview.attr('data-device') === 'mobile';
			var $attr = previewAttribute();
			var items = previewItems($attr);
			var type = $attr.find('.saito-attr__type select').val() || 'auto';
			var globalSubtitle = setting('subtitle');
			var justify = { start: 'flex-start', center: 'center', end: 'flex-end' };
			// The preview has no variations: "variation image" shows the value image instead.
			var order = { auto: ['image', 'color', 'icon'], image: ['image', 'color', 'icon'], variation_image: ['image', 'color', 'icon'], color: ['color', 'image', 'icon'], icon: ['icon', 'image', 'color'] }[type] || [];
			var px = function (key) {
				return (parseInt(setting(key), 10) || 0) + 'px';
			};

			if (previewSelected >= items.length) {
				previewSelected = items.length > 1 ? 1 : 0;
			}

			var $group = $('<div class="saito-swatches" role="presentation"></div>').attr({
				'data-layout': setting('layout') || 'row',
				'data-mobile-layout': setting('mobile_layout') || 'stack',
				'data-content': setting('content_layout') || 'column',
				'data-shape': setting('dot_shape') || 'circle'
			});

			var vars = {
				'--saito-sw-gap': px('gap'),
				'--saito-sw-padding': mobile ? px('mobile_padding') : px('padding'),
				'--saito-sw-border-width': px('border_width'),
				'--saito-sw-radius': px('radius'),
				'--saito-sw-image-height': px('image_height'),
				'--saito-sw-dot-size': px('dot_size'),
				'--saito-sw-border': setting('color_border') || 'currentColor',
				'--saito-sw-border-active': setting('color_border_active') || 'var(--saito-sw-border)',
				'--saito-sw-bg': setting('color_bg') || 'transparent',
				'--saito-sw-bg-active': setting('color_bg_active') || 'var(--saito-sw-bg)',
				'--saito-sw-text': setting('color_text') || 'inherit',
				'--saito-sw-badge': setting('color_badge') || '#1d2327',
				'--saito-sw-label-size': (parseInt(setting('label_size'), 10) || 0) ? px('label_size') : '1em',
				'--saito-sw-subtitle-size': (parseInt(setting('subtitle_size'), 10) || 0) ? px('subtitle_size') : '0.8125em',
				'--saito-sw-price-size': (parseInt(setting('price_size'), 10) || 0) ? px('price_size') : '0.9375em',
				'--saito-sw-subtitle-color': setting('color_subtitle') || 'inherit',
				'--saito-sw-subtitle-opacity': setting('color_subtitle') ? '1' : '0.65',
				'--saito-sw-price-color': setting('color_price') || 'inherit',
				'--saito-sw-inactive-opacity': (parseInt(setting('inactive_opacity'), 10) || 0) / 100,
				'--saito-sw-justify': justify[setting('align')] || 'center'
			};
			$.each(vars, function (name, value) {
				$group[0].style.setProperty(name, value);
			});

			$.each(items, function (index, item) {
				var $btn = $('<button type="button" class="saito-swatch" tabindex="-1"></button>').attr('data-index', index);
				if (index === previewSelected) {
					$btn.addClass('is-selected');
				}
				for (var k = 0; k < order.length; k++) {
					if (order[k] === 'image' && item.image) {
						$('<span class="saito-swatch__media"></span>')
							.append($('<img class="saito-swatch__image" alt="">').attr('src', item.image))
							.appendTo($btn);
						break;
					}
					if (order[k] === 'color' && item.color) {
						$('<span class="saito-swatch__color" aria-hidden="true"></span>').css('background-color', item.color).appendTo($btn);
						break;
					}
					if (order[k] === 'icon' && item.icon) {
						$('<span class="saito-swatch__icon dashicons" aria-hidden="true"></span>').addClass('dashicons-' + item.icon).appendTo($btn);
						break;
					}
				}
				var $text = $('<span class="saito-swatch__text"></span>').appendTo($btn);
				$('<span class="saito-swatch__label"></span>').text(item.label).appendTo($text);
				var subtitle = item.subtitle || globalSubtitle;
				if (subtitle) {
					$('<span class="saito-swatch__subtitle"></span>').text(subtitle).appendTo($text);
				}
				if (setting('show_price')) {
					$('<span class="saito-swatch__price"></span>').text(formatPrice(19.9 + index * 15)).appendTo($text);
				}
				// The last value shows the out-of-stock badge, so its text and position can be previewed.
				if (setting('stock_badge') && items.length > 1 && index === items.length - 1) {
					$btn.addClass('is-out-of-stock');
					var $badge = $('<span class="saito-swatch__badge"></span>').text(setting('stock_badge_text') || i18n.outOfStock || 'Out of stock');
					if (setting('stock_badge_position') === 'below') {
						$text.append($badge);
					} else {
						$btn.append($badge.addClass('saito-swatch__badge--corner'));
					}
				}
				$group.append($btn);
			});

			var $canvas = $preview.find('.saito-preview__canvas').empty().append($group);

			// Mock of the product page around the swatches: variation price and
			// add-to-cart button, so that the unit price position and the price in
			// the button can be previewed. Sample: 30 ml at 34,50 €.
			var samplePrice = 34.5;
			var reference = parseFloat(setting('unit_reference')) || 100;
			var unitText = String(setting('unit_template') || '{price}').replace('{price}', formatPrice(samplePrice / 30 * reference));
			var showUnit = setting('price_per_unit');
			var position = setting('unit_position') || 'after_cart';

			var $price = $('<p class="saito-preview__price"></p>').text(formatPrice(samplePrice));
			if (showUnit && 'in_price' === position) {
				$('<span class="saito-unit-price saito-unit-price--inline"></span>').text(unitText).appendTo($price);
			}
			$canvas.append($price);

			var buttonText = i18n.addToCart || 'Add to cart';
			if (setting('button_price')) {
				var template = String(setting('button_template') || '{text} – {price}');
				if (template.indexOf('{price}') === -1) {
					template += ' {price}';
				}
				buttonText = $.trim(template.replace('{text}', buttonText).replace('{price}', formatPrice(samplePrice)));
			}
			$('<span class="saito-preview__button"></span>').text(buttonText).appendTo($canvas);

			if (showUnit && 'after_cart' === position) {
				$('<span class="saito-unit-price"></span>').text(unitText).appendTo($canvas);
			}
		}

		var scheduled = false;
		function schedulePreview() {
			if (scheduled) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame(function () {
				scheduled = false;
				renderPreview();
			});
		}

		$preview.on('click', '.saito-swatch', function () {
			previewSelected = parseInt($(this).attr('data-index'), 10) || 0;
			renderPreview();
		});

		$preview.on('click', '.saito-segmented button', function () {
			var device = $(this).data('device');
			$preview.attr('data-device', device);
			$(this).addClass('is-active').attr('aria-pressed', 'true')
				.siblings().removeClass('is-active').attr('aria-pressed', 'false');
			renderPreview();
		});

		// Previewing the attribute being edited.
		$form.on('focusin', '.saito-attr', schedulePreview);

		/* ---------- Dirty state ---------- */

		$form.on('input change', 'input, select, textarea', markDirty);

		$form.on('submit', function () {
			submitting = true;
		});

		$(window).on('beforeunload', function (event) {
			if (dirty && !submitting) {
				event.preventDefault();
				return '';
			}
		});

		renderPreview();
	});
})(jQuery);
