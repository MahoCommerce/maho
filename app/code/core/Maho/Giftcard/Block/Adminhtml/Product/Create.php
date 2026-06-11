<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Product_Create extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/product/create.phtml');
    }
}
