<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mage_Catalog_Model_Product_Option;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class ProductCustomOptionProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductCustomOptionProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductCustomOption
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProduct($productId);
        $this->assertProductWebsitesAllowed($product, $user);

        /** @var \Mage_Catalog_Service_Product_Option $service */
        $service = \Mage::getService('catalog/product_option');
        $isNew = $operation instanceof Post;
        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = $isNew ? \Mage::getModel('catalog/product_option') : $service->getById($product, (int) ($uriVariables['id'] ?? 0));

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($product, $option);
            return null;
        }

        $this->applyBody($option, $this->parseRequestBody($context['request'] ?? null), $isNew);
        $option = $service->save($product, $option);

        return $this->provider->provide(new GetCollection(), ['productId' => $productId, 'optionId' => (int) $option->getId()], []);
    }

    /**
     * Write the fields of $body into $option. A new option gets the default of each field that $body leaves out.
     *
     * @param array<string, mixed> $body
     */
    private function applyBody(Mage_Catalog_Model_Product_Option $option, array $body, bool $isNew): void
    {
        $field = static fn(string $camel, string $snake): mixed => $body[$camel] ?? $body[$snake] ?? null;

        if (isset($body['title']) || $isNew) {
            $option->setTitle((string) ($body['title'] ?? ''));
        }
        if (isset($body['type']) || $isNew) {
            $option->setType((string) ($body['type'] ?? ''));
        }
        if (isset($body['required']) || $isNew) {
            $option->setIsRequire(!empty($body['required']));
        }
        if (($sortOrder = $field('sortOrder', 'sort_order')) !== null || $isNew) {
            $option->setSortOrder((int) $sortOrder);
        }
        if (isset($body['price'])) {
            $option->setData('price', (float) $body['price']);
        }
        if (($priceType = $field('priceType', 'price_type')) !== null || $isNew) {
            $option->setData('price_type', (string) ($priceType ?? 'fixed'));
        }
        if (isset($body['sku'])) {
            $option->setSku((string) $body['sku']);
        }
        if (($maxCharacters = $field('maxCharacters', 'max_characters')) !== null) {
            $option->setMaxCharacters((int) $maxCharacters);
        }
        if (($fileExtensions = $field('fileExtensions', 'file_extensions')) !== null) {
            $option->setFileExtension((string) $fileExtensions);
        }
        if (($imageSizeX = $field('imageSizeX', 'image_size_x')) !== null) {
            $option->setImageSizeX((int) $imageSizeX);
        }
        if (($imageSizeY = $field('imageSizeY', 'image_size_y')) !== null) {
            $option->setImageSizeY((int) $imageSizeY);
        }
        if (isset($body['values'])) {
            $values = [];
            foreach (array_values((array) $body['values']) as $index => $value) {
                $value = is_array($value) ? $value : [];
                $values[] = [
                    'title' => (string) ($value['title'] ?? ''),
                    'price' => (float) ($value['price'] ?? 0),
                    'price_type' => (string) ($value['priceType'] ?? $value['price_type'] ?? 'fixed'),
                    'sku' => (string) ($value['sku'] ?? ''),
                    'sort_order' => (int) ($value['sortOrder'] ?? $value['sort_order'] ?? $index),
                ];
            }
            $option->setData('values', $values);
        }
    }
}
