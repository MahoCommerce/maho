<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * Auth session model
 *
 * @package    Mage_Adminhtml
 */

class Mage_Adminhtml_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('adminhtml');
    }

    public function getProductIds(bool $clear = false): array|string|null
    {
        return $this->getData('product_ids', $clear ?: null);
    }

    public function setProductIds(array|string|null $value): static
    {
        return $this->setData('product_ids', $value);
    }
}
