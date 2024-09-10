<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wishlist block customer item column
 *
 * @category   Mage
 * @package    Mage_Wishlist
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
