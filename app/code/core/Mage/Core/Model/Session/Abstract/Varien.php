<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;

/**
 * @method bool|null getSkipEmptySessionCheck()
 * @method $this setSkipEmptySessionCheck(bool $flag)
 */
class Mage_Core_Model_Session_Abstract_Varien extends Varien_Object
{
    public const VALIDATOR_KEY                         = '_session_validator_data';
    public const VALIDATOR_HTTP_USER_AGENT_KEY         = 'http_user_agent';
    public const VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    = 'http_x_forwarded_for';
    public const VALIDATOR_HTTP_VIA_KEY                = 'http_via';
    public const VALIDATOR_REMOTE_ADDR_KEY             = 'remote_addr';
    public const VALIDATOR_SESSION_EXPIRE_TIMESTAMP    = 'session_expire_timestamp';
    public const VALIDATOR_SESSION_RENEW_TIMESTAMP     = 'session_renew_timestamp';
    public const VALIDATOR_SESSION_LIFETIME            = 'session_lifetime';
    public const VALIDATOR_PASSWORD_CREATE_TIMESTAMP   = 'password_create_timestamp';
    public const SECURE_COOKIE_CHECK_KEY               = '_secure_cookie_check';
    public const REGISTRY_CONCURRENCY_ERROR            = 'concurrent_connections_exceeded';

    /** @var bool Flag true if session validator data has already been evaluated */
    protected static $isValidated = false;

    /**
     * Map of session enabled hosts
     * @example array('host.name' => true)
     * @var array
     */
    protected $_sessionHosts = [];

    /** @var Session|null Symfony session instance */
    private ?Session $symfonySession = null;

    /** @var Request|null Symfony request instance */
    private ?Request $symfonyRequest = null;

    /**
     * Initialize Symfony components for modern session handling
     */
    private function initializeSymfonyComponents(): void
    {
        if ($this->symfonyRequest === null) {
            $this->symfonyRequest = Request::createFromGlobals();
        }
    }

    /**
     * Create Symfony session with proper storage handler
     */
    private function createSymfonySession(string $sessionName): Session
    {
        $handler = $this->createSessionHandler();
        $storage = new NativeSessionStorage(
            [
                'name' => $sessionName,
                'use_cookies' => false,
                'use_trans_sid' => false,
                'cache_limiter' => (string) (Mage::getConfig()->getNode('global/session_cache_limiter') ?: ''),
            ],
            $handler,
        );

        return new Session($storage);
    }

    /**
     * Create appropriate session handler based on configuration
     */
    private function createSessionHandler(): \SessionHandlerInterface
    {
        $method = $this->getSessionSaveMethod();

        switch ($method) {
            case 'redis':
                return $this->createRedisSessionHandler();

            case 'files':
            default:
                return $this->createFileSessionHandler();
        }
    }

