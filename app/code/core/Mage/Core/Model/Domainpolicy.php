<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Domainpolicy
{
    /**
     * X-Frame-Options allow (header is absent)
     */
    public const FRAME_POLICY_ALLOW = 1;

    /**
     * X-Frame-Options SAMEORIGIN
     */
    public const FRAME_POLICY_ORIGIN = 2;

    /**
     * Path to backend domain policy settings
     */
    public const XML_DOMAIN_POLICY_BACKEND = 'admin/security/domain_policy_backend';

    /**
     * Path to frontend domain policy settings
     */
    public const XML_DOMAIN_POLICY_FRONTEND = 'admin/security/domain_policy_frontend';

    /**
     * Security header configuration paths
     */
    public const XML_HSTS_ENABLED = 'admin/security/hsts_enabled';
    public const XML_HSTS_MAX_AGE = 'admin/security/hsts_max_age';
    public const XML_CONTENT_TYPE_OPTIONS = 'admin/security/content_type_options_enabled';
    public const XML_XSS_PROTECTION = 'admin/security/xss_protection_enabled';
    public const XML_REFERRER_POLICY = 'admin/security/referrer_policy';

    /**
     * Current store
     *
     * @var Mage_Core_Model_Store
     */
    protected $_store;

    /**
     * Mage_Core_Model_Domainpolicy constructor.
     * @param array $options
     * @throws Mage_Core_Model_Store_Exception
     */
    public function __construct($options = [])
    {
        $this->_store = $options['store'] ?? Mage::app()->getStore();
    }

    /**
     * Add security headers to response, depends on config settings
     *
     * @return $this
     */
    public function addDomainPolicyHeader(\Maho\Event\Observer $observer)
    {
        $action = $observer->getControllerAction();
        $response = $action->getResponse();

        // Add X-Frame-Options header (existing functionality)
        $policy = null;
        if ($action->getLayout()->getArea() === Mage_Core_Model_App_Area::AREA_ADMINHTML) {
            $policy = $this->getBackendPolicy();
        } elseif ($action->getLayout()->getArea() === Mage_Core_Model_App_Area::AREA_FRONTEND) {
            $policy = $this->getFrontendPolicy();
        }

        if ($policy) {
            $response->setHeader('X-Frame-Options', $policy, true);
        }

        // HTTP Strict Transport Security (HSTS)
        if ($this->isHstsEnabled() && $this->isSecureConnection()) {
            $hstsHeader = $this->buildHstsHeader();
            if ($hstsHeader) {
                $response->setHeader('Strict-Transport-Security', $hstsHeader, true);
            }
        }

        // X-Content-Type-Options
        if ($this->isContentTypeOptionsEnabled()) {
            $response->setHeader('X-Content-Type-Options', 'nosniff', true);
        }

        // X-XSS-Protection
        if ($this->isXssProtectionEnabled()) {
            $response->setHeader('X-XSS-Protection', '1; mode=block', true);
        }

        // Referrer-Policy
        $referrerPolicy = $this->getReferrerPolicy();
        if ($referrerPolicy) {
            $response->setHeader('Referrer-Policy', $referrerPolicy, true);
        }

        return $this;
    }

    /**
     * Get backend policy
     *
     * @return string|null
     */
    public function getBackendPolicy()
    {
        return $this->_getDomainPolicyByCode((int) (string) $this->_store->getConfig(self::XML_DOMAIN_POLICY_BACKEND));
    }

    /**
     * Get frontend policy
     *
     * @return string|null
     */
    public function getFrontendPolicy()
    {
        return $this->_getDomainPolicyByCode((int) (string) $this->_store->getConfig(self::XML_DOMAIN_POLICY_FRONTEND));
    }

    /**
     * Return string representation for policy code
     *
     * @param string $policyCode
     * @return string|null
     */
    protected function _getDomainPolicyByCode($policyCode)
    {
        $policy = match ($policyCode) {
            self::FRAME_POLICY_ALLOW => null,
            default => 'SAMEORIGIN',
        };

        return $policy;
    }

    public function isHstsEnabled(): bool
    {
        return (bool) $this->_store->getConfig(self::XML_HSTS_ENABLED);
    }

    public function isSecureConnection(): bool
    {
        return Mage::app()->getRequest()->isSecure();
    }

    public function buildHstsHeader(): ?string
    {
        $maxAge = (int) $this->_store->getConfig(self::XML_HSTS_MAX_AGE);
        if ($maxAge <= 0) {
            $maxAge = 31536000; // Default: 1 year
        }

        $header = "max-age={$maxAge}";

        return $header;
    }

    public function isContentTypeOptionsEnabled(): bool
    {
        return (bool) $this->_store->getConfig(self::XML_CONTENT_TYPE_OPTIONS);
    }

    public function isXssProtectionEnabled(): bool
    {
        return (bool) $this->_store->getConfig(self::XML_XSS_PROTECTION);
    }

    public function getReferrerPolicy(): ?string
    {
        $policy = $this->_store->getConfig(self::XML_REFERRER_POLICY);
        if ($policy && $policy != '0') {
            return (string) $policy;
        }
        return null;
    }

}
