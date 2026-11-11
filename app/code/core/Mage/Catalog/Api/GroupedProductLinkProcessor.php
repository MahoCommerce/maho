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
use Mage_Catalog_Model_Product_Type;
use Mage_Catalog_Service_Product_Link as LinkService;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class GroupedProductLinkProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly GroupedProductLinkProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);
        $product = $this->loadProductForWrite($productId, $user, Mage_Catalog_Model_Product_Type::TYPE_GROUPED);

        /** @var LinkService $service */
        $service = \Mage::getService('catalog/product_link');
        $websiteIds = $this->allowedWebsiteIds($user);

        if ($operation instanceof DeleteOperationInterface) {
            $path = ($context['request'] ?? null)?->getPathInfo() ?? '';
            $childId = preg_match('#/products/\d+/grouped/(\d+)#', $path, $matches) ? (int) $matches[1] : 0;
            $service->removeLink($product, LinkService::TYPE_GROUPED, $childId);
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if ($operation instanceof Post) {
            $service->addLink($product, LinkService::TYPE_GROUPED, self::childId($body), self::linkData($body), $websiteIds);
        } else {
            $links = [];
            foreach ($body as $link) {
                if (!is_array($link)) {
                    throw new BadRequestHttpException('Each link must be an object');
                }
                $links[self::childId($link)] = self::linkData($link);
            }
            $service->setLinks($product, LinkService::TYPE_GROUPED, $links, $websiteIds);
        }

        return $this->provider->getGroupedLinks($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED));
    }

    /**
     * @param array<string, mixed> $link
     */
    private static function childId(array $link): int
    {
        return (int) ($link['childProductId'] ?? $link['child_product_id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $link
     * @return array{qty: float, position: int}
     */
    private static function linkData(array $link): array
    {
        return ['qty' => (float) ($link['qty'] ?? 1), 'position' => (int) ($link['position'] ?? 0)];
    }
}
