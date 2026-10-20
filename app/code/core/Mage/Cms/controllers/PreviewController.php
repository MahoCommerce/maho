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
        if (!$provider->render($this, $draft)) {
            $this->_forward('noRoute');
        }
    }
}
