<?php
/**
 * Add-to-cart button text with the price (Saito_Swatches_Shop::button_text()).
 * Run by CI: `php tests/test-shop.php`.
 */

require __DIR__ . '/bootstrap-stubs.php';
require __DIR__ . '/../includes/class-saito-swatches-shop.php';

saito_swatches_assert( 'default template', 'Add to cart – 34,50 €', Saito_Swatches_Shop::button_text( '', 'Add to cart', '34,50 €' ) );
saito_swatches_assert( 'custom template', 'Je le veux (34,50 €)', Saito_Swatches_Shop::button_text( 'Je le veux ({price})', 'Add to cart', '34,50 €' ) );
saito_swatches_assert( 'template without {price}: price added at the end', 'Commander 34,50 €', Saito_Swatches_Shop::button_text( 'Commander', 'Add to cart', '34,50 €' ) );
saito_swatches_assert( 'blank template uses the default', 'Ajouter – 9 €', Saito_Swatches_Shop::button_text( '   ', 'Ajouter', '9 €' ) );
saito_swatches_assert( 'price first', '12 € · Add to cart', Saito_Swatches_Shop::button_text( '{price} · {text}', 'Add to cart', '12 €' ) );

saito_swatches_assert( 'toolkit: button and hover on', array( 'button_price' => 1, 'hover_image' => 1 ), Saito_Swatches_Shop::map_toolkit_settings( array( 'saito_active_button' => '1', 'saito_hover_product_enabled' => '1' ) ) );
saito_swatches_assert( 'toolkit: off or missing', array(), Saito_Swatches_Shop::map_toolkit_settings( array( 'saito_active_button' => '0', 'saito_hover_product_enabled' => false ) ) );

saito_swatches_tests_done();
