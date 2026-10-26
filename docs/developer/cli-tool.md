---
description: The ./maho command-line tool ships with every Maho project to run installs, migrations, cache and admin tasks, plus an interactive PHP shell and dev server.
---

# CLI tool

Every Maho based project comes with a tool to run various tasks from the command line, find it in the root of your
project:

```
$ ./maho

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
      --silent          Do not output any message
  -q, --quiet           Only errors are displayed. All other output is suppressed
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  completion                    Dump the shell completion script
  health-check                  Health check your Maho project
  help                          Display help for a command
  install                       Install Maho
  list                          List commands
  migrate                       Apply pending database schema and data updates from modules
  serve                         Run Maho with the built in web server
  shell                         Opens an interactive PHP shell with Maho bootstrapped
 admin
  admin:user:change-password    Change password of an admin users
  admin:user:create             Create an admin user
  admin:user:disable            Enable an admin user
  admin:user:enable             Enable an admin user
  admin:user:list               List all admin users
  admin:user:twofa-reset        Remove the two-factor authentication of an admin user who lost their device
 cache
  cache:disable                 Disable all caches
  cache:enable                  Enable all caches
  cache:flush                   Flush cache
  cache:minify:flush            Flush minified CSS/JS cache
 catalog
  catalog:price:website-overrides  List price rows scoped to a store or website, flagging those that look like an old converted seed
 config
  config:delete                 Delete configuration values from core_config_data table
  config:get                    Get specific configuration path values
  config:set                    Set configuration values in core_config_data table
 cron
  cron:history                  List cron jobs executions stored in the database
  cron:list                     List cron jobs configured in the XML files
  cron:run                      Run a group of cron processes (default/always) or a single job_code (eg: newsletter_send_all)
 customer
  customer:change-password      Change password of a customers
  customer:create               Create a customer
  customer:delete               Delete customers
  customer:group:create         Create a customer group
  customer:group:delete         Delete a customer group
  customer:group:list           List all customer groups
  customer:list                 List all customers
 db
  db:connect                    Opens the database command-line interface using credentials from your local.xml file
  db:optimize                   Rebuild bloated tables to return their free space to the filesystem
  db:query                      Execute a SQL query using the database credentials from your local.xml file
 dev
  dev:admin:theme:build         Compile the CSS sources (src/*.css) of every admin theme into their css/ bundles
  dev:api:resource:create       Scaffold an API Platform resource (DTO + provider) for a module, wiring it into the REST + GraphQL API
  dev:api:resource:list         List the API Platform resources discovered across all modules (REST routes, methods, permissions)
  dev:create-command            Create a new command that will integrate into your project's Maho CLI set of commands
  dev:frontend:layout:debug     Debug layout for a given URL showing handles, XML files, and block tree
  dev:frontend:theme:create     Create a new frontend theme with proper scaffolding
  dev:frontend:theme:build      Compile the CSS sources (src/*.css) of every frontend theme into their css/ bundles
  dev:frontend:theme:export     Write a store's admin theme settings to a theme.css file, so they can be committed
  dev:legacy:rename-mysql4-classes  Search for old Mysql4 classes and replaces them with Resource classes
  dev:lsp:start                 Start the LSP (Language Server Protocol) server for editor integration
  dev:mcp:start                 Start the MCP (Model Context Protocol) server for AI agent integration
  dev:module:list               List installed modules with declared and core_resource versions
  dev:paypal:webhook:simulate   Simulate a PayPal webhook event for testing
  dev:translations:missing      Display used translations strings that are missing from csv files
  dev:translations:unused       Display defined translations strings that are not used in templates
 email
  email:config:show             Show email configuration and settings
  email:queue:clear             Clear emails from the queue
  email:queue:process           Manually process the email queue
  email:test:queue              Send a test email via the queue system
  email:test:send               Send a test email
 feed
  feed:generate                 Generate a specific feed
  feed:generate:all             Generate all enabled feeds
  feed:list                     List all feeds with status
  feed:validate                 Validate an existing feed file without regenerating
 index
  index:list                    List all indexes
  index:reindex                 Reindex a single index
  index:reindex:all             Reindex all indexes
  index:reindex:product         Reindex specific product(s) across all or specified indexers
 legacy
  legacy:migrate-cron           Migrate XML <crontab><jobs> declarations to #[Maho\Config\CronJob] attributes
  legacy:migrate-observers      Migrate XML <events> observer declarations to #[Maho\Config\Observer] attributes
  legacy:migrate-routes         Migrate XML <routers> declarations to #[Maho\Config\Route] attributes
 log
  log:clean                     Clean log tables in the database
  log:status                    Show status for log tables in the database
 maintenance
  maintenance:disable           Disable maintenance mode
  maintenance:enable            Enable maintenance mode
  maintenance:status            Show maintenance mode status
 queue
  queue:list                    Show per-queue message counts and the worker pool each queue is consumed by
  queue:work                    Consume messages from the queue (long-running worker; cron keeps one running automatically)
 storage
  storage:image:clean           Delete the resized files of image settings that no store view uses
  storage:image:resize          Create the missing resized files of the images, and store the missing image sizes
  storage:migrate               Copy the local folder of storage mounts to the adapter that local.xml configures, such as an S3 bucket
 sys
  sys:currencies                Get all available currencies
  sys:directory:regions:import  Import states/provinces for a country from ISO 3166-2 standard with localization
  sys:encryptionkey:regenerate  Generate a new encryption key and save it to local.xml
  sys:locales                   Get all available locales
  sys:timezones                 Get all available timezones
 theme
  theme:content:build           Compile the Tailwind classes of the CMS, blog and catalog content into a copy of the css/styles.css of each theme
```

