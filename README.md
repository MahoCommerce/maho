<p align="center">
  <img src="https://mahocommerce.com/assets/maho-logo.svg" alt="Maho" width=400 />
</p>
<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.5+-8993be.svg" alt="PHP 8.5+" />
  <img src="https://poser.pugx.org/mahocommerce/maho/license.svg" alt="License" />
  <a href="https://plumbphp.dev/mahocommerce/maho" target="_blank"><img src="https://plumbphp.dev/badges/mahocommerce/maho/composite.svg" alt="Plumb score" /></a>
  <img src="https://github.com/MahoCommerce/maho/actions/workflows/security-php.yml/badge.svg" alt="Security" />
  <img src="https://github.com/MahoCommerce/maho/actions/workflows/codeql-analysis.yml/badge.svg" alt="CodeQL" />
  <a href="https://crowdin.com/project/maho" target="_blank"><img src="https://badges.crowdin.net/maho/localized.svg" alt="Localization" /></a>
</p>
<p align="center">
  <a href="https://github.com/MahoCommerce/phpstorm" target="_blank"><img src="https://img.shields.io/badge/PHPStorm-Plugin-000000?logo=phpstorm&logoColor=white" alt="PHPStorm Plugin" /></a>
  <a href="https://github.com/MahoCommerce/vscode" target="_blank"><img src="https://img.shields.io/badge/VS_Code-Extension-2F80ED?logo=vscodium&logoColor=white" alt="VS Code Extension" /></a>
  <a href="https://github.com/MahoCommerce/zed" target="_blank"><img src="https://img.shields.io/badge/Zed-Extension-084CCF?logo=zedindustries&logoColor=white" alt="Zed Extension" /></a>
</p>
<p align="center">
  <a href="https://demo.mahocommerce.com">Live demo</a> ·
  <a href="https://mahocommerce.com">Documentation</a> ·
  <a href="https://github.com/MahoCommerce/maho/releases/latest">Release notes</a> ·
  <a href="https://discord.gg/dWgcVUFTrS">Discord</a> ·
  <a href="https://mahocommerce.com/sponsors/">Sponsor</a>
</p>

**Maho** is an open-source ecommerce platform on PHP 8.5, Symfony 8, Doctrine DBAL 4 and
API Platform 5.

<p align="center">
  <img width="100%" alt="A tour of the Maho admin: the login page, the dashboard, the main menu, the orders grid, a product search, the product image cards and the switch to dark mode" src="https://github.com/user-attachments/assets/2a71850c-dfba-4a07-a943-008ad1c32292" />
</p>

## Quick start

```bash
composer create-project mahocommerce/maho-starter yourproject
cd yourproject
./maho serve
```

Open http://localhost:8000 and follow the installer. Select SQLite to skip the database setup.
To fill the store with products, customers and orders, run `./maho import:sample-data`.

You can also start a store with Docker:

```bash
docker run -p 54321:443 mahocommerce/maho:nightly
```

