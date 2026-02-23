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

namespace Maho\Catalog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Downloadable_Model_Product_Type;
use Maho\Catalog\Api\Resource\DownloadableLink;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<DownloadableLink>
 */
final class DownloadableLinkProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadDownloadableProduct($productId);
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

    private function loadDownloadableProduct(int $id): Mage_Catalog_Model_Product
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

        if ($product->getTypeId() !== Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE) {
            throw new BadRequestHttpException('Product is not a downloadable product');
        }

        return $product;
    }
}
