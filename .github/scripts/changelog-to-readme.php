<?php
/**
 * Replace the WordPress readme changelog with entries from CHANGELOG.md.
 *
 * Usage: php .github/scripts/changelog-to-readme.php CHANGELOG.md readme.txt
 */

declare( strict_types=1 );

if ( 3 !== $argc ) {
	fwrite( STDERR, "Usage: changelog-to-readme.php CHANGELOG.md readme.txt\n" );
	exit( 2 );
}

$changelog = file_get_contents( $argv[1] );
$readme    = file_get_contents( $argv[2] );
if ( false === $changelog || false === $readme ) {
	fwrite( STDERR, "Unable to read changelog or readme.\n" );
	exit( 1 );
}

preg_match_all( '/^## \[?(\d+\.\d+\.\d+)\]?.*?(?=^## \[?\d+\.\d+\.\d+\]?|\z)/ms', $changelog, $sections, PREG_SET_ORDER );
if ( ! $sections ) {
	fwrite( STDERR, "No changelog versions found.\n" );
	exit( 1 );
}

$output = array( '== Changelog ==', '' );
foreach ( $sections as $section ) {
	$output[] = '= ' . $section[1] . ' =';
	$title    = '';
	$bullets  = 0;
	foreach ( explode( "\n", $section[0] ) as $line ) {
		if ( preg_match( '/^### (.+)$/', $line, $heading ) ) {
			$title = trim( $heading[1] );
			continue;
		}
		if ( ! preg_match( '/^\* (.+)$/', $line, $bullet ) ) {
			continue;
		}

		$text     = preg_replace( '~\s*\(\[[0-9a-f]+\]\([^)]+/commit/[^)]+\)\)\s*$~', '', trim( $bullet[1] ) );
		$output[] = '* ' . ( '' !== $title ? $title . ': ' : '' ) . $text;
		++$bullets;
	}
	if ( 0 === $bullets ) {
		$output[] = '* Bez uživatelsky viditelných změn.';
	}
	$output[] = '';
}

$newSection = rtrim( implode( "\n", $output ) ) . "\n";
$pattern    = '/^== Changelog ==\s*$.*?(?=^== |\z)/ms';
if ( preg_match( $pattern, $readme ) ) {
	$readme = preg_replace( $pattern, rtrim( $newSection ) . "\n\n", $readme, 1 );
} else {
	$readme = rtrim( $readme ) . "\n\n" . $newSection;
}

if ( false === file_put_contents( $argv[2], rtrim( $readme ) . "\n" ) ) {
	fwrite( STDERR, "Unable to write readme.\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Updated %s with %d versions.\n", $argv[2], count( $sections ) ) );
