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
     * Set forced form key actions for CSRF protection
     */
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'massDelete', 'massStatus']);
        return parent::preDispatch();
    }

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
     * AJAX grid action for the Transaction History tab on the edit page.
     *
     * Registers the current gift card so the tab block scopes its
     * collection correctly, then renders the grid in isolation (Mage's
     * Tabs widget reloads the inner grid via this URL on page filter/sort).
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
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('giftcard/giftcard');

        if ($id) {
            $model->load($id);

            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('giftcard')->__('This gift card no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getCode() : Mage::helper('giftcard')->__('New Gift Card'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (is_array($data) && $data !== []) {
            $model->setData($data);
        }

        Mage::register('current_giftcard', $model);

        // Layout XML handle adminhtml_giftcard_edit registers the Form_Container
        // in `content` and the Tabs block in `left`; loadLayout() picks both
        // up automatically via the handle. The form and the transaction-history
        // grid render as separate tabs of the same edit form.
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Save gift card
     */
    #[Maho\Config\Route('/admin/giftcard/save')]
    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $id = $this->getRequest()->getParam('id');
            $model = Mage::getModel('giftcard/giftcard');

            if ($id) {
                $model->load($id);
            }

            try {
                // Generate code if creating new
                if (!$model->getId() && (!isset($data['code']) || $data['code'] === '')) {
                    $data['code'] = Mage::helper('giftcard')->generateCode();
                }

                // Normalise the multiselect post: the form sends website_ids[]
                // (or, if the JS is bypassed, sometimes a single value). Keep
                // the legacy website_id scalar in sync with the first selection
                // so currency derivation and the back-compat FK keep working.
                $websiteIds = $data['website_ids'] ?? [];
                if (!is_array($websiteIds)) {
                    $websiteIds = $websiteIds === '' ? [] : [$websiteIds];
                }
                $websiteIds = array_values(array_unique(array_filter(array_map('intval', $websiteIds))));
                if (empty($websiteIds)) {
                    // Nothing posted (e.g. an old form, an API caller) — fall
                    // back to the current admin website so saves never land
                    // with an empty association set that would orphan the card.
                    $websiteIds = [(int) Mage::app()->getWebsite()->getId()];
                }
                $data['website_ids'] = $websiteIds;
                $data['website_id'] = $websiteIds[0];

                // Set expiration if not set
                if (!$model->getId() && (!isset($data['expires_at']) || $data['expires_at'] === '')) {
                    $data['expires_at'] = Mage::helper('giftcard')->calculateExpirationDate();
                }

                // For new gift cards, set initial_balance = balance
                if (!$model->getId() && isset($data['balance'])) {
                    $data['initial_balance'] = $data['balance'];
                }

                // If balance changed on existing card, record as adjustment
                $oldBalance = (float) $model->getBalance();
                $newBalance = isset($data['balance']) ? (float) $data['balance'] : $oldBalance;

                $model->setData($data);
                $model->save();

                // Record balance adjustment if changed
                if ($model->getId() && $oldBalance != $newBalance) {
                    $model->adjustBalance($newBalance, $data['comment'] ?? 'Admin adjustment');
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('giftcard')->__('The gift card has been saved.'),
                );

                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                    return;
                }

                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Delete gift card
     */
    #[Maho\Config\Route('/admin/giftcard/delete')]
    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('giftcard/giftcard')->load($id);
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('giftcard')->__('The gift card has been deleted.'),
                );

                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }
        }

        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('giftcard')->__('Unable to find a gift card to delete.'),
        );

        $this->_redirect('*/*/');
    }

    /**
     * Mass delete action
     */
    #[Maho\Config\Route('/admin/giftcard/massDelete')]
    public function massDeleteAction(): void
    {
        $giftcardIds = $this->getRequest()->getParam('giftcard');

        if (!is_array($giftcardIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('giftcard')->__('Please select gift card(s).'),
            );
        } else {
            try {
                foreach ($giftcardIds as $giftcardId) {
                    $giftcard = Mage::getModel('giftcard/giftcard')->load($giftcardId);
                    $giftcard->delete();
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('giftcard')->__(
                        'Total of %d record(s) were deleted.',
                        count($giftcardIds),
                    ),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Mass status change action
     */
    #[Maho\Config\Route('/admin/giftcard/massStatus')]
    public function massStatusAction(): void
    {
        $giftcardIds = $this->getRequest()->getParam('giftcard');
        $status = $this->getRequest()->getParam('status');

        if (!is_array($giftcardIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('giftcard')->__('Please select gift card(s).'),
            );
        } else {
            try {
                foreach ($giftcardIds as $giftcardId) {
                    $giftcard = Mage::getModel('giftcard/giftcard')
                        ->load($giftcardId)
                        ->setStatus($status)
                        ->save();
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('giftcard')->__(
                        'Total of %d record(s) were updated.',
                        count($giftcardIds),
                    ),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
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

        $code = $this->getRequest()->getParam('code');

        if ($code === null || $code === '') {
            $this->getResponse()->setBody(json_encode([
                'success' => false,
                'message' => 'Please enter a gift card code.',
            ]));
            return;
        }

        $giftcard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

        if (!$giftcard->getId()) {
            $this->getResponse()->setBody(json_encode([
                'success' => false,
                'message' => 'Gift card not found.',
            ]));
            return;
        }

        $this->getResponse()->setBody(json_encode([
            'success' => true,
            'giftcard_id' => $giftcard->getId(),
            'code' => $giftcard->getCode(),
            'balance' => $giftcard->getBalance(),
            'initial_balance' => $giftcard->getInitialBalance(),
            'currency_code' => $giftcard->getCurrencyCode(),
            'website_id' => $giftcard->getWebsiteId(),
            'status' => $giftcard->getStatus(),
            'is_valid' => $giftcard->isValid(),
            'expires_at' => $giftcard->getExpiresAt(),
        ]));
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/giftcard/manage');
    }
}
