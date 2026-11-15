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
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

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

        if ($operation instanceof Post) {
            $input = RequestInput::of($this->parseRequestBody($context['request'] ?? null));
            $service->addLink($product, LinkService::TYPE_GROUPED, self::childId($input), self::linkData($input), $websiteIds);
        } else {
            $links = [];
            foreach (RequestInput::decodeRows($context['request'] ?? null, 'link') as $link) {
                $links[self::childId($link)] = self::linkData($link);
            }
            $service->setLinks($product, LinkService::TYPE_GROUPED, $links, $websiteIds);
        }

        return $this->provider->getGroupedLinks($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED));
    }

    private static function childId(RequestInput $link): int
    {
        return $link->int('childProductId', 'child_product_id') ?? 0;
    }

    /**
     * @return array{qty: float, position: int}
     */
    private static function linkData(RequestInput $link): array
    {
        return ['qty' => $link->float('qty') ?? 1.0, 'position' => $link->int('position') ?? 0];
    }
}
