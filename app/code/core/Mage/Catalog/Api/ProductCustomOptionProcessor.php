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
use Maho\ApiPlatform\Http\RequestInput;
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

        $this->applyBody($option, RequestInput::of($this->parseRequestBody($context['request'] ?? null)), $isNew);
        $option = $service->save($product, $option);

        return $this->provider->provide(new GetCollection(), ['productId' => $productId, 'optionId' => (int) $option->getId()], []);
    }

    /**
     * Write the fields of $input into $option. A new option gets the default of each field that $input leaves out.
     */
    private function applyBody(Mage_Catalog_Model_Product_Option $option, RequestInput $input, bool $isNew): void
    {
        if (($title = $input->string('title')) !== null || $isNew) {
            $option->setTitle($title ?? '');
        }
        if (($type = $input->string('type')) !== null || $isNew) {
            $option->setType($type ?? '');
        }
        if (($required = $input->bool('required')) !== null || $isNew) {
            $option->setIsRequire($required ?? false);
        }
        if (($sortOrder = $input->int('sortOrder', 'sort_order')) !== null || $isNew) {
            $option->setSortOrder($sortOrder ?? 0);
        }
        if (($price = $input->float('price')) !== null) {
            $option->setData('price', $price);
        }
        if (($priceType = $input->string('priceType', 'price_type')) !== null || $isNew) {
            $option->setData('price_type', $priceType ?? 'fixed');
        }
        if (($sku = $input->string('sku')) !== null) {
            $option->setSku($sku);
        }
        if (($maxCharacters = $input->int('maxCharacters', 'max_characters')) !== null) {
            $option->setMaxCharacters($maxCharacters);
        }
        if (($fileExtensions = $input->string('fileExtensions', 'file_extensions')) !== null) {
            $option->setFileExtension($fileExtensions);
        }
        if (($imageSizeX = $input->int('imageSizeX', 'image_size_x')) !== null) {
            $option->setImageSizeX($imageSizeX);
        }
        if (($imageSizeY = $input->int('imageSizeY', 'image_size_y')) !== null) {
            $option->setImageSizeY($imageSizeY);
        }
        $rows = $input->rows('values');
        if ($rows !== null) {
            $values = [];
            foreach ($rows as $index => $value) {
                $values[] = [
                    'title' => $value->string('title') ?? '',
                    'price' => $value->float('price') ?? 0.0,
                    'price_type' => $value->string('priceType', 'price_type') ?? 'fixed',
                    'sku' => $value->string('sku') ?? '',
                    'sort_order' => $value->int('sortOrder', 'sort_order') ?? $index,
                ];
            }
            $option->setData('values', $values);
        }
    }
}
