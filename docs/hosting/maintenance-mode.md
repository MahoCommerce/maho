---
description: Take your Maho store offline for updates with built-in maintenance mode, controlled from the command line with enable, disable and status commands.
---

# Maintenance Mode <span class="version-badge">v26.1+</span>

Maho includes built-in maintenance mode functionality that allows you to temporarily take your store offline while performing updates, migrations, or other administrative tasks.

## Quick Start

```bash
# Enable maintenance mode
./maho maintenance:enable

# Disable maintenance mode
./maho maintenance:disable

# Check current status
./maho maintenance:status
```

## CLI Commands

### Enable Maintenance Mode

```bash
./maho maintenance:enable
```

This creates a `maintenance.flag` file in your project root, which triggers maintenance mode for all visitors.

#### Allowing Specific IPs

You can allow certain IP addresses to bypass maintenance mode. This is useful for testing your changes before taking the site back online:

```bash
./maho maintenance:enable --ip=192.168.1.100
```

To allow multiple IPs, separate them with commas:

```bash
./maho maintenance:enable --ip=192.168.1.100,192.168.1.101,10.0.0.50
```

### Disable Maintenance Mode

```bash
./maho maintenance:disable
```

This removes the `maintenance.flag` file and any IP allowlist, making the store accessible to all visitors again.

### Check Status

```bash
./maho maintenance:status
```

Displays whether maintenance mode is currently active and lists any allowed IP addresses.

## How It Works

When maintenance mode is enabled:

1. A `maintenance.flag` file is created in the project root
2. If IPs are specified, a `maintenance.ip` file is created with the allowed addresses
3. All requests (except from allowlisted IPs) receive a 503 Service Unavailable response
4. The response includes `Retry-After` and `X-Robots-Tag: noindex` headers to prevent search engine issues

## Customizing the Maintenance Page

Maho ships with a default maintenance page template at `app/design/maintenance/default.phtml`. You can override this by creating your own template in your project.

### Creating a Custom Template

Create a file at `app/design/maintenance/default.phtml` in your project root:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Maintenance - My Store</title>
    <style>
        /* Your custom styles */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .maintenance-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <img src="/skin/frontend/default/default/images/logo.png" alt="My Store">
        <h1>We're upgrading our store</h1>
        <p>We'll be back online shortly. Thank you for your patience!</p>
    </div>
</body>
</html>
```

### Multi-Store Support

For multi-store setups, you can create store-specific maintenance pages. Maho looks for templates in this order using its fallback system:

1. `app/design/maintenance/{store_code}.phtml` - Store-specific template
2. `app/design/maintenance/default.phtml` - Default template

Templates are resolved through Maho's package fallback system, meaning your project files override the ones shipped with Maho.

For example, if you have stores with codes `en`, `de`, and `fr`:

```
app/design/maintenance/
├── default.phtml   # Fallback for all stores
├── de.phtml        # German store
└── fr.phtml        # French store (English uses default.phtml)
```

### Template Variables

Since the maintenance page is displayed before the full Maho application bootstraps, you have limited access to Maho functionality. However, you can use:

- `$_SERVER['MAGE_RUN_CODE']` - Current store code
- `$_SERVER['MAGE_RUN_TYPE']` - Run type ('store' or 'website')
- `$_SERVER['HTTP_HOST']` - Current domain

Example using store code for localization:

```php
<?php
$messages = [
    'en' => ['title' => 'Maintenance', 'message' => 'We\'ll be back soon!'],
    'de' => ['title' => 'Wartung', 'message' => 'Wir sind bald zurück!'],
    'fr' => ['title' => 'Maintenance', 'message' => 'Nous serons bientôt de retour!'],
];
$code = $_SERVER['MAGE_RUN_CODE'] ?? 'en';
$content = $messages[$code] ?? $messages['en'];
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($content['title']) ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($content['message']) ?></h1>
</body>
</html>
```

## SEO Considerations

Maho automatically handles SEO concerns during maintenance:

- **HTTP 503 Status**: Tells search engines the downtime is temporary
- **Retry-After Header**: Suggests when to check back (default: 1 hour)
- **X-Robots-Tag: noindex**: Prevents indexing of the maintenance page
- **Meta robots tag**: The default template includes `<meta name="robots" content="noindex, nofollow">`

This ensures search engines understand your site is temporarily unavailable and won't penalize your rankings.

## Manual Mode

If you prefer not to use the CLI, you can manage maintenance mode manually:

```bash
# Enable
touch maintenance.flag

# Enable with IP allowlist
touch maintenance.flag
echo "192.168.1.100" > maintenance.ip

# Disable
rm maintenance.flag maintenance.ip
```
