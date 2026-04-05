<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Acl
{
    /**
     * Get ACL resource tree
     */
    public function getTree(): array
    {
        $aclNode = Mage::getConfig()->getNode('adminhtml/acl/resources');
        if (!$aclNode) {
            return [];
        }

        return $this->buildTree($aclNode);
    }

    private function buildTree(\Maho\Simplexml\Element $node): array
    {
        $result = [];

        foreach ($node->children() as $child) {
            $name = $child->getName();
            $entry = [
                'id' => $name,
                'title' => (string) ($child->title ?? $name),
            ];

            if ($child->children()->count() > 0) {
                $childEntries = $this->buildTree($child);
                if (!empty($childEntries)) {
                    $entry['children'] = $childEntries;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }
}
