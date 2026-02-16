<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Option;
use Maho\Catalog\Api\Resource\ProductCustomOption;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ProductCustomOption>
 */
final class ProductCustomOptionProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ProductCustomOption
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        // Single option for PUT/DELETE
        if (isset($uriVariables['optionId'])) {
            return $this->getOption($product, (int) $uriVariables['optionId']);
        }

        return $this->getAllOptions($product);
    }

    /**
     * @return ProductCustomOption[]
     */
    public function getAllOptions(Mage_Catalog_Model_Product $product): array
    {
        $options = $product->getProductOptionsCollection();
        $result = [];

        foreach ($options as $option) {
            $result[] = $this->mapOption($option);
        }

        return $result;
    }

    private function getOption(Mage_Catalog_Model_Product $product, int $optionId): ProductCustomOption
    {
        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = Mage::getModel('catalog/product_option')->load($optionId);

        if (!$option->getId() || (int) $option->getProductId() !== (int) $product->getId()) {
            throw new NotFoundHttpException('Option not found');
        }

        return $this->mapOption($option);
    }

    private function mapOption(Mage_Catalog_Model_Product_Option $option): ProductCustomOption
    {
        $dto = new ProductCustomOption();
        $dto->id = (int) $option->getId();
        $dto->title = (string) $option->getTitle();
        $dto->type = (string) $option->getType();
        $dto->required = (bool) $option->getIsRequire();
        $dto->sortOrder = (int) $option->getSortOrder();

        $type = $option->getType();
        $selectTypes = ['drop_down', 'radio', 'checkbox', 'multiple'];

        if (in_array($type, $selectTypes)) {
            $values = [];
            $optionValues = $option->getValuesCollection();
            foreach ($optionValues as $value) {
                $values[] = [
                    'id' => (int) $value->getId(),
                    'title' => (string) $value->getTitle(),
                    'price' => (float) $value->getPrice(),
                    'priceType' => (string) ($value->getPriceType() ?: 'fixed'),
                    'sku' => $value->getSku(),
                    'sortOrder' => (int) $value->getSortOrder(),
                ];
            }
            $dto->values = $values;
        } else {
            $dto->price = $option->getPrice() !== null ? (float) $option->getPrice() : null;
            $dto->priceType = (string) ($option->getPriceType() ?: 'fixed');
            $dto->sku = $option->getSku();
            $dto->maxCharacters = $option->getMaxCharacters() ? (int) $option->getMaxCharacters() : null;
            $dto->fileExtensions = $option->getFileExtension() ?: null;
        }

        return $dto;
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
