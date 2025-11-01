<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Writer;

use Aymericcucherousset\CsvHelper\Options\CsvOptions;

final class CsvWriter
{
    private function __construct(
        private \SplFileObject $file,
        private CsvOptions $options
    ) {
        $this->file->setCsvControl($options->delimiter, $options->enclosure, $options->escape);
    }

    public static function fromPath(string $path, ?CsvOptions $options = null, bool $append = false): self
    {
        $mode = $append ? 'a' : 'w';
        $opts = $options ?? new CsvOptions();
        $file = new \SplFileObject($path, $mode);

        return new self($file, $opts);
    }

    /**
     * Write rows. Each row must be an array (indexed).
     * If $writeHeader is provided, write header first.
     *
     * @param iterable<array<int,mixed>|\Traversable<int,mixed>> $rows
     * @param null|array<int|string,string>                      $header
     */
    public function writeRows(iterable $rows, ?array $header = null): void
    {
        if (null !== $header) {
            $this->file->fputcsv($header, $this->options->delimiter, $this->options->enclosure);
        }

        foreach ($rows as $row) {
            if ($row instanceof \Traversable) {
                $row = iterator_to_array($row);
            }

            $this->file->fputcsv($row, $this->options->delimiter, $this->options->enclosure);
        }
    }

    /**
     * Write objects by extracting public properties in given $columns order (property names).
     *
     * @param iterable<object|\Traversable<int,object>> $objects
     * @param null|array<int,string>                    $columns
     * @param null|array<int|string,string>             $header
     */
    public function writeObjects(iterable $objects, ?array $columns = null, ?array $header = null): void
    {
        if (null !== $header) {
            $this->file->fputcsv($header, $this->options->delimiter, $this->options->enclosure);
        }

        foreach ($objects as $obj) {
            $arr = [];
            if (null !== $columns) {
                foreach ($columns as $c) {
                    $arr[] = $this->propGet($obj, $c);
                }
            } else {
                // get public properties
                $arr = array_values(get_object_vars($obj));
            }
            $this->file->fputcsv($arr, $this->options->delimiter, $this->options->enclosure);
        }
    }

    private function propGet(object $obj, string $property): mixed
    {
        // attempt to use getter first
        $getter = 'get'.str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $property)));
        if (method_exists($obj, $getter)) {
            return $obj->{$getter}();
        }
        // try public property
        if (property_exists($obj, $property)) {
            return $obj->{$property};
        }

        return null;
    }
}
