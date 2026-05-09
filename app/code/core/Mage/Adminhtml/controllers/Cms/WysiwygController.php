<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Cms_WysiwygController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms';

    /**
     * Validate HTML fragment via the W3C Nu validator
     */
    public function validateHtmlAction(): void
    {
        $html = $this->getRequest()->getPost('html', '');
        $html = preg_replace('/\{\{[^{}]*\}\}/', '', $html);

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
            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 15]);
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
                $skip = false;
                foreach ($ignoreList as $needle) {
                    if (str_contains($text, $needle)) {
                        $skip = true;
                        break;
                    }
                }
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
     * Template directives callback
     */
    #[Maho\Config\Route('/admin/cms_wysiwyg/directive')]
    public function directiveAction(): void
    {
        try {
            $directive = $this->getRequest()->getParam('___directive');
            $directive = Mage::helper('core')->urlDecode($directive);
            $path = Mage::getModel('cms/adminhtml_template_filter')->filter($directive);

            $allowedStreamWrappers = Mage::helper('cms')->getAllowedStreamWrappers();
            if (!Mage::getModel('core/file_validator_streamWrapper', $allowedStreamWrappers)->validate($path)) {
                Mage::throwException(Mage::helper('core')->__('Invalid stream.'));
            }

            $image = Maho::getImageManager()->decodePath($path)->encodeUsingPath($path);

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
