---
description: Use Maho setup-resource scripts for versioned data work like seed rows, config values and EAV attributes, while table structure stays in declarative sql/schema.php.
---

# Setup Resources

## Introduction

A module usually needs to do two kinds of database work: define its **table structure** (columns, indexes, foreign keys) and load **data** (seed rows, configuration values, EAV attributes). In current Maho these are two separate mechanisms:

- **Table structure** is **declarative**. You describe the target shape of your tables in a single `sql/schema.php` file and Maho reconciles the live database to match. This is the recommended approach for all new modules. See [Declarative Database Schema](../declarative-database-schema.md) (v26.7+).
- **Data** is handled by the versioned **setup-resource** scripts described on this page: small PHP scripts that run once, are tracked by version, and insert or update rows.

!!! tip "Use declarative schema for structure, setup resources for data"
    In new modules, don't create or alter tables from setup-resource scripts. Define structure in [`sql/schema.php`](../declarative-database-schema.md) and use the versioned scripts here only for **data** (seed rows, config values, EAV attributes). The older `sql/<name>_setup/install-*.php` structural scripts still run for backward compatibility, but they are legacy.

This page continues with the weblog module from the ORM article. Its `blog_posts` table is created declaratively (see the link above); here we add the data scripts that seed and later update its rows.

## Registering a Setup Resource

A setup resource is identified by a resource name, by convention `modelname_setup` (here, `weblog_setup`). You do **not** need any XML to register one: if your module ships a `data/<name>_setup/` directory (or, for legacy structural scripts, `sql/<name>_setup/`), Maho discovers it automatically and runs its scripts with the base `Mage_Core_Model_Resource_Setup` class. The directory name becomes the resource name (and the key stored in the `core_resource` table).

So for the data scripts in our weblog module, creating the directory `app/code/local/Mahotutorial/Weblog/data/weblog_setup/` is all the registration that's needed. There's nothing to add to `config.xml`, and no setup class to create.

You only need a `<global><resources>` declaration in two cases:

- you want a **custom setup class** (to add helper methods or override behavior), or
- the resource must run on a **non-default database connection**.

In that case, declare it in your `<global />` section and create the matching class:

```xml
<global>
    <!-- ... -->
    <resources>
        <weblog_setup>
            <setup>
                <module>Mahotutorial_Weblog</module>
                <class>Mahotutorial_Weblog_Model_Resource_Setup</class>
            </setup>
        </weblog_setup>
    </resources>
    <!-- ... -->
</global>
```

File: app/code/local/Mahotutorial/Weblog/Model/Resource/Setup.php

```php
class Mahotutorial_Weblog_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
}
```

!!! note "Declared vs convention resources"
    Declared resources run first, in config dependency order; convention-discovered resources run afterward. If your scripts must run in a specific order relative to another module, declare the resource so you can control ordering with `depends_on`.

## Creating a Data Script

Data scripts live in `data/<name>_setup/` and are named `data-install-x.y.z.php` (first install) and `data-upgrade-a.b.c-x.y.z.php` (upgrades). Which scripts run is driven by your module's version, declared in `config.xml`:

```xml
<modules>
    <Mahotutorial_Weblog>
        <version>0.1.0</version>
    </Mahotutorial_Weblog>
</modules>
```

The following assumes the current version of your module is 0.1.0. Create this file:

File: app/code/local/Mahotutorial/Weblog/data/weblog_setup/data-install-0.1.0.php

```php
$installer = $this;
$installer->startSetup();

$installer->run("
    INSERT INTO `{$installer->getTable('weblog/blogpost')}` (`title`, `post`, `date`)
    VALUES ('My New Title', 'This is a blog post', '2026-07-01 00:00:00');
");

$installer->endSetup();
```

Clear your Maho cache and load any page of your Maho site. The script runs once, and the row is inserted into the `blog_posts` table (which the declarative `sql/schema.php` already created).

## Resource Versions

Maho tracks which scripts have already run so they aren't repeated. Using your favorite database client, take a look at the `core_resource` table:

