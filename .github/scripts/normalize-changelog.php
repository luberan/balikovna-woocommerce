<?php
/**
 * Remove duplicate release-please commit entries from CHANGELOG.md.
 *
 * A commit is kept in its oldest listed release because that is the first
 * version that could have contained it. Empty release-loop sections are dropped.
 *
 * Usage: php .github/scripts/normalize-changelog.php [CHANGELOG.md]
 */

declare( strict_types=1 );

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
	preg_match_all( '~/commit/([0-9a-f]{7,40})~', $section, $commits );
	foreach ( $commits[1] as $commit ) {
		$remaining[ $commit ] = ( $remaining[ $commit ] ?? 0 ) + 1;
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
		if ( preg_match( '~/commit/([0-9a-f]{7,40})~', $line, $commitMatch ) ) {
			$commit = $commitMatch[1];
			--$remaining[ $commit ];
			$keep = 0 === $remaining[ $commit ];
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
