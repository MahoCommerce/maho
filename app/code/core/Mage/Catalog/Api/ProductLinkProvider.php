<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Mage_Catalog_Model_Product;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ProductLinkProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    private const LINK_COLLECTION_MAP = [
        'related' => 'getRelatedProductCollection',
        'cross_sell' => 'getCrossSellProductCollection',
        'up_sell' => 'getUpSellProductCollection',
    ];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $linkType = self::extractLinkType($context);

        if (!isset(self::LINK_COLLECTION_MAP[$linkType])) {
            throw new BadRequestHttpException("Invalid link type: {$linkType}. Valid types: related, cross_sell, up_sell");
        }

        $product = $this->loadProduct($productId);
        return $this->getLinks($product, $linkType);
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
        if (preg_match('#/products/\d+/links/([a-z_]+)#', $path, $matches)) {
            return $matches[1];
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
        if (preg_match('#/products/\d+/links/[a-z_]+/(\d+)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return ProductLink[]
     */
    public function getLinks(Mage_Catalog_Model_Product $product, string $linkType): array
    {
        $method = self::LINK_COLLECTION_MAP[$linkType];
        $collection = $product->$method();
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
}
