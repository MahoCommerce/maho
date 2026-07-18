<?php

/**
 * Breadcrumb JSON-LD structured data (schema.org/BreadcrumbList).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_Jsonld_Breadcrumbs extends Maho_StructuredData_Block_Jsonld_Abstract
{
    protected string $_eventObject = 'breadcrumbs';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $crumbs = $this->_getCrumbs();

        // Google requires at least two items for a BreadcrumbList rich result.
        if (count($crumbs) < 2) {
            return [];
        }

        $itemListElement = [];
        $position = 1;
        foreach ($crumbs as $crumb) {
            $label = trim((string) ($crumb['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $listItem = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $label,
            ];

            $link = (string) ($crumb['link'] ?? '');
            if ($link !== '') {
                $listItem['item'] = $link;
            }

            $itemListElement[] = $listItem;
            $position++;
        }

        if (count($itemListElement) < 2) {
            return [];
        }

        return [
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ];
    }

    /**
     * Reuse the crumbs already collected by the page breadcrumbs block.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function _getCrumbs(): array
    {
        $block = $this->getLayout()->getBlock('breadcrumbs');
        if ($block instanceof Mage_Page_Block_Html_Breadcrumbs) {
            return $block->getCrumbs();
        }
        return [];
    }
}
