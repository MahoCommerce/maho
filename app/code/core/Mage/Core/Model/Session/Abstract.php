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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method $this unsErrorMessage()
 * @method string getSuccessMessage()
 * @method $this setSuccessMessage(string $value)
 * @method $this unsSuccessMessage()
 * @method $this setMessages(Mage_Core_Model_Abstract|Mage_Core_Model_Message_Collection $value)
 * @method bool|null getSkipEmptySessionCheck()
 * @method $this setSkipEmptySessionCheck(bool $flag)
 */
class Mage_Core_Model_Session_Abstract extends \Maho\DataObject
{
    public const REGISTRY_KEY                          = 'symfony_session';

    public const VALIDATOR_KEY                         = '_session_validator_data';
    public const VALIDATOR_HTTP_USER_AGENT_KEY         = 'http_user_agent';
    public const VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    = 'http_x_forwarded_for';
    public const VALIDATOR_HTTP_VIA_KEY                = 'http_via';
    public const VALIDATOR_REMOTE_ADDR_KEY             = 'remote_addr';
    public const VALIDATOR_PASSWORD_CREATE_TIMESTAMP   = 'password_create_timestamp';
    public const SECURE_COOKIE_CHECK_KEY               = '_secure_cookie_check';

    public const XML_PATH_COOKIE_DOMAIN        = 'web/cookie/cookie_domain';
    public const XML_PATH_COOKIE_PATH          = 'web/cookie/cookie_path';
    public const XML_PATH_COOKIE_LIFETIME      = 'web/cookie/cookie_lifetime';
    public const XML_NODE_SESSION_SAVE         = 'global/session_save';
    public const XML_NODE_SESSION_SAVE_PATH    = 'global/session_save_path';

    public const XML_PATH_USE_REMOTE_ADDR      = 'web/session/use_remote_addr';
    public const XML_PATH_USE_HTTP_VIA         = 'web/session/use_http_via';
    public const XML_PATH_USE_X_FORWARDED      = 'web/session/use_http_x_forwarded_for';
    public const XML_PATH_USE_USER_AGENT       = 'web/session/use_http_user_agent';
    public const XML_PATH_USE_FRONTEND_SID     = 'web/session/use_frontend_sid';

    public const XML_NODE_USET_AGENT_SKIP      = 'global/session/validation/http_user_agent_skip';

    public const SESSION_ID_QUERY_PARAM        = 'SID';

    /** @var bool Flag true if session validator data has already been evaluated */
    protected static bool $isValidated = false;

    /**
     * Map of session enabled hosts
     * @example ['host.name' => true]
     */
    protected array $_sessionHosts = [];

    /**
     * URL host cache
     */
    protected static array $_urlHostCache = [];

    /**
     * Encrypted session id cache
     *
     * @var string
     */
    protected static $_encryptedSessionId;

    /**
     * Skip session id flag
     *
     * @var bool
     */
    protected $_skipSessionIdFlag   = false;

    /**
     * Return the symfony session instance from the registry
     *
     * This instance is shared across all session classes created during a request.
     * For example: core, customer, checkout, admin, adminhtml, etc.
     */
    private function getSymfonySession(): ?Session
    {
        return Mage::registry(self::REGISTRY_KEY);
    }

    /**
     * Create Symfony session with proper storage handler
     */
    private function createSymfonySession(string $sessionName): Session
    {
        $handler = $this->createSessionHandler();

        // Get session lifetime from Maho configuration
        $adminLifetime = (int) Mage::getStoreConfig('admin/security/session_cookie_lifetime');
        $frontendLifetime = (int) Mage::getStoreConfig('web/cookie/cookie_lifetime');
        $sessionLifetime = max($adminLifetime, $frontendLifetime, 86400);

        $storage = new NativeSessionStorage(
            [
                'name' => $sessionName,
                'use_cookies' => false,
                'gc_probability' => '0',
            ],
            $handler,
            // Use Symfony's default MetadataBag - no custom one needed!
        );

        $session = new Session($storage);
        Mage::register(self::REGISTRY_KEY, $session);
        return $session;
    }

    /**
     * Create appropriate session handler based on configuration
     */
    private function createSessionHandler(): \SessionHandlerInterface
    {
        $method = $this->getSessionSaveMethod();
        return match ($method) {
            'redis' => $this->createRedisSessionHandler(),
            default => $this->createFileSessionHandler(),
        };
    }

