<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_Block_Customer_Wishlist_Item_Column extends Mage_Wishlist_Block_Abstract
{
    /**
     * Checks whether column should be shown in table
     *
     * @return true
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Retrieve block html
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if ($this->isEnabled()) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * Retrieve child blocks html
     *
     * @param string $name
     * @param Mage_Core_Block_Abstract $child
     */
    #[\Override]
    protected function _beforeChildToHtml($name, $child)
    {
        $child->setItem($this->getItem());
    }

    /**
     * Retrieve column related javascript code
     *
     * @return string
     */
    public function getJs()
    {
        $js = '';
        foreach ($this->getSortedChildBlocks() as $child) {
            $js .= $child->getJs();
        }
        return $js;
    }
}
