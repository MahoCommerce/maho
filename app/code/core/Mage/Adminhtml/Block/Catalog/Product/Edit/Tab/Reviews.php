<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Reviews extends Mage_Adminhtml_Block_Review_Grid
{
    /**
     * Hide grid mass action elements
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        return $this;
    }

    /**
     * Determine ajax url for grid refresh
     *
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/reviews', ['_current' => true]);
    }
}
