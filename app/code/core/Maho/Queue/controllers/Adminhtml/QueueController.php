<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;

class Maho_Queue_Adminhtml_QueueController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/maho_queue';

    private const ACTION_RESOURCES = [
        'index'       => 'system/maho_queue/view',
        'grid'        => 'system/maho_queue/view',
        'view'        => 'system/maho_queue/view',
        'retry'       => 'system/maho_queue/retry',
        'massretry'   => 'system/maho_queue/retry',
        'discard'     => 'system/maho_queue/discard',
        'massdiscard' => 'system/maho_queue/discard',
    ];

    #[\Override]
    public function preDispatch(): static
    {
        $this->_setForcedFormKeyActions(['retry', 'discard', 'massRetry', 'massDiscard']);
        return parent::preDispatch();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        $action = strtolower((string) $this->getRequest()->getActionName());
        $resource = self::ACTION_RESOURCES[$action] ?? static::ADMIN_RESOURCE;
        return Mage::getSingleton('admin/session')->isAllowed($resource);
    }

    protected function _initAction(): static
    {
        $this->loadLayout()
            ->_setActiveMenu('system/maho_queue')
            ->_addBreadcrumb(
                Mage::helper('queue')->__('Message Queue'),
                Mage::helper('queue')->__('Message Queue'),
            );

        return $this;
    }

    #[Maho\Config\Route('/admin/queue')]
    public function indexAction(): void
    {
        if (QueueManager::transportName() === QueueManager::TRANSPORT_REDIS) {
            Mage::getSingleton('adminhtml/session')->addNotice(
                Mage::helper('queue')->__('The Redis transport is active: pending messages live in Redis and are not listed here, only failures are.'),
            );
        }

        $this->_title(Mage::helper('queue')->__('Message Queue'));
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/queue/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('queue/adminhtml_message_grid')->toHtml(),
        );
    }

    #[Maho\Config\Route('/admin/queue/view')]
    public function viewAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $message = Mage::getModel('queue/message')->load($id);

        if (!$message->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('queue')->__('Message not found.'));
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_queue_message', $message);

        $this->_title(Mage::helper('queue')->__('Message #%s', $id));
        $this->_initAction();
        $this->_addBreadcrumb(
            Mage::helper('queue')->__('Message #%s', $id),
            Mage::helper('queue')->__('Message #%s', $id),
        );
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/queue/retry')]
    public function retryAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        if (QueueManager::retryStoredMessage($id)) {
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('queue')->__('Message re-queued.'));
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('queue')->__('Only failed messages can be retried.'));
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/queue/discard')]
    public function discardAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        if (QueueManager::discardStoredMessage($id)) {
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('queue')->__('Message discarded.'));
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('queue')->__('Message not found.'));
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/queue/massRetry')]
    public function massRetryAction(): void
    {
        $retried = 0;
        foreach ($this->getMessageIds() as $id) {
            if (QueueManager::retryStoredMessage($id)) {
                $retried++;
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('queue')->__('%s message(s) re-queued.', $retried),
        );
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/queue/massDiscard')]
    public function massDiscardAction(): void
    {
        $discarded = 0;
        foreach ($this->getMessageIds() as $id) {
            if (QueueManager::discardStoredMessage($id)) {
                $discarded++;
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('queue')->__('%s message(s) discarded.', $discarded),
        );
        $this->_redirect('*/*/');
    }

    /**
     * @return list<int>
     */
    private function getMessageIds(): array
    {
        $ids = $this->getRequest()->getParam('message');
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_map(intval(...), $ids));
    }
}
