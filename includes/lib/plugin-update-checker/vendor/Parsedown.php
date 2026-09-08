<?php
require_once __DIR__ . '/ParsedownModern.php';
if ( !class_exists('Parsedown', false) ) {
	class_alias(\Balikovna_WC\Vendor\Parsedown::class, 'Parsedown');
}
