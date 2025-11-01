<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Tests\Reader;

use Aymericcucherousset\CsvHelper\Options\CsvOptions;
use Aymericcucherousset\CsvHelper\Reader\CsvReader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CsvReaderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir().'/csv_helper_test.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testReadsHeaderAndRows(): void
    {
        file_put_contents($this->tmpFile, "name,age\nAlice,30\nBob,25\n");
        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $rows = iterator_to_array($reader->rows(), false);
        $this->assertCount(2, $rows);
        $this->assertSame(['name' => 'Alice', 'age' => '30'], $rows[0]);
    }

    public function testSkipsEmptyLines(): void
    {
        file_put_contents($this->tmpFile, "name,age\n\nAlice,30\n");
        $options = new CsvOptions(skipEmptyLines: true);
        $reader = CsvReader::fromPath($this->tmpFile, $options);
        $rows = iterator_to_array($reader->rows(), false);
        $this->assertCount(1, $rows);
        $this->assertSame(['name' => 'Alice', 'age' => '30'], $rows[0]);
    }

    public function testIncludesEmptyLineWhenNotSkipping(): void
    {
        file_put_contents($this->tmpFile, "name,age\n\nAlice,30\n");
        $options = new CsvOptions(skipEmptyLines: false, hasHeader: true);
        $reader = CsvReader::fromPath($this->tmpFile, $options);
        $rows = iterator_to_array($reader->rows(), false);

        // header present -> the reader yields an empty associative row for the empty line
        // followed by the data row. There may be a trailing empty row depending on
        // fgetcsv behavior, so assert at least two rows and check the first two.
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame(['name' => '', 'age' => ''], $rows[0]);
        $this->assertSame(['name' => 'Alice', 'age' => '30'], $rows[1]);
    }

    public function testUnreadableFileThrows(): void
    {
        $path = sys_get_temp_dir().'/csv_reader_unreadable_'.uniqid().'.csv';
        // ensure the file does not exist
        if (file_exists($path)) {
            unlink($path);
        }

        $this->expectException(\Aymericcucherousset\CsvHelper\Exception\CsvException::class);
        CsvReader::fromPath($path, new CsvOptions());
    }

    public function testEmptyFileYieldsNoRows(): void
    {
        $tmp = sys_get_temp_dir().'/csv_reader_empty_'.uniqid().'.csv';
        // ensure the file is present but empty
        file_put_contents($tmp, '');

        $reader = CsvReader::fromPath($tmp, new CsvOptions());
        $rows = iterator_to_array($reader->rows(), false);

        $this->assertEmpty($rows);

        unlink($tmp);
    }
}
