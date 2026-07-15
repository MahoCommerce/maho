<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * Legacy SOAP/XML-RPC/JSON-RPC dispatcher.
 *
 * Modern /api/* requests are rewritten to rest.php (Symfony API Platform). The
 * .htaccess RewriteCond at public/.htaccess:76 carves out the four legacy paths
 * below so they fall through to index.php, where these #[Route] attributes match
 * and forward to the original Mage_Api_*Controller classes.
 *
 * Each protocol is gated behind the apiplatform/protocols/* config flag and
 * defaults to disabled, operators must opt in explicitly.
 */
class Maho_ApiPlatform_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/api/soap', methods: ['GET', 'POST'])]
    public function soapAction(): void
    {
        $this->forwardToLegacy(Maho_ApiPlatform_Helper_Data::PROTOCOL_SOAP, Mage_Api_SoapController::class);
    }

    #[Maho\Config\Route('/api/v2_soap', methods: ['GET', 'POST'])]
    public function v2SoapAction(): void
    {
        $this->forwardToLegacy(Maho_ApiPlatform_Helper_Data::PROTOCOL_V2_SOAP, Mage_Api_V2_SoapController::class);
    }

    #[Maho\Config\Route('/api/xmlrpc', methods: ['GET', 'POST'])]
    public function xmlrpcAction(): void
    {
        $this->forwardToLegacy(Maho_ApiPlatform_Helper_Data::PROTOCOL_XMLRPC, Mage_Api_XmlrpcController::class);
    }

    #[Maho\Config\Route('/api/jsonrpc', methods: ['GET', 'POST'])]
    public function jsonrpcAction(): void
    {
        $this->forwardToLegacy(Maho_ApiPlatform_Helper_Data::PROTOCOL_JSONRPC, Mage_Api_JsonrpcController::class);
    }

    private function forwardToLegacy(string $protocol, string $controllerClass): void
    {
        if (!Mage::helper('apiplatform')->isProtocolEnabled($protocol)) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->getResponse()->setBody('');
            $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return;
        }

        $controller = new $controllerClass($this->getRequest(), $this->getResponse());
        $controller->dispatch('index');
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
    }
}
