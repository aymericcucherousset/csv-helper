CSV Helper
=========

A small PHP library to read, hydrate and write CSV files with a convenient API.

Requirements
------------
- PHP 8.2+
- Composer (for installing dev deps and running scripts)

Installation
------------
Install dependencies with Composer:

```bash
composer require aymericcucherousset/csv-helper
```

Usage examples
--------------
The library provides three main helpers: a reader, a hydrator and a writer.

CsvReader
---------
Read rows from a CSV file. By default the reader expects a header row.

```php
use Aymericcucherousset\CsvHelper\Reader\CsvReader;
use Aymericcucherousset\CsvHelper\Options\CsvOptions;

$reader = CsvReader::fromPath('/path/to/file.csv', new CsvOptions(hasHeader: true));
foreach ($reader->rows() as $row) {
    // $row is an associative array when hasHeader=true
    var_dump($row);
}
```

CsvHydrator
-----------
Hydrate rows into objects using PHP attributes (CsvColumn) or header-to-property mapping.

```php
use Aymericcucherousset\CsvHelper\Hydrator\CsvHydrator;

$hydrator = new CsvHydrator($reader);
foreach ($hydrator->hydrate(MyClass::class) as $obj) {
    // $obj is an instance of MyClass
}

// or load all into memory
$all = $hydrator->hydrateAll(MyClass::class);
```

CsvWriter
---------
Write indexed rows or arrays of objects to CSV. Accepts iterable rows including Traversable (ArrayIterator) and generators.

```php
use Aymericcucherousset\CsvHelper\Writer\CsvWriter;
use Aymericcucherousset\CsvHelper\Options\CsvOptions;

$writer = CsvWriter::fromPath('/tmp/out.csv', new CsvOptions(), false);
$writer->writeRows([
    ['Alice', '30'],
    ['Bob', '25'],
], ['name','age']);

// write objects by property or getters
$writer->writeObjects([$obj1, $obj2], ['name','age'], ['name','age']);
```

Notes on behavior
-----------------
- `CsvReader::rows()` yields associative arrays when `hasHeader=true`, else indexed arrays.
- Empty CSV lines: when `skipEmptyLines` is true (default) empty rows are skipped. Tests demonstrate behavior when `skipEmptyLines=false`.
- `CsvHydrator` supports converters via attribute configuration and basic builtin casting for scalar typed properties (int, float, bool, string).
- `CsvWriter::writeRows()` supports rows that are `\Traversable` (they are converted with `iterator_to_array`).

Contributing
------------
This README is intended for users of the library. If you'd like to contribute, please read the full contributor guidelines in `CONTRIBUTING.md` which explains the PR process, testing, coding standards and useful commands.

Quick checklist for contributors:

- Run the test suite: `composer test` or `vendor/bin/phpunit`.
- Run static analysis: `composer phpstan`.
- Run style checks and fix: `composer lint` / `composer lint-fix`.
- Add unit tests for any new behavior or bug fix.

Then open a pull request explaining the change and the added tests.
