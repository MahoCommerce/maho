<?php

/**
 * Renders the assistant panel on every admin page when the feature is on and the role allows it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Assistant extends Mage_Adminhtml_Block_Template
{
    /** Admin controller name => [entity type, id parameter, model alias, label attribute]. */
    private const ENTITIES = [
        'catalog_product' => ['product', 'id', 'catalog/product', 'name'],
        'catalog_category' => ['category', 'id', 'catalog/category', 'name'],
        'customer' => ['customer', 'id', 'customer/customer', 'name'],
        'sales_order' => ['order', 'order_id', 'sales/order', 'increment_id'],
        'sales_order_invoice' => ['invoice', 'invoice_id', 'sales/order_invoice', 'increment_id'],
        'sales_order_shipment' => ['shipment', 'shipment_id', 'sales/order_shipment', 'increment_id'],
        'sales_order_creditmemo' => ['credit memo', 'creditmemo_id', 'sales/order_creditmemo', 'increment_id'],
        'cms_page' => ['CMS page', 'page_id', 'cms/page', 'title'],
        'cms_block' => ['CMS block', 'block_id', 'cms/block', 'title'],
        'promo_quote' => ['cart price rule', 'id', 'salesrule/rule', 'name'],
        'promo_catalog' => ['catalog price rule', 'id', 'catalogrule/rule', 'name'],
        'customer_group' => ['customer group', 'id', 'customer/group', 'customer_group_code'],
        'newsletter_template' => ['newsletter template', 'id', 'newsletter/template', 'template_code'],
        'system_email_template' => ['email template', 'id', 'core/email_template', 'template_code'],
    ];

    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->isAvailable()) {
            return '';
        }

        return parent::_toHtml();
    }

    public function isAvailable(): bool
    {
        $helper = Mage::helper('ai');

        return $helper->isChatEnabled() && $helper->isChatAllowed();
    }

    /**
     * Everything the panel script needs, as one JSON document.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $helper = Mage::helper('ai');
        $apiBase = rtrim(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/');

        return [
            'chatUrl' => $apiBase . '/api/admin/ai/chat',
            'confirmUrl' => $apiBase . '/api/admin/ai/chat/confirm',
            'undoUrl' => $apiBase . '/api/admin/ai/chat/undo',
            'cssUrl' => $this->getVersionedSkinUrl('ai-chat.css'),
            'introIcon' => $this->getIconSvg('sparkles'),
            'editorUrl' => $this->getVersionedJsUrl('mage/adminhtml/wysiwyg/tiptap/setup.js'),
            'needsEditorGuide' => $helper->editorGuide() === '',
            'adminName' => (string) Mage::getSingleton('admin/session')->getUser()?->getFirstname(),
            'examples' => [
                $helper->__('Which orders came in today?'),
                $helper->__('Find products that are low in stock'),
                $helper->__('Open the home page of the default store in the editor'),
                $helper->__('How many customers registered this week?'),
            ],
            'listUrl' => $this->getUrl('adminhtml/ai_chat/list'),
            'messagesUrl' => $this->getUrl('adminhtml/ai_chat/messages'),
            'deleteUrl' => $this->getUrl('adminhtml/ai_chat/delete'),
            'formKey' => $this->getFormKey(),
            'context' => $this->getPageContext(),
            'labels' => [
                'title' => $helper->__('Maho Assistant'),
                'placeholder' => $helper->__('Ask the assistant to find or change something...'),
                'introGreeting' => $helper->__('Hi %s, what can I do for you?'),
                'introGreetingAnonymous' => $helper->__('What can I do for you?'),
                'intro' => $helper->__('I can look up and change products, orders, customers, content and settings for you. Every change waits for your confirmation.'),
                'introExamples' => $helper->__('Try one of these:'),
                'introHint' => $helper->__('%s opens and closes this panel.'),
                'newChat' => $helper->__('New chat'),
                'conversations' => $helper->__('Conversations'),
                'close' => $helper->__('Close'),
                'open' => $helper->__('Open the assistant'),
                'delete' => $helper->__('Delete this conversation'),
                'deleteConfirm' => $helper->__('Delete this conversation?'),
                'approve' => $helper->__('Approve'),
                'approveSelected' => $helper->__('Approve selected'),
                'deny' => $helper->__('Deny'),
                'confirmTitle' => $helper->__('The assistant wants to make these changes:'),
                'undo' => $helper->__('Undo'),
                'changeRecord' => $helper->__('Record'),
                'changeScopeDefault' => $helper->__('Scope: every store view without a value of its own'),
                'changeScopeStore' => $helper->__('Scope: store view %s'),
                'changeField' => $helper->__('Field'),
                'changeFrom' => $helper->__('Now'),
                'changeTo' => $helper->__('After'),
                'changeNone' => $helper->__('No field changes: the values are already as requested.'),
                'changeDelete' => $helper->__('This record will be deleted.'),
                'changeEmpty' => $helper->__('(empty)'),
                'approved' => $helper->__('Approved'),
                'denied' => $helper->__('Denied'),
                'cancelled' => $helper->__('Cancelled'),
                'pending' => $helper->__('Waiting for confirmation'),
                'failed' => $helper->__('Failed'),
                'running' => $helper->__('Running'),
                'thinking' => $helper->__('Thinking'),
                'openPage' => $helper->__('Open admin page'),
                'fillForm' => $helper->__('Fill admin form'),
                'loadTools' => $helper->__('Load tools'),
                'pageAction' => $helper->__('Act on the page'),
                'contentGuide' => $helper->__('Content editor guide'),
                'remember' => $helper->__('Remember a note'),
                'forget' => $helper->__('Forget a note'),
                'actionDone' => $helper->__('Done: %s.'),
                'actionNotFound' => $helper->__('I could not find "%s" on this page.'),
                'formFilled' => $helper->__('I filled these fields in the form: %s. Review the form and save it.'),
                'formFieldsMissing' => $helper->__('I could not find these fields in the form: %s.'),
                'showSource' => $helper->__('Show the HTML source'),
                'verbList' => $helper->__('List'),
                'verbGet' => $helper->__('Show'),
                'verbCreate' => $helper->__('Create'),
                'verbUpdate' => $helper->__('Update'),
                'verbDelete' => $helper->__('Delete'),
                'done' => $helper->__('Done'),
                'destructive' => $helper->__('Deletes data'),
                'error' => $helper->__('The assistant could not answer. Try again.'),
                'stopped' => $helper->__('Stopped.'),
                'noConversations' => $helper->__('No conversations yet.'),
                'untitled' => $helper->__('Untitled conversation'),
            ],
        ];
    }

    /** A script URL with the file modification time, so a browser never keeps a stale copy. */
    public function getVersionedJsUrl(string $file): string
    {
        return $this->versioned($this->getJsUrl($file), Mage::getBaseDir('public') . DS . 'js' . DS . $file);
    }

    /** A skin file URL with the file modification time, so a browser never keeps a stale copy. */
    public function getVersionedSkinUrl(string $file): string
    {
        return $this->versioned($this->getSkinUrl($file), (string) Mage::getDesign()->getFilename($file, ['_type' => 'skin']));
    }

    private function versioned(string $url, string $path): string
    {
        return is_file($path) ? $url . '?v=' . filemtime($path) : $url;
    }

    /**
     * @return array{route: string, entity_type: string, entity_id: int|string|null, entity_label: string, store: string}
     */
    public function getPageContext(): array
    {
        $request = $this->getRequest();
        $controller = (string) $request->getControllerName();
        $context = [
            'route' => sprintf('%s/%s', $controller, (string) $request->getActionName()),
            'entity_type' => '',
            'entity_id' => null,
            'entity_label' => '',
            'store' => '',
        ];

        $storeId = (int) $request->getParam('store');
        if ($storeId > 0) {
            try {
                $context['store'] = (string) Mage::app()->getStore($storeId)->getCode();
            } catch (Mage_Core_Model_Store_Exception) {
                // the page scope stays empty
            }
        }

        if ($controller === 'system_config') {
            $section = (string) $request->getParam('section');
            if ($section !== '') {
                $context['entity_type'] = 'configuration section';
                $context['entity_id'] = $section;
            }
            return $context;
        }

        $entity = self::ENTITIES[$controller] ?? null;
        if ($entity === null) {
            return $context;
        }
        [$type, $param, $alias, $labelAttribute] = $entity;
        $id = (int) $request->getParam($param);
        if ($id <= 0) {
            return $context;
        }

        $context['entity_type'] = $type;
        $context['entity_id'] = $id;
        try {
            $model = Mage::getModel($alias)->load($id);
            if ($model->getId()) {
                $context['entity_label'] = $labelAttribute === 'name' && $model instanceof Mage_Customer_Model_Customer
                    ? (string) $model->getName()
                    : (string) $model->getData($labelAttribute);
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }

        return $context;
    }
}
