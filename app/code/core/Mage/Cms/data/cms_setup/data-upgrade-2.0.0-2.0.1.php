<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

// The cms_page entity type holds the definitions of the custom attributes of CMS pages. Each one is
// static, so its values are columns of cms_page and the type has no value tables.
$installer = new Mage_Eav_Model_Entity_Setup('cms_setup');
$installer->startSetup();

if (!$installer->getEntityType(Mage_Cms_Model_Page::ENTITY, 'entity_type_id')) {
    $installer->addEntityType(Mage_Cms_Model_Page::ENTITY, [
        'entity_model' => 'cms/page_entity',
        'attribute_model' => 'cms/attribute',
        'table' => 'cms/page',
        'entity_attribute_collection' => 'eav/entity_attribute_collection',
    ]);
    $installer->setDefaultSetToEntityType(Mage_Cms_Model_Page::ENTITY);
}

$installer->endSetup();
