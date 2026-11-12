<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_UrlrewriteController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/urlrewrite';

    /**
     * Instantiate urlrewrite, product and category
     *
     * @return $this
     */
    protected function _initRegistry()
    {
        $this->_title($this->__('Rewrite Rules'));

        // initialize urlrewrite, product and category models
        Mage::register(
            'current_urlrewrite',
            Mage::getSingleton('core/factory')->getUrlRewriteInstance()
            ->load($this->getRequest()->getParam('id', 0)),
        );
        $productId  = $this->getRequest()->getParam('product', 0);
        $categoryId = $this->getRequest()->getParam('category', 0);
        if (Mage::registry('current_urlrewrite')->getId()) {
            $productId  = Mage::registry('current_urlrewrite')->getProductId();
            $categoryId = Mage::registry('current_urlrewrite')->getCategoryId();
        }

        Mage::register('current_product', Mage::getModel('catalog/product')->load($productId));
        Mage::register('current_category', Mage::getModel('catalog/category')->load($categoryId));

        return $this;
    }

    /**
     * Show urlrewrites index page
     */
    #[Maho\Config\Route('/admin/urlrewrite/index')]
    public function indexAction(): void
    {
        $this->_initRegistry();
        $this->loadLayout();
        $this->_setActiveMenu('catalog/urlrewrite');
        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/urlrewrite'),
        );
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/urlrewrite/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/urlrewrite_grid')->toHtml(),
        );
    }

    /**
     * Show urlrewrite edit/create page
     */
    #[Maho\Config\Route('/admin/urlrewrite/edit')]
    public function editAction(): void
    {
        $this->_initRegistry();

        $this->_title($this->__('URL Rewrite'));

        $this->loadLayout();
        $this->_setActiveMenu('catalog/urlrewrite');
        $this->_addContent($this->getLayout()->createBlock('adminhtml/urlrewrite_edit'));
        $this->renderLayout();
    }

    /**
     * Ajax products grid action
     */
    #[Maho\Config\Route('/admin/urlrewrite/productGrid')]
    public function productGridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/urlrewrite_product_grid')->toHtml());
    }

    /**
     * Ajax categories tree loader action
     */
    #[Maho\Config\Route('/admin/urlrewrite/categoriesJson')]
    public function categoriesJsonAction(): void
    {
        $this->getResponse()->setBodyJson(
            Mage::getBlockSingleton('adminhtml/urlrewrite_category_tree')
                ->getTreeArray($this->getRequest()->getParam('id'), true),
        );
    }

    /**
     * Urlrewrite save action
     */
    #[Maho\Config\Route('/admin/urlrewrite/save')]
    public function saveAction(): void
    {
        $this->_initRegistry();

        if ($data = $this->getRequest()->getPost()) {
            $request = $this->getRequest();
            $session = Mage::getSingleton('adminhtml/session');
            try {
                $service = $this->getUrlRewriteService();
                $id = (int) $request->getParam('id');
                $model = $id ? $service->getById($id) : $service->newRewrite();
                $model->setIdPath($request->getParam('id_path'))
                    ->setTargetPath($request->getParam('target_path'))
                    ->setOptions($request->getParam('options'))
                    ->setDescription($request->getParam('description'))
                    ->setRequestPath($request->getParam('request_path'));
                // The form disables the store of a system rewrite
                if (!$model->getIsSystem()) {
                    $model->setStoreId((int) $request->getParam('store_id', 0));
                }
                if ($categoryId = (int) Mage::registry('current_category')->getId()) {
                    $model->setCategoryId($categoryId);
                }
                if ($productId = (int) Mage::registry('current_product')->getId()) {
                    $model->setProductId($productId);
                }

                $service->save($model);
                $session->addSuccess(Mage::helper('adminhtml')->__('The URL Rewrite has been saved.'));
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception_Input $e) {
                foreach ($e->getErrors() as $error) {
                    $session->addError($error['message']);
                }
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('adminhtml')->__('An error occurred while saving URL Rewrite.'));
            }
            $session->setUrlrewriteData($data);
        }
        $this->_redirectReferer();
    }

    /**
     * Urlrewrite delete action
     */
    #[Maho\Config\Route('/admin/urlrewrite/delete')]
    public function deleteAction(): void
    {
        if ($id = (int) $this->getRequest()->getParam('id')) {
            try {
                $service = $this->getUrlRewriteService();
                $service->delete($service->getById($id));
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The URL Rewrite has been deleted.'),
                );
            } catch (Mage_Core_Exception_NoSuchEntity $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')
                    ->addException($e, Mage::helper('adminhtml')->__('An error occurred while deleting URL Rewrite.'));
                $this->_redirect('*/*/edit/', ['id' => $id]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Urlrewrite mass delete action
     */
    #[Maho\Config\Route('/admin/urlrewrite/massDelete')]
    public function massDeleteAction(): void
    {
        $ids = $this->getRequest()->getParam('url_rewrite');
        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Please select URL rewrite(s).'),
            );
        } else {
            try {
                $service = $this->getUrlRewriteService();
                foreach ($ids as $id) {
                    $service->delete($service->getById((int) $id));
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d URL rewrite(s) have been deleted.', count($ids)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    protected function getUrlRewriteService(): Mage_Core_Service_Url_Rewrite
    {
        return Mage::getService('core/url_rewrite');
    }
}
