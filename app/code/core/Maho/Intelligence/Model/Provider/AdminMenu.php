<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
