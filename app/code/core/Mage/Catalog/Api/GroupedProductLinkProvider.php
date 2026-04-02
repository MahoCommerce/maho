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
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class GroupedProductLinkProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED);
        return $this->getGroupedLinks($product);
    }

    /**
     * @return GroupedProductLink[]
     */
    public function getGroupedLinks(Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Catalog_Model_Product_Type_Grouped $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $associated = $typeInstance->getAssociatedProducts($product);
        $result = [];

        foreach ($associated as $child) {
            $dto = new GroupedProductLink();
            $dto->id = $product->getId() . '_grouped_' . $child->getId();
            $dto->childProductId = (int) $child->getId();
            $dto->childProductSku = (string) $child->getSku();
            $dto->childProductName = (string) $child->getName();
            $dto->qty = (float) ($child->getQty() ?: 1);
            $dto->position = (int) $child->getPosition();
            $result[] = $dto;
        }

        return $result;
    }
}
