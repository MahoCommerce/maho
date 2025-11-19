<?php

/**
 * Maho
 *
 * @package    Mage_Rss
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rss_CatalogController extends Mage_Rss_Controller_Abstract
{
    public function newAction(): void
    {
        if ($this->checkFeedEnable('catalog/new')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    public function specialAction(): void
    {
        if ($this->checkFeedEnable('catalog/special')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    public function salesruleAction(): void
    {
        if ($this->checkFeedEnable('catalog/salesrule')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    public function tagAction(): void
    {
        if ($this->isFeedEnable('catalog/tag')) {
            $tagName = urldecode($this->getRequest()->getParam('tagName'));
            $tagModel = Mage::getModel('tag/tag');
            $tagModel->loadByName($tagName);
            if ($tagModel->getId() && $tagModel->getStatus() == $tagModel->getApprovedStatus()) {
                Mage::register('tag_model', $tagModel);
                $this->getResponse()->setHeader('Content-type', 'text/xml; charset=UTF-8');
                $this->loadLayout(false);
                $this->renderLayout();
                return;
            }
        }
        $this->_forward('nofeed', 'index', 'rss');
    }

    public function notifystockAction(): void
    {
        if ($this->checkFeedEnable('catalog/notifystock')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    public function reviewAction(): void
    {
        if ($this->checkFeedEnable('catalog/review')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    public function categoryAction(): void
    {
        if ($this->checkFeedEnable('catalog/category')) {
            $this->loadLayout(false);
            $this->renderLayout();
        }
    }

    /**
     * Controller pre-dispatch method to change area for some specific action.
     *
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        $action = strtolower($this->getRequest()->getActionName());
        if ($action == 'notifystock' && $this->isFeedEnable('catalog/notifystock')) {
            $this->_currentArea = Mage_Core_Model_App_Area::AREA_ADMINHTML;
            Mage::helper('rss')->authAdmin('catalog/products');
        }
        if ($action == 'review' && $this->isFeedEnable('catalog/review')) {
            $this->_currentArea = Mage_Core_Model_App_Area::AREA_ADMINHTML;
            Mage::helper('rss')->authAdmin('catalog/reviews_ratings');
        }
        return parent::preDispatch();
    }
}
