<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @method Mage_Oauth_Model_Resource_Nonce getResource()
 * @method Mage_Oauth_Model_Resource_Nonce _getResource()
 * @method string getNonce()
 * @method $this setNonce(string $nonce)
 * @method string getTimestamp()
 * @method $this setTimestamp(string $timestamp)
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
}
