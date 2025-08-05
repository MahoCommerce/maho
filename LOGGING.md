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
- **Intelligent defaults** - StreamHandler in development, RotatingFileHandler in production
- **Automatic log rotation** with 14-day retention in production mode
- **Multiple handlers** for different output destinations
- **Configurable log levels** for fine-grained control
- **Generic handler support** for any Monolog handler with primitive parameters
- **Type-safe configuration** with automatic parameter conversion

## Basic Usage

### Log Levels

Maho supports 8 log levels (from highest to lowest priority):

| Constant | Monolog Level | Description |
|----------|---------------|-------------|
| `Mage::LOG_EMERGENCY` | `Level::Emergency` | Emergency: system is unusable |
| `Mage::LOG_ALERT` | `Level::Alert` | Alert: action must be taken immediately |
| `Mage::LOG_CRITICAL` | `Level::Critical` | Critical: critical conditions |
| `Mage::LOG_ERROR` | `Level::Error` | Error: error conditions |
| `Mage::LOG_WARNING` | `Level::Warning` | Warning: warning conditions |
| `Mage::LOG_NOTICE` | `Level::Notice` | Notice: normal but significant condition |
| `Mage::LOG_INFO` | `Level::Info` | Informational: informational messages |
| `Mage::LOG_DEBUG` | `Level::Debug` | Debug: debug-level messages |

**Legacy Constants (Deprecated):**
| Legacy Constant | New Constant | 
|----------------|--------------|
| `Mage::LOG_EMERG` | `Mage::LOG_EMERGENCY` |
| `Mage::LOG_CRIT` | `Mage::LOG_CRITICAL` |
| `Mage::LOG_ERR` | `Mage::LOG_ERROR` |
| `Mage::LOG_WARN` | `Mage::LOG_WARNING` |

**Note:** Maho constants now use Monolog Level enums instead of integers for better type safety. Legacy integer values (0-7) are still supported for backwards compatibility. All logs are created with channel name "Maho" for proper branding in browser console output.

### Simple Logging

```php
// Basic logging
Mage::log('This is a debug message');
Mage::log('This is an error', Mage::LOG_ERROR);
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

### Controlling Default Handler Behavior

The default logging behavior depends on developer mode:

```php
// Check current mode
$isDeveloperMode = Mage::getIsDeveloperMode();

// Enable developer mode (uses StreamHandler)
Mage::setIsDeveloperMode(true);

// Disable developer mode (uses RotatingFileHandler)  
Mage::setIsDeveloperMode(false);
```

**When to use each mode:**
- **Development**: Use StreamHandler (`system.log`) for immediate, simple logging during development
- **Production**: Use RotatingFileHandler (`system-YYYY-MM-DD.log`) for organized, space-efficient logging

### Basic Configuration

Add logging configuration to your `app/etc/local.xml`:

```xml
<?xml version="1.0"?>
<config>
    <global>
        <log>
            <handlers>
                <!-- Simple: All log levels, 14-day rotation -->
                <file>
                    <class>Monolog\Handler\RotatingFileHandler</class>
                </file>
                
                <!-- Advanced: Custom level and retention -->
                <file_custom>
                    <class>Monolog\Handler\RotatingFileHandler</class>
                    <params>
                        <level>WARNING</level>
                        <maxFiles>30</maxFiles>
                    </params>
                </file_custom>
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
    <params>
        <level>WARNING</level>  <!-- Optional: defaults to DEBUG (all levels) -->
        <param1>value1</param1>
        <param2>value2</param2>
        <!-- Parameters map to constructor arguments -->
    </params>
</handler_name>
```

**Simple Configuration (no parameters needed):**
```xml
<handler_name>
    <class>Full\Class\Name</class>
