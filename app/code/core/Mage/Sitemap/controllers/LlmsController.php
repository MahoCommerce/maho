<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_LlmsController extends Mage_Core_Controller_Front_Action
{
    /**
     * Agents fetch this file constantly, so it must not create a session per request.
     */
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        return parent::preDispatch();
    }

    /**
     * An llms.txt present in the public directory is served by the web server instead.
     */
    #[Maho\Config\Route('/llms.txt', name: 'sitemap.llms', methods: ['GET'])]
    public function indexAction(): void
    {
        /** @var Mage_Sitemap_Model_Llms $llms */
        $llms = Mage::getSingleton('sitemap/llms');
        $store = Mage::app()->getStore();

        if (!$llms->isEnabled($store)) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        try {
            $body = $llms->generate($store);
        } catch (Throwable $e) {
            // Unlike robots.txt, a missing llms.txt carries no crawl-policy meaning.
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'text/markdown; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'public, max-age=3600', true)
            ->setBody($body);
    }
}
