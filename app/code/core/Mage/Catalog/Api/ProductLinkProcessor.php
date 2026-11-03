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
        $websiteIds = $this->getAllowedWebsiteIds($user);

        if ($operation instanceof DeleteOperationInterface) {
            $service->removeLink($product, $linkType, ProductLinkProvider::extractLinkedProductId($context));
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if ($operation instanceof Post) {
            $linkedId = (int) ($body['linkedProductId'] ?? $body['linked_product_id'] ?? 0);
            $position = (int) ($body['position'] ?? 0);
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
        foreach ($body as $link) {
            if (!is_array($link)) {
                throw new BadRequestHttpException('Each link must be an object with linkedProductId');
            }
            $links[(int) ($link['linkedProductId'] ?? $link['linked_product_id'] ?? 0)] = ['position' => (int) ($link['position'] ?? 0)];
        }
        $service->setLinks($product, $linkType, $links, $websiteIds);

        return $this->provider->getLinks($this->loadProduct($productId), $linkType);
    }
}
