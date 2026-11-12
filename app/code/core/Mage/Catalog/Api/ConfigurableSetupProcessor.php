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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
        $websiteIds = $this->allowedWebsiteIds($user);

        if ($operation instanceof DeleteOperationInterface) {
            $path = ($context['request'] ?? null)?->getPathInfo() ?? '';
            $service->removeChild($product, preg_match('#/configurable/children/(\d+)#', $path, $matches) ? (int) $matches[1] : 0);
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if ($operation instanceof Post) {
            $service->addChild($product, self::childId($body['childProductId'] ?? $body['child_product_id'] ?? $body['childId'] ?? 0), $websiteIds);
        } else {
            $codes = $body['superAttributes'] ?? $body['super_attributes'] ?? [];
            $childIds = $body['childProductIds'] ?? $body['child_product_ids'] ?? [];
            $service->setUp(
                $product,
                empty($codes) ? [] : self::attributeCodes($codes),
                empty($childIds) ? [] : self::childIds($childIds),
                $websiteIds,
            );
        }

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }

    /**
     * @return list<string>
     * @throws BadRequestHttpException when $codes is not a list of attribute codes
     */
    private static function attributeCodes(mixed $codes): array
    {
        if (!is_array($codes) || !array_is_list($codes)) {
            throw new BadRequestHttpException('superAttributes must be a list of attribute codes');
        }
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new BadRequestHttpException('superAttributes must be a list of attribute codes');
            }
        }

        return $codes;
    }

    /**
     * @return list<int>
     * @throws BadRequestHttpException when $ids is not a list of product ids
     */
    private static function childIds(mixed $ids): array
    {
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new BadRequestHttpException('childProductIds must be a list of product ids');
        }

        return array_map(self::childId(...), $ids);
    }

    /**
     * @throws BadRequestHttpException when $id is not an integer
     * @throws UnprocessableEntityHttpException when $id is below 1
     */
    private static function childId(mixed $id): int
    {
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new BadRequestHttpException('A child product id must be an integer');
        }
        $childId = (int) $id;
        if ($childId < 1) {
            throw new UnprocessableEntityHttpException('A child product id must be positive');
        }

        return $childId;
    }
}
