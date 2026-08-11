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
    public const ADMIN_RESOURCE = 'system/tools/maho_queue';

    private const ACTION_RESOURCES = [
        'index'       => 'system/tools/maho_queue/view',
        'grid'        => 'system/tools/maho_queue/view',
        'view'        => 'system/tools/maho_queue/view',
        'retry'       => 'system/tools/maho_queue/retry',
        'massretry'   => 'system/tools/maho_queue/retry',
        'discard'     => 'system/tools/maho_queue/discard',
        'massdiscard' => 'system/tools/maho_queue/discard',
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
            ->_setActiveMenu('system/tools/maho_queue')
            ->_addBreadcrumb(
                Mage::helper('queue')->__('Message Queue'),
                Mage::helper('queue')->__('Message Queue'),
            );

        return $this;
    }

    #[Maho\Config\Route('/admin/queue')]
    public function indexAction(): void
    {
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
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('queue')->__('The message was not retried: only failed or stuck messages without a newer copy of their dedupe key can be retried.'));
        }
        $this->refreshAbandonedNotice();
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
        $this->refreshAbandonedNotice();
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/queue/massRetry')]
    public function massRetryAction(): void
    {
        $retried = 0;
        $skipped = 0;
        foreach ($this->getMessageIds() as $id) {
            if (QueueManager::retryStoredMessage($id)) {
                $retried++;
            } else {
                $skipped++;
            }
        }
        $session = Mage::getSingleton('adminhtml/session');
        if ($retried > 0) {
            $session->addSuccess(Mage::helper('queue')->__('%s message(s) re-queued.', $retried));
        }
        if ($skipped > 0) {
            $session->addNotice(Mage::helper('queue')->__('%s message(s) were skipped: only failed or stuck messages without a newer copy of their dedupe key can be retried.', $skipped));
        }
        $this->refreshAbandonedNotice();
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
        $this->refreshAbandonedNotice();
        $this->_redirect('*/*/');
    }

    /** The abandoned-count notice is cached; an operator action must not leave it stale. */
    private function refreshAbandonedNotice(): void
    {
        Mage::app()->removeCache(Maho_Queue_Model_Observer::ABANDONED_COUNT_CACHE_KEY);
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
