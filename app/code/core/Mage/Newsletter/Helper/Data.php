<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_TEMPLATE_FILTER = 'global/newsletter/tempate_filter';
    public const XML_PATH_SEND_BATCH_SIZE = 'newsletter/sending/batch_size';

    protected $_moduleName = 'Mage_Newsletter';

    /**
     * Recipients handled by a single queue message
     */
    public function getSendBatchSize(): int
    {
        return max(1, Mage::getStoreConfigAsInt(self::XML_PATH_SEND_BATCH_SIZE));
    }

    /**
     * Queue a batch for every campaign whose start date has come.
     *
     * A campaign started by hand is dispatched right away; one scheduled for
     * later enters the queue here, when its time arrives, rather than sitting
     * in it as a long-delayed message. This also picks up campaigns whose chain
     * of batches was lost with a worker. Returns the number of campaigns swept;
     * a sweep is a no-op when a batch of that campaign is already queued.
     */
    public function scheduleDueQueues(): int
    {
        $collection = Mage::getResourceModel('newsletter/queue_collection')
            ->addOnlyForSendingFilter();

        $swept = 0;
        /** @var Mage_Newsletter_Model_Queue $queue */
        foreach ($collection as $queue) {
            try {
                $queue->scheduleSending();
                $swept++;
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }

        return $swept;
    }

    /**
     * Retrieve subsription confirmation url
     *
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @return string
     */
    public function getConfirmationUrl($subscriber)
    {
        return Mage::getModel('core/url')
            ->setStore($subscriber->getStoreId())
            ->getUrl('newsletter/subscriber/confirm', [
                'id'     => $subscriber->getId(),
                'code'   => $subscriber->getCode(),
            ]);
    }

    /**
     * Retrieve unsubsription url
     *
     * @param Mage_Newsletter_Model_Subscriber $subscriber
     * @return string
     */
    public function getUnsubscribeUrl($subscriber)
    {
        return Mage::getModel('core/url')
            ->setStore($subscriber->getStoreId())
            ->getUrl('newsletter/subscriber/unsubscribe', [
                'id'     => $subscriber->getId(),
                'code'   => $subscriber->getCode(),
            ]);
    }

    /**
     * Retrieve Template processor for Newsletter template
     *
     * @return false|Mage_Core_Model_Abstract|\Maho\Filter\Template
     */
    public function getTemplateProcessor()
    {
        $model = (string) Mage::getConfig()->getNode(self::XML_PATH_TEMPLATE_FILTER);
        return Mage::getModel($model);
    }
}
