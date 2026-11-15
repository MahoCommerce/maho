<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

interface Mage_CatalogInventory_Model_Resource_Indexer_Stock_Interface
{
    /**
     * Reindex all stock status data
     */
    public function reindexAll(): static;

    /**
     * Reindex stock status data for defined ids
     */
    public function reindexEntity(int|array $entityIds): static;

    /**
     * Set Product Type Id for indexer
     */
    public function setTypeId(string $typeId): static;

    /**
     * Retrieve Product Type Id for indexer
     *
     * @throws Mage_Core_Exception
     */
    public function getTypeId(): string;
}
