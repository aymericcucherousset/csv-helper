<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Options;

final class CsvOptions
{
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $escape = '\\',
        public bool $hasHeader = true,
        public bool $skipEmptyLines = true,
        public ?string $encoding = null
    ) {
    }
}
