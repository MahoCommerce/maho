<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Monolog\Level;

/**
 * Main Mage hub class
 */
final class Mage
{
    /**
     * Log level constants
     */
    public const LOG_EMERGENCY = Level::Emergency;
    public const LOG_ALERT     = Level::Alert;
    public const LOG_CRITICAL  = Level::Critical;
    public const LOG_ERROR     = Level::Error;
    public const LOG_WARNING   = Level::Warning;
    public const LOG_NOTICE    = Level::Notice;
    public const LOG_INFO      = Level::Info;
    public const LOG_DEBUG     = Level::Debug;

    /**
     * @deprecated Use LOG_EMERGENCY instead
     */
    public const LOG_EMERG = self::LOG_EMERGENCY;

    /**
     * @deprecated Use LOG_CRITICAL instead
     */
    public const LOG_CRIT = self::LOG_CRITICAL;

    /**
     * @deprecated Use LOG_ERROR instead
     */
    public const LOG_ERR = self::LOG_ERROR;

    /**
     * @deprecated Use LOG_WARNING instead
     */
    public const LOG_WARN = self::LOG_WARNING;

    /**
     * Registry collection
     *
     * @var array
     */
    private static $_registry = [];

    /**
     * Application root absolute path
     *
     * @var string|null
     */
    private static $_appRoot;

    /**
     * Application model
     *
     * @var Mage_Core_Model_App|null
     */
    private static $_app;

    /**
     * Config Model
     *
     * @var Mage_Core_Model_Config|null
     */
    private static $_config;

    /**
     * Event Collection Object
     *
     * @var \Maho\Event\Collection|null
     */
    private static $_events;

    /**
     * Object cache instance
     *
     * @var \Maho\DataObject\Cache|null
     */
    private static $_objects;

    /**
     * Is developer mode flag
     *
     * @var bool
     */
    private static $_isDeveloperMode = false;

    /**
     * Is allow throw Exception about headers already sent
     *
     * @var bool
     */
    public static $headersSentThrowsException = true;

    /**
     * Is installed flag
     *
     * @var bool|null
     */
    private static $_isInstalled;

    /**
     * Gets the current Maho version string
     */
    public static function getVersion(): string
    {
        return '26.3.0';
    }

    /**
     * Set all my static data to defaults
     */
    public static function reset()
    {
        self::$_registry        = [];
        self::$_appRoot         = null;
        self::$_app             = null;
        self::$_config          = null;
        self::$_events          = null;
        self::$_objects         = null;
        self::$_isDeveloperMode = false;
        self::$_isInstalled     = null;
        // do not reset $headersSentThrowsException
    }

    /**
     * Register a new variable
     *
     * @param string $key
     * @param mixed $value
     * @param bool $graceful
     * @throws Mage_Core_Exception
     */
    public static function register($key, $value, $graceful = false)
    {
        if (isset(self::$_registry[$key])) {
            if ($graceful) {
                return;
            }
            self::throwException("Mage registry key $key already exists");
        }
        self::$_registry[$key] = $value;
    }

    /**
     * Unregister a variable from register by key
     *
     * @param string $key
     */
    public static function unregister($key)
    {
        if (isset(self::$_registry[$key])) {
            if (is_object(self::$_registry[$key]) && (method_exists(self::$_registry[$key], '__destruct'))) {
                self::$_registry[$key]->__destruct();
            }
            unset(self::$_registry[$key]);
        }
    }

    /**
     * Retrieve a value from registry by a key
     *
     * @param string $key
     * @return mixed
     */
    public static function registry($key)
    {
        return self::$_registry[$key] ?? null;
    }

    /**
     * Set application root absolute path
     *
     * @param string $appRoot
     * @throws Mage_Core_Exception
     */
    public static function setRoot($appRoot = '')
    {
        if (self::$_appRoot) {
            return ;
        }

        if ($appRoot === '') {
            // automagically find application root by __DIR__ constant of Mage.php
            $appRoot = dirname(getcwd());
        }

        $appRoot = realpath($appRoot);

        if (is_dir($appRoot) && is_readable($appRoot)) {
            self::$_appRoot = $appRoot;
        } else {
            self::throwException("$appRoot is not a directory or not readable by this user");
        }
    }

    /**
     * Retrieve application root absolute path
     *
     * @return string
     */
    public static function getRoot()
    {
        return self::$_appRoot;
    }

