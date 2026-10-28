<?php

/**
 * Reads and writes the values of the user-defined attributes of a blog post or a blog category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use Maho\ApiPlatform\Service\CustomAttributes as Values;

final class CustomAttributes
{
    /**
     * @return array<string, mixed>
     */
    public static function read(\Maho_Blog_Model_Post|\Maho_Blog_Model_Category $model): array
    {
        return Values::read($model, Values::userDefined($model::ENTITY));
    }

    /**
     * Check $values and set them on $model. A code that is not a user-defined attribute in the set of
     * $model is an error.
     *
     * @param array<string, mixed> $values
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(\Maho_Blog_Model_Post|\Maho_Blog_Model_Category $model, array $values): void
    {
        // The nightly eav_clean_orphaned_records cron deletes a value whose attribute is not in the set
        Values::apply($model, $values, Values::userDefined($model::ENTITY), array_keys($model->getAttributes()));
    }

    /**
     * Return the codes of the user-defined attributes of $entityTypeCode, for a collection to select.
     *
     * @return list<string>
     */
    public static function codes(string $entityTypeCode): array
    {
        return array_keys(Values::userDefined($entityTypeCode));
    }
}
