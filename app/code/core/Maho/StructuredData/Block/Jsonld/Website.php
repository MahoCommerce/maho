<?php

/**
 * WebSite JSON-LD structured data with a sitelinks SearchAction.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_Jsonld_Website extends Maho_StructuredData_Block_Jsonld_Abstract
{
    protected string $_eventObject = 'website';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $helper = Mage::helper('structureddata');
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        $data = [
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
            '@type' => 'WebSite',
            'url' => $baseUrl,
            'name' => $helper->getOrganizationName(),
        ];

        // SearchAction depends on Mage_CatalogSearch (soft dependency): only add it when present.
        if (Mage::helper('core')->isModuleEnabled('Mage_CatalogSearch')) {
            $searchUrl = Mage::getUrl('catalogsearch/result');
            $queryParam = Mage::helper('catalogsearch')->getQueryParamName();
            $data['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrl . '?' . $queryParam . '={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $data;
    }
}
