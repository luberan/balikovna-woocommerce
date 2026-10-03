<?php
/**
 * Remove duplicate release-please commit entries from CHANGELOG.md.
 *
 * An entry is kept in its oldest listed release because that is the first
 * version that could have contained it. Entries are identified by commit and
 * text, because nested commits list several entries for one commit.
 * Empty release-loop sections are dropped.
 *
 * Usage: php .github/scripts/normalize-changelog.php [CHANGELOG.md]
 */

declare( strict_types=1 );

/**
 * Key of a changelog bullet, or null for lines that are not commit entries.
 */
function changelogEntryKey( string $line ): ?string {
	if ( ! preg_match( '/^\* /', $line ) || ! preg_match( '~/commit/([0-9a-f]{7,40})~', $line, $commit ) ) {
		return null;
	}
	return $commit[1] . ' ' . trim( $line );
}

$path = $argv[1] ?? dirname( __DIR__, 2 ) . '/CHANGELOG.md';
if ( ! is_file( $path ) ) {
	fwrite( STDERR, 'Changelog not found: ' . $path . PHP_EOL );
	exit( 1 );
}

$contents = str_replace( array( "\r\n", "\r" ), "\n", (string) file_get_contents( $path ) );
preg_match_all( '/^## \[?\d+\.\d+\.\d+\]?.*?(?=^## \[?\d+\.\d+\.\d+\]?|\z)/ms', $contents, $sectionMatches );
$sections = $sectionMatches[0];

$remaining = array();
foreach ( $sections as $section ) {
	foreach ( explode( "\n", $section ) as $line ) {
		$key = changelogEntryKey( $line );
		if ( null !== $key ) {
			$remaining[ $key ] = ( $remaining[ $key ] ?? 0 ) + 1;
		}
	}
}

$rendered = array();
foreach ( $sections as $section ) {
	$lines  = explode( "\n", trim( $section ) );
	$header = array_shift( $lines );
	$groups = array();
	$title  = '';

	foreach ( $lines as $line ) {
		if ( preg_match( '/^### (.+)$/', $line, $heading ) ) {
			$title = trim( $heading[1] );
			continue;
		}
		if ( ! preg_match( '/^\* /', $line ) ) {
			continue;
		}

		$keep = true;
		$key  = changelogEntryKey( $line );
		if ( null !== $key ) {
			--$remaining[ $key ];
			$keep = 0 === $remaining[ $key ];
		}
		if ( $keep ) {
			$groups[ $title ][] = $line;
		}
	}

	$groups = array_filter( $groups );
	if ( ! $groups ) {
		continue;
	}

	$output = array( $header, '' );
	foreach ( $groups as $groupTitle => $bullets ) {
		if ( '' !== $groupTitle ) {
			$output[] = '### ' . $groupTitle;
			$output[] = '';
		}
		foreach ( $bullets as $bullet ) {
			$output[] = $bullet;
		}
		$output[] = '';
	}
	$rendered[] = rtrim( implode( "\n", $output ) );
}

$normalized = "# Changelog\n\n" . implode( "\n\n", $rendered ) . "\n";
if ( false === file_put_contents( $path, $normalized ) ) {
	fwrite( STDERR, 'Unable to write changelog: ' . $path . PHP_EOL );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Normalized %s to %d non-empty releases.\n", $path, count( $rendered ) ) );
