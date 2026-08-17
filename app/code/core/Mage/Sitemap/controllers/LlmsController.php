<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_LlmsController extends Mage_Core_Controller_Front_Action
{
    public const CACHE_LIFETIME = 3600;

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

        $this->_render(fn(): string => $llms->generate($store), 'llms_' . $store->getId());
    }

    /**
     * The same index followed by the full text of the information pages.
     */
    #[Maho\Config\Route('/llms-full.txt', name: 'sitemap.llms.full', methods: ['GET'])]
    public function fullAction(): void
    {
        /** @var Mage_Sitemap_Model_Llms $llms */
        $llms = Mage::getSingleton('sitemap/llms');
        $store = Mage::app()->getStore();

        if (!$llms->isFullEnabled($store)) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->_render(fn(): string => $llms->generateFull($store), 'llms_full_' . $store->getId());
    }

    /**
     * The cache id carries the store view id, so every store view keeps its own file.
     *
     * @param callable(): string $generator
     */
    protected function _render(callable $generator, ?string $cacheId = null): void
    {
        $cacheId = Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP) ? $cacheId : null;

        try {
            $body = $cacheId === null ? false : Mage::app()->loadCache($cacheId);
            if (!is_string($body) || $body === '') {
                $body = $generator();
                if ($cacheId !== null) {
                    Mage::app()->saveCache(
                        $body,
                        $cacheId,
                        [
                            Mage_Core_Block_Abstract::CACHE_GROUP,
                            Mage_Cms_Model_Page::CACHE_TAG,
                            Mage_Catalog_Model_Category::CACHE_TAG,
                            Mage_Core_Model_Config::CACHE_TAG,
                        ],
                        self::CACHE_LIFETIME,
                    );
                }
            }
        } catch (Throwable $e) {
            // Unlike robots.txt, a missing llms.txt carries no crawl-policy meaning.
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'text/markdown; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_LIFETIME, true)
            ->setBody($body);
    }
}
