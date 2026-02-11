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
 * Wishlist session model
 *
 * @package    Mage_Wishlist
 *
 * @method $this setSharingForm(array $value)
 */

class Mage_Wishlist_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('wishlist');
    }
}
