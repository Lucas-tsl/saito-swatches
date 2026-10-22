<?php
/**
 * Uninstall: remove plugin data unless the user chose to keep it.
 *
 * @package Saito_Swatches
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Shared settings of Saito > Tools: removed only with the last Saito plugin.
require __DIR__ . '/includes/saito-hub/uninstall.php';

$saito_swatches_settings = get_option( 'saito_swatches_settings', array() );

if ( empty( $saito_swatches_settings['keep_data'] ) ) {
	delete_option( 'saito_swatches_settings' );
	delete_option( 'saito_swatches_attribute_types' );
	delete_option( 'saito_swatches_terms' );
	delete_option( 'saito_swatches_schema' );
	delete_post_meta_by_key( '_saito_swatches' );
	foreach ( array( 'saito_swatches_image', 'saito_swatches_color', 'saito_swatches_icon', 'saito_swatches_label', 'saito_swatches_subtitle', 'saito_swatches_quantity' ) as $saito_swatches_meta_key ) {
		delete_metadata( 'term', 0, $saito_swatches_meta_key, '', true );
	}
}