    /**
     * Retrieve Events Collection
     *
     * @return \Maho\Event\Collection $collection
     */
    public static function getEvents()
    {
        return self::$_events;
    }

    /**
     * Varien Objects Cache
     *
     * @param string $key optional, if specified will load this key
     * @return \Maho\DataObject\Cache
     */
    public static function objects($key = null)
    {
        if (!self::$_objects) {
            self::$_objects = new \Maho\DataObject\Cache();
        }
        if (is_null($key)) {
            return self::$_objects;
        }
        return self::$_objects->load($key);
    }

    /**
     * Retrieve application root absolute path
     *
     * @param string $type
     * @return string
     */
    public static function getBaseDir($type = 'base')
    {
        return self::getConfig()->getOptions()->getDir($type);
    }

    /**
     * Retrieve module absolute path by directory type
     *
     * @param string $type
     * @param string $moduleName
     * @return string
     */
    public static function getModuleDir($type, $moduleName)
    {
        return self::getConfig()->getModuleDir($type, $moduleName);
    }

    /**
     * Retrieve config value for store by path
     *
     * @param string $path
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @return mixed
     */
    public static function getStoreConfig($path, $store = null)
    {
        return self::app()->getStore($store)->getConfig($path);
    }

    /**
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     */
    public static function getStoreConfigAsFloat(string $path, $store = null): float
    {
        return (float) self::getStoreConfig($path, $store);
    }

    /**
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     */
    public static function getStoreConfigAsInt(string $path, $store = null): int
    {
        return (int) self::getStoreConfig($path, $store);
    }

    /**
     * Retrieve config flag for store by path
     *
     * @param string $path
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @return bool
     */
    public static function getStoreConfigFlag($path, $store = null)
    {
        $flag = self::getStoreConfig($path, $store);
        $flag = is_string($flag) ? strtolower($flag) : $flag;
        if (!empty($flag) && $flag !== 'false') {
            return true;
        }
        return false;
    }

    /**
     * Get base URL path by type
     *
     * @param Mage_Core_Model_Store::URL_TYPE_* $type
     * @param null|bool $secure
     * @return string
     */
    public static function getBaseUrl($type = Mage_Core_Model_Store::URL_TYPE_LINK, $secure = null)
    {
        return self::app()->getStore()->getBaseUrl($type, $secure);
    }

    /**
     * Generate url by route and parameters
     *
     * @param   null|string $route
     * @param   array $params
     * @return  string
     */
    public static function getUrl($route = '', $params = [])
    {
        return self::getModel('core/url')->getUrl($route, $params);
    }

    /**
     * Get design package singleton
     *
     * @return Mage_Core_Model_Design_Package
     */
    public static function getDesign()
    {
        return self::getSingleton('core/design_package');
    }

    /**
     * Retrieve a config instance
     *
     * @return Mage_Core_Model_Config|null
     */
    public static function getConfig()
    {
        return self::$_config;
    }

    /**
     * Add observer to events object
     *
     * @param string $eventName
     * @param callable $callback
     * @param array $data
     * @param string $observerName
     * @param class-string|'' $observerClass
     * @return \Maho\Event\Collection
     * @throws Mage_Core_Exception
     */
    public static function addObserver($eventName, $callback, $data = [], $observerName = '', $observerClass = '')
    {
        if ($observerClass == '') {
            $observerClass = \Maho\Event\Observer::class;
        }
        if (!class_exists($observerClass)) {
            self::throwException("Invalid observer class: $observerClass");
        }
        $observer = new $observerClass();
        $observer->setName($observerName)->addData($data)->setEventName($eventName)->setCallback($callback);
        return self::getEvents()->addObserver($observer);
    }

    /**
     * Dispatch event
     *
     * Calls all observer callbacks registered for this event
     * and multiple observers matching event name pattern
     *
     * @param string $name
     * @return Mage_Core_Model_App
     */
    public static function dispatchEvent($name, array $data = [])
    {
        \Maho\Profiler::start('DISPATCH EVENT:' . $name);
        $result = self::app()->dispatchEvent($name, $data);
        \Maho\Profiler::stop('DISPATCH EVENT:' . $name);
        return $result;
    }

    /**
     * Retrieve model instance by alias
     *
     * ```php
     * $model = Mage::getModel('core/store'); // Mage_Core_Model_Store
     * ```
     *
     * @param string $modelAlias
     * @param array|string|object $arguments
     * @return Mage_Core_Model_Abstract|false
     */
    public static function getModel($modelAlias, $arguments = [])
    {
        return self::getConfig()->getModelInstance($modelAlias, $arguments);
    }