    /**
     * Create Redis session handler using Symfony's RedisSessionHandler
     */
    private function createRedisSessionHandler(): \SessionHandlerInterface
    {
        $redisConfig = Mage::getConfig()->getNode('global/redis_session');
        if (!$redisConfig) {
            throw new Exception('Redis session configuration not found in redis_session');
        }

        $dsn = (string) $redisConfig->dsn;
        if (!$dsn) {
            throw new Exception('Redis DSN is required in redis_session/dsn. Format: redis://[password@]host[:port][/database]');
        }

        $options = [];

        // Set prefix option if configured
        if ($prefix = (string) $redisConfig->key_prefix) {
            $options['prefix'] = $prefix;
        }

        $redis = RedisAdapter::createConnection($dsn);
        return new RedisSessionHandler($redis, $options);
    }

    private function createFileSessionHandler(): \SessionHandlerInterface
    {
        $savePath = $this->getSessionSavePath();
        return new NativeFileSessionHandler($savePath);
    }

    /**
     * Migrate legacy cookies for backward compatibility
     */
    private function migrateLegacyCookies(string $sessionName, Mage_Core_Model_Cookie $cookie): void
    {
        if (!$cookie->get($sessionName) && $sessionName === Mage_Core_Controller_Front_Action::SESSION_NAMESPACE) {
            foreach (Mage_Core_Controller_Front_Action::SESSION_LEGACY_NAMESPACES as $namespace) {
                if ($cookie->get($namespace)) {
                    $_COOKIE[$sessionName] = $cookie->get($namespace);
                    $cookie->delete($namespace);
                    break;
                }
            }
        }
    }

    /**
     * Configure and start session
     * @throws Mage_Core_Model_Store_Exception
     */
    public function start(?string $sessionName = null): self
    {
        if ($this->getSymfonySession() !== null && !$this->getSkipEmptySessionCheck()) {
            return $this;
        }

        // Do not start a session if no sessionName was provided
        if (empty($sessionName)) {
            return $this;
        }

        // Create Symfony session instance
        $symfonySession = $this->createSymfonySession($sessionName);

        $cookie = $this->getCookie();

        // Migrate old cookie from (om_)frontend => maho_session
        $this->migrateLegacyCookies($sessionName, $cookie);

        // Set the session name to maho_session, maho_admin_session, etc
        $this->setSessionName($sessionName);

        // Call any custom logic in child classes for setting the session id
        // I.e. Checking the SID query param to enable switching between hosts
        $this->setSessionId();

        // If we still do not have a session id, then read from the cookie value
        // Otherwise, we will be starting a new session.
        if (empty($this->getSessionId()) && is_string($cookie->get($sessionName))) {
            $this->setSessionId($cookie->get($sessionName));
        }

        \Maho\Profiler::start(__METHOD__ . '/start');

        // Start session using modern Symfony approach
        $symfonySession->start();

        // Secure cookie check to prevent MITM attack
        if (Mage::app()->getFrontController()->getRequest()->isSecure() && !$cookie->isSecure()) {
            $secureCookieName = $this->getSessionName() . '_cid';
            $secureCookieValue = $cookie->get($secureCookieName);

            // Migrate old cookie from (om_)frontend_cid => maho_session_cid
            if (!$secureCookieValue && $sessionName === Mage_Core_Controller_Front_Action::SESSION_NAMESPACE) {
                foreach (Mage_Core_Controller_Front_Action::SESSION_LEGACY_NAMESPACES as $namespace) {
                    if ($cookie->get($namespace . '_cid')) {
                        $secureCookieValue = $cookie->get($namespace . '_cid');
                        $_COOKIE[$sessionName] = $secureCookieValue;
                        $cookie->delete($namespace . '_cid');
                        break;
                    }
                }
            }

            if (!isset($_SESSION[self::SECURE_COOKIE_CHECK_KEY])) {
                // Secure cookie check value not in session yet
                $secureCookieValue = Mage::helper('core')->getRandomString(16);
                $_SESSION[self::SECURE_COOKIE_CHECK_KEY] = md5($secureCookieValue);
            } elseif (!is_string($secureCookieValue) || $_SESSION[self::SECURE_COOKIE_CHECK_KEY] !== md5($secureCookieValue)) {
                // Secure cookie check value is invalid, regenerate session
                session_regenerate_id(false);
                $sessionHosts = $this->getSessionHosts();
                $currentCookieDomain = $cookie->getDomain();
                foreach (array_keys($sessionHosts) as $host) {
                    // Delete cookies with the same name for parent domains
                    if (strpos($currentCookieDomain, $host) > 0) {
                        $cookie->delete($this->getSessionName(), null, $host);
                    }
                }
                unset($secureCookieValue);
                session_unset();
            }
        }

        // Observers can change settings of the cookie such as lifetime, regenerate the session id, etc
        Mage::dispatchEvent('session_before_renew_cookie', ['cookie' => $cookie, 'session_name' => $sessionName]);

        // Set or renew regular session cookie
        $this->setSessionCookie();

        // Set or renew secure cookie if needed
        if (isset($secureCookieName) && isset($secureCookieValue)) {
            $cookie->set($secureCookieName, $secureCookieValue, null, null, null, true, true);
        }

        \Maho\Profiler::stop(__METHOD__ . '/start');

        return $this;
    }

