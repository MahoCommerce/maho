<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Cms_WysiwygController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms';

    /**
     * Replace template directives with a non-empty placeholder before HTML validation.
     *
     * A directive used inside an attribute (e.g. src="{{media url="..."}}") would otherwise
     * collapse to src="" once stripped, triggering a bogus "Must be non-empty" validator error.
     * Match against the canonical directive pattern so exactly the constructs the template filter
     * renders are blanked out, and the resulting placeholder (#) stays syntactically valid.
     */
    public static function blankDirectivesForValidation(string $html): string
    {
        return (string) preg_replace(\Maho\Filter\Template::CONSTRUCTION_PATTERN, '#', $html);
    }

    /**
     * Validate HTML fragment via the W3C Nu validator
     */
    public function validateHtmlAction(): void
    {
        $html = self::blankDirectivesForValidation($this->getRequest()->getPost('html', ''));

        $prefix = "<!DOCTYPE html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>v</title></head>\n<body>\n";
        $suffix = "\n</body>\n</html>";
        $prefixLines = substr_count($prefix, "\n");

        $ignoreList = [];
        $ignoreConfig = (string) Mage::getStoreConfig('cms/html_validator/ignore');
        foreach (explode("\n", $ignoreConfig) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $ignoreList[] = $line;
        }

        try {
            $client = \Maho\Http\Client::create(['timeout' => 15]);
            $response = $client->request('POST', 'https://validator.w3.org/nu/?out=json', [
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => $prefix . $html . $suffix,
            ]);

            $result = json_decode($response->getContent(), true);
            $messages = [];
            $ignoredCount = 0;

            foreach ($result['messages'] ?? [] as $msg) {
                if (isset($msg['firstLine'])) {
                    $msg['firstLine'] -= $prefixLines;
                }
                if (isset($msg['lastLine'])) {
                    $msg['lastLine'] -= $prefixLines;
                }
                if (($msg['lastLine'] ?? 1) < 1) {
                    continue;
                }
                $text = (string) ($msg['message'] ?? '');
                $skip = array_any($ignoreList, fn($needle) => str_contains($text, $needle));
                if ($skip) {
                    $ignoredCount++;
                    continue;
                }
                $messages[] = $msg;
            }

            $this->getResponse()
                ->setHeader('Content-Type', 'application/json', true)
                ->setBody(Mage::helper('core')->jsonEncode([
                    'messages' => $messages,
                    'ignored' => $ignoredCount,
                ]));
        } catch (\Exception $e) {
            Mage::logException($e);
            $this->getResponse()
                ->setHeader('Content-Type', 'application/json', true)
                ->setBody(Mage::helper('core')->jsonEncode([
                    'error' => true,
                    'message' => Mage::helper('cms')->__('Could not reach the HTML validator service.'),
                ]));
        }
    }

    /**
     * Report what a save removes from this content. The author can still change it.
     *
     * `scope` selects the template processor of the save path. The catalog processor resolves
     * fewer directives than the CMS processor. An unresolved directive is removed, not kept.
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg/sanitizePreview', methods: ['POST'])]
    public function sanitizePreviewAction(): void
    {
        $html = (string) $this->getRequest()->getPost('html', '');
        $renderer = $this->getRequest()->getParam('scope') === 'catalog'
            ? Mage::helper('catalog')->getPageTemplateProcessor()
            : Mage::helper('cms')->getPageTemplateProcessor();

        $filtered = (string) Mage::getSingleton('core/input_filter_maliciousCode')
            ->filterPreservingDirectives($html, false, $renderer);

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode([
                'removed' => Mage_Core_Model_Input_Filter_MaliciousCode::describeRemoved($html, $filtered),
            ]));
    }

    /**
     * Render the posted widget, block and icon directives as the storefront of a store view shows
     * them. Each directive needs the ACL resource of its type, so the response holds only what
     * the admin can already see. A directive that the admin cannot render is not in the response.
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg/renderDirectives', methods: ['POST'])]
    public function renderDirectivesAction(): void
    {
        /** @var Mage_Cms_Model_Wysiwyg_Render $renderer */
        $renderer = Mage::getSingleton('cms/wysiwyg_render');
        $session = Mage::getSingleton('admin/session');

        $directives = [];
        foreach ((array) $this->getRequest()->getPost('directives', []) as $directive) {
            $type = is_string($directive) ? $renderer->getDirectiveType($directive) : null;
            $resource = $type === null ? null : Mage_Cms_Model_Wysiwyg_Render::TYPES[$type];
            if ($type !== null && ($resource === null || $session->isAllowed($resource))) {
                $directives[$directive] = $directive;
            }
        }
        $directives = array_slice(array_values($directives), 0, Mage_Cms_Model_Wysiwyg_Render::MAX_DIRECTIVES);

        $store = Mage::app()->getStores()[(int) $this->getRequest()->getPost('store_id')] ?? null;
        if (!$store instanceof Mage_Core_Model_Store || $directives === []) {
            $this->getResponse()->setBodyJson(['head' => '', 'html' => new \stdClass()]);
            return;
        }

        $this->getResponse()->setBodyJson($renderer->render($directives, (int) $store->getId()));
    }

    /**
     * Send the stylesheets and the Tailwind source of the theme of a store view. The plain HTML
     * editor reads them to complete class names and to find the classes that nothing styles.
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg/classCheck', methods: ['POST'])]
    public function classCheckAction(): void
    {
        $store = Mage::app()->getStores()[(int) $this->getRequest()->getPost('store_id')] ?? null;
        if (!$store instanceof Mage_Core_Model_Store) {
            $this->getResponse()->setBodyJson(['head' => '', 'styles' => '', 'css' => '']);
            return;
        }

        $this->getResponse()->setBodyJson(Mage::getSingleton('cms/wysiwyg_render')->renderStyles((int) $store->getId()));
    }

    /**
     * The preview only reports on content that the caller posted. It reveals no other data.
     * The CMS resource would block an admin who edits products but not CMS pages.
     * The render checks the resource of each directive itself. The class check sends only
     * the public stylesheets of the storefront.
     */
    #[\Override]
    protected function _isAllowed()
    {
        if (in_array($this->getRequest()->getActionName(), ['sanitizePreview', 'renderDirectives', 'classCheck'], true)) {
            return Mage::getSingleton('admin/session')->isLoggedIn();
        }
        return parent::_isAllowed();
    }

    /**
     * Template directives callback
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg/directive')]
    public function directiveAction(): void
    {
        try {
            $directive = $this->getRequest()->getParam('___directive');
            $image = Mage::getModel('cms/adminhtml_template_filter')
                ->encodeDirectiveImage(Mage::helper('core')->urlDecode((string) $directive));

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-type', $image->mediaType(), true);

        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()
                ->setHttpResponseCode(500);
        }

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        if (isset($image)) {
            print $image;
        }
        exit(0);
    }
}