    /**
     * Retrieve model singleton by alias
     *
     * ```php
     * $model = Mage::getModel('core/session'); // Mage_Core_Model_Session
     * ```
     *
     * @param string $modelAlias
     * @return Mage_Core_Model_Abstract|false
     */
    public static function getSingleton($modelAlias, array $arguments = [])
    {
        $registryKey = "_singleton/$modelAlias";
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getModel($modelAlias, $arguments));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Retrieve resource model by alias
     *
     * ```php
     * $model = Mage::getResourceModel('core/store_collection'); // Mage_Core_Model_Resource_Store_Collection
     * ```
     *
     * @param string $modelAlias
     * @param array $arguments
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract|false
     */
    public static function getResourceModel($modelAlias, $arguments = [])
    {
        return self::getConfig()->getResourceModelInstance($modelAlias, $arguments);
    }

    /**
     * Retrieve Controller instance by ClassName
     *
     * @param class-string $class
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Front_Action
     * @throws Mage_Core_Exception
     */
    public static function getControllerInstance($class, $request, $response, array $invokeArgs = [])
    {
        if (!class_exists($class)) {
            self::throwException("Invalid controller class: $class");
        }
        return new $class($request, $response, $invokeArgs);
    }

    /**
     * Retrieve resource model singleton by alias
     *
     * ```php
     * $model = Mage::getResourceSingleton('core/session'); // Mage_Core_Model_Resource_Session
     * ```
     *
     * @param string $modelAlias
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract|false
     */
    public static function getResourceSingleton($modelAlias, array $arguments = [])
    {
        $registryKey = "_resource_singleton/$modelAlias";
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getResourceModel($modelAlias, $arguments));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Retrieve block object
     *
     * @param string $type
     * @return Mage_Core_Block_Abstract|stdClass|false
     */
    public static function getBlockSingleton($type)
    {
        $action = self::app()->getFrontController()->getAction();
        return $action ? $action->getLayout()->getBlockSingleton($type) : false;
    }

    /**
     * Retrieve helper singleton by alias
     *
     * ```php
     * $helper = Mage::helper('core'); // Mage_Core_Helper_Data
     * $helper = Mage::helper('core/url'); // Mage_Core_Helper_Url
     * ```
     *
     * @param string $helperAlias
     * @return Mage_Core_Helper_Abstract|false
     */
    public static function helper($helperAlias)
    {
        $registryKey = "_helper/$helperAlias";
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getConfig()->getHelperInstance($helperAlias));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Retrieve resource helper model singleton
     *
     * ```php
     * $model = Mage::getResourceHelper('core'); // Mage_Core_Model_Resource_Helper_Mysql4
     * ```
     *
     * @param string $moduleAlias
     * @return Mage_Core_Model_Resource_Helper_Abstract|false
     */
    public static function getResourceHelper($moduleAlias)
    {
        $registryKey = "_resource_helper/$moduleAlias";
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getConfig()->getResourceHelperInstance($moduleAlias));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Return new exception by module to be thrown
     *
     * @param string $moduleName
     * @param string $message
     * @param integer $code
     * @return Mage_Core_Exception
     */
    public static function exception($moduleName = 'Mage_Core', $message = '', $code = 0)
    {
        $className = "{$moduleName}_Exception";
        if (!class_exists($className)) {
            $className = 'Mage_Core_Exception';
        }
        return new $className($message, $code);
    }

    /**
     * Throw Exception
     *
     * @param string $message
     * @param string $messageStorage
     * @return never
     * @throws Mage_Core_Exception
     */
    public static function throwException($message, $messageStorage = null)
    {
        if ($messageStorage && ($storage = self::getSingleton($messageStorage))) {
            $storage->addError($message);
        }
        throw new Mage_Core_Exception($message);
    }

    public static function addBootupWarning(string $message)
    {
        self::$_registry['bootup_warnings'] ??= [];
        self::$_registry['bootup_warnings'][] = $message;
    }

