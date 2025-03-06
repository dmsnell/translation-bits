# Translation Bits

Find, extract, and render translations in raw HTML documents using familiar
WordPress syntax and translation calls.

While a work-in-progress, this library intends to provide a safe-by-default
interface to remove the need to consider any escaping issues with the Bits.
That is, the plugin will ensure that proper escaping occurs for translations
regardless of whether they are being rendered into a text node, an attribute,
a URL attribute, JavaScript, etc…

The `Translation_Processor::extract( $filename, $html )` method return an
array containing every translation Bit with its location within the document.

## Example

```php
<?php
$strings = Translation_Processor::extract(
	'test-page.php',
	<<<HTML
		<h1><?wp __( "Title: " ); ?>The post title</h1>
		<div>This is a <?wp __( "Translation" ); ?> embedded within the HTML.</div>
		<p>There is a story to tell about <?wp __( "Translation" ); ?> and it’s fun.</p>
		HTML
);

echo json_encode( $strings, JSON_PRETTY_PRINT );

echo Translation_Processor::strings_to_pot_fragment( $strings ) . PHP_EOL;
```

```json
{
    "Title: ": [
        [
            "test-page.php",
            1,
            5
        ]
    ],
    "Translation": [
        [
            "test-page.php",
            2,
            16
        ],
        [
            "test-page.php",
            3,
            35
        ]
    ]
}
```

```
#: test-page.php:1
msgid "Title: "
msgstr ""

#: test-page.php:2 test-page.php:3
msgid "Translation"
msgstr ""


```

## Near-term Roadmap

 - Translator comment support, including a syntax for how to communicate this.
 - A render function which performs translations dynamically.
 - Support for complicated translation functions such as `_n()`.
