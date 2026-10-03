<?php

/**
 * Value lists for EnumSource that no Maho source model provides.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

final class ValueLists
{
    public const META_ROBOTS = ['INDEX,FOLLOW', 'NOINDEX,FOLLOW', 'INDEX,NOFOLLOW', 'NOINDEX,NOFOLLOW'];

    /** @return list<int> */
    public static function websites(): array
    {
        return array_values(array_map(static fn(\Mage_Core_Model_Website $w): int => (int) $w->getId(), \Mage::app()->getWebsites()));
    }

    /** @return list<int> */
    public static function storeViews(): array
    {
        return array_values(array_map(static fn(\Mage_Core_Model_Store $s): int => (int) $s->getId(), \Mage::app()->getStores()));
    }

    /** @return list<int> */
    public static function productAttributeSets(): array
    {
        $typeId = (int) \Mage::getSingleton('eav/config')->getEntityType(\Mage_Catalog_Model_Product::ENTITY)->getId();
        $ids = \Mage::getResourceModel('eav/entity_attribute_set_collection')->setEntityTypeFilter($typeId)->getAllIds();

        return array_values(array_map(intval(...), $ids));
    }

    /** @return array<int, mixed> option arrays with value and label; the empty option is skipped by EnumSource */
    public static function customerGenders(): array
    {
        return \Mage::getSingleton('eav/config')->getAttribute('customer', 'gender')->getSource()->getAllOptions();
    }
}
