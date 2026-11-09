<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Option;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Component\HttpFoundation\Response;

final class ProductCustomOptionProvider extends \Maho\ApiPlatform\Provider
{
    use ProductLoaderTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ProductCustomOption|Response
    {
        $operationName = $operation->getName();

        if ($operationName === 'download_option_file') {
            return $this->downloadOptionFile($context);
        }

        $productId = (int) ($uriVariables['productId'] ?? 0);
        $product = $this->loadProductForRead($productId);

        // Single option for PUT/DELETE
        if (isset($uriVariables['optionId'])) {
            return $this->getOption($product, (int) $uriVariables['optionId']);
        }

        return $this->getAllOptions($product);
    }

    private function downloadOptionFile(array $context): Response
    {
        // {optionId} and {key} are not declared as uriVariables on this route, so
        // API Platform would coerce them toward the resource's int identifier and
        // mangle the hex secret key. Read both straight from the request path to
        // keep the key intact.
        $request = $context['request'] ?? null;
        $path = $request instanceof \Symfony\Component\HttpFoundation\Request ? $request->getPathInfo() : '';
        $optionId = 0;
        $key = '';
        if (preg_match('#/custom-option-file/(\d+)/([^/?]+)#', $path, $m)) {
            $optionId = (int) $m[1];
            $key = $m[2];
        }

        /** @var \Mage_Catalog_Service_Product_Option $service */
        $service = Mage::getService('catalog/product_option');
        $file = $service->openUploadedFile($optionId, $key);

        $content = (string) stream_get_contents($file['stream']);
        fclose($file['stream']);

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Type' => $file['type'],
                'Content-Disposition' => 'attachment; filename="' . addslashes($file['title']) . '"',
                'Content-Length' => (string) strlen($content),
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    /**
     * @return ProductCustomOption[]
     */
    public function getAllOptions(Mage_Catalog_Model_Product $product): array
    {
        $options = $product->getProductOptionsCollection();
        $result = [];

        // getProductOptionsCollection() already eager-loads every option's values
        // (addValuesToResult), so read them off the loaded models instead of
        // issuing a getValuesCollection() query per select-type option (N+1).
        foreach ($options as $option) {
            $result[] = $this->mapOption($option, true);
        }

        return $result;
    }

    private function getOption(Mage_Catalog_Model_Product $product, int $optionId): ProductCustomOption
    {
        /** @var \Mage_Catalog_Service_Product_Option $service */
        $service = Mage::getService('catalog/product_option');
        return $this->mapOption($service->getById($product, $optionId));
    }

    private function mapOption(Mage_Catalog_Model_Product_Option $option, bool $valuesPreloaded = false): ProductCustomOption
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
            $optionValues = $valuesPreloaded ? $option->getValues() : $option->getValuesCollection();
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
            $dto->imageSizeX = $option->getImageSizeX() ? (int) $option->getImageSizeX() : null;
            $dto->imageSizeY = $option->getImageSizeY() ? (int) $option->getImageSizeY() : null;
        }

        return $dto;
    }
}
