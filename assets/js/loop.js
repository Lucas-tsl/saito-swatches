/**
 * Saito Swatches — shop and category pages: hovering a swatch shows the variation image.
 * No dependency: a few lines of vanilla JS.
 */
(function () {
	'use strict';

	function productImage(swatch) {
		var card = swatch.closest('li.product, .product, .wc-block-grid__product, .wp-block-post');
		return card ? card.querySelector('img.attachment-woocommerce_thumbnail, img.wp-post-image, .wc-block-components-product-image img, .woocommerce-LoopProduct-link img') : null;
	}

	function show(swatch) {
		var img = productImage(swatch);
		var src = swatch.getAttribute('data-image');
		if (!img || !src) {
			return;
		}
		if (!img.hasAttribute('data-saito-src')) {
			img.setAttribute('data-saito-src', img.getAttribute('src') || '');
			img.setAttribute('data-saito-srcset', img.getAttribute('srcset') || '');
		}
		img.setAttribute('src', src);
		var srcset = swatch.getAttribute('data-srcset');
		if (srcset) {
			img.setAttribute('srcset', srcset);
		} else {
			img.removeAttribute('srcset');
		}
	}

	function restore(swatch) {
		var img = productImage(swatch);
		if (!img || !img.hasAttribute('data-saito-src')) {
			return;
		}
		img.setAttribute('src', img.getAttribute('data-saito-src'));
		var srcset = img.getAttribute('data-saito-srcset');
		if (srcset) {
			img.setAttribute('srcset', srcset);
		} else {
			img.removeAttribute('srcset');
		}
	}

	['mouseover', 'focusin'].forEach(function (type) {
		document.addEventListener(type, function (event) {
			var swatch = event.target.closest && event.target.closest('.saito-loop-swatch[data-image]');
			if (swatch) {
				show(swatch);
			}
		});
	});
	['mouseout', 'focusout'].forEach(function (type) {
		document.addEventListener(type, function (event) {
			var swatch = event.target.closest && event.target.closest('.saito-loop-swatch[data-image]');
			if (swatch && !(event.relatedTarget && swatch.contains(event.relatedTarget))) {
				restore(swatch);
			}
		});
	});
})();
