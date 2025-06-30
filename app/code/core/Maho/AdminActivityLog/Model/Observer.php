<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

            // Don't log index events
            if ($object instanceof Mage_Index_Model_Event) {
                return;
            }

            $objectHash = spl_object_hash($object);
            $oldData = $this->_oldData[$objectHash] ?? [];
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
                'isAjax', 'ajax', 'callback', 'controller', 'action', 'module', 'update_time',
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
                    if (!in_array($key, $originalDataKeys) && !isset($oldData[$key])) {
                        continue;
                    }

                    $oldValue = $oldData[$key] ?? null;

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
                if (str_contains($fullActionName, $excluded)) {
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
        $class = $object::class;
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

        return $map[$class] ?? strtolower(str_replace('_Model_', '_', $class));
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

    public function logMassAction(Varien_Event_Observer $observer): void
    {
        if (!Mage::getStoreConfigFlag('admin/adminactivitylog/enabled')) {
            return;
        }

        if (!Mage::getStoreConfigFlag('admin/adminactivitylog/log_mass_actions')) {
            return;
        }

        if (!Mage::getSingleton('admin/session')->isLoggedIn()) {
            return;
        }

        $controllerAction = $observer->getEvent()->getControllerAction();
        $request = $controllerAction->getRequest();

        // Get all parameters for debugging
        $allParams = $request->getParams();
        $actionName = $request->getActionName();
        $controllerName = $request->getControllerName();
        $moduleName = $request->getModuleName();

        // Check if this is a mass action request - improved detection
        $isMassAction = false;

        // Check action name patterns
        if (stripos($actionName, 'mass') !== false) {
            $isMassAction = true;
        }

        // Check for massaction parameter
        if ($request->getParam('massaction') || $request->getParam('massaction_prepare_key')) {
            $isMassAction = true;
        }

        // Check for specific mass action URLs/controllers
        $massActionControllers = ['massaction'];
        if (in_array($controllerName, $massActionControllers)) {
            $isMassAction = true;
        }

        // Check for product mass update attributes specifically
        if ($controllerName === 'catalog_product_action_attribute' ||
            ($controllerName === 'catalog_product' && $actionName === 'massUpdateAttributes')) {
            $isMassAction = true;
        }

        if (!$isMassAction) {
            return;
        }

        // For mass attribute updates, only log the final "save" action to avoid duplicates
        if ($controllerName === 'catalog_product_action_attribute' && $actionName !== 'save') {
            return;
        }

        // Get selected IDs for mass actions - try all possible parameter names
        $selectedIds = [];

        // For mass attribute updates, look for the product IDs in the right place
        if ($controllerName === 'catalog_product_action_attribute') {
            // Product IDs are stored in the adminhtml session during mass attribute updates
            $session = Mage::getSingleton('adminhtml/session');
            $productIds = $session->getProductIds();

            if (empty($productIds)) {
                // Fallback: check request parameters
                $productIds = $request->getParam('product', []);
                if (empty($productIds)) {
                    $productIds = $request->getParam('selected', []);
                }
                if (empty($productIds)) {
                    $productIds = $request->getParam('entity_id', []);
                }
                if (empty($productIds)) {
                    // Look in POST data
                    $postData = $request->getPost();
                    if (isset($postData['product'])) {
                        $productIds = $postData['product'];
                    }
                }
            }
            $selectedIds = is_array($productIds) ? $productIds : [$productIds];
        } else {
            // Try different parameter names used by different mass actions
            $possibleParams = ['selected', 'massaction', 'entity_id', 'product', 'product_ids', 'ids'];
            foreach ($possibleParams as $param) {
                $ids = $request->getParam($param, []);
                if (!empty($ids)) {
                    $selectedIds = is_array($ids) ? $ids : [$ids];
                    break;
                }
            }
        }

        if (empty($selectedIds)) {
            return;
        }

        $user = Mage::getSingleton('admin/session')->getUser();
        $entityCount = count($selectedIds);

        // Determine entity type from controller
        $entityType = $this->_getMassActionEntityType($controllerAction);

        // Create a clean, readable entity name
        $actionDescription = $this->_getReadableActionName($actionName, $controllerName);
        $entityName = "{$actionDescription} ({$entityCount} items)";

        // Get clean attribute data for mass attribute updates and product names
        $attributeData = [];
        $productNames = [];

        if ($actionName === 'save' && $controllerName === 'catalog_product_action_attribute') {
            $postData = $request->getPost();
            if (isset($postData['attributes']) && is_array($postData['attributes'])) {
                foreach ($postData['attributes'] as $attrCode => $attrValue) {
                    if ($attrValue !== '' && $attrValue !== null) {
                        $attributeData[$attrCode] = $attrValue;
                    }
                }
            }

            // Get product names for the selected IDs
            $productCollection = Mage::getModel('catalog/product')->getCollection()
                ->addFieldToFilter('entity_id', ['in' => array_slice($selectedIds, 0, 10)])
                ->addAttributeToSelect('name');

            foreach ($productCollection as $product) {
                $productNames[] = $product->getName() . ' (ID: ' . $product->getId() . ')';
            }
        }

        // Only store attribute changes in old_data/new_data, not action/controller info
        $oldDataToStore = [];
        $newDataToStore = $attributeData;

        // Create a meaningful entity name with product list
        if (!empty($productNames)) {
            $productList = implode("\n", $productNames);
            if (count($productNames) < $entityCount) {
                $remaining = $entityCount - count($productNames);
                $productList .= "\n(and {$remaining} more)";
            }
            $entityName = $productList;
        }

        $data = [
            'action_type' => 'mass_update',
            'entity_type' => $entityType,
            'entity_id' => null, // For mass actions, no single entity ID makes sense
            'entity_name' => $entityName,
            'username' => $user->getUsername(),
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'request_url' => Mage::helper('core/url')->getCurrentUrl(),
            'old_data' => json_encode($oldDataToStore),
            'new_data' => json_encode($newDataToStore),
        ];

        Mage::getModel('adminactivitylog/activity')->setData($data)->save();
    }

    protected function _getMassActionEntityType(Mage_Core_Controller_Varien_Action $controllerAction): string
    {
        $controllerName = $controllerAction->getRequest()->getControllerName();
        $moduleName = $controllerAction->getRequest()->getModuleName();

        // Clean up controller name and remove common suffixes
        $cleanController = str_replace(['_action_attribute', '_action'], '', $controllerName);

        // For admin module, just use the controller name without the module prefix
        if ($moduleName === 'admin' || $moduleName === 'adminhtml') {
            return $cleanController;
        }

        // For other modules, include module name if different from controller
        if ($cleanController !== $moduleName && !empty($cleanController)) {
            return $moduleName . '/' . $cleanController;
        }

        return $cleanController;
    }

    protected function _getReadableActionName(string $actionName, string $controllerName): string
    {
        // Handle specific controller/action combinations
        if ($controllerName === 'catalog_product_action_attribute') {
            return match ($actionName) {
                'edit' => 'Mass Edit Product Attributes',
                'save' => 'Mass Update Product Attributes',
                'validate' => 'Mass Validate Product Attributes',
                default => 'Mass Product Attribute Action',
            };
        }

        // Generic action name cleanup
        $readableNames = [
            'massDelete' => 'Mass Delete',
            'massStatus' => 'Mass Status Change',
            'massUpdateAttributes' => 'Mass Update Attributes',
            'massRefresh' => 'Mass Refresh',
            'massReindex' => 'Mass Reindex',
            'massDisable' => 'Mass Disable',
            'massEnable' => 'Mass Enable',
            'save' => 'Save',
            'edit' => 'Edit',
            'validate' => 'Validate',
        ];

        // Fallback: capitalize and clean up action name
        return $readableNames[$actionName] ?? ucfirst(str_replace('_', ' ', $actionName));
    }

    public function cleanOldLogs(): void
    {
        Mage::helper('adminactivitylog')->cleanOldLogs();
    }
}