!!! note
    <span class="version-badge">v26.9+</span> The `queue:*` commands drive the
    [message queue](message-queue.md) and its [worker pools](message-queue.md#worker-pools). On earlier
    versions only the `email:queue:*` commands existed, because the queue was email-specific.
    `health-check` and `db:optimize` are covered in
    [Health check & database maintenance](../hosting/health-check.md).
    <span class="version-badge">v26.11+</span> `storage:migrate`, `storage:image:resize`
    and `storage:image:clean` are covered in [Shared storage](../hosting/storage.md#move-an-existing-store).

This tool is inspired by [Laravel Artisan](https://laravel.com/docs/11.x/artisan){:target="_blank"},
[n98-magerun](https://github.com/netz98/n98-magerun){:target="_blank"}, and it was created using the awesome
[Symfony Console](https://symfony.com/doc/current/console.html){:target="_blank"} component.

## Available commands

The list of the built-in commands is growing rapidly, at the moment yon can either run
`./maho` within your Maho based project or you can check
[Maho CLI commands directory](https://github.com/MahoCommerce/maho/tree/main/lib/MahoCLI/Commands){:target="_blank"}
within our GitHub repository.

All commands should be self-explanatory, also thanks to the inline descriptions.

## Give the input on the command line

Each command takes its input as arguments and options. Run `./maho help <command>` to see them. If you
leave out a required argument, the command asks for it in the terminal:

```bash
./maho customer:group:create Wholesale 3   # asks nothing
./maho customer:group:create               # asks for the code and the tax class
```

A command that asks for a confirmation before it changes data skips the question with `--force` (`-f`).

## Run a command from a script

Add `-n` (`--no-interaction`) to each command in a script, a cron job or a CI pipeline. With `-n`, a command
asks nothing:

- An optional argument that you leave out gets its default value.
- A required argument that you leave out stops the command with the error "Not enough arguments".
- A confirmation gets the answer "no", unless you add `--force`.

Without `-n`, a command that needs an answer waits for input. If the script sends no more input, the command
stops with an error.

## Add your custom commands

### Using the built-in command generator

The easiest way to create a new command is to run:

```bash
$ ./maho dev:create-command
```

The generator asks for:

1. Command name (e.g., `my-custom-command` or `cache:clean`)
2. Command description

You can also give both on the command line: `./maho dev:create-command cache:clean "Clean the cache"`.

It will automatically:

- Create the command file in the correct location (`lib/MahoCLI/Commands`)
- Set up the proper namespace and class name
- Add all necessary imports and boilerplate code

Now you can edit your newly create file in `lib/MahoCLI/Commands`.

### Manual creation

Alternatively, you can create commands manually:

1. Create `lib/MahoCLI/Commands` in the main directory of your project
2. Create your command file, e.g. `MyCustomCommand.php` in `lib/MahoCLI/Commands`. You can also organize commands into subdirectories (e.g. `lib/MahoCLI/Commands/Catalog/ImportCommand.php`) - they will be discovered automatically as long as the namespace matches the directory structure:
```php
<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'my-custom-command',
    description: 'This command is just a test'
)]
class MyCustomCommand extends BaseMahoCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $output->writeln("<info>IT WORKED!</info>");
        return Command::SUCCESS;
    }
}
```
3. Add a PSR4 autoload configuration in your `composer.json`
```
"autoload": {
    "psr-4": {
        "MahoCLI\\": "lib/MahoCLI"
    }
}
```
4. run `composer dump-autoload`

Now you can run `./maho` and you will see it appear in the list of available commands:
```
Available commands:
  completion                    Dump the shell completion script
  help                          Display help for a command
  install                       Install Maho
  list                          List commands
  my-custom-command             This command is just a test
  serve                         Run Maho with the built in web server
```

and you will be able to run it with

```
$ ./maho my-custom-command
IT WORKED!
```