<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @method Mage_Oauth_Model_Resource_Consumer _getResource()
 * @method Mage_Oauth_Model_Resource_Consumer getResource()
 * @method Mage_Oauth_Model_Resource_Consumer_Collection getCollection()
 * @method Mage_Oauth_Model_Resource_Consumer_Collection getResourceCollection()
 * @method string getName()
 * @method $this setName(string $name)
 * @method string getKey()
 * @method $this setKey(string $key)
 * @method string getSecret()
 * @method $this setSecret(string $secret)
 * @method string getCallbackUrl()
 * @method $this setCallbackUrl(string $url)
 * @method string getUpdatedAt()
 * @method string getRejectedCallbackUrl()
 * @method $this setRejectedCallbackUrl(string $rejectedCallbackUrl)
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
 */
class Mage_Oauth_Model_Consumer extends Mage_Core_Model_Abstract
{
    /**
     * Key hash length
     */
    public const KEY_LENGTH = 32;

    /**
     * Secret hash length
     */
    public const SECRET_LENGTH = 32;

    #[\Override]
    protected function _construct()
    {
        $this->_init('oauth/consumer');
    }

    /**
     * BeforeSave actions
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->setUpdatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        $this->setCallbackUrl(trim((string) $this->getCallbackUrl()));
        $this->setRejectedCallbackUrl(trim((string) $this->getRejectedCallbackUrl()));
        $this->validate();
        parent::_beforeSave();
        return $this;
    }

    /**
     * Validate data
     *
     * @return bool
     * @throw Mage_Core_Exception|Exception   Throw exception on fail validation
     */
    public function validate()
    {
        /** @var Mage_Oauth_Model_Consumer_Validator_KeyLength $validatorLength */
        $validatorLength = Mage::getModel('oauth/consumer_validator_keyLength', ['length' => self::KEY_LENGTH]);

        $validatorLength->setName('Consumer Key');
        if (!$validatorLength->isValid($this->getKey())) {
            $messages = $validatorLength->getMessages();
            Mage::throwException(array_shift($messages));
        }

        $validatorLength->setLength(self::SECRET_LENGTH);
        $validatorLength->setName('Consumer Secret');
        if (!$validatorLength->isValid($this->getSecret())) {
            $messages = $validatorLength->getMessages();
            Mage::throwException(array_shift($messages));
        }

        // The callback allowlist trusts these values, so a URL it cannot match is refused here
        foreach (['callback_url' => 'Callback URL', 'rejected_callback_url' => 'Rejected Callback URL'] as $field => $label) {
            $url = (string) $this->getData($field);
            if ($url === '') {
                continue;
            }
            $parts = parse_url($url);
            if ($parts === false || !isset($parts['scheme']) || isset($parts['user'])) {
                Mage::throwException(Mage::helper('oauth')->__('%s must be an absolute URL without user info.', Mage::helper('oauth')->__($label)));
            }
        }
        return true;
    }

}
