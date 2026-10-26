<?php
/**
 * Build the release staging directory and ZIP from an explicit allowlist.
 *
 * Usage: php .github/scripts/build-plugin.php
 */

declare( strict_types=1 );

$root      = isset( $argv[1] ) ? realpath( $argv[1] ) : dirname( __DIR__, 2 );
if ( false === $root || ! is_dir( $root ) ) {
	fail( 'Plugin source directory does not exist.' );
}
$slug      = 'balikovna-woocommerce';
$buildRoot = $root . DIRECTORY_SEPARATOR . 'build';
$target    = $buildRoot . DIRECTORY_SEPARATOR . $slug;
$allowlist = array(
	'balikovna-woocommerce.php',
	'uninstall.php',
	'LICENSE',
	'README.md',
	'readme.txt',
	'assets',
	'includes',
	'languages',
);

function fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

function runBuildScript( string $script, array $arguments ): void {
	$process = proc_open(
		array_merge( array( PHP_BINARY, __DIR__ . '/' . $script ), $arguments ),
		array( 0 => STDIN, 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Unable to start release preparation: ' . $script );
	}
	fwrite( STDOUT, stream_get_contents( $pipes[1] ) );
	fwrite( STDERR, stream_get_contents( $pipes[2] ) );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( 0 !== proc_close( $process ) ) {
		throw new RuntimeException( 'Release preparation failed: ' . $script );
	}
}

function removeTree( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $entry ) {
		if ( $entry->isLink() || $entry->isFile() ) {
			unlink( $entry->getPathname() );
		} else {
			rmdir( $entry->getPathname() );
		}
	}
	rmdir( $path );
}

function copyTree( string $source, string $destination ): void {
	if ( is_link( $source ) ) {
		fail( 'Symlinks are not allowed in the release staging set: ' . $source );
	}
	if ( is_file( $source ) ) {
		$parent = dirname( $destination );
		if ( ! is_dir( $parent ) && ! mkdir( $parent, 0777, true ) && ! is_dir( $parent ) ) {
			fail( 'Unable to create directory: ' . $parent );
		}
		if ( ! copy( $source, $destination ) ) {
			fail( 'Unable to copy file: ' . $source );
		}
		return;
	}

	if ( ! is_dir( $destination ) && ! mkdir( $destination, 0777, true ) && ! is_dir( $destination ) ) {
		fail( 'Unable to create directory: ' . $destination );
	}
	$iterator = new DirectoryIterator( $source );
	foreach ( $iterator as $entry ) {
		if ( $entry->isDot() ) {
			continue;
		}
		copyTree( $entry->getPathname(), $destination . DIRECTORY_SEPARATOR . $entry->getFilename() );
	}
}

function matchedVersion( string $contents, string $pattern, string $label ): string {
	if ( ! preg_match( $pattern, $contents, $match ) ) {
		fail( 'Unable to read ' . $label . '.' );
	}
	$version = trim( $match[1] );
	if ( ! preg_match( '/^(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)(?:\s*<!--\s*x-release-please-version\s*-->)?$/', $version, $version_match ) ) {
		fail( $label . ' is not a semantic version or supported legacy marker: ' . $version );
	}
	return $version_match[1];
}

function removeLegacyVersionMarker( string $path ): void {
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		fail( 'Unable to read staged metadata: ' . $path );
	}
	$clean = preg_replace( '/\s*<!--\s*x-release-please-version\s*-->/', '', $contents );
	if ( $clean !== $contents && false === file_put_contents( $path, $clean ) ) {
		fail( 'Unable to normalize staged metadata: ' . $path );
	}
}

$pluginContents = file_get_contents( $root . DIRECTORY_SEPARATOR . 'balikovna-woocommerce.php' );
$readmeContents = file_get_contents( $root . DIRECTORY_SEPARATOR . 'readme.txt' );
if ( false === $pluginContents || false === $readmeContents ) {
	fail( 'Unable to read plugin metadata.' );
}

