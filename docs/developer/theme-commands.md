---
description: Reference for the three Maho CLI commands that create, build and export storefront themes, with every option, what each one writes, and when you need it.
---

# Theme Commands <span class="version-badge">v26.9+</span>

Three commands cover the life of a storefront theme:

| Command | What it does | Who needs it |
|---|---|---|
| `dev:frontend:theme:create` | Scaffolds a new theme, as plain CSS or with its own Tailwind build | Every theme author |
| `dev:frontend:theme:build` | Compiles the CSS sources of a theme into its deployable bundles | Themes with a `src/` directory, and Maho contributors |
| `dev:frontend:theme:export` | Writes the Theme Settings of a store into a `theme.css` file | Developers who move admin settings into git |
| `theme:content:build` | Compiles the classes of the CMS, blog and catalog content into a copy of `css/styles.css` | Deploy scripts of stores that use classes in content |

All three run from the project root with `./maho`. The [Theme Development Guide](theme-development.md) explains the theme system the commands work on. This page is the reference for the commands themselves.

!!! info "Three ways to restyle a store"
    Maho has three customization paths. **The admin** (System > Configuration > Design > Theme Settings) restyles a store with no files and no build. **A plain CSS theme** sets the same variables in a `css/theme.css` file and adds its own rules. **A Tailwind theme** compiles its own stylesheet, for when you write your own templates with Tailwind class names. The commands below serve the last two paths, and `export` is the bridge from the first one.

## dev:frontend:theme:create

Creates a theme with the correct directory structure and starter files.

```bash
./maho dev:frontend:theme:create
```

### Interactive mode

Without options, the command asks four questions:

1. **Package name**, for example `acmestore`.
2. **Theme name**, default `default`.
3. **Parent theme**, chosen from a list of the installed themes. The suggested parent is `acmestore/default` when the package already has a default theme, and `base/default` otherwise. Choose **Other** to type any `package/theme` value.
4. **Use Tailwind?**, default no. Answer no to write plain CSS in `css/theme.css`. Answer yes to write `src/tailwind.css` and compile it with the build command. You can change your mind later, because both shapes end up in the same `css/` files.

Names must be lowercase, start with a letter, and contain only letters, digits, hyphens and underscores.

The command refuses to overwrite a theme that exists. When you create a theme that is not `default` in a package that has no `default` theme, the command warns you and asks to confirm. A package default theme is the recommended base: every other theme in the package inherits from it automatically.

### Non-interactive mode

Pass `--package` to skip every question. The other options fill in the remaining answers.

| Option | Short | Value | Effect |
|---|---|---|---|
| `--package` | `-p` | package name | Required for non-interactive mode |
| `--theme` | `-t` | theme name | Default `default` |
| `--parent` | | `package/theme` | Default: the package default theme when it exists, otherwise `base/default` |
| `--tailwind` / `--no-tailwind` | | | Choose the plain CSS shape or the Tailwind shape. Default: plain CSS |

```bash
# A plain CSS theme, inheriting from base/default
./maho dev:frontend:theme:create -p acmestore -t default

# A plain CSS theme that starts from the Fashion identity
./maho dev:frontend:theme:create -p acmestore -t default --parent base/fashion

# A theme with its own Tailwind build
./maho dev:frontend:theme:create -p acmestore -t default --tailwind
```

The parent must exist. When it does not, the command lists the available themes and stops.

### What it writes

For a plain CSS theme:

```
app/design/frontend/acmestore/default/
├── etc/theme.xml          Declares the parent theme
└── layout/local.xml       Your layout changes, with commented examples

public/skin/frontend/acmestore/default/
└── css/theme.css          Your design tokens and CSS rules
```

For a Tailwind theme, `css/theme.css` is replaced by `src/tailwind.css`, the build entry. Its first line imports Maho's own build entry, which carries the whole configuration: the daisyUI plugin, the component layer and the template scan rules.

`css/theme.css` needs no entry in `local.xml`: every page already loads it after the compiled `styles.css`.

When the parent is not `base/default`, the generated `theme.css` starts with an import of the parent's `theme.css`. The skin fallback serves the first `theme.css` it finds, so a file in your theme would otherwise hide the whole identity of the parent.

### After a Tailwind scaffold

When the Tailwind toolchain is installed, the command compiles the theme once, so `css/styles.css` exists. Without the toolchain, it prints a warning and the storefront shows the colors of the parent until you run the build command, which offers to install the toolchain.

A Tailwind theme owns its compiled engine. Maho's `styles.css` no longer reaches the store, so rebuild the theme after every Maho upgrade.

### Next steps

The command prints them at the end:

1. In the admin, go to **System > Configuration > Design**. Set the package and the theme.
2. Edit `css/theme.css` (plain CSS) or `src/tailwind.css` (Tailwind).
3. Add layout changes to `layout/local.xml`, and templates to `template/` only when you must.

## dev:frontend:theme:build

Compiles the CSS sources of every theme that has them.

```bash
./maho dev:frontend:theme:build
```

### What it compiles

The command finds every top-level `src/*.css` file of every theme under `public/skin/frontend/`, except files whose names start with an underscore. Those are partials that the entries import. Each entry compiles into `css/`, minified, with a name that follows the entry:

| Source | Output |
|---|---|
| `src/tailwind.css` | `css/styles.css`, which replaces Maho's compiled bundle |
| `src/theme.css` | `css/theme.css` |
| `src/<name>.css` | `css/<name>.css` |

A theme without a `src/` directory needs no build. Its `css/` files are served as they are.

### Options

| Option | Short | Effect |
|---|---|---|
| `--theme package/theme` | `-t` | Build one theme instead of all |
| `--watch` | `-w` | Rebuild on every change until you press Ctrl+C |
| `--map` | | Add an inline source map, so the browser developer tools show the `src/` files |

```bash
# Build one theme
./maho dev:frontend:theme:build --theme acmestore/default

# Rebuild while you work
./maho dev:frontend:theme:build --theme acmestore/default --watch
```

Watch output is not minified. Run a plain build, without `--watch` and `--map`, before you commit.

The admin skin has the same build, with `dev:admin:theme:build`. See [Admin theme](admin-theme.md#rebuild-the-admin-css).

### The toolchain

The command looks for the Tailwind CLI in `node_modules/.bin/tailwindcss` in the project root. When it is missing, the command offers to install `tailwindcss`, `@tailwindcss/cli` and `daisyui` as dev dependencies. It picks the package manager whose lock file the project carries, from npm, bun, pnpm and yarn, and falls back to the first one installed. A project whose `package.json` already declares `@tailwindcss/cli` gets a plain install, so the pinned versions win.

The toolchain needs Node.js or Bun. Without either, the command stops with an error. Store owners never need it: the admin settings and plain CSS themes work without a build.

This works the same when Maho is a Composer dependency of your project. The project root is the working directory in both cases, and the toolchain installs into the project's `node_modules/`.

### Commit the output

The compiled `css/` files are meant to be committed, so production needs no Node.js for the classes of the templates. Maho's own repository runs the same command in CI and fails when the committed bundles do not match the sources. The classes of the content in the database need the [content build](#themecontentbuild), which runs on the server.

### Exit status

- Success, with one line per compiled bundle.
- A warning and success when no theme has sources.
- Failure when `--theme` names a theme without sources, when the toolchain cannot be installed, or when a bundle fails to compile. The Tailwind error output is printed.

## dev:frontend:theme:export

Writes the Theme Settings of a store to a `theme.css` file.

```bash
./maho dev:frontend:theme:export --theme acmestore/default
```

A merchant tunes colors, fonts and shape in **System > Configuration > Design > Theme Settings**. This command turns that result into a file a developer keeps in git. The fields hold real CSS values, so the export is a straight copy: one `:root` block with every variable that is set, plus the quiet surface colors Maho derives from the page background.

### Options

| Option | Short | Effect |
|---|---|---|
| `--theme package/theme` | `-t` | Where to write: `public/skin/frontend/<package>/<theme>/css/theme.css` |
| `--store <code>` | `-s` | Read the settings of this store view. Default: the default scope |
| `--stdout` | | Print the file instead of writing it |
| `--force` | `-f` | Overwrite an existing `theme.css` |

```bash
# Review the output first
./maho dev:frontend:theme:export --stdout

# Export the settings of one store view
./maho dev:frontend:theme:export --store fashion_en --theme acmestore/fashion

# Replace the theme.css of an existing theme
./maho dev:frontend:theme:export --theme acmestore/default --force
```

The store code is the one shown in **System > Manage Stores**.

### Behavior

- When the scope has no Theme Settings, the command says so and stops.
- When the target `theme.css` exists, the command stops unless you pass `--force`. Use `--stdout` to review the output and merge it by hand into a theme that already has an identity file.
- `--theme` is required unless you pass `--stdout`.
- On success the command tells you the number of variables written.

After the export, commit the file and clear the fields in Theme Settings. The store looks the same, and the identity now lives in your theme.

A setting a module of yours adds to Theme Settings is exported too. See the [Theme Development Guide](theme-development.md#adding-your-own-setting).

## theme:content:build

Compiles the Tailwind classes of the CMS, blog and catalog content into a copy of the `css/styles.css` of each theme, and writes it to the media mount. The page head then links the copy.

```bash
./maho theme:content:build
```

A save of the content queues the same build, so a store with a queue worker needs the command only in its deploy script and after a script that writes content with SQL. It takes no options. It lists the sources of the content, then one row for each theme: `built`, `unchanged`, `empty` or `failed`. It fails when the Tailwind CLI is missing from `node_modules/.bin/`, when another build runs, or when a theme fails to compile.

See [Tailwind build of the content](../hosting/tailwind-content-build.md) for the requirements on the server, and [Classes in CMS and catalog content](theme-development.md#classes-in-cms-and-catalog-content) for the content of your own module.

## Which command when

| You want to | Run |
|---|---|
| Start a new theme | `dev:frontend:theme:create` |
| Write Tailwind class names in your own templates | `dev:frontend:theme:create --tailwind`, then `dev:frontend:theme:build` after each change |
| Change colors, fonts or shape without files | Nothing: use Theme Settings in the admin |
| Keep admin settings in git | `dev:frontend:theme:export` |
| Upgrade Maho with a Tailwind theme | `dev:frontend:theme:build` |
| Work on Maho's own base theme | `dev:frontend:theme:build --watch` |
| Serve the classes of the content at once after a deploy | `theme:content:build` |
