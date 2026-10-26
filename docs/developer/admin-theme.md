---
description: How the Maho admin is styled with Tailwind CSS 4 and daisyUI 5, how an extension matches it, and how to rebuild the admin CSS.
---

# Admin Theme <span class="version-badge">v26.11+</span>

The admin skin is built on Tailwind CSS 4 and daisyUI 5, like the [storefront](theme-development.md). One theme covers light and dark mode.

## Match the admin in an extension

Keep the admin classes in your templates: `button.save`, `button.delete`, `input.input-text`, `.entry-edit-head` with `.fieldset`, `.messages` and the others. The admin gives them the daisyUI styles, so your screens match with no CSS of their own.

In the admin, daisyUI classes carry the `d-` prefix (`d-btn`, `d-badge`), because names such as `.grid` and `.fieldset` already have a meaning in the admin.

For your own CSS, use the theme variables, not fixed colors. They change with dark mode:

```css
.my-module-panel {
    color: var(--color-base-content);
    background: var(--color-base-100);
    border: 1px solid var(--color-base-300);
    border-radius: var(--maho-radius-lg);
}
```

The `--maho-*` variables of earlier releases keep their names and values.

## Rebuild the admin CSS

The compiled `css/styles.css` is committed, so you need a build only when you change the `src/` files of an admin skin:

```bash
./maho dev:admin:theme:build
```

It takes the same options and toolchain as [`dev:frontend:theme:build`](theme-commands.md#devfrontendthemebuild).