</handler_name>
```

**Important Notes:**
- All configured handlers are automatically enabled. To disable a handler, simply remove it from the configuration.
- **Default log level is DEBUG** - if no `<level>` is specified, the handler will capture all log messages
- Empty `<params>` section can be omitted entirely for minimal configuration

### Parameter Mapping

The system automatically maps XML parameters to constructor arguments:

- **Common parameters** like `level`, `stream`, `filename`, `file`, `path` are handled automatically
- **Type conversion** is performed based on parameter types (int, bool, array, etc.)
- **Default values** are used when parameters are not specified

## Available Handlers

Maho's XML configuration system supports handlers that use **primitive parameters only** (strings, integers, booleans, arrays). Handlers requiring complex objects must be configured manually in custom code.

### ✅ **Supported Handlers (XML Configuration)**

These handlers work out-of-the-box with XML configuration because they only require primitive parameters (strings, integers, booleans, arrays):

**💡 Quick Start:** Most handlers work with just the class name - no parameters needed! Default level is **DEBUG** (captures all messages).

**File Handlers:**
- `StreamHandler` - Write to single file
- `RotatingFileHandler` - Daily rotation with automatic cleanup

**System Handlers:**  
- `SyslogHandler` - System syslog integration
- `ErrorLogHandler` - PHP error log integration

**Communication Handlers:**
- `SlackWebhookHandler` - Slack notifications
- `TelegramBotHandler` - Telegram notifications  
- `NativeMailerHandler` - Email via PHP mail()

**Development Handlers:**
- `BrowserConsoleHandler` - Browser console output

### File Handlers

#### RotatingFileHandler (Default)
Automatically rotates log files daily and keeps logs for 14 days by default.

```xml
<!-- Simple: All levels, default 14-day retention -->
<file>
    <class>Monolog\Handler\RotatingFileHandler</class>
</file>

<!-- Custom: Specific level and retention -->
<file>
    <class>Monolog\Handler\RotatingFileHandler</class>
    <params>
        <level>WARNING</level>
        <maxFiles>30</maxFiles>
    </params>
</file>
```

#### StreamHandler
Writes logs to a single file (no rotation).

```xml
<!-- Simple: All levels -->
<file>
    <class>Monolog\Handler\StreamHandler</class>
</file>

<!-- Custom: Specific level only -->
<file>
    <class>Monolog\Handler\StreamHandler</class>
    <params>
        <level>ERROR</level>
    </params>
</file>
```

**Default Behavior:**
When no XML configuration is present or no handlers are configured, Maho automatically selects the appropriate handler based on developer mode:

**Development Mode (`Mage::getIsDeveloperMode() === true`):**
- **StreamHandler**: Simple, immediate logging to a single file
- **File**: `system.log` (no rotation)
- **Benefits**: Immediate writes, easier debugging, simpler log management

**Production Mode (default):**
- **RotatingFileHandler**: Professional log management with rotation
- **Daily rotation**: New log file created each day  
- **14-day retention**: Automatically deletes logs older than 14 days
- **Naming pattern**: `system-2025-08-05.log` (dated files)
- **Benefits**: Prevents disk space issues, organized by date

### System Handlers

#### SyslogHandler
Sends logs to system syslog.

```xml
<syslog>
    <class>Monolog\Handler\SyslogHandler</class>
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
Outputs logs to browser console (useful for development).

```xml
<!-- Simple: All log levels -->
<browser>
    <class>Monolog\Handler\BrowserConsoleHandler</class>
</browser>

<!-- Custom: Specific level -->
<browser>
    <class>Monolog\Handler\BrowserConsoleHandler</class>
    <params>
        <level>ERROR</level>
    </params>
</browser>
```

### ❌ **Unsupported Handlers (Manual Setup Required)**

These handlers require complex object dependencies (not primitive parameters) and cannot be configured via XML:

- **RedisHandler** - Requires Redis client object (`Predis\Client` or `Redis`)
- **SymfonyMailerHandler** - Requires Mailer and Email objects  
- **MongoDBHandler** - Requires MongoDB client object
- **ElasticsearchHandler** - Requires Elasticsearch client object
- **AmqpHandler** - Requires AMQP connection object
- **RabbitMqHandler** - Requires RabbitMQ connection object

For these handlers, configure them manually in custom code. For email logging, use **NativeMailerHandler** instead (XML-configurable).

