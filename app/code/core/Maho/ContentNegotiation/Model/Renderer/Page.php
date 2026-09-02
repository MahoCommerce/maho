<?php

/**
 * Builds the markdown for a CMS page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_Page extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    #[\Override]
    public function render(): ?string
    {
        $page = $this->getPage();
        if ($page === null) {
            return null;
        }

        $sections = [$this->heading((string) $page->getTitle())];
        $html = Mage::helper('cms')->getPageTemplateProcessor()->filter((string) $page->getContent());
        $body = $this->toMarkdown($html);
        if ($body !== '') {
            $sections[] = $body;
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        return $this->getPage()?->getCacheTags() ?: [];
    }

    /**
     * Mage_Cms_Helper_Page loads the singleton for both the home page and cms/page/view.
     */
    private function getPage(): ?Mage_Cms_Model_Page
    {
        $page = Mage::getSingleton('cms/page');

        return $page instanceof Mage_Cms_Model_Page && $page->getId() ? $page : null;
    }
}