    /**
     * Create Redis session handler with modern Symfony approach
     */
    private function createRedisSessionHandler(): \SessionHandlerInterface
    {
        // Use the existing Redis setup but with modern wrapper
        $redisConfig = Mage::getConfig()->getNode('global/redis_session');

        if (!$redisConfig) {
            throw new Exception('Redis session configuration not found in global/redis_session');
        }

        // Set up Redis connection parameters
        $host = (string) ($redisConfig->host ?: '127.0.0.1');
        $port = (int) ($redisConfig->port ?: 6379);
        $database = (int) ($redisConfig->database ?: 0);
        $password = (string) ($redisConfig->password ?: '');

        // Create Redis connection
        $redis = new \Redis();
        $redis->connect($host, $port);

        if ($password) {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        // Return Redis session handler
        return new class ($redis) implements \SessionHandlerInterface {
            private \Redis $redis;
            private string $prefix = 'session:';

            public function __construct(\Redis $redis)
            {
                $this->redis = $redis;
            }

            public function open(string $path, string $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string
            {
                $data = $this->redis->get($this->prefix . $id);
                return $data !== false ? (string) $data : '';
            }

            public function write(string $id, string $data): bool
            {
                $ttl = (int) (ini_get('session.gc_maxlifetime') ?: 3600);
                return $this->redis->setex($this->prefix . $id, $ttl, $data);
            }

            public function destroy(string $id): bool
            {
                $result = $this->redis->del($this->prefix . $id);
                return is_int($result) && $result > 0;
            }

            public function gc(int $max_lifetime): int
            {
                // Redis handles expiration automatically
                return 0;
            }
        };
    }

    /**
     * Create file session handler
     */
    private function createFileSessionHandler(): \SessionHandlerInterface
    {
        $savePath = $this->getSessionSavePath();

        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }

        if (!is_writable($savePath)) {
            throw new Exception("Session save path '{$savePath}' is not writable");
        }

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
     * Start session using modern Symfony approach
     */
    private function startSymfonySession(): void
    {
        try {
            // Start Symfony session
            if ($this->symfonySession && !$this->symfonySession->isStarted()) {
                $this->symfonySession->start();
            }
        } catch (Throwable $e) {
            if (Mage::registry(self::REGISTRY_CONCURRENCY_ERROR)) {
                Maho::errorReport();
                die();
            } else {
                Mage::printException($e);
            }
        }
    }

    /**
     * Configure and start session
     *
     * @param string $sessionName
     * @return $this
     * @throws Mage_Core_Model_Store_Exception
     */
    public function start($sessionName = null)
    {
        if (isset($_SESSION) && !$this->getSkipEmptySessionCheck()) {
            return $this;
        }

        // Do not start a session if no sessionName was provided
        if (empty($sessionName)) {
            return $this;
        }

        // Initialize Symfony components
        $this->initializeSymfonyComponents();

        // Create Symfony session instance
        $this->symfonySession = $this->createSymfonySession($sessionName);

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

        Varien_Profiler::start(__METHOD__ . '/start');

        // Start session using modern Symfony approach with legacy fallback
        $this->startSymfonySession();

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

        Varien_Profiler::stop(__METHOD__ . '/start');

        return $this;
    }

    /**
     * Get session hosts
     *
     * @return array
     */
    public function getSessionHosts()
    {
        return $this->_sessionHosts;
    }

    /**
     * Set session hosts
     *
     * @return $this
     */
    public function setSessionHosts(array $hosts)
    {
        $this->_sessionHosts = $hosts;
        return $this;
    }

    public function setSessionCookie(): self
    {
        $mahoCookie = $this->getCookie();
        $sessionName = $this->getSessionName();
        $sessionId = $this->getSessionId();

        // Use Symfony Cookie for enhanced security features if available
        if ($this->symfonyRequest) {
            $isSecure = $this->symfonyRequest->isSecure();
            $domain = $mahoCookie->getDomain();
            $path = $mahoCookie->getPath();
            $lifetime = $mahoCookie->getLifetime();

            // Create Symfony cookie with enhanced security
            $symfonyCookie = new Cookie(
                $sessionName,
                $sessionId,
                $lifetime > 0 ? time() + $lifetime : 0,
                $path,
                $domain,
                $isSecure,
                true, // httpOnly
                false, // raw
                $isSecure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX,
            );

            // Set cookie headers directly for immediate effect
            $cookieHeader = $symfonyCookie->__toString();
            if (!headers_sent()) {
                header("Set-Cookie: $cookieHeader", false);
            }
        }

        // Keep legacy cookie setting for backward compatibility
        $mahoCookie->set($sessionName, $sessionId);

        return $this;
    }

    /**
     * Retrieve cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public function getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Revalidate cookie
     * @deprecated after 1.4 cookie renew moved to session start method
     * @return $this
     */
    public function revalidateCookie()
    {
        return $this;
    }

    /**
     * Init session with namespace
     *
     * @param string $namespace
     * @param string $sessionName
     * @return $this
     */
    public function init($namespace, $sessionName = null)
    {
        if (!$this->symfonySession || !$this->symfonySession->isStarted()) {
            $this->start($sessionName);
        }

        // Initialize namespace in Symfony session
        if ($this->symfonySession && !$this->symfonySession->has($namespace)) {
            $this->symfonySession->set($namespace, []);
        }

        // For backward compatibility, also set $_SESSION
        if (!isset($_SESSION[$namespace])) {
            $_SESSION[$namespace] = [];
        }

        $this->_data = &$_SESSION[$namespace];

        $this->validate();

        return $this;
    }

    /**
     * Additional get data with clear mode
     *
     * @param string $key
     * @param bool $clear
     * @return mixed
     */
    #[\Override]
    public function getData($key = '', $clear = false)
    {
        $data = parent::getData($key);
        if ($clear && isset($this->_data[$key])) {
            unset($this->_data[$key]);
        }
        return $data;
    }

    /**
     * @return false|string
     */
    public function getSessionId()
    {
        if ($this->symfonySession && $this->symfonySession->isStarted()) {
            return $this->symfonySession->getId();
        }
        return session_id();
    }

    /**
     * Set custom session id
     *
     * @param string $id
     * @return $this
     */
    public function setSessionId($id = null)
    {
        if (!is_null($id) && preg_match('#^[0-9a-zA-Z,-]+$#', $id)) {
            if ($this->symfonySession && !$this->symfonySession->isStarted()) {
                $this->symfonySession->setId($id);
            } else {
                session_id($id);
            }
        }
        return $this;
    }

    /**
     * Retrieve session name
     *
     * @return string
     */
    public function getSessionName()
    {
        if ($this->symfonySession) {
            return $this->symfonySession->getName();
        }
        return session_name();
    }

    /**
     * Set session name
     *
     * @param string $name
     * @return $this
     */
    public function setSessionName($name)
    {
        if (!empty($name)) {
            if ($this->symfonySession && !$this->symfonySession->isStarted()) {
                $this->symfonySession->setName($name);
            } else {
                session_name($name);
            }
        }
        return $this;
    }

    /**
     * Unset all data
     *
     * @return $this
     */
    public function unsetAll()
    {
        $this->unsetData();
        return $this;
    }

    /**
     * Alias for unsetAll
     *
     * @return $this
     */
    public function clear()
    {
        return $this->unsetAll();
    }

    /**
     * Retrieve session save method
     * Default files
     *
     * @return string
     */
    public function getSessionSaveMethod()
    {
        return 'files';
    }

    /**
     * Get session save path
     *
     * @return string
     */
    public function getSessionSavePath()
    {
        return Mage::getBaseDir('session');
    }

    /**
     * Use REMOTE_ADDR in validator key
     *
     * @return bool
     */
    public function useValidateRemoteAddr()
    {
        return true;
    }

    /**
     * Use HTTP_VIA in validator key
     *
     * @return bool
     */
    public function useValidateHttpVia()
    {
        return true;
    }

    /**
     * Use HTTP_X_FORWARDED_FOR in validator key
     *
     * @return bool
     */
    public function useValidateHttpXForwardedFor()
    {
        return true;
    }

    /**
     * Use HTTP_USER_AGENT in validator key
     *
     * @return bool
     */
    public function useValidateHttpUserAgent()
    {
        return true;
    }

    /**
     * Use session expire timestamp in validator key
     *
     * @return bool
     */
    public function useValidateSessionExpire()
    {
        return $this->getCookie()->getLifetime() > 0;
    }

    /**
     * Password creation timestamp must not be newer than last session renewal
     *
     * @return bool
     */
    public function useValidateSessionPasswordTimestamp()
    {
        return true;
    }

    /**
     * Retrieve skip User Agent validation strings (Flash etc)
     *
     * @return array
     */
    public function getValidateHttpUserAgentSkip()
    {
        return [];
    }

    /**
     * Validate session
     *
     * @throws Mage_Core_Model_Session_Exception
     * @return $this
     */
    public function validate()
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
                $this->getCookie()->delete(session_name());
                // throw core session exception
                throw new Mage_Core_Model_Session_Exception('');
            }

            // Refresh expire timestamp
            if ($this->useValidateSessionExpire() || $this->useValidateSessionPasswordTimestamp()) {
                $this->setValidatorSessionRenewTimestamp(time());
                $_SESSION[self::VALIDATOR_KEY][self::VALIDATOR_SESSION_LIFETIME] = $this->getCookie()->getLifetime();
            }
        }

