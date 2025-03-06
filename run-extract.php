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

$strings = Translation_Processor::extract( 'test-page.php', <<<HTML
<h1><?wp __( "Title: " ); ?>The post title</h1>
<div>This is a <?wp __( "Translation" ); ?> embedded within the HTML.</div>
<p>There is a story to tell about <?wp __( "Translation" ); ?> and it’s fun.</p>
HTML
);

echo Translation_Processor::strings_to_pot_fragment( $strings ) . PHP_EOL;
