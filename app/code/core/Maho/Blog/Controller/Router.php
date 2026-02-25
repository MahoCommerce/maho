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

        // Check if this is a blog URL (prefix/...)
        $pattern = '#^' . preg_quote($urlPrefix, '#') . '/(.+?)/?$#';
        if (!preg_match($pattern, $identifier, $matches)) {
            return false;
        }

        $pathPart = $matches[1];
        $storeId = Mage::app()->getStore()->getId();

        $catPrefix = $helper->getCategoryUrlPrefix();

        // Redirect bare /category-prefix to blog index
        if ($pathPart === $catPrefix) {
            Mage::app()->getResponse()
                ->setRedirect(Mage::getUrl($urlPrefix), 301)
                ->sendResponse();
            return true;
        }

        // Check for category URL (prefix/cat-prefix/{path}) — before post matching
        if ($helper->areCategoriesEnabled()) {
            $categoryPattern = '#^' . preg_quote($catPrefix, '#') . '/(.+?)$#';
            if (preg_match($categoryPattern, $pathPart, $catMatches)) {
                $categoryPath = $catMatches[1];
                // The last segment is the category url key
                $segments = explode('/', $categoryPath);
                $categoryUrlKey = array_pop($segments);
                $category = Mage::getModel('blog/category');
                $categoryId = $category->getCategoryIdByUrlKey($categoryUrlKey, $storeId);
                if ($categoryId) {
                    $request->setModuleName('blog')
                        ->setControllerName('index')
                        ->setActionName('category')
                        ->setParam('category_id', $categoryId);
                    $request->setAlias(
                        Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
                        $identifier,
                    );
                    return true;
                }
            }
        }

        // Check for post URL (prefix/{url-key})
        $urlKey = $pathPart;
        $post = Mage::getModel('blog/post');
        $postId = $post->getPostIdByUrlKey($urlKey, $storeId);
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
