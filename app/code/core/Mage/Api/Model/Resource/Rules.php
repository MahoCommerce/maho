<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
}
