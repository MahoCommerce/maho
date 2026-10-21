<?php

/**
 * Shows the draft of the content editor on the storefront, in the theme of the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_PreviewController extends Mage_Core_Controller_Front_Action
{
    /** The full action name of the storefront page that the draft shows on. */
    private ?string $storefrontAction = null;

    /**
     * Run the request in the store view of the draft. The store view must be set before the
     * session, the locale and the design load. A `___store` query would set the store cookie
     * of the admin, and the storefront of the admin would then open that store view.
     *
     * The preview shows the page to a guest and starts no session. In a frame of another site,
     * the browser drops the session cookie, so each refresh would store one more session.
     */
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, true);
        $draft = Mage::getSingleton('cms/preview')->loadDraft((string) $this->getRequest()->getParam('draft'));
        $store = $draft !== null ? Mage::app()->getStores()[$draft['store_id']] ?? null : null;
        if ($store?->getIsActive()) {
            Mage::app()->setCurrentStore((int) $store->getId());
        }

        return parent::preDispatch();
    }

    /**
     * The draft id is the only key: it is random, and the admin gives it to the user who
     * wrote the draft. The page is never cached or indexed, and only the admin can frame it.
     */
    #[Maho\Config\Route('/cms/preview/view/{draft}', name: 'cms.preview.view', methods: ['GET'], requirements: ['draft' => '[0-9a-f]{32}'])]
    public function viewAction(): void
    {
        /** @var Mage_Cms_Model_Preview $preview */
        $preview = Mage::getSingleton('cms/preview');
        $draft = $preview->loadDraft((string) $this->getRequest()->getParam('draft'));
        $provider = $draft !== null ? $preview->getProvider($draft['entity']) : null;
        if ($draft === null || $provider === null || $draft['store_id'] !== (int) Mage::app()->getStore()->getId()) {
            $this->_forward('noRoute');
            return;
        }

        $this->getResponse()
            ->setHeader('Cache-Control', 'no-store, private', true)
            ->setHeader('X-Robots-Tag', 'noindex, nofollow', true)
            ->clearHeader('X-Frame-Options')
            ->setHeader('Content-Security-Policy', "frame-ancestors 'self' " . $draft['admin_origin'], true);

        Mage::register(Mage_Cms_Model_Preview::REGISTRY_KEY, ['draft' => $draft, 'provider' => $provider]);
        $this->storefrontAction = $provider->getStorefrontAction($draft);
        // A cached block can hold the saved content, and a block cached here holds draft content
        // and links to this preview URL, such as the page links of a widget pager
        $rendered = Mage::app()->getCache()->runWithout(
            Mage_Core_Block_Abstract::CACHE_GROUP,
            fn(): bool => $provider->render($this, $draft),
        );
        if (!$rendered) {
            $this->_forward('noRoute');
        }
    }

    /**
     * Add the handle of the storefront page next to the one of the preview, so the layout
     * updates of that page apply too.
     */
    #[\Override]
    public function addActionLayoutHandles()
    {
        parent::addActionLayoutHandles();
        if ($this->storefrontAction !== null) {
            $this->getLayout()->getUpdate()->addHandle($this->storefrontAction);
        }
        return $this;
    }

    /**
     * Add the body class of the storefront page, which the theme uses to style the content.
     */
    #[\Override]
    public function generateLayoutBlocks()
    {
        parent::generateLayoutBlocks();
        $root = $this->getLayout()->getBlock('root');
        if ($this->storefrontAction !== null && $root instanceof Mage_Page_Block_Html) {
            $root->addBodyClass(str_replace('_', '-', $this->storefrontAction));
        }
        return $this;
    }
}
