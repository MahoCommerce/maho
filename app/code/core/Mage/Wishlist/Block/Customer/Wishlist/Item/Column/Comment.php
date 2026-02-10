<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Wishlist_Block_Customer_Wishlist_Item_Column_Comment extends Mage_Wishlist_Block_Customer_Wishlist_Item_Column
{
    /**
     * Retrieve column javascript code
     *
     * @return string
     */
    #[\Override]
    public function getJs()
    {
        /** @var Mage_Wishlist_Helper_Data $helper */
        $helper = $this->helper('wishlist');

        return parent::getJs() . "
        function focusComment(obj) {
            if( obj.value == '" . $helper->defaultCommentString() . "' ) {
                obj.value = '';
            } else if( obj.value == '' ) {
                obj.value = '" . $helper->defaultCommentString() . "';
            }
        }
        ";
    }
}