        return $this;
    }

    /**
     * Update the session's last legitimate renewal time (call when customer password is updated to avoid
     * being logged out)
     *
     * @param int $timestamp
     * @return void
     */
    public function setValidatorSessionRenewTimestamp($timestamp)
    {
        $_SESSION[self::VALIDATOR_KEY][self::VALIDATOR_SESSION_RENEW_TIMESTAMP] = $timestamp;
    }

    /**
     * Validate data
     *
     * @return bool
     */
    protected function _validate()
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

        if ($this->useValidateSessionExpire()
            && isset($sessionData[self::VALIDATOR_SESSION_RENEW_TIMESTAMP])
            && isset($sessionData[self::VALIDATOR_SESSION_LIFETIME])
            && ((int) $sessionData[self::VALIDATOR_SESSION_RENEW_TIMESTAMP] + (int) $sessionData[self::VALIDATOR_SESSION_LIFETIME])
            < time()
        ) {
            return false;
        }
        if ($this->useValidateSessionPasswordTimestamp()
            && isset($validatorData[self::VALIDATOR_PASSWORD_CREATE_TIMESTAMP])
            && isset($sessionData[self::VALIDATOR_SESSION_RENEW_TIMESTAMP])
            && $validatorData[self::VALIDATOR_PASSWORD_CREATE_TIMESTAMP]
            > $sessionData[self::VALIDATOR_SESSION_RENEW_TIMESTAMP]
        ) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve unique user data for validator
     *
     * @return array
     */
    public function getValidatorData()
    {
        $parts = [
            self::VALIDATOR_REMOTE_ADDR_KEY             => '',
            self::VALIDATOR_HTTP_VIA_KEY                => '',
            self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY    => '',
            self::VALIDATOR_HTTP_USER_AGENT_KEY         => '',
        ];

        // Use Symfony Request for modern HTTP handling if available
        if ($this->symfonyRequest) {
            // collect ip data using Symfony
            $parts[self::VALIDATOR_REMOTE_ADDR_KEY] = $this->symfonyRequest->getClientIp() ?: '';
            $parts[self::VALIDATOR_HTTP_VIA_KEY] = $this->symfonyRequest->headers->get('Via', '');
            $parts[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY] = $this->symfonyRequest->headers->get('X-Forwarded-For', '');
            $parts[self::VALIDATOR_HTTP_USER_AGENT_KEY] = $this->symfonyRequest->headers->get('User-Agent', '');
        } else {
            // Fallback to legacy method
            if (Mage::helper('core/http')->getRemoteAddr()) {
                $parts[self::VALIDATOR_REMOTE_ADDR_KEY] = Mage::helper('core/http')->getRemoteAddr();
            }
            if (isset($_ENV['HTTP_VIA'])) {
                $parts[self::VALIDATOR_HTTP_VIA_KEY] = (string) $_ENV['HTTP_VIA'];
            }
            if (isset($_ENV['HTTP_X_FORWARDED_FOR'])) {
                $parts[self::VALIDATOR_HTTP_X_FORVARDED_FOR_KEY] = (string) $_ENV['HTTP_X_FORWARDED_FOR'];
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $parts[self::VALIDATOR_HTTP_USER_AGENT_KEY] = (string) $_SERVER['HTTP_USER_AGENT'];
            }
        }

        // get time when password was last changed
        if (isset($this->_data['visitor_data']['customer_id'])) {
            $parts[self::VALIDATOR_PASSWORD_CREATE_TIMESTAMP] =
                Mage::helper('customer')->getPasswordTimestamp($this->_data['visitor_data']['customer_id']);
        }

        return $parts;
    }

    /**
     * @return array
     */
    public function getSessionValidatorData()
    {
        return $_SESSION[self::VALIDATOR_KEY];
    }

    /**
     * Regenerate session Id
     *
     * @return $this
     */
    public function regenerateSessionId()
    {
        if ($this->symfonySession && $this->symfonySession->isStarted()) {
            $this->symfonySession->migrate(true);
        } else {
            session_regenerate_id(true);
        }

        $this->setSessionCookie();
        return $this;
    }
}
