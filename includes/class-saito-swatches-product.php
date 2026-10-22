<?php
/**
 * Per-product swatches: product tab, variation data, shop loop swatches, unit price of simple products.
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything that depends on a single product.
 *
 * Product meta "_saito_swatches":
 * [ 'show_price' => default|yes|no, 'capacity' => '30',
 *   'attributes' => [ attribute key => [ 'type', 'shape', 'size', 'values' => [ value key => image, color, icon, label, quantity ] ] ] ]
 */
final class Saito_Swatches_Product {

	const META = '_saito_swatches';

	/**
	 * Request cache of variation data.
	 *
	 * @var array
	 */
	private static $variation_cache = array();

	/**
	 * How many product grid templates (post-template, product-template) are being rendered.
	 *
	 * @var int
	 */
	private static $template_depth = 0;

	/**
	 * Blocks that repeat their inner blocks for each product of a grid.
	 *
	 * @return string[]
	 */
	private static function template_blocks() {
		return array( 'core/post-template', 'woocommerce/product-template' );
	}

	/**
	 * Before a block renders: note when a grid template starts (its inner blocks render inside it).
	 *
	 * @param array $parsed Parsed block.
	 * @return array
	 */
	public static function enter_template_block( $parsed ) {
		if ( ! empty( $parsed['blockName'] ) && in_array( $parsed['blockName'], self::template_blocks(), true ) ) {
			++self::$template_depth;
		}
		return $parsed;
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
			add_action( 'wp_ajax_saito_swatches_copy_config', array( __CLASS__, 'ajax_copy_config' ) );
		}
		/**
		 * Where the shop loop swatches are printed. Default: right after the product link closes
		 * (WooCommerce closes it at priority 5), before the add-to-cart button (priority 10), so the
		 * swatch links are never nested inside the product link.
		 *
		 * @param array $position array( hook, priority ).
		 */
		$position = apply_filters( 'saito_swatches_loop_position', array( 'woocommerce_after_shop_loop_item', 7 ) );
		if ( is_array( $position ) && isset( $position[0], $position[1] ) ) {
			add_action( (string) $position[0], array( __CLASS__, 'render_loop_swatches' ), (int) $position[1] );
		}
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'simple_unit_price' ), 20, 2 );
		add_filter( 'render_block_data', array( __CLASS__, 'enter_template_block' ) );
		add_filter( 'render_block', array( __CLASS__, 'render_block_swatches' ), 10, 3 );
	}

	/* ---------------------------------------------------------------------
	 * Configuration
	 * ------------------------------------------------------------------- */

	/**
	 * Shapes for color dots, icons and images.
	 *
	 * @return array
	 */
	public static function shapes() {
		return array(
			'circle'  => __( 'Circle', 'saito-swatches' ),
			'rounded' => __( 'Rounded square', 'saito-swatches' ),
			'square'  => __( 'Square', 'saito-swatches' ),
		);
	}

	/**
	 * Key of an attribute in the product config (taxonomy name, or sanitized custom attribute name).
	 *
	 * @param string $attribute Attribute name.
	 * @return string
	 */
	public static function attr_key( $attribute ) {
		return taxonomy_exists( $attribute ) ? $attribute : sanitize_title( $attribute );
	}

	/**
	 * Saved config of a product, sanitized.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function config( $product_id ) {
		$raw = get_post_meta( $product_id, self::META, true );
		return self::sanitize_config( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Sanitize a product config.
	 *
	 * @param array $raw Raw config.
	 * @return array
	 */
	public static function sanitize_config( $raw ) {
		$types  = array_merge( array( 'default' ), array_keys( Saito_Swatches_Terms::types() ) );
		$shapes = array_merge( array( 'default' ), array_keys( self::shapes() ) );

		$show = isset( $raw['show_price'] ) ? sanitize_key( $raw['show_price'] ) : 'default';
		$out  = array(
			'show_price' => in_array( $show, array( 'default', 'yes', 'no' ), true ) ? $show : 'default',
			'capacity'   => isset( $raw['capacity'] ) ? Saito_Swatches_Terms::sanitize_quantity( $raw['capacity'] ) : '',
			'attributes' => array(),
		);

		$attributes = isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
		foreach ( $attributes as $key => $attr ) {
			$key = sanitize_title( $key );
			if ( '' === $key || ! is_array( $attr ) ) {
				continue;
			}
			$type  = isset( $attr['type'] ) ? sanitize_key( $attr['type'] ) : 'default';
			$shape = isset( $attr['shape'] ) ? sanitize_key( $attr['shape'] ) : 'default';
			$size  = isset( $attr['size'] ) && is_numeric( $attr['size'] ) ? min( 300, max( 0, (int) $attr['size'] ) ) : 0;

			$values     = array();
			$raw_values = isset( $attr['values'] ) && is_array( $attr['values'] ) ? $attr['values'] : array();
			foreach ( $raw_values as $value_key => $value ) {
				$value_key = Saito_Swatches_Settings::key( $value_key );
				if ( '' === $value_key || ! is_array( $value ) ) {
					continue;
				}
				$color = isset( $value['color'] ) ? sanitize_hex_color( sanitize_text_field( $value['color'] ) ) : '';
				$clean = array_filter(
					array(
						'image'    => isset( $value['image'] ) ? absint( $value['image'] ) : 0,
						'color'    => $color ? $color : '',
						'icon'     => isset( $value['icon'] ) ? Saito_Swatches_Terms::sanitize_icon( $value['icon'] ) : '',
						'label'    => isset( $value['label'] ) ? sanitize_text_field( $value['label'] ) : '',
						'quantity' => isset( $value['quantity'] ) ? Saito_Swatches_Terms::sanitize_quantity( $value['quantity'] ) : '',
					)
				);
				if ( $clean ) {
					$values[ $value_key ] = $clean;
				}
			}

			$clean_attr = array(
				'type'   => in_array( $type, $types, true ) ? $type : 'default',
				'shape'  => in_array( $shape, $shapes, true ) ? $shape : 'default',
				'size'   => $size,
				'values' => $values,
			);
			if ( 'default' !== $clean_attr['type'] || 'default' !== $clean_attr['shape'] || $size || $values ) {
				$out['attributes'][ $key ] = $clean_attr;
			}
		}

		return $out;
	}

	/**
	 * Config of one attribute of a product (with defaults).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $attribute  Attribute name.
	 * @return array
	 */
	public static function attribute_config( $product_id, $attribute ) {
		$config = self::config( $product_id );
		$key    = self::attr_key( $attribute );
		$attr   = isset( $config['attributes'][ $key ] ) ? $config['attributes'][ $key ] : array();
		return wp_parse_args(
			$attr,
			array(
				'type'   => 'default',
				'shape'  => 'default',
				'size'   => 0,
				'values' => array(),
			)
		);
	}

	/**
	 * Swatch type of an attribute for a product: product setting, else global attribute setting.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $attribute  Attribute name.
	 * @return string
	 */
	public static function resolved_type( $product_id, $attribute ) {
		$attr = self::attribute_config( $product_id, $attribute );
		if ( 'default' !== $attr['type'] ) {
			return $attr['type'];
		}
		return taxonomy_exists( $attribute ) ? Saito_Swatches_Terms::type( $attribute ) : 'auto';
	}

	/**
	 * Whether prices are shown in the swatches of a product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function show_price( $product_id ) {
		$config = self::config( $product_id );
		if ( 'default' !== $config['show_price'] ) {
			return 'yes' === $config['show_price'];
		}
		return ! empty( Saito_Swatches_Settings::get()['show_price'] );
	}

	/**
	 * Capacity of a product (unit price): Saito field, else the configured meta key (e.g. ACF "product_capacity").
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	public static function capacity( $product ) {
		$id     = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$config = self::config( $id );
		if ( '' !== $config['capacity'] ) {
			return (float) $config['capacity'];
		}
		$meta_key = Saito_Swatches_Settings::get()['capacity_meta'];
		if ( '' === $meta_key ) {
			return 0.0;
		}
		$value = Saito_Swatches_Terms::sanitize_quantity( get_post_meta( $id, $meta_key, true ) );
		return '' !== $value ? (float) $value : 0.0;
	}

	/* ---------------------------------------------------------------------
	 * Variation data and swatch items
	 * ------------------------------------------------------------------- */

	/**
	 * Image and lowest price per attribute value, read from the product's variations.
	 *
	 * @param WC_Product $product   Variable product.
	 * @param string     $attribute Attribute name.
	 * @return array value key ("*" = any value) => array( image_id, price, in_stock ).
	 */
	public static function variation_data( $product, $attribute ) {
		$cache_key = $product->get_id() . '|' . $attribute;
		if ( isset( self::$variation_cache[ $cache_key ] ) ) {
			return self::$variation_cache[ $cache_key ];
		}

		$out      = array();
		$attr_key = sanitize_title( $attribute );
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || ! $variation->exists() || 'publish' !== $variation->get_status() ) {
				continue;
			}
			$attrs = $variation->get_attributes();
			$value = isset( $attrs[ $attr_key ] ) ? (string) $attrs[ $attr_key ] : '';
			$key   = '' === $value ? '*' : Saito_Swatches_Settings::key( $value );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'image_id' => 0,
					'price'    => null,
					'in_stock' => false,
				);
			}
			if ( $variation->is_in_stock() ) {
				$out[ $key ]['in_stock'] = true;
			}
			$image_id = (int) $variation->get_image_id( 'edit' );
			if ( $image_id && ! $out[ $key ]['image_id'] ) {
				$out[ $key ]['image_id'] = $image_id;
			}
			if ( $variation->is_purchasable() && '' !== (string) $variation->get_price() ) {
				$price = (float) wc_get_price_to_display( $variation );
				if ( null === $out[ $key ]['price'] || $price < $out[ $key ]['price'] ) {
					$out[ $key ]['price'] = $price;
				}
			}
		}

		self::$variation_cache[ $cache_key ] = $out;
		return $out;
	}

	/**
	 * Everything needed to display the swatches of one attribute of a product.
	 *
	 * @param WC_Product $product   Variable product.
	 * @param string     $attribute Attribute name (taxonomy or custom).
	 * @param array      $options   Option values offered for the attribute.
	 * @return array|null Null when the attribute keeps its dropdown.
	 */
	public static function build( $product, $attribute, $options ) {
		$type = self::resolved_type( $product->get_id(), $attribute );
		if ( 'select' === $type || ! $options ) {
			return null;
		}
		$attr   = self::attribute_config( $product->get_id(), $attribute );
		$vdata  = self::variation_data( $product, $attribute );
		$s      = Saito_Swatches_Settings::get();
		$items  = array();
		$visual = false;
		$raw    = array();

		if ( taxonomy_exists( $attribute ) ) {
			foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
				if ( in_array( $term->slug, $options, true ) ) {
					$raw[] = array( $term->slug, apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ), Saito_Swatches_Terms::visual( $term->term_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
				}
			}
		} else {
			foreach ( $options as $option ) {
				$raw[] = array( (string) $option, apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ), Saito_Swatches_Terms::empty_visual() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
			}
		}

		foreach ( $raw as $entry ) {
			list( $value, $label, $data ) = $entry;
			$key = Saito_Swatches_Settings::key( $value );

			// Product-level values override the global (term) values.
			$override = isset( $attr['values'][ $key ] ) ? $attr['values'][ $key ] : array();
			if ( ! empty( $override['image'] ) ) {
				$data['image_id'] = $override['image'];
			}
			foreach ( array( 'color', 'icon', 'label', 'quantity' ) as $field ) {
				if ( ! empty( $override[ $field ] ) ) {
					$data[ $field ] = $override[ $field ];
				}
			}

			$vd = isset( $vdata[ $key ] ) ? $vdata[ $key ] : ( isset( $vdata['*'] ) ? $vdata['*'] : array() );

			$data['variation_image_id'] = ! empty( $vd['image_id'] ) ? (int) $vd['image_id'] : 0;
			$data['price']              = isset( $vd['price'] ) ? $vd['price'] : null;
			// Out of stock only when this value has variations and none of them is in stock.
			$data['in_stock'] = $vd ? ( ! empty( $vd['in_stock'] ) || ( isset( $vdata['*'] ) && ! empty( $vdata['*']['in_stock'] ) ) ) : true;
			$data['value']              = $value;
			$data['name']               = $label;

			if ( $data['image_id'] || $data['color'] || $data['icon'] ) {
				$visual = true;
			}
			$items[] = $data;
		}

		// Automatic type: swatches only once something visual is configured.
		if ( 'auto' === $type && ! $visual ) {
			return null;
		}

		return array(
			'type'  => $type,
			'shape' => 'default' !== $attr['shape'] ? $attr['shape'] : $s['dot_shape'],
			'size'  => (int) $attr['size'],
			'items' => $items,
		);
	}

	/* ---------------------------------------------------------------------
	 * Product edit screen: "Saito Swatches" tab
	 * ------------------------------------------------------------------- */

	/**
	 * Add the tab to the "Product data" box.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['saito_swatches'] = array(
			'label'    => __( 'Saito Swatches', 'saito-swatches' ),
			'target'   => 'saito_swatches_product_data',
			'class'    => array(),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Assets for the product edit screen.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_admin( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'saito-swatches-product', SAITO_SWATCHES_URL . 'assets/css/product.css', array( 'wp-color-picker' ), saito_swatches_asset_version( 'assets/css/product.css' ) );
		wp_enqueue_script( 'saito-swatches-product', SAITO_SWATCHES_URL . 'assets/js/product.js', array( 'jquery', 'wp-color-picker' ), saito_swatches_asset_version( 'assets/js/product.js' ), true );
		wp_localize_script(
			'saito-swatches-product',
			'saitoSwatchesProduct',
			array(
				'chooseImage' => __( 'Choose a swatch image', 'saito-swatches' ),
				'useImage'    => __( 'Use this image', 'saito-swatches' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'copyNonce'   => wp_create_nonce( 'saito_swatches_copy_config' ),
				/* translators: %s: product name. */
				'copyConfirm' => __( 'Replace the swatch settings of this product with those of "%s"? Nothing is saved until you update the product.', 'saito-swatches' ),
				/* translators: %d: number of values. */
				'copyDone'    => __( 'Settings copied (%d values). Update the product to save them.', 'saito-swatches' ),
				'copyNone'    => __( 'Settings copied. This product has no value in common with the other one: only the general settings were copied.', 'saito-swatches' ),
				'copyError'   => __( 'The settings could not be loaded. Reload the page and try again.', 'saito-swatches' ),
			)
		);
	}

	/**
	 * Small preview of the global (term) visual, shown as a hint in the product tab.
	 *
	 * @param array $data Term data.
	 * @return string
	 */
	private static function global_hint( $data ) {
		$out = '';
		if ( $data['image_id'] ) {
			$url  = wp_get_attachment_image_url( $data['image_id'], 'thumbnail' );
			$out .= $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '';
		} elseif ( $data['color'] ) {
			$out .= '<span class="saito-pp__dot" style="background-color:' . esc_attr( $data['color'] ) . '"></span>';
		} elseif ( $data['icon'] ) {
			$out .= '<span class="dashicons dashicons-' . esc_attr( $data['icon'] ) . '"></span>';
		}
		return $out ? '<span class="saito-pp__global" title="' . esc_attr__( 'Global value (Saito → Swatches → Attributes)', 'saito-swatches' ) . '">' . $out . '<span>' . esc_html__( 'Global', 'saito-swatches' ) . '</span></span>' : '';
	}

	/**
	 * Render the product tab panel.
	 */
	public static function render_panel() {
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : null;
		$config  = $post ? self::config( $post->ID ) : self::sanitize_config( array() );
		$s       = Saito_Swatches_Settings::get();
		$acf     = $post && '' !== $s['capacity_meta'] ? Saito_Swatches_Terms::sanitize_quantity( get_post_meta( $post->ID, $s['capacity_meta'], true ) ) : '';
		$types   = array( 'default' => __( 'Default', 'saito-swatches' ) ) + Saito_Swatches_Terms::types();
		$shapes  = array( 'default' => __( 'Default', 'saito-swatches' ) ) + self::shapes();
		?>
		<div id="saito_swatches_product_data" class="panel woocommerce_options_panel hidden saito-pp">
			<?php self::render_copy_bar( $post ? $post->ID : 0 ); ?>
			<div class="options_group">
				<p class="form-field">
					<label for="saito_swatches_capacity"><?php esc_html_e( 'Capacity (unit price)', 'saito-swatches' ); ?></label>
					<input type="text" inputmode="decimal" class="short" id="saito_swatches_capacity" name="saito_swatches_product[capacity]" value="<?php echo esc_attr( $config['capacity'] ); ?>" placeholder="<?php echo esc_attr( '' !== $acf ? $acf : __( 'e.g. 30', 'saito-swatches' ) ); ?>">
					<span class="description">
						<?php
						if ( '' !== $acf ) {
							/* translators: 1: value, 2: meta key. */
							echo esc_html( sprintf( __( 'Empty: %1$s, read from the "%2$s" field.', 'saito-swatches' ), $acf, $s['capacity_meta'] ) );
						} else {
							esc_html_e( 'Used for the unit price of simple products, in the unit of the unit price text.', 'saito-swatches' );
						}
						?>
					</span>
				</p>
				<p class="form-field">
					<label for="saito_swatches_show_price"><?php esc_html_e( 'Price in the swatches', 'saito-swatches' ); ?></label>
					<select id="saito_swatches_show_price" name="saito_swatches_product[show_price]">
						<?php /* translators: %s: Yes or No. */ ?>
						<option value="default" <?php selected( $config['show_price'], 'default' ); ?>><?php echo esc_html( sprintf( __( 'Default (%s)', 'saito-swatches' ), ! empty( $s['show_price'] ) ? __( 'Yes', 'saito-swatches' ) : __( 'No', 'saito-swatches' ) ) ); ?></option>
						<option value="yes" <?php selected( $config['show_price'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'saito-swatches' ); ?></option>
						<option value="no" <?php selected( $config['show_price'], 'no' ); ?>><?php esc_html_e( 'No', 'saito-swatches' ); ?></option>
					</select>
				</p>
			</div>

			<?php
			$attributes = $product ? array_filter(
				$product->get_attributes(),
				function ( $attribute ) {
					return $attribute instanceof WC_Product_Attribute && $attribute->get_variation();
				}
			) : array();

			if ( ! $attributes ) :
				?>
				<p class="saito-pp__empty"><?php esc_html_e( 'Add attributes "Used for variations" in the Attributes tab, save the product, and their swatches appear here.', 'saito-swatches' ); ?></p>
				<?php
			endif;

			foreach ( $attributes as $attribute ) :
				$name     = $attribute->get_name();
				$key      = self::attr_key( $name );
				$attr     = isset( $config['attributes'][ $key ] ) ? $config['attributes'][ $key ] : array();
				$attr     = wp_parse_args(
					$attr,
					array(
						'type'   => 'default',
						'shape'  => 'default',
						'size'   => 0,
						'values' => array(),
					)
				);
				$base     = 'saito_swatches_product[attributes][' . $key . ']';
				$taxonomy = $attribute->is_taxonomy();
				$global   = $taxonomy ? $types[ Saito_Swatches_Terms::type( $name ) ] : $types['auto'];
				$values   = array();
				if ( $taxonomy ) {
					$terms = $attribute->get_terms();
					foreach ( $terms ? $terms : array() as $term ) {
						$values[] = array( $term->slug, $term->name, Saito_Swatches_Terms::visual( $term->term_id ) );
					}
				} else {
					foreach ( $attribute->get_options() as $option ) {
						$values[] = array( $option, $option, Saito_Swatches_Terms::empty_visual() );
					}
				}
				?>
				<div class="saito-pp__attr">
					<div class="saito-pp__head">
					<h4 class="saito-pp__title"><?php echo esc_html( wc_attribute_label( $name, $product ) ); ?>
						<span class="saito-pp__badge"><?php echo $taxonomy ? esc_html__( 'Global attribute', 'saito-swatches' ) : esc_html__( 'Custom attribute', 'saito-swatches' ); ?></span>
					</h4>
					<div class="saito-pp__settings">
						<label class="saito-pp__setting saito-pp__setting--type"><?php esc_html_e( 'Type', 'saito-swatches' ); ?>
							<select name="<?php echo esc_attr( $base . '[type]' ); ?>">
								<?php foreach ( $types as $value => $label ) : ?>
									<?php /* translators: %s: swatch type. */ ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $attr['type'], $value ); ?>><?php echo esc_html( 'default' === $value ? sprintf( __( 'Default (%s)', 'saito-swatches' ), $global ) : $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="saito-pp__setting saito-pp__setting--shape"><?php esc_html_e( 'Shape', 'saito-swatches' ); ?>
							<select name="<?php echo esc_attr( $base . '[shape]' ); ?>">
								<?php foreach ( $shapes as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $attr['shape'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="saito-pp__setting saito-pp__setting--size"><?php esc_html_e( 'Size', 'saito-swatches' ); ?>
							<span class="saito-pp__unit"><input type="number" min="0" max="300" step="1" name="<?php echo esc_attr( $base . '[size]' ); ?>" value="<?php echo esc_attr( $attr['size'] ? $attr['size'] : '' ); ?>" placeholder="<?php echo esc_attr( (string) $s['dot_size'] ); ?>" title="<?php esc_attr_e( 'Empty: size set in Saito → Swatches → Appearance.', 'saito-swatches' ); ?>"> px</span>
						</label>
					</div>
					</div>
					<div class="saito-pp__scroll">
					<table class="saito-pp__table">
						<thead>
							<tr>
								<th class="col-name"><?php esc_html_e( 'Value', 'saito-swatches' ); ?></th>
								<th class="col-image"><?php esc_html_e( 'Image', 'saito-swatches' ); ?></th>
								<th class="col-color"><?php esc_html_e( 'Color', 'saito-swatches' ); ?></th>
								<th class="col-icon"><?php esc_html_e( 'Icon', 'saito-swatches' ); ?></th>
								<th class="col-texts"><?php esc_html_e( 'Texts (optional)', 'saito-swatches' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $values as $entry ) :
								list( $value, $label, $data ) = $entry;
								$vkey  = Saito_Swatches_Settings::key( $value );
								$ov    = isset( $attr['values'][ $vkey ] ) ? $attr['values'][ $vkey ] : array();
								$ov    = wp_parse_args(
									$ov,
									array(
										'image'    => 0,
										'color'    => '',
										'icon'     => '',
										'label'    => '',
										'quantity' => '',
									)
								);
								$vbase = $base . '[values][' . $vkey . ']';
								$thumb = $ov['image'] ? wp_get_attachment_image_url( $ov['image'], 'thumbnail' ) : '';
								?>
								<tr class="saito-pp__row<?php echo $thumb ? ' has-image' : ''; ?>">
									<td class="saito-pp__name"><strong><?php echo esc_html( $label ); ?></strong><?php echo wp_kses_post( self::global_hint( $data ) ); ?></td>
									<td><div class="saito-pp__media">
										<button type="button" class="saito-pp__thumb" aria-label="<?php esc_attr_e( 'Choose image', 'saito-swatches' ); ?>">
											<?php if ( $thumb ) : ?>
												<img src="<?php echo esc_url( $thumb ); ?>" alt="">
											<?php else : ?>
												<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
											<?php endif; ?>
										</button>
										<input type="hidden" class="saito-pp__image" name="<?php echo esc_attr( $vbase . '[image]' ); ?>" value="<?php echo esc_attr( $ov['image'] ? $ov['image'] : '' ); ?>">
										<button type="button" class="button-link saito-pp__remove"><?php esc_html_e( 'Remove', 'saito-swatches' ); ?></button>
									</div></td>
									<td><input type="text" class="saito-pp__color" name="<?php echo esc_attr( $vbase . '[color]' ); ?>" value="<?php echo esc_attr( $ov['color'] ); ?>" aria-label="<?php esc_attr_e( 'Swatch color', 'saito-swatches' ); ?>"></td>
									<td><div class="saito-pp__icon-wrap">
										<span class="saito-pp__icon-preview dashicons<?php echo $ov['icon'] ? ' ' . esc_attr( 'dashicons-' . $ov['icon'] ) : ''; ?>" aria-hidden="true"></span>
										<select class="saito-pp__icon" name="<?php echo esc_attr( $vbase . '[icon]' ); ?>" aria-label="<?php esc_attr_e( 'Swatch icon', 'saito-swatches' ); ?>">
											<option value=""><?php esc_html_e( 'No icon', 'saito-swatches' ); ?></option>
											<?php foreach ( Saito_Swatches_Terms::icons() as $icon ) : ?>
												<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $ov['icon'], $icon ); ?>><?php echo esc_html( $icon ); ?></option>
											<?php endforeach; ?>
										</select>
									</div></td>
									<td><div class="saito-pp__texts">
										<input type="text" name="<?php echo esc_attr( $vbase . '[label]' ); ?>" value="<?php echo esc_attr( $ov['label'] ); ?>" placeholder="<?php echo esc_attr( '' !== $data['label'] ? $data['label'] : __( 'Displayed label', 'saito-swatches' ) ); ?>" aria-label="<?php esc_attr_e( 'Displayed label', 'saito-swatches' ); ?>">
										<input type="text" inputmode="decimal" name="<?php echo esc_attr( $vbase . '[quantity]' ); ?>" value="<?php echo esc_attr( $ov['quantity'] ); ?>" placeholder="<?php echo esc_attr( '' !== $data['quantity'] ? $data['quantity'] : __( 'Quantity (unit price)', 'saito-swatches' ) ); ?>" aria-label="<?php esc_attr_e( 'Quantity (unit price)', 'saito-swatches' ); ?>">
									</div></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
					<p class="saito-pp__help">
						<?php
						echo $taxonomy
							? esc_html__( 'Empty fields use the global values of the attribute (Saito → Swatches → Attributes). Fill them only to change this product.', 'saito-swatches' )
							: esc_html__( 'This attribute only exists on this product: its swatches are set here.', 'saito-swatches' );
						?>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Products that have Saito Swatches settings (sources for "copy settings").
	 *
	 * @param int $exclude Product ID to leave out (the product being edited).
	 * @return int[]
	 */
	public static function configured_products( $exclude = 0 ) {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin screen only, limited result set.
			)
		);
		$ids = array_map( 'intval', $ids );
		return array_values( array_diff( $ids, array( (int) $exclude ) ) );
	}

	/**
	 * "Copy settings from another product" bar of the product tab.
	 *
	 * @param int $product_id Product being edited.
	 */
	private static function render_copy_bar( $product_id ) {
		$sources = self::configured_products( $product_id );
		?>
		<div class="saito-pp__copy">
			<label for="saito_swatches_copy_source"><?php esc_html_e( 'Copy the settings of another product', 'saito-swatches' ); ?></label>
			<?php if ( $sources ) : ?>
				<div class="saito-pp__copy-controls">
					<select id="saito_swatches_copy_source">
						<option value=""><?php esc_html_e( 'Choose a product…', 'saito-swatches' ); ?></option>
						<?php foreach ( $sources as $id ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button saito-pp__copy-button" disabled><?php esc_html_e( 'Copy', 'saito-swatches' ); ?></button>
				</div>
				<p class="saito-pp__copy-help"><?php esc_html_e( 'Copies the type, shape, size, price display and the image, color, icon and texts of the values both products share. The capacity is not copied.', 'saito-swatches' ); ?></p>
				<p class="saito-pp__copy-status" role="status" aria-live="polite"></p>
			<?php else : ?>
				<p class="saito-pp__copy-help"><?php esc_html_e( 'No other product has swatch settings yet. Once you have set up one product, you can copy its settings here.', 'saito-swatches' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: settings of a product, to copy them into the product being edited.
	 */
	public static function ajax_copy_config() {
		check_ajax_referer( 'saito_swatches_copy_config', 'nonce' );
		$source = isset( $_POST['source'] ) ? absint( $_POST['source'] ) : 0;
		$target = isset( $_POST['target'] ) ? absint( $_POST['target'] ) : 0;
		if ( ! $source || 'product' !== get_post_type( $source ) || ! current_user_can( 'edit_post', $source ) || ( $target && ! current_user_can( 'edit_post', $target ) ) ) {
			wp_send_json_error( null, 403 );
		}

		$config = self::config( $source );
		$images = array();
		foreach ( $config['attributes'] as $attr ) {
			foreach ( $attr['values'] as $value ) {
				if ( ! empty( $value['image'] ) ) {
					$url = wp_get_attachment_image_url( $value['image'], 'thumbnail' );
					if ( $url ) {
						$images[ $value['image'] ] = $url;
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'config' => $config,
				'images' => (object) $images,
			)
		);
	}

	/**
	 * Save the product tab (WooCommerce has checked the product nonce; checked again here).
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'], $_POST['saito_swatches_product'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$raw    = map_deep( wp_unslash( $_POST['saito_swatches_product'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by map_deep + sanitize_config().
		$config = self::sanitize_config( is_array( $raw ) ? $raw : array() );

		if ( 'default' === $config['show_price'] && '' === $config['capacity'] && ! $config['attributes'] ) {
			$product->delete_meta_data( self::META );
		} else {
			$product->update_meta_data( self::META, $config );
		}
	}

	/* ---------------------------------------------------------------------
	 * Shop and category pages
	 * ------------------------------------------------------------------- */

	/**
	 * Block themes: product grids built with blocks (Product Collection, Products by category…) do not
	 * run the classic loop hooks, so the swatches are added after one of the blocks of each product card.
	 *
	 * @param string        $content Block HTML.
	 * @param array         $parsed  Parsed block.
	 * @param WP_Block|null $block   Block instance.
	 * @return string
	 */
	public static function render_block_swatches( $content, $parsed, $block = null ) {
		if ( ! empty( $parsed['blockName'] ) && in_array( $parsed['blockName'], self::template_blocks(), true ) ) {
			self::$template_depth = max( 0, self::$template_depth - 1 ); // The grid template has finished.
			return $content;
		}
		$s = Saito_Swatches_Settings::get();
		if ( empty( $s['enabled'] ) || empty( $s['loop_swatches'] ) || empty( $parsed['blockName'] ) ) {
			return $content;
		}
		$targets = array(
			'after_price' => 'woocommerce/product-price',
			'after_title' => 'core/post-title',
			'after_image' => 'woocommerce/product-image',
		);
		$target  = isset( $targets[ $s['loop_block_position'] ] ) ? $targets[ $s['loop_block_position'] ] : $targets['after_price'];
		/**
		 * Name of the block after which the swatches are printed in block product grids.
		 *
		 * @param string $target Block name, e.g. "woocommerce/product-price".
		 */
		$target = (string) apply_filters( 'saito_swatches_loop_block', $target );
		if ( $target !== $parsed['blockName'] || ! $block instanceof WP_Block ) {
			return $content;
		}

		// Only inside product grids (query loops), never for the main product of a single product page.
		$attrs    = isset( $parsed['attrs'] ) ? (array) $parsed['attrs'] : array();
		$in_loop  = self::$template_depth > 0 || ! empty( $attrs['isDescendentOfQueryLoop'] ) || isset( $block->context['queryId'] );
		$post_id  = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
		if ( ! $in_loop || ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return $content;
		}
		$card_product = wc_get_product( $post_id );
		if ( ! $card_product || ! $card_product->is_type( 'variable' ) ) {
			return $content;
		}

		$align = '';
		if ( ! empty( $attrs['textAlign'] ) ) {
			$align = sanitize_key( $attrs['textAlign'] );
		} elseif ( 'woocommerce/product-image' === $target ) {
			$align = 'center';
		}

		global $product;
		$previous = $product;
		$product  = $card_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's global product, restored right below.
		ob_start();
		self::render_loop_swatches( $align );
		$swatches = ob_get_clean();
		$product  = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's global product.

		if ( '' !== $swatches ) {
			self::enqueue_loop_assets();
		}
		return $content . $swatches;
	}

	/**
	 * Assets of the loop swatches, also when the grid is outside WooCommerce pages (home page, landing pages).
	 */
	private static function enqueue_loop_assets() {
		wp_enqueue_style( 'saito-swatches', SAITO_SWATCHES_URL . 'assets/css/frontend.css', array(), saito_swatches_asset_version( 'assets/css/frontend.css' ) );
		wp_enqueue_script( 'saito-swatches-loop', SAITO_SWATCHES_URL . 'assets/js/loop.js', array(), saito_swatches_asset_version( 'assets/js/loop.js' ), true );
	}

	/**
	 * Small swatches in product loops (first attribute with swatches).
	 *
	 * @param string $align Optional alignment: left, center or right (block grids).
	 */
	public static function render_loop_swatches( $align = '' ) {
		global $product;
		$s = Saito_Swatches_Settings::get();
		if ( empty( $s['enabled'] ) || empty( $s['loop_swatches'] ) || ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		foreach ( $product->get_variation_attributes() as $attribute => $options ) {
			$data = self::build( $product, $attribute, (array) $options );
			if ( ! $data ) {
				continue;
			}

			$limit = max( 1, (int) $s['loop_limit'] );
			$count = count( $data['items'] );
			$align = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : '';
			$out   = sprintf(
				'<div class="saito-loop-swatches"%1$s data-shape="%2$s" aria-label="%3$s" style="--saito-loop-size:%4$dpx">',
				$align ? ' data-align="' . esc_attr( $align ) . '"' : '',
				esc_attr( $data['shape'] ),
				esc_attr( wc_attribute_label( $attribute, $product ) ),
				absint( $s['loop_size'] )
			);

			foreach ( array_slice( $data['items'], 0, $limit ) as $item ) {
				$label = '' !== $item['label'] ? $item['label'] : $item['name'];
				$url   = add_query_arg( 'attribute_' . sanitize_title( $attribute ), rawurlencode( $item['value'] ), $product->get_permalink() );
				$hover = '';
				$image = $item['variation_image_id'] ? $item['variation_image_id'] : 0;
				if ( $image ) {
					$src = wp_get_attachment_image_src( $image, 'woocommerce_thumbnail' );
					if ( $src ) {
						$hover = ' data-image="' . esc_url( $src[0] ) . '" data-srcset="' . esc_attr( (string) wp_get_attachment_image_srcset( $image, 'woocommerce_thumbnail' ) ) . '"';
					}
				}
				$oos   = empty( $item['in_stock'] ) && ! empty( $s['stock_badge'] );
				$title = $oos ? $label . ' — ' . Saito_Swatches_Settings::stock_badge_text() : $label;
				$out  .= '<a class="saito-loop-swatch' . ( $oos ? ' is-out-of-stock' : '' ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '"' . $hover . '>';
				$out .= self::loop_visual( $data['type'], $item, $label );
				$out .= '<span class="screen-reader-text">' . esc_html( $title ) . '</span></a>';
			}
			if ( $count > $limit ) {
				$out .= '<a class="saito-loop-swatch saito-loop-swatch--more" href="' . esc_url( $product->get_permalink() ) . '">+' . esc_html( (string) ( $count - $limit ) ) . '</a>';
			}
			$out .= '</div>';

			echo wp_kses_post( $out );
			return; // One attribute per product card.
		}
	}

	/**
	 * Visual of a loop swatch.
	 *
	 * @param string $type  Swatch type.
	 * @param array  $item  Item data.
	 * @param string $label Label.
	 * @return string
	 */
	private static function loop_visual( $type, $item, $label ) {
		$order = array(
			'variation_image' => array( 'variation_image', 'image', 'color', 'icon' ),
			'image'           => array( 'image', 'color', 'icon' ),
			'color'           => array( 'color', 'image', 'icon' ),
			'icon'            => array( 'icon', 'image', 'color' ),
			'auto'            => array( 'image', 'color', 'icon' ),
			'label'           => array(),
		);
		foreach ( isset( $order[ $type ] ) ? $order[ $type ] : $order['auto'] as $kind ) {
			$image = 'variation_image' === $kind ? $item['variation_image_id'] : ( 'image' === $kind ? $item['image_id'] : 0 );
			if ( $image ) {
				$url = wp_get_attachment_image_url( $image, 'thumbnail' );
				if ( $url ) {
					return '<span class="saito-loop-swatch__media"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async"></span>';
				}
			}
			if ( 'color' === $kind && $item['color'] ) {
				return '<span class="saito-loop-swatch__color" style="background-color:' . esc_attr( $item['color'] ) . '"></span>';
			}
			if ( 'icon' === $kind && $item['icon'] ) {
				wp_enqueue_style( 'dashicons' );
				return '<span class="saito-loop-swatch__icon dashicons dashicons-' . esc_attr( $item['icon'] ) . '"></span>';
			}
		}
		return '<span class="saito-loop-swatch__text">' . esc_html( $label ) . '</span>';
	}

	/* ---------------------------------------------------------------------
	 * Unit price of simple products
	 * ------------------------------------------------------------------- */

	/**
	 * Formatted unit price text.
	 *
	 * @param float $price    Price to display.
	 * @param float $quantity Quantity.
	 * @return string Plain text.
	 */
	public static function unit_price_text( $price, $quantity ) {
		$s         = Saito_Swatches_Settings::get();
		$reference = max( 1, (float) $s['unit_reference'] );
		$amount    = $price / $quantity * $reference;
		$formatted = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		return str_replace( '{price}', $formatted, $s['unit_template'] );
	}

	/**
	 * Add the unit price after the price of simple products that have a capacity.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function simple_unit_price( $html, $product ) {
		$s = Saito_Swatches_Settings::get();
		if ( empty( $s['price_per_unit'] ) || empty( $s['unit_simple'] ) || ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || '' === $html ) {
			return $html;
		}
		$single = function_exists( 'is_product' ) && is_product() && (int) get_queried_object_id() === $product->get_id();
		if ( ! $single && empty( $s['unit_loop'] ) ) {
			return $html;
		}
		$quantity = self::capacity( $product );
		$price    = (float) wc_get_price_to_display( $product );
		if ( $quantity <= 0 || $price <= 0 ) {
			return $html;
		}
		return $html . ' <span class="saito-unit-price saito-unit-price--simple">' . esc_html( self::unit_price_text( $price, $quantity ) ) . '</span>';
	}
}
