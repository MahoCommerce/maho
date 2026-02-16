<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Type;
use Maho\Catalog\Api\Resource\ConfigurableSetup;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ConfigurableSetup>
 */
final class ConfigurableSetupProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ConfigurableSetup
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            throw new BadRequestHttpException('Product is not a configurable product');
        }

        return [$this->getSetup($product)];
    }

    public function getSetup(Mage_Catalog_Model_Product $product): ConfigurableSetup
    {
        $dto = new ConfigurableSetup();
        $dto->id = (int) $product->getId();

        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);

        // Get super attributes
        $configurableAttributes = $typeInstance->getConfigurableAttributesAsArray($product);
        $dto->superAttributes = array_map(
            fn($attr) => $attr['attribute_code'],
            $configurableAttributes,
        );

        // Get child IDs
        $dto->childProductIds = array_map(
            'intval',
            $typeInstance->getUsedProductIds($product),
        );

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