$headerVersion   = matchedVersion( $pluginContents, '/^[ \t\/*#@]*Version:\s*(.+)$/mi', 'Plugin header Version' );
$constantVersion = matchedVersion( $pluginContents, "/BALIKOVNA_WC_VERSION',\s*'([^']+)'/", 'BALIKOVNA_WC_VERSION' );
$stableVersion   = matchedVersion( $readmeContents, '/^Stable tag:\s*(.+)$/mi', 'Stable tag' );
if ( 1 !== count( array_unique( array( $headerVersion, $constantVersion, $stableVersion ) ) ) ) {
	fail( 'Plugin header, constant, and Stable tag versions do not match.' );
}

removeTree( $target );
if ( ! is_dir( $buildRoot ) && ! mkdir( $buildRoot, 0777, true ) && ! is_dir( $buildRoot ) ) {
	fail( 'Unable to create build directory.' );
}
foreach ( $allowlist as $relativePath ) {
	$source = $root . DIRECTORY_SEPARATOR . $relativePath;
	if ( ! file_exists( $source ) ) {
		fail( 'Required release path is missing: ' . $relativePath );
	}
	copyTree( $source, $target . DIRECTORY_SEPARATOR . $relativePath );
}

removeLegacyVersionMarker( $target . DIRECTORY_SEPARATOR . 'balikovna-woocommerce.php' );
removeLegacyVersionMarker( $target . DIRECTORY_SEPARATOR . 'readme.txt' );

if ( ! is_file( $target . '/includes/lib/plugin-update-checker/vendor/Parsedown.php' ) ) {
	fail( 'Parsedown.php is missing from release staging.' );
}

$parserRoot = $target . '/includes/lib/plugin-update-checker/vendor/';
$parserManifest = json_decode( (string) file_get_contents( $parserRoot . 'parsedown-manifest.json' ), true );
if ( ! is_array( $parserManifest ) || version_compare( $parserManifest['version'] ?? '0', '1.8.0', '<' )
	|| ( $parserManifest['scoped_sha256'] ?? '' ) !== hash_file( 'sha256', $parserRoot . 'ParsedownModern.php' )
	|| ! is_file( $parserRoot . 'parsedown-license.txt' ) ) {
	fail( 'Bundled Parsedown version, checksum or license is invalid.' );
}

if ( is_file( $root . '/CHANGELOG.md' ) ) {
	$changelog = tempnam( $buildRoot, 'changelog-' );
	if ( false === $changelog ) {
		fail( 'Unable to create a temporary release changelog.' );
	}
	try {
		if ( ! copy( $root . '/CHANGELOG.md', $changelog ) ) {
			throw new RuntimeException( 'Unable to copy the release changelog.' );
		}
		runBuildScript( 'normalize-changelog.php', array( $changelog ) );
		runBuildScript( 'changelog-to-readme.php', array( $changelog, $target . '/readme.txt' ) );
	} finally {
		if ( ! unlink( $changelog ) ) {
			throw new RuntimeException( 'Unable to remove the temporary release changelog.' );
		}
	}
}

$archivePath = $buildRoot . DIRECTORY_SEPARATOR . $slug . '.zip';
$archive = new ZipArchive();
if ( true !== $archive->open( $archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) || ! $archive->addEmptyDir( $slug ) ) {
	fail( 'Unable to create the plugin ZIP.' );
}
$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
foreach ( $files as $file ) {
	$name = $slug . '/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $target ) + 1 ) );
	$added = $file->isDir() ? $archive->addEmptyDir( $name ) : $archive->addFile( $file->getPathname(), $name );
	if ( ! $added ) {
		fail( 'Unable to add a plugin ZIP entry: ' . $name );
	}
}
if ( ! $archive->close() ) {
	fail( 'Unable to finish the plugin ZIP.' );
}

fwrite( STDOUT, 'Staged ' . $slug . ' ' . $headerVersion . ' in ' . $target . PHP_EOL );
fwrite( STDOUT, 'Built ' . $archivePath . PHP_EOL );
