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
        if ($this->_shouldDeferJavaScript($body)) {
            $processedBody = $this->_deferJavaScriptLoading($body);
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

    /**
     * Check if JavaScript deferral should be applied
     */
    protected function _shouldDeferJavaScript(string $body): bool
    {
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

        // Skip for admin panel
        if (Mage::app()->getStore()->isAdmin()) {
            return false;
        }

        // Check if feature is enabled
        return Mage::getStoreConfigFlag('dev/js/load_on_intent');
    }

    /**
     * Defer JavaScript loading until user interaction
     */
    protected function _deferJavaScriptLoading(string $html): string
    {
        $scriptIndex = 0;
        
        // More precise regex that handles edge cases better
        $html = preg_replace_callback(
            '/<script(?:\s+[^>]*?)?>(?:(?!<\/script>).)*?<\/script>/is',
            function ($matches) use (&$scriptIndex) {
                // Skip if contains our loader or already has data attributes
                if (str_contains($matches[0], 'mahoLazyJs') || preg_match('/\sdata-(?!maho-script)\w+=/i', $matches[0])) {
                    return $matches[0];
                }
                
                // Extract and process attributes
                if (preg_match('/<script(\s+[^>]*?)?>(.*?)<\/script>/is', $matches[0], $parts)) {
                    $attrs = $parts[1] ?? '';
                    $content = $parts[2];
                    
                    // Remove existing type attribute more efficiently
                    $attrs = preg_replace('/\stype\s*=\s*["\']?[^"\'>\s]+["\']?/i', '', $attrs);
                    
                    return '<script type="text/plain" data-maho-script="' . $scriptIndex++ . '"' . $attrs . '>' . $content . '</script>';
                }
                
                return $matches[0];
            },
            $html
        );
        
        // Inject loader before </body> or at end
        $loader = $this->_getJavaScriptLoader();
        $bodyPos = stripos($html, '</body>');
        
        return $bodyPos !== false 
            ? substr($html, 0, $bodyPos) . $loader . substr($html, $bodyPos)
            : $html . $loader;
    }

    /**
     * Get the JavaScript loader code
     */
    protected function _getJavaScriptLoader(): string
    {
        return '<script>
(function() {
    "use strict";
    
    const events = ["click", "keydown", "mousemove", "mouseover", "touchstart", "scroll", "wheel"];
    let loaded = false;
    let scripts = [];
    let loadIndex = 0;
    
    function init() {
        // Collect all deferred scripts
        document.querySelectorAll(\'script[type="text/plain"][data-maho-script]\').forEach(script => {
            scripts.push({
                element: script,
                src: script.getAttribute("src"),
                content: script.textContent,
                index: +script.getAttribute("data-maho-script")
            });
        });
        
        // Sort by original index to maintain order
        scripts.sort((a, b) => a.index - b.index);
        
        // Setup event listeners
        events.forEach(event => {
            document.addEventListener(event, load, { once: true, passive: true });
        });
        
        // Also trigger on first real user interaction with forms
        document.addEventListener("focusin", load, { once: true, passive: true });
        document.addEventListener("change", load, { once: true, passive: true });
    }
    
    function load() {
        if (loaded) return;
        loaded = true;
        
        // Remove remaining event listeners
        events.forEach(event => document.removeEventListener(event, load));
        
        // Start loading scripts
        loadNext();
    }
    
    function loadNext() {
        if (loadIndex >= scripts.length) {
            // All scripts loaded, fire events
            fireEvents();
            return;
        }
        
        const script = scripts[loadIndex++];
        const newScript = document.createElement("script");
        
        // Copy all attributes except type and data-maho-script
        for (const attr of script.element.attributes) {
            if (attr.name !== "type" && attr.name !== "data-maho-script") {
                newScript.setAttribute(attr.name, attr.value);
            }
        }
        
        if (script.src) {
            // External script
            newScript.onload = newScript.onerror = () => {
                // Some scripts may define things in onload, give them time
                setTimeout(loadNext, 0);
            };
            newScript.src = script.src;
        } else {
            // Inline script
            newScript.textContent = script.content;
        }
        
        // Replace original script element
        script.element.parentNode.replaceChild(newScript, script.element);
        
        // Continue immediately for inline scripts
        if (!script.src) {
            setTimeout(loadNext, 0);
        }
    }
    
    function fireEvents() {
        setTimeout(() => {
            // Fire DOMContentLoaded
            const domEvent = new Event("DOMContentLoaded", { bubbles: true, cancelable: false });
            document.dispatchEvent(domEvent);
            
            // Fire load event
            const loadEvent = new Event("load", { bubbles: false, cancelable: false });
            window.dispatchEvent(loadEvent);
            
            // Trigger direct event handlers
            if (typeof window.onload === "function") {
                window.onload(loadEvent);
            }
            if (typeof document.onreadystatechange === "function") {
                document.onreadystatechange(new Event("readystatechange"));
            }
            
            // Trigger Prototype.js events
            if (typeof document.fire === "function") {
                document.fire("dom:loaded");
                document.fire("contentloaded");
                // Some custom implementations use these variations
                document.fire("dom:content:loaded");
                document.fire("DOMContentLoaded");
            }
            
            // Trigger Event.observe callbacks for dom:loaded
            if (typeof Event !== "undefined" && Event.observers) {
                const observers = Event.observers;
                for (let i = 0; i < observers.length; i++) {
                    if (observers[i] && observers[i].eventName === "dom:loaded") {
                        try {
                            observers[i].handler.call(document);
                        } catch (e) {}
                    }
                }
            }
            
            // jQuery ready events (in case some extensions use it)
            if (typeof jQuery !== "undefined" && jQuery.isReady === false) {
                jQuery.ready();
            }
            
            // Magento-specific initialization
            if (typeof varienGlobalEvents !== "undefined" && varienGlobalEvents.fireEvent) {
                varienGlobalEvents.fireEvent("dom:loaded");
            }
            
            // For scripts that check document.readyState
            Object.defineProperty(document, "readyState", {
                configurable: true,
                get: function() { return "complete"; }
            });
        }, 0);
    }
    
    // Initialize when ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        setTimeout(init, 0);
    }
})();
</script>';
    }
}
