<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
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
    public const XML_PATH_REMEMBER_ENABLED     = 'web/cookie/remember_enabled';
    public const XML_PATH_REMEMBER_LIFETIME    = 'web/cookie/remember_cookie_lifetime';
    public const XML_PATH_ADMIN_LIFETIME       = 'admin/security/session_cookie_lifetime';
    public const XML_NODE_SESSION_SAVE         = 'global/session_save';
    public const XML_NODE_SESSION_SAVE_PATH    = 'global/session_save_path';

    public const XML_PATH_USE_REMOTE_ADDR      = 'web/session/use_remote_addr';
    public const XML_PATH_USE_HTTP_VIA         = 'web/session/use_http_via';
    public const XML_PATH_USE_X_FORWARDED      = 'web/session/use_http_x_forwarded_for';
    public const XML_PATH_USE_USER_AGENT       = 'web/session/use_http_user_agent';

    public const XML_NODE_USET_AGENT_SKIP      = 'global/session/validation/http_user_agent_skip';

    /** Lifetime floor, in seconds, wherever no area policy narrows it */
    private const DEFAULT_SESSION_LIFETIME = 86400;

    /** @var bool Flag true if session validator data has already been evaluated */
    protected static bool $isValidated = false;

    /** Declared explicitly so the DataObject magic setter cannot bind it into $_SESSION */
    protected ?int $sessionLifetime = null;

    /**
     * Map of session enabled hosts
     * @example ['host.name' => true]
     */
    protected array $_sessionHosts = [];

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
        // Before createSessionHandler(), which hands it to the Redis handler as its ttl
        $this->sessionLifetime = self::resolveStoredSessionLifetime($sessionName);

        $handler = $this->createSessionHandler();

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

    private function getSessionLifetime(): int
    {
        // A non-positive ttl makes the Redis handler's setEx() fail and drop the session
        return max(1, $this->sessionLifetime ?? self::DEFAULT_SESSION_LIFETIME);
    }

    /**
     * Reaching this destroys the record, so it is the longest lifetime the area can grant rather
     * than the one this request resolves: both the store view and the Remember Me flag follow the
     * request, so a narrower value would let any request destroy a session it does not own. Being
     * the longest, it also always covers the cookie, which stays what governs access.
     */
    private static function resolveStoredSessionLifetime(string $sessionName): int
    {
        if ($sessionName === Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE) {
            return self::resolveConfiguredSessionLifetime($sessionName, store: Mage_Core_Model_Store::ADMIN_CODE);
        }

        $stores = Mage::app()->getStores();
        $stores[] = Mage_Core_Model_Store::ADMIN_CODE;

        $lifetime = 0;
        foreach ($stores as $store) {
            $lifetime = max(
                $lifetime,
                self::resolveConfiguredSessionLifetime($sessionName, false, $store),
                self::resolveConfiguredSessionLifetime($sessionName, true, $store),
            );
        }

        return $lifetime;
    }

    /** Remember Me lives inside the session, so only the observer can pass it */
    public static function resolveConfiguredSessionLifetime(
        string $sessionName,
        bool $rememberMe = false,
        int|string|Mage_Core_Model_Store|null $store = null,
    ): int {
        if ($sessionName === Mage_Core_Controller_Front_Action::SESSION_NAMESPACE) {
            $path = $rememberMe && Mage::getStoreConfigFlag(self::XML_PATH_REMEMBER_ENABLED, $store)
                ? self::XML_PATH_REMEMBER_LIFETIME
                : self::XML_PATH_COOKIE_LIFETIME;

            return max(
                Mage_Core_Controller_Front_Action::SESSION_MIN_LIFETIME,
                min(
                    Mage::getStoreConfigAsInt($path, $store),
                    Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME,
                ),
            );
        }

        if ($sessionName === Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE) {
            return max(
                Mage_Adminhtml_Controller_Action::SESSION_MIN_LIFETIME,
                min(
                    Mage::getStoreConfigAsInt(self::XML_PATH_ADMIN_LIFETIME, $store),
                    Mage_Adminhtml_Controller_Action::SESSION_MAX_LIFETIME,
                ),
            );
        }

        // Namespaces with no observer of their own, an extension's included
        return min(
            max(
                Mage::getStoreConfigAsInt(self::XML_PATH_ADMIN_LIFETIME, $store),
                Mage::getStoreConfigAsInt(self::XML_PATH_COOKIE_LIFETIME, $store),
                self::DEFAULT_SESSION_LIFETIME,
            ),
            Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME,
        );
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

        $options['ttl'] = $this->getSessionLifetime();

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
        $this->setSessionId();

        // If we still do not have a session id, then read from the cookie value
        // Otherwise, we will be starting a new session.
        if (empty($this->getSessionId()) && is_string($cookie->get($sessionName))) {
            $this->setSessionId($cookie->get($sessionName));
        }

        \Maho\Profiler::start(__METHOD__ . '/start');

        // Start session using modern Symfony approach
        $symfonySession->start();

        // Read before anything constructs a session model, whose validate() re-stamps this
        $lastUsed = $symfonySession->getMetadataBag()->getLastUsed();

        $this->expireIdleSession($symfonySession, $lastUsed);

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
                // Secure cookie check value is invalid, regenerate session. The old record is kept
                // on purpose: the requester may not own the id it presented
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

    private function expireIdleSession(Session $symfonySession, int $lastUsed): bool
    {
        if ($lastUsed <= 0 || time() - $lastUsed <= $this->getSessionLifetime()) {
            return false;
        }

        // Emptied through the reference, since a session model may already be bound to these keys
        // and reassigning $_SESSION would leave it holding what this call is expiring
        foreach (array_keys($_SESSION) as $key) {
            $_SESSION[$key] = [];
        }

        // Set but empty, these would fail validate() and fake a secure cookie mismatch
        unset($_SESSION[self::VALIDATOR_KEY], $_SESSION[self::SECURE_COOKIE_CHECK_KEY]);

        $symfonySession->migrate(true);

        return true;
    }

    /** Longest lifetime a store can hand out, for callers that cannot know which policy applies */
    protected static function getLongestConfiguredSessionLifetime(int|string|Mage_Core_Model_Store|null $store = null): int
    {
        $rememberLifetime = Mage::getStoreConfigFlag(self::XML_PATH_REMEMBER_ENABLED, $store)
            ? Mage::getStoreConfigAsInt(self::XML_PATH_REMEMBER_LIFETIME, $store)
            : 0;

        return min(
            max(
                Mage::getStoreConfigAsInt(self::XML_PATH_ADMIN_LIFETIME, $store),
                Mage::getStoreConfigAsInt(self::XML_PATH_COOKIE_LIFETIME, $store),
                $rememberLifetime,
                self::DEFAULT_SESSION_LIFETIME,
            ),
            Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME,
        );
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
        return $this->getSymfonySession()?->getId() ?? false;
    }

    public function getSessionName(): string
    {
        return $this->getSymfonySession()?->getName() ?? '';
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
        if (!is_null($id) && preg_match('#^[0-9a-zA-Z,-]+$#', $id)) {
            $this->getSymfonySession()->setId($id);
        }

        $this->addHost(true);
        return $this;
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
        $currentCookieDomain = ltrim((string) $this->getCookie()->getDomain(), '.');
        foreach (array_keys($sessionHosts) as $host) {
            if ($host !== '' && $host !== $currentCookieDomain
                && str_ends_with($currentCookieDomain, '.' . $host)
            ) {
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
            return array_any($userAgentValidated, fn($agent) => preg_match('/' . $agent . '/iu', $validatorData[self::VALIDATOR_HTTP_USER_AGENT_KEY]));
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
