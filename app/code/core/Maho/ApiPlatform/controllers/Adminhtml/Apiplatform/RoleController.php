<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Adminhtml_Apiplatform_RoleController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/api/api2_roles';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'save']);
        return parent::preDispatch();
    }

    /**
     * Available API v2 resources for permission assignment
     * Format: 'resource_id' => ['label' => 'Display Name', 'read' => bool, 'write' => bool]
     */
    private const API_RESOURCES = [
        'orders'     => ['label' => 'Orders', 'read' => true, 'write' => true],
        'customers'  => ['label' => 'Customers', 'read' => true, 'write' => true],
        'carts'      => ['label' => 'Carts', 'read' => true, 'write' => true],
        'newsletter' => ['label' => 'Newsletter', 'read' => true, 'write' => true],
        'products'   => ['label' => 'Products', 'read' => true, 'write' => false],
        'categories' => ['label' => 'Categories', 'read' => true, 'write' => false],
        'cms'        => ['label' => 'CMS Pages & Blocks', 'read' => true, 'write' => false],
        'blog'       => ['label' => 'Blog Posts', 'read' => true, 'write' => false],
        'stores'     => ['label' => 'Stores', 'read' => true, 'write' => false],
        'countries'  => ['label' => 'Countries', 'read' => true, 'write' => false],
        // 'shipments' removed - no REST endpoints, only embedded in orders
    ];

    protected function _initAction(): static
    {
        $this->loadLayout()
            ->_setActiveMenu('system/api/api2_roles')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('API v2 Roles'), $this->__('API v2 Roles'));
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('API v2 Roles'));
        $this->_initAction();
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('API v2 Roles'));

        $id = (int) $this->getRequest()->getParam('role_id');

        $roleData = null;
        $permissions = [];

        if ($id) {
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $roleTable = $resource->getTableName('api/role');

            $roleData = $read->fetchRow(
                $read->select()->from($roleTable)->where('role_id = ?', $id)->where('role_type = ?', 'G'),
            );

            if (!$roleData) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('This role no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }

            // Load current permissions
            $ruleTable = $resource->getTableName('api/rule');
            $rules = $read->fetchAll(
                $read->select()
                    ->from($ruleTable, ['resource_id', 'api_permission'])
                    ->where('role_id = ?', $id)
                    ->where('role_type = ?', 'G')
                    ->where('api_permission = ?', 'allow'),
            );
            foreach ($rules as $rule) {
                $permissions[] = $rule['resource_id'];
            }
        }

        Mage::register('api_role_data', $roleData ?: []);
        Mage::register('api_role_permissions', $permissions);
        Mage::register('api_resources', self::API_RESOURCES);

        $this->_title($roleData ? $roleData['role_name'] : $this->__('New Role'));

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? $this->__('Edit Role') : $this->__('New Role'),
                $id ? $this->__('Edit Role') : $this->__('New Role'),
            );

        $this->renderLayout();
    }

    public function saveAction(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('*/*/');
            return;
        }

        $data = $this->getRequest()->getPost();
        $id = (int) $this->getRequest()->getParam('role_id');

        try {
            $resource = Mage::getSingleton('core/resource');
            $write = $resource->getConnection('core_write');
            $roleTable = $resource->getTableName('api/role');
            $ruleTable = $resource->getTableName('api/rule');

            $roleName = $data['role_name'] ?? '';
            if (empty($roleName)) {
                Mage::throwException($this->__('Role name is required.'));
            }

            $write->beginTransaction();

            try {
                if ($id) {
                    $write->update($roleTable, ['role_name' => $roleName], ['role_id = ?' => $id]);
                } else {
                    $write->insert($roleTable, [
                        'parent_id'  => 0,
                        'tree_level' => 1,
                        'sort_order' => 0,
                        'role_type'  => 'G',
                        'user_id'    => 0,
                        'role_name'  => $roleName,
                    ]);
                    $id = (int) $write->lastInsertId();
                }

                // Delete existing rules for this role
                $write->delete($ruleTable, [
                    'role_id = ?' => $id,
                    'role_type = ?' => 'G',
                ]);

                // Insert new permission rules
                $permissions = $data['permissions'] ?? [];
                if (in_array('all', $permissions, true)) {
                    $write->insert($ruleTable, [
                        'role_id'        => $id,
                        'resource_id'    => 'all',
                        'api_privileges' => null,
                        'assert_id'      => 0,
                        'role_type'      => 'G',
                        'api_permission' => 'allow',
                    ]);
                } else {
                    foreach ($permissions as $permission) {
                        $write->insert($ruleTable, [
                            'role_id'        => $id,
                            'resource_id'    => $permission,
                            'api_privileges' => null,
                            'assert_id'      => 0,
                            'role_type'      => 'G',
                            'api_permission' => 'allow',
                        ]);
                    }
                }

                $write->commit();
            } catch (\Exception $e) {
                $write->rollBack();
                throw $e;
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Role has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['role_id' => $id]);
            } else {
                $this->_redirect('*/*/');
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            if ($id) {
                $this->_redirect('*/*/edit', ['role_id' => $id]);
            } else {
                $this->_redirect('*/*/new');
            }
        }
    }

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('role_id');
        if (!$id) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $resource = Mage::getSingleton('core/resource');
            $write = $resource->getConnection('core_write');

            $write->beginTransaction();
            try {
                $write->delete($resource->getTableName('api/rule'), ['role_id = ?' => $id, 'role_type = ?' => 'G']);
                $write->delete($resource->getTableName('api/role'), ['parent_id = ?' => $id, 'role_type = ?' => 'U']);
                $write->delete($resource->getTableName('api/role'), ['role_id = ?' => $id, 'role_type = ?' => 'G']);
                $write->commit();
            } catch (\Exception $e) {
                $write->rollBack();
                throw $e;
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Role has been deleted.'));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }
}
