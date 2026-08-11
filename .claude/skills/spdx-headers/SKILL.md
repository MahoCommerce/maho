---
name: spdx-headers
description: Migrate Maho source-file headers from the legacy phpDocumentor docblock (Maho summary line + @copyright / @license URL) to the modern SPDX format. Works in any Maho repo (maho, modules, themes). Use when asked to convert headers to SPDX, apply issue #939, or modernize license headers.
argument-hint: "[path or glob to migrate; omit to migrate files changed vs main]"
---

Migrate file headers to SPDX format, per MahoCommerce/maho issue #939.

This is **not** a pure find-and-replace. The mechanical parts (tag conversion, license
identifier, comment syntax per file type) are deterministic, but two things need judgment
per file and must be done by reading the file, not by regex:

1. The PHP class **description** that replaces the noise ` * Maho` summary line.
2. Whether an existing summary line is meaningful prose to keep or legacy noise to drop.

So work file-by-file (use parallel subagents in batches for large scopes), not a sed script.

## 1. Determine scope

- **`$ARGUMENTS` is a path or glob** (e.g. `app/code/core/Mage/Catalog`, `**/*.phtml`):
  migrate every matching tracked file. This is a deliberate batch migration.
- **`$ARGUMENTS` is empty**: migrate only files changed on the current branch vs `main`
  (`git diff --name-only --diff-filter=d main...HEAD`). This matches the issue's
  "file-by-file, no big-bang" intent: you migrate the header of any file you were
  already touching.

In both cases restrict to tracked files with these extensions and skip anything under
`vendor/`, `node_modules/`, `.git/`, and minified assets (`*.min.js`, `*.min.css`):

```
php  phtml  js  css  xml  html  htm
```

Then filter to files that still carry the **old** header: they contain ` * Maho` as a
summary line, or an `@license` tag, or `@copyright`. Files already in SPDX format
(`SPDX-License-Identifier:` present) are skipped, so the skill is idempotent and safe to
re-run.

## 2. License identifier — translate verbatim, do not reassign by extension

> **Important — resolves a contradiction in the issue.** Issue #939's "License split"
> paragraph says PHP/JS/CSS code is OSL-3.0, but its own JS/CSS example block (and real
> files like `public/js/mage/cookies.js`) carry AFL-3.0. The safe, correct rule for
> **existing** files is to **preserve the license the file already declares** — Magento
> deliberately licensed frontend JS/skin assets as AFL and core code as OSL, and the
> `@license` URL already encodes that choice. Translate it 1:1:

| Existing `@license` URL contains | SPDX-License-Identifier |
| -------------------------------- | ----------------------- |
| `osl-3.0`                        | `OSL-3.0`               |
| `afl-3.0`                        | `AFL-3.0`               |

The by-extension **split applies only to brand-new files** (files you are creating, not
migrating):

| New file type                              | License   |
| ------------------------------------------ | --------- |
| PHP, JS, CSS (source code)                 | `OSL-3.0` |
| PHTML, XML, HTML, image/asset companions   | `AFL-3.0` |

If a migrated file somehow has no `@license` to translate, fall back to the split table.

## 3. Copyright lines — preserve verbatim, just reformat

Convert each `@copyright` line to an `SPDX-FileCopyrightText:` line. Keep the holder and
year range **exactly as written** (git history is the attribution log; never invent or
"correct" inherited Magento/OpenMage years). Only reformat:

```
@copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
        →   SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
```

Rules:
- Drop the `Copyright (c) ` prefix.
- Parentheses around the URL become angle brackets `<...>`.
- One `SPDX-FileCopyrightText:` line per holder.
- **Order holders newest-maintainer-first: Maho, then OpenMage, then Magento**, then any
  other third-party authors (e.g. the original library author on a vendored file), ordered
  among themselves by copyright year, newest first. This is the reverse of how the legacy
  `@copyright` lines were written (Magento first) — the current maintainer leads. Not every
  holder is present in every file; just preserve the priority among those that are.
- **Bump only the Maho line's end year to the current year** (the year you are running
  this), since you are modifying the file. Leave Magento/OpenMage years untouched.
- Do **not** add yourself as a new copyright holder.

## 4. Canonical layouts per file type

### PHP (`.php`, `.phtml`) — docblock, license per §2

The PHP docblock must stay a `/** */` block (phpDocumentor and `@package` depend on it).
The phpdoc CI workflow strips ` * SPDX-` lines before generating docs, leaving the
canonical `summary / blank / tags` block — so the SPDX lines live *inside* the docblock.

```php
<?php

/**
 * Short class description ending with a period.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2016-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ModuleName
 */

declare(strict_types=1);
```

Layout rules:
- **Description on the first line, ending with a period.** It becomes the phpDocumentor
  summary. Write a genuine one-line description by reading the class/file. **Omit it
  entirely if the class name is self-explanatory** — do not write filler like "the Foo
  class." When omitted, the docblock opens directly with the SPDX block (no blank line):
  ```php
  /**
   * SPDX-FileCopyrightText: ...
   * SPDX-License-Identifier: OSL-3.0
   * @package Mage_ModuleName
   */
  ```
