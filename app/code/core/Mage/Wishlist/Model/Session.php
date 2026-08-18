<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

/**
 * Wishlist session model
 *
 * @package    Mage_Wishlist
 */

class Mage_Wishlist_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('wishlist');
    }

    public function setSharingForm(?array $value): static
    {
        return $this->setData('sharing_form', $value);
    }
}