    public function setSessionCookie(): self
    {
        $mahoCookie = $this->getCookie();
        $sessionName = $this->getSessionName();
        $sessionId = $this->getSessionId();

        $mahoCookie->set($sessionName, $sessionId);

        return $this;
    }

    /**
     * Retrieve cookie object
     */
    public function getCookie(): Mage_Core_Model_Cookie
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Init session with namespace
     */
    public function init(string $namespace, ?string $sessionName = null): self
    {
        if ($this->getSymfonySession() === null) {
            $this->start($sessionName);
        }

        // Initialize $_SESSION namespace
        if (!isset($_SESSION[$namespace])) {
            $_SESSION[$namespace] = [];
        }

        $this->_data = &$_SESSION[$namespace];

        $this->validate();
        $this->addHost(true);

        return $this;
    }

    /**
     * Additional get data with clear mode
     *
     * @param string $key
     * @param mixed $index For compatibility with parent; when bool, acts as clear flag
     * @return mixed
     */
    #[\Override]
    public function getData($key = '', $index = null)
    {
        // If $index is a boolean, treat it as the clear flag for backward compatibility
        if (is_bool($index) && $index && isset($this->_data[$key])) {
            $data = parent::getData($key);
            unset($this->_data[$key]);
            return $data;
        }

        return parent::getData($key, $index);
    }

    /**
     * @return false|string
     */
    public function getSessionId()
    {
        return $this->getSymfonySession()->getId();
    }

    public function getSessionName(): string
    {
        return $this->getSymfonySession()->getName();
    }

    public function setSessionName(string $name): self
    {
        if (!empty($name)) {
            $this->getSymfonySession()->setName($name);
        }
        return $this;
    }

    /**
     * Unset all data
     */
    public function unsetAll(): self
    {
        $this->unsetData();
        return $this;
    }

    /**
     * Alias for unsetAll
     */
    public function clear(): self
    {
        return $this->unsetAll();
    }

    public function regenerateSessionId(): self
    {
        if ($this->getSymfonySession()->migrate(true)) {
            $this->setSessionCookie();
        }
        return $this;
    }

    public function getCookieDomain(): string
    {
        return $this->getCookie()->getDomain();
    }

    public function getCookiePath(): string
    {
        return $this->getCookie()->getPath();
    }

    public function getCookieLifetime(): int
    {
        return $this->getCookie()->getLifetime();
    }

    /**
     * Use REMOTE_ADDR in validator key
     */
    public function useValidateRemoteAddr(): bool
    {
        $use = Mage::getStoreConfig(self::XML_PATH_USE_REMOTE_ADDR);
        if (is_null($use)) {
            return true;
        }
        return (bool) $use;
    }

    /**
     * Use HTTP_VIA in validator key
     */
    public function useValidateHttpVia(): bool
    {
        $use = Mage::getStoreConfig(self::XML_PATH_USE_HTTP_VIA);
        if (is_null($use)) {
            return true;
        }
        return (bool) $use;
    }

    /**
     * Use HTTP_X_FORWARDED_FOR in validator key
     */
    public function useValidateHttpXForwardedFor(): bool
    {
        $use = Mage::getStoreConfig(self::XML_PATH_USE_X_FORWARDED);
        if (is_null($use)) {
            return true;
        }
        return (bool) $use;
    }

    /**
     * Use HTTP_USER_AGENT in validator key
     */
    public function useValidateHttpUserAgent(): bool
    {
        $use = Mage::getStoreConfig(self::XML_PATH_USE_USER_AGENT);
        if (is_null($use)) {
            return true;
        }
        return (bool) $use;
    }

