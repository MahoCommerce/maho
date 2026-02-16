<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Maho\Catalog\Api\Resource\ProductTierPrice;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ProductTierPrice>
 */
final class ProductTierPriceProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        $tierPrices = $product->getTierPrice();
        if (!is_array($tierPrices)) {
            return [];
        }

        $result = [];
        foreach ($tierPrices as $i => $tp) {
            $dto = new ProductTierPrice();
            $dto->id = $productId . '_' . $i;
            $dto->customerGroupId = (int) ($tp['cust_group'] ?? 32000) === 32000
                ? 'all'
                : (int) $tp['cust_group'];
            $dto->websiteId = (int) ($tp['website_id'] ?? 0);
            $dto->qty = (float) ($tp['price_qty'] ?? 1);
            $dto->price = (float) ($tp['price'] ?? 0);
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
