<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Adminhtml_GiftcardController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'sales/giftcard/manage';

    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/giftcard/manage')
            ->_addBreadcrumb(
                'Sales',
                'Sales',
            )
            ->_addBreadcrumb(
                'Gift Cards',
                'Gift Cards',
            );

        return $this;
    }

    /**
     * Index action - gift card grid
     */
    #[Maho\Config\Route('/admin/giftcard/index')]
    public function indexAction(): void
    {
        $this->_initAction();
        $this->_title('Gift Cards');
        $this->renderLayout();
    }

    /**
     * Grid action for AJAX
     */
    #[Maho\Config\Route('/admin/giftcard/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * AJAX reload of the Transaction History tab's grid (filter/sort).
     */
    #[Maho\Config\Route('/admin/giftcard/historyGrid')]
    public function historyGridAction(): void
    {
        $model = Mage::getModel('giftcard/giftcard');
        $id = (int) $this->getRequest()->getParam('id');
        if ($id > 0) {
            $model->load($id);
        }
        Mage::register('current_giftcard', $model);

        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('giftcard/adminhtml_giftcard_edit_history')
                ->toHtml(),
        );
    }

    /**
     * New gift card
     */
    #[Maho\Config\Route('/admin/giftcard/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit gift card
     */
    #[Maho\Config\Route('/admin/giftcard/edit')]
    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $model = $id ? $this->getService()->getById($id) : Mage::getModel('giftcard/giftcard');
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }

        $this->_title($model->getId() ? $model->getCode() : Mage::helper('giftcard')->__('New Gift Card'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (is_array($data) && $data !== []) {
            $model->setData($data);
        }

        Mage::register('current_giftcard', $model);

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Save gift card. A changed balance of an existing card is an adjustment with a history entry.
     */
    #[Maho\Config\Route('/admin/giftcard/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $session = Mage::getSingleton('adminhtml/session');
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $model = $id ? $this->getService()->getById($id) : Mage::getModel('giftcard/giftcard');
            $saveData = $data;
            // Nothing posted leaves the stored associations untouched
            $websiteIds = $saveData['website_ids'] ?? null;
            unset($saveData['website_ids']);

            $isNew = !$model->getId();
            $oldBalance = (float) $model->getBalance();
            $newBalance = isset($saveData['balance']) ? (float) $saveData['balance'] : $oldBalance;
            if (!$isNew) {
                // update() below is the only writer of the balance of an existing card
                $saveData['balance'] = $oldBalance;
            }

            $model->setData($saveData);
            if ($websiteIds !== null) {
                $model->setWebsiteIds(is_array($websiteIds) ? $websiteIds : [$websiteIds]);
            }
            if ($isNew) {
                $this->getService()->save($model);
            } else {
                $this->getService()->update($model, $newBalance, null, $saveData['comment'] ?? 'Admin adjustment');
            }

            $session->addSuccess(Mage::helper('giftcard')->__('The gift card has been saved.'));
            $session->setFormData(false);
            $this->getRequest()->getParam('back') ? $this->_redirect('*/*/edit', ['id' => $model->getId()]) : $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('giftcard')->__('Unable to save the gift card.'));
        }
        $session->setFormData($data);
        $this->_redirect('*/*/edit', ['id' => $id]);
    }

    #[Maho\Config\Route('/admin/giftcard/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $this->getService()->delete($this->getService()->getById((int) $this->getRequest()->getParam('id')));
            $session->addSuccess(Mage::helper('giftcard')->__('The gift card has been deleted.'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/giftcard/massDelete')]
    public function massDeleteAction(): void
    {
        $this->massAction(fn(Maho_Giftcard_Model_Giftcard $giftcard) => $this->getService()->delete($giftcard), 'Total of %d record(s) were deleted.');
    }

    #[Maho\Config\Route('/admin/giftcard/massStatus')]
    public function massStatusAction(): void
    {
        $status = (string) $this->getRequest()->getParam('status');
        $this->massAction(fn(Maho_Giftcard_Model_Giftcard $giftcard) => $this->getService()->save($giftcard->setStatus($status)), 'Total of %d record(s) were updated.');
    }

    /**
     * Run $action on each selected card, then show $successMessage with the number of cards.
     *
     * @param callable(Maho_Giftcard_Model_Giftcard): mixed $action
     */
    private function massAction(callable $action, string $successMessage): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $giftcardIds = $this->getRequest()->getParam('giftcard');
        if (!is_array($giftcardIds)) {
            $session->addError(Mage::helper('giftcard')->__('Please select gift card(s).'));
            $this->_redirect('*/*/index');
            return;
        }
        try {
            foreach ($giftcardIds as $giftcardId) {
                $action($this->getService()->getById((int) $giftcardId));
            }
            $session->addSuccess(Mage::helper('giftcard')->__($successMessage, count($giftcardIds)));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Check balance action (AJAX)
     */
    #[Maho\Config\Route('/admin/giftcard/checkBalance')]
    public function checkBalanceAction(): void
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        try {
            $giftcard = $this->getService()->getByCode((string) $this->getRequest()->getParam('code'));
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(['success' => false, 'message' => $e->getMessage()]));
            return;
        }

        $websiteIds = $giftcard->getWebsiteIds();
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode([
            'success' => true,
            'giftcard_id' => $giftcard->getId(),
            'code' => $giftcard->getCode(),
            'balance' => $giftcard->getBalance(),
            'initial_balance' => $giftcard->getInitialBalance(),
            // A card orphaned by a website deletion has no currency source
            'currency_code' => $websiteIds === [] ? null : $giftcard->getCurrencyCode(),
            'website_ids' => $websiteIds,
            'status' => $giftcard->getStatus(),
            'is_valid' => $giftcard->isValid(),
            'expires_at' => $giftcard->getExpiresAt(),
        ]));
    }

    private function getService(): Maho_Giftcard_Service_Giftcard
    {
        return Mage::getService('giftcard/giftcard');
    }
}
