<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class Mage_Log_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_LOG_ENABLED = 'system/log/enable_log';

    protected $_moduleName = 'Mage_Log';

    /**
     * @var int
     */
    protected $_logLevel;

    /**
     * Allowed extensions that can be used to create a log file
     */
    private $_allowedFileExtensions = ['log', 'txt', 'html', 'csv'];

    /**
     * Logger instances
     */
    private static array $_loggers = [];

    /**
     * Mage_Log_Helper_Data constructor.
     */
    public function __construct(array $data = [])
    {
        $this->_logLevel = $data['log_level'] ?? Mage::getStoreConfigAsInt(self::XML_PATH_LOG_ENABLED);
    }

    /**
     * Are visitor should be logged
     *
     * @return bool
     */
    public function isVisitorLogEnabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_VISITORS
        || $this->isLogEnabled();
    }

    /**
     * Are all events should be logged
     *
     * @return bool
     */
    public function isLogEnabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_ALL;
    }

    /**
     * Are all events should be disabled
     *
     * @return bool
     */
    public function isLogDisabled()
    {
        return $this->_logLevel == Mage_Log_Model_Adminhtml_System_Config_Source_Loglevel::LOG_LEVEL_NONE;
    }

    /**
     * Checking if file extensions is allowed. If passed then return true.
     *
     * @param string $file
     * @return bool
     */
    public function isLogFileExtensionValid($file)
    {
        $result = false;
        $validatedFileExtension = pathinfo($file, PATHINFO_EXTENSION);
        if ($validatedFileExtension && in_array($validatedFileExtension, $this->_allowedFileExtensions)) {
            $result = true;
        }

        return $result;
    }

    public function log(mixed $message, Level|int|null $level = null, string $file = '', bool $forceLog = false): void
    {
        // Check if XML log configuration exists - if so, bypass backend settings
        if (self::isLogConfigManagedByXml()) {
            $logActive = true;
            $maxLogLevel = Mage::LOG_DEBUG; // XML handlers manage their own levels
            if (empty($file)) {
                $file = 'system.log'; // Default file when using XML config
            }
        } else {
            // Use backend configuration when no XML config present
            try {
                $logActive = Mage::getStoreConfig('dev/log/active');
                if (empty($file)) {
                    $file = Mage::getStoreConfig('dev/log/file');
                }
            } catch (Exception $e) {
                $logActive = true;
            }

            if (!Mage::getIsDeveloperMode() && !$logActive && !$forceLog) {
                return;
            }

            try {
                $maxLogLevel = (int) Mage::getStoreConfig('dev/log/max_level');
            } catch (Throwable $e) {
                $maxLogLevel = Mage::LOG_DEBUG;
            }
        }

        $level ??= Mage::LOG_DEBUG;

        if (!Mage::getIsDeveloperMode() && !self::isLogConfigManagedByXml() && !$forceLog) {
            // Convert levels for comparison
            $levelValue = self::convertLogLevel($level);
            $maxLevelValue = self::convertLogLevel($maxLogLevel);
            if ($levelValue->value > $maxLevelValue->value) {
                return;
            }
        }

        if (empty($file)) {
            $file = self::isLogConfigManagedByXml() ?
                'system.log' :
                (string) Mage::getConfig()->getNode('dev/log/file', Mage_Core_Model_Store::DEFAULT_CODE);
        } else {
            $file = basename($file);
        }

        // Get or create logger
        if (!isset(self::$_loggers[$file])) {
            $this->createLogger($file, $maxLogLevel, $forceLog);
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        // Escape PHP tags for security
        $message = str_replace('<?', '< ?', $message);

        // Convert log level and log the message
        $monologLevel = self::convertLogLevel($level);
        self::$_loggers[$file]->log($monologLevel, $message);

        // Auto-flush BrowserConsoleHandler for immediate output
        $this->flushBrowserConsoleHandlers($file);
    }

    protected function createLogger(string $file, Level|int $maxLogLevel, bool $forceLog): void
    {
        // Validate file extension before save - use existing allowedFileExtensions logic
        $_allowedFileExtensions = explode(
            ',',
            (string) Mage::getConfig()->getNode('dev/log/allowedFileExtensions', Mage_Core_Model_Store::DEFAULT_CODE),
        );
        if (!($extension = pathinfo($file, PATHINFO_EXTENSION)) || !in_array($extension, $_allowedFileExtensions)) {
            return;
        }

        $logDir = Mage::getBaseDir('var') . DS . 'log';
        $logFile = $logDir . DS . $file;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $logger = new Logger('Maho');

        // Convert old Zend_Log level to Monolog level for configuration
        $configLevel = self::convertLogLevel($maxLogLevel);
        if ($forceLog || Mage::getIsDeveloperMode()) {
            $configLevel = Level::Debug;
        }

        // Add configured handlers
        static::addConfiguredHandlers($logger, $logFile, $configLevel);

        self::$_loggers[$file] = $logger;

        // Set file permissions (only for non-rotating handlers)
        if (!static::isRotatingFileHandler($logger) && !file_exists($logFile)) {
            touch($logFile);
            chmod($logFile, 0640);
        }
    }

    /**
     * Add configured handlers from XML to logger
     */
    protected static function addConfiguredHandlers(Logger $logger, string $logFile, Level $defaultLevel): void
    {
        $config = Mage::getConfig();
        if (!$config) {
            // Fallback handler based on developer mode
            $handler = self::createDefaultMonologHandler($logFile, $defaultLevel);
            $logger->pushHandler($handler);
            return;
        }

        $handlers = $config->getNode('global/log/handlers');
        if (!$handlers) {
            // Fallback handler based on developer mode
            $handler = self::createDefaultMonologHandler($logFile, $defaultLevel);
            $logger->pushHandler($handler);
            return;
        }

        $hasActiveHandler = false;
        foreach ($handlers->children() as $handlerName => $handlerConfig) {
            $handler = self::createMonologHandler($handlerName, $handlerConfig, $logFile, $defaultLevel);
            $logger->pushHandler($handler);
            $hasActiveHandler = true;
        }

        // Always ensure at least one handler is active
        if (!$hasActiveHandler) {
            $handler = self::createDefaultMonologHandler($logFile, $defaultLevel);
            $logger->pushHandler($handler);
        }
    }

    /**
     * Create a handler based on configuration using reflection
     */
    protected static function createMonologHandler(string $name, object $config, string $logFile, Level $defaultLevel): object
    {
        $className = (string) $config->class;

        // Validate class name
        if (!class_exists($className)) {
            throw new Mage_Core_Exception(
                "Log handler '{$name}' class does not exist: {$className}. Please check if the required package is installed.",
            );
        }

        // Validate it's a Monolog handler
        if (!is_subclass_of($className, \Monolog\Handler\HandlerInterface::class)) {
            throw new Mage_Core_Exception(
                "Log handler '{$name}' class {$className} must implement Monolog\Handler\HandlerInterface",
            );
        }

        try {
            $reflection = new ReflectionClass($className);

            // Get constructor parameters
            $constructor = $reflection->getConstructor();
            if (!$constructor) {
                return new $className();
            }

            $args = self::buildHandlerConstructorArgs($constructor, $config, $logFile, $defaultLevel);
            /** @var \Monolog\Handler\HandlerInterface $handler */
            $handler = $reflection->newInstanceArgs($args);

            // Apply custom formatter only to file-based handlers (not browser console)
            if (method_exists($handler, 'setFormatter') && 
                !($handler instanceof \Monolog\Handler\BrowserConsoleHandler)) {
                $handler->setFormatter(self::createMonologFormatter());
            }

            return $handler;
        } catch (Exception $e) {
            throw new Mage_Core_Exception(
                sprintf(
                    "Failed to create log handler '%s' of type %s. Error: %s. Check handler configuration parameters in XML.",
                    $name,
                    $className,
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Build constructor arguments for handler using reflection and configuration
     */
    protected static function buildHandlerConstructorArgs(ReflectionMethod $constructor, object $config, string $logFile, Level $defaultLevel): array
    {
        $args = [];
        $params = $constructor->getParameters();

        foreach ($params as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();

            // Handle common parameter patterns
            try {
                $value = match ($paramName) {
                    'stream', 'filename', 'file', 'path' => $logFile,
                    'level' => isset($config->params->level) ?
                        Level::fromName((string) $config->params->level) : Level::Debug,
                    'bubble' => isset($config->params->bubble) ?
                        (bool) $config->params->bubble : true,
                    'dateFormat' => isset($config->params->dateFormat) ?
                        (string) $config->params->dateFormat : RotatingFileHandler::FILE_PER_DAY,
                    'filenameFormat' => isset($config->params->filenameFormat) ?
                        (string) $config->params->filenameFormat : '{filename}-{date}',
                    default => self::getHandlerConfigValue($config, $paramName, $param),
                };
            } catch (Exception $e) {
                // Handle invalid level names with a more descriptive error
                if ($paramName === 'level' && isset($config->params->level)) {
                    throw new Mage_Core_Exception(
                        "Invalid log level '{$config->params->level}' for parameter '{$paramName}'. Valid levels are: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY",
                        0,
                        $e,
                    );
                }
                throw $e;
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * Get configuration value with type conversion
     */
    protected static function getHandlerConfigValue(object $config, string $paramName, ReflectionParameter $param): mixed
    {
        $configValue = $config->params->{$paramName} ?? null;

        if ($configValue === null) {
            return $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        $paramType = $param->getType();
        if (!$paramType) {
            return (string) $configValue;
        }

        // Type conversion based on parameter type
        $typeName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'mixed';
        return match ($typeName) {
            'int' => (int) $configValue,
            'float' => (float) $configValue,
            'bool' => (bool) $configValue,
            'array' => is_array($configValue) ? $configValue : [(string) $configValue],
            default => (string) $configValue,
        };
    }

    /**
     * Create a custom log formatter that matches the old Zend_Log format
     */
    protected static function createMonologFormatter(): LineFormatter
    {
        // Format: timestamp level_name: message
        $format = "%datetime% %level_name%: %message%\n";
        $dateFormat = 'Y-m-d H:i:s';

        $formatter = new LineFormatter($format, $dateFormat);

        // Don't include context and extra data (removes the empty [] [])
        $formatter->includeStacktraces(false);

        return $formatter;
    }

    /**
     * Create the default handler based on developer mode
     */
    protected static function createDefaultMonologHandler(string $logFile, Level $defaultLevel): object
    {
        $isDeveloperMode = Mage::getIsDeveloperMode();

        if ($isDeveloperMode) {
            // Development: StreamHandler for immediate, simple logging
            $handler = new StreamHandler($logFile, $defaultLevel);
        } else {
            // Production: RotatingFileHandler with 14-day retention
            $handler = new RotatingFileHandler($logFile, 14, $defaultLevel);
        }

        $handler->setFormatter(self::createMonologFormatter());
        return $handler;
    }

    /**
     * Flush BrowserConsoleHandler to ensure immediate output
     */
    protected function flushBrowserConsoleHandlers(string $file): void
    {
        if (!isset(self::$_loggers[$file])) {
            return;
        }

        foreach (self::$_loggers[$file]->getHandlers() as $handler) {
            if ($handler instanceof \Monolog\Handler\BrowserConsoleHandler) {
                $handler->send();
            }
        }
    }

    /**
     * Check if logger has a RotatingFileHandler
     */
    protected static function isRotatingFileHandler(Logger $logger): bool
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof \Monolog\Handler\RotatingFileHandler) {
                return true;
            }
        }
        return false;
    }


    /**
     * Check if XML log configuration is managed by XML
     */
    public static function isLogConfigManagedByXml(): bool
    {
        $config = Mage::getConfig();
        if (!$config) {
            return false;
        }

        $handlers = $config->getNode('global/log/handlers');
        return $handlers && $handlers->hasChildren();
    }

    /**
     * Convert log level constants to Monolog Level objects
     */
    protected static function convertLogLevel(Level|int|null $level): Level
    {
        // If it's already a Level enum, return it
        if ($level instanceof Level) {
            return $level;
        }

        // Handle legacy integer values and null
        if (is_int($level)) {
            return match ($level) {
                0 => Level::Emergency,
                1 => Level::Alert,
                2 => Level::Critical,
                3 => Level::Error,
                4 => Level::Warning,
                5 => Level::Notice,
                6 => Level::Info,
                7 => Level::Debug,
                default => Level::Debug,
            };
        }

        // At this point, $level must be null (since we handled Level and int)
        return Level::Debug;
    }
}
