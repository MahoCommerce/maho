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

        $uri = $helper->fromMarkdownUrl((string) $request->getRequestUri());
        if ($uri === null) {
            return;
        }

        $request->setRequestUri($uri);
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
    }

    #[Maho\Config\Observer('controller_front_send_response_before')]
    public function negotiateResponse(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('contentnegotiation');
        $request = Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        if (!$helper->isEnabled() || !$request->isGet()) {
            return;
        }

        if ($response->isRedirect()) {
            $this->keepSuffixOnRedirect($response);
            return;
        }

        if ($response->getHttpResponseCode() === 200 && $this->isHtml($response)) {
            $this->negotiate($request, $response);
        }

        // The suffix names a markdown resource: when none exists, the HTML page is not an answer.
        if ($helper->wasSuffixStripped()
            && $response->getHttpResponseCode() === 200
            && !$this->isMarkdown($response)
        ) {
            $this->sendNotFound($response);
        }
    }

    /**
     * Announces the markdown version on an HTML response, or replaces the body with it.
     */
    private function negotiate(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        if (!$helper->isAllowedRoute($request)) {
            return;
        }

        $renderer = Mage::getSingleton('contentnegotiation/resolver')->resolve($helper->getRoute($request));
        if ($renderer === null) {
            return;
        }

        if (!$helper->isMarkdownRequest($request)) {
            $response->setHeader('Vary', 'Accept');
            $response->setHeader('Link', sprintf(
                '<%s>; rel="alternate"; type="%s"',
                $helper->getMarkdownUrl($request),
                Maho_ContentNegotiation_Helper_Data::MIME_TYPE,
            ), false);
            return;
        }

        $markdown = $renderer->render();
        if ($markdown === null) {
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
     * A canonical or URL rewrite redirect was computed from the stripped URI, so the agent that
     * asked for ".md" would land on the HTML page. Only a target on this store gets the suffix back.
     */
    private function keepSuffixOnRedirect(Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        $location = (string) $response->getSymfonyResponse()->headers->get('Location');
        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        if (!$helper->wasSuffixStripped()
            || $location === ''
            || !str_starts_with($location, $baseUrl)
            || str_ends_with(explode('?', $location, 2)[0], Maho_ContentNegotiation_Helper_Data::SUFFIX)
        ) {
            return;
        }

        $response->setHeader('Location', $helper->toMarkdownUrl($location), true);
    }

    /**
     * A response without a Content-Type is HTML: only Mage_Core_Model_App::getResponse() sets the default.
     */
    private function isHtml(Mage_Core_Controller_Response_Http $response): bool
    {
        $type = $response->getSymfonyResponse()->headers->get('Content-Type');

        return $type === null || str_starts_with($type, 'text/html');
    }

    private function isMarkdown(Mage_Core_Controller_Response_Http $response): bool
    {
        $type = (string) $response->getSymfonyResponse()->headers->get('Content-Type');

        return str_starts_with($type, Maho_ContentNegotiation_Helper_Data::MIME_TYPE);
    }

    private function sendMarkdown(Mage_Core_Controller_Response_Http $response, string $markdown): void
    {
        $response->clearBody()
            ->setBody($markdown)
            ->setHeader('Content-Type', Maho_ContentNegotiation_Helper_Data::MIME_TYPE . '; charset=UTF-8')
            ->setHeader('Vary', 'Accept')
            ->setHeader('X-Robots-Tag', 'noindex');
    }

    private function sendNotFound(Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        $response->clearBody()
            ->setHttpResponseCode(404)
            ->setBody('# ' . $helper->__('Not Found') . "\n\n" . $helper->__('This page has no markdown version.') . "\n")
            ->setHeader('Content-Type', Maho_ContentNegotiation_Helper_Data::MIME_TYPE . '; charset=UTF-8', true)
            ->setHeader('X-Robots-Tag', 'noindex', true);
    }
}