## Multiple Handler Support

Maho supports multiple handlers simultaneously. Each handler processes logs **at or above** its configured level, creating a cascading alert system.

### Basic Multi-Handler Example

```xml
<log>
    <handlers>
        <!-- File logging for all levels -->
        <file>
            <class>Monolog\Handler\RotatingFileHandler</class>
            <params>
                <level>DEBUG</level>
                <maxFiles>14</maxFiles>
            </params>
        </file>
        
        <!-- Slack alerts for errors -->
        <slack>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            <params>
                <level>ERROR</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#alerts</channel>
            </params>
        </slack>
        
        <!-- Email for critical issues -->
        <email>
            <class>Monolog\Handler\NativeMailerHandler</class>
            <params>
                <level>CRITICAL</level>
                <to>admin@example.com</to>
                <subject>CRITICAL: Maho System Alert</subject>
            </params>
        </email>
    </handlers>
</log>
```

## Advanced Examples

### Multi-Handler Setup

```xml
<log>
    <handlers>
        <!-- Rotating file logging for all levels -->
        <file>
            <class>Monolog\Handler\RotatingFileHandler</class>
            
            <params>
                <level>DEBUG</level>
                <maxFiles>14</maxFiles>
            </params>
        </file>
        
        <!-- Slack for errors -->
        <slack>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            
            <params>
                <level>ERROR</level>
                <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                <channel>#alerts</channel>
            </params>
        </slack>
        
        <!-- Email for critical issues -->
        <email>
            <class>Monolog\Handler\NativeMailerHandler</class>
            
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
        <!-- Rotating file logging for all levels -->
        <file>
            <class>Monolog\Handler\RotatingFileHandler</class>
            
            <params>
                <level>DEBUG</level>
                <maxFiles>14</maxFiles>
            </params>
        </file>
        
        <!-- Slack for general errors - #alerts channel -->
        <slack_errors>
            <class>Monolog\Handler\SlackWebhookHandler</class>
            
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
        
        <params>
            <level>DEBUG</level>
        </params>
    </file>
    
    <!-- Payment issues - dedicated channel -->
    <slack_payments>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        
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
<!-- Production: Rotating file + Slack for errors -->
<handlers>
    <file>
        <class>Monolog\Handler\RotatingFileHandler</class>
        
        <params>
            <level>WARNING</level>
            <maxFiles>14</maxFiles>
        </params>
    </file>
    <slack>
        <class>Monolog\Handler\SlackWebhookHandler</class>
        
        <params>
            <level>ERROR</level>
            <webhookUrl>https://hooks.slack.com/services/PROD/WEBHOOK/URL</webhookUrl>
        </params>
    </slack>
</handlers>
```

```xml
<!-- Development: Rotating file + Browser console -->
<handlers>
    <file>
        <class>Monolog\Handler\RotatingFileHandler</class>
        
        <params>
            <level>DEBUG</level>
            <maxFiles>7</maxFiles>
        </params>
    </file>
    <browser>
        <class>Monolog\Handler\BrowserConsoleHandler</class>
        
        <params>
            <level>DEBUG</level>
        </params>
    </browser>
</handlers>
```

### Complex Handler Configurations

#### Syslog with Custom Facility and Ident

```xml
<syslog_custom>
    <class>Monolog\Handler\SyslogHandler</class>
    
    <params>
        <ident>maho-store-prod</ident>
        <facility>16</facility> <!-- LOG_LOCAL0 = 16 -->
        <level>WARNING</level>
        <logopts>3</logopts> <!-- LOG_CONS | LOG_PID = 3 -->
    </params>
</syslog_custom>
```

#### Slack with Advanced Configuration

```xml
<slack_advanced>
    <class>Monolog\Handler\SlackWebhookHandler</class>
    
    <params>
        <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
        <channel>#critical-alerts</channel>
        <username>Maho Production</username>
        <useAttachment>true</useAttachment>
        <iconEmoji>:rotating_light:</iconEmoji>
        <level>CRITICAL</level>
        <includeContextAndExtra>true</includeContextAndExtra>
        <excludeFields>
            <field>password</field>
            <field>cc_number</field>
        </excludeFields>
    </params>
</slack_advanced>
```

