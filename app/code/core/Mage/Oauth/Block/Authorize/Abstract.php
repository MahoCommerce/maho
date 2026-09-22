<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
 */
abstract class Mage_Oauth_Block_Authorize_Abstract extends Mage_Core_Block_Template
{
    /**
     * Helper
     *
     * @var Mage_Oauth_Helper_Data
     */
    protected $_helper;

    /**
     * Consumer model
     *
     * @var Mage_Oauth_Model_Consumer
     */
    protected $_consumer;

    public function __construct()
    {
        parent::__construct();
        $this->_helper = Mage::helper('oauth');
    }

    /**
     * Get consumer instance by token value
     *
     * @return Mage_Oauth_Model_Consumer
     */
    public function getConsumer()
    {
        if ($this->_consumer === null) {
            /** @var Mage_Oauth_Model_Token $token */
            $token = Mage::getModel('oauth/token');
            $token->load($this->getToken(), 'token');
            $this->_consumer = $token->getConsumer();
        }
        return $this->_consumer;
    }

    /**
     * Get absolute path to template
     *
     * Load template from adminhtml/default area flag is_simple is set
     *
     * @return string
     */
    #[\Override]
    public function getTemplateFile()
    {
        if (!$this->getIsSimple()) {
            return parent::getTemplateFile();
        }

        //load base template from admin area
        $params = [
            '_relative' => true,
            '_area'     => 'adminhtml',
            '_package'  => 'default',
        ];
        return Mage::getDesign()->getTemplateFilename($this->getTemplate(), $params);
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

    public function getIsSimple(): ?bool
    {
        $value = $this->getData('is_simple');
        return $value === null ? null : (bool) $value;
    }

    public function setIsSimple(?bool $value = true): static
    {
        return $this->setData('is_simple', $value);
    }

    public function getHasException(): ?bool
    {
        $value = $this->getData('has_exception');
        return $value === null ? null : (bool) $value;
    }

    public function setHasException(?bool $value = true): static
    {
        return $this->setData('has_exception', $value);
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

    public function getIsLogged(): ?bool
    {
        $value = $this->getData('is_logged');
        return $value === null ? null : (bool) $value;
    }

    public function setIsLogged(?bool $value = true): static
    {
        return $this->setData('is_logged', $value);
    }
}
