<?php

declare(strict_types=1);

namespace Aymericcucherousset\CsvHelper\Hydrator;

use Aymericcucherousset\CsvHelper\Exception\CsvMappingException;
use Aymericcucherousset\CsvHelper\Mapping\CsvColumn;
use Aymericcucherousset\CsvHelper\Reader\CsvReader;
use Generator;

/**
 * Very small hydrator: uses attributes if requested, else header-to-property matching.
 */
final class CsvHydrator
{
    public function __construct(
        private CsvReader $reader
    ) {
    }

    /**
     * Hydrate into objects (all in memory).
     *
     * @template T of object
     *
     * @param class-string<T> $class         Fully-qualified class name to hydrate into
     * @param bool            $useAttributes Whether to use property attributes for mapping
     *
     * @return array<T> Array of instances of the given class
     */
    public function hydrateAll(string $class, bool $useAttributes = true): array
    {
        $result = [];
        foreach ($this->hydrate($class, $useAttributes) as $obj) {
            $result[] = $obj;
        }

        return $result;
    }

    /**
     * Hydrate as generator (streaming).
     *
     * @template T of object
     *
     * @param class-string<T> $class Fully-qualified class name to hydrate into
     *
     * @return \Generator<int, T>
     */
    public function hydrate(string $class, bool $useAttributes = true): Generator
    {
        $ref = new \ReflectionClass($class);
        foreach ($this->reader->rows() as $row) {
            $instance = $ref->newInstanceWithoutConstructor();
            foreach ($ref->getProperties() as $prop) {
                $name = $prop->getName();
                $csvColumn = null;
                if ($useAttributes) {
                    $attrs = $prop->getAttributes(CsvColumn::class);
                    if ([] !== $attrs) {
                        /** @var CsvColumn $csvColumn */
                        $csvColumn = $attrs[0]->newInstance();
                    }
                }
                $value = null;
                // determine value from mapping
                if ($csvColumn && null !== $csvColumn->index) {
                    $value = $row[$csvColumn->index] ?? null;
                } elseif ($csvColumn && null !== $csvColumn->name) {
                    $value = $row[$csvColumn->name] ?? null;
                } else {
                    // header-to-property mapping
                    if (is_string(key($row))) {
                        $key = $this->normalizeHeaderKey($name);
                        // try multiple normalizations
                        $found = null;
                        foreach ($row as $h => $v) {
                            if ($this->normalizeHeaderKey($h) === $key) {
                                $found = $v;

                                break;
                            }
                        }
                        $value = $found ?? null;
                    } else {
                        // numeric indexed row; try by index if property is numeric? skip
                        $value = null;
                    }
                }

                // required check
                if (($csvColumn && $csvColumn->required) && (null === $value || '' === $value)) {
                    throw new CsvMappingException(sprintf('Required column for property "%s" is missing or empty.', $name));
                }

                // conversion
                if ($csvColumn && null !== $csvColumn->converter && null !== $value && '' !== $value) {
                    $callable = $csvColumn->converter;
                    if (!is_callable($callable)) {
                        // allow "Class::method" style
                        if (false !== strpos($callable, '::')) {
                            $parts = explode('::', $callable, 2);
                            $callable = [$parts[0], $parts[1]];
                        }
                    }
                    if (!is_callable($callable)) {
                        throw new CsvMappingException(sprintf('Converter for property "%s" is not callable.', $name));
                    }

                    try {
                        $value = call_user_func($callable, $value);
                    } catch (\Throwable $e) {
                        throw new CsvMappingException(sprintf('Converter for property "%s" failed: %s', $name, $e->getMessage()), 0, $e);
                    }
                }

                // Type handling — basic attempt for typed properties
                if ($prop->hasType()) {
                    $type = $prop->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                        // non-builtins: assume value is already object or null — skip
                    } else {
                        // builtin types: basic casts for string,int,float,bool
                        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';
                        if (null === $value || '' === $value) {
                            // leave as null for nullable types
                            if ($type?->allowsNull()) {
                                $propValue = null;
                            } else {
                                // keep empty string or cast default
                                $propValue = $this->castValue($value, $typeName);
                            }
                        } else {
                            $propValue = $this->castValue($value, $typeName);
                        }
                        $prop->setValue($instance, $propValue);

                        continue;
                    }
                }

                // default set
                $prop->setValue($instance, $value);
            }

            yield $instance;
        }
    }

    private function normalizeHeaderKey(string $k): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $k));
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if (null === $value) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y'], true),
            'string' => (string) $value,
            default => $value,
        };
    }
}
