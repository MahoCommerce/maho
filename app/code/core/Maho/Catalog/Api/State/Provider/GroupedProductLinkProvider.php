<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Type;
use Maho\Catalog\Api\Resource\GroupedProductLink;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<GroupedProductLink>
 */
final class GroupedProductLinkProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            throw new BadRequestHttpException('Product is not a grouped product');
        }

        return $this->getGroupedLinks($product);
    }

    /**
     * @return GroupedProductLink[]
     */
    public function getGroupedLinks(Mage_Catalog_Model_Product $product): array
    {
        $associated = $product->getTypeInstance(true)->getAssociatedProducts($product);
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

    private function loadProduct(int $id): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        if ($storeId) {
            $product->setStoreId($storeId);
        }
        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        return $product;
    }
}