    /**
     * Password creation timestamp must not be newer than last session renewal.
     * Classes that extend from this may turn that off if they need to not check this.
     * Like some sort of API session that doesn't use passwords and so sessions shouldn't expire.
     */
    public function useValidateSessionPasswordTimestamp(): bool
    {
        return true;
    }

    /**
     * Check whether SID can be used for session initialization
     * Admin area will always have this feature enabled
     */
    public function useSid(): bool
    {
        return Mage::app()->getStore()->isAdmin() || Mage::getStoreConfig(self::XML_PATH_USE_FRONTEND_SID);
    }

    /**
     * Retrieve skip User Agent validation strings (Flash etc)
     */
    public function getValidateHttpUserAgentSkip(): array
    {
        $userAgents = [];
        $skip = Mage::getConfig()->getNode(self::XML_NODE_USET_AGENT_SKIP);
        foreach ($skip->children() as $userAgent) {
            $userAgents[] = (string) $userAgent;
        }
        return $userAgents;
    }

    /**
     * Retrieve messages from session
     */
    public function getMessages(bool $clear = false): Mage_Core_Model_Message_Collection
    {
        if (!$this->getData('messages')) {
            $this->setMessages(Mage::getModel('core/message_collection'));
        }

        if ($clear) {
            $messages = clone $this->getData('messages');
            $this->getData('messages')->clear();
            Mage::dispatchEvent('core_session_abstract_clear_messages');
            return $messages;
        }
        return $this->getData('messages');
    }

    /**
     * Not Mage exception handling
     */
    public function addException(Exception $exception, string $alternativeText): self
    {
        Mage::logException($exception);
        $this->addError($alternativeText);
        return $this;
    }

    /**
     * Adding new message to message collection
     */
    public function addMessage(Mage_Core_Model_Message_Abstract $message): self
    {
        $this->getMessages()->add($message);
        Mage::dispatchEvent('core_session_abstract_add_message');
        return $this;
    }

