<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_System_StoreController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/store';

    /** The model alias of each value of the store_type parameter. */
    private const MODELS = ['website' => 'core/website', 'group' => 'core/store_group', 'store' => 'core/store'];

    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        $this->loadLayout()
            ->_setActiveMenu('system/store')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'))
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Manage Stores'), Mage::helper('adminhtml')->__('Manage Stores'))
        ;
        return $this;
    }

    #[Maho\Config\Route('/admin/system_store/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
             ->_title($this->__('Stores'));

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('adminhtml/system_store_store'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_store/newWebsite')]
    public function newWebsiteAction(): void
    {
        Mage::register('store_type', 'website');
        $this->_forward('newStore');
    }

    #[Maho\Config\Route('/admin/system_store/newGroup')]
    public function newGroupAction(): void
    {
        Mage::register('store_type', 'group');
        $this->_forward('newStore');
    }

    #[Maho\Config\Route('/admin/system_store/newStore')]
    public function newStoreAction(): void
    {
        if (!Mage::registry('store_type')) {
            Mage::register('store_type', 'store');
        }
        Mage::register('store_action', 'add');
        $this->_forward('editStore');
    }

    #[Maho\Config\Route('/admin/system_store/editWebsite')]
    public function editWebsiteAction(): void
    {
        Mage::register('store_type', 'website');
        $this->_forward('editStore');
    }

    #[Maho\Config\Route('/admin/system_store/editGroup')]
    public function editGroupAction(): void
    {
        Mage::register('store_type', 'group');
        $this->_forward('editStore');
    }

    #[Maho\Config\Route('/admin/system_store/editStore')]
    public function editStoreAction(): void
    {
        $this->_title($this->__('System'))
             ->_title($this->__('Stores'));

        $session = $this->_getSession();
        if ($session->getPostData()) {
            Mage::register('store_post_data', $session->getPostData());
            $session->unsPostData();
        }
        if (!Mage::registry('store_type')) {
            Mage::register('store_type', 'store');
        }
        if (!Mage::registry('store_action')) {
            Mage::register('store_action', 'edit');
        }
        $type = (string) Mage::registry('store_type');
        $isAdd = Mage::registry('store_action') == 'add';

        try {
            $model = $isAdd
                ? Mage::getModel(self::MODELS[$type])
                : $this->loadItem($type, (int) $this->getRequest()->getParam($type . '_id'));
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('store_data', $model);

        $title = match ($type) {
            'website' => Mage::helper('core')->__('Website'),
            'group' => Mage::helper('core')->__('Store'),
            default => Mage::helper('core')->__('Store View'),
        };
        $this->_title($isAdd ? $this->__('New ') . $title : $model->getName());

        if (!$isAdd && $type !== 'group' && !$model->isReadOnly()) {
            $session->addNotice($type === 'website'
                ? Mage::helper('core')->__('Before modifying the website code please make sure that it is not used in index.php.')
                : Mage::helper('core')->__('Before modifying the store view code please make sure that it is not used in index.php.'));
        }

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('adminhtml/system_store_edit'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_store/save')]
    public function saveAction(): void
    {
        $postData = $this->getRequest()->getPost();
        $type = $postData['store_type'] ?? '';
        if (!$this->getRequest()->isPost() || empty($postData['store_action']) || !isset(self::MODELS[$type])) {
            $this->_redirect('*/*/');
            return;
        }

        $session = $this->_getSession();
        $data = $postData[$type];
        $id = $data[$type . '_id'] ?? '';
        try {
            $model = $id ? $this->loadItem($type, (int) $id) : Mage::getModel(self::MODELS[$type]);
            $model->setData($data);
            if ($id == '') {
                $model->setId(null);
            }
            $this->storeService()->save($model);
            $session->addSuccess(match ($type) {
                'website' => Mage::helper('core')->__('The website has been saved.'),
                'group' => Mage::helper('core')->__('The store has been saved.'),
                default => Mage::helper('core')->__('The store view has been saved'),
            });
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('core')->__('An error occurred while saving. Please review the error log.'));
        }
        $session->setPostData($postData);
        $this->_redirectReferer();
    }

    #[Maho\Config\Route('/admin/system_store/deleteWebsitePost')]
    public function deleteWebsitePostAction(): void
    {
        $this->_deleteItem('website', Mage::helper('core')->__('The website has been deleted.'), Mage::helper('core')->__('Unable to delete website. Please, try again later.'));
    }

    #[Maho\Config\Route('/admin/system_store/deleteGroupPost')]
    public function deleteGroupPostAction(): void
    {
        $this->_deleteItem('group', Mage::helper('core')->__('The store has been deleted.'), Mage::helper('core')->__('Unable to delete store. Please, try again later.'));
    }

    #[Maho\Config\Route('/admin/system_store/deleteStorePost')]
    public function deleteStorePostAction(): void
    {
        $this->_deleteItem('store', Mage::helper('core')->__('The store view has been deleted.'), Mage::helper('core')->__('Unable to delete store view. Please, try again later.'));
    }

    /**
     * Delete the $type with the ID of the item_id parameter.
     */
    protected function _deleteItem(string $type, string $success, string $failure): void
    {
        $session = $this->_getSession();
        $itemId = (int) $this->getRequest()->getParam('item_id');
        try {
            $this->storeService()->delete($this->loadItem($type, $itemId));
            $session->addSuccess($success);
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $failure);
        }
        $this->_redirect('*/*/edit' . ucfirst($type), [$type . '_id' => $itemId]);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    protected function loadItem(string $type, int $id): Mage_Core_Model_Website|Mage_Core_Model_Store_Group|Mage_Core_Model_Store
    {
        return match ($type) {
            'website' => $this->storeService()->getWebsiteById($id),
            'group' => $this->storeService()->getGroupById($id),
            default => $this->storeService()->getStoreById($id),
        };
    }

    protected function storeService(): Mage_Core_Service_Store
    {
        return Mage::getService('core/store');
    }
}
