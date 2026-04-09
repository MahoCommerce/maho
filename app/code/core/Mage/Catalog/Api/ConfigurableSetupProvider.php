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

final class ConfigurableSetupProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
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
}
