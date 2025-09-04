<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Controller_Response_Http extends Zend_Controller_Response_Http
{
    /**
     * Transport object for observers to perform
     * @var Varien_Object
     */
    protected static $_transportObject = null;

    /**
     * Fixes CGI only one Status header allowed bug
     * @link  http://bugs.php.net/bug.php?id=36705
     */
    #[\Override]
    public function sendHeaders()
    {
        if (!$this->canSendHeaders()) {
            Mage::log('HEADERS ALREADY SENT: ' . mageDebugBacktrace(true, true, true));
            return $this;
        }

        if (str_starts_with(php_sapi_name(), 'cgi')) {
            $statusSent = false;
            foreach ($this->_headersRaw as $i => $header) {
                if (stripos($header, 'status:') === 0) {
                    if ($statusSent) {
                        unset($this->_headersRaw[$i]);
                    } else {
                        $statusSent = true;
                    }
                }
            }
            foreach ($this->_headers as $i => $header) {
                if (strcasecmp($header['name'], 'status') === 0) {
                    if ($statusSent) {
                        unset($this->_headers[$i]);
                    } else {
                        $statusSent = true;
                    }
                }
            }
        }

        return parent::sendHeaders();
    }

    #[\Override]
    public function sendResponse()
    {
        Mage::dispatchEvent('http_response_send_before', ['response' => $this]);

        // Process body to defer JavaScript loading
        $body = $this->getBody();
        if ($this->shouldDeferJavaScript($body)) {
            $deferMode = $this->getJavaScriptDeferMode();
            $processedBody = $this->deferJavaScriptLoading($body, $deferMode);
            $this->setBody($processedBody);
        }

        parent::sendResponse();
    }

    /**
     * Additionally check for session messages in several domains case
     */
    #[\Override]
    public function setRedirect($url, $code = 302)
    {
        /**
         * Use single transport object instance
         */
        if (self::$_transportObject === null) {
            self::$_transportObject = new Varien_Object();
        }
        self::$_transportObject->setUrl($url);
        self::$_transportObject->setCode($code);
        Mage::dispatchEvent(
            'controller_response_redirect',
            ['response' => $this, 'transport' => self::$_transportObject],
        );

        return parent::setRedirect(self::$_transportObject->getUrl(), self::$_transportObject->getCode());
    }

    /**
     * Method send already collected headers and exit from script
     */
    public function sendHeadersAndExit(): never
    {
        $this->sendHeaders();
        exit;
    }

    /**
     * Prepare JSON formatted data for response to client
     */
    public function setBodyJson(mixed $response): self
    {
        $this->setHeader('Content-type', 'application/json', true);

        if (is_string($response) && json_validate($response)) {
            $this->setBody($response);
        } else {
            $this->setBody(Mage::helper('core')->jsonEncode($response));
        }

        return $this;
    }

    protected function getJavaScriptDeferMode(): int
    {
        return (int) Mage::getStoreConfig('dev/js/defer_mode');
    }

    /**
     * Check if JavaScript deferral should be applied
     */
    protected function shouldDeferJavaScript(string $body): bool
    {
        // Check if feature is enabled
        if ($this->getJavaScriptDeferMode() == Mage_Core_Model_Source_Js_Defer::MODE_DISABLED) {
            return false;
        }

        // Skip for admin panel
        if (Mage::app()->getStore()->isAdmin()) {
            return false;
        }

        // Skip for checkout pages
        if (Mage::app()->getRequest()->getModuleName() === 'checkout') {
            return false;
        }

        // Skip if already processed
        if (str_contains($body, 'mahoLazyJs')) {
            return false;
        }

        // Skip for non-HTML responses
        $contentType = '';
        foreach ($this->_headers as $header) {
            if (strcasecmp($header['name'], 'Content-Type') === 0) {
                $contentType = $header['value'];
                break;
            }
        }

        // If no content type is set, check if body contains HTML
        if (empty($contentType)) {
            // Check if it looks like HTML
            if (!preg_match('/<html|<body|<!DOCTYPE/i', substr($body, 0, 1000))) {
                return false;
            }
        } elseif (!str_contains($contentType, 'text/html')) {
            return false;
        }

        return true;
    }

    /**
     * Defer JavaScript loading based on selected mode
     */
    protected function deferJavaScriptLoading(string $html, int $mode): string
    {
        $scriptIndex = 0;
        $scripts = [];

        // Extract all scripts and transform based on mode
        $html = preg_replace_callback(
            '/<script(?:\s+[^>]*?)?>(?:(?!<\/script>).)*?<\/script>/is',
            function ($matches) use (&$scriptIndex, $mode, &$scripts) {
                // For load on intent mode, transform scripts
                if ($mode == Mage_Core_Model_Source_Js_Defer::MODE_LOAD_ON_INTENT) {
                    // Skip if contains our loader, already has data attributes, or is a speculation rules script
                    $shouldSkip = str_contains($matches[0], 'mahoLazyJs') ||
                                  str_contains($matches[0], 'type="speculationrules"') ||
                                  preg_match('/\sdata-(?!maho-script)\w+=/i', $matches[0]);

                    if ($shouldSkip) {
                        $scripts[] = $matches[0];
                        return '';
                    }

                    // Extract and process attributes
                    if (preg_match('/<script(\s+[^>]*?)?>(.*?)<\/script>/is', $matches[0], $parts)) {
                        $attrs = $parts[1];
                        $content = $parts[2];

                        // Remove existing type attribute more efficiently
                        $attrs = preg_replace('/\stype\s*=\s*["\']?[^"\'>\s]+["\']?/i', '', $attrs);

                        $scripts[] = '<script type="text/plain" data-maho-script="' . $scriptIndex++ . '"' . $attrs . '>' . $content . '</script>';
                    } else {
                        $scripts[] = $matches[0];
                    }
                } else {
                    // For defer only mode, keep script as-is
                    $scripts[] = $matches[0];
                }

                // Remove script from original position
                return '';
            },
            $html,
        );

        // Prepare scripts for bottom insertion
        $scriptsHtml = '';
        if (!empty($scripts)) {
            $scriptsHtml = implode("\n", $scripts);

            // Add loader for load on intent mode
            if ($mode == Mage_Core_Model_Source_Js_Defer::MODE_LOAD_ON_INTENT) {
                $scriptsHtml .= "\n" . $this->getJavaScriptLoader();
            }
        }

        // Inject scripts before </body> or at end
        $bodyPos = stripos($html, '</body>');

        return $bodyPos !== false
            ? substr($html, 0, $bodyPos) . "\n" . $scriptsHtml . "\n" . substr($html, $bodyPos)
            : $html . "\n" . $scriptsHtml;
    }

    protected function getJavaScriptLoader(): string
    {
        $baseJsUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS);
        return '<script src="' . $baseJsUrl . 'maho-load-on-intent.js"></script>';
    }
}