```sql
mysql> select * from core_resource where code = 'weblog_setup';
+--------------+---------+--------------+
| code         | version | data_version |
+--------------+---------+--------------+
| weblog_setup | 0.1.0   | 0.1.0        |
+--------------+---------+--------------+
1 row in set (0.00 sec)
```

The `data_version` column records the highest data script that has run for this resource. Because it's already at 0.1.0, the install script won't run again. This is how Maho knows not to re-run your script on every page load.

If you want to re-run your data-install script while developing, delete (or lower) the row for your module:

```sql
DELETE FROM core_resource WHERE code = 'weblog_setup';
```

On the next page load the script runs again.

## Anatomy of a Data Script

Let's go over the script. First:

```php
$installer = $this;
```

Each script is run from the context of a Setup Resource object (the base `Mage_Core_Model_Resource_Setup` unless you declared a custom class). Aliasing `$this` to `$installer` is the convention used throughout the core modules.

Next, the work is bookended by:

```php
$installer->startSetup();
//...
$installer->endSetup();
```

If you look at `Mage_Core_Model_Resource_Setup` in `app/code/core/Mage/Core/Model/Resource/Setup.php` you'll see these delegate to the connection:

```php
public function startSetup()
{
    $this->getConnection()->startSetup();
    return $this;
}

public function endSetup()
{
    $this->getConnection()->endSetup();
    return $this;
}
```

You can look into `\Maho\Db\Adapter\Pdo\Mysql` in `lib/Maho/Db/Adapter/Pdo/Mysql.php` to see the connection-level setup these run for MySQL.

Then the call to `run`:

```php
$installer->run(...);
```

accepts a string of SQL; you may include any number of statements separated by a semicolon. Note also:

```php
$installer->getTable('weblog/blogpost')
```

The `getTable` method resolves a Maho model URI to its real table name, so your data script keeps working even if the underlying table name is changed in configuration. `Mage_Core_Model_Resource_Setup` exposes many such helper methods; the best way to learn them is to read the data scripts in the core modules.

## Module Upgrades

Once a `data-install` script has run for a module, Maho will **never run another install script for that resource again** (short of deleting its `core_resource` row). To change or add data later, write an **upgrade** script.

Upgrade scripts live in the same `data/<name>_setup/` directory and are named with two version numbers: the version you're upgrading **from** and the version **to**. To trigger them, bump the module version in `config.xml`:

```xml
<modules>
    <Mahotutorial_Weblog>
        <version>0.2.0</version>
    </Mahotutorial_Weblog>
</modules>
```

File: app/code/local/Mahotutorial/Weblog/data/weblog_setup/data-upgrade-0.1.0-0.2.0.php

```php
$installer = $this;
$installer->startSetup();

$installer->run("
    UPDATE `{$installer->getTable('weblog/blogpost')}`
    SET `post` = CONCAT(`post`, ' (updated)')
    WHERE `title` = 'My New Title';
");

$installer->endSetup();
```

When Maho sees the module version is higher than the recorded `data_version`, it runs every applicable upgrade script in order, from lowest to highest, to bring the resource up to date. Name your upgrade ranges so they chain without gaps, for example:

```
data-upgrade-0.1.0-0.1.5.php   # 0.1.0 to 0.1.5
data-upgrade-0.1.5-0.2.0.php   # 0.1.5 to 0.2.0
```

Maho will run both on a single page load. If instead you wrote two scripts that both start **from** 0.1.0, only the first to advance the version would take effect, so always chain `from`-`to` ranges end to end.

!!! warning "Coordinate upgrade scripts across developers"
    Because scripts are matched by `from`-`to` version ranges, multiple developers adding upgrade scripts independently can collide. Keep the version ranges contiguous, and decide as a team who owns version bumps.

## Changing table structure

To change a table's columns, indexes, or foreign keys, **do not** write a setup-resource upgrade script. Edit your module's [`sql/schema.php`](../declarative-database-schema.md) to describe the new target structure and run `./maho migrate` (or let it apply on the next request). With structure declarative, your module `<version>` no longer tracks schema state; it only gates the remaining **data** scripts, so you bump the version when you add a new `data-*.php` script, not when you change a table.
