<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_Block_Customer_Wishlist_Items extends Mage_Core_Block_Template
{
    /**
     * Retrieve table column object list
     *
     * @return array
     */
    public function getColumns()
    {
        $columns = [];
        foreach ($this->getSortedChildren() as $code) {
            $child = $this->getChild($code);
            if ($child->isEnabled()) {
                $columns[] = $child;
            }
        }
        return $columns;
    }
}
