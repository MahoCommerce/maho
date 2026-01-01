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

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * HTTP Request class wrapping Symfony Request
 *
 * Provides compatibility layer for Mage_Core_Controller_Request_Http while using Symfony HttpFoundation
 */
class Mage_Core_Controller_Request_Http
{
    public const XML_NODE_DIRECT_FRONT_NAMES = 'global/request/direct_front_name';
    public const DEFAULT_HTTP_PORT = 80;
    public const DEFAULT_HTTPS_PORT = 443;
    public const SCHEME_HTTP = 'http';
    public const SCHEME_HTTPS = 'https';

    /**
     * Symfony Request instance
     */
    protected SymfonyRequest $symfonyRequest;

    /**
     * ORIGINAL_PATH_INFO
     */
    protected string $_originalPathInfo = '';
    protected ?string $_storeCode = null;
    protected string $_requestString = '';

    /**
     * Path info array used before applying rewrite from config
     */
    protected ?array $_rewritedPathInfo = null;
    protected ?string $_requestedRouteName = null;
    protected array $_routingInfo = [];
    protected ?string $_route = null;
    protected ?array $_directFrontNames = null;
    protected ?string $_controllerModule = null;

    /**
     * Straight request flag.
     * If flag is determined no additional logic is applicable
     */
    protected bool $_isStraight = false;

    /**
     * Request's original information before forward.
     */
    protected array $_beforeForwardInfo = [];

    /**
     * Flag for recognizing if request internally forwarded
     */
    protected bool $_internallyForwarded = false;

    /**
     * Has the action been dispatched?
     */
    protected bool $_dispatched = false;

    /**
     * Module/Controller/Action info
     */
    protected ?string $_module = null;
    protected ?string $_controller = null;
    protected ?string $_action = null;

    /**
     * Keys for retrieving MCA from params
     */
    protected string $_moduleKey = 'module';
    protected string $_controllerKey = 'controller';
    protected string $_actionKey = 'action';

    /**
     * Request parameters
     */
    protected array $_params = [];

    /**
     * Aliases
     */
    protected array $_aliases = [];

    /**
     * PATH_INFO
     */
    protected string $_pathInfo = '';

    /**
     * Base URL
     */
    protected ?string $_baseUrl = null;

    /**
     * Base path
     */
    protected ?string $_basePath = null;

    /**
     * REQUEST_URI
     */
    protected ?string $_requestUri = null;

    /**
     * Allowed parameter sources
     */
    protected array $_paramSources = ['_GET', '_POST'];

    /**
     * Raw request body
     */
    protected string|false|null $_rawBody = null;

    public function __construct(string|SymfonyRequest|null $uri = null)
    {
        // Accept either a SymfonyRequest object or create one from URI/globals
        if ($uri instanceof SymfonyRequest) {
            $this->symfonyRequest = $uri;
        } elseif ($uri !== null) {
            // Parse URI and create request
            $parsedUrl = parse_url($uri);
            $this->symfonyRequest = SymfonyRequest::create($uri);
        } else {
            $this->symfonyRequest = SymfonyRequest::createFromGlobals();
        }
    }

    /**
     * Get the Symfony Request instance (for internal use)
     */
    public function getSymfonyRequest(): SymfonyRequest
    {
        return $this->symfonyRequest;
    }

    // ========== Mage_Core_Controller_Request_Http Methods ==========

    public function getModuleName(): ?string
    {
        if ($this->_module === null) {
            $this->_module = $this->getParam($this->getModuleKey());
        }
        return $this->_module;
    }

    public function setModuleName(string|null $value): self
    {
        $this->_module = $value;
        return $this;
    }

    public function getControllerName(): ?string
    {
        if ($this->_controller === null) {
            $this->_controller = $this->getParam($this->getControllerKey());
        }
        return $this->_controller;
    }

    public function setControllerName(string|null $value): self
    {
        $this->_controller = $value;
        return $this;
    }

    public function getActionName(): ?string
    {
        if ($this->_action === null) {
            $this->_action = $this->getParam($this->getActionKey());
        }
        return $this->_action;
    }

    public function setActionName(string|null $value): self
    {
        $this->_action = $value;
        if ($value === null) {
            $this->setParam($this->getActionKey(), $value);
        }
        return $this;
    }

    public function getModuleKey(): string
    {
        return $this->_moduleKey;
    }

    public function setModuleKey(string $key): self
    {
        $this->_moduleKey = (string) $key;
        return $this;
    }

    public function getControllerKey(): string
    {
        return $this->_controllerKey;
    }

