<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Adminhtml_Apiplatform_UserController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/api/api2_users';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'save']);
        return parent::preDispatch();
    }

    protected function _initAction(): static
    {
        $this->loadLayout()
            ->_setActiveMenu('system/api/api2_users')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('API v2 Users'), $this->__('API v2 Users'));
        return $this;
    }

    #[Maho\Config\Route('/admin/apiplatform_user/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('API v2 Users'));
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/apiplatform_user/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/apiplatform_user/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/apiplatform_user/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('API v2 Users'));

        $id = (int) $this->getRequest()->getParam('user_id');
        $model = Mage::getModel('api/user');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('This API user no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getUsername() : $this->__('New API User'));

        // Re-populate the form from session flash on save errors. Use the same
        // field allowlist as saveAction(), never blanket-setData() the raw POST,
        // or a crafted submit could write password, is_superadmin, etc. on
        // the next render.
        $data = Mage::getSingleton('adminhtml/session')->getApiUserData(true);
        if (!empty($data)) {
            $model->setUsername($data['username'] ?? $model->getUsername());
            $model->setFirstname($data['firstname'] ?? $model->getFirstname());
            $model->setLastname($data['lastname'] ?? $model->getLastname());
            $model->setEmail($data['email'] ?? $model->getEmail());
            $model->setIsActive($data['is_active'] ?? $model->getIsActive());
            if (array_key_exists('allowed_store_ids', $data)) {
                $storeIds = array_values(array_filter(array_map('intval', (array) $data['allowed_store_ids'])));
                $model->setAllowedStoreIds($storeIds === [] ? null : Mage::helper('core')->jsonEncode($storeIds));
            }
        }

        Mage::register('api_user', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? $this->__('Edit API User') : $this->__('New API User'),
                $id ? $this->__('Edit API User') : $this->__('New API User'),
            );

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/apiplatform_user/save', methods: ['POST'])]
    public function saveAction(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('*/*/');
            return;
        }

        $data = $this->getRequest()->getPost();
        $id = (int) $this->getRequest()->getParam('user_id');

        try {
            $model = Mage::getModel('api/user');
            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::throwException($this->__('API user not found.'));
                }
            }

            $model->setUsername($data['username'] ?? '');
            $model->setFirstname($data['firstname'] ?? '');
            $model->setLastname($data['lastname'] ?? '');
            $model->setEmail($data['email'] ?? '');
            $model->setIsActive($data['is_active'] ?? 1);

            // Normalize the store-restriction multiselect into a JSON array of
            // ints (empty selection => null => all stores). The JWT issuer reads
            // this column to scope tokens.
            $storeIds = array_values(array_filter(array_map('intval', (array) ($data['allowed_store_ids'] ?? []))));
            $model->setAllowedStoreIds($storeIds === [] ? null : Mage::helper('core')->jsonEncode($storeIds));

            // Set API key if provided
            if (!empty($data['api_key'])) {
                $model->setApiKey($data['api_key']);
            }

            $model->save();

            // Generate or regenerate client credentials (direct DB update since model doesn't support these fields)
            $resource = Mage::getSingleton('core/resource');
            $currentClientId = $resource->getConnection('core_read')->fetchOne(
                $resource->getConnection('core_read')->select()
                    ->from($resource->getTableName('api/user'), ['client_id'])
                    ->where('user_id = ?', $model->getId()),
            );

            if (!$currentClientId || !empty($data['regenerate_client_credentials'])) {
                $clientId = 'maho_' . bin2hex(random_bytes(16));
                $clientSecret = bin2hex(random_bytes(32));

                $resource->getConnection('core_write')->update(
                    $resource->getTableName('api/user'),
                    [
                        'client_id' => $clientId,
                        'client_secret' => password_hash($clientSecret, PASSWORD_BCRYPT),
                    ],
                    ['user_id = ?' => (int) $model->getId()],
                );

                // Store plain secret in session for one-time display
                Mage::getSingleton('adminhtml/session')->setNewClientSecret($clientSecret);
                Mage::getSingleton('adminhtml/session')->setNewClientId($clientId);
            }

            // Save role assignment
            if (isset($data['api_role'])) {
                $this->_saveRoleAssignment($model, (int) $data['api_role']);
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('API user has been saved.'));

            // Show client credentials if just generated
            $newSecret = Mage::getSingleton('adminhtml/session')->getNewClientSecret(true);
            $newClientId = Mage::getSingleton('adminhtml/session')->getNewClientId(true);
            if ($newSecret) {
                Mage::getSingleton('adminhtml/session')->addNotice(
                    $this->__('Client ID: %s', $newClientId) . '<br/>' .
                    $this->__('Client Secret: %s', $newSecret) . '<br/>' .
                    $this->__('Save these credentials now. The secret cannot be retrieved later.'),
                );
            }

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['user_id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/');
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            // Flash only the safe display fields editAction() re-reads. Never
            // persist secrets (api_key, client credentials) into the session.
            $safeData = array_intersect_key($data, array_flip([
                'username', 'firstname', 'lastname', 'email', 'is_active', 'allowed_store_ids', 'api_role',
            ]));
            Mage::getSingleton('adminhtml/session')->setApiUserData($safeData);
            if ($id) {
                $this->_redirect('*/*/edit', ['user_id' => $id]);
            } else {
                $this->_redirect('*/*/new');
            }
        }
    }

    #[Maho\Config\Route('/admin/apiplatform_user/delete', methods: ['POST'])]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('user_id');
        if (!$id) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $model = Mage::getModel('api/user')->load($id);
            if (!$model->getId()) {
                Mage::throwException($this->__('API user not found.'));
            }
            $model->delete();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('API user has been deleted.'));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * Save user-to-role assignment in api_role table
     */
    private function _saveRoleAssignment(Mage_Api_Model_User $user, int $roleId): void
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $roleTable = $resource->getTableName('api/role');

        // Delete existing user role entries
        $write->delete($roleTable, [
            'user_id = ?' => $user->getId(),
            'role_type = ?' => 'U',
        ]);

        // Insert new assignment if role selected
        if ($roleId > 0) {
            $write->insert($roleTable, [
                'parent_id'  => $roleId,
                'tree_level' => 2,
                'sort_order' => 0,
                'role_type'  => 'U',
                'user_id'    => $user->getId(),
                'role_name'  => $user->getUsername(),
            ]);
        }
    }
}
