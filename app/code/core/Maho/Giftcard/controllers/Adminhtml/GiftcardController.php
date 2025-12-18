<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Adminhtml_GiftcardController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'customer/giftcard/manage';

    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('customer/giftcard/manage')
            ->_addBreadcrumb(
                'Customers',
                'Customers',
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
    public function indexAction(): void
    {
        $this->_initAction();
        $this->_title('Gift Cards');
        $this->renderLayout();
    }

    /**
     * Grid action for AJAX
     */
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * New gift card
     */
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit gift card
     */
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
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('current_giftcard', $model);

        $this->_initAction();
        $this->_addContent($this->getLayout()->createBlock('giftcard/adminhtml_giftcard_edit'));
        $this->renderLayout();
    }

    /**
     * Save gift card
     */
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
                if (!$model->getId() && empty($data['code'])) {
                    $data['code'] = Mage::helper('giftcard')->generateCode();
                }

                // Set expiration if not set
                if (!$model->getId() && empty($data['expires_at'])) {
                    $data['expires_at'] = Mage::helper('giftcard')->calculateExpirationDate();
                }

                // If balance changed, record as adjustment
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
    public function checkBalanceAction(): void
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        $code = $this->getRequest()->getParam('code');

        if (empty($code)) {
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
            'status' => $giftcard->getStatus(),
            'is_valid' => $giftcard->isValid(),
            'expires_at' => $giftcard->getExpiresAt(),
        ]));
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/giftcard/manage');
    }
}
