<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * All plugin settings live in a single option: saito_swatches_settings.
 */
final class Saito_Swatches_Settings {

	const OPTION = 'saito_swatches_settings';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'             => 1,
			'hide_label'          => 1,
			'hide_reset'          => 1,
			'price_per_unit'      => 0,
			'unit_reference'      => 100,
			'unit_position'       => 'after_cart',
			'reserve_height'      => 100,
			'unit_simple'         => 1,
			'unit_loop'           => 0,
			'show_price'          => 0,
			'button_price'        => 0,
			'hover_image'         => 0,
			'loop_swatches'       => 0,
			'loop_limit'          => 5,
			'loop_size'           => 22,
			'loop_block_position' => 'after_price',

			// Appearance.
			'image_size'          => 'thumbnail',
			'image_height'        => 60,
			'dot_size'            => 32,
			'dot_shape'           => 'circle',
			'layout'              => 'row',
			'content_layout'      => 'column',
			'align'               => 'center',
			'mobile_breakpoint'   => 1050,
			'mobile_layout'       => 'stack',
			'gap'                 => 20,
			'padding'             => 25,
			'mobile_padding'      => 10,
			'border_width'        => 1,
			'radius'              => 0,
			'color_border'        => '#000000',
			'color_border_active' => '#000000',
			'color_bg'            => '',
			'color_bg_active'     => '',
			'color_text'          => '',
			'inactive_opacity'    => 50,
			'unavailable_style'   => 'fade',
			'label_size'          => 0,
			'subtitle_size'       => 0,
			'price_size'          => 0,
			'color_subtitle'      => '',
			'color_price'         => '',
			'stock_badge'         => 1,
			'stock_badge_position' => 'corner',
			'color_badge'         => '',

			// Texts.
			'subtitle'            => '',
			'alt_template'        => '{label}',
			'unit_template'       => '{price} / 100 ml',
			'unavailable_text'    => '',
			'stock_badge_text'    => '',
			'reset_text'          => '',
			'button_template'     => '',

			// Advanced.
			'extra_classes'       => '',
			'hide_selectors'      => '',
			'load_everywhere'     => 0,
			'capacity_meta'       => 'product_capacity',
			'navi_bridge'         => 1,
			'keep_data'           => 0,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}

		/**
		 * Filters the Saito Swatches settings at runtime.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings Settings merged with defaults.
		 */
		return apply_filters( 'saito_swatches_settings', self::$cache );
	}

	/**
	 * Clear the request cache (after an update).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Normalised matching key for a value (term slug, term name or custom option).
	 * Case, accents, spaces, dashes and underscores are ignored: "30ml", "30 ml" and "30-ML" match.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function key( $value ) {
		return str_replace( array( '-', '_' ), '', sanitize_title( remove_accents( (string) $value ) ) );
	}

	/**
	 * Image attachment ID configured for a value (term slug, term name or custom value).
	 *
	 * @param string $value Attribute value.
	 * @return int Attachment ID, 0 when none.
	 */
	public static function image_id( $value ) {
		$key     = self::key( $value );
		$visuals = '' !== $key ? Saito_Swatches_Terms::all_visuals() : array();
		return isset( $visuals[ $key ] ) ? absint( $visuals[ $key ]['image_id'] ) : 0;
	}

	/**
	 * Allowed choices for select settings.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'unit_position'     => array( 'after_cart', 'in_price' ),
			'layout'            => array( 'row', 'grid', 'stack' ),
			'content_layout'    => array( 'column', 'row' ),
			'align'             => array( 'start', 'center', 'end' ),
			'mobile_layout'     => array( 'stack', 'row', 'grid' ),
			'unavailable_style' => array( 'fade', 'strike', 'hide' ),
			'dot_shape'         => array( 'circle', 'rounded', 'square' ),
			'stock_badge_position' => array( 'corner', 'below' ),
			'loop_block_position'  => array( 'after_price', 'after_title', 'after_image' ),
		);
	}

	/**
	 * Text of the "out of stock" badge (setting, or the translated default).
	 *
	 * @return string
	 */
	public static function stock_badge_text() {
		$text = self::get()['stock_badge_text'];
		return '' !== $text ? $text : __( 'Out of stock', 'saito-swatches' );
	}

	/**
	 * Sanitize the whole option before it is saved.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		// Checkboxes: absent from the request means "off".
		foreach ( array( 'enabled', 'hide_label', 'hide_reset', 'price_per_unit', 'unit_simple', 'unit_loop', 'show_price', 'button_price', 'hover_image', 'loop_swatches', 'stock_badge', 'load_everywhere', 'navi_bridge', 'keep_data' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		// Integers with bounds.
		$ints = array(
			'unit_reference'    => array( 1, 100000 ),
			'reserve_height'    => array( 0, 1000 ),
			'image_height'      => array( 10, 600 ),
			'dot_size'          => array( 8, 200 ),
			'mobile_breakpoint' => array( 0, 3000 ),
			'gap'               => array( 0, 200 ),
			'padding'           => array( 0, 200 ),
			'mobile_padding'    => array( 0, 200 ),
			'border_width'      => array( 0, 20 ),
			'radius'            => array( 0, 200 ),
			'inactive_opacity'  => array( 0, 100 ),
			'loop_limit'        => array( 1, 30 ),
			'loop_size'         => array( 12, 80 ),
			'label_size'        => array( 0, 60 ),
			'subtitle_size'     => array( 0, 60 ),
			'price_size'        => array( 0, 60 ),
		);
		foreach ( $ints as $key => $range ) {
			$value       = isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ? (int) $input[ $key ] : $defaults[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $value ) );
		}

		// Selects.
		foreach ( self::choices() as $key => $allowed ) {
			$value       = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : '';
			$out[ $key ] = in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
		}

		$sizes             = get_intermediate_image_sizes();
		$sizes[]           = 'full';
		$size              = isset( $input['image_size'] ) ? sanitize_key( $input['image_size'] ) : '';
		$out['image_size'] = in_array( $size, $sizes, true ) ? $size : $defaults['image_size'];

		// Colors: empty means "inherit from the theme".
		foreach ( array( 'color_border', 'color_border_active', 'color_bg', 'color_bg_active', 'color_text', 'color_badge', 'color_subtitle', 'color_price' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( trim( (string) $input[ $key ] ) ) : '';
			$out[ $key ] = $color ? $color : '';
		}

		// Texts.
		foreach ( array( 'subtitle', 'alt_template', 'unit_template', 'unavailable_text', 'stock_badge_text', 'reset_text', 'button_template' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		// CSS classes: keep only valid class names.
		$classes = isset( $input['extra_classes'] ) ? preg_split( '/\s+/', (string) $input['extra_classes'] ) : array();
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		$out['extra_classes'] = implode( ' ', array_unique( $classes ) );

		// CSS selectors: used as a jQuery selector, so strip anything that is not selector syntax.
		$selectors = isset( $input['hide_selectors'] ) ? sanitize_text_field( $input['hide_selectors'] ) : '';

		$out['hide_selectors'] = trim( preg_replace( '/[^a-zA-Z0-9_\-\.\#\s,>:\[\]=\'"\*~\+\(\)]/', '', $selectors ) );

		// Meta key read for the capacity of simple products (e.g. an ACF field).
		$out['capacity_meta'] = isset( $input['capacity_meta'] ) ? sanitize_key( $input['capacity_meta'] ) : '';

		self::flush();

		return $out;
	}
}