#### Email Handler with Multiple Recipients

```xml
<email_alerts>
    <class>Monolog\Handler\NativeMailerHandler</class>
    
    <params>
        <to>
            <recipient>admin@example.com</recipient>
            <recipient>ops-team@example.com</recipient>
        </to>
        <subject>[URGENT] Maho Store Critical Error</subject>
        <from>noreply@example.com</from>
        <level>CRITICAL</level>
        <maxColumnWidth>80</maxColumnWidth>
        <contentType>text/html</contentType>
    </params>
</email_alerts>
```

#### Rotating File Handler with Custom Filename Format

```xml
<rotating_custom>
    <class>Monolog\Handler\RotatingFileHandler</class>
    
    <params>
        <filename>var/log/maho.log</filename>
        <maxFiles>7</maxFiles>
        <level>INFO</level>
        <filenameFormat>{filename}-{date}</filenameFormat>
        <dateFormat>Y-m-d</dateFormat>
    </params>
</rotating_custom>
```

#### Telegram Bot Handler

```xml
<telegram_alerts>
    <class>Monolog\Handler\TelegramBotHandler</class>
    
    <params>
        <apiKey>YOUR_BOT_TOKEN</apiKey>
        <channel>@your_channel_name</channel>
        <level>ERROR</level>
        <parseMode>HTML</parseMode>
        <disableWebPagePreview>true</disableWebPagePreview>
        <disableNotification>false</disableNotification>
    </params>
</telegram_alerts>
```

#### Error Log Handler with Custom Message Type

```xml
<system_error_log>
    <class>Monolog\Handler\ErrorLogHandler</class>
    
    <params>
        <messageType>3</messageType> <!-- 3 = append to file -->
        <level>ERROR</level>
        <expandNewlines>true</expandNewlines>
    </params>
</system_error_log>
```

### Performance-Optimized Configuration

For high-traffic sites, use buffered handlers to reduce I/O operations:

```xml
<handlers>
    <!-- Buffered file handler -->
    <file_buffered>
        <class>Monolog\Handler\BufferHandler</class>
        <params>
            <handler>
                <class>Monolog\Handler\StreamHandler</class>
                <params>
                    <stream>var/log/system.log</stream>
                    <level>INFO</level>
                </params>
            </handler>
            <bufferLimit>100</bufferLimit>
            <flushOnOverflow>true</flushOnOverflow>
            <level>INFO</level>
        </params>
    </file_buffered>
    
    <!-- Group handler for critical alerts -->
    <critical_group>
        <class>Monolog\Handler\GroupHandler</class>
        <params>
            <handlers>
                <slack>
                    <class>Monolog\Handler\SlackWebhookHandler</class>
                    <params>
                        <webhookUrl>https://hooks.slack.com/services/YOUR/WEBHOOK/URL</webhookUrl>
                        <channel>#critical</channel>
                        <level>CRITICAL</level>
                    </params>
                </slack>
                <email>
                    <class>Monolog\Handler\NativeMailerHandler</class>
                    <params>
                        <to>oncall@example.com</to>
                        <subject>CRITICAL ERROR</subject>
                        <from>alerts@example.com</from>
                        <level>CRITICAL</level>
                    </params>
                </email>
            </handlers>
            <bubble>true</bubble>
        </params>
    </critical_group>
</handlers>
```

### Creating Custom Handlers

You can create your own Monolog handler and use it in the configuration:

```php
// app/code/local/YourCompany/Log/Handler/CustomHandler.php
namespace YourCompany\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;

class CustomHandler extends AbstractProcessingHandler
{
    private string $apiEndpoint;
    
    public function __construct(string $apiEndpoint, $level = Level::Debug, bool $bubble = true)
    {
        $this->apiEndpoint = $apiEndpoint;
        parent::__construct($level, $bubble);
    }
    
    protected function write(array $record): void
    {
        // Send log to your custom API
        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($record));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }
}
```

