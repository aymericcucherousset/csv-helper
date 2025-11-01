<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Reader;

use Aymericcucherousset\CsvHelper\Exception\CsvException;
use Aymericcucherousset\CsvHelper\Options\CsvOptions;

final class CsvReader
{
    private \SplFileObject $file;
    private CsvOptions $options;

    /**
     * CSV header row, when `hasHeader` option is enabled.
     *
     * Keys are numeric indexes or header names, values are the header values as strings.
     *
     * @var null|array<int|string,string>
     */
    private ?array $header = null;

    private function __construct(\SplFileObject $file, CsvOptions $options)
    {
        $this->file = $file;
        $this->options = $options;
        // Only READ_CSV is required
        $this->file->setFlags(\SplFileObject::READ_CSV);
        $this->file->setCsvControl($options->delimiter, $options->enclosure, $options->escape);
    }

    public static function fromPath(string $path, ?CsvOptions $options = null): self
    {
        if (!is_readable($path)) {
            throw new CsvException("CSV file not readable: {$path}");
        }
        $opts = $options ?? new CsvOptions();

        $file = new \SplFileObject($path, 'r');

        return new self($file, $opts);
    }

    /**
     * Yields CSV rows as associative or indexed arrays.
     */
    public function rows(): \Generator
    {
        $this->file->rewind();
        $first = true;

        while (!$this->file->eof()) {
            $row = $this->file->fgetcsv();

            if (false === $row || null === $row) {
                continue;
            }

            // Sometimes an empty line gives [null] or [""]
            if ($this->options->skipEmptyLines && $this->isEmptyRow($row)) {
                continue;
            }

            if ($first && $this->options->hasHeader) {
                // Normalize header values to strings so the property always holds
                // an array<int|string,string> (no nulls). fgetcsv may return null
                // for empty cells, so cast to string.
                $this->header = array_map(static fn ($c): string => (string) $c, $row);

                $first = false;

                continue;
            }

            $first = false;

            if ($this->options->hasHeader && null !== $this->header) {
                yield $this->assocRow($row);
            } else {
                yield $row;
            }
        }
    }

    /**
     * Check if a row is empty (all cells are null or empty string).
     *
     * @param array<int|string,mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (null !== $cell && '' !== $cell) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert indexed row to associative using header.
     *
     * @param array<int,mixed> $row
     *
     * @return array<int|string,mixed>
     */
    private function assocRow(array $row): array
    {
        $assoc = [];
        $len = max(count($this->header ?? []), count($row));
        for ($i = 0; $i < $len; ++$i) {
            $key = $this->header[$i] ?? (string) $i;
            $assoc[$key] = $row[$i] ?? '';
        }

        return $assoc;
    }
}
