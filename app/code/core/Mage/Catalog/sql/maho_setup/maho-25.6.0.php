<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// Schema portion of this upgrade (catalog_category_dynamic_rule table) is now
// declared in sql/schema.php. Only the EAV attribute install remains.

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

$installer->addAttribute('catalog_category', 'is_dynamic', [
    'type'                       => 'int',
    'group'                      => 'Dynamic Category',
    'label'                      => 'Is Dynamic Category',
    'input'                      => 'select',
    'source'                     => 'eav/entity_attribute_source_boolean',
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                    => true,
    'required'                   => false,
    'user_defined'               => true,
    'default'                    => '0',
]);
