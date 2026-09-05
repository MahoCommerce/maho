<?php

/**
 * Header switcher that links every website of the installation by its default store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

class Mage_Page_Block_Switch_Website extends Mage_Core_Block_Template
{
    /**
     * Websites with an active default store, sorted by sort order then name.
     *
     * @return list<array{code: string, name: string, url: string, is_current: bool}>
     */
    public function getWebsites(): array
    {
        if (!$this->hasData('websites')) {
            $current = (int) Mage::app()->getStore()->getWebsiteId();
            $websites = [];
            foreach (Mage::app()->getWebsites() as $website) {
                $store = $website->getDefaultStore();
                if (!$store || !$store->getIsActive()) {
                    continue;
                }
                $websites[] = [
                    'code' => (string) $website->getCode(),
                    'name' => (string) $website->getName(),
                    'url' => $store->getUrl('', ['_query' => []]),
                    'is_current' => (int) $website->getId() === $current,
                    'sort_order' => (int) $website->getSortOrder(),
                ];
            }
            usort($websites, static fn(array $a, array $b) => [$a['sort_order'], $a['name']] <=> [$b['sort_order'], $b['name']]);
            $this->setData('websites', array_map(static function (array $website): array {
                unset($website['sort_order']);
                return $website;
            }, $websites));
        }
        return $this->getData('websites');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (count($this->getWebsites()) < 2) {
            return '';
        }
        return parent::_toHtml();
    }
}
