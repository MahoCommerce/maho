<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_Resource_Abstract
{
    /**
     * Resource configuration
     *
     * @var \Maho\Simplexml\Element
     */
    protected $_resourceConfig = null;

    /**
     * Fields that an API read never returns, for every entity type.
     *
     * These are card numbers and security codes (plain or encrypted), gateway debug payloads that can contain them,
     * and the encrypted password that a quote keeps for a customer who registers during checkout. This list applies
     * to reads only: $_ignoredAttributeCodes also controls writes, and subclasses replace its entries.
     *
     * @var string[]
     */
    protected array $_secretFields = [
        'cc_number',
        'cc_number_enc',
        'cc_cid',
        'cc_cid_enc',
        'cc_debug_request_body',
        'cc_debug_response_body',
        'cc_debug_response_serialized',
        'password_hash',
    ];

    /**
     * Keys of the additional_information of a payment that an API read never returns.
     *
     * PayPal Payflow Link keeps the secret of its silent post callback and the secure token of its hosted form here.
     *
     * @var string[]
     */
    protected array $_paymentSecretInformationKeys = ['secure_silent_post_hash', 'secure_token', 'secure_token_id'];

    /**
     * Remove the stored secrets from entity data that the API returns.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function _removeSecrets(array $data): array
    {
        foreach ($this->_secretFields as $field) {
            unset($data[$field]);
        }

        if (isset($data['additional_information']) && is_array($data['additional_information'])) {
            foreach ($this->_paymentSecretInformationKeys as $key) {
                unset($data['additional_information'][$key]);
            }
        }

        return $data;
    }

    /**
     * Retrieve webservice session
     *
     * @return Mage_Api_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('api/session');
    }

    /**
     * Retrieve webservice configuration
     *
     * @return Mage_Api_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('api/config');
    }

    /**
     * Set configuration for api resource
     *
     * @return $this
     */
    public function setResourceConfig(\Maho\Simplexml\Element $xml)
    {
        $this->_resourceConfig = $xml;
        return $this;
    }

    /**
     * Retrieve configuration for api resource
     *
     * @return \Maho\Simplexml\Element
     */
    public function getResourceConfig()
    {
        return $this->_resourceConfig;
    }

    /**
     * Retrieve webservice server
     *
     * @return Mage_Api_Model_Server
     */
    protected function _getServer()
    {
        return Mage::getSingleton('api/server');
    }

    /**
     * Dispatches fault
     *
     * @param string $code
     * @param string|null $customMessage
     * @throws Mage_Api_Exception
     */
    protected function _fault($code, $customMessage = null): never
    {
        throw new Mage_Api_Exception($code, $customMessage);
    }
}
