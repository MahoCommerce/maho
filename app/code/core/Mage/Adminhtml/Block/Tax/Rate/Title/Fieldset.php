<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * Tax Rate Titles Fieldset
 */
class Mage_Adminhtml_Block_Tax_Rate_Title_Fieldset extends \Maho\Data\Form\Element\Fieldset
{
    #[\Override]
    public function getChildrenHtml()
    {
        return Mage::getBlockSingleton('adminhtml/tax_rate_title')->toHtml();
    }
}
