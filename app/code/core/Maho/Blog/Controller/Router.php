<?php

/**
 * Maho
 *
 * @package    Mage_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    public function initControllerRouters(Varien_Event_Observer $observer): void
    {
        /** @var Mage_Core_Controller_Varien_Front $front */
        $front = $observer->getEvent()->getFront();
        $front->addRouter('blog', $this);
    }

    #[\Override]
    public function match(Zend_Controller_Request_Http $request): bool
    {
        if (!Mage::isInstalled() || !Mage::helper('blog')->isEnabled()) {
            return false;
        }

        $identifier = trim($request->getPathInfo(), '/');

        // Check if this is a blog URL (starts with 'blog/')
        if (!preg_match('#^blog/(.+?)/?$#', $identifier, $matches)) {
            return false;
        }

        $urlKey = $matches[1];
        $condition = new Varien_Object([
            'identifier' => $identifier,
            'continue'   => true,
        ]);
        Mage::dispatchEvent('cms_controller_router_match_before', [
            'router'    => $this,
            'condition' => $condition,
        ]);

        if ($condition->getRedirectUrl()) {
            Mage::app()->getFrontController()->getResponse()
                ->setRedirect($condition->getRedirectUrl())
                ->sendResponse();
            $request->setDispatched(true);
            return true;
        }

        if (!$condition->getContinue()) {
            return false;
        }

        $post   = Mage::getModel('blog/post');
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
