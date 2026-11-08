// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0
//
// Marks the pages that a pull request changes in the docs preview of mahocommerce.com: a bar at
// the bottom of each page, and a dot next to each changed page in the navigation. The Docs
// workflow adds this script to the preview build only.

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

    const pathOf = (link) => new URL(link.href, location.href).pathname;

    const element = (tag, text) => {
        const node = document.createElement(tag);
        if (text) {
            node.textContent = text;
        }
        return node;
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
        if (changed.includes(location.pathname)) {
            bar.append(element('strong', 'This page changed'));
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
