<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * @template TItem of Mage_Catalog_Model_Abstract
 */
abstract class Mage_Catalog_Block_Seo_Sitemap_Abstract extends Mage_Core_Block_Template
{
    /**
     * Init pager
     */
    public function bindPager(string $pagerName): void
    {
        $pager = $this->getLayout()->getBlock($pagerName);
        /** @var Mage_Page_Block_Html_Pager $pager */
        if ($pager) {
            $pager->setAvailableLimit([50 => 50]);
            $pager->setCollection($this->getCollection());
            $pager->setShowPerPage(false);
        }
    }

    /**
     * @param TItem $item
     */
    abstract public function getItemUrl(Mage_Catalog_Model_Abstract $item): string;
}
