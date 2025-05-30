<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
