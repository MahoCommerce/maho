<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

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
