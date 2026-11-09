<?php

/**
 * A table of the attributes of a blog post or a blog category that have "Visible on Front" set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Attributes extends Mage_Eav_Block_Frontend_Attributes
{
    /**
     * The flags of the attribute can change after the save, so sanitize the value again before the
     * processor resolves its directives.
     */
    #[\Override]
    protected function renderHtmlValue(Mage_Eav_Model_Entity_Attribute $attribute, string $html): string
    {
        if ($attribute->getData('is_wysiwyg_enabled') && $attribute->getData('is_html_allowed_on_front')) {
            $processor = Mage::helper('cms')->getPageTemplateProcessor();
            return (string) $processor->filter(
                (string) Mage::getSingleton('core/input_filter_maliciousCode')->filterPreservingDirectives($html, true, $processor),
            );
        }
        return parent::renderHtmlValue($attribute, $html);
    }
}
