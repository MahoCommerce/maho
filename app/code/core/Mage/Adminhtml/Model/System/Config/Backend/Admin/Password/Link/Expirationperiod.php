<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Backend_Admin_Password_Link_Expirationperiod extends Mage_Core_Model_Config_Data
{
    /**
     * Validate expiration period value before saving
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $resetPasswordLinkExpirationPeriod = (int) $this->getValue();

        if ($resetPasswordLinkExpirationPeriod < 1) {
            $resetPasswordLinkExpirationPeriod = (int) $this->getOldValue();
        }
        $this->setValue((string) $resetPasswordLinkExpirationPeriod);
        return $this;
    }
}
