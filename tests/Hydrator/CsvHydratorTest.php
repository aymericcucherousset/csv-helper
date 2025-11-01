<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Tests\Hydrator;

use Aymericcucherousset\CsvHelper\Hydrator\CsvHydrator;
use Aymericcucherousset\CsvHelper\Mapping\CsvColumn;
use Aymericcucherousset\CsvHelper\Options\CsvOptions;
use Aymericcucherousset\CsvHelper\Reader\CsvReader;
use PHPUnit\Framework\TestCase;

final class Person
{
    #[CsvColumn(name: 'name')]
    public string $name;

    #[CsvColumn(name: 'age', converter: 'intval')]
    public int $age;

    // property without attribute, should be mapped by header fallback
    public ?string $note = null;
}

// Converter used by tests to simulate a throwing converter
class ThrowingConverter
{
    public static function thrower(mixed $v): mixed
    {
        throw new \RuntimeException('boom');
    }
}

/**
 * @internal
 *
 * @coversNothing
 */
final class CsvHydratorTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir().'/csv_hydrator_test.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testHydratesWithAttributes(): void
    {
        file_put_contents($this->tmpFile, "name,age,note\nAlice,30,ok\nBob,25,\n");
        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);
        $arr = iterator_to_array($hydrator->hydrate(Person::class), false);
        $this->assertCount(2, $arr);
        $this->assertInstanceOf(Person::class, $arr[0]);
        $this->assertSame('Alice', $arr[0]->name);
        $this->assertSame(30, $arr[0]->age);
        $this->assertSame('ok', $arr[0]->note);
    }

    public function testHydratesWithoutAttributes(): void
    {
        // header-to-property fallback (no attributes used)
        file_put_contents($this->tmpFile, "name,age,note\nCarol,40,ok\n");
        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);
        $arr = iterator_to_array($hydrator->hydrate(Person::class, false), false);

        $this->assertCount(1, $arr);
        $this->assertSame('Carol', $arr[0]->name);
        $this->assertSame(40, $arr[0]->age);
        $this->assertSame('ok', $arr[0]->note);
    }

    public function testRequiredColumnThrows(): void
    {
        // property marked required but empty in CSV should throw
        file_put_contents($this->tmpFile, "name,age\n,30\n");

        // local class with required attribute
        $cls = new class () {
            #[CsvColumn(name: 'name', required: true)]
            public string $name;
        };

        $fqcn = $this->getAnonymousClassName($cls);

        // Use options without header so rows are numeric-indexed and index mapping applies
        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: false));
        $hydrator = new CsvHydrator($reader);

        $this->expectException(\Aymericcucherousset\CsvHelper\Exception\CsvMappingException::class);
        iterator_to_array($hydrator->hydrate($fqcn), false);
    }

    public function testInvalidConverterThrows(): void
    {
        file_put_contents($this->tmpFile, "name,age\nDave,notanumber\n");

        $cls = new class () {
            #[CsvColumn(name: 'age', converter: 'this_is_not_callable')]
            public int $age;
        };
        $fqcn = $this->getAnonymousClassName($cls);

        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);

        $this->expectException(\Aymericcucherousset\CsvHelper\Exception\CsvMappingException::class);
        iterator_to_array($hydrator->hydrate($fqcn), false);
    }

    public function testConverterExceptionIsWrapped(): void
    {
        file_put_contents($this->tmpFile, "name,age\nEve,123\n");
        $cls = new class () {
            #[CsvColumn(name: 'age', converter: 'Aymericcucherousset\\CsvHelper\\Tests\\Hydrator\\ThrowingConverter::thrower')]
            public int $age;
        };
        $fqcn = $this->getAnonymousClassName($cls);

        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);

        $this->expectException(\Aymericcucherousset\CsvHelper\Exception\CsvMappingException::class);
        $this->expectExceptionMessage('boom');
        iterator_to_array($hydrator->hydrate($fqcn), false);
    }

    public function testIndexMapping(): void
    {
        // when using index mapping we provide rows without a header
        file_put_contents($this->tmpFile, "Gina,50\n");

        $cls = new class () {
            #[CsvColumn(index: 0)]
            public string $first;

            #[CsvColumn(index: 1)]
            public int $second;
        };
        $fqcn = $this->getAnonymousClassName($cls);

        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: false));
        $hydrator = new CsvHydrator($reader);

        $arr = iterator_to_array($hydrator->hydrate($fqcn), false);

        // Inform static analyzers (phpstan) about the anonymous class shape so
        // property accesses like `$arr[0]->first` are recognized as valid.
        /** @var list<object{first:string,second:int}> $arr */
        $this->assertCount(1, $arr);
        $this->assertSame('Gina', $arr[0]->first);
        $this->assertSame(50, $arr[0]->second);
    }

    public function testNullablePropertyBecomesNull(): void
    {
        file_put_contents($this->tmpFile, "name,note\nHank,\n");

        $cls = new class () {
            public string $name;
            public ?string $note = null;
        };
        $fqcn = $this->getAnonymousClassName($cls);

        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);

        $arr = iterator_to_array($hydrator->hydrate($fqcn), false);

        // Tell phpstan the anonymous class shape for these results.
        /** @var list<object{name:string,note:?string}> $arr */
        $this->assertSame('Hank', $arr[0]->name);
        $this->assertNull($arr[0]->note);
    }

    /**
     * Helper to get a usable FQCN for an anonymous class instance in tests.
     *
     * @param object $obj
     *
     * @return class-string
     */
    private function getAnonymousClassName(object $obj): string
    {
        $r = new \ReflectionObject($obj);

        return $r->getName();
    }

    public function testHydrateAllWithAttributes(): void
    {
        file_put_contents($this->tmpFile, "name,age,note\nAlice,30,ok\nBob,25,\n");
        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions());
        $hydrator = new CsvHydrator($reader);
        $arr = $hydrator->hydrateAll(Person::class, true);

        $this->assertCount(2, $arr);
        $this->assertInstanceOf(Person::class, $arr[1]);
        $this->assertSame('Bob', $arr[1]->name);
        $this->assertSame(25, $arr[1]->age);
        $this->assertNull($arr[1]->note);
    }

    public function testFloatAndBoolCasting(): void
    {
        // ensure float and bool typed properties are properly cast from CSV values
        file_put_contents($this->tmpFile, "score,active\n4.5,yes\n0,no\n");

        $cls = new class () {
            public float $score;
            public bool $active;
        };

        $fqcn = $this->getAnonymousClassName($cls);

        $reader = CsvReader::fromPath($this->tmpFile, new CsvOptions(hasHeader: true));
        $hydrator = new CsvHydrator($reader);

        $arr = iterator_to_array($hydrator->hydrate($fqcn), false);

        /** @var list<object{score:float,active:bool}> $arr */
        $this->assertCount(2, $arr);
        $this->assertSame(4.5, $arr[0]->score);
        $this->assertTrue($arr[0]->active);

        $this->assertSame(0.0, $arr[1]->score);
        $this->assertFalse($arr[1]->active);
    }
}
