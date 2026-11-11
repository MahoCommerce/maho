<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Catalog_Product_ReviewController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    #[\Override]
    protected $_publicActions = ['edit'];

    #[Maho\Config\Route('/admin/catalog_product_review/index')]
    public function indexAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Reviews and Ratings'))
             ->_title($this->__('Customer Reviews'));

        $this->_title($this->__('All Reviews'));

        if ($this->getRequest()->getParam('ajax')) {
            return $this->_forward('reviewGrid');
        }

        $this->loadLayout();
        $this->_setActiveMenu('catalog/reviews_ratings/reviews/all');

        $this->_addContent($this->getLayout()->createBlock('adminhtml/review_main'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/catalog_product_review/pending')]
    public function pendingAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Reviews and Ratings'))
             ->_title($this->__('Customer Reviews'));

        $this->_title($this->__('Pending Reviews'));

        if ($this->getRequest()->getParam('ajax')) {
            Mage::register('usePendingFilter', true);
            return $this->_forward('reviewGrid');
        }

        $this->loadLayout();
        $this->_setActiveMenu('catalog/reviews_ratings/reviews/pending');

        Mage::register('usePendingFilter', true);
        $this->_addContent($this->getLayout()->createBlock('adminhtml/review_main'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/catalog_product_review/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Reviews and Ratings'))
             ->_title($this->__('Customer Reviews'));

        $this->_title($this->__('Edit Review'));

        $this->loadLayout();
        if ($this->getRequest()->getParam('ret') === 'pending') {
            $this->_setActiveMenu('catalog/reviews_ratings/reviews/pending');
        } else {
            $this->_setActiveMenu('catalog/reviews_ratings/reviews/all');
        }

        $this->_addContent($this->getLayout()->createBlock('adminhtml/review_edit'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/catalog_product_review/new')]
    public function newAction(): void
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Reviews and Ratings'))
             ->_title($this->__('Customer Reviews'));

        $this->_title($this->__('New Review'));

        $this->loadLayout();
        $this->_setActiveMenu('catalog/reviews_ratings/reviews/all');

        $this->_addContent($this->getLayout()->createBlock('adminhtml/review_add'));
        $this->_addContent($this->getLayout()->createBlock('adminhtml/review_product_grid'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/catalog_product_review/save')]
    public function saveAction()
    {
        if (($data = $this->getRequest()->getPost()) && ($reviewId = $this->getRequest()->getParam('id'))) {
            $session = Mage::getSingleton('adminhtml/session');
            try {
                $service = $this->reviewService();
                $review = $service->getById((int) $reviewId)->addData($data)->setId($reviewId);
                $service->save($review, (array) $this->getRequest()->getParam('ratings', []));
                $session->addSuccess(Mage::helper('catalog')->__('The review has been saved.'));
            } catch (Mage_Core_Exception_NoSuchEntity) {
                $session->addError(Mage::helper('catalog')->__('The review was removed by another user or does not exist.'));
            } catch (Mage_Core_Exception $e) {
                $this->addErrors($e);
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('catalog')->__('An error occurred while saving this review.'));
            }

            return $this->getResponse()->setRedirect($this->getUrl($this->getRequest()->getParam('ret') == 'pending' ? '*/*/pending' : '*/*/'));
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/catalog_product_review/delete')]
    public function deleteAction(): void
    {
        $reviewId   = $this->getRequest()->getParam('id', false);
        $session    = Mage::getSingleton('adminhtml/session');

        try {
            $this->reviewService()->delete($this->reviewService()->getById((int) $reviewId));

            $session->addSuccess(Mage::helper('catalog')->__('The review has been deleted'));
            if ($this->getRequest()->getParam('ret') == 'pending') {
                $this->getResponse()->setRedirect($this->getUrl('*/*/pending'));
            } else {
                $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            }
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('catalog')->__('An error occurred while deleting this review.'));
        }

        $this->_redirect('*/*/edit/', ['id' => $reviewId]);
    }

    #[Maho\Config\Route('/admin/catalog_product_review/massDelete')]
    public function massDeleteAction(): void
    {
        $reviewsIds = $this->getRequest()->getParam('reviews');
        $session    = Mage::getSingleton('adminhtml/session');

        if (!is_array($reviewsIds)) {
            $session->addError(Mage::helper('adminhtml')->__('Please select review(s).'));
        } else {
            try {
                foreach ($reviewsIds as $reviewId) {
                    $this->reviewService()->delete($this->reviewService()->getById((int) $reviewId));
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) have been deleted.', count($reviewsIds)),
                );
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('adminhtml')->__('An error occurred while deleting record(s).'));
            }
        }

        $this->_redirect('*/*/' . $this->getRequest()->getParam('ret', 'index'));
    }

    #[Maho\Config\Route('/admin/catalog_product_review/massUpdateStatus')]
    public function massUpdateStatusAction(): void
    {
        $reviewsIds = $this->getRequest()->getParam('reviews');
        $session    = Mage::getSingleton('adminhtml/session');

        if (!is_array($reviewsIds)) {
            $session->addError(Mage::helper('adminhtml')->__('Please select review(s).'));
        } else {
            try {
                $status = (int) $this->getRequest()->getParam('status');
                foreach ($reviewsIds as $reviewId) {
                    $this->reviewService()->moderate($this->reviewService()->getById((int) $reviewId), $status);
                }
                $session->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) have been updated.', count($reviewsIds)),
                );
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('adminhtml')->__('An error occurred while updating the selected review(s).'));
            }
        }

        $this->_redirect('*/*/' . $this->getRequest()->getParam('ret', 'index'));
    }

    #[Maho\Config\Route('/admin/catalog_product_review/massVisibleIn')]
    public function massVisibleInAction(): void
    {
        $reviewsIds = $this->getRequest()->getParam('reviews');
        $session    = Mage::getSingleton('adminhtml/session');

        if (!is_array($reviewsIds)) {
            $session->addError(Mage::helper('adminhtml')->__('Please select review(s).'));
        } else {
            try {
                $stores = array_map(intval(...), (array) $this->getRequest()->getParam('stores'));
                foreach ($reviewsIds as $reviewId) {
                    $this->reviewService()->moderate($this->reviewService()->getById((int) $reviewId), null, $stores);
                }
                $session->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) have been updated.', count($reviewsIds)),
                );
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('adminhtml')->__('An error occurred while updating the selected review(s).'));
            }
        }

        $this->_redirect('*/*/pending');
    }

    #[Maho\Config\Route('/admin/catalog_product_review/productGrid')]
    public function productGridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/review_product_grid')->toHtml());
    }

    #[Maho\Config\Route('/admin/catalog_product_review/reviewGrid')]
    public function reviewGridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/review_grid')->toHtml());
    }

    #[Maho\Config\Route('/admin/catalog_product_review/jsonProductInfo')]
    public function jsonProductInfoAction(): void
    {
        $response = new \Maho\DataObject();
        $product = Mage::getModel('catalog/product')
            ->load((int) $this->getRequest()->getParam('id'));

        if ($product->getId()) {
            $response->setId($product->getId());
            $response->addData($product->getData());
        } else {
            $response->setError(1);
            $response->setMessage(Mage::helper('catalog')->__('Unable to get the product ID.'));
        }

        $this->getResponse()->setBodyJson($response);
    }

    #[Maho\Config\Route('/admin/catalog_product_review/post')]
    public function postAction(): void
    {
        $productId  = $this->getRequest()->getParam('product_id', false);
        $session    = Mage::getSingleton('adminhtml/session');

        if ($data = $this->getRequest()->getPost()) {
            if (Mage::app()->isSingleStoreMode()) {
                $data['stores'] = [Mage::app()->getStore(true)->getId()];
            } elseif (isset($data['select_stores'])) {
                $data['stores'] = $data['select_stores'];
            }

            // A review without a customer is the review of an administrator
            $review = Mage::getModel('review/review')->setData($data)
                ->setEntityPkValue((int) $productId)
                ->setStoreId((int) Mage::app()->getStore()->getId())
                ->setCustomerId(null);

            try {
                $this->reviewService()->save($review, (array) $this->getRequest()->getParam('ratings', []));

                $session->addSuccess(Mage::helper('catalog')->__('The review has been saved.'));
                if ($this->getRequest()->getParam('ret') == 'pending') {
                    $this->getResponse()->setRedirect($this->getUrl('*/*/pending'));
                } else {
                    $this->getResponse()->setRedirect($this->getUrl('*/*/'));
                }

                return;
            } catch (Mage_Core_Exception $e) {
                $this->addErrors($e);
            } catch (Exception $e) {
                $session->addException($e, Mage::helper('adminhtml')->__('An error occurred while saving review.'));
            }
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }

    #[Maho\Config\Route('/admin/catalog_product_review/ratingItems')]
    public function ratingItemsAction(): void
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/review_rating_detailed')->setIndependentMode()->toHtml(),
        );
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'pending' => Mage::getSingleton('admin/session')->isAllowed('catalog/reviews_ratings/reviews/pending'),
            default => Mage::getSingleton('admin/session')->isAllowed('catalog/reviews_ratings/reviews/all'),
        };
    }

    protected function reviewService(): Mage_Review_Service_Review
    {
        return Mage::getService('review/review');
    }

    /**
     * Show one error for each field of a Mage_Core_Exception_Input, or the message of $e.
     */
    private function addErrors(Mage_Core_Exception $e): void
    {
        $errors = $e instanceof Mage_Core_Exception_Input ? $e->getErrors() : [['message' => $e->getMessage()]];
        foreach ($errors as $error) {
            Mage::getSingleton('adminhtml/session')->addError($error['message']);
        }
    }
}
