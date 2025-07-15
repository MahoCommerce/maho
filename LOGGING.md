# Logging in Maho

Maho uses [Monolog](https://github.com/Seldaek/monolog) for comprehensive logging capabilities. This document explains how to configure and use the logging system.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Configuration](#configuration)
- [Available Handlers](#available-handlers)
- [Advanced Examples](#advanced-examples)
- [Migration from Zend_Log](#migration-from-zend_log)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

Maho's logging system is built on Monolog 3.x and provides:

- **Backward compatibility** with existing `Mage::log()` calls
- **Multiple handlers** for different output destinations
- **Configurable log levels** for fine-grained control
- **Generic handler support** for any Monolog handler
- **Type-safe configuration** with automatic parameter conversion

## Basic Usage

### Log Levels

Maho supports 8 log levels (from highest to lowest priority):

| Constant | Value | Description |
|----------|-------|-------------|
| `Mage::LOG_EMERG` | 0 | Emergency: system is unusable |
| `Mage::LOG_ALERT` | 1 | Alert: action must be taken immediately |
| `Mage::LOG_CRIT` | 2 | Critical: critical conditions |
| `Mage::LOG_ERR` | 3 | Error: error conditions |
| `Mage::LOG_WARN` | 4 | Warning: warning conditions |
| `Mage::LOG_NOTICE` | 5 | Notice: normal but significant condition |
| `Mage::LOG_INFO` | 6 | Informational: informational messages |
| `Mage::LOG_DEBUG` | 7 | Debug: debug-level messages |

### Simple Logging

```php
// Basic logging
Mage::log('This is a debug message');
Mage::log('This is an error', Mage::LOG_ERR);
Mage::log('Custom file message', Mage::LOG_INFO, 'custom.log');

// Force logging (ignores level restrictions)
Mage::log('Important message', Mage::LOG_DEBUG, 'debug.log', true);

// Exception logging
try {
    // Some code that might throw an exception
} catch (Exception $e) {
    Mage::logException($e);
}

// Log adapter for filtered logging
$adapter = Mage::getModel('core/log_adapter', 'payment.log');
$adapter->setFilterDataKeys(['password', 'secret']);
$adapter->setData(['user' => 'john', 'password' => 'secret123']);
$adapter->log();
```

## Configuration

### Configuration Modes

Maho supports two configuration modes for logging:

1. **Admin Panel Mode** - Simple configuration via System → Configuration → Advanced → Developer → Log Settings
2. **XML Mode** - Advanced configuration via XML files (local.xml)

**Important**: When XML log configuration is present, the admin panel logging section is automatically hidden to prevent conflicts.

### Basic Configuration

Add logging configuration to your `app/etc/local.xml`:

```xml
<?xml version="1.0"?>
<config>
    <global>
        <log>
            <handlers>
                <file>
                    <class>Monolog\Handler\StreamHandler</class>
                    <enabled>1</enabled>
                    <params>
                        <level>DEBUG</level>
                    </params>
                </file>
            </handlers>
        </log>
    </global>
</config>
```

### Handler Configuration Structure

Each handler follows this structure:

```xml
<handler_name>
    <class>Full\Class\Name</class>
    <enabled>1</enabled> <!-- 1 = enabled, 0 = disabled -->
    <params>
        <level>DEBUG</level>
        <param1>value1</param1>
        <param2>value2</param2>
        <!-- Parameters map to constructor arguments -->
    </params>
</handler_name>
```

### Parameter Mapping

The system automatically maps XML parameters to constructor arguments:

- **Common parameters** like `level`, `stream`, `filename`, `file`, `path` are handled automatically
- **Type conversion** is performed based on parameter types (int, bool, array, etc.)
- **Default values** are used when parameters are not specified

## Available Handlers

### File Handlers

#### StreamHandler (Default)
Writes logs to a file stream.

```xml
<file>
    <class>Monolog\Handler\StreamHandler</class>
    <enabled>1</enabled>
    <params>
        <level>DEBUG</level>
    </params>
</file>
```

#### RotatingFileHandler
Automatically rotates log files daily.

```xml
<rotating>
    <class>Monolog\Handler\RotatingFileHandler</class>
    <enabled>1</enabled>
    <params>
        <level>DEBUG</level>
        <maxFiles>30</maxFiles>
    </params>
</rotating>
```

### System Handlers

#### SyslogHandler
Sends logs to system syslog.

```xml
<syslog>
    <class>Monolog\Handler\SyslogHandler</class>
    <enabled>1</enabled>
    <params>
        <level>WARNING</level>
        <ident>maho</ident>
        <facility>128</facility> <!-- LOG_USER = 128 -->
    </params>
</syslog>
```

#### ErrorLogHandler
Writes to PHP's error log.

```xml
<errorlog>
    <class>Monolog\Handler\ErrorLogHandler</class>
    <enabled>1</enabled>
    <params>
        <level>ERROR</level>
        <messageType>0</messageType>
    </params>
</errorlog>
```

### Communication Handlers

#### SlackWebhookHandler
Sends logs to Slack channels.

```xml
<slack>
    <class>Monolog\Handler\SlackWebhookHandler</class>
    <enabled>1</enabled>
    <params>
        <level>ERROR</level>
        <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
        <channel>#alerts</channel>
        <username>Maho</username>
        <useAttachment>true</useAttachment>
        <iconEmoji>:warning:</iconEmoji>
    </params>
</slack>
```

#### TelegramBotHandler
Sends logs to Telegram chats.

```xml
<telegram>
    <class>Monolog\Handler\TelegramBotHandler</class>
    <enabled>1</enabled>
    <params>
        <level>CRITICAL</level>
        <apiKey>YOUR_BOT_API_KEY</apiKey>
        <channel>@your_channel_or_chat_id</channel>
    </params>
</telegram>
```

#### NativeMailerHandler
Sends logs via email.

```xml
<email>
    <class>Monolog\Handler\NativeMailerHandler</class>
    <enabled>1</enabled>
    <params>
        <level>CRITICAL</level>
        <to>admin@example.com</to>
        <subject>Critical Error in Maho</subject>
        <from>noreply@example.com</from>
    </params>
</email>
```

### Development Handlers

#### BrowserConsoleHandler
Outputs logs to browser console.

```xml
<browser>
    <class>Monolog\Handler\BrowserConsoleHandler</class>
    <enabled>1</enabled>
    <params>
        <level>DEBUG</level>
    </params>
</browser>
```

### Database Handlers

#### RedisHandler
Stores logs in Redis.

```xml
<redis>
    <class>Monolog\Handler\RedisHandler</class>
    <enabled>1</enabled>
    <params>
        <level>INFO</level>
        <!-- Additional Redis configuration needed -->
    </params>
</redis>
```

#### MongoDBHandler
Stores logs in MongoDB.

```xml
<mongodb>
    <class>Monolog\Handler\MongoDBHandler</class>
    <enabled>1</enabled>
    <params>
        <level>INFO</level>
        <!-- MongoDB configuration needed -->
    </params>
</mongodb>
```

## Advanced Examples

### Multi-Handler Setup

```xml
<log>
    <handlers>
        <!-- File logging for all levels -->
        <file>
            <class>Monolog\Handler\StreamHandler</class>
            <enabled>1</enabled>
            <params>
                <level>DEBUG</level>
            </params>
        </file>
        
        <!-- Slack for errors -->
        <slack>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            <enabled>1</enabled>
            <params>
                <level>ERROR</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#alerts</channel>
            </params>
        </slack>
        
        <!-- Email for critical issues -->
        <email>
            <class>Monolog\Handler\NativeMailerHandler</class>
            <enabled>1</enabled>
            <params>
                <level>CRITICAL</level>
                <to>admin@example.com</to>
                <subject>CRITICAL: Maho System Alert</subject>
            </params>
        </email>
    </handlers>
</log>
```

### Multiple Handlers of Same Type

You can configure multiple handlers of the same type for different purposes. For example, multiple Slack handlers for different alert levels:

```xml
<log>
    <handlers>
        <!-- File logging for all levels -->
        <file>
            <class>Monolog\Handler\StreamHandler</class>
            <enabled>1</enabled>
            <params>
                <level>DEBUG</level>
            </params>
        </file>
        
        <!-- Slack for general errors - #alerts channel -->
        <slack_errors>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            <enabled>1</enabled>
            <params>
                <level>ERROR</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#alerts</channel>
                <username>Maho-Errors</username>
                <iconEmoji>:warning:</iconEmoji>
            </params>
        </slack_errors>
        
        <!-- Slack for critical issues - #critical channel -->
        <slack_critical>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            <enabled>1</enabled>
            <params>
                <level>CRITICAL</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#critical</channel>
                <username>Maho-Critical</username>
                <iconEmoji>:fire:</iconEmoji>
            </params>
        </slack_critical>
        
        <!-- Slack for warnings - #warnings channel -->
        <slack_warnings>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            <enabled>1</enabled>
            <params>
                <level>WARNING</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#warnings</channel>
                <username>Maho-Warnings</username>
                <iconEmoji>:yellow_circle:</iconEmoji>
            </params>
        </slack_warnings>
    </handlers>
</log>
```

**How level filtering works:**
- `WARNING` level logs go to: file + #warnings + #alerts + #critical
- `ERROR` level logs go to: file + #alerts + #critical  
- `CRITICAL` level logs go to: file + #critical only
- `INFO` level logs go to: file only

### Specialized Alert Channels

You can also set up handlers for different types of alerts:

```xml
<handlers>
    <!-- Default file logging -->
    <file>
        <class>Monolog\Handler\StreamHandler</class>
        <enabled>1</enabled>
        <params>
            <level>DEBUG</level>
        </params>
    </file>
    
    <!-- Payment issues - dedicated channel -->
    <slack_payments>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        <enabled>1</enabled>
        <params>
            <level>ERROR</level>
            <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
            <channel>#payments</channel>
            <username>Maho-Payments</username>
            <iconEmoji>:credit_card:</iconEmoji>
        </params>
    </slack_payments>
    
    <!-- Security issues - high priority channel -->
    <slack_security>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        <enabled>1</enabled>
        <params>
            <level>WARNING</level>
            <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
            <channel>#security</channel>
            <username>Maho-Security</username>
            <iconEmoji>:shield:</iconEmoji>
        </params>
    </slack_security>
    
    <!-- Emergency alerts -->
    <slack_emergency>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        <enabled>1</enabled>
        <params>
            <level>EMERGENCY</level>
            <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
            <channel>#emergency</channel>
            <username>Maho-EMERGENCY</username>
            <iconEmoji>:rotating_light:</iconEmoji>
        </params>
    </slack_emergency>
</handlers>
```

### Custom Handler

You can use any Monolog handler by specifying its full class name:

```xml
<custom>
    <class>Your\Custom\MonologHandler</class>
    <enabled>1</enabled>
    <params>
        <level>INFO</level>
        <customParam>value</customParam>
        <numericParam>123</numericParam>
        <boolParam>true</boolParam>
    </params>
</custom>
```

### Environment-Specific Configuration

```xml
<!-- Production: File + Slack for errors -->
<handlers>
    <file>
        <class>Monolog\Handler\RotatingFileHandler</class>
        <enabled>1</enabled>
        <params>
            <level>WARNING</level>
            <maxFiles>30</maxFiles>
        </params>
    </file>
    <slack>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        <enabled>1</enabled>
        <params>
            <level>ERROR</level>
            <webhookUrl>https://hooks.slack.com/services/PROD/WEBHOOK/URL</webhookUrl>
        </params>
    </slack>
</handlers>
```

```xml
<!-- Development: File + Browser console -->
<handlers>
    <file>
        <class>Monolog\Handler\StreamHandler</class>
        <enabled>1</enabled>
        <params>
            <level>DEBUG</level>
        </params>
    </file>
    <browser>
        <class>Monolog\Handler\BrowserConsoleHandler</class>
        <enabled>1</enabled>
        <params>
            <level>DEBUG</level>
        </params>
    </browser>
</handlers>
```

## Migration from Zend_Log

### Automatic Migration

The system automatically handles the migration:

- **Old constants** (`Zend_Log::ERR`) are replaced with `Mage::LOG_ERR`
- **Same method signatures** for `Mage::log()` and `Mage::logException()`
- **Same log format** by default (can be customized)
- **Same file permissions** (640 for files, 750 for directories)

### Updated Constants

| Old Zend_Log | New Mage | Value |
|--------------|----------|-------|
| `Zend_Log::EMERG` | `Mage::LOG_EMERG` | 0 |
| `Zend_Log::ALERT` | `Mage::LOG_ALERT` | 1 |
| `Zend_Log::CRIT` | `Mage::LOG_CRIT` | 2 |
| `Zend_Log::ERR` | `Mage::LOG_ERR` | 3 |
| `Zend_Log::WARN` | `Mage::LOG_WARN` | 4 |
| `Zend_Log::NOTICE` | `Mage::LOG_NOTICE` | 5 |
| `Zend_Log::INFO` | `Mage::LOG_INFO` | 6 |
| `Zend_Log::DEBUG` | `Mage::LOG_DEBUG` | 7 |

## Best Practices

### 1. Log Levels

- Use **DEBUG** for detailed diagnostic information
- Use **INFO** for general application flow
- Use **WARNING** for potentially harmful situations
- Use **ERROR** for error conditions that allow the application to continue
- Use **CRITICAL** for serious errors that require immediate attention

### 2. Performance

- Use appropriate log levels to avoid excessive logging in production
- Consider using `RotatingFileHandler` to prevent log files from growing too large
- Use level filtering to send only relevant messages to external services

### 3. Security

- Never log sensitive information (passwords, API keys, personal data)
- Use the log adapter's `setFilterDataKeys()` to automatically filter sensitive data
- Consider using separate log files for different types of information

### 4. Monitoring

- Set up different handlers for different severity levels
- Use external services (Slack, email) for critical errors
- Monitor log file sizes and rotation

### 5. Multiple Handlers

- **Use unique handler names** - Each handler must have a unique name (e.g., `slack_errors`, `slack_critical`)
- **Understand level filtering** - Handlers process messages at or above their configured level
- **Avoid duplicate notifications** - Consider that multiple handlers may trigger for the same log entry
- **Group by purpose** - Use descriptive names that indicate the handler's purpose

### 6. Configuration Override

- **XML overrides admin** - When XML log configuration exists, admin panel settings are ignored
- **All-or-nothing** - If any XML handlers are configured, all backend settings are bypassed
- **Admin section hidden** - The logging section disappears from admin when XML config is detected
- **Clear separation** - Use admin for simple setups, XML for advanced configurations

## Troubleshooting

### Common Issues

#### Handler Not Working

1. **Check class exists**: Ensure the handler class is available
2. **Check parameters**: Verify parameter names match constructor arguments
3. **Check dependencies**: Some handlers require additional packages
4. **Check permissions**: Ensure log directory is writable

#### Log Files Not Created

1. **Check directory permissions**: `var/log/` must be writable
2. **Check file extensions**: Only `.log`, `.txt`, `.html`, `.csv` are allowed
3. **Check configuration**: Verify handler is enabled and configured correctly

#### External Services Not Receiving Logs

1. **Check network connectivity**: Ensure server can reach external services
2. **Check credentials**: Verify API keys, webhooks, etc. are correct
3. **Check log levels**: Ensure messages meet the minimum level requirement

### Debug Configuration

To debug logging configuration issues:

```php
// Enable error reporting for logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test logging
Mage::log('Test message', Mage::LOG_DEBUG, 'debug.log', true);
```

### Log File Locations

- **Default location**: `var/log/`
- **System logs**: `var/log/system.log`
- **Exception logs**: `var/log/exception.log`
- **Custom logs**: `var/log/[filename].log`

## Support

For issues with the logging system:

1. Check the [Maho documentation](https://docs.mahocommerce.com)
2. Review the [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/README.md)
3. Submit issues to the [Maho GitHub repository](https://github.com/MahoCommerce/maho)