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
        $model = Mage::getModel('apiplatform/user');

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
            $model->setIsActive(array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $model->getIsActive());
            if (array_key_exists('allowed_store_ids', $data)) {
                $model->setAllowedStoreIds((array) $data['allowed_store_ids']);
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
            $model = Mage::getModel('apiplatform/user');
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
            $model->setIsActive((bool) ($data['is_active'] ?? 1));
            $model->setAllowedStoreIds((array) ($data['allowed_store_ids'] ?? []));

            if (!empty($data['api_key'])) {
                $model->setApiKey($data['api_key']);
            }

            $clientSecret = null;
            if (!$model->getClientId() || !empty($data['regenerate_client_credentials'])) {
                $clientSecret = $model->generateClientCredentials();
            }

            $model->save();

            if ($clientSecret !== null) {
                // The plain secret is shown once, on the next page, and never stored
                Mage::getSingleton('adminhtml/session')->setNewClientSecret($clientSecret);
                Mage::getSingleton('adminhtml/session')->setNewClientId($model->getClientId());
            }

            if (isset($data['api_role'])) {
                $model->assignRole((int) $data['api_role']);
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('API user has been saved.'));

            // Show client credentials if just generated
            $newSecret = Mage::getSingleton('adminhtml/session')->getNewClientSecret(true);
            $newClientId = Mage::getSingleton('adminhtml/session')->getNewClientId(true);
            if ($newSecret) {
                Mage::getSingleton('adminhtml/session')->addNotice(
                    $this->__('Client ID: %s') . "\n"
                    . $this->__('Client Secret: %s') . "\n"
                    . $this->__('Save these credentials now. The secret cannot be retrieved later.'),
                    $newClientId,
                    $newSecret,
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

    // Reached through the edit form's delete button, so it has to answer GET like
    // every other admin delete action; CSRF is covered by the forced form key.
    #[Maho\Config\Route('/admin/apiplatform_user/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('user_id');
        if (!$id) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $model = Mage::getModel('apiplatform/user')->load($id);
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
}
