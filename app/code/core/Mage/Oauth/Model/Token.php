<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @method Mage_Oauth_Model_Resource_Token_Collection getCollection()
 * @method Mage_Oauth_Model_Resource_Token_Collection getResourceCollection()
 * @method Mage_Oauth_Model_Resource_Token getResource()
 * @method Mage_Oauth_Model_Resource_Token _getResource()
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
 */
class Mage_Oauth_Model_Token extends Mage_Core_Model_Abstract
{
    /**
     * Token types
     */
    public const TYPE_REQUEST = 'request';
    public const TYPE_ACCESS  = 'access';

    /**
     * Lengths of token fields
     */
    public const LENGTH_TOKEN    = 32;
    public const LENGTH_SECRET   = 32;
    public const LENGTH_VERIFIER = 32;

    /**
     * Customer types
     */
    public const USER_TYPE_ADMIN    = 'admin';
    public const USER_TYPE_CUSTOMER = 'customer';

    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('oauth/token');
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

    /**
     * Authorize token
     *
     * @param int $userId Authorization user identifier
     * @param string $userType Authorization user type
     * @return $this
     */
    public function authorize($userId, $userType)
    {
        if (!$this->getId() || !$this->getConsumerId()) {
            Mage::throwException('Token is not ready to be authorized');
        }
        if ($this->getAuthorized()) {
            Mage::throwException('Token is already authorized');
        }
        if (self::USER_TYPE_ADMIN == $userType) {
            $this->setAdminId($userId);
        } elseif (self::USER_TYPE_CUSTOMER == $userType) {
            $this->setCustomerId($userId);
        } else {
            Mage::throwException('User type is unknown');
        }
        /** @var Mage_Oauth_Helper_Data $helper */
        $helper = Mage::helper('oauth');

        $this->setVerifier($helper->generateVerifier());
        $this->setAuthorized(1);
        $this->save();

        $this->getResource()->cleanOldAuthorizedTokensExcept($this);

        return $this;
    }

    /**
     * Convert token to access type
     *
     * @return $this
     */
    public function convertToAccess()
    {
        if (self::TYPE_REQUEST != $this->getType()) {
            Mage::throwException('Can not convert due to token is not request type');
        }
        /** @var Mage_Oauth_Helper_Data $helper */
        $helper = Mage::helper('oauth');

        $this->setType(self::TYPE_ACCESS);
        $this->setToken($helper->generateToken());
        $this->setSecret($helper->generateTokenSecret());
        $this->save();

        return $this;
    }

    /**
     * Generate and save request token
     *
     * @param int $consumerId Consumer identifier
     * @param string $callbackUrl Callback URL
     * @return $this
     */
    public function createRequestToken($consumerId, $callbackUrl)
    {
        /** @var Mage_Oauth_Helper_Data $helper */
        $helper = Mage::helper('oauth');

        $this->setData([
            'consumer_id'  => $consumerId,
            'type'         => self::TYPE_REQUEST,
            'token'        => $helper->generateToken(),
            'secret'       => $helper->generateTokenSecret(),
            'callback_url' => $callbackUrl,
        ]);
        $this->save();

        return $this;
    }

    /**
     * Get OAuth user type
     *
     * @return string
     * @throws Exception
     */
    public function getUserType()
    {
        if ($this->getAdminId()) {
            return self::USER_TYPE_ADMIN;
        }
        if ($this->getCustomerId()) {
            return self::USER_TYPE_CUSTOMER;
        }
        Mage::throwException('User type is unknown');
    }

    /**
     * Get string representation of token
     *
     * @param string $format
     * @return string
     */
    #[\Override]
    public function toString($format = '')
    {
        return http_build_query(['oauth_token' => $this->getToken(), 'oauth_token_secret' => $this->getSecret()]);
    }

    /**
     * Before save actions
     *
     * @return Mage_Oauth_Model_Token
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->validate();

        if ($this->isObjectNew() && $this->getCreatedAt() === null) {
            $this->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        }
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
        $validatorLength = Mage::getModel(
            'oauth/consumer_validator_keyLength',
        );
        $validatorLength->setLength(self::LENGTH_SECRET);
        $validatorLength->setName('Token Secret Key');
        if (!$validatorLength->isValid($this->getSecret())) {
            $messages = $validatorLength->getMessages();
            Mage::throwException(array_shift($messages));
        }

        $validatorLength->setLength(self::LENGTH_TOKEN);
        $validatorLength->setName('Token Key');
        if (!$validatorLength->isValid($this->getToken())) {
            $messages = $validatorLength->getMessages();
            Mage::throwException(array_shift($messages));
        }

        if (($verifier = $this->getVerifier()) !== null) {
            $validatorLength->setLength(self::LENGTH_VERIFIER);
            $validatorLength->setName('Verifier Key');
            if (!$validatorLength->isValid($verifier)) {
                $messages = $validatorLength->getMessages();
                Mage::throwException(array_shift($messages));
            }
        }
        return true;
    }

    /**
     * Get Token Consumer
     *
     * @return Mage_Oauth_Model_Consumer
     */
    public function getConsumer()
    {
        if (!$this->getData('consumer')) {
            /** @var Mage_Oauth_Model_Consumer $consumer */
            $consumer = Mage::getModel('oauth/consumer');
            $consumer->load($this->getConsumerId());
            $this->setData('consumer', $consumer);
        }

        return $this->getData('consumer');
    }

    public function getAdminId(): ?int
    {
        $value = $this->getData('admin_id');
        return $value === null ? null : (int) $value;
    }

    public function setAdminId(?int $value): static
    {
        return $this->setData('admin_id', $value);
    }

    public function getAuthorized(): ?bool
    {
        $value = $this->getData('authorized');
        return $value === null ? null : (bool) $value;
    }

    public function setAuthorized(?bool $value): static
    {
        return $this->setData('authorized', $value);
    }

    public function getCallbackUrl(): ?string
    {
        $value = $this->getData('callback_url');
        return $value === null ? null : (string) $value;
    }

    public function setCallbackUrl(?string $value): static
    {
        return $this->setData('callback_url', $value);
    }

    public function getConsumerId(): ?int
    {
        $value = $this->getData('consumer_id');
        return $value === null ? null : (int) $value;
    }

    public function setConsumerId(?int $value): static
    {
        return $this->setData('consumer_id', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function getRevoked(): ?bool
    {
        $value = $this->getData('revoked');
        return $value === null ? null : (bool) $value;
    }

    public function setRevoked(?bool $value): static
    {
        return $this->setData('revoked', $value);
    }

    public function getSecret(): ?string
    {
        $value = $this->getData('secret');
        return $value === null ? null : (string) $value;
    }

    public function setSecret(?string $value): static
    {
        return $this->setData('secret', $value);
    }

    public function getToken(): ?string
    {
        $value = $this->getData('token');
        return $value === null ? null : (string) $value;
    }

    public function setToken(?string $value): static
    {
        return $this->setData('token', $value);
    }

    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function setType(?string $value): static
    {
        return $this->setData('type', $value);
    }

    public function getVerifier(): ?string
    {
        $value = $this->getData('verifier');
        return $value === null ? null : (string) $value;
    }

    public function setVerifier(?string $value): static
    {
        return $this->setData('verifier', $value);
    }

}