- When a description is present: description line, one blank ` *` line, then the SPDX
  block (tight — no blank lines inside it).
- `@package` immediately after the SPDX block, **no blank line above it**. Preserve the
  module's existing `@package` value. (Some old headers also carry `@category` — drop it;
  the issue's canonical layout keeps only `@package`.)
- For `.phtml` the docblock sits after the opening `<?php`, exactly as today; preserve any
  `/** @var ... $this */` annotation lines that follow it.

**Two docblocks in one file — do NOT merge them.** Many files have a second docblock
sitting directly above the `class`/`interface`/`trait` line (often after `declare`/`use`),
e.g.:

```php
/**
 * Maho
 * ...license docblock...
 */

declare(strict_types=1);

/**
 * @method int getParentId()
 * @method $this setParentId(int $value)
 */
class Mage_Admin_Model_Acl_Role extends Mage_Core_Model_Abstract
```

phpDocumentor treats the top block as **file-level** (it owns SPDX + `@package`) and the
second block as **class-level** (it owns `@method`/`@property`/`@mixin` annotations and/or
the class description). Keep them separate:

- Convert only the **top** docblock to SPDX. Leave the class-level docblock exactly where
  it is. **Never hoist `@method`/`@property`/`@mixin` lines into the license block** —
  IDEs and PHPStan read them off the class element.
- The class **description** belongs in exactly one place, never both:
  - If the class-level block already has a prose description, that *is* the phpDocumentor
    class summary — leave it there and **omit** the description line in the top SPDX block.
  - If the class-level block has only `@method`/`@property` tags (no prose), put the
    one-line description in the top SPDX block (or omit if the class name is
    self-explanatory). Leave the annotation block untouched.
  - If there is only one docblock (no class-level block), the top SPDX block's first line
    becomes the class description — write a meaningful one-liner there (or omit).

A `.phtml` is a template, so on a **new** template use `AFL-3.0`; on migration translate
its existing `@license` per §2 (templates already declare AFL).

### XML / HTML (`.xml`, `.html`, `.htm`) — comment block, AFL on new files

Current XML headers wrap a `/** */` docblock inside an XML comment. Replace the whole
inner block with a clean SPDX comment (no `/** */`, no leading ` * `):

```xml
<?xml version="1.0"?>
<!--
SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
SPDX-License-Identifier: AFL-3.0
-->
```

Keep the `<?xml ... ?>` prolog as the first line when present; the comment follows it.

### JS (`.js`) — line comments

```js
// SPDX-FileCopyrightText: 2018-2022 The OpenMage Contributors <https://openmage.org>
// SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
// SPDX-License-Identifier: AFL-3.0
```

(Note the AFL on inherited frontend JS — translate the existing `@license`, see §2.)

### CSS (`.css`) — block comment, **not** `//`

> **Important — resolves a second issue glitch.** The issue shows `//` for "JS / CSS",
> but `//` is **invalid in plain CSS** and can break the stylesheet. CSS must use a
> `/* */` block comment:

```css
/*
SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
SPDX-License-Identifier: OSL-3.0
*/
```

## 5. Preserving real descriptions in JS/CSS/XML

The ` * Maho` line is noise — always drop it. But some files have a **genuine** multi-line
description after it (e.g. `giftcard.css`: "Gift Card PDF Styles / Override this file..."). 
Keep that description, placed above the SPDX block in the file's native comment syntax,
separated by a blank comment line. Use judgment: keep meaningful prose, drop boilerplate.

## 6. What to remove

In every migrated header, delete:
- the ` * Maho` (or `Maho`) summary line and its trailing blank ` *` line,
- every `@copyright` tag (replaced by `SPDX-FileCopyrightText:`),
- the `@license` tag (replaced by `SPDX-License-Identifier:`),
- any `@category` tag.

Keep `@package` (PHP/phtml only). Keep `@var`/other functional annotations.

## 7. Verify

After migrating, run these checks over the migrated set and report the result:

```bash
# No legacy tags should remain in migrated files:
grep -rl -E '@license|@copyright|^ \* Maho$' <migrated files>     # expect: none

# Every migrated file must now declare a license:
grep -L 'SPDX-License-Identifier:' <migrated files>               # expect: none

# CSS must not contain // SPDX (would be invalid):
grep -l '^// SPDX' -- '*.css'                                     # expect: none
```

Then run the repo's linters on touched PHP so formatting stays clean:

```bash
composer lint:cs-fixer    # or: vendor/bin/php-cs-fixer fix
```

Report: how many files migrated, broken down by extension and resulting license, plus any
files you skipped because they needed a description you couldn't confidently write (list
them so the user can supply wording).

## Notes for cross-repo use

- This skill is repo-agnostic: it keys off file extension and the legacy header signature,
  not hardcoded paths, so it runs the same in `maho`, module repos, and theme repos.
- The standalone `strip-maho-phpdoc.sh` (which only removes the ` * Maho` line) is
  **superseded** by this skill — the SPDX migration removes that line as part of the
  conversion. Don't run both.
- If a repo's `AGENTS.md`/`CLAUDE.md` still documents the old `@copyright`/`@license`
  header for new files, update that section to the SPDX layout too, or new files will keep
  being created in the old format.
