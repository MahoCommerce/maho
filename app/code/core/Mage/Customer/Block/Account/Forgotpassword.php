<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Customer account navigation sidebar
 *
 * @package    Mage_Customer
 */

class Mage_Customer_Block_Account_Forgotpassword extends Mage_Core_Block_Template
{
    public function setEmailValue(?string $value): static
    {
        return $this->setData('email_value', $value);
    }
}
