<?php
/**
 * Front-end: swatches markup, styles and scripts.
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WooCommerce's variation dropdown filter, so the core template stays untouched.
 */
final class Saito_Swatches_Frontend {

	/**
	 * Whether swatches were printed in the current variations form.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'render_swatches' ), 20, 2 );
		add_filter( 'woocommerce_reset_variations_link', array( __CLASS__, 'filter_reset_link' ), 20 );
		add_action( 'woocommerce_before_variations_form', array( __CLASS__, 'reset_form_state' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * A new variations form starts.
	 */
	public static function reset_form_state() {
		self::$rendered = false;
	}

	/**
	 * Whether front-end assets should load on the current page.
	 *
	 * @return bool
	 */
	private static function should_load() {
		$settings = Saito_Swatches_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		$load = ! empty( $settings['load_everywhere'] ) || ( function_exists( 'is_product' ) && is_product() );

		/**
		 * Filters whether Saito Swatches assets load on the current page (e.g. for quick-view popups).
		 *
		 * @since 1.0.0
		 *
		 * @param bool $load Whether to load the assets.
		 */
		return (bool) apply_filters( 'saito_swatches_load_assets', $load );
	}

	/**
	 * Enqueue styles and scripts.
	 */
	public static function enqueue_assets() {
		$settings = Saito_Swatches_Settings::get();

		// Shop, category and tag pages: loop swatches and unit price only (no variation form there).
		$woo_page = function_exists( 'is_woocommerce' ) && is_woocommerce();
		if ( ! empty( $settings['enabled'] ) && $woo_page && ( ! empty( $settings['loop_swatches'] ) || ! empty( $settings['unit_loop'] ) ) ) {
			if ( ! is_product() ) { // Product pages get the full assets below.
				wp_enqueue_style( 'saito-swatches', SAITO_SWATCHES_URL . 'assets/css/frontend.css', array(), saito_swatches_asset_version( 'assets/css/frontend.css' ) );
				wp_add_inline_style( 'saito-swatches', self::inline_css( $settings ) );
			}
			if ( ! empty( $settings['loop_swatches'] ) ) { // Also for related products on product pages.
				wp_enqueue_script( 'saito-swatches-loop', SAITO_SWATCHES_URL . 'assets/js/loop.js', array(), saito_swatches_asset_version( 'assets/js/loop.js' ), true );
			}
		}

		if ( ! self::should_load() ) {
			return;
		}

		wp_enqueue_style( 'saito-swatches', SAITO_SWATCHES_URL . 'assets/css/frontend.css', array(), saito_swatches_asset_version( 'assets/css/frontend.css' ) );
		if ( in_array( 'icon', (array) get_option( Saito_Swatches_Terms::OPTION_TYPES, array() ), true ) ) {
			wp_enqueue_style( 'dashicons' ); // Loaded in the head when an attribute uses icons, to avoid a late flash.
		}
		wp_add_inline_style( 'saito-swatches', self::inline_css( $settings ) );

		wp_enqueue_script( 'saito-swatches', SAITO_SWATCHES_URL . 'assets/js/frontend.js', array( 'jquery', 'wc-add-to-cart-variation' ), saito_swatches_asset_version( 'assets/js/frontend.js' ), true );
		wp_localize_script( 'saito-swatches', 'saitoSwatches', self::script_data( $settings ) );
	}

	/**
	 * Data passed to the front-end script.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function script_data( $settings ) {
		// Term images and colors by matching key, readable by other plugins (window.saitoSwatches.images).
		$images = array();
		foreach ( Saito_Swatches_Terms::all_visuals() as $key => $visual ) {
			$entry = array();
			$url   = $visual['image_id'] ? wp_get_attachment_image_url( $visual['image_id'], 'thumbnail' ) : '';
			if ( $url ) {
				$entry['url'] = $url;
			}
			if ( $visual['color'] ) {
				$entry['color'] = $visual['color'];
			}
			if ( $entry ) {
				$images[ $key ] = $entry;
			}
		}

		// On a product page, this product's own settings (and variation images) win.
		$product = function_exists( 'is_product' ) && is_product() ? wc_get_product( get_queried_object_id() ) : null;
		if ( $product && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_variation_attributes() as $attribute => $options ) {
				$data = Saito_Swatches_Product::build( $product, $attribute, (array) $options );
				if ( ! $data ) {
					continue;
				}
				foreach ( $data['items'] as $item ) {
					$image_id = 'variation_image' === $data['type'] && $item['variation_image_id'] ? $item['variation_image_id'] : $item['image_id'];
					$entry    = array();
					$url      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
					if ( $url ) {
						$entry['url'] = $url;
					}
					if ( $item['color'] ) {
						$entry['color'] = $item['color'];
					}
					if ( $entry ) {
						$images[ Saito_Swatches_Settings::key( $item['value'] ) ] = $entry;
					}
				}
			}
		}

		/**
		 * Filters the images shared with the Saito Navi sticky add-to-cart swatches.
		 *
		 * @since 1.0.0
		 *
		 * @param array $images Matching key => array( 'url' => image URL ).
		 */
		$images = apply_filters( 'saito_swatches_bridge_images', $images );

