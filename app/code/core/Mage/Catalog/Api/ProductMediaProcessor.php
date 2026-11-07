<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ProductMediaProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductMediaProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductMedia|array|null
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);
        $product = $this->loadProductForWrite($productId, $user);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);
        $valueId = (int) ($body['valueId'] ?? $body['value_id'] ?? $body['id'] ?? 0);
        $label = isset($body['label']) ? (string) $body['label'] : null;
        $position = isset($body['position']) ? (int) $body['position'] : null;
        $disabled = isset($body['disabled']) ? (bool) $body['disabled'] : null;
        /** @var \Mage_Catalog_Service_Product_Media $service */
        $service = \Mage::getService('catalog/product_media');

        if ($operation instanceof DeleteOperationInterface) {
            $service->removeImage($product, $valueId ?: (int) ($request?->query->get('valueId') ?? 0));
            return null;
        }

        if (!$operation instanceof Post) {
            $types = isset($body['types']) && is_array($body['types']) ? array_values($body['types']) : null;
            $service->updateImage($product, $valueId, $label, $position, $disabled, $types);
            return $this->provider->getMediaGallery($this->loadProduct($productId));
        }

        $types = array_values((array) ($body['types'] ?? []));
        $base64 = $body['base64'] ?? $body['imageData'] ?? $body['image_data'] ?? null;
        $imageUrl = $body['imageUrl'] ?? $body['image_url'] ?? null;
        if ($base64 !== null) {
            $imageData = base64_decode((string) $base64, true);
            if ($imageData === false) {
                throw new BadRequestHttpException('Invalid base64 image data');
            }
            $service->addImageFromData($product, $imageData, (string) ($body['filename'] ?? 'upload.jpg'), $types, $label, $position, $disabled);
        } elseif ($imageUrl !== null) {
            $service->addImageFromUrl($product, (string) $imageUrl, $types, $label, $position, $disabled);
        } else {
            throw new \Mage_Core_Exception_Input()->addError('base64', \Mage::helper('catalog')->__('Either base64, imageData, or imageUrl is required'));
        }
        return $this->mapNewestGalleryImage($this->loadProduct($productId));
    }

    /**
     * Map the gallery row with the highest value_id, so an image uploaded with disabled=true is returned too.
     */
    private function mapNewestGalleryImage(\Mage_Catalog_Model_Product $product): ProductMedia
    {
        $newest = null;
        foreach ($product->getData('media_gallery')['images'] ?? [] as $image) {
            if ($newest === null || (int) ($image['value_id'] ?? 0) > (int) ($newest['value_id'] ?? 0)) {
                $newest = $image;
            }
        }

        $dto = new ProductMedia();
        $dto->id = (int) ($newest['value_id'] ?? 0);
        if ($newest === null) {
            return $dto;
        }
        $file = (string) ($newest['file'] ?? '');
        $dto->file = $file;
        $dto->url = $file !== '' ? (string) $product->getMediaConfig()->getMediaUrl($file) : null;
        $dto->label = ($newest['label'] ?? null) ?: null;
        $dto->position = (int) ($newest['position'] ?? 0);
        $dto->disabled = (bool) ($newest['disabled'] ?? false);
        $dto->types = $product->getImage() === $file ? ['image'] : [];
        return $dto;
    }
}
