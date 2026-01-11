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

class Mage_Adminhtml_Customer_System_Config_ValidatevatController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/config';

    /**
     * Perform customer VAT ID validation
     *
     * @return \Maho\DataObject
     */
    protected function _validate()
    {
        return Mage::helper('customer')->checkVatNumber(
            $this->getRequest()->getParam('country'),
            $this->getRequest()->getParam('vat'),
        );
    }

    /**
     * Check whether vat is valid
     */
    public function validateAction(): void
    {
        $result = $this->_validate();
        $this->getResponse()->setBody((string) (int) $result->getIsValid());
    }

    /**
     * Retrieve validation result as JSON
     */
    public function validateAdvancedAction(): void
    {
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');

        $result = $this->_validate();
        $valid = $result->getIsValid();
        $success = $result->getRequestSuccess();
        // ID of the store where order is placed
        $storeId = $this->getRequest()->getParam('store_id');
        // Sanitize value if needed
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
        ]);
    }
}
