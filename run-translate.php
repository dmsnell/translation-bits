<?php

require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/class-wp-token-map.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-active-formatting-elements.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-attribute-token.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-decoder.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-doctype-info.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-open-elements.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-processor-state.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-span.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-stack-event.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-token.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/html5-named-character-references.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-unsupported-exception.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-text-replacement.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-tag-processor.php';
require_once '/Users/dmsnell/code/wordpress-develop/src/wp-includes/html-api/class-wp-html-processor.php';
require_once __DIR__ . '/class.translation-processor.php';

$langs = array(
	'en-US' => array(
		'css-lang'   => 'lang:en-US',
	),
	'de-DE' => array(
		'css-lang'    => 'lang:de-DE',
		'Translation' => 'Ubersetzung',
		'test'        => 'Probe',
		'%1$d book'   => 'ein Buch',
		'%1$d books'  => '%1$d Bücher',
	)
);

$lang    = 'de-DE';
$html    = file_get_contents( __DIR__ . '/example.html' );
$strings = Translation_Processor::translate( $html, function ( $token ) use ( $langs, $lang ){
	$format = match ( $token['func'] ) {
		'_n' => intval( $token['args'][2] ) === 1 ? $token['args'][0] : $token['args'][1],
		default => $token['args'][0]
	};

	$format = $langs[ $lang ][ $format ] ?? $langs['en-US'][ $format ] ?? $format;
	return '_n' === $token['func'] ? sprintf( $format, intval( $token['args'][2] ) ) : $format;
} );

echo $strings . PHP_EOL;
