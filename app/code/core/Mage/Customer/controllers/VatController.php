<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * VAT validation frontend controller
 *
 * Provides AJAX endpoint for real-time VAT number validation.
 */
class Mage_Customer_VatController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get customer helper (contains VAT validation methods)
     */
    protected function _getVatHelper(): Mage_Customer_Helper_Data
    {
        return Mage::helper('customer');
    }

    /**
     * Validate VAT number via AJAX
     *
     * Expected POST parameters:
     * - form_key: Form key for CSRF protection
     * - country: ISO-2 country code (e.g., 'DE', 'FR')
     * - vat_number: VAT number to validate
     *
     * Response JSON:
     * - valid: bool
     * - format_valid: bool
     * - success: bool (request to VIES succeeded)
     * - message: string
     * - cached: bool
     * - format_only: bool
     */
    public function validateAction(): void
    {
        $result = [
            'valid' => false,
            'format_valid' => false,
            'success' => false,
            'message' => '',
            'cached' => false,
            'format_only' => false,
        ];

        try {
            $vatHelper = $this->_getVatHelper();

            // Validate request method
            if (!$this->getRequest()->isPost()) {
                $result['message'] = $this->__('Invalid request method.');
                $this->_sendJsonResponse($result);
                return;
            }

            // Validate form key (CSRF protection)
            if (!$this->_validateFormKey()) {
                $result['message'] = $this->__('Invalid form key. Please refresh the page.');
                $this->_sendJsonResponse($result);
                return;
            }

            // Get and validate parameters (use getPost for POST data)
            $country = trim((string) $this->getRequest()->getPost('country'));
            $vatNumber = trim((string) $this->getRequest()->getPost('vat_number'));

            if (empty($country)) {
                $result['message'] = $this->__('Country is required.');
                $this->_sendJsonResponse($result);
                return;
            }

            if (empty($vatNumber)) {
                $result['message'] = $this->__('VAT number is required.');
                $this->_sendJsonResponse($result);
                return;
            }

            // Check if country is supported
            if (!$vatHelper->hasVatFormatPattern($country)) {
                $result['country_supported'] = false;
                $this->_sendJsonResponse($result);
                return;
            }

            // Perform validation
            $validationResult = $vatHelper->checkVatNumber($country, $vatNumber);

            $result = [
                'valid' => (bool) $validationResult->getIsValid(),
                'format_valid' => (bool) $validationResult->getFormatValid(),
                'success' => (bool) $validationResult->getRequestSuccess(),
                'message' => (string) $validationResult->getMessage(),
                'cached' => (bool) $validationResult->getCached(),
                'format_only' => (bool) $validationResult->getFormatOnly(),
                'country_supported' => true,
            ];
        } catch (Exception $e) {
            Mage::log('VAT validation controller error: ' . $e->getMessage(), Mage::LOG_ERROR);
            $result['message'] = $this->__('An error occurred during validation. Please try again.');
        }

        $this->_sendJsonResponse($result);
    }

    /**
     * Send JSON response
     *
     * @param array<string, mixed> $data
     */
    protected function _sendJsonResponse(array $data): void
    {
        $this->getResponse()->setBodyJson($data);
    }
}
