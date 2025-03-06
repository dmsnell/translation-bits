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

The `Translation_Processor::strings_to_pot_fragment( $strings )` method takes
the output from the `::extract()` method and produces a string which can be
added into a `.pot` file for translation. Successive results from extracting
different files can be combined and merged for generated `.pot`s for an
entire project.

## Example

```php
<?php
$strings = Translation_Processor::extract(
	'test-page.php',
	<<<'HTML'
		<h1 name="some <?wp _x( 'test', 'noun', 'zs-sync' ); ?> thing" class="<?wp /* translators: a CSS class name indicating the language */ __( 'css-lang' ); ?>"><?wp __( "Title: " ); ?>The post title</h1>
        <div>This is a <?wp __( "Translation" ); ?> embedded within the HTML.</div>
        <p>There is a story to tell about <?wp __( "Translation" ); ?> and it’s fun.</p>
        <p>I have <?wp /* translators: 1: number of books. */ _n( '%1$d book', '%1$d books' ); ?></p>
		HTML
);

echo json_encode( $strings, JSON_PRETTY_PRINT );

echo Translation_Processor::strings_to_pot_fragment( $strings ) . PHP_EOL;
```

```json
{
    "test": {
        "context": "noun",
        "domain": "zs-sync",
        "funcall": "_x( 'test', 'noun', 'zs-sync' );",
        "locations": [
            [
                "test-page.php",
                1,
                16
            ]
        ]
    },
    "css-lang": {
        "translator_comment": "translators: a CSS class name indicating the language",
        "funcall": "__( 'css-lang' );",
        "locations": [
            [
                "test-page.php",
                1,
                71
            ]
        ]
    },
    "Title: ": {
        "funcall": "__( \"Title: \" );",
        "locations": [
            [
                "test-page.php",
                1,
                158
            ]
        ]
    },
    "Translation": {
        "funcall": "__( \"Translation\" );",
        "locations": [
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
    },
    "%1$d book": {
        "translator_comment": "translators: 1: number of books.",
        "funcall": "_n( '%1$d book', '%1$d books' );",
        "locations": [
            [
                "test-page.php",
                4,
                11
            ]
        ]
    }
}
```

```
#: test-page.php:1
msgctxt "noun"
msgid "test"
msgstr ""

#. translators: a CSS class name indicating the language

#: test-page.php:1
msgid "css-lang"
msgstr ""

#: test-page.php:1
msgid "Title: "
msgstr ""

#: test-page.php:2 test-page.php:3
msgid "Translation"
msgstr ""

#. translators: 1: number of books.

#: test-page.php:4
msgid "%1$d book"
msgstr ""


```

## Near-term Roadmap

 - A render function which performs translations dynamically.
