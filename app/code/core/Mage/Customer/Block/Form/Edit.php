<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer edit form block
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Form_Edit extends Mage_Customer_Block_Account_Dashboard
{
    /**
     * Return all EAV fields used in this form as groups
     */
    public function getGroupedFields(): array
    {
        return Mage::helper('customer')->getGroupedFields('customer_account_edit', $this->getCustomer());
    }

    /**
     * Return extra EAV fields used in this form
     */
    public function getExtraFields(): array
    {
        return Mage::helper('customer')->getExtraFields('customer_account_edit', $this->getCustomer());
    }
}
