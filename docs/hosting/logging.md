---
description: "Maho's logging is built on Monolog 3.x, keeping Mage::log() compatibility while adding log rotation, multiple handlers and configurable levels."
---

# Logging in Maho <span class="version-badge">v25.9+</span>

Maho uses [Monolog 3.x](https://github.com/Seldaek/monolog) for logging, providing a robust and flexible logging system that maintains backward compatibility with existing `Mage::log()` calls while offering advanced features for modern development.

## Overview

The logging system in Maho provides:

- **Backward compatibility** with existing `Mage::log()` calls
- **Intelligent defaults** for development and production environments
- **Automatic log rotation** in production
- **Multiple handler support** for different output destinations
- **Configurable log levels** for fine-grained control
- **Exception logging** with stack traces and context

## Log Levels

Maho supports 8 log levels, from highest to lowest priority:

1. **Emergency** (`Mage::LOG_EMERGENCY`): System is unusable
2. **Alert** (`Mage::LOG_ALERT`): Immediate action required
3. **Critical** (`Mage::LOG_CRITICAL`): Critical conditions
4. **Error** (`Mage::LOG_ERROR`): Error conditions
5. **Warning** (`Mage::LOG_WARNING`): Warning conditions
6. **Notice** (`Mage::LOG_NOTICE`): Significant normal condition
7. **Info** (`Mage::LOG_INFO`): Informational messages
8. **Debug** (`Mage::LOG_DEBUG`): Debug-level messages

## Basic Usage

### Simple Logging

```php
// Basic logging (uses INFO level by default)
Mage::log('This is a basic log message');

// Specify log level
Mage::log('Error occurred', Mage::LOG_ERROR);
Mage::log('Debug information', Mage::LOG_DEBUG);
Mage::log('Warning message', Mage::LOG_WARNING);

// Log to custom file
Mage::log('Custom log entry', Mage::LOG_INFO, 'custom.log');
```

### Exception Logging

```php
try {
    // Risky code that might throw an exception
    $result = $this->performRiskyOperation();
} catch (Exception $e) {
    // Log the exception with full stack trace
    Mage::logException($e);
    
    // Or log with custom message and level
    Mage::log('Operation failed: ' . $e->getMessage(), Mage::LOG_ERROR);
}
```

### Advanced Logging with Context

```php
// Log with additional context data
Mage::log('User action performed', Mage::LOG_INFO, null, [
    'user_id' => $userId,
    'action' => 'product_view',
    'product_id' => $productId
]);

// Log arrays and objects
$orderData = ['order_id' => 123, 'total' => 99.99];
Mage::log('Order processed', Mage::LOG_INFO, 'orders.log', $orderData);
```

## Configuration

Maho supports two configuration modes for logging:

### 1. Admin Panel Mode (Simple)

Configure logging through the Maho admin panel:

1. Navigate to **System > Configuration > Advanced > Developer > Log Settings**
2. Enable logging and set the desired log level
3. Configure file rotation settings

### 2. XML Mode (Advanced)

For advanced configurations, use XML configuration files in your module or local configuration:

```xml
<config>
    <global>
        <log>
            <handlers>
                <file>
                    <class>Monolog\Handler\RotatingFileHandler</class>
                    <params>
                        <filename>var/log/system.log</filename>
                        <level>DEBUG</level>
                        <maxFiles>14</maxFiles>
                    </params>
                </file>
                <email>
                    <class>Monolog\Handler\SwiftMailerHandler</class>
                    <params>
                        <level>ERROR</level>
                        <to>admin@example.com</to>
                        <subject>Maho Error Alert</subject>
                    </params>
                </email>
            </handlers>
        </log>
    </global>
</config>
```

## Default Behavior

### Development Mode
- Uses `StreamHandler` for single file logging
- All log levels enabled by default
- Logs to `var/log/system.log`

### Production Mode
- Uses `RotatingFileHandler` for automatic rotation
- Daily log rotation with 14-day retention
- ERROR level and above by default
- Automatic cleanup of old log files

## Supported Handlers

Maho supports various Monolog handlers for different logging destinations:

### File Handlers
```xml
<!-- Single file handler -->
<stream>
    <class>Monolog\Handler\StreamHandler</class>
    <params>
        <filename>var/log/application.log</filename>
        <level>INFO</level>
    </params>
</stream>

<!-- Rotating file handler -->
<rotating>
    <class>Monolog\Handler\RotatingFileHandler</class>
    <params>
        <filename>var/log/system.log</filename>
        <level>DEBUG</level>
        <maxFiles>30</maxFiles>
    </params>
</rotating>
```

### System Handlers
```xml
<!-- System log handler -->
<syslog>
    <class>Monolog\Handler\SyslogHandler</class>
    <params>
        <ident>maho</ident>
        <level>WARNING</level>
    </params>
</syslog>

<!-- Error log handler -->
<errorlog>
    <class>Monolog\Handler\ErrorLogHandler</class>
    <params>
        <level>ERROR</level>
    </params>
</errorlog>
```

### Communication Handlers
```xml
<!-- Slack notifications -->
<slack>
    <class>Monolog\Handler\SlackWebhookHandler</class>
    <params>
        <webhookUrl>https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK</webhookUrl>
        <channel>#alerts</channel>
        <level>ERROR</level>
    </params>
</slack>
```

## Migration from Zend_Log

If you're migrating from older Maho versions that used Zend_Log, the transition is seamless:

### Before (Zend_Log)
```php
Mage::log('Message', Zend_Log::INFO);
Mage::log('Error', Zend_Log::ERR, 'custom.log');
```

### After (Monolog - No Changes Required!)
```php
// These calls work exactly the same
Mage::log('Message', Mage::LOG_INFO);
Mage::log('Error', Mage::LOG_ERROR, 'custom.log');
```

## Best Practices

### 1. Use Appropriate Log Levels
```php
// Good: Use specific log levels
Mage::log('User login attempt', Mage::LOG_INFO);
Mage::log('Invalid password', Mage::LOG_WARNING);
Mage::log('Database connection failed', Mage::LOG_ERROR);

// Bad: Everything at same level
Mage::log('User login attempt', Mage::LOG_DEBUG);
Mage::log('Database connection failed', Mage::LOG_DEBUG);
```

### 2. Include Context Information
```php
// Good: Provide context
Mage::log('Payment failed', Mage::LOG_ERROR, 'payment.log', [
    'order_id' => $orderId,
    'payment_method' => $paymentMethod,
    'error_code' => $errorCode
]);

// Bad: Generic message
Mage::log('Payment failed', Mage::LOG_ERROR);
```

### 3. Use Custom Log Files for Modules
```php
// Group related logs together
Mage::log('Inventory updated', Mage::LOG_INFO, 'inventory.log');
Mage::log('Product imported', Mage::LOG_INFO, 'import.log');
Mage::log('Email sent', Mage::LOG_INFO, 'email.log');
```

### 4. Handle Exceptions Properly
```php
try {
    $this->processPayment($order);
} catch (PaymentException $e) {
    Mage::logException($e);
    // Log additional context if helpful
    Mage::log('Payment processing failed for order: ' . $order->getId(), 
        Mage::LOG_ERROR, 'payment.log');
    throw $e; // Re-throw if needed
}
```

### 5. Performance Considerations
```php
// Good: Check if debug logging is enabled before expensive operations
if (Mage::getStoreConfig('dev/log/active')) {
    $debugData = $this->generateExpensiveDebugData();
    Mage::log($debugData, Mage::LOG_DEBUG);
}

// Bad: Always generate debug data
$debugData = $this->generateExpensiveDebugData();
Mage::log($debugData, Mage::LOG_DEBUG);
```

## Troubleshooting

### Common Issues

1. **Logs not appearing**: Check if logging is enabled in admin panel and verify file permissions on `var/log/` directory
2. **Log files growing too large**: Configure log rotation in production environments
3. **Performance impact**: Use appropriate log levels and avoid logging in high-frequency operations

### Debugging Log Configuration
```php
// Check current log configuration
$config = Mage::getConfig()->getNode('global/log');
Mage::log('Current log config: ' . $config->asXML(), Mage::LOG_DEBUG);

// Verify log handlers
$logger = Mage::getModel('core/logger');
Mage::log('Active handlers: ' . count($logger->getHandlers()), Mage::LOG_DEBUG);
```

This comprehensive logging system ensures that your Maho application maintains detailed logs for debugging, monitoring, and auditing purposes while providing the flexibility to customize logging behavior for different environments and requirements.