<?php

/**
 * Maho
 *
 * @package    Mage_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    public function initControllerRouters(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Varien_Front $front */
        $front = $observer->getEvent()->getFront();
        $front->addRouter('blog', $this);
    }

    #[\Override]
    public function match(Mage_Core_Controller_Request_Http $request): bool
    {
        if (!Mage::isInstalled() || !Mage::helper('blog')->isEnabled()) {
            return false;
        }

        $identifier = trim($request->getPathInfo(), '/');
        $helper = Mage::helper('blog');
        $urlPrefix = $helper->getBlogUrlPrefix();

        // Check if this matches the blog index page (exact match with prefix only)
        if ($identifier === $urlPrefix) {
            $request->setModuleName('blog')
                ->setControllerName('index')
                ->setActionName('index');
            $request->setAlias(
                Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
                $identifier,
            );
            return true;
        }

        // Check if this is a blog post URL (prefix/post-url-key)
        $pattern = '#^' . preg_quote($urlPrefix, '#') . '/(.+?)/?$#';
        if (!preg_match($pattern, $identifier, $matches)) {
            return false;
        }

        $urlKey = $matches[1];
        $post = Mage::getModel('blog/post');
        $postId = $post->getPostIdByUrlKey($urlKey, Mage::app()->getStore()->getId());
        if (!$postId) {
            return false;
        }

        $request->setModuleName('blog')
            ->setControllerName('index')
            ->setActionName('view')
            ->setParam('post_id', $postId);
        $request->setAlias(
            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
            $identifier,
        );

        return true;
    }
}
