<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HTTP Response class wrapping Symfony Response
 *
 * Provides compatibility layer for Mage_Core_Controller_Response_Http while using Symfony HttpFoundation
 */
class Mage_Core_Controller_Response_Http implements \Stringable
{
    /**
     * Symfony Response instance
     */
    protected SymfonyResponse $symfonyResponse;

    /**
     * Transport object for observers to perform
     */
    protected static ?\Maho\DataObject $_transportObject = null;

    /**
     * Array of raw headers
     */
    protected array $_headersRaw = [];

    /**
     * Flag; is this response a redirect?
     */
    protected bool $_isRedirect = false;

    /**
     * Array of exceptions
     */
    protected array $_exceptions = [];

    /**
     * Body content
     */
    protected array $_body = [];

    /**
     * Body segment order
     */
    protected array $_bodyOrder = [];

    /**
     * Output callback
     */
    protected mixed $_outputCallback = null;

    /**
     * Exception thrown by action controller
     */
    public bool $headersSentThrowsException = false;

    /**
     * Whether headers have been emitted; Symfony sends with replace=false,
     * so a second emission would duplicate headers instead of replacing them
     */
    protected bool $_headersSent = false;

    public function __construct(array|SymfonyResponse|null $args = null)
    {
        // Handle both array (Mage factory) and SymfonyResponse (direct instantiation) arguments
        if ($args instanceof SymfonyResponse) {
            $this->symfonyResponse = $args;
            $this->setBody($args->getContent() ?: '');
        } elseif (is_array($args) && isset($args[0]) && $args[0] instanceof SymfonyResponse) {
            $this->symfonyResponse = $args[0];
            $this->setBody($args[0]->getContent() ?: '');
        } else {
            $this->symfonyResponse = new SymfonyResponse();
        }
    }

    /**
     * Get the Symfony Response instance (for internal use)
     */
    public function getSymfonyResponse(): SymfonyResponse
    {
        return $this->symfonyResponse;
    }

    /**
     * Set a header
     */
    public function setHeader(string $name, string $value, bool $replace = true): self
    {
        $this->canSendHeaders(true);
        $this->symfonyResponse->headers->set($this->_normalizeHeader($name), $value, $replace);
        return $this;
    }

    /**
     * Set redirect URL
     *
     * Sets Location header and response code. Forces replacement of any prior
     * redirects.
     */
    public function setRedirect(string $url, int $code = 302): self
    {
        /**
         * Use single transport object instance
         */
        if (self::$_transportObject === null) {
            self::$_transportObject = new \Maho\DataObject();
        }
        self::$_transportObject->setUrl($url);
        self::$_transportObject->setCode($code);
        Mage::dispatchEvent(
            'controller_response_redirect',
            ['response' => $this, 'transport' => self::$_transportObject],
        );

        $this->canSendHeaders(true);
        $code = self::$_transportObject->getCode();
        $this->setHeader('Location', self::$_transportObject->getUrl(), true)
             ->setHttpResponseCode($code);

        // Keep permanent redirects browser-cacheable; the header bag default
        // (no-cache, private) would force a re-request of the old URL every visit
        if ($code === 301 || $code === 308) {
            $this->setHeader('Cache-Control', 'max-age=86400', true);
        }

        $this->_isRedirect = true;

        return $this;
    }

    /**
     * Set redirect URL without exit
     */
    public function setRedirectUrl(string $url): self
    {
        $this->setHeader('Location', $url, true);
        $this->_isRedirect = true;
        return $this;
    }

    /**
     * Is this a redirect?
     */
    public function isRedirect(): bool
    {
        return $this->_isRedirect;
    }

    /**
     * Return headers as [['name' => ..., 'value' => ..., 'replace' => ...], ...]
     * (legacy M1 format, derived from the Symfony header bag)
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->symfonyResponse->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = [
                    'name' => $name,
                    'value' => $value,
                    'replace' => true,
                ];
            }
        }
        return $headers;
    }

    /**
     * Whether a header is present
     */
    public function hasHeader(string $name): bool
    {
        return $this->symfonyResponse->headers->has($name);
    }

    /**
     * Clear headers
     */
    public function clearHeaders(): self
    {
        $this->symfonyResponse->headers = new \Symfony\Component\HttpFoundation\ResponseHeaderBag();
        return $this;
    }

    /**
     * Clear header by name
     */
    public function clearHeader(string $name): self
    {
        $this->symfonyResponse->headers->remove($name);
        return $this;
    }

    /**
     * Set raw HTTP header
     */
    public function setRawHeader(string $value): self
    {
        $this->canSendHeaders(true);
        $this->_headersRaw[] = $value;
        return $this;
    }

    /**
     * Get all header values as an array
     */
    public function getRawHeaders(): array
    {
        return $this->_headersRaw;
    }

    /**
     * Clear all raw headers
     */
    public function clearRawHeaders(): self
    {
        $this->_headersRaw = [];
        return $this;
    }

