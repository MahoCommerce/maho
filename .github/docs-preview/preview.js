// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0
//
// Marks the pages that a pull request changes in the docs preview of mahocommerce.com: a bar at
// the bottom of each page, a dot next to each changed page in the navigation, and a word diff of
// the Markdown source of the current page. The Docs workflow adds this script to the preview build only.

(() => {
    if (window.docsPreviewLoaded) {
        return;
    }
    window.docsPreviewLoaded = true;

    const style = document.createElement('style');
    style.textContent = `
        .docs-preview-bar {
            position: fixed;
            z-index: 10;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: .4em 1em;
            max-width: calc(100% - 2rem);
            padding: .5em 1.2em;
            border-radius: 1.5em;
            background: #e11d48;
            color: #fff;
            font: 500 .7rem/1.4 var(--md-text-font-family, sans-serif);
            box-shadow: 0 .3em 1em rgb(0 0 0 / 25%);
        }
        .docs-preview-bar a { color: inherit; text-decoration: underline; }
        .docs-preview-bar details { position: relative; }
        .docs-preview-bar summary { cursor: pointer; }
        .docs-preview-bar ul {
            position: absolute;
            bottom: calc(100% + 1em);
            left: 50%;
            transform: translateX(-50%);
            width: max-content;
            max-width: min(28rem, calc(100vw - 2rem));
            max-height: 60vh;
            overflow: auto;
            margin: 0;
            padding: .6em 1em;
            list-style: none;
            border-radius: .5em;
            background: #fff;
            box-shadow: 0 .3em 1em rgb(0 0 0 / 25%);
        }
        .docs-preview-bar li { padding: .2em 0; }
        .docs-preview-bar li a { color: #e11d48; }
        .docs-preview-bar button {
            padding: 0;
            border: 0;
            background: none;
            color: inherit;
            font: inherit;
            text-decoration: underline;
            cursor: pointer;
        }
        .docs-preview-diff {
            position: absolute;
            bottom: calc(100% + 1em);
            left: 50%;
            transform: translateX(-50%);
            width: min(48rem, calc(100vw - 2rem));
            max-height: 70vh;
            overflow: auto;
            padding: .6em 1em;
            border-radius: .5em;
            background: var(--md-default-bg-color, #fff);
            color: var(--md-default-fg-color, #000);
            font: .7rem/1.6 var(--md-code-font-family, monospace);
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            box-shadow: 0 .3em 1em rgb(0 0 0 / 25%);
        }
        .docs-preview-diff[hidden] { display: none; }
        .docs-preview-diff h4 {
            margin: 1em 0 .3em;
            font: inherit;
            opacity: .6;
        }
        .docs-preview-diff h4:first-child { margin-top: 0; }
        .docs-preview-diff div { min-height: 1.6em; padding: 0 .3em; }
        .docs-preview-diff ins { background: rgb(34 197 94 / 30%); text-decoration: none; }
        .docs-preview-diff del { background: rgb(225 29 72 / 25%); }
        .docs-preview-diff .docs-preview-added { background: rgb(34 197 94 / 12%); }
        .docs-preview-diff .docs-preview-removed { background: rgb(225 29 72 / 10%); }
        .md-nav__link.docs-preview-changed::after {
            content: '';
            flex: none;
            width: .5em;
            height: .5em;
            margin-left: .4em;
            border-radius: 50%;
            background: #e11d48;
        }
    `;
    document.head.append(style);

    const data = fetch('/_preview/changed.json')
        .then((response) => (response.ok ? response.json() : null))
        .catch(() => null);

    let diffs = null;
    const diffOf = (path) => {
        diffs ??= fetch('/_preview/diff.json')
            .then((response) => (response.ok ? response.json() : {}))
            .catch(() => ({}));
        return diffs.then((all) => all[path.slice(1)] ?? null);
    };

    const pathOf = (link) => new URL(link.href, location.href).pathname;

    const element = (tag, text) => {
        const node = document.createElement(tag);
        if (text) {
            node.textContent = text;
        }
        return node;
    };

    const renderDiff = (panel, hunks) => {
        if (!hunks || hunks.length === 0) {
            panel.append(element('p', 'The diff is not available.'));
            return;
        }
        for (const hunk of hunks) {
            panel.append(element('h4', `Line ${hunk.line}`));
            for (const parts of hunk.lines) {
                const line = element('div');
                const operations = new Set(parts.map(([operation]) => operation));
                if (operations.size === 1 && operations.has('+')) {
                    line.className = 'docs-preview-added';
                } else if (operations.size === 1 && operations.has('-')) {
                    line.className = 'docs-preview-removed';
                }
                for (const [operation, text] of parts) {
                    line.append(operation === '+' ? element('ins', text) : operation === '-' ? element('del', text) : text);
                }
                panel.append(line);
            }
        }
    };

    // The panel and the list of changed pages open at the same place, so each one closes the other.
    const changesToggle = (bar, path) => {
        const button = element('button', 'Show changes');
        button.type = 'button';
        const panel = element('div');
        panel.className = 'docs-preview-diff';
        panel.hidden = true;
        button.addEventListener('click', async () => {
            if (panel.hidden && panel.childElementCount === 0) {
                renderDiff(panel, await diffOf(path));
            }
            panel.hidden = !panel.hidden;
            button.textContent = panel.hidden ? 'Show changes' : 'Hide changes';
            if (!panel.hidden) {
                bar.querySelector('details')?.removeAttribute('open');
            }
        });
        bar.addEventListener('toggle', (event) => {
            if (event.target.open && !panel.hidden) {
                button.click();
            }
        }, true);
        return [button, panel];
    };

    const mark = async () => {
        const preview = await data;
        if (!preview) {
            return;
        }
        const changed = preview.changed.map((path) => `/${path}`);
        // The table of contents uses the same class, with #anchor links to the current page.
        const navLinks = [...document.querySelectorAll('.md-nav__link[href]')]
            .filter((link) => new URL(link.href, location.href).hash === '');
        for (const node of document.querySelectorAll('.docs-preview-changed')) {
            node.classList.remove('docs-preview-changed');
        }
        for (const link of navLinks.filter((link) => changed.includes(pathOf(link)))) {
            link.classList.add('docs-preview-changed');
            // Mark each section that holds the page, so that a collapsed section shows it too.
            let section = link.closest('.md-nav__item--nested');
            while (section) {
                section.querySelector(':scope > .md-nav__link')?.classList.add('docs-preview-changed');
                section = section.parentElement.closest('.md-nav__item--nested');
            }
        }

        document.querySelector('.docs-preview-bar')?.remove();
        const bar = element('div');
        bar.className = 'docs-preview-bar';
        const intro = element('span', 'Preview of ');
        const pullRequest = element('a', `#${preview.pr}`);
        pullRequest.href = `https://github.com/${preview.repository}/pull/${preview.pr}`;
        intro.append(pullRequest);
        bar.append(intro);
        if ((preview.added ?? []).includes(location.pathname.slice(1))) {
            bar.append(element('strong', 'New page'));
        } else if (changed.includes(location.pathname)) {
            bar.append(element('strong', 'This page changed'), ...changesToggle(bar, location.pathname));
        }

        if (changed.length === 0) {
            bar.append(element('span', 'No page changed'));
        } else {
            const list = element('ul');
            for (const path of changed) {
                const title = navLinks.find((link) => pathOf(link) === path)?.textContent.trim();
                const link = element('a', title || path);
                link.href = path;
                const item = element('li');
                item.append(link);
                list.append(item);
            }
            const details = element('details');
            details.append(element('summary', `${changed.length} changed ${changed.length === 1 ? 'page' : 'pages'}`), list);
            bar.append(details);
        }
        if (preview.removed.length > 0) {
            bar.append(element('span', `${preview.removed.length} removed`));
        }
        document.body.append(bar);
    };

    // With navigation.instant, the theme loads the next page without a reload and emits it on document$.
    if (window.document$) {
        window.document$.subscribe(mark);
    } else {
        mark();
    }
})();
