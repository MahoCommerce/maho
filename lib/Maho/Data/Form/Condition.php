<?php

/**
 * Evaluate a field condition in the format of the admin dependence block, on the server.
 *
 * A condition maps a field to its wanted values: ['country_id' => ['IT', 'SM']]. An entry with an
 * integer key and an 'operator' holds a group: ['operator' => 'OR', 'condition' => [...]]. The
 * entries of one level are joined with AND. The rules are the same as evalCondition() of
 * formElementDependenceController in public/js/mage/adminhtml/form.js.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data\Form;

final class Condition
{
    public const MODE_NOT = 'NOT';
    public const MODE_AND = 'AND';
    public const MODE_OR = 'OR';
    public const MODE_XOR = 'XOR';

    public const MODES = [self::MODE_NOT, self::MODE_AND, self::MODE_OR, self::MODE_XOR];

    public static function isGroup(mixed $entry): bool
    {
        return is_array($entry)
            && in_array($entry['operator'] ?? null, self::MODES, true)
            && is_array($entry['condition'] ?? null);
    }

    /**
     * Return true when $values meet $condition. A field that $values does not hold has no value.
     *
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
            $wanted = array_map(strval(...), is_array($entry) ? $entry : [$entry]);
            $results[] = array_intersect(self::fieldValues($values[$field] ?? null), $wanted) !== [];
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
                $values = array_map(strval(...), is_array($entry) ? $entry : [$entry]);
                $result[$field] = array_values(array_unique([...$result[$field] ?? [], ...$values]));
            }
        }
        return $result;
    }

    /**
     * Return an error for each part of $condition that does not follow the format.
     *
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
