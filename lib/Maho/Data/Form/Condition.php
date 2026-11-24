<?php

/**
 * Evaluate a field condition in the format of the admin dependence block, on the server.
 *
 * A condition maps a field to its wanted values: ['country_id' => ['IT', 'SM']]. An entry with an
 * integer key and an 'operator' holds a group: ['operator' => 'OR', 'condition' => [...]]. The
 * entries of one level are joined with AND. The wanted value ANY_VALUE matches a field that holds
 * any value that is not empty. The rules are the same as evalCondition() of
 * formElementDependenceController in public/js/mage/adminhtml/form.js.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data\Form;

/**
 * @phpstan-type ConditionGroup array{operator: 'NOT'|'AND'|'OR'|'XOR', condition: array<array-key, mixed>}
 * @phpstan-type FormCondition array<array-key, string|int|float|bool|array<array-key, string|int|float|bool>|ConditionGroup>
 */
final class Condition
{
    public const string MODE_NOT = 'NOT';
    public const string MODE_AND = 'AND';
    public const string MODE_OR = 'OR';
    public const string MODE_XOR = 'XOR';

    public const array MODES = [self::MODE_NOT, self::MODE_AND, self::MODE_OR, self::MODE_XOR];

    /** The wanted value that matches any value that is not empty. */
    public const string ANY_VALUE = '__any__';

    /**
     * @phpstan-assert-if-true ConditionGroup $entry
     */
    public static function isGroup(mixed $entry): bool
    {
        return is_array($entry)
            && in_array($entry['operator'] ?? null, self::MODES, true)
            && is_array($entry['condition'] ?? null);
    }

    /**
     * Return true when $values meet $condition. A field that $values does not hold has no value.
     *
     * @param FormCondition $condition
     * @param array<string, mixed> $values a list or a comma string is a multiselect value
     */
    public static function evaluate(array $condition, array $values, string $mode = self::MODE_AND): bool
    {
        if ($condition === []) {
            return true;
        }
        $results = [];
        foreach ($condition as $field => $entry) {
            if (self::isGroup($entry)) {
                $results[] = self::evaluate($entry['condition'], $values, $entry['operator']);
                continue;
            }
            $wanted = self::wantedOf($entry);
            $fieldValues = self::fieldValues($values[$field] ?? null);
            $results[] = array_intersect($fieldValues, $wanted) !== []
                || (in_array(self::ANY_VALUE, $wanted, true) && self::hasValue($fieldValues));
        }
        $met = count(array_filter($results));
        return match ($mode) {
            self::MODE_NOT => $met === 0,
            self::MODE_OR => $met > 0,
            self::MODE_XOR => $met === 1,
            default => $met === count($results),
        };
    }

    /**
     * Return the names of the fields that $condition reads, at every level.
     *
     * @param FormCondition $condition
     * @return list<string>
     */
    public static function fields(array $condition): array
    {
        $fields = [];
        foreach ($condition as $field => $entry) {
            if (self::isGroup($entry)) {
                $fields = [...$fields, ...self::fields($entry['condition'])];
            } elseif (is_string($field)) {
                $fields[] = $field;
            }
        }
        return array_values(array_unique($fields));
    }

    /**
     * Return the wanted values of each field in $condition, at every level.
     *
     * @param FormCondition $condition
     * @return array<string, list<string>>
     */
    public static function wantedValues(array $condition): array
    {
        $result = [];
        foreach ($condition as $field => $entry) {
            if (self::isGroup($entry)) {
                foreach (self::wantedValues($entry['condition']) as $groupField => $values) {
                    $result[$groupField] = array_values(array_unique([...$result[$groupField] ?? [], ...$values]));
                }
            } elseif (is_string($field)) {
                $result[$field] = array_values(array_unique([...$result[$field] ?? [], ...self::wantedOf($entry)]));
            }
        }
        return $result;
    }

    /**
     * Return the wanted values of the field entry $entry of a condition as strings.
     *
     * @param string|int|float|bool|array<array-key, string|int|float|bool> $entry
     * @return list<string>
     */
    private static function wantedOf(string|int|float|bool|array $entry): array
    {
        return array_values(array_map(strval(...), is_array($entry) ? $entry : [$entry]));
    }

    /**
     * Tell whether $condition follows the format, at every level. Check a decoded condition with this method before
     * you give it to the other methods.
     *
     * @param array<array-key, mixed> $condition
     * @phpstan-assert-if-true FormCondition $condition
     */
    public static function isValid(array $condition): bool
    {
        return self::structureErrors($condition) === [];
    }

    /**
     * Return an error for each part of $condition that does not follow the format.
     *
     * @param array<array-key, mixed> $condition
     * @return list<string>
     */
    public static function structureErrors(array $condition): array
    {
        $errors = [];
        foreach ($condition as $field => $entry) {
            if (is_int($field)) {
                if (!is_array($entry) || !array_key_exists('operator', $entry)) {
                    $errors[] = sprintf('Entry %d is not a group.', $field);
                } elseif (!in_array($entry['operator'], self::MODES, true)) {
                    $errors[] = sprintf("Invalid operator '%s', must be one of NOT, AND, OR, XOR.", is_scalar($entry['operator']) ? $entry['operator'] : gettype($entry['operator']));
                } elseif (!is_array($entry['condition'] ?? null) || $entry['condition'] === []) {
                    $errors[] = sprintf('The %s group has no condition.', $entry['operator']);
                } else {
                    $errors = [...$errors, ...self::structureErrors($entry['condition'])];
                }
                continue;
            }
            $values = is_array($entry) ? $entry : [$entry];
            if ($values === [] || array_filter($values, fn($value) => !is_scalar($value)) !== []) {
                $errors[] = sprintf('The field %s needs a list of values.', $field);
            }
        }
        return $errors;
    }

    /**
     * Tell whether $fieldValues, a result of fieldValues(), holds a value that is not empty.
     *
     * @param list<string> $fieldValues
     */
    public static function hasValue(array $fieldValues): bool
    {
        return array_filter($fieldValues, static fn(string $value): bool => trim($value) !== '') !== [];
    }

    /**
     * Return the value of a field as a list of strings. A list or a comma string is a multiselect value.
     *
     * @return list<string>
     */
    public static function fieldValues(mixed $value): array
    {
        if ($value === null || $value === false) {
            return [];
        }
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }
        return array_map(strval(...), is_array($value) ? array_values($value) : [$value]);
    }
}
