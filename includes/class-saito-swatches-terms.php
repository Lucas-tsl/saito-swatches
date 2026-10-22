<?php
/**
 * Attribute terms: everything a swatch needs, stored on the term itself.
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * Swatch data per attribute value (image, color, icon, label, subtitle, quantity) and swatch type per attribute.
 * Editable from Saito → Swatches → Attributes and from Products → Attributes → Configure terms.
 */
final class Saito_Swatches_Terms {

	const OPTION_TYPES  = 'saito_swatches_attribute_types';
	const OPTION_SCHEMA = 'saito_swatches_schema';
	const META_IMAGE    = 'saito_swatches_image';
	const META_COLOR    = 'saito_swatches_color';
	const META_ICON     = 'saito_swatches_icon';
	const META_LABEL    = 'saito_swatches_label';
	const META_SUBTITLE = 'saito_swatches_subtitle';
	const META_QUANTITY = 'saito_swatches_quantity';
	const NONCE         = 'saito_swatches_term_nonce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'register_term_hooks' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

			// Swatch type field on WooCommerce's own attribute screen.
			add_action( 'woocommerce_after_add_attribute_fields', array( __CLASS__, 'attribute_type_field_add' ) );
			add_action( 'woocommerce_after_edit_attribute_fields', array( __CLASS__, 'attribute_type_field_edit' ) );
			add_action( 'woocommerce_attribute_added', array( __CLASS__, 'save_attribute_type' ), 10, 2 );
			add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'save_attribute_type' ), 10, 2 );
		}
	}

	/**
	 * All term meta keys used by the plugin.
	 *
	 * @return string[]
	 */
	public static function meta_keys() {
		return array( self::META_IMAGE, self::META_COLOR, self::META_ICON, self::META_LABEL, self::META_SUBTITLE, self::META_QUANTITY );
	}

	/**
	 * Swatch types an attribute can use.
	 *
	 * @return array type => label.
	 */
	public static function types() {
		return array(
			'auto'            => __( 'Automatic', 'saito-swatches' ),
			'image'           => __( 'Image', 'saito-swatches' ),
			'variation_image' => __( 'Variation image', 'saito-swatches' ),
			'color'           => __( 'Color', 'saito-swatches' ),
			'label'           => __( 'Button with text', 'saito-swatches' ),
			'icon'            => __( 'Icon', 'saito-swatches' ),
			'select'          => __( 'Dropdown (no swatches)', 'saito-swatches' ),
		);
	}

	/**
	 * Swatch type of an attribute taxonomy.
	 *
	 * @param string $taxonomy Attribute taxonomy (e.g. pa_contenance).
	 * @return string
	 */
	public static function type( $taxonomy ) {
		$types = get_option( self::OPTION_TYPES, array() );
		$type  = is_array( $types ) && isset( $types[ $taxonomy ] ) ? $types[ $taxonomy ] : 'auto';
		return in_array( $type, array( 'auto', 'image', 'variation_image', 'color', 'label', 'icon', 'select' ), true ) ? $type : 'auto';
	}

	/**
	 * Sanitize the attribute types option.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize_types( $input ) {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( $input as $taxonomy => $type ) {
			$taxonomy = sanitize_key( $taxonomy );
			$type     = sanitize_key( $type );
			if ( 0 === strpos( $taxonomy, 'pa_' ) && in_array( $type, array( 'image', 'variation_image', 'color', 'label', 'icon', 'select' ), true ) ) {
				$out[ $taxonomy ] = $type;
			}
		}
		return $out;
	}

	/**
	 * Positive decimal quantity as a string, or '' (accepts "15,5").
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_quantity( $value ) {
		$value = str_replace( ',', '.', sanitize_text_field( (string) $value ) );
		return is_numeric( $value ) && (float) $value > 0 ? (string) (float) $value : '';
	}

	/**
	 * Everything stored on a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array image_id, color, icon, label, subtitle, quantity.
	 */
	public static function visual( $term_id ) {
		$color = sanitize_hex_color( (string) get_term_meta( $term_id, self::META_COLOR, true ) );
		return array(
			'image_id' => absint( get_term_meta( $term_id, self::META_IMAGE, true ) ),
			'color'    => $color ? $color : '',
			'icon'     => self::sanitize_icon( get_term_meta( $term_id, self::META_ICON, true ) ),
			'label'    => sanitize_text_field( (string) get_term_meta( $term_id, self::META_LABEL, true ) ),
			'subtitle' => sanitize_text_field( (string) get_term_meta( $term_id, self::META_SUBTITLE, true ) ),
			'quantity' => self::sanitize_quantity( get_term_meta( $term_id, self::META_QUANTITY, true ) ),
		);
	}

	/**
	 * Empty term data.
	 *
	 * @return array
	 */
	public static function empty_visual() {
		return array(
			'image_id' => 0,
			'color'    => '',
			'icon'     => '',
			'label'    => '',
			'subtitle' => '',
			'quantity' => '',
		);
	}

	/**
	 * Icons offered in the picker (Dashicons, bundled with WordPress).
	 *
	 * @return array
	 */
	public static function icons() {
		return array(
			'star-filled', 'star-empty', 'heart', 'awards', 'carrot', 'coffee', 'beer', 'food', 'palmtree', 'pets',
			'sun', 'cloud', 'admin-site-alt3', 'drumstick', 'buddicons-community', 'superhero', 'smiley', 'flag',
			'lightbulb', 'shield', 'lock', 'tag', 'cart', 'gift', 'products', 'archive', 'portfolio', 'clock',
			'calendar-alt', 'location', 'admin-home', 'car', 'airplane', 'yes', 'no', 'plus', 'minus', 'marker',
			'warning', 'info', 'thumbs-up', 'thumbs-down', 'universal-access', 'visibility', 'image-filter',
			'art', 'admin-appearance', 'color-picker', 'format-image', 'camera', 'video-alt3', 'microphone',
			'format-audio', 'book', 'welcome-learn-more', 'businessperson', 'groups', 'networking', 'performance',
			'chart-bar', 'money-alt', 'tickets-alt', 'store', 'bank', 'hammer', 'admin-tools', 'database',
		);
	}

	/**
	 * Keep only a known icon name.
	 *
	 * @param mixed $icon Icon name.
	 * @return string
	 */
	public static function sanitize_icon( $icon ) {
		$icon = sanitize_key( (string) $icon );
		return in_array( $icon, self::icons(), true ) ? $icon : '';
	}

	/**
	 * Register term meta (typed and sanitized at the API level).
	 */
	public static function register_meta() {
		$metas = array(
			self::META_IMAGE    => array( 'integer', 'absint' ),
			self::META_COLOR    => array( 'string', 'sanitize_hex_color' ),
			self::META_ICON     => array( 'string', array( __CLASS__, 'sanitize_icon' ) ),
			self::META_LABEL    => array( 'string', 'sanitize_text_field' ),
			self::META_SUBTITLE => array( 'string', 'sanitize_text_field' ),
			self::META_QUANTITY => array( 'string', array( __CLASS__, 'sanitize_quantity' ) ),
		);
		foreach ( $metas as $key => $def ) {
			register_meta(
				'term',
				$key,
				array(
					'type'              => $def[0],
					'single'            => true,
					'sanitize_callback' => $def[1],
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Attribute taxonomies (pa_*).
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		$list = array();
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$list[] = wc_attribute_taxonomy_name( $tax->attribute_name );
		}
		return $list;
	}

	/**
	 * One-time migration (1.0 → 1.1): the former "Values / Adjustments" list is copied onto the
	 * matching terms (image only when the term has none; label, subtitle, quantity when empty),
	 * then removed from the settings.
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION_SCHEMA, 0 ) >= 2 ) {
			return;
		}
		$settings = get_option( Saito_Swatches_Settings::OPTION, array() );
		$rows     = is_array( $settings ) && ! empty( $settings['values'] ) && is_array( $settings['values'] ) ? $settings['values'] : array();

		if ( $rows ) {
			$terms_by_key = array();
			foreach ( self::taxonomies() as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					foreach ( array( $term->slug, $term->name ) as $value ) {
						$key = Saito_Swatches_Settings::key( $value );
						if ( '' !== $key ) {
							$terms_by_key[ $key ][] = $term->term_id;
						}
					}
				}
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['key'] ) ) {
					continue;
				}
				$key = Saito_Swatches_Settings::key( $row['key'] );
				if ( empty( $terms_by_key[ $key ] ) ) {
					continue;
				}
				foreach ( array_unique( $terms_by_key[ $key ] ) as $term_id ) {
					$current = self::visual( $term_id );
					if ( ! empty( $row['image_id'] ) && ! $current['image_id'] ) {
						update_term_meta( $term_id, self::META_IMAGE, absint( $row['image_id'] ) );
					}
					foreach ( array(
						'label'    => self::META_LABEL,
						'subtitle' => self::META_SUBTITLE,
					) as $field => $meta ) {
						$value = isset( $row[ $field ] ) ? sanitize_text_field( $row[ $field ] ) : '';
						if ( '' !== $value && '' === $current[ $field ] ) {
							update_term_meta( $term_id, $meta, $value );
						}
					}
					$quantity = isset( $row['quantity'] ) ? self::sanitize_quantity( $row['quantity'] ) : '';
					if ( '' !== $quantity && '' === $current['quantity'] ) {
						update_term_meta( $term_id, self::META_QUANTITY, $quantity );
					}
				}
			}
		}

		if ( is_array( $settings ) && array_key_exists( 'values', $settings ) ) {
			unset( $settings['values'] );
			update_option( Saito_Swatches_Settings::OPTION, $settings );
			Saito_Swatches_Settings::flush();
		}
		update_option( self::OPTION_SCHEMA, 2 );
	}

	/**
	 * Hook the term screens of every attribute taxonomy.
	 */
	public static function register_term_hooks() {
		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_form_fields' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_term' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term' ) );
			add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'add_column' ) );
			add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
		}
	}

	/**
	 * Whether the current screen is an attribute terms screen.
	 *
	 * @return bool
	 */
	private static function on_term_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->base, array( 'edit-tags', 'term' ), true ) && in_array( $screen->taxonomy, self::taxonomies(), true );
	}

	/**
	 * Assets for the term screens.
	 */
	public static function enqueue_assets() {
		if ( ! self::on_term_screen() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'saito-swatches-terms', SAITO_SWATCHES_URL . 'assets/css/terms.css', array( 'wp-color-picker' ), saito_swatches_asset_version( 'assets/css/terms.css' ) );
		wp_enqueue_script( 'saito-swatches-terms', SAITO_SWATCHES_URL . 'assets/js/terms.js', array( 'jquery', 'wp-color-picker' ), saito_swatches_asset_version( 'assets/js/terms.js' ), true );
		wp_localize_script(
			'saito-swatches-terms',
			'saitoSwatchesTerms',
			array(
				'chooseImage' => __( 'Choose a swatch image', 'saito-swatches' ),
				'useImage'    => __( 'Use this image', 'saito-swatches' ),
			)
		);
	}

	/**
	 * Image picker.
	 *
	 * @param array $visual Current data.
	 */
	private static function image_control( $visual ) {
		$thumb = $visual['image_id'] ? wp_get_attachment_image_url( $visual['image_id'], 'thumbnail' ) : '';
		?>
		<div class="saito-term-image<?php echo $thumb ? ' has-image' : ''; ?>">
			<span class="saito-term-image__preview">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="">
				<?php endif; ?>
			</span>
			<input type="hidden" name="saito_swatches_image" class="saito-term-image__id" value="<?php echo esc_attr( $visual['image_id'] ? $visual['image_id'] : '' ); ?>">
			<button type="button" class="button saito-term-image__choose"><?php esc_html_e( 'Choose image', 'saito-swatches' ); ?></button>
			<button type="button" class="button-link saito-term-image__remove"><?php esc_html_e( 'Remove image', 'saito-swatches' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Color input.
	 *
	 * @param array $visual Current data.
	 */
	private static function color_control( $visual ) {
		printf( '<input type="text" name="saito_swatches_color" id="saito-swatches-color" class="saito-term-color" value="%s">', esc_attr( $visual['color'] ) );
	}

	/**
	 * Icon picker (select + live preview).
	 *
	 * @param array $visual Current data.
	 */
	private static function icon_control( $visual ) {
		echo '<span class="saito-term-icon">';
		printf( '<span class="saito-term-icon__preview dashicons %s" aria-hidden="true"></span>', $visual['icon'] ? esc_attr( 'dashicons-' . $visual['icon'] ) : '' );
		echo '<select name="saito_swatches_icon" id="saito-swatches-icon" class="saito-term-icon__select">';
		echo '<option value="">' . esc_html__( 'No icon', 'saito-swatches' ) . '</option>';
		foreach ( self::icons() as $icon ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $icon ), selected( $visual['icon'], $icon, false ) );
		}
		echo '</select></span>';
	}

	/**
	 * Text inputs (label, subtitle, quantity) for the term forms.
	 *
	 * @return array field => label, placeholder.
	 */
	private static function text_fields() {
		return array(
			'label'    => array( __( 'Label shown on the swatch', 'saito-swatches' ), __( 'Value name', 'saito-swatches' ) ),
			'subtitle' => array( __( 'Swatch subtitle', 'saito-swatches' ), __( 'Global subtitle', 'saito-swatches' ) ),
			'quantity' => array( __( 'Quantity for the unit price', 'saito-swatches' ), __( 'Auto', 'saito-swatches' ) ),
		);
	}

	/**
	 * Help text shared by the forms.
	 *
	 * @return string
	 */
	private static function help() {
		return __( 'Which one is shown depends on the swatch type of the attribute (Saito → Swatches → Attributes). In automatic mode: image, then color, then icon.', 'saito-swatches' );
	}

	/**
	 * Fields on the "Add new term" form.
	 */
	public static function add_form_fields() {
		$empty = self::empty_visual();
		wp_nonce_field( 'saito_swatches_term', self::NONCE );
		?>
		<div class="form-field saito-term-form">
			<label><?php esc_html_e( 'Swatch image', 'saito-swatches' ); ?></label>
			<?php self::image_control( $empty ); ?>
		</div>
		<div class="form-field saito-term-form">
			<label for="saito-swatches-color"><?php esc_html_e( 'Swatch color', 'saito-swatches' ); ?></label>
			<?php self::color_control( $empty ); ?>
		</div>
		<div class="form-field saito-term-form">
			<label for="saito-swatches-icon"><?php esc_html_e( 'Swatch icon', 'saito-swatches' ); ?></label>
			<?php self::icon_control( $empty ); ?>
			<p class="description"><?php echo esc_html( self::help() ); ?></p>
		</div>
		<?php foreach ( self::text_fields() as $field => $def ) : ?>
			<div class="form-field saito-term-form">
				<label for="saito-swatches-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $def[0] ); ?></label>
				<input type="text" id="saito-swatches-<?php echo esc_attr( $field ); ?>" class="saito-term-text" name="saito_swatches_<?php echo esc_attr( $field ); ?>" value="" placeholder="<?php echo esc_attr( $def[1] ); ?>">
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Fields on the "Edit term" form.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function edit_form_fields( $term ) {
		$visual = self::visual( $term->term_id );
		?>
		<tr class="form-field saito-term-form">
			<th scope="row"><label><?php esc_html_e( 'Swatch image', 'saito-swatches' ); ?></label></th>
			<td><?php wp_nonce_field( 'saito_swatches_term', self::NONCE ); ?><?php self::image_control( $visual ); ?></td>
		</tr>
		<tr class="form-field saito-term-form">
			<th scope="row"><label for="saito-swatches-color"><?php esc_html_e( 'Swatch color', 'saito-swatches' ); ?></label></th>
			<td><?php self::color_control( $visual ); ?></td>
		</tr>
		<tr class="form-field saito-term-form">
			<th scope="row"><label for="saito-swatches-icon"><?php esc_html_e( 'Swatch icon', 'saito-swatches' ); ?></label></th>
			<td><?php self::icon_control( $visual ); ?><p class="description"><?php echo esc_html( self::help() ); ?></p></td>
		</tr>
		<?php foreach ( self::text_fields() as $field => $def ) : ?>
			<tr class="form-field saito-term-form">
				<th scope="row"><label for="saito-swatches-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
				<td><input type="text" id="saito-swatches-<?php echo esc_attr( $field ); ?>" name="saito_swatches_<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $visual[ $field ] ); ?>" placeholder="<?php echo esc_attr( $def[1] ); ?>"></td>
			</tr>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Save the swatch fields of a term (WooCommerce term screens).
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'saito_swatches_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$data = array();
		foreach ( array( 'image', 'color', 'icon', 'label', 'subtitle', 'quantity' ) as $field ) {
			$data[ $field ] = isset( $_POST[ 'saito_swatches_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'saito_swatches_' . $field ] ) ) : '';
		}
		self::save_visual( $term_id, $data );
	}

	/**
	 * Write the term metas from raw values (empty value = meta removed).
	 *
	 * @param int   $term_id Term ID.
	 * @param array $data    image, color, icon, label, subtitle, quantity.
	 */
	public static function save_visual( $term_id, $data ) {
		$values = array(
			self::META_IMAGE    => isset( $data['image'] ) ? absint( $data['image'] ) : 0,
			self::META_COLOR    => isset( $data['color'] ) ? sanitize_hex_color( sanitize_text_field( $data['color'] ) ) : '',
			self::META_ICON     => isset( $data['icon'] ) ? self::sanitize_icon( $data['icon'] ) : '',
			self::META_LABEL    => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '',
			self::META_SUBTITLE => isset( $data['subtitle'] ) ? sanitize_text_field( $data['subtitle'] ) : '',
			self::META_QUANTITY => isset( $data['quantity'] ) ? self::sanitize_quantity( $data['quantity'] ) : '',
		);
		foreach ( $values as $key => $value ) {
			if ( $value ) {
				update_term_meta( $term_id, $key, $value );
			} else {
				delete_term_meta( $term_id, $key );
			}
		}
	}

	/**
	 * "Swatch" column in the terms list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new['saito_swatch'] = __( 'Swatch', 'saito-swatches' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Swatch column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function render_column( $content, $column, $term_id ) {
		if ( 'saito_swatch' !== $column ) {
			return $content;
		}
		$visual = self::visual( $term_id );
		$out    = '';
		if ( $visual['image_id'] ) {
			$url  = wp_get_attachment_image_url( $visual['image_id'], 'thumbnail' );
			$out .= $url ? '<img class="saito-term-col__image" src="' . esc_url( $url ) . '" alt="">' : '';
		}
		if ( $visual['color'] ) {
			$out .= '<span class="saito-term-col__color" style="background-color:' . esc_attr( $visual['color'] ) . '"></span>';
		}
		if ( $visual['icon'] ) {
			$out .= '<span class="saito-term-col__icon dashicons dashicons-' . esc_attr( $visual['icon'] ) . '" aria-hidden="true"></span>';
		}
		return $out ? '<span class="saito-term-col">' . $out . '</span>' : '—';
	}

	/**
	 * Type select markup (WooCommerce attribute screen).
	 *
	 * @param string $current Current type.
	 */
	private static function type_select( $current ) {
		echo '<select name="saito_swatches_attribute_type" id="saito_swatches_attribute_type">';
		foreach ( self::types() as $type => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $type ), selected( $current, $type, false ), esc_html( $label ) );
		}
		echo '</select>';
		wp_nonce_field( 'saito_swatches_attribute_type', 'saito_swatches_attribute_nonce' );
	}

	/**
	 * Swatch type on "Add new attribute".
	 */
	public static function attribute_type_field_add() {
		?>
		<div class="form-field">
			<label for="saito_swatches_attribute_type"><?php esc_html_e( 'Swatch type (Saito Swatches)', 'saito-swatches' ); ?></label>
			<?php self::type_select( 'auto' ); ?>
			<p class="description"><?php esc_html_e( 'Set the image, color or icon of each value in "Configure terms".', 'saito-swatches' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Swatch type on "Edit attribute".
	 */
	public static function attribute_type_field_edit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the attribute being edited.
		$id        = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$attribute = $id ? wc_get_attribute( $id ) : null;
		$current   = $attribute ? self::type( $attribute->slug ) : 'auto';
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="saito_swatches_attribute_type"><?php esc_html_e( 'Swatch type (Saito Swatches)', 'saito-swatches' ); ?></label></th>
			<td>
				<?php self::type_select( $current ); ?>
				<p class="description"><?php esc_html_e( 'Set the image, color or icon of each value in "Configure terms".', 'saito-swatches' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the swatch type from WooCommerce's attribute screen.
	 *
	 * @param int   $id   Attribute ID.
	 * @param array $data Attribute data.
	 */
	public static function save_attribute_type( $id, $data = array() ) {
		if ( ! isset( $_POST['saito_swatches_attribute_nonce'], $_POST['saito_swatches_attribute_type'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saito_swatches_attribute_nonce'] ) ), 'saito_swatches_attribute_type' ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		$attribute = wc_get_attribute( $id );
		if ( ! $attribute ) {
			return;
		}
		$types                     = get_option( self::OPTION_TYPES, array() );
		$types                     = is_array( $types ) ? $types : array();
		$types[ $attribute->slug ] = sanitize_key( wp_unslash( $_POST['saito_swatches_attribute_type'] ) );
		update_option( self::OPTION_TYPES, self::sanitize_types( $types ) );
	}

	/**
	 * Register the options posted by the Attributes tab of the Saito settings page.
	 */
	public static function register_settings() {
		register_setting(
			'saito_swatches',
			self::OPTION_TYPES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_types' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		// Not stored: the sanitize callback writes the term data / new terms and returns nothing.
		register_setting(
			'saito_swatches',
			'saito_swatches_terms',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'apply_terms_form' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Save term data and create new terms from the Attributes tab.
	 *
	 * Expected input: [ taxonomy => [ 'existing' => [ term_id => data ], 'new' => [ [ name + data ] ] ] ].
	 *
	 * @param mixed $input Raw input.
	 * @return array Always empty (nothing to store).
	 */
	public static function apply_terms_form( $input ) {
		static $done = false;
		if ( $done || ! is_array( $input ) || ! current_user_can( 'manage_product_terms' ) ) {
			return array();
		}
		$done = true; // update_option() may sanitize twice (add + update); apply once.

		$taxonomies = self::taxonomies();
		foreach ( $input as $taxonomy => $data ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! in_array( $taxonomy, $taxonomies, true ) || ! taxonomy_exists( $taxonomy ) || ! is_array( $data ) ) {
				continue;
			}

			if ( ! empty( $data['existing'] ) && is_array( $data['existing'] ) ) {
				foreach ( $data['existing'] as $term_id => $values ) {
					$term_id = absint( $term_id );
					$term    = $term_id ? get_term( $term_id, $taxonomy ) : null;
					if ( $term instanceof WP_Term && is_array( $values ) ) {
						self::save_visual( $term_id, $values );
					}
				}
			}

			if ( ! empty( $data['new'] ) && is_array( $data['new'] ) ) {
				foreach ( $data['new'] as $row ) {
					$name = is_array( $row ) && isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
					if ( '' === $name ) {
						continue;
					}
					$existing = term_exists( $name, $taxonomy );
					$result   = $existing ? $existing : wp_insert_term( $name, $taxonomy );
					if ( is_array( $result ) && ! empty( $result['term_id'] ) ) {
						self::save_visual( (int) $result['term_id'], $row );
					}
				}
			}
		}

		return array();
	}

	/**
	 * Whether WCBoost Variation Swatches is active (it filters the same WooCommerce hook, later).
	 *
	 * @return bool
	 */
	public static function wcboost_active() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $plugin ) {
			if ( false !== strpos( (string) $plugin, 'wcboost-variation-swatches' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Map of term visuals for every attribute taxonomy, by matching key.
	 *
	 * @return array key => term data.
	 */
	public static function all_visuals() {
		$taxonomies = array_values( array_filter( self::taxonomies(), 'taxonomy_exists' ) );
		if ( ! $taxonomies ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small set, product pages only.
					'relation' => 'OR',
					array(
						'key'     => self::META_IMAGE,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::META_COLOR,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::META_ICON,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$out = array();
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $term ) {
			$visual = self::visual( $term->term_id );
			foreach ( array( $term->slug, $term->name ) as $value ) {
				$key = Saito_Swatches_Settings::key( $value );
				if ( '' !== $key && ! isset( $out[ $key ] ) ) {
					$out[ $key ] = $visual;
				}
			}
		}
		return $out;
	}
}
