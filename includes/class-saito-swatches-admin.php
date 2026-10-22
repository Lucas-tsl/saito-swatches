<?php
/**
 * Admin: Saito menu, settings page, assets.
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page under the shared "Saito" menu.
 */
final class Saito_Swatches_Admin {

	const SLUG  = 'saito-swatches';
	const GROUP = 'saito_swatches';
	const CAP   = 'manage_woocommerce';

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SAITO_SWATCHES_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability required to edit the settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAP;
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $tab Optional tab.
	 * @return string
	 */
	public static function page_url( $tab = '' ) {
		$args = array( 'page' => self::SLUG );
		if ( $tab ) {
			$args['tab'] = $tab;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'saito-swatches' ) . '</a>' );
		return $links;
	}

	/**
	 * Parent menu: the shared "Saito" menu (includes/saito-hub/hub.php).
	 *
	 * @return string
	 */
	private static function saito_parent() {
		/**
		 * Filters the parent menu slug the settings page is attached to.
		 *
		 * @since 1.0.0
		 *
		 * @param string $parent Parent menu slug.
		 */
		return (string) apply_filters( 'saito_swatches_parent_menu', saito_hub_menu_slug() );
	}

	/**
	 * Register the settings page under the shared "Saito" menu.
	 */
	public static function register_menu() {
		self::$hook = (string) add_submenu_page( self::saito_parent(), __( 'Saito Swatches', 'saito-swatches' ), __( 'Swatches', 'saito-swatches' ), self::CAP, self::SLUG, array( __CLASS__, 'render_page' ) );
	}

	/**
	 * Register the option with the Settings API.
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			Saito_Swatches_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Saito_Swatches_Settings', 'sanitize' ),
				'default'           => Saito_Swatches_Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Enqueue admin assets on the settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		$settings = Saito_Swatches_Settings::get();

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style( 'saito-swatches', SAITO_SWATCHES_URL . 'assets/css/frontend.css', array(), saito_swatches_asset_version( 'assets/css/frontend.css' ) );
		wp_add_inline_style( 'saito-swatches', Saito_Swatches_Frontend::mobile_layout_css( '.saito-preview[data-device="mobile"] .saito-swatches.saito-swatches' ) );
		wp_enqueue_style( 'saito-swatches-admin', SAITO_SWATCHES_URL . 'assets/css/admin.css', array( 'saito-swatches', 'wp-color-picker' ), saito_swatches_asset_version( 'assets/css/admin.css' ) );

		wp_enqueue_script( 'saito-swatches-admin', SAITO_SWATCHES_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), saito_swatches_asset_version( 'assets/js/admin.js' ), true );

		$front = Saito_Swatches_Frontend::script_data( $settings );

		wp_localize_script(
			'saito-swatches-admin',
			'saitoSwatchesAdmin',
			array(
				'currency' => $front['currency'],
				'i18n'     => array(
					'chooseImage' => __( 'Choose a swatch image', 'saito-swatches' ),
					'useImage'    => __( 'Use this image', 'saito-swatches' ),
					'unsaved'     => __( 'Unsaved changes', 'saito-swatches' ),
					'sampleLabel' => __( 'Sample', 'saito-swatches' ),
					'outOfStock'  => __( 'Out of stock', 'saito-swatches' ),
				),
			)
		);
	}

	/**
	 * Tabs of the settings page.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'general'    => array( __( 'General', 'saito-swatches' ), 'dashicons-admin-generic' ),
			'attributes' => array( __( 'Attributes', 'saito-swatches' ), 'dashicons-tag' ),
			'appearance' => array( __( 'Appearance', 'saito-swatches' ), 'dashicons-admin-appearance' ),
			'texts'      => array( __( 'Texts', 'saito-swatches' ), 'dashicons-editor-textcolor' ),
			'advanced'   => array( __( 'Advanced', 'saito-swatches' ), 'dashicons-admin-tools' ),
		);
	}

	/**
	 * Field name for a setting key.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function name( $key ) {
		return Saito_Swatches_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Print one setting field.
	 *
	 * @param string $type  toggle|number|select|text|color.
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param array  $args  description, min, max, unit, options, placeholder.
	 */
	private static function field( $type, $key, $label, $args = array() ) {
		$settings = Saito_Swatches_Settings::get();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$id       = 'saito-swatches-' . str_replace( '_', '-', $key );
		$args     = wp_parse_args(
			$args,
			array(
				'description' => '',
				'min'         => 0,
				'max'         => '',
				'unit'        => '',
				'options'     => array(),
				'placeholder' => '',
			)
		);

		echo '<div class="saito-field saito-field--' . esc_attr( $type ) . '">';
		echo '<div class="saito-field__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $args['description'] ) {
			echo '<p class="saito-field__help">' . wp_kses( $args['description'], array( 'code' => array(), 'strong' => array() ) ) . '</p>';
		}
		echo '</div><div class="saito-field__control">';

		switch ( $type ) {
			case 'toggle':
				printf(
					'<label class="saito-toggle"><input type="checkbox" id="%1$s" name="%2$s" value="1" data-setting="%3$s" %4$s><span class="saito-toggle__track" aria-hidden="true"></span></label>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( $key ),
					checked( ! empty( $value ), true, false )
				);
				break;

			case 'number':
				printf(
					'<span class="saito-input-unit"><input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" data-setting="%6$s">%7$s</span>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( $value ),
					esc_attr( $args['min'] ),
					esc_attr( $args['max'] ),
					esc_attr( $key ),
					$args['unit'] ? '<span class="saito-input-unit__suffix">' . esc_html( $args['unit'] ) . '</span>' : ''
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s" data-setting="%3$s">', esc_attr( $id ), esc_attr( self::name( $key ) ), esc_attr( $key ) );
				foreach ( $args['options'] as $option_value => $option_label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option_value ), selected( (string) $value, (string) $option_value, false ), esc_html( $option_label ) );
				}
				echo '</select>';
				break;

			case 'color':
				printf(
					'<input type="text" class="saito-color" id="%1$s" name="%2$s" value="%3$s" data-setting="%4$s" data-default-color="%5$s">',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( $value ),
					esc_attr( $key ),
					esc_attr( Saito_Swatches_Settings::defaults()[ $key ] )
				);
				break;

			default:
				printf(
					'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" data-setting="%5$s">',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( $value ),
					esc_attr( $args['placeholder'] ),
					esc_attr( $key )
				);
		}

		echo '</div></div>';
	}

	/**
	 * Open a settings card.
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 */
	private static function card_open( $title, $description = '' ) {
		echo '<section class="saito-card">';
		echo '<header class="saito-card__header"><h2 class="saito-card__title">' . esc_html( $title ) . '</h2>';
		if ( $description ) {
			echo '<p class="saito-card__desc">' . esc_html( $description ) . '</p>';
		}
		echo '</header><div class="saito-card__body">';
	}

	/**
	 * Close a settings card.
	 */
	private static function card_close() {
		echo '</div></section>';
	}

	/**
	 * One term row of the Attributes tab (also the template for new terms).
	 *
	 * @param string       $base   Field name prefix.
	 * @param array        $visual image_id, color, icon.
	 * @param WP_Term|null $term   Existing term, or null for a new one.
	 */
	private static function render_term_row( $base, $visual, $term ) {
		$thumb = $visual['image_id'] ? wp_get_attachment_image_url( $visual['image_id'], 'thumbnail' ) : '';
		?>
		<tr class="saito-tv<?php echo $thumb ? ' has-image' : ''; ?><?php echo $term ? '' : ' saito-tv--new'; ?>">
			<td class="saito-tv__name">
				<?php if ( $term ) : ?>
					<strong><?php echo esc_html( $term->name ); ?></strong>
					<code><?php echo esc_html( $term->slug ); ?></code>
				<?php else : ?>
					<input type="text" name="<?php echo esc_attr( $base . '[name]' ); ?>" placeholder="<?php esc_attr_e( 'New value, e.g. 70 ml', 'saito-swatches' ); ?>" aria-label="<?php esc_attr_e( 'Name of the new value', 'saito-swatches' ); ?>">
				<?php endif; ?>
			</td>
			<td class="saito-tv__image-cell">
				<button type="button" class="saito-tv__thumb" aria-label="<?php esc_attr_e( 'Choose image', 'saito-swatches' ); ?>">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="">
					<?php else : ?>
						<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
					<?php endif; ?>
				</button>
				<input type="hidden" class="saito-tv__image" name="<?php echo esc_attr( $base . '[image]' ); ?>" value="<?php echo esc_attr( $visual['image_id'] ? $visual['image_id'] : '' ); ?>">
				<button type="button" class="button-link saito-tv__remove"><?php esc_html_e( 'Remove', 'saito-swatches' ); ?></button>
			</td>
			<td class="saito-tv__color-cell">
				<input type="text" class="saito-tv__color" name="<?php echo esc_attr( $base . '[color]' ); ?>" value="<?php echo esc_attr( $visual['color'] ); ?>" aria-label="<?php esc_attr_e( 'Swatch color', 'saito-swatches' ); ?>">
			</td>
			<td class="saito-tv__icon-cell">
				<span class="saito-tv__icon-preview dashicons<?php echo $visual['icon'] ? ' ' . esc_attr( 'dashicons-' . $visual['icon'] ) : ''; ?>" aria-hidden="true"></span>
				<select class="saito-tv__icon" name="<?php echo esc_attr( $base . '[icon]' ); ?>" aria-label="<?php esc_attr_e( 'Swatch icon', 'saito-swatches' ); ?>">
					<option value=""><?php esc_html_e( 'No icon', 'saito-swatches' ); ?></option>
					<?php foreach ( Saito_Swatches_Terms::icons() as $icon ) : ?>
						<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $visual['icon'], $icon ); ?>><?php echo esc_html( $icon ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="saito-tv__texts-cell">
				<input type="text" class="saito-tv__label" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $visual['label'] ); ?>" placeholder="<?php esc_attr_e( 'Displayed label', 'saito-swatches' ); ?>" aria-label="<?php esc_attr_e( 'Displayed label', 'saito-swatches' ); ?>">
				<input type="text" class="saito-tv__subtitle" name="<?php echo esc_attr( $base . '[subtitle]' ); ?>" value="<?php echo esc_attr( $visual['subtitle'] ); ?>" placeholder="<?php esc_attr_e( 'Subtitle', 'saito-swatches' ); ?>" aria-label="<?php esc_attr_e( 'Subtitle', 'saito-swatches' ); ?>">
				<input type="text" inputmode="decimal" class="saito-tv__quantity" name="<?php echo esc_attr( $base . '[quantity]' ); ?>" value="<?php echo esc_attr( $visual['quantity'] ); ?>" placeholder="<?php esc_attr_e( 'Quantity (unit price)', 'saito-swatches' ); ?>" aria-label="<?php esc_attr_e( 'Quantity (unit price)', 'saito-swatches' ); ?>">
			</td>
			<td class="saito-tv__actions">
				<?php if ( ! $term ) : ?>
					<button type="button" class="saito-icon-button saito-icon-button--danger saito-tv__delete" aria-label="<?php esc_attr_e( 'Delete this value', 'saito-swatches' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Attributes tab: swatch type per attribute, visuals per term, new terms.
	 */
	private static function render_attributes_panel() {
		$taxonomies = wc_get_attribute_taxonomies();

		if ( Saito_Swatches_Terms::wcboost_active() ) {
			echo '<div class="notice notice-warning inline saito-inline-notice"><p>' . esc_html__( 'WCBoost Variation Swatches is active. It changes the same WooCommerce dropdowns after Saito Swatches, so its swatches can replace these ones. Deactivate it to let Saito Swatches handle every attribute.', 'saito-swatches' ) . '</p></div>';
		}

		if ( ! $taxonomies ) {
			self::card_open( __( 'Swatches by attribute', 'saito-swatches' ) );
			echo '<p>' . esc_html__( 'No global attribute yet. Create one in Products → Attributes, then come back here.', 'saito-swatches' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ) . '">' . esc_html__( 'Open Products → Attributes', 'saito-swatches' ) . '</a></p>';
			self::card_close();
			return;
		}

		echo '<p class="saito-panel-intro">' . esc_html__( 'Everything about your swatches is here: how each attribute is displayed, the image, color or icon of each value, its optional texts, and new values. These are the same data as in Products → Attributes → Configure terms. Values are shown in the order set in WooCommerce.', 'saito-swatches' ) . '</p>';

		foreach ( $taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$label = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 200,
				)
			);
			$terms = is_wp_error( $terms ) ? array() : $terms;
			$base  = 'saito_swatches_terms[' . $taxonomy . ']';
			$type  = Saito_Swatches_Terms::type( $taxonomy );
			?>
			<section class="saito-card saito-attr" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
				<header class="saito-card__header saito-attr__header">
					<div>
						<h2 class="saito-card__title"><?php echo esc_html( $label ); ?> <code class="saito-attr__slug"><?php echo esc_html( $taxonomy ); ?></code></h2>
						<p class="saito-card__desc">
							<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=product' ) ); ?>"><?php esc_html_e( 'Configure terms in WooCommerce', 'saito-swatches' ); ?></a>
						</p>
					</div>
					<label class="saito-attr__type">
						<span><?php esc_html_e( 'Swatch type', 'saito-swatches' ); ?></span>
						<select name="<?php echo esc_attr( Saito_Swatches_Terms::OPTION_TYPES . '[' . $taxonomy . ']' ); ?>">
							<?php foreach ( Saito_Swatches_Terms::types() as $value => $type_label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</header>
				<div class="saito-card__body">
					<?php if ( ! empty( $tax->attribute_type ) && 'select' !== $tax->attribute_type ) : ?>
						<p class="saito-attr__warning">
							<?php
							/* translators: %s: WooCommerce attribute type, e.g. "color". */
							echo esc_html( sprintf( __( 'WooCommerce type of this attribute: "%s" (set by another swatches plugin). Set it back to "Select" in Products → Attributes if that plugin is no longer used.', 'saito-swatches' ), $tax->attribute_type ) );
							?>
						</p>
					<?php endif; ?>
					<table class="saito-tv-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Value', 'saito-swatches' ); ?></th>
								<th><?php esc_html_e( 'Image', 'saito-swatches' ); ?></th>
								<th><?php esc_html_e( 'Color', 'saito-swatches' ); ?></th>
								<th><?php esc_html_e( 'Icon', 'saito-swatches' ); ?></th>
								<th><?php esc_html_e( 'Texts (optional)', 'saito-swatches' ); ?></th>
								<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'saito-swatches' ); ?></span></th>
							</tr>
						</thead>
						<tbody class="saito-tv-body">
							<?php
							foreach ( $terms as $term ) {
								self::render_term_row( $base . '[existing][' . $term->term_id . ']', Saito_Swatches_Terms::visual( $term->term_id ), $term );
							}
							?>
						</tbody>
					</table>
					<p>
						<button type="button" class="button saito-attr__add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add a value to this attribute', 'saito-swatches' ); ?></button>
					</p>
					<template class="saito-attr__template">
						<?php
						self::render_term_row(
							$base . '[new][__N__]',
							Saito_Swatches_Terms::empty_visual(),
							null
						);
						?>
					</template>
				</div>
			</section>
			<?php
		}
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$settings = Saito_Swatches_Settings::get();
		$tabs     = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects the visible tab.
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}
		?>
		<div class="wrap saito-admin">
			<header class="saito-header">
				<div class="saito-header__brand">
					<span class="saito-header__logo" aria-hidden="true">
						<svg viewBox="0 0 20 20" width="22" height="22"><path fill="currentColor" d="M2.5 11h3.5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H2.5a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1zm6-3h3.5a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm6-5H18a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-3.5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/></svg>
					</span>
					<div>
						<h1 class="saito-header__title"><?php esc_html_e( 'Saito Swatches', 'saito-swatches' ); ?> <span class="saito-badge"><?php echo esc_html( 'v' . SAITO_SWATCHES_VERSION ); ?></span></h1>
						<p class="saito-header__subtitle"><?php esc_html_e( 'Image swatches and unit price for WooCommerce variations.', 'saito-swatches' ); ?></p>
					</div>
				</div>
				<nav class="saito-header__links">
					<a href="https://wordpress.org/plugins/saito-swatches/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'saito-swatches' ); ?></a>
					<a href="https://wordpress.org/support/plugin/saito-swatches/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'saito-swatches' ); ?></a>
				</nav>
			</header>
			<hr class="wp-header-end">

			<?php settings_errors(); ?>

			<nav class="saito-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'saito-swatches' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a href="<?php echo esc_url( self::page_url( $slug ) ); ?>" class="saito-tab<?php echo $slug === $current ? ' is-active' : ''; ?>" role="tab" id="saito-tab-<?php echo esc_attr( $slug ); ?>" aria-controls="saito-panel-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $slug === $current ? 'true' : 'false'; ?>" data-tab="<?php echo esc_attr( $slug ); ?>">
						<span class="dashicons <?php echo esc_attr( $tab[1] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $tab[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="saito-form" id="saito-swatches-form">
				<?php settings_fields( self::GROUP ); ?>

				<div class="saito-layout">
					<div class="saito-main">

						<?php /* General. */ ?>
						<div class="saito-panel" role="tabpanel" id="saito-panel-general" aria-labelledby="saito-tab-general"<?php echo 'general' === $current ? '' : ' hidden'; ?>>
							<?php if ( ! Saito_Swatches_Terms::all_visuals() ) : ?>
								<section class="saito-card saito-card--highlight">
									<header class="saito-card__header">
										<h2 class="saito-card__title"><?php esc_html_e( 'First steps', 'saito-swatches' ); ?></h2>
										<p class="saito-card__desc"><?php esc_html_e( 'Three steps and your variation dropdowns become image swatches.', 'saito-swatches' ); ?></p>
									</header>
									<ol class="saito-steps">
										<li><strong><?php esc_html_e( 'Choose a swatch type', 'saito-swatches' ); ?></strong><span><?php esc_html_e( 'In the Attributes tab, pick image, color, button or icon for each attribute.', 'saito-swatches' ); ?></span></li>
										<li><strong><?php esc_html_e( 'Give each value its visual', 'saito-swatches' ); ?></strong><span><?php esc_html_e( 'Still in the Attributes tab: an image from your media library, a color or an icon. You can add new values there too.', 'saito-swatches' ); ?></span></li>
										<li><strong><?php esc_html_e( 'Save and open a variable product', 'saito-swatches' ); ?></strong><span><?php esc_html_e( 'Attributes with at least one configured value show swatches; the others keep their dropdown.', 'saito-swatches' ); ?></span></li>
									</ol>
									<p><a href="<?php echo esc_url( self::page_url( 'attributes' ) ); ?>" class="button button-primary saito-goto" data-tab="attributes"><?php esc_html_e( 'Open the Attributes tab', 'saito-swatches' ); ?></a></p>
								</section>
							<?php endif; ?>

							<?php
							self::card_open( __( 'Activation', 'saito-swatches' ) );
							self::field( 'toggle', 'enabled', __( 'Enable swatches', 'saito-swatches' ), array( 'description' => __( 'Turn off to get the standard WooCommerce dropdowns back without losing your settings.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Variation form', 'saito-swatches' ), __( 'How the WooCommerce form around the swatches behaves.', 'saito-swatches' ) );
							self::field( 'toggle', 'hide_label', __( 'Hide the attribute name', 'saito-swatches' ), array( 'description' => __( 'The label shown before the swatches (e.g. "Size").', 'saito-swatches' ) ) );
							self::field( 'toggle', 'hide_reset', __( 'Hide the "Clear" link', 'saito-swatches' ) );
							self::field( 'number', 'reserve_height', __( 'Reserved height under the swatches', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 1000, 'description' => __( 'Space kept for the price and the add-to-cart button, which WooCommerce loads after the choice. Prevents layout shifts (CLS). 0 to disable.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Unit price', 'saito-swatches' ), __( 'Shows a price per reference quantity under the selected variation (e.g. per 100 ml). The text is editable in the Texts tab.', 'saito-swatches' ) );
							self::field( 'toggle', 'price_per_unit', __( 'Show the unit price', 'saito-swatches' ) );
							self::field( 'number', 'unit_reference', __( 'Reference quantity', 'saito-swatches' ), array( 'min' => 1, 'max' => 100000, 'description' => __( '100 gives a price per 100 ml if your values are in ml. The quantity of each value is read from its "Quantity" field, or from the first number in its name.', 'saito-swatches' ) ) );
							self::field(
								'select',
								'unit_position',
								__( 'Position', 'saito-swatches' ),
								array(
									'options' => array(
										'after_cart' => __( 'Under the add-to-cart button', 'saito-swatches' ),
										'in_price'   => __( 'Next to the variation price', 'saito-swatches' ),
									),
								)
							);
							self::field( 'toggle', 'unit_simple', __( 'Also on simple products', 'saito-swatches' ), array( 'description' => __( 'Uses the capacity set in the "Saito Swatches" tab of each product, or the field set in the Advanced tab (e.g. an ACF field).', 'saito-swatches' ) ) );
							self::field( 'toggle', 'unit_loop', __( 'Also on shop and category pages', 'saito-swatches' ), array( 'description' => __( 'For simple products, next to their price in product lists.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Prices and shop pages', 'saito-swatches' ) );
							self::field( 'toggle', 'show_price', __( 'Show the price in each swatch', 'saito-swatches' ), array( 'description' => __( 'The lowest price of the variations with this value. Each product can change this in its "Saito Swatches" tab.', 'saito-swatches' ) ) );
							self::field( 'toggle', 'button_price', __( 'Show the price in the add-to-cart button', 'saito-swatches' ), array( 'description' => __( 'On product pages, e.g. "Add to cart – 34,50 €". For variable products, the price of the selected variation. Works with or without swatches.', 'saito-swatches' ) ) );
							self::field( 'text', 'button_template', __( 'Add-to-cart button text', 'saito-swatches' ), array( 'placeholder' => '{text} – {price}', 'description' => __( '<code>{text}</code> is the usual button text, <code>{price}</code> the price. Anywhere on a product page, the <code>[saito_swatches_price]</code> shortcode also shows the price of the selected variation.', 'saito-swatches' ) ) );
							self::field( 'toggle', 'hover_image', __( 'Show the second image on hover in product lists', 'saito-swatches' ), array( 'description' => __( 'On shop, category and block product grids, hovering a product shows the first image of its gallery. Large screens with a mouse only.', 'saito-swatches' ) ) );
							self::field( 'toggle', 'loop_swatches', __( 'Show swatches on shop and category pages', 'saito-swatches' ), array( 'description' => __( 'Small swatches under the product price. Hovering one shows the variation image; clicking opens the product with this value selected.', 'saito-swatches' ) ) );
							self::field( 'number', 'loop_limit', __( 'Swatches shown per product', 'saito-swatches' ), array( 'min' => 1, 'max' => 30, 'description' => __( 'The others are summed up as "+3".', 'saito-swatches' ) ) );
							self::field( 'number', 'loop_size', __( 'Size of shop page swatches', 'saito-swatches' ), array( 'min' => 12, 'max' => 80, 'unit' => 'px' ) );
							self::field(
								'select',
								'loop_block_position',
								__( 'Position in block product grids', 'saito-swatches' ),
								array(
									'options'     => array(
										'after_price' => __( 'After the price', 'saito-swatches' ),
										'after_title' => __( 'After the product name', 'saito-swatches' ),
										'after_image' => __( 'After the image', 'saito-swatches' ),
									),
									'description' => __( 'For shop and category pages built with WooCommerce blocks (Product Collection). Classic themes show the swatches under the price.', 'saito-swatches' ),
								)
							);
							self::card_close();
							?>
						</div>

						<?php /* Attributes. */ ?>
						<div class="saito-panel" role="tabpanel" id="saito-panel-attributes" aria-labelledby="saito-tab-attributes"<?php echo 'attributes' === $current ? '' : ' hidden'; ?>>
							<?php self::render_attributes_panel(); ?>
						</div>

						<?php /* Appearance. */ ?>
						<div class="saito-panel" role="tabpanel" id="saito-panel-appearance" aria-labelledby="saito-tab-appearance"<?php echo 'appearance' === $current ? '' : ' hidden'; ?>>
							<?php
							self::card_open( __( 'Layout', 'saito-swatches' ) );
							self::field(
								'select',
								'layout',
								__( 'Swatches layout', 'saito-swatches' ),
								array(
									'options' => array(
										'row'   => __( 'In a row', 'saito-swatches' ),
										'grid'  => __( 'Grid', 'saito-swatches' ),
										'stack' => __( 'Stacked', 'saito-swatches' ),
									),
								)
							);
							self::field(
								'select',
								'content_layout',
								__( 'Image and text', 'saito-swatches' ),
								array(
									'options' => array(
										'column' => __( 'Image above the text', 'saito-swatches' ),
										'row'    => __( 'Image beside the text', 'saito-swatches' ),
									),
								)
							);
							self::field(
								'select',
								'align',
								__( 'Alignment', 'saito-swatches' ),
								array(
									'options'     => array(
										'start'  => __( 'Left', 'saito-swatches' ),
										'center' => __( 'Center', 'saito-swatches' ),
										'end'    => __( 'Right', 'saito-swatches' ),
									),
									'description' => __( 'Used by the "In a row" layout.', 'saito-swatches' ),
								)
							);
							self::field( 'number', 'gap', __( 'Space between swatches', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 200 ) );
							self::card_close();

							self::card_open( __( 'Swatch', 'saito-swatches' ) );
							self::field( 'number', 'padding', __( 'Inner spacing', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 200 ) );
							self::field( 'number', 'border_width', __( 'Border width', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 20 ) );
							self::field( 'number', 'radius', __( 'Corner radius', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 200 ) );
							$sizes = array();
							foreach ( get_intermediate_image_sizes() as $size ) {
								$sizes[ $size ] = $size;
							}
							$sizes['full'] = __( 'Full size', 'saito-swatches' );
							self::field( 'select', 'image_size', __( 'Image file size', 'saito-swatches' ), array( 'options' => $sizes, 'description' => __( 'The file loaded by the browser. Pick the smallest one that stays sharp.', 'saito-swatches' ) ) );
							self::field( 'number', 'image_height', __( 'Displayed image height', 'saito-swatches' ), array( 'min' => 10, 'max' => 600, 'unit' => 'px' ) );
							self::field( 'number', 'dot_size', __( 'Color dot and icon size', 'saito-swatches' ), array( 'min' => 8, 'max' => 200, 'unit' => 'px' ) );
							self::field( 'select', 'dot_shape', __( 'Shape of dots and images', 'saito-swatches' ), array( 'options' => Saito_Swatches_Product::shapes() ) );
							self::field( 'number', 'inactive_opacity', __( 'Opacity of non-selected swatches', 'saito-swatches' ), array( 'max' => 100, 'unit' => '%' ) );
							self::field(
								'select',
								'unavailable_style',
								__( 'Unavailable combinations', 'saito-swatches' ),
								array(
									'options' => array(
										'fade'   => __( 'Faded', 'saito-swatches' ),
										'strike' => __( 'Faded and struck through', 'saito-swatches' ),
										'hide'   => __( 'Hidden', 'saito-swatches' ),
									),
								)
							);
							self::card_close();

							self::card_open( __( 'Out of stock badge', 'saito-swatches' ), __( 'A small badge on values whose variations are all out of stock. Its text is set in the Texts tab.', 'saito-swatches' ) );
							self::field( 'toggle', 'stock_badge', __( 'Show the badge on out-of-stock values', 'saito-swatches' ), array( 'description' => __( 'The value stays clickable, so customers can still see its price and stock message. Shop page swatches are faded and struck through.', 'saito-swatches' ) ) );
							self::field(
								'select',
								'stock_badge_position',
								__( 'Badge position', 'saito-swatches' ),
								array(
									'options' => array(
										'corner' => __( 'In the top corner of the swatch', 'saito-swatches' ),
										'below'  => __( 'Under the text', 'saito-swatches' ),
									),
								)
							);
							self::field( 'color', 'color_badge', __( 'Badge color', 'saito-swatches' ), array( 'description' => __( 'Empty: dark grey. The text is white.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Typography', 'saito-swatches' ), __( 'Sizes of the texts inside a swatch. 0 keeps a size relative to your theme font.', 'saito-swatches' ) );
							self::field( 'number', 'label_size', __( 'Value name', 'saito-swatches' ), array( 'max' => 60, 'unit' => 'px' ) );
							self::field( 'number', 'subtitle_size', __( 'Subtitle', 'saito-swatches' ), array( 'max' => 60, 'unit' => 'px' ) );
							self::field( 'number', 'price_size', __( 'Price', 'saito-swatches' ), array( 'max' => 60, 'unit' => 'px' ) );
							self::field( 'color', 'color_subtitle', __( 'Subtitle color', 'saito-swatches' ), array( 'description' => __( 'Empty: the text color, lighter.', 'saito-swatches' ) ) );
							self::field( 'color', 'color_price', __( 'Price color', 'saito-swatches' ) );
							self::card_close();

							self::card_open( __( 'Colors', 'saito-swatches' ), __( 'Leave a color empty to use the one from your theme.', 'saito-swatches' ) );
							self::field( 'color', 'color_border', __( 'Border', 'saito-swatches' ) );
							self::field( 'color', 'color_border_active', __( 'Border when selected', 'saito-swatches' ) );
							self::field( 'color', 'color_bg', __( 'Background', 'saito-swatches' ) );
							self::field( 'color', 'color_bg_active', __( 'Background when selected', 'saito-swatches' ) );
							self::field( 'color', 'color_text', __( 'Text', 'saito-swatches' ) );
							self::card_close();

							self::card_open( __( 'Mobile', 'saito-swatches' ) );
							self::field( 'number', 'mobile_breakpoint', __( 'Mobile below', 'saito-swatches' ), array( 'max' => 3000, 'unit' => 'px', 'description' => __( 'Screen width under which the mobile settings apply. 0 to disable.', 'saito-swatches' ) ) );
							self::field(
								'select',
								'mobile_layout',
								__( 'Mobile layout', 'saito-swatches' ),
								array(
									'options' => array(
										'stack' => __( 'Stacked, full width', 'saito-swatches' ),
										'row'   => __( 'In a row', 'saito-swatches' ),
										'grid'  => __( 'Two columns', 'saito-swatches' ),
									),
								)
							);
							self::field( 'number', 'mobile_padding', __( 'Mobile inner spacing', 'saito-swatches' ), array( 'unit' => 'px', 'max' => 200 ) );
							self::card_close();
							?>
						</div>

						<?php /* Texts. */ ?>
						<div class="saito-panel" role="tabpanel" id="saito-panel-texts" aria-labelledby="saito-tab-texts"<?php echo 'texts' === $current ? '' : ' hidden'; ?>>
							<?php
							self::card_open( __( 'Texts shown to customers', 'saito-swatches' ), __( 'Every text of the swatches can be changed here. Leave a field empty to hide that text.', 'saito-swatches' ) );
							self::field( 'text', 'subtitle', __( 'Subtitle', 'saito-swatches' ), array( 'placeholder' => __( 'e.g. Eau de parfum', 'saito-swatches' ), 'description' => __( 'Shown under each value. A value can have its own subtitle in the Attributes tab.', 'saito-swatches' ) ) );
							self::field( 'text', 'unit_template', __( 'Unit price text', 'saito-swatches' ), array( 'placeholder' => '{price} / 100 ml', 'description' => __( '<code>{price}</code> is replaced by the price, formatted with your WooCommerce currency settings.', 'saito-swatches' ) ) );
							self::field( 'text', 'alt_template', __( 'Image alternative text', 'saito-swatches' ), array( 'placeholder' => '{label}', 'description' => __( 'Read by screen readers and search engines. <code>{label}</code> is replaced by the value name, e.g. "Bottle {label}".', 'saito-swatches' ) ) );
							self::field( 'text', 'unavailable_text', __( 'Unavailable tooltip', 'saito-swatches' ), array( 'placeholder' => __( 'e.g. Out of stock', 'saito-swatches' ), 'description' => __( 'Shown when hovering a value that cannot be selected.', 'saito-swatches' ) ) );
							self::field( 'text', 'stock_badge_text', __( 'Out of stock badge', 'saito-swatches' ), array( 'placeholder' => __( 'Out of stock', 'saito-swatches' ), 'description' => __( 'Shown on values whose variations are all out of stock, e.g. "Sold out" or "Back soon". Empty uses the default text. The badge can be turned off in the Appearance tab.', 'saito-swatches' ) ) );
							self::field( 'text', 'reset_text', __( '"Clear" link text', 'saito-swatches' ), array( 'placeholder' => __( 'Clear', 'saito-swatches' ), 'description' => __( 'Used when the link is not hidden in the General tab. Empty keeps the WooCommerce text.', 'saito-swatches' ) ) );
							self::card_close();
							?>
						</div>

						<?php /* Advanced. */ ?>
						<div class="saito-panel" role="tabpanel" id="saito-panel-advanced" aria-labelledby="saito-tab-advanced"<?php echo 'advanced' === $current ? '' : ' hidden'; ?>>
							<?php
							self::card_open( __( 'Theme compatibility', 'saito-swatches' ) );
							self::field( 'text', 'extra_classes', __( 'Extra CSS classes', 'saito-swatches' ), array( 'placeholder' => 'my-class other-class', 'description' => __( 'Added to every swatch, to reuse styles from your theme (e.g. a font class).', 'saito-swatches' ) ) );
							self::field( 'text', 'hide_selectors', __( 'Elements to hide', 'saito-swatches' ), array( 'placeholder' => '.price-per-unit, .my-old-price', 'description' => __( 'CSS selectors, separated by commas, hidden on product pages that show swatches (e.g. a unit price already added by your theme).', 'saito-swatches' ) ) );
							self::field( 'toggle', 'load_everywhere', __( 'Load on every page', 'saito-swatches' ), array( 'description' => __( 'Needed only if variation forms appear outside product pages (quick view, shop page).', 'saito-swatches' ) ) );
							self::field( 'text', 'capacity_meta', __( 'Capacity field of products', 'saito-swatches' ), array( 'placeholder' => 'product_capacity', 'description' => __( 'Meta key read for the capacity of a product when its "Saito Swatches" tab has none (e.g. the name of an ACF field). Empty to disable.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Saito Navi', 'saito-swatches' ) );
							self::field( 'toggle', 'navi_bridge', __( 'Share images with Saito Navi', 'saito-swatches' ), array( 'description' => __( 'Shows the images and colors of your values in the swatches of the Saito Navi sticky add-to-cart.', 'saito-swatches' ) ) );
							self::card_close();

							self::card_open( __( 'Data', 'saito-swatches' ) );
							self::field( 'toggle', 'keep_data', __( 'Keep settings when deleting the plugin', 'saito-swatches' ) );
							self::card_close();

							self::card_open( __( 'For developers', 'saito-swatches' ) );
							?>
							<ul class="saito-hooks">
								<li><code>saito_swatches_settings</code> — <?php esc_html_e( 'filter the settings at runtime.', 'saito-swatches' ); ?></li>
								<li><code>saito_swatches_load_assets</code> — <?php esc_html_e( 'load the scripts on more pages.', 'saito-swatches' ); ?></li>
								<li><code>saito_swatches_parent_menu</code> — <?php esc_html_e( 'change the admin menu this page belongs to.', 'saito-swatches' ); ?></li>
								<li><code>saito_swatches_bridge_images</code> — <?php esc_html_e( 'change the images sent to Saito Navi.', 'saito-swatches' ); ?></li>
								<li><code>saito_swatches_get_image_url( $value, $size )</code> — <?php esc_html_e( 'get the image of a value from another plugin.', 'saito-swatches' ); ?></li>
							</ul>
							<?php
							self::card_close();
							?>
						</div>
					</div>

					<aside class="saito-aside">
						<section class="saito-card saito-preview" data-device="desktop">
							<header class="saito-card__header saito-preview__header">
								<h2 class="saito-card__title"><?php esc_html_e( 'Live preview', 'saito-swatches' ); ?></h2>
								<div class="saito-segmented" role="group" aria-label="<?php esc_attr_e( 'Preview device', 'saito-swatches' ); ?>">
									<button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Desktop', 'saito-swatches' ); ?></span></button>
									<button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Mobile', 'saito-swatches' ); ?></span></button>
								</div>
							</header>
							<div class="saito-preview__stage">
								<div class="saito-preview__canvas"></div>
							</div>
							<p class="saito-preview__note"><?php esc_html_e( 'Approximate rendering: fonts and some styles come from your theme.', 'saito-swatches' ); ?></p>
						</section>
					</aside>
				</div>

				<div class="saito-savebar">
					<span class="saito-savebar__status" aria-live="polite"></span>
					<?php submit_button( __( 'Save changes', 'saito-swatches' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
