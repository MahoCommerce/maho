<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_AdminMenu
{
    /**
     * Get admin menu structure from merged adminhtml.xml config
     */
    public function getMenu(): array
    {
        $adminConfig = Mage::getSingleton('admin/config')->getAdminhtmlConfig();
        $menuNode = $adminConfig->getNode('menu');
        if (!$menuNode) {
            return [];
        }

        return $this->buildMenu($menuNode);
    }

    private function buildMenu(\Maho\Simplexml\Element $node): array
    {
        $items = [];

        foreach ($node->children() as $child) {
            $name = $child->getName();
            $item = [
                'id' => $name,
                'title' => (string) ($child->title ?? $name),
                'sort_order' => (int) ($child->sort_order ?? 0),
            ];

            if (!empty($child->action)) {
                $item['action'] = (string) $child->action;
            }

            if (isset($child->children) && $child->children->children()->count() > 0) {
                $item['children'] = $this->buildMenu($child->children);
            }

            $items[] = $item;
        }

        usort($items, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $items;
    }
}
