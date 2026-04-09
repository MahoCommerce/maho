<p align="center">
  <img src="https://mahocommerce.com/assets/maho-logo.svg" alt="Maho" width=400 />
</p>
<p align="center">
  <img src="https://poser.pugx.org/mahocommerce/maho/license.svg" alt="License" />
  <img src="https://img.shields.io/badge/PHP-8.3+-8993be.svg" alt="PHP 8.3+" />
  <img src="https://github.com/MahoCommerce/maho/actions/workflows/security-php.yml/badge.svg" alt="Security" />
  <img src="https://github.com/MahoCommerce/maho/actions/workflows/codeql-analysis.yml/badge.svg" alt="CodeQL" />
  <a href="https://crowdin.com/project/maho" target="_blank"><img src="https://img.shields.io/badge/Localize-98%25-32c754" alt="Localization" /></a>
  <a href="https://deepwiki.com/MahoCommerce/maho" target="_blank"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki" /></a>
</p>
<p align="center">
  <a href="https://github.com/MahoCommerce/phpstorm" target="_blank"><img src="https://img.shields.io/badge/PHPStorm-Plugin-000000?logo=phpstorm&logoColor=white" alt="PHPStorm Plugin" /></a>
  <a href="https://github.com/MahoCommerce/vscode" target="_blank"><img src="https://img.shields.io/badge/VS_Code-Extension-2F80ED?logo=vscodium&logoColor=white" alt="VS Code Extension" /></a>
  <a href="https://github.com/MahoCommerce/zed" target="_blank"><img src="https://img.shields.io/badge/Zed-Extension-084CCF?logo=zedindustries&logoColor=white" alt="Zed Extension" /></a>
</p>

**Maho** is a modern, open-source ecommerce platform built on PHP 8.3+, Symfony, Doctrine DBAL,
Laminas, and 100% vanilla JS. A drop-in replacement for Magento 1 projects with full compatibility
and a complete toolchain including Composer and PHPStan plugins, and language packs.

### Modern tech stack

- **PHP 8.3+** with strict types, attributes, and modern language features
- **Doctrine DBAL 4** for database operations - supports MySQL, MariaDB, PostgreSQL, and SQLite
- **Symfony components** for HTTP, caching, console, validation, mailer, and more
- **Monolog** for structured logging, **DomPdf** for PDF generation
- **100% vanilla JavaScript** - no jQuery, no Prototype.js, no legacy frameworks
- **No legacy baggage** - Zend Framework and IE compatibility code have been completely removed

### Enterprise features built in

- **Automated email marketing** with multi-step campaigns and behavior-based triggers
- **Customer segmentation** with rule-based targeting
- **Dynamic categories** that update automatically based on product rules
- **Passkey and 2FA authentication** for secure admin access
- **PayPal v6 SDK** with advanced checkout, vault, and Pay Later; **[1st party Braintree module](https://github.com/MahoCommerce/module-braintree)** for cards, Apple Pay, and Google Pay
- **Blog module**, Meta Pixel integration, and advanced payment restrictions
- **Multi-store** capabilities with comprehensive APIs (REST, SOAP, JSON-RPC)

### Developer experience

- **50+ CLI commands** for admin, cache, indexing, cron, database, and development tasks
- **Built-in LSP and MCP server** for deep IDE integration and AI-assisted development
- **Composer plugin** for module management, **PHPStan plugin** for static analysis
- **Language packs** via Crowdin with 98% coverage
- Clean MVC architecture, event-driven system, and modular design

## Getting started

```bash
composer create-project mahocommerce/maho-starter yourproject
```

Or try it instantly with Docker:

```bash
docker run -p 54321:443 mahocommerce/maho:nightly
```

Then open https://localhost:54321 and follow the web installer (select SQLite to skip database setup).

For production Docker setups, see the [official Docker images](https://hub.docker.com/r/mahocommerce/maho).
Full details on web server configuration, database setup, and deployment options in the
[Getting Started guide](https://mahocommerce.com/getting-started).

## Documentation

- [mahocommerce.com](https://mahocommerce.com) - official documentation
- [PHP API reference](https://phpdoc.mahocommerce.com) - full class and method documentation
- [DeepWiki](https://deepwiki.com/MahoCommerce/maho) - AI-powered code exploration
- [Contributing Guide](CONTRIBUTING.md) - development setup, code style, testing, and PR guidelines

## About the name

"Maho" ([pronounced "mah-hoh"](https://www.ingles.com/pronunciacion/majo)) is the name of the
ancient indigenous people of Lanzarote and Fuerteventura in the Canary Islands - a resilient
population who thrived in challenging environments. In Spanish it means *nice, cool*; in Japanese
it means *magic*. The name reflects our strength and resilience in the demanding landscape of modern ecommerce.

## Community

- [Discord](https://discord.gg/dWgcVUFTrS)
- [GitHub Discussions](https://github.com/MahoCommerce/maho/discussions)
- [Email](mailto:info@mahocommerce.com)

## Code of Conduct

All participants are expected to follow our [Code of Conduct](https://github.com/MahoCommerce/maho?tab=coc-ov-file).
