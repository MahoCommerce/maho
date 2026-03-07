<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_Search_Type
{
    public function toOptionArray(): array
    {
        $types = [
            Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE     => 'Like',
            Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT => 'Fulltext',
            Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE  => 'Combine (Like and Fulltext)',
        ];
        $options = [];
        foreach ($types as $k => $v) {
            $options[] = [
                'value' => $k,
                'label' => $v,
            ];
        }
        return $options;
    }
}