		return array(
			'images'          => (object) $images,
			'naviBridge'      => ! empty( $settings['navi_bridge'] ),
			'pricePerUnit'    => ! empty( $settings['price_per_unit'] ),
			'unitReference'   => (float) $settings['unit_reference'],
			'unitTemplate'    => $settings['unit_template'],
			'unitPosition'    => $settings['unit_position'],
			'hideSelectors'   => $settings['hide_selectors'],
			'unavailableText' => $settings['unavailable_text'],
			'currency'        => array(
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'decimals' => wc_get_price_decimals(),
				'decimal'  => wc_get_price_decimal_separator(),
				'thousand' => wc_get_price_thousand_separator(),
			),
		);
	}

	/**
	 * CSS custom properties and responsive rules built from the settings.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function inline_css( $s ) {
		$justify = array(
			'start'  => 'flex-start',
			'center' => 'center',
			'end'    => 'flex-end',
		);

		$vars = array(
			'--saito-sw-gap'              => absint( $s['gap'] ) . 'px',
			'--saito-sw-padding'          => absint( $s['padding'] ) . 'px',
			'--saito-sw-border-width'     => absint( $s['border_width'] ) . 'px',
			'--saito-sw-radius'           => absint( $s['radius'] ) . 'px',
			'--saito-sw-image-height'     => absint( $s['image_height'] ) . 'px',
			'--saito-sw-dot-size'         => absint( $s['dot_size'] ) . 'px',
			'--saito-sw-border'           => $s['color_border'] ? $s['color_border'] : 'currentColor',
			'--saito-sw-border-active'    => $s['color_border_active'] ? $s['color_border_active'] : 'var(--saito-sw-border)',
			'--saito-sw-bg'               => $s['color_bg'] ? $s['color_bg'] : 'transparent',
			'--saito-sw-bg-active'        => $s['color_bg_active'] ? $s['color_bg_active'] : 'var(--saito-sw-bg)',
			'--saito-sw-text'             => $s['color_text'] ? $s['color_text'] : 'inherit',
			'--saito-sw-badge'            => $s['color_badge'] ? $s['color_badge'] : '#1d2327',
			'--saito-sw-label-size'       => absint( $s['label_size'] ) ? absint( $s['label_size'] ) . 'px' : '1em',
			'--saito-sw-subtitle-size'    => absint( $s['subtitle_size'] ) ? absint( $s['subtitle_size'] ) . 'px' : '0.8125em',
			'--saito-sw-price-size'       => absint( $s['price_size'] ) ? absint( $s['price_size'] ) . 'px' : '0.9375em',
			'--saito-sw-subtitle-color'   => $s['color_subtitle'] ? $s['color_subtitle'] : 'inherit',
			'--saito-sw-subtitle-opacity' => $s['color_subtitle'] ? '1' : '0.65',
			'--saito-sw-price-color'      => $s['color_price'] ? $s['color_price'] : 'inherit',
			'--saito-sw-inactive-opacity' => round( absint( $s['inactive_opacity'] ) / 100, 2 ),
			'--saito-sw-justify'          => isset( $justify[ $s['align'] ] ) ? $justify[ $s['align'] ] : 'center',
		);

		$css = '.saito-swatches{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		$css .= '}';

		if ( ! empty( $s['hide_label'] ) ) {
			$css .= 'form.variations_form table.variations tr:has(.saito-swatches) th.label{display:none}';
		}

		if ( absint( $s['reserve_height'] ) > 0 ) {
			$css .= 'form.variations_form:has(.saito-swatches) .single_variation_wrap{min-height:' . absint( $s['reserve_height'] ) . 'px}';
		}

		$breakpoint = absint( $s['mobile_breakpoint'] );
		if ( $breakpoint > 0 ) {
			$css .= '@media (max-width:' . $breakpoint . 'px){'
				. '.saito-swatches{--saito-sw-padding:' . absint( $s['mobile_padding'] ) . 'px}'
				. self::mobile_layout_css( '.saito-swatches.saito-swatches' )
				. '}';
		}

		return $css;
	}

	/**
	 * Mobile layout rules (also reused by the admin live preview).
	 *
	 * @param string $scope Selector for the swatches container.
	 * @return string
	 */
	public static function mobile_layout_css( $scope ) {
		return $scope . '[data-mobile-layout="stack"]{display:flex;flex-direction:column;align-items:stretch}'
			. $scope . '[data-mobile-layout="stack"] .saito-swatch{width:100%}'
			. $scope . '[data-mobile-layout="row"]{display:flex;flex-direction:row;flex-wrap:wrap}'
			. $scope . '[data-mobile-layout="row"] .saito-swatch{width:auto;flex:1 1 0}'
			. $scope . '[data-mobile-layout="grid"]{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}'
			. $scope . '[data-mobile-layout="grid"] .saito-swatch{width:auto}';
	}

	/**
	 * Hide or relabel WooCommerce's "Clear" link when swatches are shown.
	 *
	 * @param string $link Reset link HTML.
	 * @return string
	 */
	public static function filter_reset_link( $link ) {
		if ( ! self::$rendered ) {
			return $link;
		}
		$settings = Saito_Swatches_Settings::get();
		if ( ! empty( $settings['hide_reset'] ) ) {
			return '';
		}
		if ( '' !== $settings['reset_text'] ) {
			return '<a class="reset_variations" href="#" aria-label="' . esc_attr( $settings['reset_text'] ) . '">' . esc_html( $settings['reset_text'] ) . '</a>';
		}
		return $link;
	}

	/**
	 * Visual part of a swatch, according to the swatch type.
	 * Fallbacks: image → color → icon (automatic), and the closest available visual for explicit types.
	 *
	 * @param string $type  Swatch type.
	 * @param array  $item  Item data (image_id, variation_image_id, color, icon).
	 * @param string $label Value label (for the alt text).
	 * @param array  $s     Settings.
	 * @return string
	 */
	private static function visual_markup( $type, $item, $label, $s ) {
		$order = array(
			'auto'            => array( 'image', 'color', 'icon' ),
			'image'           => array( 'image', 'color', 'icon' ),
			'variation_image' => array( 'variation_image', 'image', 'color', 'icon' ),
			'color'           => array( 'color', 'image', 'icon' ),
			'icon'            => array( 'icon', 'image', 'color' ),
			'label'           => array(),
		);
		$candidates = isset( $order[ $type ] ) ? $order[ $type ] : $order['auto'];

		foreach ( $candidates as $kind ) {
			$image_id = 'variation_image' === $kind ? $item['variation_image_id'] : ( 'image' === $kind ? $item['image_id'] : 0 );
			if ( $image_id ) {
				$alt   = '' !== $s['alt_template'] ? str_replace( '{label}', wp_strip_all_tags( $label ), $s['alt_template'] ) : '';
				$image = wp_get_attachment_image(
					$image_id,
					$s['image_size'],
					false,
					array(
						'class'        => 'saito-swatch__image',
						'alt'          => $alt,
						'loading'      => 'eager',
						'decoding'     => 'async',
						'data-no-lazy' => '1',
					)
				);
				if ( $image ) {
					return '<span class="saito-swatch__media">' . $image . '</span>';
				}
			}
			if ( 'color' === $kind && $item['color'] ) {
				return '<span class="saito-swatch__color" style="background-color:' . esc_attr( $item['color'] ) . '" aria-hidden="true"></span>';
			}
			if ( 'icon' === $kind && $item['icon'] ) {
				wp_enqueue_style( 'dashicons' );
				return '<span class="saito-swatch__icon dashicons dashicons-' . esc_attr( $item['icon'] ) . '" aria-hidden="true"></span>';
			}
		}

		return '';
	}

	/**
	 * Print the swatches before the native <select> (kept, hidden, for WooCommerce's script).
	 *
	 * @param string $html Dropdown HTML.
	 * @param array  $args Dropdown arguments.
	 * @return string
	 */
	public static function render_swatches( $html, $args ) {
		$s = Saito_Swatches_Settings::get();
		if ( empty( $s['enabled'] ) ) {
			return $html;
		}

		$product   = isset( $args['product'] ) && $args['product'] instanceof WC_Product ? $args['product'] : null;
		$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		$options   = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		if ( ! $product || '' === $attribute ) {
			return $html;
		}
		if ( empty( $options ) ) {
			$attrs   = $product->get_variation_attributes();
			$options = isset( $attrs[ $attribute ] ) ? (array) $attrs[ $attribute ] : array();
		}

		// Global attribute values + this product's settings (also for custom attributes).
		$data = Saito_Swatches_Product::build( $product, $attribute, $options );
		if ( ! $data || ! $data['items'] ) {
			return $html;
		}

		self::$rendered = true;

		$type        = $data['type'];
		$show_price  = Saito_Swatches_Product::show_price( $product->get_id() );
		$select_name = ! empty( $args['name'] ) ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
		$selected    = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$classes     = trim( 'saito-swatch ' . $s['extra_classes'] );
		$style       = $data['size'] ? ' style="--saito-sw-dot-size:' . absint( $data['size'] ) . 'px;--saito-sw-image-height:' . absint( $data['size'] ) . 'px"' : '';

		$out = sprintf(
			'<div class="saito-swatches" role="radiogroup" aria-label="%1$s" data-attribute_name="%2$s" data-layout="%3$s" data-mobile-layout="%4$s" data-content="%5$s" data-unavailable="%6$s" data-type="%7$s" data-shape="%8$s"%9$s>',
			esc_attr( wc_attribute_label( $attribute, $product ) ),
			esc_attr( $select_name ),
			esc_attr( $s['layout'] ),
			esc_attr( $s['mobile_layout'] ),
			esc_attr( $s['content_layout'] ),
			esc_attr( $s['unavailable_style'] ),
			esc_attr( $type ),
			esc_attr( $data['shape'] ),
			$style
		);

		foreach ( $data['items'] as $item ) {
			$label    = '' !== $item['label'] ? $item['label'] : $item['name'];
			$subtitle = '' !== $item['subtitle'] ? $item['subtitle'] : $s['subtitle'];
			$active   = '' !== $selected && $selected === $item['value'];
			$oos      = ! empty( $s['stock_badge'] ) && empty( $item['in_stock'] );

			$out .= sprintf(
				'<button type="button" class="%1$s%2$s" role="radio" aria-checked="%3$s" data-value="%4$s"%5$s>',
				esc_attr( $classes ),
				( $active ? ' is-selected' : '' ) . ( $oos ? ' is-out-of-stock' : '' ),
				$active ? 'true' : 'false',
				esc_attr( $item['value'] ),
				'' !== $item['quantity'] ? ' data-quantity="' . esc_attr( $item['quantity'] ) . '"' : ''
			);

			$out .= self::visual_markup( $type, $item, $label, $s );

			$out .= '<span class="saito-swatch__text">';
			$out .= '<span class="saito-swatch__label">' . esc_html( $label ) . '</span>';
			if ( '' !== $subtitle ) {
				$out .= '<span class="saito-swatch__subtitle">' . esc_html( $subtitle ) . '</span>';
			}
			if ( $show_price && null !== $item['price'] ) {
				$out .= '<span class="saito-swatch__price">' . wp_kses_post( wc_price( $item['price'] ) ) . '</span>';
			}
			if ( $oos && 'below' === $s['stock_badge_position'] ) {
				$out .= '<span class="saito-swatch__badge">' . esc_html( Saito_Swatches_Settings::stock_badge_text() ) . '</span>';
			}
			$out .= '</span>';
			if ( $oos && 'below' !== $s['stock_badge_position'] ) {
				$out .= '<span class="saito-swatch__badge saito-swatch__badge--corner">' . esc_html( Saito_Swatches_Settings::stock_badge_text() ) . '</span>';
			}
			$out .= '</button>';
		}

		$out .= '</div>';

		return '<div class="saito-swatches-wrap">' . $out . $html . '</div>';
	}
}
