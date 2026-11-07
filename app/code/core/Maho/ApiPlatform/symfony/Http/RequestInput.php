<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Http;

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Typed reads of request input that the serializer does not type: a raw request body, the GraphQL style input
 * of a processor, or a row of either. The serializer types only the fields of a resource, so a value under an
 * alias, a key the resource does not declare, or an entry of an array field can be of any JSON type.
 *
 * Each read takes the first of its keys that is set and not null, and returns null when there is none, so a
 * caller can tell a missing field from a given one. Values convert as Symfony's InputBag converts them, but a
 * value of the wrong type throws the exception the serializer throws for a field of a resource, so both answer
 * 400 with the same body. InputBag itself is final, and its parent ParameterBag throws errors that answer 500.
 */
final class RequestInput
{
    /**
     * @param array<array-key, mixed> $input
     * @param string $path where $input sits in the request, for the error of a nested row, e.g. "items[0]"
     */
    public function __construct(private readonly array $input = [], private readonly string $path = '') {}

    /**
     * @param array<array-key, mixed> $input
     */
    public static function of(array $input, string $path = ''): self
    {
        return new self($input, $path);
    }

    /**
     * The JSON body of $request: [] when there is none, a 400 when it is not a JSON object or array.
     * Reads the content whatever the content type says, as Request::toArray() does, so a JSON body sent
     * with a form content type still reads.
     *
     * @return array<array-key, mixed>
     * @throws BadRequestHttpException
     */
    public static function decode(?Request $request): array
    {
        if ($request === null || $request->getContent() === '') {
            return [];
        }
        try {
            return $request->toArray();
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }
    }

    /**
     * A string, or a number as its string. Shared with the readers that report a wrong type their own way.
     */
    public static function toString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }

    /**
     * An integer, or a string of one. Shared with the readers that collect their errors instead of throwing.
     */
    public static function toInt(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int === false ? null : $int;
    }

    /**
     * A number, or a string of one.
     */
    public static function toFloat(mixed $value): ?float
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $float === false ? null : $float;
    }

    /**
     * A string. A number is taken as its string, so a numeric SKU or code sent as a JSON number still reads.
     *
     * @throws NotNormalizableValueException
     */
    public function string(string ...$keys): ?string
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        return self::toString($value) ?? throw $this->invalid($key, $value, 'string');
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function int(string ...$keys): ?int
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        return self::toInt($value) ?? throw $this->invalid($key, $value, 'int');
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function float(string ...$keys): ?float
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        return self::toFloat($value) ?? throw $this->invalid($key, $value, 'float');
    }

    /**
     * A boolean as InputBag::getBoolean() reads it: true or false, 1 or 0, and strings such as "true", "false",
     * "1", "0", "yes" and "no".
     *
     * @throws NotNormalizableValueException
     */
    public function bool(string ...$keys): ?bool
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        $bool = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null;
        return $bool ?? throw $this->invalid($key, $value, 'bool');
    }

    /**
     * A JSON object or array.
     *
     * @return array<array-key, mixed>|null
     * @throws NotNormalizableValueException
     */
    public function array(string ...$keys): ?array
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        return is_array($value) ? $value : throw $this->invalid($key, $value, 'array');
    }

    /**
     * A JSON array.
     *
     * @return list<mixed>|null
     * @throws NotNormalizableValueException
     */
    public function list(string ...$keys): ?array
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        return is_array($value) && array_is_list($value) ? $value : throw $this->invalid($key, $value, 'list');
    }

    /**
     * A list of strings, numbers taken as their strings.
     *
     * @return list<string>|null
     * @throws NotNormalizableValueException
     */
    public function stringList(string ...$keys): ?array
    {
        return $this->mapList($keys, 'string', fn(self $item): ?string => $item->string('0'));
    }

    /**
     * A list of integers, or of strings of them.
     *
     * @return list<int>|null
     * @throws NotNormalizableValueException
     */
    public function intList(string ...$keys): ?array
    {
        return $this->mapList($keys, 'int', fn(self $item): ?int => $item->int('0'));
    }

    /**
     * A list of JSON objects, each as a RequestInput that names its errors by its place in the request.
     *
     * @return list<self>|null
     * @throws NotNormalizableValueException
     */
    public function rows(string ...$keys): ?array
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid($key, $value, 'list');
        }
        return self::rowsOf($value, $this->pathOf($key));
    }

    /**
     * Each entry of a list of JSON objects as a RequestInput, e.g. a body that is a list of rows.
     *
     * @param list<mixed> $rows
     * @return list<self>
     * @throws NotNormalizableValueException
     */
    public static function rowsOf(array $rows, string $path = ''): array
    {
        $inputs = [];
        foreach ($rows as $index => $row) {
            $rowPath = "{$path}[{$index}]";
            if (!is_array($row)) {
                throw self::invalidAt($rowPath, $row, 'array');
            }
            $inputs[] = new self($row, $rowPath);
        }
        return $inputs;
    }

    /**
     * @param array<string> $keys
     * @param string $type the type of an entry, for the error of a null one
     * @param \Closure(self): T $read reads key "0" of a one-entry input
     * @template T
     * @return list<T>|null
     */
    private function mapList(array $keys, string $type, \Closure $read): ?array
    {
        [$key, $value] = $this->find($keys);
        if ($key === null) {
            return null;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid($key, $value, 'list');
        }
        $items = [];
        foreach ($value as $index => $item) {
            $entry = new self(['0' => $item], $this->pathOf($key) . "[{$index}]");
            // A null entry has a wrong type too, where a null field only means that it is not given
            $items[] = $read($entry) ?? throw self::invalidAt($entry->path, null, $type);
        }
        return $items;
    }

    /**
     * @param array<string> $keys
     * @return array{0: ?string, 1: mixed}
     */
    private function find(array $keys): array
    {
        foreach ($keys as $key) {
            $value = $this->input[$key] ?? null;
            if ($value !== null) {
                return [$key, $value];
            }
        }
        return [null, null];
    }

    private function pathOf(string $key): string
    {
        return match (true) {
            $this->path === '' => $key,
            $key === '0' => $this->path,
            default => "{$this->path}.{$key}",
        };
    }

    private function invalid(string $key, mixed $value, string $type): NotNormalizableValueException
    {
        return self::invalidAt($this->pathOf($key), $value, $type);
    }

    private static function invalidAt(string $path, mixed $value, string $type): NotNormalizableValueException
    {
        // The message and the path of the serializer, so the error reads as one of a field of a resource
        return NotNormalizableValueException::createForUnexpectedDataType(
            sprintf('The type of the "%s" attribute must be "%s", "%s" given.', $path, $type, gettype($value)),
            $value,
            [$type],
            $path,
            true,
        );
    }
}
