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

final class ProductMediaProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);
        return $this->getMediaGallery($product);
    }

    /**
     * @return ProductMedia[]
     */
    public function getMediaGallery(Mage_Catalog_Model_Product $product): array
    {
        $gallery = $product->getMediaGalleryImages();
        if (!$gallery) {
            return [];
        }

        // Get image role assignments
        $mainImage = $product->getData('image');
        $smallImage = $product->getData('small_image');
        $thumbnail = $product->getData('thumbnail');

        $result = [];
        foreach ($gallery as $image) {
            $dto = new ProductMedia();
            $dto->id = (int) $image->getValueId();
            $dto->file = (string) $image->getFile();
            $dto->url = (string) $image->getUrl();
            $dto->label = $image->getLabel() ?: null;
            $dto->position = (int) $image->getPosition();
            $dto->disabled = (bool) $image->getDisabled();

            $types = [];
            if ($mainImage === $dto->file) {
                $types[] = 'image';
            }
            if ($smallImage === $dto->file) {
                $types[] = 'small_image';
            }
            if ($thumbnail === $dto->file) {
                $types[] = 'thumbnail';
            }
            $dto->types = $types;

            $result[] = $dto;
        }

        return $result;
    }
}
