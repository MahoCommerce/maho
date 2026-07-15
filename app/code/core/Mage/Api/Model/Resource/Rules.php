<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

class Mage_Api_Model_Resource_Rules extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/rule', 'rule_id');
    }

    /**
     * Save rule
     */
    public function saveRel(Mage_Api_Model_Rules $rule)
    {
        $permission = '';
        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            $roleId = $rule->getRoleId();
            $adapter->delete($this->getMainTable(), ['role_id = ?' => $roleId]);
            $masterResources = Mage::getModel('api/roles')->getResourcesList2D();
            $masterAdmin = false;
            if ($postedResources = $rule->getResources()) {
                foreach ($masterResources as $index => $resName) {
                    if (!$masterAdmin) {
                        $permission = (in_array($resName, $postedResources)) ? 'allow' : 'deny';
                        $adapter->insert($this->getMainTable(), [
                            'role_type'     => 'G',
                            'resource_id'   => trim($resName, '/'),
                            'api_privileges'    => null,
                            'assert_id'     => 0,
                            'role_id'       => $roleId,
                            'api_permission'    => $permission,
                        ]);
                    }
                    if ($resName == 'all' && $permission == 'allow') {
                        $masterAdmin = true;
                    }
                }
            }

            $adapter->commit();
        } catch (Mage_Core_Exception $e) {
            $adapter->rollBack();
            throw $e;
        } catch (Exception $e) {
            $adapter->rollBack();
        }
    }

    /**
     * Set resource ID as ID field name
     * @see Mage_Adminhtml_Block_Api_OrphanedResource_Grid::_prepareCollection()
     *
     * @return $this
     */
    public function setResourceIdAsIdFieldName()
    {
        $this->_idFieldName = 'resource_id';
        return $this;
    }

    /**
     * Valid resource IDs across every stack that stores rules in api_rule.
     *
     * The table is shared: Mage_Api (SOAP/XML-RPC) declares its resources in the
     * api.xml ACL tree, while Maho_ApiPlatform (REST/GraphQL) declares its own in
     * the permission registry. Orphan detection must consider both, or it would
     * flag (and offer to delete) every API Platform rule. The registry is only
     * unioned in when that module is installed, keeping Mage_Api decoupled.
     *
     * @return list<string>
     */
    protected function getValidResourceIds(): array
    {
        $valid = Mage::getModel('api/roles')->getResourcesList2D();

        if (class_exists(\Maho\ApiPlatform\Security\ApiPermissionRegistry::class)) {
            $valid = array_merge($valid, (new \Maho\ApiPlatform\Security\ApiPermissionRegistry())->getPermissionIds());
        }

        return $valid;
    }

    /**
     * Get collection of orphaned resources (in database but no longer defined in any API ACL)
     */
    public function getOrphanedResourcesCollection(): Mage_Core_Model_Resource_Db_Collection_Abstract
    {
        $collection = Mage::getResourceModel('api/rules_collection')
            ->addFieldToFilter('resource_id', ['nin' => $this->getValidResourceIds()])
            ->addFieldToSelect('resource_id');
        $collection->getSelect()->group('resource_id');
        return $collection;
    }

    /**
     * Delete orphaned resources
     *
     * @throws Mage_Core_Exception
     */
    public function deleteOrphanedResources(array $orphanedIds): int
    {
        if ($orphanedIds === []) {
            return 0;
        }

        $validIds = array_intersect($orphanedIds, $this->getValidResourceIds());
        if ($validIds !== []) {
            throw new Mage_Core_Exception(
                Mage::helper('adminhtml')->__(
                    'The following role resource(s) are not orphaned: %s',
                    implode(', ', $validIds),
                ),
            );
        }

        return $this->_getWriteAdapter()
            ->delete($this->getMainTable(), ['resource_id IN (?)' => $orphanedIds]);
    }
}
