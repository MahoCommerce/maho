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
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class ConfigurableSetupProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ConfigurableSetupProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ConfigurableSetup
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);
        $product = $this->loadProductForWrite($productId, $user, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        /** @var \Mage_Catalog_Service_Product_Configurable $service */
        $service = \Mage::getService('catalog/product_configurable');
        $websiteIds = $this->getAllowedWebsiteIds($user);

        if ($operation instanceof DeleteOperationInterface) {
            $path = ($context['request'] ?? null)?->getPathInfo() ?? '';
            $service->removeChild($product, preg_match('#/configurable/children/(\d+)#', $path, $matches) ? (int) $matches[1] : 0);
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if ($operation instanceof Post) {
            $service->addChild($product, (int) ($body['childProductId'] ?? $body['child_product_id'] ?? $body['childId'] ?? 0), $websiteIds);
        } else {
            $service->setSuperAttributes($product, array_values((array) ($body['superAttributes'] ?? $body['super_attributes'] ?? [])));
            $childIds = array_map(intval(...), array_values((array) ($body['childProductIds'] ?? $body['child_product_ids'] ?? [])));
            if ($childIds !== []) {
                $service->setChildren($product, $childIds, $websiteIds);
            }
        }

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }
}
