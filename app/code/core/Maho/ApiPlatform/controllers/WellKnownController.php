<?php

/**
 * Serves the /.well-known discovery documents for the APIs this install exposes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_WellKnownController extends Mage_Core_Controller_Front_Action
{
    public const CACHE_LIFETIME = 3600;

    /**
     * Agents poll these files, and none of them depends on who is asking.
     */
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        return parent::preDispatch();
    }

    /**
     * RFC 9727: the single place that names every API this install serves.
     */
    #[Maho\Config\Route('/.well-known/api-catalog', name: 'apiplatform.wellknown.catalog', methods: ['GET'])]
    public function apiCatalogAction(): void
    {
        if (!$this->helper()->hasPublicApi()) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->_renderJson($this->discovery()->getApiCatalog(), Maho_ApiPlatform_Model_Discovery::TYPE_LINKSET);
    }

    /**
     * The MCP server card, read before a client connects to /api/mcp. Served at both paths the
     * draft has used, since neither is settled.
     */
    #[Maho\Config\Route('/.well-known/mcp.json', name: 'apiplatform.wellknown.mcp', methods: ['GET'])]
    #[Maho\Config\Route('/.well-known/mcp/server-card.json', name: 'apiplatform.wellknown.mcp.card', methods: ['GET'])]
    public function mcpAction(): void
    {
        if (!$this->helper()->isMcpEnabled()) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->_renderJson($this->discovery()->getServerCard());
    }

    /**
     * RFC 9728: what the resource_metadata parameter of a 401 challenge points at.
     */
    #[Maho\Config\Route('/.well-known/oauth-protected-resource', name: 'apiplatform.wellknown.oauth', methods: ['GET'])]
    public function oauthProtectedResourceAction(): void
    {
        if (!$this->helper()->hasPublicApi()) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->_renderJson($this->discovery()->getProtectedResourceMetadata());
    }

    /**
     * @param array<string, mixed> $document
     */
    protected function _renderJson(array $document, string $contentType = Maho_ApiPlatform_Model_Discovery::TYPE_JSON): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', $contentType . '; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_LIFETIME, true)
            ->setBody(Mage::helper('core')->jsonEncode($document));
    }

    protected function discovery(): Maho_ApiPlatform_Model_Discovery
    {
        /** @var Maho_ApiPlatform_Model_Discovery $discovery */
        $discovery = Mage::getSingleton('apiplatform/discovery');
        return $discovery;
    }

    protected function helper(): Maho_ApiPlatform_Helper_Data
    {
        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        return $helper;
    }
}