    /**
     * Get initialized application object.
     *
     * @param string $code
     * @param string $type
     * @param string|array $options
     * @return Mage_Core_Model_App
     */
    public static function app($code = '', $type = 'store', $options = [])
    {
        if (self::$_app === null) {
            self::$_app = new Mage_Core_Model_App();
            self::setRoot();
            self::$_events = new \Maho\Event\Collection();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);

            \Maho\Profiler::start('self::app::init');
            self::$_app->init($code, $type, $options);
            \Maho\Profiler::stop('self::app::init');
            self::$_app->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
        }
        return self::$_app;
    }

    /**
     * @static
     * @param string $code
     * @param string $type
     * @param array $options
     * @param string|array $modules
     */
    public static function init($code = '', $type = 'store', $options = [], $modules = [])
    {
        try {
            self::setRoot();
            self::$_app = new Mage_Core_Model_App();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);

            if (!empty($modules)) {
                self::$_app->initSpecified($code, $type, $options, $modules);
            } else {
                self::$_app->init($code, $type, $options);
            }
        } catch (Mage_Core_Model_Session_Exception $e) {
            header('Location: ' . self::getBaseUrl());
            die;
        } catch (Mage_Core_Model_Store_Exception $e) {
            Maho::errorReport([], 404);
            die;
        } catch (Exception $e) {
            self::printException($e);
            die;
        }
    }

    /**
     * Front end main entry point
     *
     * @param string $code
     * @param string $type
     * @param string|array $options
     */
    public static function run($code = '', $type = 'store', $options = [])
    {
        try {
            \Maho\Profiler::start('mage');
            self::setRoot();
            self::$_app = new Mage_Core_Model_App();
            if (isset($options['request'])) {
                self::$_app->setRequest($options['request']);
            }
            if (isset($options['response'])) {
                self::$_app->setResponse($options['response']);
            }
            self::$_events = new \Maho\Event\Collection();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);
            self::$_app->run([
                'scope_code' => $code,
                'scope_type' => $type,
                'options'    => $options,
            ]);
            \Maho\Profiler::stop('mage');
        } catch (Mage_Core_Model_Session_Exception $e) {
            header('Location: ' . self::getBaseUrl());
            die();
        } catch (Mage_Core_Model_Store_Exception $e) {
            Maho::errorReport([], 404);
            die();
        } catch (Exception $e) {
            if (self::isInstalled()) {
                self::dispatchEvent('mage_run_installed_exception', ['exception' => $e]);
                self::printException($e);
                exit();
            }
            try {
                self::dispatchEvent('mage_run_exception', ['exception' => $e]);
                if (!headers_sent() && self::isInstalled()) {
                    header('Location:' . self::getUrl('install'));
                } else {
                    self::printException($e);
                }
            } catch (Exception $ne) {
                self::printException($ne, $e->getMessage());
            }
        }
    }

    /**
     * Set application isInstalled flag based on given options
     *
     * @param array $options
     */
    protected static function _setIsInstalled($options = [])
    {
        if (isset($options['is_installed'])) {
            self::$_isInstalled = (bool) $options['is_installed'];
        }
    }

    /**
     * Set application Config model
     *
     * @param array $options
     */
    protected static function _setConfigModel($options = [])
    {
        if (isset($options['config_model']) && class_exists($options['config_model'])) {
            $alternativeConfigModelName = $options['config_model'];
            unset($options['config_model']);
            $alternativeConfigModel = new $alternativeConfigModelName($options);
        } else {
            $alternativeConfigModel = null;
        }

        if (!is_null($alternativeConfigModel) && ($alternativeConfigModel instanceof Mage_Core_Model_Config)) {
            self::$_config = $alternativeConfigModel;
        } else {
            self::$_config = new Mage_Core_Model_Config($options);
        }
    }

    /**
     * Retrieve application installation flag
     *
     * @param string|array $options
     * @return bool
     */
    public static function isInstalled($options = [])
    {
        if (self::$_isInstalled === null) {
            self::setRoot();

            if (is_string($options)) {
                $options = ['etc_dir' => $options];
            }
            $etcDir = self::getRoot() . DS . 'app' . DS . 'etc';
            if (!empty($options['etc_dir'])) {
                $etcDir = $options['etc_dir'];
            }
            $localConfigFile = $etcDir . DS . 'local.xml';

            self::$_isInstalled = false;

            $localXmlContent = $_ENV['MAHO_LOCAL_XML'] ?? $_SERVER['MAHO_LOCAL_XML'] ?? null;
            if (!empty($localXmlContent) && !file_exists($localConfigFile)) {
                @mkdir($etcDir, 0750, true);
                $result = file_put_contents($localConfigFile, $localXmlContent, LOCK_EX);
                if ($result === false) {
                    throw new Exception("Failed to write $localConfigFile.");
                }

                chmod($localConfigFile, 0640);
            }

            if ($localConfig = @simplexml_load_file($localConfigFile)) {
                date_default_timezone_set(Mage_Core_Model_Locale::DEFAULT_TIMEZONE);
                if (($date = $localConfig->global->install->date) && strtotime((string) $date)) {
                    self::$_isInstalled = true;
                }
            }
        }
        return self::$_isInstalled;
    }

    /**
     * log facility (??)
     *
     * @param array|object|string $message
     * @param Level|int|null $level
     * @param string|null $file
     * @param bool $forceLog
     */
    public static function log($message, $level = null, $file = '', $forceLog = false)
    {
        if (!self::getConfig()) {
            return;
        }

        try {
            $logger = self::getModel('core/logger');
            if ($logger !== false) {
                $logger->log($message, $level, $file, $forceLog);
            }
        } catch (Exception $e) {
            // Silently fail to avoid logging loops
        }
    }

    /**
     * Write exception to log
     */
    public static function logException(Throwable $e)
    {
        if (!self::getConfig()) {
            return;
        }
        self::log("\n" . $e->__toString(), self::LOG_ERROR, 'exception.log');
    }

    /**
     * Set enabled developer mode
     *
     * @param bool $mode
     * @return bool
     */
    public static function setIsDeveloperMode($mode)
    {
        self::$_isDeveloperMode = (bool) $mode;
        return self::$_isDeveloperMode;
    }

    /**
     * Retrieve enabled developer mode
     *
     * @return bool
     */
    public static function getIsDeveloperMode()
    {
        return self::$_isDeveloperMode;
    }

    /**
     * Display exception
     */
    public static function printException(Throwable $e, $extra = '')
    {
        if (self::$_isDeveloperMode) {
            print '<pre>';

            if (!empty($extra)) {
                print $extra . "\n\n";
            }

            print $e->getMessage() . "\n\n";
            print $e->getTraceAsString();
            print '</pre>';
        } else {
            $reportData = [
                (empty($extra) ? '' : $extra . "\n\n") . $e->getMessage(),
                $e->getTraceAsString(),
            ];

            // retrieve server data
            if (isset($_SERVER['REQUEST_URI'])) {
                $reportData['url'] = $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $reportData['script_name'] = $_SERVER['SCRIPT_NAME'];
            }

            Maho::errorReport($reportData);
        }

        die();
    }

    /**
     * Define system folder directory url by virtue of running script directory name
     * Try to find requested folder by shifting to domain root directory
     *
     * @param   string  $folder
     * @param   boolean $exitIfNot
     * @return  string
     */
    public static function getScriptSystemUrl($folder, $exitIfNot = false)
    {
        $runDirUrl  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $runDir     = rtrim(dirname($_SERVER['SCRIPT_FILENAME']), DS);

        $baseUrl    = null;
        if (is_dir($runDir . '/' . $folder)) {
            $baseUrl = str_replace(DS, '/', $runDirUrl);
        } else {
            $runDirUrlArray = explode('/', $runDirUrl);
            $runDirArray    = explode('/', $runDir);
            $count          = count($runDirArray);

            for ($i = 0; $i < $count; $i++) {
                array_pop($runDirUrlArray);
                array_pop($runDirArray);
                $_runDir = implode('/', $runDirArray);
                if (!empty($_runDir)) {
                    $_runDir .= '/';
                }

                if (is_dir($_runDir . $folder)) {
                    $_runDirUrl = implode('/', $runDirUrlArray);
                    $baseUrl    = str_replace(DS, '/', $_runDirUrl);
                    break;
                }
            }
        }

        if (is_null($baseUrl)) {
            $errorMessage = "Unable detect system directory: $folder";
            if ($exitIfNot) {
                // exit because of infinity loop
                exit($errorMessage);
            }
            self::printException(new Exception(), $errorMessage);
        }

        return $baseUrl;
    }

    public static function getEncryptionKeyAsHex(): string
    {
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }

    public static function getEncryptionKeyAsBinary(): string
    {
        return sodium_hex2bin(self::getEncryptionKeyAsHex());
    }

    public static function generateEncryptionKeyAsHex(): string
    {
        return sodium_bin2hex(sodium_crypto_secretbox_keygen());
    }
}
