<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Tests\Writer;

use Aymericcucherousset\CsvHelper\Options\CsvOptions;
use Aymericcucherousset\CsvHelper\Writer\CsvWriter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CsvWriterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir().'/csv_writer_test.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testWriteRowsAndHeader(): void
    {
        $writer = CsvWriter::fromPath($this->tmpFile, new CsvOptions(), false);
        $rows = [
            ['Alice', '30'],
            ['Bob', '25'],
        ];
        $writer->writeRows($rows, ['name', 'age']);
        $content = file_get_contents($this->tmpFile);

        $this->assertNotFalse($content);
        $this->assertStringContainsString('name,age', $content);
        $this->assertStringContainsString('Alice,30', $content);
    }

    public function testWriteObjects(): void
    {
        $writer = CsvWriter::fromPath($this->tmpFile, new CsvOptions(), false);

        $a = new class () {
            public string $name = 'Alice';
            public int $age = 30;
        };

        $b = new class () {
            public string $name = 'Bob';
            public int $age = 25;
        };

        // write with explicit columns and header
        $writer->writeObjects([$a, $b], ['name', 'age'], ['name', 'age']);

        $content = file_get_contents($this->tmpFile);

        $this->assertNotFalse($content);
        $this->assertStringContainsString('name,age', $content);
        $this->assertStringContainsString('Alice,30', $content);
        $this->assertStringContainsString('Bob,25', $content);
    }

    public function testWriteObjectsWithGetters(): void
    {
        $writer = CsvWriter::fromPath($this->tmpFile, new CsvOptions(), false);

        $a = new class () {
            private string $name = 'Alice';
            private int $age = 30;

            public function getName(): string
            {
                return $this->name;
            }

            public function getAge(): int
            {
                return $this->age;
            }
        };

        $b = new class () {
            private string $name = 'Bob';
            private int $age = 25;

            public function getName(): string
            {
                return $this->name;
            }

            public function getAge(): int
            {
                return $this->age;
            }
        };

        // write with explicit columns and header (uses getters)
        $writer->writeObjects([$a, $b], ['name', 'age'], ['name', 'age']);

        $content = file_get_contents($this->tmpFile);

        $this->assertNotFalse($content);
        $this->assertStringContainsString('name,age', $content);
        $this->assertStringContainsString('Alice,30', $content);
        $this->assertStringContainsString('Bob,25', $content);
    }

    public function testWriteObjectsMissingProperty(): void
    {
        $writer = CsvWriter::fromPath($this->tmpFile, new CsvOptions(), false);

        $a = new class () {
            public string $name = 'Alice';
            public int $age = 30;
        };

        // include a non-existing property 'note' in columns
        $writer->writeObjects([$a], ['name', 'note', 'age'], ['name', 'note', 'age']);

        $content = file_get_contents($this->tmpFile);

        $this->assertNotFalse($content);
        // expect empty cell for missing 'note' column
        $this->assertStringContainsString('Alice,,30', $content);
    }

    public function testWriteRowsWithTraversableRows(): void
    {
        $writer = CsvWriter::fromPath($this->tmpFile, new CsvOptions(), false);

        $rows = [
            new \ArrayIterator(['Alice', '30']),
            new \ArrayIterator(['Bob', '25']),
        ];

        $writer->writeRows($rows, ['name', 'age']);

        $content = file_get_contents($this->tmpFile);

        $this->assertNotFalse($content);
        $this->assertStringContainsString('name,age', $content);
        $this->assertStringContainsString('Alice,30', $content);
        $this->assertStringContainsString('Bob,25', $content);
    }
}
