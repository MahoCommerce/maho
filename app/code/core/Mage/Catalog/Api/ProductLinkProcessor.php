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
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class ProductLinkProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductLinkProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductLink|array|null
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        /** @var \Mage_Catalog_Service_Product_Link $service */
        $service = \Mage::getService('catalog/product_link');
        $linkType = ProductLinkProvider::extractLinkType($context);
        $service->assertType($linkType);
        $product = $this->loadProductForWrite($productId, $user);
        $websiteIds = $this->allowedWebsiteIds($user);

        if ($operation instanceof DeleteOperationInterface) {
            $service->removeLink($product, $linkType, ProductLinkProvider::extractLinkedProductId($context));
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if ($operation instanceof Post) {
            $input = RequestInput::of($body);
            $linkedId = $input->int('linkedProductId', 'linked_product_id') ?? 0;
            $position = $input->int('position') ?? 0;
            $service->addLink($product, $linkType, $linkedId, ['position' => $position], $websiteIds);

            $linked = \Mage::getModel('catalog/product')->load($linkedId);
            $dto = new ProductLink();
            $dto->id = $productId . '_' . $linkType . '_' . $linkedId;
            $dto->linkedProductId = $linkedId;
            $dto->linkedProductSku = (string) $linked->getSku();
            $dto->linkedProductName = (string) $linked->getName();
            $dto->position = $position;
            return $dto;
        }

        $links = [];
        foreach (RequestInput::rowsOf($body) as $link) {
            $links[$link->int('linkedProductId', 'linked_product_id') ?? 0] = ['position' => $link->int('position') ?? 0];
        }
        $service->setLinks($product, $linkType, $links, $websiteIds);

        return $this->provider->getLinks($this->loadProduct($productId), $linkType);
    }
}
