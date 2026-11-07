<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Type_Configurable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The super attributes of a configurable product, checked as the admin checks them.
 */
final class ConfigurableSuperAttributes
{
    /**
     * The attribute ids of the requested super attribute codes, by code.
     *
     * @return array<string, int>
     * @throws BadRequestHttpException when $codes is not a list of attribute codes
     * @throws UnprocessableEntityHttpException when an attribute is unknown, outside the attribute set of the product
     *     or not usable to configure products
     */
    public static function resolve(Mage_Catalog_Model_Product $product, mixed $codes): array
    {
        if (!is_array($codes) || !array_is_list($codes)) {
            throw new BadRequestHttpException('superAttributes must be a list of attribute codes');
        }

        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $setAttributes = $typeInstance->getSetAttributes($product);

        $attributeIds = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new BadRequestHttpException('superAttributes must be a list of attribute codes');
            }
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $code);
            if (!$attribute || !$attribute->getId()) {
                throw new UnprocessableEntityHttpException("Attribute not found: {$code}");
            }
            // The configurable product reads only the attributes of its attribute set that can configure products
            $setAttribute = $setAttributes[$code] ?? null;
            if (!$setAttribute instanceof \Mage_Eav_Model_Entity_Attribute || !$typeInstance->canUseAttribute($setAttribute)) {
                throw new UnprocessableEntityHttpException("Attribute {$code} cannot configure products of this attribute set");
            }
            $attributeIds[$code] = (int) $attribute->getId();
        }

        return $attributeIds;
    }

    /**
     * The attribute ids of the super attributes the product already has.
     *
     * @return list<int>
     */
    public static function saved(Mage_Catalog_Model_Product $product): array
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);

        return array_map(
            intval(...),
            $typeInstance->getConfigurableAttributeCollection($product)->getColumnValues('attribute_id'),
        );
    }

    /**
     * As in the admin, the super attributes of a configurable product cannot change once saved.
     *
     * @param array<string, int> $attributeIds
     * @param list<int> $savedIds
     * @throws UnprocessableEntityHttpException when $attributeIds are not the saved ones
     */
    public static function assertUnchanged(array $attributeIds, array $savedIds): void
    {
        $requested = array_values(array_unique($attributeIds));
        sort($requested);
        sort($savedIds);
        if ($requested !== $savedIds) {
            throw new UnprocessableEntityHttpException(
                'The configurable product already has other super attributes, which cannot be changed',
            );
        }
    }
}
