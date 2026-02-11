<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * Wishlist RSS URL to Email Block
 *
 * @package    Mage_Wishlist
 *
 * @method $this setWishlistId(int $value)
 */

class Mage_Wishlist_Block_Share_Email_Rss extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('wishlist/email/rss.phtml');
    }
}
