<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Block_Form_Edit extends Mage_Customer_Block_Account_Dashboard
{
    /**
     * The new email that waits for its confirmation link, or null when there is none.
     */
    public function getPendingEmail(): ?string
    {
        return Mage::getService('customer/customer')->getPendingEmail($this->getCustomer());
    }
}
