<?php

/**
 * Option source listing blog categories, used by the Recent Posts widget category parameter.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Source_Category
{
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => Mage::helper('blog')->__('-- All Categories --')],
        ];

        $collection = Mage::getResourceModel('blog/category_collection')
            ->addRootFilter()
            ->addActiveFilter()
            ->setOrder('path', Maho\Db\Select::SQL_ASC);

        foreach ($collection as $category) {
            $depth = max(0, (int) $category->getLevel() - 1);
            $options[] = [
                'value' => $category->getId(),
                'label' => str_repeat('. . ', $depth) . $category->getName(),
            ];
        }

        return $options;
    }
}
