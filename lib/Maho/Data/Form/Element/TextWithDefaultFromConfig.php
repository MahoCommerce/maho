<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data\Form\Element;

/**
 * Text input with automatic placeholder from system config default
 *
 * Usage:
 * $fieldset->addField('my_field', 'text_with_default_from_config', [
 *     'name'        => 'my_field',
 *     'label'       => 'My Field',
 *     'config_path' => 'section/group/field',  // System config path for default value
 * ]);
 *
 * When the field is empty, the system config value will be used.
 * The placeholder shows the default value to the user.
 */
class TextWithDefaultFromConfig extends Text
{
    #[\Override]
    public function getElementHtml(): string
    {
        $this->applyConfigPlaceholder();
        return parent::getElementHtml();
    }

    /**
     * Apply placeholder from system config if config_path is set
     */
    protected function applyConfigPlaceholder(): void
    {
        $configPath = $this->getConfigPath();
        if (!$configPath) {
            return;
        }

        // Only set placeholder if not already set
        if ($this->getPlaceholder()) {
            return;
        }

        $defaultValue = \Mage::getStoreConfig($configPath);
        if ($defaultValue !== null && $defaultValue !== '') {
            $helper = \Mage::helper('adminhtml');
            $this->setPlaceholder($helper->__('Default: %s', $defaultValue));
        }
    }

    /**
     * Get the config path for the default value
     */
    public function getConfigPath(): ?string
    {
        return $this->getData('config_path');
    }
}
