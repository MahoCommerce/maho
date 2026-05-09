<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Admin VAT validation controller
 *
 * Provides AJAX endpoint for VAT number validation in admin panel.
 * Uses cached VIES validation with format pre-check.
 */

class Mage_Adminhtml_Customer_System_Config_ValidatevatController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/config';

    /**
     * Perform customer VAT ID validation with caching
     *
     * Supports both parameter naming conventions:
     * - 'vat' (legacy admin)
     * - 'vat_number' (frontend validate-vat)
     */
    protected function _validate(): \Maho\DataObject
    {
        $country = trim((string) $this->getRequest()->getParam('country'));
        $vatNumber = trim((string) (
            $this->getRequest()->getParam('vat_number')
            ?: $this->getRequest()->getParam('vat')
        ));

        return Mage::helper('customer')->checkVatNumber($country, $vatNumber);
    }

    /**
     * Validate VAT number
     *
     * Detects AJAX requests and returns appropriate response format:
     * - AJAX: JSON with full validation details
     * - Non-AJAX: Plain text "0" or "1" (legacy)
     */
    #[Maho\Config\Route('/admin/customer_system_config_validatevat/validate')]
    public function validateAction(): void
    {
        $result = $this->_validate();

        // Detect AJAX request and return JSON
        if ($this->getRequest()->isAjax()) {
            $this->getResponse()->setBodyJson([
                'error' => 0,
                'valid' => (bool) $result->getIsValid(),
                'format_valid' => (bool) $result->getFormatValid(),
                'success' => (bool) $result->getRequestSuccess(),
                'message' => (string) $result->getMessage(),
                'cached' => (bool) $result->getCached(),
                'format_only' => (bool) $result->getFormatOnly(),
                'country_supported' => $result->getCountrySupported() !== false,
            ]);
            return;
        }

        // Legacy plain text response
        $this->getResponse()->setBody((string) (int) $result->getIsValid());
    }

    /**
     * Retrieve validation result as JSON with customer group suggestion
     *
     * Used by the "Validate VAT Number" button in order create form.
     */
    #[Maho\Config\Route('/admin/customer_system_config_validatevat/validateAdvanced')]
    public function validateAdvancedAction(): void
    {
        $result = $this->_validate();
        $valid = $result->getIsValid();
        $success = $result->getRequestSuccess();

        // ID of the store where order is placed
        $storeId = $this->getRequest()->getParam('store_id');
        if (!is_null($storeId)) {
            $storeId = (int) $storeId;
        }

        $groupId = Mage::helper('customer')->getCustomerGroupIdBasedOnVatNumber(
            $this->getRequest()->getParam('country'),
            $result,
            $storeId,
        );

        $this->getResponse()->setBodyJson([
            'valid' => $valid,
            'group' => $groupId,
            'success' => $success,
            'cached' => (bool) $result->getCached(),
            'format_valid' => (bool) $result->getFormatValid(),
            'format_only' => (bool) $result->getFormatOnly(),
            'message' => (string) $result->getMessage(),
        ]);
    }
}
