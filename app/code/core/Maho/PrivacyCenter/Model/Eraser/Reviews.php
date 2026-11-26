<?php

/**
 * Removes the name of the customer from the reviews, or deletes the reviews, as the configuration of the store says.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Reviews extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->getReviewAction() === Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_DELETE
            ? Mage::helper('privacycenter')->__('Your product reviews')
            : Mage::helper('privacycenter')->__('The name on your product reviews');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $helper = Mage::helper('privacycenter');
        if ($subject->isGuest()) {
            return $helper->__('A guest review has no link to an email: no review changed');
        }
        $adapter = $this->getConnection();
        $reviewIds = array_map(intval(...), $adapter->fetchCol(
            $adapter->select()
                ->from($this->getTable('review/review_detail'), ['review_id'])
                ->where('customer_id = ?', $subject->customerId),
        ));
        if ($reviewIds === []) {
            return $helper->__('No reviews');
        }

        $adapter->update(
            $this->getTable('rating/rating_option_vote'),
            ['remote_ip' => null, 'remote_ip_long' => null, 'customer_id' => null],
            ['review_id IN (?)' => $reviewIds],
        );

        if ($helper->getReviewAction($subject->storeId) === Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_DELETE) {
            foreach ($reviewIds as $reviewId) {
                Mage::getModel('review/review')->load($reviewId)->delete();
            }
            return $helper->__('%d reviews deleted', count($reviewIds));
        }

        $adapter->update(
            $this->getTable('review/review_detail'),
            ['nickname' => $helper->__('Anonymous'), 'customer_id' => null],
            ['review_id IN (?)' => $reviewIds],
        );
        return $helper->__('%d reviews anonymized', count($reviewIds));
    }
}
