<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class Mage_Core_Model_Logger
{
    /**
     * Logger instances
     */
    private static array $_loggers = [];

    /**
     * Log wrapper
     *
     * @param mixed $message
     * @param Level|int|null $level
     * @param string $file
     * @param bool $forceLog
     */
    public function log($message, $level = null, $file = '', $forceLog = false)
    {
        // Check if XML log configuration exists - if so, bypass backend settings
        if (self::isLogConfigManagedByXml()) {
            $logActive = true;
            $minLogLevel = Mage::LOG_DEBUG; // XML handlers manage their own levels
            if (empty($file)) {
                $file = 'system.log'; // Default file when using XML config
            }
        } else {
            // Use backend configuration when no XML config present
            try {
                $logActive = Mage::getStoreConfig('dev/log/active');
            } catch (Exception $e) {
                $logActive = true;
            }

            if (!Mage::getIsDeveloperMode() && !$logActive && !$forceLog) {
                return;
            }

            try {
                $minLogLevel = (int) Mage::getStoreConfig('dev/log/min_level');
            } catch (Throwable $e) {
                $minLogLevel = Mage::LOG_DEBUG;
            }
        }

        $level ??= Mage::LOG_DEBUG;

        if (!Mage::getIsDeveloperMode() && !$logActive && !$forceLog) {
            return;
        }

        if (!Mage::getIsDeveloperMode() && !$forceLog) {
            // Convert levels for comparison
            $levelValue = self::convertLogLevel($level);
            $minLevelValue = self::convertLogLevel($minLogLevel);
            if ($levelValue->value < $minLevelValue->value) {
                return;
            }
        }

        if (empty($file)) {
            $file = 'system.log';
        } else {
            $file = basename($file);
        }

        // Get or create logger
        if (!isset(self::$_loggers[$file])) {
            $this->createLogger($file, $minLogLevel, $forceLog);
        }

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        // Escape PHP tags for security
        $message = str_replace('<?', '< ?', $message);

        // Convert log level and log the message
        $monologLevel = self::convertLogLevel($level);
        self::$_loggers[$file]->log($monologLevel, $message);
    }

    /**
     * Log exception wrapper
     */
    public function logException(Throwable $e)
    {
        Mage::logException($e);
    }

    protected function createLogger(string $file, Level|int $minLogLevel, bool $forceLog): void
    {
        $logDir = Mage::getBaseDir('var') . DS . 'log';
        $logFile = $logDir . DS . $file;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $logger = new Logger('Maho');

        // Convert old Zend_Log level to Monolog level for configuration
        $configLevel = self::convertLogLevel($minLogLevel);
        if ($forceLog || Mage::getIsDeveloperMode()) {
            $configLevel = Level::Debug;
        }

        // Add configured handlers
        static::addConfiguredHandlers($logger, $logFile, $configLevel);

        // OpenTelemetry: Add trace context processor if tracer is available
        $tracer = Mage::getTracer();
        if ($tracer && $tracer->isEnabled()) {
            try {
                // TraceContext processor adds trace_id and span_id to all log records
                $logger->pushProcessor(new Maho_OpenTelemetry_Handler_TraceContext($tracer));
            } catch (\Throwable $e) {
                // Silently fail - telemetry should never break logging
                // Use error_log() to avoid re-entrant createLogger() → stack overflow
                error_log('Failed to add trace context processor: ' . $e->getMessage());
            }
        }

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

            // Apply custom formatter to all handlers that support it
            if (method_exists($handler, 'setFormatter')) {
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
                        "Invalid log level '{$config->params->level}'. Use one of: debug, info, notice, warning, error, critical, alert, emergency (case-insensitive)",
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
            'array' => self::convertToArray($configValue),
            default => (string) $configValue,
        };
    }

    /**
     * Convert XML configuration value to array
     */
    protected static function convertToArray(mixed $value): array
    {
        // Already an array
        if (is_array($value)) {
            return $value;
        }

        // SimpleXMLElement with children
        if ($value instanceof SimpleXMLElement) {
            $result = [];

            // Check if it has child elements with the same name (like multiple <recipient> tags)
            foreach ($value->children() as $childName => $child) {
                // If there are multiple children with the same name, collect them all
                if (isset($value->$childName)) {
                    foreach ($value->$childName as $item) {
                        $result[] = (string) $item;
                    }
                    return $result;
                }
            }

            // Otherwise, treat it as a single value
            return [(string) $value];
        }

        // Single value - convert to array
        return [(string) $value];
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
