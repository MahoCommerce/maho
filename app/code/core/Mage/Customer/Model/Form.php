<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Form Model
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Form extends Mage_Eav_Model_Form
{
    /**
     * Current module pathname
     *
     * @var string
     */
    protected $_moduleName = 'customer';

    /**
     * Current EAV entity type code
     *
     * @var string
     */
    protected $_entityTypeCode = 'customer';

    /**
     * Get EAV Entity Form Attribute Collection for Customer
     * exclude 'created_at'
     *
     * @return Mage_Customer_Model_Resource_Form_Attribute_Collection
     */
    #[\Override]
    protected function _getFormAttributeCollection()
    {
        $collection = parent::_getFormAttributeCollection()
                    ->addFieldToFilter('ea.attribute_code', ['neq' => 'created_at']);

        $entity = $this->getEntity();
        $attributeSetId = null;

        if ($entity instanceof Mage_Customer_Model_Customer) {
            $group = Mage::getModel('customer/group')
                   ->load($entity->getGroupId());
            $attributeSetId = $group->getCustomerAttributeSetId();
        } elseif ($entity instanceof Mage_Customer_Model_Address) {
            $customer = $entity->getCustomer();
            if ($customer) {
                $group = Mage::getModel('customer/group')
                       ->load($customer->getGroupId());
                $attributeSetId = $group->getCustomerAddressAttributeSetId();
            }
        }

        if (!is_null($attributeSetId) && $attributeSetId != 0) {
            $collection->filterAttributeSet($attributeSetId);
        }

        return $collection;
    }
}
