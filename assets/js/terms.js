/**
 * Saito Swatches — swatch fields on Products → Attributes → Configure terms.
 */
/* global jQuery, wp, saitoSwatchesTerms */
(function ($) {
	'use strict';

	var i18n = window.saitoSwatchesTerms || {};

	function setImage($box, id, url) {
		$box.find('.saito-term-image__id').val(id || '');
		var $preview = $box.find('.saito-term-image__preview').empty();
		if (id && url) {
			$('<img alt="">').attr('src', url).appendTo($preview);
		}
		$box.toggleClass('has-image', !!(id && url));
	}

	$(document).on('click', '.saito-term-image__choose', function (event) {
		event.preventDefault();
		var $box = $(this).closest('.saito-term-image');
		var frame = wp.media({
			title: i18n.chooseImage,
			button: { text: i18n.useImage },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var sizes = attachment.sizes || {};
			setImage($box, attachment.id, sizes.thumbnail ? sizes.thumbnail.url : attachment.url);
		});
		frame.open();
	});

	$(document).on('click', '.saito-term-image__remove', function (event) {
		event.preventDefault();
		setImage($(this).closest('.saito-term-image'), '', '');
	});

	$(document).on('change', '.saito-term-icon__select', function () {
		var icon = $(this).val();
		$(this).siblings('.saito-term-icon__preview')
			.attr('class', 'saito-term-icon__preview dashicons' + (icon ? ' dashicons-' + icon : ''));
	});

	$(function () {
		if ($.fn.wpColorPicker) {
			$('.saito-term-color').wpColorPicker();
		}
	});

	// "Add new term" is submitted with AJAX: reset our fields once the term is created.
	$(document).ajaxComplete(function (event, xhr, settings) {
		var data = settings && typeof settings.data === 'string' ? settings.data : '';
		if (data.indexOf('action=add-tag') === -1 || !xhr || !xhr.responseXML) {
			return;
		}
		if ($(xhr.responseXML).find('wp_error').length) {
			return;
		}
		var $form = $('#addtag');
		setImage($form.find('.saito-term-image'), '', '');
		$form.find('.wp-picker-clear').trigger('click');
		$form.find('.saito-term-color').val('');
		$form.find('.wp-picker-holder .iris-picker').hide();
		$form.find('.saito-term-icon__select').val('').trigger('change');
	});
})(jQuery);
