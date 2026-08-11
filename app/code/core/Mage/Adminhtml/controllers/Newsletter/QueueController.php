<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

use Mage_Newsletter_Model_Queue as Queue;

class Mage_Adminhtml_Newsletter_QueueController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'newsletter/queue';

    /**
     * Content and audience are frozen once a campaign starts, so the edit form disables
     * these inputs and a browser posts none of them: applying the empties would blank
     * the campaign, and a request that carries them anyway is refused.
     */
    protected const FROZEN_AFTER_START = [
        'start_at', 'stores', 'customer_segments',
        'subject', 'sender_name', 'sender_email', 'text', 'styles',
    ];

    /**
     * Queue list action
     */
    #[Maho\Config\Route('/admin/newsletter_queue/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Newsletter'))->_title($this->__('Newsletter Queue'));

        if ($this->getRequest()->getQuery('ajax')) {
            $this->_forward('grid');
            return;
        }

        $this->loadLayout();

        $this->_setActiveMenu('newsletter/queue');

        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/newsletter_queue', 'queue'),
        );

        $this->_addBreadcrumb(Mage::helper('newsletter')->__('Newsletter Queue'), Mage::helper('newsletter')->__('Newsletter Queue'));

        $this->renderLayout();
    }

    /**
     * Drop Newsletter queue template
     */
    #[Maho\Config\Route('/admin/newsletter_queue/drop')]
    public function dropAction(): void
    {
        $request = $this->getRequest();
        if ($request->getParam('text') && !$request->getPost('text')) {
            $this->getResponse()->setRedirect($this->getUrl('*/newsletter_queue'));
        }
        $this->loadLayout('newsletter_queue_preview');
        $this->renderLayout();
    }

    /**
     * Preview Newsletter queue template
     */
    #[Maho\Config\Route('/admin/newsletter_queue/preview')]
    public function previewAction()
    {
        $this->loadLayout();
        $data = $this->getRequest()->getParams();
        if (empty($data) || !isset($data['id'])) {
            $this->_forward('noRoute');
            return $this;
        }

        // set default value for selected store
        $data['preview_store_id'] = Mage::app()->getAnyStoreView()->getId();

        $this->getLayout()->getBlock('preview_form')->setFormData($data);
        $this->renderLayout();
    }

    /**
     * Queue list Ajax action
     */
    #[Maho\Config\Route('/admin/newsletter_queue/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/newsletter_queue_grid')->toHtml());
    }

    #[Maho\Config\Route('/admin/newsletter_queue/start')]
    public function startAction(): void
    {
        $queue = Mage::getModel('newsletter/queue')
            ->load($this->getRequest()->getParam('id'));
        if ($queue->getId()) {
            if (!in_array($queue->getQueueStatus(), [Queue::STATUS_NEVER, Queue::STATUS_PAUSE])) {
                $this->_redirect('*/*');
                return;
            }

            $queue->setQueueStartAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->setQueueStatus(Queue::STATUS_SENDING)
                ->save();

            $this->_scheduleSending($queue);
        }

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/newsletter_queue/pause')]
    public function pauseAction(): void
    {
        $queue = Mage::getSingleton('newsletter/queue')
            ->load($this->getRequest()->getParam('id'));

        if ($queue->getQueueStatus() != Queue::STATUS_SENDING) {
            $this->_redirect('*/*');
            return;
        }

        $queue->setQueueStatus(Queue::STATUS_PAUSE);
        $queue->save();

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/newsletter_queue/resume')]
    public function resumeAction(): void
    {
        $queue = Mage::getSingleton('newsletter/queue')
            ->load($this->getRequest()->getParam('id'));

        if ($queue->getQueueStatus() != Queue::STATUS_PAUSE) {
            $this->_redirect('*/*');
            return;
        }

        $queue->setQueueStatus(Queue::STATUS_SENDING);
        $queue->save();

        $this->_scheduleSending($queue);

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/newsletter_queue/cancel')]
    public function cancelAction(): void
    {
        $queue = Mage::getSingleton('newsletter/queue')
            ->load($this->getRequest()->getParam('id'));

        if ($queue->getQueueStatus() != Queue::STATUS_SENDING) {
            $this->_redirect('*/*');
            return;
        }

        $queue->setQueueStatus(Queue::STATUS_CANCEL);
        $queue->save();

        $this->_redirect('*/*');
    }

    /**
     * Same job as the newsletter_send_all cron, for hosts driving it by URL
     */
    #[Maho\Config\Route('/admin/newsletter_queue/sending')]
    public function sendingAction(): void
    {
        Mage::helper('newsletter')->scheduleDueQueues();
    }

    #[Maho\Config\Route('/admin/newsletter_queue/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Newsletter'))->_title($this->__('Newsletter Queue'));

        Mage::register('current_queue', Mage::getSingleton('newsletter/queue'));

        $id = $this->getRequest()->getParam('id');
        $templateId = $this->getRequest()->getParam('template_id');

        if ($id) {
            $queue = Mage::registry('current_queue')->load($id);
        } elseif ($templateId) {
            $template = Mage::getModel('newsletter/template')->load($templateId);
            $queue = Mage::registry('current_queue')->setTemplateId($template->getId());
        }

        $this->_title($this->__('Edit Queue'));

        $this->loadLayout();

        $this->_setActiveMenu('newsletter/queue');

        $this->_addBreadcrumb(
            Mage::helper('newsletter')->__('Newsletter Queue'),
            Mage::helper('newsletter')->__('Newsletter Queue'),
            $this->getUrl('*/newsletter_queue'),
        );
        $this->_addBreadcrumb(Mage::helper('newsletter')->__('Edit Queue'), Mage::helper('newsletter')->__('Edit Queue'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/newsletter_queue/save')]
    public function saveAction(): void
    {
        try {
            /** @var Queue $queue */
            $queue = Mage::getModel('newsletter/queue');

            $templateId = $this->getRequest()->getParam('template_id');
            if ($templateId) {
                /** @var Mage_Newsletter_Model_Template $template */
                $template = Mage::getModel('newsletter/template')->load($templateId);

                if (!$template->getId() || $template->getIsSystem()) {
                    Mage::throwException($this->__('Wrong newsletter template.'));
                }

                $queue->setTemplateId($template->getId())
                    ->setQueueStatus(Queue::STATUS_NEVER);
            } else {
                $queue->load($this->getRequest()->getParam('id'));
            }

            if (!in_array($queue->getQueueStatus(), [Queue::STATUS_NEVER, Queue::STATUS_PAUSE])) {
                $this->_redirect('*/*');
                return;
            }

            if ($queue->getQueueStatus() == Queue::STATUS_NEVER) {
                $stores = $this->getRequest()->getParam('stores', []);

                $queue->setQueueStartAtByString($this->getRequest()->getParam('start_at'))
                    ->setStores($stores)
                    ->setNewsletterSubject($this->getRequest()->getParam('subject'))
                    ->setNewsletterSenderName($this->getRequest()->getParam('sender_name'))
                    ->setNewsletterSenderEmail($this->getRequest()->getParam('sender_email'))
                    ->setNewsletterText($this->getRequest()->getParam('text'))
                    ->setNewsletterStyles($this->getRequest()->getParam('styles'));

                // Save customer segment assignments if CustomerSegmentation module is enabled
                if (Mage::helper('core')->isModuleEnabled('Maho_CustomerSegmentation')) {
                    $segmentIds = Mage::helper('customersegmentation')
                        ->getQueueSegmentIds($this->getRequest()->getParam('customer_segments', []));

                    $outside = Mage::helper('customersegmentation')->getSegmentsOutsideStores($segmentIds, $stores);
                    if ($outside !== []) {
                        Mage::throwException($this->__(
                            'These customer segments cover none of the selected stores: %s.',
                            implode(', ', $outside),
                        ));
                    }

                    $queue->setCustomerSegmentIds(implode(',', $segmentIds));
                }
            } else {
                $this->_assertNothingFrozenWasPosted();
            }

            if ($queue->getQueueStatus() == Queue::STATUS_PAUSE
                && $this->getRequest()->getParam('_resume', false)
            ) {
                $queue->setQueueStatus(Queue::STATUS_SENDING);
            }

            $queue->save();
            $this->_scheduleSending($queue);
            $this->_redirect('*/*');
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $this->_redirect('*/*/edit', ['id' => $id]);
            } else {
                $this->_redirectReferer();
            }
        }
    }

    protected function _assertNothingFrozenWasPosted(): void
    {
        foreach (self::FROZEN_AFTER_START as $field) {
            if ($this->getRequest()->getParam($field) !== null) {
                Mage::throwException(
                    $this->__('A campaign can no longer be edited once it has started; it can only be paused, resumed or cancelled.'),
                );
            }
        }
    }

    /**
     * A campaign that cannot be queued would sit there looking scheduled, so
     * say so; the cron retries it either way.
     */
    protected function _scheduleSending(Queue $queue): void
    {
        try {
            $queue->scheduleSending();
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError(
                Mage::helper('newsletter')->__('The newsletter was saved but could not be queued for sending: %s', $e->getMessage()),
            );
        }
    }
}
