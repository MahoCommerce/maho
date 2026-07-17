<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

class Mage_Review_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_REVIEW_GUETS_ALLOW = 'catalog/review/allow_guest';

    protected $_moduleName = 'Mage_Review';

    /**
     * @param string $origDetail
     * @return string
     */
    public function getDetail($origDetail)
    {
        return nl2br(Mage::helper('core/string')->truncate($origDetail, 50));
    }

    /**
     * getDetailHtml return short detail info in HTML
     * @param string $origDetail Full detail info
     * @return string
     */
    public function getDetailHtml($origDetail)
    {
        return nl2br(Mage::helper('core/string')->truncate($this->escapeHtml($origDetail), 50));
    }

    /**
     * @return bool
     */
    public function getIsGuestAllowToWrite()
    {
        return Mage::getStoreConfigFlag(self::XML_REVIEW_GUETS_ALLOW);
    }

    /**
     * Get review statuses with their codes
     *
     * @return array
     */
    public function getReviewStatuses()
    {
        return [
            Mage_Review_Model_Review::STATUS_APPROVED     => $this->__('Approved'),
            Mage_Review_Model_Review::STATUS_PENDING      => $this->__('Pending'),
            Mage_Review_Model_Review::STATUS_NOT_APPROVED => $this->__('Not Approved'),
        ];
    }

    /**
     * Get review statuses as option array
     *
     * @return array
     */
    public function getReviewStatusesOptionArray()
    {
        $result = [];
        foreach ($this->getReviewStatuses() as $k => $v) {
            $result[] = ['value' => $k, 'label' => $v];
        }

        return $result;
    }

    /**
     * Check if current customer has any product reviews
     */
    public function customerHasReviews(): bool
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if (!$customerId) {
            return false;
        }

        return Mage::getModel('review/review')->getProductCollection()
            ->addStoreFilter(Mage::app()->getStore()->getId())
            ->addCustomerFilter($customerId)
            ->getSize() > 0;
    }
}
