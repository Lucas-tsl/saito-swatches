<?php
/**
 * Price in the add-to-cart button, dynamic price shortcode and second image on hover
 * in product lists (features taken over from the former Saito Dev Toolkit).
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shop extras.
 */
final class Saito_Swatches_Shop {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'simple_button_text' ), 20, 2 );
		add_shortcode( 'saito_swatches_price', array( __CLASS__, 'price_shortcode' ) );
		// Name used by Saito Dev Toolkit: pages that already contain it keep working.
		add_shortcode( 'saito_prix_dynamique', array( __CLASS__, 'price_shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_hover_style' ) );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'classic_hover_image' ), 11 );
		add_filter( 'render_block_woocommerce/product-image', array( __CLASS__, 'block_hover_image' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'import_toolkit_settings' ) );
	}

	/**
	 * Settings of Saito Dev Toolkit mapped to Saito Swatches (no WordPress needed:
	 * tests/test-shop.php).
	 *
	 * @param array $toolkit Toolkit options (name => value, missing = not set).
	 * @return array Saito Swatches settings to turn on.
	 */
	public static function map_toolkit_settings( $toolkit ) {
		$map = array();
		if ( ! empty( $toolkit['saito_active_button'] ) ) {
			$map['button_price'] = 1;
		}
		if ( ! empty( $toolkit['saito_hover_product_enabled'] ) ) {
			$map['hover_image'] = 1;
		}
		return $map;
	}

	/**
	 * Once: turn on the price in the button and the hover image when they were on in
	 * Saito Dev Toolkit, whose modules they replace. Runs on admin_init rather than on
	 * activation, so that an update of an already active plugin also imports them.
	 */
	public static function import_toolkit_settings() {
		if ( get_option( 'saito_swatches_toolkit_imported' ) ) {
			return;
		}
		update_option( 'saito_swatches_toolkit_imported', 1, false );
		$toolkit = array();
		foreach ( array( 'saito_active_button', 'saito_hover_product_enabled' ) as $name ) {
			$toolkit[ $name ] = get_option( $name );
		}
		$map = self::map_toolkit_settings( $toolkit );
		if ( $map ) {
			$settings = get_option( Saito_Swatches_Settings::OPTION, array() );
			update_option( Saito_Swatches_Settings::OPTION, array_merge( is_array( $settings ) ? $settings : array(), $map ) );
			Saito_Swatches_Settings::flush();
		}
	}

	/**
	 * Button text with the price (no WordPress needed: tests/test-shop.php).
	 *
	 * @param string $template Template with {text} and {price}, empty for the default.
	 * @param string $text     Button text.
	 * @param string $price    Formatted price (plain text).
	 * @return string
	 */
	public static function button_text( $template, $text, $price ) {
		$template = '' !== trim( (string) $template ) ? (string) $template : '{text} – {price}';
		if ( false === strpos( $template, '{price}' ) ) {
			$template .= ' {price}';
		}
		return trim( str_replace( array( '{text}', '{price}' ), array( (string) $text, (string) $price ), $template ) );
	}

	/**
	 * Price as plain text, as displayed by the shop (taxes included or not).
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_text( $product ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Simple products: price in the add-to-cart button of the product page (variable
	 * products are handled by assets/js/frontend.js once a variation is chosen).
	 *
	 * @param string          $text    Button text.
	 * @param WC_Product|null $product Product.
	 * @return string
	 */
	public static function simple_button_text( $text, $product = null ) {
		$s = Saito_Swatches_Settings::get();
		if ( empty( $s['button_price'] ) || ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || '' === $product->get_price() ) {
			return $text;
		}
		// Only the main product of the page, not related or cross-sell products.
		if ( ! function_exists( 'is_product' ) || ! is_product() || get_queried_object_id() !== $product->get_id() ) {
			return $text;
		}
		return self::button_text( $s['button_template'], $text, self::price_text( $product ) );
	}

	/**
	 * [saito_swatches_price]: the product price, replaced by the price of the selected
	 * variation (assets/js/frontend.js). Optional attribute id="123".
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function price_shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'saito_swatches_price' );
		$product = $atts['id'] ? wc_get_product( absint( $atts['id'] ) ) : null;
		if ( ! $product ) {
			global $product;
		}
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		return '<span class="saito-swatches-dynamic-price" data-product-id="' . esc_attr( $product->get_id() ) . '">' . wp_kses_post( $product->get_price_html() ) . '</span>';
	}

	/**
	 * Second image of the product: first gallery image, empty when none.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $size    Image size.
	 * @return string
	 */
	private static function hover_image_html( $product, $size ) {
		$gallery = $product->get_gallery_image_ids();
		if ( ! $gallery ) {
			return '';
		}
		return wp_get_attachment_image(
			(int) reset( $gallery ),
			$size,
			false,
			array(
				'class'       => 'saito-swatches-hover-img',
				'alt'         => '',
				'loading'     => 'lazy',
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * Whether the hover image is on.
	 *
	 * @return bool
	 */
	private static function hover_enabled() {
		$s = Saito_Swatches_Settings::get();
		return ! empty( $s['hover_image'] );
	}

	/**
	 * Small stylesheet of the hover image, wherever product lists can appear.
	 */
	public static function enqueue_hover_style() {
		if ( self::hover_enabled() ) {
			wp_enqueue_style( 'saito-swatches-hover', SAITO_SWATCHES_URL . 'assets/css/hover.css', array(), saito_swatches_asset_version( 'assets/css/hover.css' ) );
		}
	}

	/**
	 * Classic product lists: second image printed right after the product thumbnail.
	 */
	public static function classic_hover_image() {
		global $product;
		// Block themes: the image is added by the Product Image block (block_hover_image()).
		// WooCommerce also fires this classic hook in block product grids for older
		// plugins, which printed a second, oversized image outside the product image.
		if ( ! self::hover_enabled() || ! $product instanceof WC_Product || wp_is_block_theme() ) {
			return;
		}
		echo self::hover_image_html( $product, 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
	}

	/**
	 * Block product grids: second image added inside the Product Image block.
	 *
	 * @param string        $content Block HTML.
	 * @param array         $parsed  Parsed block.
	 * @param WP_Block|null $block   Block instance.
	 * @return string
	 */
	public static function block_hover_image( $content, $parsed, $block = null ) {
		if ( ! self::hover_enabled() || ! $block instanceof WP_Block || empty( $block->context['postId'] ) ) {
			return $content;
		}
		// Product lists only, not the main image of a product page.
		if ( function_exists( 'is_product' ) && is_product() && (int) $block->context['postId'] === get_queried_object_id() ) {
			return $content;
		}
		$product = wc_get_product( (int) $block->context['postId'] );
		$image   = $product ? self::hover_image_html( $product, 'woocommerce_thumbnail' ) : '';
		if ( '' === $image ) {
			return $content;
		}
		// After the first image of the block, inside the same link.
		return (string) preg_replace( '#(<img\b[^>]*>)#', '$1' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $image ), $content, 1 );
	}
}
