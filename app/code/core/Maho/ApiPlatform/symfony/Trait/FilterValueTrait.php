<?php

/**
 * Read single values and search words from the filters of a collection request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait FilterValueTrait
{
    /**
     * Each word of a search adds a LIKE condition, so a search uses only its first words.
     */
    public const MAX_SEARCH_WORDS = 10;

    /**
     * Return the filter $key as a string, or null when it is absent or empty.
     * A query such as `?key[]=x` gives a list, which is a 400 error.
     *
     * @param array<string, mixed> $filters
     */
    protected function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            throw new BadRequestHttpException("{$key} must be a single value, not a list");
        }
        return (string) $value;
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function intFilter(array $filters, string $key): ?int
    {
        $value = $this->stringFilter($filters, $key);
        if ($value === null) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new BadRequestHttpException("{$key} must be an integer");
        }
        return (int) $value;
    }

    /**
     * Return the filter $key as a boolean, or null when it is absent or empty.
     * The values true, false, 1, 0, yes, no, on and off are accepted.
     *
     * @param array<string, mixed> $filters
     */
    protected function booleanFilter(array $filters, string $key): ?bool
    {
        $value = $this->stringFilter($filters, $key);
        if ($value === null) {
            return null;
        }
        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new BadRequestHttpException("{$key} must be true or false");
        }
        return $flag;
    }

    /**
     * Split $search at white space and return the first MAX_SEARCH_WORDS words.
     *
     * @return list<string>
     */
    protected function searchWords(?string $search): array
    {
        $words = preg_split('/\s+/', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_slice($words, 0, self::MAX_SEARCH_WORDS);
    }
}
