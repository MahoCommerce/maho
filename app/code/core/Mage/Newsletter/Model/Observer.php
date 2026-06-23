<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

use Symfony\Component\Mime\Email;

class Mage_Newsletter_Model_Observer
{
    /**
     * Add RFC 8058 List-Unsubscribe headers to outgoing newsletter broadcasts.
     *
     * List-Unsubscribe exposes the one-click endpoint, List-Unsubscribe-Post opts the URL into
     * the one-click POST flow that Gmail/Yahoo require from bulk senders. The send() path is
     * shared with transactional mail — including the newsletter confirmation and unsubscription
     * notifications, which also carry a 'subscriber' variable — so the headers are added only
     * when the sender explicitly marks the mail as a newsletter broadcast via 'is_newsletter'.
     */
    #[Maho\Config\Observer('email_template_send_before')]
    public function addListUnsubscribeHeaders(\Maho\Event\Observer $observer): void
    {
        $mail = $observer->getEvent()->getMail();
        if (!$mail instanceof Email) {
            return;
        }
        $variables = $observer->getEvent()->getVariables();
        if (empty($variables['is_newsletter'])) {
            return;
        }
        $subscriber = $variables['subscriber'] ?? null;
        if (!$subscriber instanceof Mage_Newsletter_Model_Subscriber) {
            return;
        }
        $url = Mage::helper('newsletter')->getUnsubscribeUrl($subscriber);
        $headers = $mail->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', '<' . $url . '>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    }

    /**
     * @return $this
     */
    #[Maho\Config\Observer('customer_save_after')]
    public function subscribeCustomer(\Maho\Event\Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        if (($customer instanceof Mage_Customer_Model_Customer)) {
            Mage::getModel('newsletter/subscriber')->subscribeCustomer($customer);
        }
        return $this;
    }

    /**
     * Customer delete handler
     *
     * @return $this
     */
    #[Maho\Config\Observer('customer_delete_after')]
    public function customerDeleted(\Maho\Event\Observer $observer)
    {
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->loadByEmail($observer->getEvent()->getCustomer()->getEmail());
        if ($subscriber->getId()) {
            $subscriber->delete();
        }
        return $this;
    }

    /**
     * @param \Maho\Event\Observer $schedule
     */
    #[Maho\Config\CronJob('newsletter_send_all', schedule: '*/5 * * * *')]
    public function scheduledSend($schedule)
    {
        $countOfQueue  = 3;
        $countOfSubscritions = 20;

        /** @var Mage_Newsletter_Model_Resource_Queue_Collection $collection */
        $collection = Mage::getModel('newsletter/queue')->getCollection()
            ->setPageSize($countOfQueue)
            ->setCurPage(1)
            ->addOnlyForSendingFilter()
            ->load();

        $collection->walk('sendPerSubscriber', [$countOfSubscritions]);
    }
}
