<?php

declare( strict_types=1 );

$root = dirname( __DIR__, 2 );
$lock = json_decode( file_get_contents( $root . '/composer.lock' ), true, 512, JSON_THROW_ON_ERROR );
$package = null;
foreach ( array_merge( $lock['packages'], $lock['packages-dev'] ) as $candidate ) {
	if ( 'erusev/parsedown' === $candidate['name'] ) {
		$package = $candidate;
		break;
	}
}
if ( ! $package || version_compare( $package['version'], '1.8.0', '<' ) ) {
	throw new RuntimeException( 'A locked Parsedown >= 1.8.0 is required.' );
}
$source = file_get_contents( $root . '/vendor/erusev/parsedown/Parsedown.php' );
$tokens = token_get_all( $source, TOKEN_PARSE );
if ( T_OPEN_TAG !== $tokens[0][0] ) {
	throw new RuntimeException( 'Unexpected Parsedown source format.' );
}
$tokens[0][1] = "<?php\nnamespace Balikovna_WC\\Vendor;\n";
$scoped = implode( '', array_map( function ( $token ) { return is_array( $token ) ? $token[1] : $token; }, $tokens ) );
$manifest = json_encode(
	array(
		'package' => $package['name'],
		'version' => $package['version'],
		'reference' => $package['source']['reference'],
		'upstream_sha256' => hash( 'sha256', $source ),
		'scoped_sha256' => hash( 'sha256', $scoped ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
$outputs = array(
	'ParsedownModern.php' => $scoped,
	'parsedown-manifest.json' => $manifest,
	'parsedown-license.txt' => file_get_contents( $root . '/vendor/erusev/parsedown/LICENSE.txt' ),
);
$check = in_array( '--check', $argv, true );
foreach ( $outputs as $name => $contents ) {
	$path = $root . '/includes/lib/plugin-update-checker/vendor/' . $name;
	if ( $check ) {
		if ( ! is_file( $path ) || file_get_contents( $path ) !== $contents ) {
			throw new RuntimeException( 'Bundled file differs from locked dependency: ' . $name );
		}
	} elseif ( false === file_put_contents( $path, $contents ) ) {
		throw new RuntimeException( 'Unable to write bundled dependency: ' . $name );
	}
}
echo $check ? "Bundled Parsedown matches composer.lock.\n" : "Scoped Parsedown generated from composer.lock.\n";