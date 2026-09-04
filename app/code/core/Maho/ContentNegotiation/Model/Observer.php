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
        if (!$helper->isEnabled() || !$helper->isReadRequest($request)) {
            return;
        }

        $uri = $helper->fromMarkdownUrl((string) $request->getRequestUri(), $request->getBaseUrl());
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
        if ($helper->markdownRoute($request) === null || !$helper->usesCache()) {
            return;
        }

        $markdown = Mage::app()->loadCache($helper->getCacheId($request));
        if (!is_string($markdown) || $markdown === '') {
            return;
        }

        $this->sendMarkdown($action->getResponse(), $markdown);
        $action->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
    }

    /**
     * The action has loaded its entity by the time the blocks are generated, so the markdown is
     * built here and the HTML output of the layout, which the response would discard, is skipped.
     * A route has no markdown version unless its action generates layout blocks.
     */
    #[Maho\Config\Observer('controller_action_layout_generate_blocks_after')]
    public function renderMarkdownInsteadOfLayout(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Varien_Action $action */
        $action = $observer->getAction();
        $request = $action->getRequest();
        $route = Mage::helper('contentnegotiation')->markdownRoute($request);
        if ($route !== null && $this->renderMarkdown($route, $request, $action->getResponse())) {
            $action->setFlag('', 'no-renderLayout', true);
        }
    }

    #[Maho\Config\Observer('controller_front_send_response_before')]
    public function negotiateResponse(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('contentnegotiation');
        $request = Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        if (!$helper->isEnabled() || !$helper->isReadRequest($request)) {
            return;
        }

        if ($response->isRedirect()) {
            $this->keepSuffixOnRedirect($response);
            return;
        }

        if ($response->getHttpResponseCode() !== 200) {
            return;
        }

        if ($this->isHtml($response) && !$helper->isMarkdownRequest($request)) {
            $this->announceMarkdown($request, $response);
        }

        // The suffix names a markdown resource: when none exists, the HTML page is not an answer.
        if ($helper->wasSuffixStripped() && !$this->isMarkdown($response)) {
            $this->sendNotFound($response);
        }
    }

    private function announceMarkdown(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        $route = $helper->getRoute($request);
        if (!$helper->isRouteAllowed($route) || !Mage::getSingleton('contentnegotiation/resolver')->hasRenderer($route)) {
            return;
        }

        $this->addVary($response);
        $response->setHeader('Link', sprintf(
            '<%s>; rel="alternate"; type="%s"',
            $helper->getMarkdownUrl($request),
            Maho_ContentNegotiation_Helper_Data::MIME_TYPE,
        ), false);
    }

    /**
     * False when the route has no renderer or the page has no entity to render, so the HTML
     * response stays as it is.
     */
    private function renderMarkdown(string $route, Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response): bool
    {
        $renderer = Mage::getSingleton('contentnegotiation/resolver')->resolve($route);
        $markdown = $renderer?->render();
        if ($renderer === null || $markdown === null) {
            return false;
        }

        $helper = Mage::helper('contentnegotiation');
        if ($helper->usesCache()) {
            Mage::app()->saveCache(
                $markdown,
                $helper->getCacheId($request),
                [Mage_Core_Block_Abstract::CACHE_GROUP, ...$renderer->getCacheTags()],
                $helper->getCacheLifetime(),
            );
        }

        $this->sendMarkdown($response, $markdown);

        return true;
    }

    /**
     * A canonical or URL rewrite redirect was computed from the stripped URI, so the agent that
     * asked for ".md" would land on the HTML page. The core redirects send a Location without a
     * host; an absolute one gets the suffix back only when it points at this store.
     */
    private function keepSuffixOnRedirect(Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        $location = (string) $response->getSymfonyResponse()->headers->get('Location');
        $relative = str_starts_with($location, '/') && !str_starts_with($location, '//');
        if (!$helper->wasSuffixStripped()
            || $location === ''
            || (!$relative && !Mage::helper('core/url')->isInternalUrl($location))
            || $helper->hasMarkdownSuffix($location)
        ) {
            return;
        }

        $response->setHeader('Location', $helper->toMarkdownUrl($location, keepQuery: true), true);
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

    /**
     * Added to a Vary another module already set, never in place of it.
     */
    private function addVary(Mage_Core_Controller_Response_Http $response): void
    {
        $symfony = $response->getSymfonyResponse();
        if (!in_array('Accept', $symfony->getVary(), true)) {
            $symfony->setVary('Accept', false);
        }
    }

    private function sendMarkdown(Mage_Core_Controller_Response_Http $response, string $markdown): void
    {
        $response->clearBody()
            ->setBody($markdown)
            ->setHeader('Content-Type', Maho_ContentNegotiation_Helper_Data::MIME_TYPE . '; charset=UTF-8', true)
            ->setHeader('X-Robots-Tag', 'noindex', true);
        $this->addVary($response);
    }

    private function sendNotFound(Mage_Core_Controller_Response_Http $response): void
    {
        $helper = Mage::helper('contentnegotiation');
        $response->setHttpResponseCode(404);
        $this->sendMarkdown($response, '# ' . $helper->__('Not Found') . "\n\n" . $helper->__('This page has no markdown version.') . "\n");
    }
}
