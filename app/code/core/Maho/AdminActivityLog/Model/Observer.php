<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Model_Observer
{
    protected array $_oldData = [];

    public function logAdminLogin(Varien_Event_Observer $observer): void
    {
        try {
            $user = $observer->getEvent()->getUser();
            if ($user && $user->getId()) {
                Mage::getModel('adminactivitylog/login')->logLogin($user);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminLogout(Varien_Event_Observer $observer): void
    {
        try {
            $user = Mage::getSingleton('admin/session')->getUser();
            if ($user && $user->getId()) {
                Mage::getModel('adminactivitylog/login')->logLogout($user);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminLoginFailed(Varien_Event_Observer $observer): void
    {
        try {
            $username = $observer->getEvent()->getUserName();
            $exception = $observer->getEvent()->getException();
            $reason = $exception ? $exception->getMessage() : 'Unknown error';

            if ($username) {
                Mage::getModel('adminactivitylog/login')->logFailedLogin($username, $reason);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminActivityBefore(Varien_Event_Observer $observer): void
    {
        try {
            if (!$this->_shouldLogActivity() || !$this->_isAdminArea()) {
                return;
            }

            $object = $observer->getEvent()->getObject();
            if (!$object || !$object->getId()) {
                return;
            }

            $objectHash = spl_object_hash($object);
            $this->_oldData[$objectHash] = $object->getOrigData();
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminActivityAfter(Varien_Event_Observer $observer): void
    {
        try {
            if (!$this->_shouldLogActivity() || !$this->_isAdminArea()) {
                return;
            }

            if (!Mage::getStoreConfigFlag('admin/adminactivitylog/log_save_actions')) {
                return;
            }

            $object = $observer->getEvent()->getObject();
            if (!$object) {
                return;
            }

            // Don't log AdminActivityLog model saves to prevent infinite loops
            if ($object instanceof Maho_AdminActivityLog_Model_Activity || $object instanceof Maho_AdminActivityLog_Model_Login) {
                return;
            }

            $objectHash = spl_object_hash($object);
            $oldData = isset($this->_oldData[$objectHash]) ? $this->_oldData[$objectHash] : [];
            $isNew = empty($oldData);

            $entityType = $this->_getEntityType($object);
            $entityName = $this->_getEntityName($object);

            $changedData = [];
            $oldChangedData = [];
            $newChangedData = [];

            // Fields to ignore (system fields, timestamps, and form fields)
            $ignoreFields = [
                'updated_at', 'created_at', 'entity_id', 'has_options', 'required_options',
                'form_key', 'key', 'uenc', 'form_token', 'session_id', '_store', '_redirect',
                'isAjax', 'ajax', 'callback', 'controller', 'action', 'module', 'update_time'
            ];

            if (!$isNew) {
                // Get the original data keys to ensure we only track DB fields
                $originalDataKeys = array_keys($oldData);
                
                foreach ($object->getData() as $key => $value) {
                    // Skip ignored fields
                    if (in_array($key, $ignoreFields)) {
                        continue;
                    }

                    // Skip fields that weren't in the original data (likely not DB fields)
                    if (!$isNew && !in_array($key, $originalDataKeys) && !isset($oldData[$key])) {
                        continue;
                    }

                    $oldValue = isset($oldData[$key]) ? $oldData[$key] : null;

                    // Only log if there's a meaningful change
                    if ($oldValue != $value) {
                        // Skip changes where both old and new are null/empty
                        if (($oldValue === null || $oldValue === '') && ($value === null || $value === '')) {
                            continue;
                        }

                        $changedData[$key] = [
                            'old' => $oldValue,
                            'new' => $value,
                        ];
                        $oldChangedData[$key] = $oldValue;
                        $newChangedData[$key] = $value;
                    }
                }
            }

            $data = [
                'action_type' => $isNew ? 'create' : 'update',
                'module' => $this->_getCurrentModule(),
                'controller' => $this->_getCurrentController(),
                'action' => $this->_getCurrentAction(),
                'entity_type' => $entityType,
                'entity_id' => $object->getId(),
                'entity_name' => $entityName,
                'old_data' => $isNew ? [] : $oldChangedData,
                'new_data' => $isNew ? $object->getData() : $newChangedData,
            ];

            Mage::getModel('adminactivitylog/activity')->logActivity($data);

            unset($this->_oldData[$objectHash]);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminDelete(Varien_Event_Observer $observer): void
    {
        try {
            if (!$this->_shouldLogActivity() || !$this->_isAdminArea()) {
                return;
            }

            if (!Mage::getStoreConfigFlag('admin/adminactivitylog/log_delete_actions')) {
                return;
            }

            $object = $observer->getEvent()->getObject();
            if (!$object || !$object->getId()) {
                return;
            }

            $data = [
                'action_type' => 'delete',
                'module' => $this->_getCurrentModule(),
                'controller' => $this->_getCurrentController(),
                'action' => $this->_getCurrentAction(),
                'entity_type' => $this->_getEntityType($object),
                'entity_id' => $object->getId(),
                'entity_name' => $this->_getEntityName($object),
                'old_data' => $object->getData(),
            ];

            Mage::getModel('adminactivitylog/activity')->logActivity($data);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logPageVisit(Varien_Event_Observer $observer): void
    {
        try {
            if (!$this->_shouldLogActivity() || !Mage::getStoreConfigFlag('admin/adminactivitylog/log_page_visit')) {
                return;
            }

            $controllerAction = $observer->getEvent()->getControllerAction();
            if (!$controllerAction) {
                return;
            }

            $excludedActions = ['adminactivitylog_activity', 'adminactivitylog_login', 'ajax', 'notifications'];
            $fullActionName = $controllerAction->getFullActionName();

            foreach ($excludedActions as $excluded) {
                if (strpos($fullActionName, $excluded) !== false) {
                    return;
                }
            }

            $data = [
                'action_type' => 'page_visit',
                'module' => $this->_getCurrentModule(),
                'controller' => $this->_getCurrentController(),
                'action' => $this->_getCurrentAction(),
            ];

            Mage::getModel('adminactivitylog/activity')->logActivity($data);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    protected function _shouldLogActivity(): bool
    {
        return Mage::getStoreConfigFlag('admin/adminactivitylog/enabled')
            && Mage::getSingleton('admin/session')->isLoggedIn();
    }

    protected function _isAdminArea(): bool
    {
        return Mage::app()->getStore()->isAdmin();
    }

    protected function _getCurrentModule(): string
    {
        return (string) Mage::app()->getRequest()->getModuleName();
    }

    protected function _getCurrentController(): string
    {
        return (string) Mage::app()->getRequest()->getControllerName();
    }

    protected function _getCurrentAction(): string
    {
        return (string) Mage::app()->getRequest()->getActionName();
    }

    protected function _getEntityType(Mage_Core_Model_Abstract $object): string
    {
        $class = get_class($object);
        $map = [
            'Mage_Catalog_Model_Product' => 'product',
            'Mage_Catalog_Model_Category' => 'category',
            'Mage_Customer_Model_Customer' => 'customer',
            'Mage_Sales_Model_Order' => 'order',
            'Mage_Cms_Model_Page' => 'cms_page',
            'Mage_Cms_Model_Block' => 'cms_block',
            'Mage_Admin_Model_User' => 'admin_user',
            'Mage_Admin_Model_Role' => 'admin_role',
        ];

        return isset($map[$class]) ? $map[$class] : strtolower(str_replace('_Model_', '_', $class));
    }

    protected function _getEntityName(Mage_Core_Model_Abstract $object): string
    {
        $nameFields = ['name', 'title', 'sku', 'increment_id', 'username', 'email', 'identifier'];

        foreach ($nameFields as $field) {
            if ($object->hasData($field) && $object->getData($field)) {
                return (string) $object->getData($field);
            }
        }

        return 'ID: ' . $object->getId();
    }

    public function cleanOldLogs(): void
    {
        Mage::helper('adminactivitylog')->cleanOldLogs();
    }
}
