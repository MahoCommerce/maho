<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class BundleOptionProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);
        return $this->getBundleOptions($product);
    }

    /**
     * @return BundleOption[]
     */
    public function getBundleOptions(Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Bundle_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $optionsCollection = $typeInstance->getOptionsCollection($product);
        $selectionsCollection = $typeInstance->getSelectionsCollection(
            $typeInstance->getOptionsIds($product),
            $product,
        );

        // Index selections by option_id
        $selectionsByOption = [];
        foreach ($selectionsCollection as $selection) {
            $optionId = (int) $selection->getOptionId();
            $selectionsByOption[$optionId][] = $selection;
        }

        $result = [];
        foreach ($optionsCollection as $option) {
            $dto = new BundleOption();
            $dto->id = (int) $option->getId();
            $dto->title = (string) ($option->getDefaultTitle() ?: $option->getTitle());
            $dto->type = (string) $option->getType();
            $dto->required = (bool) $option->getRequired();
            $dto->position = (int) $option->getPosition();

            $optionId = (int) $option->getId();
            $selections = $selectionsByOption[$optionId] ?? [];
            $dto->selections = [];

            foreach ($selections as $sel) {
                $dto->selections[] = [
                    'selectionId' => (int) $sel->getSelectionId(),
                    'productId' => (int) $sel->getProductId(),
                    'sku' => (string) $sel->getSku(),
                    'name' => (string) $sel->getName(),
                    'price' => (float) $sel->getSelectionPriceValue(),
                    'priceType' => ((int) $sel->getSelectionPriceType() === 1) ? 'percent' : 'fixed',
                    'qty' => (float) $sel->getSelectionQty(),
                    'canChangeQty' => (bool) $sel->getSelectionCanChangeQty(),
                    'isDefault' => (bool) $sel->getIsDefault(),
                    'position' => (int) $sel->getPosition(),
                ];
            }

            $result[] = $dto;
        }

        return $result;
    }
}
