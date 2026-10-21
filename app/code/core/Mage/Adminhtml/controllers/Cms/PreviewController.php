<?php

/**
 * Stores the draft that the Preview pane of the content editor shows.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Cms_PreviewController extends Mage_Adminhtml_Controller_Action
{
    /**
     * The editor posts its whole edit form here. The provider of `preview_entity` keeps
     * the fields that change the storefront and sanitizes them as a save does.
     */
    #[Maho\Config\Route('/admin/cms_preview/draft', methods: ['POST'])]
    public function draftAction(): void
    {
        $request = $this->getRequest();
        $entity = (string) $request->getPost('preview_entity');
        /** @var Mage_Cms_Model_Preview $preview */
        $preview = Mage::getSingleton('cms/preview');
        $provider = $preview->getProvider($entity);
        $store = Mage::app()->getStores()[(int) $request->getPost('preview_store')] ?? null;
        if ($provider === null || $store === null || !$store->getIsActive()) {
            $this->getResponse()->setHttpResponseCode(422)->setBodyJson(['error' => true, 'message' => $this->__('This content has no preview.')]);
            return;
        }

        $prepared = $provider->prepare($request->getPost());
        $entityId = (int) $request->getPost($preview->getIdField($entity));
        $contextId = (int) $request->getPost('preview_context');
        $draftId = $preview->saveDraft([
            'entity' => $entity,
            'entity_id' => $entityId > 0 ? $entityId : null,
            'store_id' => (int) $store->getId(),
            'admin_user_id' => (int) Mage::getSingleton('admin/session')->getUser()->getId(),
            'admin_origin' => $request->getScheme() . '://' . $request->getHttpHost(false),
            'context_page_id' => $contextId > 0 ? $contextId : null,
            'data' => $prepared['data'],
        ], (string) $request->getPost('preview_draft') ?: null);

        $this->getResponse()->setBodyJson([
            'draft' => $draftId,
            'url' => Mage::getModel('core/url')->setStore($store)->getUrl('cms/preview/view', ['draft' => $draftId, '_secure' => $request->isSecure()]),
            'warnings' => $prepared['warnings'],
        ]);
    }

    /**
     * The ACL resource of the editor that posts the draft decides, as the provider declares it.
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        $resource = Mage::getSingleton('cms/preview')->getAclResource((string) $this->getRequest()->getPost('preview_entity'));
        return $resource !== null && Mage::getSingleton('admin/session')->isAllowed($resource);
    }
}