Then use it in your configuration:

```xml
<custom_api>
    <class>YourCompany\Log\Handler\CustomHandler</class>
    <params>
        <apiEndpoint>https://api.yourservice.com/logs</apiEndpoint>
        <level>ERROR</level>
    </params>
</custom_api>
```

## Migration from Zend_Log

### Automatic Migration

The system automatically handles the migration:

- **Old constants** (`Zend_Log::ERR`) are replaced with `Mage::LOG_ERR`
- **Same method signatures** for `Mage::log()` and `Mage::logException()`
- **Same log format** by default (can be customized)
- **Same file permissions** (640 for files, 750 for directories)

### Updated Constants

| Old Zend_Log | New Mage (Recommended) | Legacy Mage (Deprecated) | Value |
|--------------|------------------------|--------------------------|-------|
| `Zend_Log::EMERG` | `Mage::LOG_EMERGENCY` | `Mage::LOG_EMERG` | 0 |
| `Zend_Log::ALERT` | `Mage::LOG_ALERT` | `Mage::LOG_ALERT` | 1 |
| `Zend_Log::CRIT` | `Mage::LOG_CRITICAL` | `Mage::LOG_CRIT` | 2 |
| `Zend_Log::ERR` | `Mage::LOG_ERROR` | `Mage::LOG_ERR` | 3 |
| `Zend_Log::WARN` | `Mage::LOG_WARNING` | `Mage::LOG_WARN` | 4 |
| `Zend_Log::NOTICE` | `Mage::LOG_NOTICE` | `Mage::LOG_NOTICE` | 5 |
| `Zend_Log::INFO` | `Mage::LOG_INFO` | `Mage::LOG_INFO` | 6 |
| `Zend_Log::DEBUG` | `Mage::LOG_DEBUG` | `Mage::LOG_DEBUG` | 7 |

### Upgrade Path

#### Step 1: Update Your Code (Optional)
While the old `Zend_Log` constants still work, you can update them:

```php
// Old way (still works)
Mage::log('Error message', Zend_Log::ERR);

// Legacy way (deprecated but works)
Mage::log('Error message', Mage::LOG_ERR);

// New way (recommended)
Mage::log('Error message', Mage::LOG_ERROR);
```

#### Step 2: Keep Using Admin Configuration
If you're happy with simple file logging, no changes are needed. Your existing configuration in Admin Panel → System → Configuration → Advanced → Developer → Log Settings continues to work.

#### Step 3: Migrate to Advanced XML Configuration (Optional)
To use advanced features like Slack notifications or multiple handlers:

1. Add log configuration to `app/etc/local.xml`:
```xml
<config>
    <global>
        <log>
            <handlers>
                <!-- Enhanced file logging with rotation -->
                <file>
                    <class>Monolog\Handler\RotatingFileHandler</class>
                    <params>
                        <level>DEBUG</level>
                        <maxFiles>14</maxFiles>
                    </params>
                </file>
                <!-- Add new handlers as needed -->
            </handlers>
        </log>
    </global>
</config>
```

2. Once XML configuration is added, the admin panel logging section will be hidden automatically.

#### Step 4: Test Your Configuration
```php
// Test different log levels
Mage::log('Debug message', Mage::LOG_DEBUG);
Mage::log('Info message', Mage::LOG_INFO);
Mage::log('Warning message', Mage::LOG_WARNING);
Mage::log('Error message', Mage::LOG_ERROR);
Mage::log('Critical message', Mage::LOG_CRITICAL);

// Test exception logging
try {
    throw new Exception('Test exception');
} catch (Exception $e) {
    Mage::logException($e);
}
```

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

1. **Check handler is supported**: Only handlers with primitive parameters work via XML (see supported list above)
2. **Check class exists**: Ensure the handler class is available in Monolog
3. **Check parameters**: Verify parameter names match constructor arguments  
4. **Check dependencies**: Some handlers require additional packages (e.g., Slack webhook)
5. **Check permissions**: Ensure log directory is writable for file handlers

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
