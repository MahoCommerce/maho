<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Mage_Catalog_Model_Resource_Product_Indexer_Price_Interface
{
    /**
     * Reindex temporary (price result data) for all products
     */
    public function reindexAll();

    /**
     * Reindex temporary (price result data) for defined product(s)
     *
     * @param int|array $entityIds
     */
    public function reindexEntity($entityIds);

    /**
     * Register data required by product type process in event object
     */
    public function registerEvent(Mage_Index_Model_Event $event);
}
