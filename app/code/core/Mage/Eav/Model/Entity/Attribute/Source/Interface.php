<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * Entity attribute select source interface
 * Source is providing the selection options for user interface
 *
 * A source that reads a collection keeps the other columns of the collection, such as the title of a country.
 *
 * @phpstan-type SourceOption array{value: int|string|list<array{value: int|string, label: string}>, label: string, ...<string, string|int|float|bool|null>}
 */

interface Mage_Eav_Model_Entity_Attribute_Source_Interface
{
    /**
     * Retrieve all options
     *
     * @param bool $withEmpty Add an empty option first
     * @param bool $defaultValues Use the admin values instead of the labels of the current store
     * @return list<SourceOption>
     */
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array;

    /**
     * Retrieve Option value text
     *
     * @param string $value
     * @return mixed
     */
    public function getOptionText($value);
}
