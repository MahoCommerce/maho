<?php

/**
 * The page of the link that confirms a new email: it shows the new email and posts the confirmation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Block_Account_Emailchange extends Mage_Core_Block_Template
{
    public function getNewEmail(): string
    {
        return (string) $this->getData('new_email');
    }

    public function getToken(): string
    {
        return (string) $this->getData('token');
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('customer/account/confirmEmailPost', ['_secure' => true]);
    }
}