    public function setControllerKey(string $key): self
    {
        $this->_controllerKey = (string) $key;
        return $this;
    }

    public function getActionKey(): string
    {
        return $this->_actionKey;
    }

    public function setActionKey(string $key): self
    {
        $this->_actionKey = (string) $key;
        return $this;
    }

    public function getParam(string|int|null $key, mixed $default = null): mixed
    {
        // First check internal params
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        }

        // Convert key to string for Symfony parameter bags
        $stringKey = (string) $key;

        // Then check POST parameters
        if ($this->symfonyRequest->request->has($stringKey)) {
            // Use all() to support array values
            return $this->symfonyRequest->request->all()[$stringKey];
        }

        // Then check GET parameters
        if ($this->symfonyRequest->query->has($stringKey)) {
            // Use all() to support array values
            return $this->symfonyRequest->query->all()[$stringKey];
        }

        // Finally check route attributes
        if ($this->symfonyRequest->attributes->has($stringKey)) {
            return $this->symfonyRequest->attributes->get($stringKey);
        }

        return $default;
    }

    public function getUserParams(): array
    {
        return $this->_params;
    }

    public function getUserParam(string|int $key, mixed $default = null): mixed
    {
        return $this->_params[$key] ?? $default;
    }

    public function setParam(string|int $key, mixed $value): self
    {
        $this->_params[$key] = $value;
        return $this;
    }

    public function setParams(array $array): self
    {
        $this->_params = $this->_params + $array;
        return $this;
    }

    public function getParams(): array
    {
        $params = $this->_params;
        foreach ($this->_paramSources as $source) {
            if (isset($GLOBALS[$source]) && is_array($GLOBALS[$source])) {
                $params += $GLOBALS[$source];
            }
        }
        return $params;
    }

    public function clearParams(): self
    {
        $this->_params = [];
        return $this;
    }

    public function setDispatched(bool $flag = true): self
    {
        $this->_dispatched = (bool) $flag;
        return $this;
    }

    public function isDispatched(): bool
    {
        return $this->_dispatched;
    }

    // ========== Mage_Core_Controller_Request_Http Methods ==========

    public function __get(string $key): mixed
    {
        return $this->_params[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $_COOKIE[$key] ?? $_SERVER[$key] ?? $_ENV[$key] ?? null;
    }

    public function get(string $key): mixed
    {
        return $this->__get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        throw new Exception('Setting values via the overloading operator is prohibited; use setPost(), setQuery(), or setParam()');
    }

    public function set(string $key, mixed $value): void
    {
        throw new Exception('Setting values via set() is prohibited; use setPost(), setQuery(), or setParam()');
    }

    public function __isset(string $key): bool
    {
        return isset($this->_params[$key])
            || isset($_GET[$key])
            || isset($_POST[$key])
            || isset($_COOKIE[$key])
            || isset($_SERVER[$key])
            || isset($_ENV[$key]);
    }

    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    public function setQuery(array|string $spec, mixed $value = null): self
    {
        if (is_array($spec)) {
            $_GET = $spec;
            $this->symfonyRequest->query->replace($spec);
        } else {
            $_GET[$spec] = $value;
            $this->symfonyRequest->query->set($spec, $value);
        }
        return $this;
    }

    public function getQuery(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    public function setPost(array|string $spec, mixed $value = null): self
    {
        if (is_array($spec)) {
            $_POST = $spec;
            $this->symfonyRequest->request->replace($spec);
        } else {
            $_POST[$spec] = $value;
            $this->symfonyRequest->request->set($spec, $value);
        }
        return $this;
    }

    public function getPost(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    public function getCookie(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->symfonyRequest->cookies->all();
        }
        if (!$this->symfonyRequest->cookies->has($key)) {
            return $default;
        }
        return $this->symfonyRequest->cookies->get($key);
    }

    public function getServer(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_SERVER;
        }
        return $_SERVER[$key] ?? $default;
    }

    public function getEnv(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_ENV;
        }
        return $_ENV[$key] ?? $default;
    }

    public function setRequestUri(string|null $requestUri = null): self
    {
        if ($requestUri === null) {
            $requestUri = $this->symfonyRequest->getRequestUri();
        }
        $this->_requestUri = $requestUri;
        return $this;
    }

    public function getRequestUri(): ?string
    {
        if ($this->_requestUri === null) {
            $this->_requestUri = $this->symfonyRequest->getRequestUri();
        }
        return $this->_requestUri;
    }

    public function setBaseUrl(string|null $baseUrl = null): self
    {
        if ($baseUrl === null) {
            $baseUrl = $this->symfonyRequest->getBaseUrl();
        }
        $this->_baseUrl = $baseUrl;
        return $this;
    }

    public function getBaseUrl(bool $raw = false): string
    {
        if ($this->_baseUrl === null) {
            $this->_baseUrl = $this->symfonyRequest->getBaseUrl();
        }
        $url = $this->_baseUrl;
        $url = str_replace('\\', '/', $url);
        return $url;
    }

    public function setBasePath(string|null $basePath = null): self
    {
        if ($basePath === null) {
            $basePath = $this->symfonyRequest->getBasePath();
        }
        $this->_basePath = $basePath;
        return $this;
    }

    public function getBasePath(): string
    {
        if ($this->_basePath === null) {
            $this->_basePath = $this->symfonyRequest->getBasePath();
        }
        $path = $this->_basePath;
        if (empty($path)) {
            $path = '/';
        } else {
            $path = str_replace('\\', '/', $path);
        }
        return $path;
    }

    public function setPathInfo(string|null $pathInfo = null): self
    {
        if ($pathInfo === null) {
            $requestUri = $this->getRequestUri();
            if ($requestUri === null) {
                return $this;
            }

            // Remove the query string from REQUEST_URI
            $pos = strpos($requestUri, '?');
            if ($pos) {
                $requestUri = substr($requestUri, 0, $pos);
            }

            $baseUrl = $this->getBaseUrl();
            $pathInfo = substr($requestUri, strlen($baseUrl));

            if ($baseUrl && $pathInfo && (stripos($pathInfo, '/') !== 0)) {
                $pathInfo = '';
                $this->setActionName('noRoute');
            } elseif ($baseUrl && !$pathInfo) {
                $pathInfo = '';
            } elseif (!$baseUrl) {
                $pathInfo = $requestUri;
            }

            if ($this->_canBeStoreCodeInUrl()) {
                $pathParts = explode('/', ltrim($pathInfo, '/'), 2);
                $storeCode = $pathParts[0];

                if (!$this->isDirectAccessFrontendName($storeCode)) {
                    $stores = Mage::app()->getStores(true, true);
                    if ($storeCode !== '' && isset($stores[$storeCode])) {
                        Mage::app()->setCurrentStore($storeCode);
                        $pathInfo = '/' . ($pathParts[1] ?? '');
                    } elseif ($storeCode !== '') {
                        $this->setActionName('noRoute');
                    }
                }
            }

            $this->_originalPathInfo = (string) $pathInfo;
            $this->_requestString = $pathInfo . ($pos !== false ? substr($requestUri, $pos) : '');
        }

        $this->_pathInfo = (string) $pathInfo;
        return $this;
    }

    public function getPathInfo(): string
    {
        if ($this->_pathInfo === '') {
            $this->setPathInfo();
        }
        return $this->_pathInfo;
    }

    public function setParamSources(array $paramSources = []): self
    {
        $this->_paramSources = $paramSources;
        return $this;
    }

    public function getParamSources(): array
    {
        return $this->_paramSources;
    }

    public function setAlias(string $name, string $target): self
    {
        $this->_aliases[$name] = $target;
        return $this;
    }

    public function getAlias(string $name): ?string
    {
        $aliases = $this->getAliases();
        return $aliases[$name] ?? null;
    }

    public function getAliases(): array
    {
        return $this->_routingInfo['aliases'] ?? $this->_aliases;
    }

    public function getMethod(): string
    {
        return $this->symfonyRequest->getMethod();
    }

    public function isPost(): bool
    {
        return $this->symfonyRequest->isMethod('POST');
    }

    public function isGet(): bool
    {
        return $this->symfonyRequest->isMethod('GET');
    }

    public function isPut(): bool
    {
        return $this->symfonyRequest->isMethod('PUT');
    }

    public function isDelete(): bool
    {
        return $this->symfonyRequest->isMethod('DELETE');
    }

    public function isHead(): bool
    {
        return $this->symfonyRequest->isMethod('HEAD');
    }

    public function isOptions(): bool
    {
        return $this->symfonyRequest->isMethod('OPTIONS');
    }

    public function isPatch(): bool
    {
        return $this->symfonyRequest->isMethod('PATCH');
    }

    public function isXmlHttpRequest(): bool
    {
        return $this->symfonyRequest->isXmlHttpRequest();
    }

    public function isFlashRequest(): bool
    {
        $header = strtolower($this->getHeader('USER_AGENT'));
        return str_contains($header, ' flash');
    }

    public function isSecure(): bool
    {
        return $this->symfonyRequest->isSecure();
    }

    public function getRawBody(): string|false
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = $this->symfonyRequest->getContent();
        }
        return $this->_rawBody;
    }

    public function getHeader(string $header): string|false
    {
        // First try to get from Symfony headers object
        $value = $this->symfonyRequest->headers->get($header);
        if ($value !== null) {
            return $value;
        }

        // Fall back to server variables for backwards compatibility
        $header = str_replace('-', '_', strtoupper($header));
        if (!str_starts_with($header, 'HTTP_')) {
            $header = 'HTTP_' . $header;
        }
        return $this->symfonyRequest->server->get($header, false);
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->symfonyRequest->headers->all();
    }

    /**
     * Get host from request
     */
    public function getHost(): string
    {
        return $this->symfonyRequest->getHost();
    }

    public function getScheme(): string
    {
        return $this->getServer('HTTPS') == 'on'
          || $this->getServer('HTTP_X_FORWARDED_PROTO') == 'https'
          || (Mage::isInstalled() && Mage::app()->isCurrentlySecure()) ?
            self::SCHEME_HTTPS :
            self::SCHEME_HTTP;
    }

    public function getHttpHost(bool $trimPort = true): false|string
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }
        $host = $_SERVER['HTTP_HOST'];
        if ($trimPort) {
            $hostParts = explode(':', $_SERVER['HTTP_HOST']);
            $host = $hostParts[0];
        }

        if (str_contains($host, ',') || str_contains($host, ';')) {
            $response = new Mage_Core_Controller_Response_Http();
            $response->setHttpResponseCode(400)->sendHeaders();
            exit();
        }

        return $host;
    }

    public function getClientIp(bool $checkProxy = true): ?string
    {
        return $this->symfonyRequest->getClientIp();
    }

    // ========== Maho-specific Methods ==========

    /**
     * Set original path info
     */
    public function setOriginalPathInfo(string $pathInfo): self
    {
        $this->_originalPathInfo = $pathInfo;
        return $this;
    }

    public function getOriginalPathInfo(): string
    {
        if (empty($this->_originalPathInfo)) {
            $this->setPathInfo();
        }
        return $this->_originalPathInfo;
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    /**
     * Set store code from path
     */
    public function setStoreCodeFromPath(string $storeCode): self
    {
        $this->_storeCode = $storeCode;
        return $this;
    }

    public function getStoreCodeFromPath(): ?string
    {
        if (!$this->_storeCode) {
            // get store view code
            if ($this->_canBeStoreCodeInUrl()) {
                $p = explode('/', trim($this->getPathInfo(), '/'));
                $storeCode = $p[0];

                $stores = Mage::app()->getStores(true, true);

                if ($storeCode !== '' && isset($stores[$storeCode])) {
                    array_shift($p);
                    $this->setPathInfo(implode('/', $p));
                    $this->_storeCode = $storeCode;
                    Mage::app()->setCurrentStore($storeCode);
                } else {
                    $this->_storeCode = Mage::app()->getStore()->getCode();
                }
            } else {
                $this->_storeCode = Mage::app()->getStore()->getCode();
            }
        }
        return $this->_storeCode;
    }

    /**
     * Specify new path info
     * It happen when occur rewrite based on configuration
     */
    public function rewritePathInfo(string $pathInfo): self
    {
        if (($pathInfo != $this->getPathInfo()) && ($this->_rewritedPathInfo === null)) {
            $this->_rewritedPathInfo = explode('/', trim($this->getPathInfo(), '/'));
        }
        $this->setPathInfo($pathInfo);
        return $this;
    }

    /**
     * Check if can be store code as part of url
     */
    protected function _canBeStoreCodeInUrl(): bool
    {
        return Mage::isInstalled() && Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL);
    }

    /**
     * Check if code declared as direct access frontend name
     * this mean what this url can be used without store code
     */
    public function isDirectAccessFrontendName(string $code): bool
    {
        $names = $this->getDirectFrontNames();
        return isset($names[$code]);
    }

    /**
     * Get list of front names available with access without store code
     */
    public function getDirectFrontNames(): array
    {
        if (is_null($this->_directFrontNames)) {
            $names = Mage::getConfig()->getNode(self::XML_NODE_DIRECT_FRONT_NAMES);
            if ($names) {
                $this->_directFrontNames = $names->asArray();
            } else {
                return [];
            }
        }
        return $this->_directFrontNames;
    }

    public function getOriginalRequest(): self
    {
        $request = new self();
        $request->setPathInfo($this->getOriginalPathInfo());
        return $request;
    }

    public function getRequestString(): string
    {
        return $this->_requestString;
    }

    public function setRouteName(string $route): self
    {
        $this->_route = $route;
        $router = Mage::app()->getFrontController()->getRouterByRoute($route);
        if (!$router) {
            return $this;
        }
        $module = $router->getFrontNameByRoute($route);
        if ($module) {
            $this->setModuleName($module);
        }
        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->_route;
    }

    /**
     * Specify module name where was found currently used controller
     */
    public function setControllerModule(string $module): self
    {
        $this->_controllerModule = $module;
        return $this;
    }

    /**
     * Get module name of currently used controller
     */
    public function getControllerModule(): ?string
    {
        return $this->_controllerModule;
    }

    /**
     * Get route name used in request (ignore rewrite)
     */
    public function getRequestedRouteName(): ?string
    {
        if (isset($this->_routingInfo['requested_route'])) {
            return $this->_routingInfo['requested_route'];
        }
        if ($this->_requestedRouteName === null) {
            if ($this->_rewritedPathInfo !== null && isset($this->_rewritedPathInfo[0])) {
                $fronName = $this->_rewritedPathInfo[0];
                $router = Mage::app()->getFrontController()->getRouterByFrontName($fronName);
                $this->_requestedRouteName = $router->getRouteByFrontName($fronName);
            } else {
                // no rewritten path found, use default route name
                return $this->getRouteName();
            }
        }
        return $this->_requestedRouteName;
    }

    /**
     * Get controller name used in request (ignore rewrite)
     */
    public function getRequestedControllerName(): ?string
    {
        if (isset($this->_routingInfo['requested_controller'])) {
            return $this->_routingInfo['requested_controller'];
        }
        if (($this->_rewritedPathInfo !== null) && isset($this->_rewritedPathInfo[1])) {
            return $this->_rewritedPathInfo[1];
        }
        return $this->getControllerName();
    }

    /**
     * Get action name used in request (ignore rewrite)
     */
    public function getRequestedActionName(): ?string
    {
        if (isset($this->_routingInfo['requested_action'])) {
            return $this->_routingInfo['requested_action'];
        }
        if (($this->_rewritedPathInfo !== null) && isset($this->_rewritedPathInfo[2])) {
            return $this->_rewritedPathInfo[2];
        }
        return $this->getActionName();
    }

    /**
     * Set routing info data
     */
    public function setRoutingInfo(array $data): self
    {
        $this->_routingInfo = $data;
        return $this;
    }

    /**
     * Get routing info
     */
    public function getRoutingInfo(): array
    {
        return $this->_routingInfo;
    }

    /**
     * Collect properties changed by _forward in protected storage
     * before _forward was called first time.
     */
    public function initForward(): self
    {
        if (empty($this->_beforeForwardInfo)) {
            $this->_beforeForwardInfo = [
                'params' => $this->getParams(),
                'action_name' => $this->getActionName(),
                'controller_name' => $this->getControllerName(),
                'module_name' => $this->getModuleName(),
            ];
        }

        return $this;
    }

    /**
     * Set before forward info
     */
    public function setBeforeForwardInfo(array $info): self
    {
        $this->_beforeForwardInfo = $info;
        return $this;
    }

    /**
     * Retrieve property's value which was before _forward call.
     * If property was not changed during _forward call null will be returned.
     * If passed name will be null whole state array will be returned.
     */
    public function getBeforeForwardInfo(string|null $name = null): array|string|null
    {
        if (is_null($name)) {
            return $this->_beforeForwardInfo;
        }

        return $this->_beforeForwardInfo[$name] ?? null;
    }

    /**
     * Set _isStraight flag value
     */
    public function setIsStraight(bool $flag): self
    {
        $this->_isStraight = $flag;
        return $this;
    }

    /**
     * Specify/get _isStraight flag value
     */
    public function isStraight(bool|null $flag = null): bool
    {
        if ($flag !== null) {
            $this->_isStraight = $flag;
        }
        return $this->_isStraight;
    }

    /**
     * Check is Request from AJAX
     */
    public function isAjax(): bool
    {
        if ($this->isXmlHttpRequest()) {
            return true;
        }
        if ($this->getParam('ajax') || $this->getParam('isAjax')) {
            return true;
        }
        return false;
    }

    /**
     * Define that request was forwarded internally
     */
    public function setInternallyForwarded(bool $flag = true): self
    {
        $this->_internallyForwarded = (bool) $flag;
        return $this;
    }

    /**
     * Checks if request was forwarded internally
     */
    public function getInternallyForwarded(): bool
    {
        return $this->_internallyForwarded;
    }
}
