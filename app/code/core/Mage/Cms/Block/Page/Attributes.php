<?php

/**
 * A table of the attributes of a CMS page that have "Visible on Frontend" set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Block_Page_Attributes extends Mage_Eav_Block_Frontend_Attributes
{
    /**
     * Return the page of the block data "entity", or else the page that the request shows.
     */
    #[\Override]
    public function getEntity(): ?Mage_Cms_Model_Page
    {
        $page = $this->getData('entity') ?? Mage::getSingleton('cms/page');
        return $page instanceof Mage_Cms_Model_Page && $page->getId() ? $page : null;
    }

    /**
     * A CMS page has no EAV resource, so the attribute comes from the list of the page.
     */
    #[\Override]
    protected function getHtmlAttribute(string $code): ?Mage_Eav_Model_Entity_Attribute
    {
        $attribute = $this->getEntity()?->getAttributes()[$code] ?? null;
        if (!$attribute instanceof Mage_Eav_Model_Entity_Attribute || !$attribute->getData('is_html_allowed_on_front')) {
            return null;
        }
        return $attribute;
    }
}
