<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
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
     * Add X-Frame-Options header to response, depends on config settings
     *
     * @return $this
     */
    public function addDomainPolicyHeader(Varien_Event_Observer $observer)
    {
        $action = $observer->getControllerAction();
        $policy = null;

        if ($action->getLayout()->getArea() === Mage_Core_Model_App_Area::AREA_ADMINHTML) {
            $policy = $this->getBackendPolicy();
        } elseif ($action->getLayout()->getArea() === Mage_Core_Model_App_Area::AREA_FRONTEND) {
            $policy = $this->getFrontendPolicy();
        }

        if ($policy) {
            $response = $action->getResponse();
            $response->setHeader('X-Frame-Options', $policy, true);
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
}