Then open https://localhost:54321. For web server, database and production setups, read the
[Getting Started guide](https://mahocommerce.com/about/getting-started/) and the
[official Docker images](https://hub.docker.com/r/mahocommerce/maho).

## For developers

### The stack

- **PHP 8.5** with strict types, typed accessors, `#[\Override]` and attributes
- **Symfony 8.1** components: HTTP, console, routing, messenger, mailer, validation, cache
- **Doctrine DBAL 4.5** with a declarative schema, on **MySQL, MariaDB, PostgreSQL and SQLite**
- **API Platform 5.0** for REST, GraphQL and MCP from one set of resources
- **Tailwind CSS 4 and daisyUI 5** for the storefront and the admin, **TipTap 3** for content
- **Vanilla JavaScript**: no jQuery, no Prototype.js
- **Monolog** for logs, **OpenTelemetry** for traces and metrics, **Pest** for tests

### Configuration in PHP attributes

Routes, observers, cron jobs, queue handlers and API resources live next to the code, not in XML:

```php
#[Maho\Config\Route('/catalog/product/view/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
public function viewAction(): void {}

#[Maho\Config\Observer('catalog_product_save_after')]
public function handleProductSave(\Maho\Event\Observer $observer): void {}

#[Maho\Config\CronJob('my_cron_job', schedule: '0 2 * * *')]
public function runJob(Mage_Cron_Model_Schedule $schedule): void {}
```

A schema change is a diff of `sql/schema.php`, not one more upgrade script. To override a
controller, extend it: the router finds the subclass.

### APIs

- **REST and GraphQL** on one set of resources, with JWT, granular permissions, ETags and
  idempotency keys. The OpenAPI 3.1 spec is at `/api/docs.json`. Read the
  [API guide](https://mahocommerce.com/api/v2/)
- **MCP server** with OAuth 2.1 login, so Claude, ChatGPT and other agents work with the
  catalog and the orders. Read the [MCP guide](https://mahocommerce.com/api/v2/mcp/)
- **Legacy SOAP, XML-RPC and JSON-RPC** APIs stay available for existing integrations

### Tools

- **114 CLI commands** for install, cache, indexes, cron, database dumps, imports, exports,
  storage, themes and admin users. Read the [CLI guide](https://mahocommerce.com/developer/cli-tool/)
- **Message queue** on Symfony Messenger, with worker pools and an admin grid
- **Storage mounts** on Flysystem: put media, uploads and feeds on S3, Google Cloud Storage or Azure
- **Built-in LSP and MCP servers** for your editor and your coding agent
- **[Composer plugin](https://github.com/MahoCommerce/maho-composer-plugin)** for modules and a
  **[PHPStan plugin](https://github.com/MahoCommerce/maho-phpstan-plugin)** for static analysis
- **Signed release packages** at SLSA Build Level 3

## For merchants

- **Storefront**: eleven themes with a live theme editor, one-step checkout, social login with
  Google, Apple and Facebook, customer 2FA, product gallery layouts
- **Admin**: an AI assistant on every page, a drag-and-drop page builder, passkey and 2FA login,
  light and dark mode, an image editor, content versions, and the
  [Maho Admin app](https://play.google.com/store/apps/details?id=com.mahocommerce.admin) for
  phones and tablets
- **Marketing**: customer segments, email automation, dynamic categories, gift cards, a blog, and
  product feeds for Google Shopping, Meta, Bing and Pinterest
- **Payments and shipping**: PayPal v6 with Pay Later and saved cards, a
  [Braintree module](https://github.com/MahoCommerce/module-braintree) for cards, Apple Pay and
  Google Pay, external shipping rate APIs
- **Search engines and AI agents**: schema.org data, SEO rules for layered navigation, `llms.txt`
  and a markdown version of each page
- **Compliance**: WCAG accessibility scans, EU VAT number checks, the EU revocation button
- **Multi-store**: many websites, stores and currencies from one admin

Read the [User Guide](https://mahocommerce.com/user-guide/) for each feature.

## Releases

Maho uses calendar versions: `26.11.0` is the release of November 2026. A new release ships about
every two months, and patch releases bring fixes in between. Only the latest release gets fixes.
Each release can contain breaking changes, so read the
[release notes](https://github.com/MahoCommerce/maho/releases) before you upgrade. The
[release strategy](https://mahocommerce.com/about/release-strategy/) explains why.

## Migrate from Magento 1 or OpenMage

Maho keeps the Magento 1 architecture, so your modules, themes and data move with you. Maho is
not 100% backward compatible: each release removes old code and lists the breaking changes. The
[migration guide](https://mahocommerce.com/about/migrate-to-maho/) gives the steps.

## Documentation

- [mahocommerce.com](https://mahocommerce.com): user guide, developer guide, API and hosting
- [PHP API reference](https://phpdoc.mahocommerce.com): all classes and methods
- [DeepWiki](https://deepwiki.com/MahoCommerce/maho): ask questions about the code
- [AGENTS.md](AGENTS.md): the conventions of the codebase, for people and for coding agents

## Contributing

Bug reports, pull requests and translations are welcome. Read the
[contributing guide](https://mahocommerce.com/community/contributing/) to set up a development
store, run the linters and open a pull request. Translations happen on
[Crowdin](https://crowdin.com/project/maho).

Maho is built by a small team, with generative AI as part of the daily work. Every pull request
passes PHPStan, PHP CS Fixer, Rector and the test suite on seven database backends, and a
maintainer reviews every change before the merge. AI use in your pull requests is welcome, but you
must understand and stand behind the code that you submit. Read our
[GenAI transparency statement](https://mahocommerce.com/about/genai-transparency/).

## Sponsors and funding

Maho is free software, and sponsors pay for the time that it takes. Please
[become a sponsor](https://mahocommerce.com/sponsors/).

<a href="https://nlnet.nl/project/Maho"><img width="400" alt="Funded by the European Commission, NGI TALER Fund by NLnet" src="https://github.com/user-attachments/assets/ceea3170-0bd5-46cb-8565-895aebc4119f" /></a>

Maho receives funding from the [NGI TALER Fund](https://nlnet.nl/taler) of NLnet, with support
from the Next Generation Internet programme of the European Commission.

## Community

- [Discord](https://discord.gg/dWgcVUFTrS)
- [GitHub Discussions](https://github.com/MahoCommerce/maho/discussions)
- [Email](mailto:info@mahocommerce.com)

All participants follow our [Code of Conduct](https://github.com/MahoCommerce/maho?tab=coc-ov-file).

## About the name

"Maho" ([pronounced "mah-hoh"](https://www.ingles.com/pronunciacion/majo)) is the name of the
ancient indigenous people of Lanzarote and Fuerteventura in the Canary Islands, a resilient
people who thrived in a hard land. In Spanish it means *nice, cool*, and in Japanese it means
*magic*.

## License

Maho is licensed under [OSL-3.0](LICENSE.txt). Templates, layouts and front-end assets are licensed
under [AFL-3.0](LICENSE_AFL.txt).
