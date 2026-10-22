<?php
/**
 * Minimal WordPress functions to run the tests without WordPress
 * (the tested files only declare classes and functions).
 */

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}
function add_filter() {}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

$GLOBALS['saito_swatches_failures'] = 0;

function saito_swatches_assert( $label, $expected, $actual ) {
	if ( $expected !== $actual ) {
		$GLOBALS['saito_swatches_failures']++;
		fwrite( STDERR, sprintf( "FAIL: %s (expected %s, got %s)\n", $label, var_export( $expected, true ), var_export( $actual, true ) ) );
		return;
	}
	echo "OK: {$label}\n";
}

function saito_swatches_tests_done() {
	if ( $GLOBALS['saito_swatches_failures'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['saito_swatches_failures']} failure(s).\n" );
		exit( 1 );
	}
	echo "\nAll tests passed.\n";
}
