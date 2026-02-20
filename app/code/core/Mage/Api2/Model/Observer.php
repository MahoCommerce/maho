<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Observer
{
    /**
     * Save relation of admin user to API2 role
     */
    public function saveAdminToRoleRelation(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Admin_Model_User $user Object */
        $user = $observer->getObject();

        if ($user->hasData('api2_roles')) {
            $roles = $user->getData('api2_roles');

            if (!is_array($roles) || !isset($roles[0])) {
                throw new Exception('API2 roles property has wrong data format.');
            }

            /** @var Mage_Api2_Model_Resource_Acl_Global_Role $resourceModel */
            $resourceModel = Mage::getResourceModel('api2/acl_global_role');
            $resourceModel->saveAdminToRoleRelation($user->getId(), $roles[0]);
        }
    }

    /**
     * After save attribute if it is not visible on front remove it from Attribute ACL
     *
     * @return $this
     */
    public function catalogAttributeSaveAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = $observer->getEvent()->getAttribute();
        if ($attribute->getIsUserDefined() && $attribute->dataHasChangedFor('is_visible_on_front')
            && !$attribute->getIsVisibleOnFront()
        ) {
            /** @var Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection $collection */
            $collection = Mage::getResourceModel('api2/acl_filter_attribute_collection');
            /** @var Mage_Api2_Model_Acl_Filter_Attribute $aclFilter */
            foreach ($collection as $aclFilter) {
                if ($aclFilter->getResourceId() != Mage_Api2_Model_Acl_Global_Rule::RESOURCE_ALL) {
                    $allowedAttributes = explode(',', $aclFilter->getAllowedAttributes());
                    $allowedAttributes = array_diff($allowedAttributes, [$attribute->getAttributeCode()]);
                    $aclFilter->setAllowedAttributes(implode(',', $allowedAttributes))->save();
                }
            }
        }

        return $this;
    }

    /**
     * Upgrade API key hash when api user has logged in
     *
     * @param \Maho\Event\Observer $observer
     */
    public function upgradeApiKey($observer)
    {
        $apiKey = $observer->getEvent()->getApiKey();
        $model = $observer->getEvent()->getModel();
        if (!(bool) $model->getApiPasswordUpgraded()
            && !Mage::helper('core')->getEncryptor()->validateHashByVersion(
                $apiKey,
                $model->getApiKey(),
                Mage_Core_Model_Encryption::HASH_VERSION_SHA256,
            )
        ) {
            Mage::getModel('api/user')->load($model->getId())->setNewApiKey($apiKey)->save();
            $model->setApiPasswordUpgraded(true);
        }
    }
}