    /**
     * Clear a specific raw header
     */
    public function clearRawHeader(string $headerRaw): self
    {
        $key = array_search($headerRaw, $this->_headersRaw);
        if ($key !== false) {
            unset($this->_headersRaw[$key]);
        }
        return $this;
    }

    /**
     * Clear all headers, normal and raw
     */
    public function clearAllHeaders(): self
    {
        return $this->clearHeaders()
                    ->clearRawHeaders();
    }

    /**
     * Set HTTP response code to use with headers
     */
    public function setHttpResponseCode(int $code): self
    {
        $this->symfonyResponse->setStatusCode($code);
        return $this;
    }

    /**
     * Retrieve HTTP response code
     */
    public function getHttpResponseCode(): int
    {
        return $this->symfonyResponse->getStatusCode();
    }

    /**
     * Can we send headers?
     */
    public function canSendHeaders(bool $throw = false): bool
    {
        $ok = headers_sent($file, $line);
        if ($ok && $throw && $this->headersSentThrowsException) {
            throw new Exception('Cannot send headers; headers already sent in ' . $file . ', line ' . $line);
        }

        return !$ok;
    }

    /**
     * Send all headers
     *
     * Emission is delegated to the wrapped Symfony response, so ResponseHeaderBag
     * defaults (conservative Cache-Control, Date) apply. The response is prepared
     * against the current request first (RFC compliance: Content-Type charset,
     * HEAD/304 body stripping, protocol version, secure cookies). Raw headers are
     * sent after preparation and suppress any same-named header from the bag.
     */
    public function sendHeaders(): self
    {
        if ($this->_headersSent) {
            return $this;
        }
        if (!$this->canSendHeaders()) {
            Mage::log('HEADERS ALREADY SENT: ' . mageDebugBacktrace(true, true, true));
            return $this;
        }

        $this->symfonyResponse->prepare(Mage::app()->getRequest()->getSymfonyRequest());

        foreach ($this->_headersRaw as $rawHeader) {
            header($rawHeader);
            if (($pos = strpos($rawHeader, ':')) !== false) {
                $name = trim(substr($rawHeader, 0, $pos));
                // Set-Cookie is multi-valued; removing it would wipe every bag cookie
                if (strcasecmp($name, 'Set-Cookie') !== 0) {
                    $this->symfonyResponse->headers->remove($name);
                }
            }
        }

        $this->symfonyResponse->sendHeaders();
        $this->_headersSent = true;

        return $this;
    }

