<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Block_Form_Remember extends Mage_Core_Block_Template
{
    /**
     * Prevent rendering if Remember me is disabled
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (Mage::getStoreConfigFlag('web/cookie/remember_enabled')) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * Is "Remember Me" checked
     *
     * @return bool
     */
    public function isRememberMeChecked()
    {
        return Mage::getStoreConfigFlag('web/cookie/remember_default');
    }
}
