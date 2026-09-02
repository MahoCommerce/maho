<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Observer
{
    /**
     * Runs before the front controller computes the path info and before the core rewrite
     * observer, so URL rewrites match the path without the suffix.
     */
    #[Maho\Config\Observer('controller_front_init_before')]
    public function stripMarkdownSuffix(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('contentnegotiation');
        $request = Mage::app()->getRequest();
        if (!$helper->isEnabled() || !$request->isGet()) {
            return;
        }

        $uri = (string) $request->getRequestUri();
        $query = '';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $query = substr($uri, $pos);
            $uri = substr($uri, 0, $pos);
        }
        if (!str_ends_with($uri, Maho_ContentNegotiation_Helper_Data::SUFFIX)) {
            return;
        }

        $path = substr($uri, 0, -strlen(Maho_ContentNegotiation_Helper_Data::SUFFIX));
        if (trim($path, '/') === '') {
            return;
        }

        $path = Mage::helper('core/url')->addOrRemoveTrailingSlash($path);
        $request->setRequestUri($path . $query);
        $helper->markSuffixStripped();
    }

    #[Maho\Config\Observer('controller_action_predispatch')]
    public function serveCachedMarkdown(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Varien_Action $action */
        $action = $observer->getControllerAction();
        $request = $action->getRequest();
        $helper = Mage::helper('contentnegotiation');
        if (!$helper->isMarkdownRequest($request)
            || !$helper->isAllowedRoute($request)
            || !Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP)
        ) {
            return;
        }

        $markdown = Mage::app()->loadCache($helper->getCacheId($request));
        if (!is_string($markdown) || $markdown === '') {
            return;
        }

        $this->sendMarkdown($action->getResponse(), $markdown);
        $action->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        $helper->markServed();
    }

    #[Maho\Config\Observer('controller_front_send_response_before')]
    public function negotiateResponse(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('contentnegotiation');
        $request = Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        if ($helper->wasServed()
            || !$helper->isEnabled()
            || !$request->isGet()
            || $response->getHttpResponseCode() !== 200
            || !$this->isHtml($response)
            || !$helper->isAllowedRoute($request)
        ) {
            return;
        }

        if (!$helper->isMarkdownRequest($request)) {
            $response->setHeader('Vary', 'Accept');
            $url = $helper->getMarkdownUrl($request);
            if ($url !== null) {
                $response->setHeader('Link', sprintf('<%s>; rel="alternate"; type="%s"', $url, Maho_ContentNegotiation_Helper_Data::MIME_TYPE), false);
            }
            return;
        }

        $renderer = Mage::getSingleton('contentnegotiation/resolver')->resolve($helper->getRoute($request));
        $markdown = $renderer?->render();
        if ($renderer === null || $markdown === null) {
            return;
        }

        if (Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP)) {
            Mage::app()->saveCache(
                $markdown,
                $helper->getCacheId($request),
                [Mage_Core_Block_Abstract::CACHE_GROUP, ...$renderer->getCacheTags()],
                $helper->getCacheLifetime(),
            );
        }

        $this->sendMarkdown($response, $markdown);
    }

    /**
     * A response without a Content-Type is HTML: only Mage_Core_Model_App::getResponse() sets the default.
     */
    private function isHtml(Mage_Core_Controller_Response_Http $response): bool
    {
        $type = $response->getSymfonyResponse()->headers->get('Content-Type');

        return $type === null || str_starts_with($type, 'text/html');
    }

    private function sendMarkdown(Mage_Core_Controller_Response_Http $response, string $markdown): void
    {
        $response->clearBody()
            ->setBody($markdown)
            ->setHeader('Content-Type', Maho_ContentNegotiation_Helper_Data::MIME_TYPE . '; charset=UTF-8')
            ->setHeader('Vary', 'Accept')
            ->setHeader('X-Robots-Tag', 'noindex');
    }
}
