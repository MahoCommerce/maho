<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_RobotsController extends Mage_Core_Controller_Front_Action
{
    /**
     * Crawlers fetch this file constantly, so it must not create a session per request.
     */
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        return parent::preDispatch();
    }

    /**
     * A robots.txt present in the public directory is served by the web server instead.
     */
    #[Maho\Config\Route('/robots.txt', name: 'sitemap.robots', methods: ['GET'])]
    public function indexAction(): void
    {
        /** @var Mage_Sitemap_Model_Robots $robots */
        $robots = Mage::getSingleton('sitemap/robots');
        $store = Mage::app()->getStore();

        if (!$robots->isEnabled($store)) {
            // A crawler reads 4xx as "no policy, crawl everything".
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        try {
            $body = $robots->generate($store);
        } catch (Throwable $e) {
            // A 5xx here would read as "disallow everything".
            Mage::logException($e);
            $body = Mage_Sitemap_Model_Robots::FALLBACK;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)
            ->setBody($body);
    }
}
