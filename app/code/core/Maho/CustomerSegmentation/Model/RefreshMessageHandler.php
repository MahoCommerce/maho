<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Model_RefreshMessageHandler
{
    /**
     * @throws Throwable when the segment could not be refreshed, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function refresh(Maho_CustomerSegmentation_Model_RefreshMessage $message): void
    {
        /** @var Maho_CustomerSegmentation_Service_Segment $service */
        $service = Mage::getService('customersegmentation/segment');
        try {
            $segment = $service->getById($message->segmentId);
        } catch (Mage_Core_Exception_NoSuchEntity) {
            // An administrator deleted the segment after the request
            return;
        }

        $helper = Mage::helper('customersegmentation');
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($service, $segment, $helper): string {
                $service->refresh($segment);
                return $helper->__('The segment "%s" has %d customers.', $segment->getName(), (int) $segment->getMatchedCustomersCount());
            },
            $helper->__('The segment "%s" has been refreshed', $segment->getName()),
            $helper->__('The segment "%s" could not be refreshed', $segment->getName()),
            'adminhtml/customersegmentation_index/index',
            $message->adminUserId,
            'customer/customersegmentation/refresh',
        );
    }
}
