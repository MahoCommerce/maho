---
description: Override Maho configuration with ENV variables that take precedence over XML and database values, scoped per default, website or store view using MAHO_CONFIG names.
---

# Override configurations with ENV variables

## Introduction

In Maho it's possible to use ENV variables to override configurations. The configuration loading order is as follows:

1. Configurations in XML files are loaded first.
2. These are then overridden by configurations saved in the database (in the `core_config_data` table).
3. Finally, all previous configurations can be overridden by ENV variables.

This layered approach allows for flexible configuration management, with ENV variables taking the highest precedence.

This feature is inspired by a similar one in Magento 2, you can find a very good documentation
[on their website](https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/paths/override-config-settings),
we suggest you to read it since it applies to Maho too.

## Examples

Each example will override the `general/store_information/name` value.

To override the `default` `general/store_information/name`, set the
`MAHO_CONFIG__DEFAULT__GENERAL__STORE_INFORMATION__NAME` env variable to the value you want.

To override the `general/store_information/name` configuration for `base` website,
set the `MAHO_CONFIG__WEBSITES__BASE__GENERAL__STORE_INFORMATION__NAME` env variable to the value you want.

To override `general/store_information/name` for the `german` store view,
set the `MAHO_CONFIG__STORES__GERMAN__GENERAL__STORE_INFORMATION__NAME` env variable to the value you want.

This schema should be self-explanatory, but here you have it detailed:
```
MAHO_CONFIG__
^ Prefix (required)
             <SCOPE>__
             ^ Where scope is DEFAULT, WEBSITES__<WEBSITE_CODE> or STORES__<STORE_CODE>
                      <SYSTEM_VARIABLE_NAME>
                      ^ Where GROUP, SECTION and FIELD are separated by __
```

