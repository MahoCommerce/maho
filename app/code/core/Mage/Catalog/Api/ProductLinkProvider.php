<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Mage_Catalog_Model_Product;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class ProductLinkProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $linkType = self::extractLinkType($context);

        self::linkService()->assertType($linkType);

        $product = $this->loadProductForRead($productId);
        // A guest and a customer see the links that the storefront lists. A backend reader manages all links.
        return $this->getLinks($product, $linkType, visibleOnly: !$this->canReadBackendProducts());
    }

    /**
     * Extract linkType from the request path.
     * API Platform only populates uriVariables declared as Link objects,
     * so plain string path parameters must be extracted from the request URI.
     */
    public static function extractLinkType(array $context): string
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            return '';
        }

        $path = $request->getPathInfo();
        if (preg_match('#/products/\d+/links/([a-z-]+)#', $path, $matches)) {
            // URL is kebab-case (cross-sell); internal link-type codes use snake_case (cross_sell).
            return str_replace('-', '_', $matches[1]);
        }

        return '';
    }

    /**
     * Extract linkedProductId from the request path for DELETE operations.
     */
    public static function extractLinkedProductId(array $context): int
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            return 0;
        }

        $path = $request->getPathInfo();
        if (preg_match('#/products/\d+/links/[a-z-]+/(\d+)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return ProductLink[]
     */
    public function getLinks(Mage_Catalog_Model_Product $product, string $linkType, bool $visibleOnly = false): array
    {
        $collection = $visibleOnly
            ? self::linkService()->getVisibleLinkCollection($product, $linkType, StoreContext::getStoreId())
            : self::linkService()->getLinkCollection($product, $linkType);
        $collection->addAttributeToSelect(['name', 'sku']);

        $result = [];
        foreach ($collection as $linked) {
            $dto = new ProductLink();
            $dto->id = $product->getId() . '_' . $linkType . '_' . $linked->getId();
            $dto->linkedProductId = (int) $linked->getId();
            $dto->linkedProductSku = (string) $linked->getSku();
            $dto->linkedProductName = (string) $linked->getName();
            $dto->position = (int) $linked->getPosition();
            $result[] = $dto;
        }

        return $result;
    }

    private static function linkService(): \Mage_Catalog_Service_Product_Link
    {
        /** @var \Mage_Catalog_Service_Product_Link */
        return \Mage::getService('catalog/product_link');
    }
}
