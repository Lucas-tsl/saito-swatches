<?php
/**
 * Quantity of a value for the unit price (Saito_Swatches_Terms::sanitize_quantity()).
 * Run by CI: `php tests/test-quantity.php`.
 */

require __DIR__ . '/bootstrap-stubs.php';
require __DIR__ . '/../includes/class-saito-swatches-terms.php';

saito_swatches_assert( 'integer', '30', Saito_Swatches_Terms::sanitize_quantity( '30' ) );
saito_swatches_assert( 'decimal comma', '15.5', Saito_Swatches_Terms::sanitize_quantity( '15,5' ) );
saito_swatches_assert( 'decimal point', '0.75', Saito_Swatches_Terms::sanitize_quantity( '0.75' ) );
saito_swatches_assert( 'spaces trimmed', '100', Saito_Swatches_Terms::sanitize_quantity( ' 100 ' ) );
saito_swatches_assert( 'zero refused', '', Saito_Swatches_Terms::sanitize_quantity( '0' ) );
saito_swatches_assert( 'negative refused', '', Saito_Swatches_Terms::sanitize_quantity( '-5' ) );
saito_swatches_assert( 'text refused', '', Saito_Swatches_Terms::sanitize_quantity( '30 ml' ) );
saito_swatches_assert( 'empty', '', Saito_Swatches_Terms::sanitize_quantity( '' ) );

saito_swatches_tests_done();