    /**
     * Adding new error message
     */
    public function addError(string $message): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->error($message));
        return $this;
    }

    /**
     * Adding new warning message
     */
    public function addWarning(string $message): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->warning($message));
        return $this;
    }

    /**
     * Adding new notice message
     */
    public function addNotice(string $message): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->notice($message));
        return $this;
    }

    /**
     * Adding new success message
     */
    public function addSuccess(string $message): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->success($message));
        return $this;
    }

    /**
     * Adding messages array to message collection
     */
    public function addMessages(array $messages): self
    {
        foreach ($messages as $message) {
            $this->addMessage($message);
        }
        return $this;
    }

    /**
     * Adds messages array to message collection, but doesn't add duplicates to it
     *
     * @param   array|string|Mage_Core_Model_Message_Abstract $messages
     * @return  $this
     */
    public function addUniqueMessages($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        if (!$messages) {
            return $this;
        }

        $messagesAlready = [];
        $items = $this->getMessages()->getItems();
        foreach ($items as $item) {
            if ($item instanceof Mage_Core_Model_Message_Abstract) {
                $text = $item->getText();
            } elseif (is_string($item)) {
                $text = $item;
            } else {
                continue; // Some unknown object, do not put it in already existing messages
            }
            $messagesAlready[$text] = true;
        }

        foreach ($messages as $message) {
            if ($message instanceof Mage_Core_Model_Message_Abstract) {
                $text = $message->getText();
            } elseif (is_string($message)) {
                $text = $message;
            } else {
                $text = null; // Some unknown object, add it anyway
            }

            // Check for duplication
            if ($text !== null) {
                if (isset($messagesAlready[$text])) {
                    continue;
                }
                $messagesAlready[$text] = true;
            }
            $this->addMessage($message);
        }

        return $this;
    }

    /**
     * Set custom session id
     */
    public function setSessionId(?string $id = null): self
    {
        if (is_null($id) && $this->useSid()) {
            $queryParam = $this->getSessionIdQueryParam();
            if (isset($_GET[$queryParam]) && Mage::getSingleton('core/url')->isOwnOriginUrl()) {
                $id = $_GET[$queryParam];
            }
        }

        if (!is_null($id) && preg_match('#^[0-9a-zA-Z,-]+$#', $id)) {
            $this->getSymfonySession()->setId($id);
        }

        $this->addHost(true);
        return $this;
    }

    /**
     * Get encrypted session identifier.
     * No reason use crypt key for session id encryption, we can use session identifier as is.
     */
    public function getEncryptedSessionId(): string
    {
        if (!self::$_encryptedSessionId) {
            self::$_encryptedSessionId = $this->getSessionId();
        }
        return self::$_encryptedSessionId;
    }

    public function getSessionIdQueryParam(): string
    {
        $sessionName = $this->getSessionName();
        if ($sessionName && $queryParam = (string) Mage::getConfig()->getNode($sessionName . '/session/query_param')) {
            return $queryParam;
        }
        return self::SESSION_ID_QUERY_PARAM;
    }

    /**
     * Set skip flag if need skip generating of _GET session_id_key param
     */
    public function setSkipSessionIdFlag(bool $flag): self
    {
        $this->_skipSessionIdFlag = $flag;
        return $this;
    }

    /**
     * Retrieve session id skip flag
     */
    public function getSkipSessionIdFlag(): bool
    {
        return $this->_skipSessionIdFlag;
    }

    /**
     * If session cookie is not applicable due to host or path mismatch - add session id to query
     *
     * @param string $urlHost can be host or url
     * @return string {session_id_key}={session_id_encrypted}
     */
    public function getSessionIdForHost(string $urlHost): string
    {
        if ($this->getSkipSessionIdFlag() === true) {
            return '';
        }

        $httpHost = Mage::app()->getFrontController()->getRequest()->getHttpHost();
        if (!$httpHost) {
            return '';
        }

        $urlHostArr = explode('/', $urlHost, 4);
        if (!empty($urlHostArr[2])) {
            $urlHost = $urlHostArr[2];
        }
        $urlPath = empty($urlHostArr[3]) ? '' : $urlHostArr[3];

        if (!isset(self::$_urlHostCache[$urlHost])) {
            $urlHostArr = explode(':', $urlHost);
            $urlHost = $urlHostArr[0];
            $sessionId = $httpHost !== $urlHost && !$this->isValidForHost($urlHost)
                ? $this->getEncryptedSessionId() : '';
            self::$_urlHostCache[$urlHost] = $sessionId;
        }

        return Mage::app()->getStore()->isAdmin() || $this->isValidForPath($urlPath) ? self::$_urlHostCache[$urlHost]
            : $this->getEncryptedSessionId();
    }

    /**
     * Check if session is valid for given hostname
     */
    public function isValidForHost(string $host): bool
    {
        $hostArr = explode(':', $host);
        $hosts = $this->getSessionHosts();
        return !empty($hosts[$hostArr[0]]);
    }

    /**
     * Check if session is valid for given path
     */
    public function isValidForPath(string $path): bool
    {
        $cookiePath = trim($this->getCookiePath(), '/') . '/';
        if ($cookiePath == '/') {
            return true;
        }

        $urlPath = trim($path, '/') . '/';

        return str_starts_with($urlPath, $cookiePath);
    }

    /**
     * Add hostname to session
     */
    public function addHost(string|true $host): self
    {
        if ($host === true) {
            if (!$host = Mage::app()->getFrontController()->getRequest()->getHttpHost()) {
                return $this;
            }
        }

        if (!$host) {
            return $this;
        }

        $hosts = $this->getSessionHosts();
        $hosts[$host] = true;
        $this->setSessionHosts($hosts);
        return $this;
    }

    public function getSessionHosts(): array
    {
        return $this->_sessionHosts;
    }

    public function setSessionHosts(array $hosts): self
    {
        $this->_sessionHosts = $hosts;
        return $this;
    }

    /**
     * Retrieve session save method
     * Default files
     */
    public function getSessionSaveMethod(): string
    {
        if (Mage::isInstalled() && $sessionSave = Mage::getConfig()->getNode(self::XML_NODE_SESSION_SAVE)) {
            return $sessionSave->__toString();
        }
        return 'files';
    }

    public function getSessionSavePath(): string
    {
        if (Mage::isInstalled() && $sessionSavePath = Mage::getConfig()->getNode(self::XML_NODE_SESSION_SAVE_PATH)) {
            return $sessionSavePath;
        }
        return Mage::getBaseDir('session');
    }

    /**
     * Renew session id and update session cookie
     */
    public function renewSession(): self
    {
        $this->getCookie()->delete($this->getSessionName());
        $this->regenerateSessionId();

        $sessionHosts = $this->getSessionHosts();
        $currentCookieDomain = $this->getCookie()->getDomain();
        foreach (array_keys($sessionHosts) as $host) {
            // Delete cookies with the same name for parent domains
            if (strpos($currentCookieDomain, $host) > 0) {
                $this->getCookie()->delete($this->getSessionName(), null, $host);
            }
        }

        return $this;
    }

    /**
     * Validate session
     *
     * @throws Mage_Core_Model_Session_Exception
     */
    public function validate(): self
    {
        // Backwards compatibility with legacy sessions (validator data stored per-namespace)
        if (isset($this->_data[self::VALIDATOR_KEY])) {
            $_SESSION[self::VALIDATOR_KEY] = $this->_data[self::VALIDATOR_KEY];
            unset($this->_data[self::VALIDATOR_KEY]);
        }
        if (!isset($_SESSION[self::VALIDATOR_KEY])) {
            $_SESSION[self::VALIDATOR_KEY] = $this->getValidatorData();
        } else {
            if (!self::$isValidated && ! $this->_validate()) {
                $this->getCookie()->delete($this->getSessionName());
                // throw core session exception
                throw new Mage_Core_Model_Session_Exception('');
            }

            // Refresh Symfony session metadata
            $this->setValidatorSessionRenewTimestamp();
        }

        return $this;
    }

    /**
     * Update the session's last legitimate renewal time (call when customer password is updated to avoid
     * being logged out)
     */
    public function setValidatorSessionRenewTimestamp(?int $timestamp = null): void
    {
        $session = $this->getSymfonySession();
        if ($session !== null) {
            $session->getMetadataBag()->stampNew($this->getCookie()->getLifetime());
        }
    }

    /**
     * Validate data
     */
    protected function _validate(): bool
    {
        $sessionData = $_SESSION[self::VALIDATOR_KEY];
        $validatorData = $this->getValidatorData();
        self::$isValidated = true; // Only validate once since the validator data is the same for every namespace

        if ($this->useValidateRemoteAddr()
                && $sessionData[self::VALIDATOR_REMOTE_ADDR_KEY] != $validatorData[self::VALIDATOR_REMOTE_ADDR_KEY]
        ) {
            return false;
        }
        if ($this->useValidateHttpVia()
                && $sessionData[self::VALIDATOR_HTTP_VIA_KEY] != $validatorData[self::VALIDATOR_HTTP_VIA_KEY]
        ) {
            return false;
        }

        if ($this->useValidateHttpXForwardedFor()
                && $sessionData[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY] != $validatorData[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY]
        ) {
            return false;
        }
        if ($this->useValidateHttpUserAgent()
            && $sessionData[self::VALIDATOR_HTTP_USER_AGENT_KEY] != $validatorData[self::VALIDATOR_HTTP_USER_AGENT_KEY]
        ) {
            $userAgentValidated = $this->getValidateHttpUserAgentSkip();
            foreach ($userAgentValidated as $agent) {
                if (preg_match('/' . $agent . '/iu', $validatorData[self::VALIDATOR_HTTP_USER_AGENT_KEY])) {
                    return true;
                }
            }
            return false;
        }

        $session = $this->getSymfonySession();
        if ($session !== null) {
            $metadataBag = $session->getMetadataBag();

            if ($this->useValidateSessionPasswordTimestamp()) {
                if ($metadataBag->getLastUsed() < ($validatorData[self::VALIDATOR_PASSWORD_CREATE_TIMESTAMP] ?? 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Retrieve unique user data for validator
     */
    public function getValidatorData(): array
    {
        $parts = [
            self::VALIDATOR_REMOTE_ADDR_KEY             => '',
            self::VALIDATOR_HTTP_VIA_KEY                => '',
            self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    => '',
            self::VALIDATOR_HTTP_USER_AGENT_KEY         => '',
        ];

        // Use Symfony Request for modern HTTP handling
        $request = Request::createFromGlobals();
        $parts[self::VALIDATOR_REMOTE_ADDR_KEY] = $request->getClientIp() ?: '';
        $parts[self::VALIDATOR_HTTP_VIA_KEY] = $request->headers->get('Via', '');
        $parts[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY] = $request->headers->get('X-Forwarded-For', '');
        $parts[self::VALIDATOR_HTTP_USER_AGENT_KEY] = $request->headers->get('User-Agent', '');

        // get time when password was last changed
        if (isset($this->_data['visitor_data']['customer_id'])) {
            $parts[self::VALIDATOR_PASSWORD_CREATE_TIMESTAMP] =
                Mage::helper('customer')->getPasswordTimestamp($this->_data['visitor_data']['customer_id']);
        }

        return $parts;
    }

    public function getSessionValidatorData(): array
    {
        return $_SESSION[self::VALIDATOR_KEY];
    }
}
