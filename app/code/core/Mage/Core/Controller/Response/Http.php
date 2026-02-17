<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HTTP Response class wrapping Symfony Response
 *
 * Provides compatibility layer for Mage_Core_Controller_Response_Http while using Symfony HttpFoundation
 */
class Mage_Core_Controller_Response_Http
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
     * Array of headers
     */
    protected array $_headers = [];

    /**
     * Array of raw headers
     */
    protected array $_headersRaw = [];

    /**
     * HTTP response code
     */
    protected int $_httpResponseCode = 200;

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

    public function __construct(array|SymfonyResponse|null $args = null)
    {
        // Handle both array (Mage factory) and SymfonyResponse (direct instantiation) arguments
        if ($args instanceof SymfonyResponse) {
            $this->symfonyResponse = $args;
            // Sync content and code
            $this->setBody($args->getContent() ?: '');
            $this->_httpResponseCode = $args->getStatusCode();
        } elseif (is_array($args) && isset($args[0]) && $args[0] instanceof SymfonyResponse) {
            $this->symfonyResponse = $args[0];
            $this->setBody($args[0]->getContent() ?: '');
            $this->_httpResponseCode = $args[0]->getStatusCode();
        } else {
            $this->symfonyResponse = new SymfonyResponse();
            // Set default protocol version to 1.1
            $this->symfonyResponse->setProtocolVersion('1.1');
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
        $name = $this->_normalizeHeader($name);

        if ($replace) {
            foreach ($this->_headers as $key => $header) {
                if ($name == $header['name']) {
                    unset($this->_headers[$key]);
                }
            }
        }

        $this->_headers[] = [
            'name' => $name,
            'value' => $value,
            'replace' => $replace,
        ];

        $this->symfonyResponse->headers->set($name, $value, $replace);

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
        $this->setHeader('Location', self::$_transportObject->getUrl(), true)
             ->setHttpResponseCode(self::$_transportObject->getCode());

        $this->symfonyResponse->setStatusCode(self::$_transportObject->getCode());
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
     * Return array of headers; see {@link $_headers} for format
     */
    public function getHeaders(): array
    {
        return $this->_headers;
    }

    /**
     * Clear headers
     */
    public function clearHeaders(): self
    {
        $this->_headers = [];
        $this->symfonyResponse->headers = new \Symfony\Component\HttpFoundation\ResponseHeaderBag();
        return $this;
    }

    /**
     * Clear header by name
     */
    public function clearHeader(string $name): self
    {
        $name = $this->_normalizeHeader($name);
        foreach ($this->_headers as $key => $header) {
            if ($name == $header['name']) {
                unset($this->_headers[$key]);
            }
        }
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
        if ((100 > $code) || (599 < $code)) {
            throw new Exception('Invalid HTTP response code');
        }

        $this->_httpResponseCode = $code;
        $this->symfonyResponse->setStatusCode($code);
        return $this;
    }

    /**
     * Retrieve HTTP response code
     */
    public function getHttpResponseCode(): int
    {
        return $this->_httpResponseCode;
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
     * Sends any headers specified. If an {@link setHttpResponseCode() HTTP response code}
     * has been specified, it is sent with the first header.
     */
    public function sendHeaders(): self
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

        $httpCodeSent = false;

        foreach ($this->_headersRaw as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header, true, $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header);
            }
        }

        foreach ($this->_headers as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header['name'] . ': ' . $header['value'], $header['replace'], $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        }

        if (!$httpCodeSent) {
            header('HTTP/1.1 ' . $this->_httpResponseCode);
        }

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
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the response object contain an exception with a given message?
     */
    public function hasExceptionOfMessage(string $message): bool
    {
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the response object contain an exception with a given code?
     */
    public function hasExceptionOfCode(int $code): bool
    {
        $code = (int) $code;
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                return true;
            }
        }

        return false;
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

        echo $this->outputBody();
    }

    /**
     * Magic __toString functionality
     */
    #[\Override]
    public function __toString(): string
    {
        ob_start();
        $this->sendResponse();
        return ob_get_clean();
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
        // Set expiry time
        if ($lifetime > 0) {
            $expire = time() + $lifetime;
        } else {
            $expire = 0;
        }

        // Build cookie header
        $cookieHeader = $name . '=' . urlencode($value);
        if ($expire > 0) {
            $cookieHeader .= '; expires=' . gmdate('D, d-M-Y H:i:s', $expire) . ' GMT';
        }
        if (!empty($path)) {
            $cookieHeader .= '; path=' . $path;
        }
        if (!empty($domain)) {
            $cookieHeader .= '; domain=' . $domain;
        }
        if ($secure) {
            $cookieHeader .= '; Secure';
        }
        if ($httponly) {
            $cookieHeader .= '; HttpOnly';
        }

        $this->setHeader('Set-Cookie', $cookieHeader, false);

        return $this;
    }

    /**
     * Clear cookie (set with past expiry)
     */
    public function clearCookie(string $name, string $path = '/', string|null $domain = null): self
    {
        // Set cookie with past expiry time
        $cookieHeader = $name . '=deleted';
        $cookieHeader .= '; expires=' . gmdate('D, d-M-Y H:i:s', time() - 3600) . ' GMT';
        if (!empty($path)) {
            $cookieHeader .= '; path=' . $path;
        }
        if (!empty($domain)) {
            $cookieHeader .= '; domain=' . $domain;
        }

        $this->setHeader('Set-Cookie', $cookieHeader, false);

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
