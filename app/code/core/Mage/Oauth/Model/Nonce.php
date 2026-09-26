<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

declare(strict_types=1);

/**
 * @method Mage_Oauth_Model_Resource_Nonce getResource()
 * @method Mage_Oauth_Model_Resource_Nonce _getResource()
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
 */
class Mage_Oauth_Model_Nonce extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('oauth/nonce');
    }

    /**
     * "After save" actions
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();

        //Cleanup old entries
        /** @var Mage_Oauth_Helper_Data $helper */
        $helper = Mage::helper('oauth');
        if ($helper->isCleanupProbability()) {
            $this->_getResource()->deleteOldEntries($helper->getCleanupExpirationPeriod());
        }
        return $this;
    }

    public function getNonce(): ?string
    {
        $value = $this->getData('nonce');
        return $value === null ? null : (string) $value;
    }

    public function setNonce(?string $value): static
    {
        return $this->setData('nonce', $value);
    }

    public function getTimestamp(): ?int
    {
        $value = $this->getData('timestamp');
        return $value === null ? null : (int) $value;
    }

    public function setTimestamp(?int $value): static
    {
        return $this->setData('timestamp', $value);
    }

}
