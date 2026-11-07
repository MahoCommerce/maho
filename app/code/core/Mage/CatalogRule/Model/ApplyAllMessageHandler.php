<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

class Mage_CatalogRule_Model_ApplyAllMessageHandler
{
    /**
     * @throws Throwable when the rules could not be applied, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function applyAll(Mage_CatalogRule_Model_ApplyAllMessage $message): void
    {
        $helper = Mage::helper('catalogrule');
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($helper): string {
                Mage::getModel('catalogrule/rule')->applyAll();
                return $helper->__('The catalog prices now use the active catalog price rules.');
            },
            $helper->__('The catalog price rules have been applied'),
            $helper->__('The catalog price rules could not be applied'),
            'adminhtml/promo_catalog/index',
            $message->adminUserId,
            'promo/catalog',
        );
    }
}
