<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Blog_Model_Observer
{
    public function addBlogToTopmenuItems(Varien_Event_Observer $observer): void
    {
        if (!Mage::helper('blog')->shouldShowInNavigation()) {
            return;
        }

        /** @var Varien_Data_Tree_Node $menu */
        $menu = $observer->getMenu();
        $tree = $menu->getTree();

        $blogNode = new Varien_Data_Tree_Node([
            'name' => Mage::helper('blog')->__('Blog'),
            'id' => 'blog-node',
            'url' => Mage::helper('blog')->getBlogUrl(),
            'has_active' => false, // Blog has no children, so always false
            'is_active' => Mage::app()->getRequest()->getModuleName() === 'blog',
        ], 'id', $tree, $menu);

        $menu->addChild($blogNode);
    }
}
