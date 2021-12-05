# Npp-Autocomplete-Normalizer

Clean Notepad++ autocomplete files `*.xml`, by sorting items, removing duplicates, and document formatting.

## Installation

- Download the archive
- Put your `php.xml` to the `src/` directory, or replace `php.xml` file's contents
- Install php >= php7.1 (I used PHP 8.0.1 (cli) (built: Jan  5 2021 23:43:39) (ZTS Visual C++ 2019 x64)) and run it in the browser, or just run `php index.php`

On the right pane you'll see the normalized version of your autocomplete file.

The generated XML file location `dist/php.xml`

## Bonus Features

1. You can find dummy example parser (`parsers/wp_parse_docs.php`) on how to get keywords from official WP documentation. Just comment out line like so:
```
require_once('parsers/wp_parse_docs.php');
//require_once('parsers/php_parse_docs.php');
```
in `run.php` and then run:

```php run.php```
to download functions from the docs.

2. Second parser was done to get all functions from the php.net docs. There were about ~8,500 functions, so I installed <a href="https://zealdocs.org/" target="_blank">Zeal docs</a> locally and parsed data from the local web server.
With the help of this example it's possible to download faster any keywords and docs from the popular languages and frameworks.

To run this parser you will need:

 * Zeal docs could be downloaded from here https://zealdocs.org/
 * Create symbolic link of `docsets/` folder and place it inside the `zeal.docs` host folder that should be served.
 * Run the web server to serve the pages.
 * Remove file's content from `tmp/tmp.xml`
 * Run the parser `php run.php`
 * The result file you can find here `dist/php.net/php.xml`
 * When the parser will finish it's work - copy the result file to `src/php.xml` and run `php index.php` to normalize it's content.
 * After that use normalized result in `dist/php.xml`.

Autocomplete Example file (php.xml) you can find <a href="https://github.com/CoreText/WordPress-Auto-complete-for-Notepad-Plus">here</a>