    /**
     * Set body content
     */
    public function setBody(string $content, string|null $name = null): self
    {
        if (is_null($name)) {
            $name = 'default';
        }

        $this->_body[$name] = $content;
        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Append content to the body content
     */
    public function appendBody(string $content, string|null $name = null): self
    {
        if (is_null($name)) {
            $name = 'default';
        }

        if (isset($this->_body[$name])) {
            $this->_body[$name] .= $content;
        } else {
            $this->_body[$name] = $content;
        }

        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Prepend content to body
     */
    public function prependBody(string $content, string|null $name = null): self
    {
        if (is_null($name)) {
            $name = 'default';
        }

        if (!isset($this->_body[$name])) {
            $this->_body[$name] = $content;
        } else {
            $this->_body[$name] = $content . $this->_body[$name];
        }

        $this->symfonyResponse->setContent($this->outputBody());

        return $this;
    }

    /**
     * Clear body content
     */
    public function clearBody(string|null $name = null): self
    {
        if (!is_null($name)) {
            if (isset($this->_body[$name])) {
                unset($this->_body[$name]);
            }
        } else {
            $this->_body = [];
        }

        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Return the body content
     */
    public function getBody(bool|string $spec = false): string|array
    {
        if (false === $spec) {
            return $this->outputBody();
        }
        if (true === $spec) {
            return $this->_body;
        }
        return $this->_body[$spec] ?? '';
    }

    /**
     * Append content to a body segment
     */
    public function append(string $name, string $content): self
    {
        if (isset($this->_body[$name])) {
            $this->_body[$name] .= $content;
        } else {
            $this->_body[$name] = $content;
        }

        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Prepend content to a body segment
     */
    public function prepend(string $name, string $content): self
    {
        if (isset($this->_body[$name])) {
            $this->_body[$name] = $content . $this->_body[$name];
        } else {
            $this->_body[$name] = $content;
        }

        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Insert content before a named segment
     */
    public function insert(string $name, string $content, string|null $parent = null, bool $before = false): self
    {
        if (isset($this->_body[$name])) {
            $this->_body[$name] = $content;
        } else {
            $tmp = [];
            $inserted = false;

            foreach ($this->_body as $key => $value) {
                if ($key == $parent) {
                    if ($before) {
                        $tmp[$name] = $content;
                        $tmp[$key] = $value;
                    } else {
                        $tmp[$key] = $value;
                        $tmp[$name] = $content;
                    }
                    $inserted = true;
                } else {
                    $tmp[$key] = $value;
                }
            }

            if (!$inserted) {
                $tmp[$name] = $content;
            }

            $this->_body = $tmp;
        }

        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Set body segment output order
     */
    public function setOutputOrder(array $order): self
    {
        $this->_bodyOrder = $order;
        $this->symfonyResponse->setContent($this->outputBody());
        return $this;
    }

    /**
     * Set output callback
     */
    public function setOutputCallback(mixed $callback): self
    {
        $this->_outputCallback = $callback;
        return $this;
    }

    /**
     * Get output callback
     */
    public function getOutputCallback(): mixed
    {
        return $this->_outputCallback;
    }

    /**
     * Echo the body segments
     */
    public function outputBody(): string
    {
        $content = '';

        // If order is specified, use it
        if (!empty($this->_bodyOrder)) {
            foreach ($this->_bodyOrder as $key) {
                if (isset($this->_body[$key])) {
                    $content .= $this->_body[$key];
                }
            }
        } else {
            // Otherwise use natural order
            foreach ($this->_body as $segment) {
                $content .= $segment;
            }
        }

        return $content;
    }

    /**
     * Register an exception with the response
     */
    public function setException(Throwable $e): self
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    /**
     * Retrieve the exception stack
     */
    public function getException(): array
    {
        return $this->_exceptions;
    }

    /**
     * Retrieve the exception stack (alias)
     */
    public function getExceptions(): array
    {
        return $this->_exceptions;
    }

    /**
     * Has an exception been registered with the response? (alias for isException)
     */
    public function hasExceptions(): bool
    {
        return !empty($this->_exceptions);
    }

    /**
     * Has an exception been registered with the response?
     */
    public function isException(): bool
    {
        return !empty($this->_exceptions);
    }

    /**
     * Does the response object contain an exception of a given type?
     */
    public function hasExceptionOfType(string $type): bool
    {
        return array_any($this->_exceptions, fn($e) => $e instanceof $type);
    }

    /**
     * Does the response object contain an exception with a given message?
     */
    public function hasExceptionOfMessage(string $message): bool
    {
        return array_any($this->_exceptions, fn($e) => $message == $e->getMessage());
    }

    /**
     * Does the response object contain an exception with a given code?
     */
    public function hasExceptionOfCode(int $code): bool
    {
        $code = (int) $code;
        return array_any($this->_exceptions, fn($e) => $code == $e->getCode());
    }

    /**
     * Retrieve first exception of given type
     */
    public function getExceptionByType(string $type): false|Throwable
    {
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                return $e;
            }
        }

        return false;
    }

    /**
     * Retrieve first exception with a given message
     */
    public function getExceptionByMessage(string $message): false|Throwable
    {
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                return $e;
            }
        }

        return false;
    }

    /**
     * Retrieve first exception with a given code
     */
    public function getExceptionByCode(int $code): false|Throwable
    {
        $code = (int) $code;
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                return $e;
            }
        }

        return false;
    }

    /**
     * Send the response, including all headers
     */
    public function sendResponse(): void
    {
        Mage::dispatchEvent('http_response_send_before', ['response' => $this]);

        // Process body to defer JavaScript loading
        $body = $this->getBody();
        if ($this->shouldDeferJavaScript($body)) {
            $deferMode = $this->getJavaScriptDeferMode();
            $processedBody = $this->deferJavaScriptLoading($body, $deferMode);
            $this->setBody($processedBody);
        }

        $this->sendHeaders();
        $this->symfonyResponse->sendContent();
    }

    /**
     * Magic __toString functionality
     */
    #[\Override]
    public function __toString(): string
    {
        ob_start();
        $this->sendResponse();
        return (string) ob_get_clean();
    }

    /**
     * Normalize a header name
     */
    protected function _normalizeHeader(string $name): string
    {
        $filtered = str_replace(['-', '_'], ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);
        return $filtered;
    }

    /**
     * Set cookie
     */
    public function setCookie(
        string $name,
        string $value = '',
        int $lifetime = 0,
        string $path = '/',
        string|null $domain = null,
        bool $secure = false,
        bool $httponly = false,
    ): self {
        $this->symfonyResponse->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
            $name,
            $value,
            $lifetime > 0 ? time() + $lifetime : 0,
            $path ?: '/',
            $domain ?: null,
            $secure,
            $httponly,
            false,
            null,
        ));

        return $this;
    }

    /**
     * Clear cookie (set with past expiry)
     */
    public function clearCookie(string $name, string $path = '/', string|null $domain = null): self
    {
        $this->symfonyResponse->headers->clearCookie($name, $path ?: '/', $domain ?: null, false, false);
        return $this;
    }

    /**
     * Check if response has been sent
     */
    public function isSent(): bool
    {
        return headers_sent();
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
        $contentType = (string) $this->symfonyResponse->headers->get('Content-Type');

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
                    // Skip if contains our loader, is a speculation rules script, or has data-maho-nodefer
                    $shouldSkip = str_contains($matches[0], 'mahoLazyJs') ||
                                  str_contains($matches[0], 'type="speculationrules"') ||
                                  preg_match('/\sdata-maho-nodefer[\s>=]/i', $matches[0]);

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
        ) ?? $html;

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
