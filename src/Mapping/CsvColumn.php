<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CsvColumn
{
    public function __construct(
        public ?string $name = null,      // header name
        public ?int $index = null,        // 0-based index
        public ?string $converter = null, // callable string "Class::method" or global function name
        public bool $required = false
    ) {
    }
}
