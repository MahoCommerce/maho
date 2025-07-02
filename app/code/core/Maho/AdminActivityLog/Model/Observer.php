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
    protected ?string $_currentActionGroupId = null;

    /**
     * Fields to ignore (system fields, timestamps, and form fields)
     */
    protected array $ignoreFields = [
        'updated_at', 'created_at', 'entity_id', 'has_options', 'required_options',
        'form_key', 'key', 'uenc', 'form_token', 'session_id', '_store', '_redirect',
        'isAjax', 'ajax', 'callback', 'controller', 'action', 'module', 'update_time',
    ];

    /**
     * Get or generate action group ID for the current request
     */
    protected function _getActionGroupId(): string
    {
        if ($this->_currentActionGroupId === null) {
            // Generate a unique ID based on:
            // - Request start time (consistent during entire request)
            // - Admin session ID (to separate different users)
            // - Request URL (to separate different actions)
            $sessionId = Mage::getSingleton('admin/session')->getSessionId();
            $requestUrl = Mage::helper('core/url')->getCurrentUrl();
            // Use request start time instead of current microtime for consistency
            $timestamp = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

            // Create a hash of these components
            $this->_currentActionGroupId = hash('sha256', $sessionId . '|' . $requestUrl . '|' . $timestamp);
        }

        return $this->_currentActionGroupId;
    }

    public function logAdminLogin(Varien_Event_Observer $observer): void
    {
        try {
            if (!Mage::helper('adminactivitylog')->shouldLogAuth()) {
                return;
            }

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
            if (!Mage::helper('adminactivitylog')->shouldLogAuth()) {
                return;
            }

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
            if (!Mage::helper('adminactivitylog')->shouldLogFailedAuth()) {
                return;
            }

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
            if (!Mage::helper('adminactivitylog')->shouldLogActivity()) {
                return;
            }

            $object = $observer->getEvent()->getObject();
            if (!$object || !$object->getId()) {
                return;
            }

            $objectHash = spl_object_hash($object);

            if ($object instanceof Mage_Core_Model_Config_Data) {
                $this->_oldData[$objectHash] = [
                    'path' => $object->getPath(),
                    'website_code' => $object->getWebsiteCode(),
                    'store_code' => $object->getStoreCode(),
                    'value' => $object->getOldValue(),
                ];
            } else {
                $this->_oldData[$objectHash] = $object->getOrigData();
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminActivityAfter(Varien_Event_Observer $observer): void
    {
        try {
            if (!Mage::helper('adminactivitylog')->shouldLogSaveActions()) {
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

            // Don't log config data if not changed
            if ($object instanceof Mage_Core_Model_Config_Data && !$object->isValueChanged()) {
                return;
            }

            $objectHash = spl_object_hash($object);

            $oldData = $this->_oldData[$objectHash] ?? [];
            $newData = $object->getData();

            $oldChangedData = [];
            $newChangedData = [];

            $isNew = empty($oldData);

            if ($isNew) {
                $oldChangedData = $oldData;
                $newChangedData = $newData;
            } else {
                foreach ($oldData as $key => $oldValue) {
                    $newValue = $newData[$key] ?? null;

                    // Only log if there's a meaningful change
                    if (!$object instanceof Mage_Core_Model_Config_Data) {
                        if ($oldValue == $newValue) {
                            continue;
                        }
                        if (($oldValue === null || $oldValue === '') && ($newValue === null || $newValue === '')) {
                            continue;
                        }
                    }

                    $oldChangedData[$key] = $oldValue;
                    $newChangedData[$key] = $newValue;
                }
            }

            $dbFields = Mage::helper('adminactivitylog')->getDbFields($object);

            $oldChangedData = $this->filterFields($oldChangedData, $dbFields);
            $newChangedData = $this->filterFields($newChangedData, $dbFields);

            $groupId = $this->_getActionGroupId();
            $entityType = $this->_getEntityType($object);
            $entityName = $this->_getEntityName($object);

            $data = [
                'action_type' => $isNew ? 'create' : 'update',
                'action_group_id' => $groupId,
                'module' => $this->_getCurrentModule(),
                'controller' => $this->_getCurrentController(),
                'action' => $this->_getCurrentAction(),
                'entity_type' => $entityType,
                'entity_id' => $object->getId(),
                'entity_name' => $entityName,
                'request_url' => $this->_getRelativeAdminUrl(),
                'old_data' => $oldChangedData,
                'new_data' => $newChangedData,
            ];

            if (count(array_keys($newChangedData)) > 0) {
                Mage::getModel('adminactivitylog/activity')->logActivity($data);
            }
            unset($this->_oldData[$objectHash]);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logAdminDelete(Varien_Event_Observer $observer): void
    {
        try {
            if (!Mage::helper('adminactivitylog')->shouldLogDeleteActions()) {
                return;
            }

            $object = $observer->getEvent()->getObject();
            if (!$object || !$object->getId()) {
                return;
            }

            $objectHash = spl_object_hash($object);
            $oldData = $this->_oldData[$objectHash] ?? $object->getData();

            $dbFields = Mage::helper('adminactivitylog')->getDbFields($object);

            $data = [
                'action_type' => 'delete',
                'action_group_id' => $this->_getActionGroupId(),
                'module' => $this->_getCurrentModule(),
                'controller' => $this->_getCurrentController(),
                'action' => $this->_getCurrentAction(),
                'entity_type' => $this->_getEntityType($object),
                'entity_id' => $object->getId(),
                'entity_name' => $this->_getEntityName($object),
                'request_url' => $this->_getRelativeAdminUrl(),
                'old_data' => $this->filterFields($oldData, $dbFields),
            ];

            Mage::getModel('adminactivitylog/activity')->logActivity($data);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function logPageVisit(Varien_Event_Observer $observer): void
    {
        try {
            if (!Mage::helper('adminactivitylog')->shouldLogPageVisit()) {
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
                'request_url' => $this->_getRelativeAdminUrl(),
            ];

            Mage::getModel('adminactivitylog/activity')->logActivity($data);
        } catch (Exception $e) {
            Mage::logException($e);
        }
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

    protected function _getRelativeAdminUrl(): string
    {
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $adminFrontName = (string) Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName');

        // Find the position of the admin front name in the URL
        $pos = strpos($currentUrl, "/{$adminFrontName}/");
        if ($pos !== false) {
            // Extract everything after the admin front name and slash
            $relativePath = substr($currentUrl, $pos + strlen("/{$adminFrontName}/"));
            
            // Remove query parameters and fragments
            $relativePath = strtok($relativePath, '?');
            $relativePath = strtok($relativePath, '#');
            
            // Split by slash and keep only the first parameter (if any)
            $parts = explode('/', $relativePath);
            if (count($parts) > 3) {
                // Keep module/controller/action + first parameter (id/value)
                $relativePath = implode('/', array_slice($parts, 0, 4));
            }
            
            return $relativePath;
        }

        // Fallback if admin front name not found
        return '';
    }

    protected function filterFields(array $data, ?array $dbFields): array
    {
        if ($dbFields !== null) {
            $data = array_intersect_key($data, array_flip($dbFields));
        }
        return array_diff_key($data, array_flip($this->ignoreFields));
    }

    protected function _getEntityType(Mage_Core_Model_Abstract $object): string
    {
        $class = $object::class;
        $map = [
            // Main entities (high priority)
            'Mage_Catalog_Model_Product' => 'product',
            'Mage_Catalog_Model_Category' => 'category',
            'Mage_Customer_Model_Customer' => 'customer',
            'Mage_Sales_Model_Order' => 'order',
            'Mage_Cms_Model_Page' => 'cms_page',
            'Mage_Cms_Model_Block' => 'cms_block',
            'Mage_Admin_Model_User' => 'admin_user',
            'Mage_Admin_Model_Role' => 'admin_role',

            // Supporting entities (low priority)
            'Mage_Catalog_Model_Product_Attribute' => 'catalog_product_attribute',
            'Mage_Catalog_Model_Product_Type_Configurable_Attribute' => 'catalog_product_type_configurable_attribute',
            'Mage_Eav_Model_Entity_Attribute_Option' => 'eav_attribute_option',
            'Mage_Catalog_Model_Product_Link' => 'catalog_product_link',
            'Mage_Catalog_Model_Product_Website' => 'catalog_product_website',
            'Mage_Catalog_Model_Category_Product' => 'catalog_product_category',
            'Mage_CatalogInventory_Model_Stock_Item' => 'catalog_product_stock_item',
        ];

        return $map[$class] ?? strtolower(str_replace('_Model_', '_', $class));
    }

    protected function _getEntityName(Mage_Core_Model_Abstract $object): string
    {
        $nameFields = ['name', 'title', 'sku', 'increment_id', 'username', 'email', 'identifier', 'path'];

        foreach ($nameFields as $field) {
            if ($object->hasData($field) && $object->getData($field)) {
                return (string) $object->getData($field);
            }
        }

        return 'ID: ' . $object->getId();
    }

    public function logMassAction(Varien_Event_Observer $observer): void
    {
        if (!Mage::helper('adminactivitylog')->shouldLogMassActions()) {
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
            'action_group_id' => $this->_getActionGroupId(),
            'entity_type' => $entityType,
            'entity_id' => null, // For mass actions, no single entity ID makes sense
            'entity_name' => $entityName,
            'username' => $user->getUsername(),
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'request_url' => $this->_getRelativeAdminUrl(),
            'old_data' => $oldDataToStore,
            'new_data' => $newDataToStore,
        ];

        Mage::getModel('adminactivitylog/activity')->logActivity($data);
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

    public function encryptionKeyRegenerated(Varien_Event_Observer $observer): void
    {
        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $observer->getEvent()->getOutput();
        $encryptCallback = $observer->getEvent()->getEncryptCallback();
        $decryptCallback = $observer->getEvent()->getDecryptCallback();
        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $output->write('Re-encrypting data on adminactivitylog_activity table... ');
        $table = Mage::getSingleton('core/resource')->getTableName('adminactivitylog/activity');

        // Re-encrypt old_data
        $select = $readConnection->select()
            ->from($table)
            ->where('old_data IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['old_data' => $encryptCallback($decryptCallback($encryptedDataRow['old_data']))],
                ['activity_id = ?' => $encryptedDataRow['activity_id']],
            );
        }

        // Re-encrypt new_data
        $select = $readConnection->select()
            ->from($table)
            ->where('new_data IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['new_data' => $encryptCallback($decryptCallback($encryptedDataRow['new_data']))],
                ['activity_id = ?' => $encryptedDataRow['activity_id']],
            );
        }

        $output->writeln('OK');
    }

}
