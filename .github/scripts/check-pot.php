<?php
/**
 * Compare the translatable messages in two POT files while ignoring headers.
 *
 * Usage: php .github/scripts/check-pot.php committed.pot generated.pot
 */

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use Gettext\Translations;

if ( 3 !== $argc ) {
	fwrite( STDERR, "Usage: check-pot.php committed.pot generated.pot\n" );
	exit( 2 );
}

function messages( string $path ): array {
	if ( ! is_file( $path ) ) {
		throw new RuntimeException( 'POT file not found: ' . $path );
	}

	$translations = Translations::fromPoFile( $path );
	$messages     = array();
	foreach ( $translations as $translation ) {
		$original = $translation->getOriginal();
		if ( '' === $original ) {
			continue;
		}
		$messages[] = json_encode(
			array(
				$translation->getContext(),
				$original,
				$translation->getPlural(),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}
	sort( $messages, SORT_STRING );
	return $messages;
}

try {
	$committed = messages( $argv[1] );
	$generated = messages( $argv[2] );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}

if ( ! $committed ) {
	fwrite( STDERR, "Committed POT contains no translatable messages.\n" );
	exit( 1 );
}

$missing = array_values( array_diff( $generated, $committed ) );
$stale   = array_values( array_diff( $committed, $generated ) );
if ( $missing || $stale ) {
	foreach ( $missing as $message ) {
		fwrite( STDERR, 'Missing from committed POT: ' . $message . PHP_EOL );
	}
	foreach ( $stale as $message ) {
		fwrite( STDERR, 'Stale in committed POT: ' . $message . PHP_EOL );
	}
	exit( 1 );
}

fwrite( STDOUT, sprintf( "POT messages match (%d entries).\n", count( $committed ) ) );
