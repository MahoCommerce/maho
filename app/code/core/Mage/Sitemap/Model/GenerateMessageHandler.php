<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_GenerateMessageHandler
{
    /**
     * @throws Throwable when the sitemap could not be generated, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function generate(Mage_Sitemap_Model_GenerateMessage $message): void
    {
        /** @var Mage_Sitemap_Model_Sitemap $sitemap */
        $sitemap = Mage::getModel('sitemap/sitemap')->load($message->sitemapId);
        if (!$sitemap->getId()) {
            // An administrator deleted the sitemap after the request
            return;
        }

        $helper = Mage::helper('sitemap');
        Mage::helper('adminnotification')->runAndNotify(
            function () use ($sitemap, $helper): string {
                $sitemap->generateXml();
                return $helper->__('The sitemap "%s" has been generated.', $sitemap->getSitemapFilename());
            },
            $helper->__('The sitemap has been generated'),
            $helper->__('The sitemap "%s" could not be generated', $sitemap->getSitemapFilename()),
            'adminhtml/sitemap/index',
            $message->adminUserId,
            'catalog/sitemap',
        );
    }
}
