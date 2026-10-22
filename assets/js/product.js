/**
 * Saito Swatches — "Saito Swatches" tab of the product edit screen.
 */
/* global jQuery, wp, saitoSwatchesProduct */
(function ($) {
	'use strict';

	var i18n = window.saitoSwatchesProduct || {};

	function setImage($row, id, url) {
		$row.find('.saito-pp__image').val(id || '');
		var $thumb = $row.find('.saito-pp__thumb').empty();
		if (id && url) {
			$('<img alt="">').attr('src', url).appendTo($thumb);
		} else {
			$('<span class="dashicons dashicons-format-image" aria-hidden="true"></span>').appendTo($thumb);
		}
		$row.toggleClass('has-image', !!(id && url));
	}

	$(document).on('click', '.saito-pp__thumb', function (event) {
		event.preventDefault();
		var $row = $(this).closest('.saito-pp__row');
		var frame = wp.media({
			title: i18n.chooseImage,
			button: { text: i18n.useImage },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var sizes = attachment.sizes || {};
			setImage($row, attachment.id, sizes.thumbnail ? sizes.thumbnail.url : attachment.url);
		});
		frame.open();
	});

	$(document).on('click', '.saito-pp__remove', function (event) {
		event.preventDefault();
		setImage($(this).closest('.saito-pp__row'), '', '');
	});

	$(document).on('change', '.saito-pp__icon', function () {
		var icon = $(this).val();
		$(this).siblings('.saito-pp__icon-preview')
			.attr('class', 'saito-pp__icon-preview dashicons' + (icon ? ' dashicons-' + icon : ''));
	});

	$(function () {
		if ($.fn.wpColorPicker) {
			$('.saito-pp__color').wpColorPicker();
		}
	});

	/* ---------- Copy the settings of another product ---------- */

	// "saito_swatches_product[attributes][KEY][values][VKEY][image]" → the bracket parts.
	function nameParts(name) {
		var parts = [];
		String(name || '').replace(/\[([^\]]*)\]/g, function (m, part) {
			parts.push(part);
		});
		return parts;
	}

	function setColor($input, color) {
		if ($.fn.wpColorPicker && $input.closest('.wp-picker-container').length) {
			if (color) {
				$input.wpColorPicker('color', color);
			} else {
				$input.closest('.wp-picker-container').find('.wp-picker-clear').trigger('click');
			}
		}
		$input.val(color || '');
	}

	function applyConfig(config, images) {
		var $panel = $('#saito_swatches_product_data');
		var attributes = config.attributes || {};
		var copied = 0;

		$panel.find('#saito_swatches_show_price').val(config.show_price || 'default');

		$panel.find('.saito-pp__attr').each(function () {
			var $attr = $(this);
			var $type = $attr.find('select[name$="[type]"]');
			var key = nameParts($type.attr('name'))[1];
			var attr = attributes[key] || {};
			var values = attr.values || {};

			$type.val(attr.type || 'default');
			$attr.find('select[name$="[shape]"]').val(attr.shape || 'default');
			$attr.find('input[name$="[size]"]').val(attr.size ? attr.size : '');

			$attr.find('.saito-pp__row').each(function () {
				var $row = $(this);
				var vkey = nameParts($row.find('.saito-pp__image').attr('name'))[3];
				var value = values[vkey] || {};
				if (Object.keys(value).length) {
					copied++;
				}
				setImage($row, value.image || '', value.image ? (images[value.image] || '') : '');
				setColor($row.find('.saito-pp__color'), value.color || '');
				$row.find('.saito-pp__icon').val(value.icon || '').trigger('change');
				$row.find('input[name$="[label]"]').val(value.label || '');
				$row.find('input[name$="[quantity]"]').val(value.quantity || '');
			});
		});

		return copied;
	}

	$(document).on('change', '#saito_swatches_copy_source', function () {
		$('.saito-pp__copy-button').prop('disabled', !$(this).val());
		$('.saito-pp__copy-status').text('').removeClass('is-error is-success');
	});

	$(document).on('click', '.saito-pp__copy-button', function (event) {
		event.preventDefault();
		var $button = $(this);
		var $select = $('#saito_swatches_copy_source');
		var $status = $('.saito-pp__copy-status');
		var source = $select.val();
		if (!source) {
			return;
		}
		var name = $.trim($select.find('option:selected').text());
		if (!window.confirm(String(i18n.copyConfirm || '').replace('%s', name))) {
			return;
		}

		$button.prop('disabled', true).addClass('is-busy');
		$.post(i18n.ajaxUrl, {
			action: 'saito_swatches_copy_config',
			nonce: i18n.copyNonce,
			source: source,
			target: $('#post_ID').val() || 0
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				$status.text(i18n.copyError || '').removeClass('is-success').addClass('is-error');
				return;
			}
			var copied = applyConfig(response.data.config || {}, response.data.images || {});
			$status
				.text(copied ? String(i18n.copyDone || '').replace('%d', copied) : (i18n.copyNone || ''))
				.removeClass('is-error').addClass('is-success');
		}).fail(function () {
			$status.text(i18n.copyError || '').removeClass('is-success').addClass('is-error');
		}).always(function () {
			$button.prop('disabled', false).removeClass('is-busy');
		});
	});
})(jQuery);
