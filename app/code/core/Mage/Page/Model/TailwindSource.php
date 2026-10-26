<?php

/**
 * The HTML of the design config fields that the page layout shows, for the Tailwind build.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

class Mage_Page_Model_TailwindSource
{
    public const CONFIG_PATHS = [
        'design/footer/copyright',
        'design/footer/absolute_footer',
        'design/head/demonotice_text',
    ];

    /**
     * The value of each path in CONFIG_PATHS in every scope.
     *
     * @return Generator<int, string>
     */
    #[Maho\Config\TailwindSource('page_config')]
    public function getConfigContent(): Generator
    {
        $resource = Mage::getResourceSingleton('core/design_content');
        yield from $resource->readColumns($resource->getTable('core/config_data'), 'config_id', ['value'], ['path' => self::CONFIG_PATHS]);
    }

    #[Maho\Config\Observer('core_config_data_save_commit_after', id: 'tailwind_source_check_page_config')]
    public function checkSavedConfig(\Maho\Event\Observer $observer): void
    {
        $object = $observer->getEvent()->getData('data_object');
        if ($object instanceof Mage_Core_Model_Config_Data
            && in_array($object->getPath(), self::CONFIG_PATHS, true)
            && $object->isValueChanged()
        ) {
            Mage::getSingleton('core/design_content_bundle')->queueBuild();
        }
    }
}
