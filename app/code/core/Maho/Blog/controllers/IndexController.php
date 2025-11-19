<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        if (!Mage::helper('blog')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function viewAction(): void
    {
        if (!Mage::helper('blog')->isEnabled()) {
            $this->_forward('noRoute');
            return;
        }

        $postId = $this->getRequest()->getParam('post_id');
        $post = Mage::getModel('blog/post')->load($postId);
        if (!$post->getId()) {
            $this->_forward('noRoute');
            return;
        }

        Mage::register('current_blog_post', $post);
        $this->loadLayout();
        $this->renderLayout();
    }
}
