<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Block_Page extends Mage_Core_Block_Abstract
{
    /**
     * Retrieve Page instance
     *
     * @return Mage_Cms_Model_Page
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getPage()
    {
        if (!$this->hasData('page')) {
            if ($this->getPageId()) {
                $page = Mage::getModel('cms/page')
                    ->setStoreId(Mage::app()->getStore()->getId())
                    ->load($this->getPageId(), 'identifier');
            } else {
                $page = Mage::getSingleton('cms/page');
            }
            $this->addModelTags($page);
            $this->setData('page', $page);
        }
        return $this->getData('page');
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $page = $this->getPage();
        $breadcrumbsArray = [];
        $breadcrumbs = null;

        // show breadcrumbs
        if (Mage::getStoreConfig('web/default/show_cms_breadcrumbs')
            && ($breadcrumbs = $this->getLayout()->getBlock('breadcrumbs'))
            && !Mage::helper('cms/page')->isHomePage($page)
            && !Mage::helper('cms/page')->isNoRoutePage($page)
        ) {
            $breadcrumbsArray[] = [
                'crumbName' => 'home',
                'crumbInfo' => [
                    'label' => Mage::helper('cms')->__('Home'),
                    'title' => Mage::helper('cms')->__('Go to Home Page'),
                    'link'  => Mage::getBaseUrl(),
                ],
            ];
            $breadcrumbsArray[] = [
                'crumbName' => 'cms_page',
                'crumbInfo' => [
                    'label' => $page->getTitle(),
                    'title' => $page->getTitle(),
                ],
            ];
            $breadcrumbsObject = new \Maho\DataObject();
            $breadcrumbsObject->setCrumbs($breadcrumbsArray);

            Mage::dispatchEvent('cms_generate_breadcrumbs', ['breadcrumbs' => $breadcrumbsObject]);

            if ($breadcrumbs instanceof Mage_Page_Block_Html_Breadcrumbs) {
                foreach ($breadcrumbsObject->getCrumbs() as $breadcrumbsItem) {
                    $breadcrumbs->addCrumb($breadcrumbsItem['crumbName'], $breadcrumbsItem['crumbInfo']);
                }
            }
        }

        /** @var Mage_Page_Block_Html $root */
        $root = $this->getLayout()->getBlock('root');
        if ($root) {
            $root->addBodyClass('cms-' . $page->getIdentifier());
        }

        /** @var Mage_Page_Block_Html_Head $head */
        $head = $this->getLayout()->getBlock('head');
        if ($head) {
            $head->setTitle($page->getTitle());
            $head->setKeywords($page->getMetaKeywords());
            $head->setDescription($page->getMetaDescription());
            if ($canonicalUrl = $this->getCanonicalUrl()) {
                $head->addLinkRel('canonical', $canonicalUrl);
            }
        }

        return parent::_prepareLayout();
    }

    /**
     * Prepare HTML content
     *
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     * @throws Exception
     */
    #[\Override]
    protected function _toHtml()
    {
        /** @var Mage_Cms_Helper_Data $helper */
        $helper = Mage::helper('cms');
        $processor = $helper->getPageTemplateProcessor();
        $html = $processor->filter($this->getPage()->getContent());
        $html = $this->getMessagesBlock()->toHtml() . $html;
        return $html;
    }

    /**
     * Return the URL of the page for <link rel="canonical">, or null when the page gets no canonical.
     * The home page is the base URL, which its own identifier redirects to.
     */
    public function getCanonicalUrl(): ?string
    {
        $page = $this->getPage();
        $identifier = (string) $page->getIdentifier();
        /** @var Mage_Cms_Helper_Page $helper */
        $helper = Mage::helper('cms/page');
        if ($identifier === ''
            || $helper->isNoRoutePage($page)
            || stripos((string) $page->getMetaRobots(), 'noindex') !== false
        ) {
            return null;
        }

        /** @var Mage_Core_Helper_Url $urlHelper */
        $urlHelper = Mage::helper('core/url');
        $params = ['_query' => $urlHelper->getCanonicalStoreQuery()];
        if ($helper->isHomePage($page)) {
            return $urlHelper->keepBasePathSlash(Mage::getUrl('', $params));
        }

        return Mage::getUrl(null, ['_direct' => $identifier] + $params);
    }

    public function getPageId(): ?int
    {
        $value = $this->getData('page_id');
        return $value === null ? null : (int) $value;
    }
}
