<?php
/**
 * Clean up plugin operational data on explicit WordPress uninstall.
 *
 * @package Balikovna_WC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-balikovna-cleanup.php';
\Balikovna_WC\Cleanup::uninstall();
