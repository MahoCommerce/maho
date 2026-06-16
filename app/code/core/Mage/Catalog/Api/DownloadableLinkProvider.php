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
use Mage_Downloadable_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;

final class DownloadableLinkProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);
        return $this->getLinks($product);
    }

    /**
     * @return DownloadableLink[]
     */
    public function getLinks(Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Downloadable_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $links = $typeInstance->getLinks($product);

        $result = [];
        foreach ($links as $link) {
            $dto = new DownloadableLink();
            $dto->id = (int) $link->getId();
            $dto->title = (string) ($link->getTitle() ?: $link->getStoreTitle());
            $dto->price = (float) $link->getPrice();
            $dto->sortOrder = (int) $link->getSortOrder();
            $dto->numberOfDownloads = (int) $link->getNumberOfDownloads();
            $dto->linkType = (string) $link->getLinkType();
            $dto->linkUrl = $link->getLinkUrl();
            $dto->sampleUrl = $link->getSampleUrl();
            $dto->sampleType = $link->getSampleType();
            $result[] = $dto;
        }

        return $result;
    }
}
