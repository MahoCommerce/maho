// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0
//
// Marks the pages that a pull request changes in the docs preview of mahocommerce.com: a banner
// at the top of each page, and a dot next to each changed page in the navigation. The Docs
// workflow adds this script to the preview build only.

(() => {
    if (window.docsPreviewLoaded) {
        return;
    }
    window.docsPreviewLoaded = true;

    const style = document.createElement('style');
    style.textContent = `
        .docs-preview-banner {
            margin: 0 0 1.5em;
            padding: .8em 1em;
            border-left: 4px solid #e11d48;
            background: color-mix(in srgb, #e11d48 8%, transparent);
            font-size: .75rem;
        }
        .docs-preview-banner p, .docs-preview-banner ul { margin: 0; }
        .docs-preview-banner ul { margin-top: .4em; }
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

        document.querySelector('.docs-preview-banner')?.remove();
        const banner = element('div');
        banner.className = 'docs-preview-banner';
        const intro = element('p', 'Preview of pull request ');
        const pullRequest = element('a', `#${preview.pr}`);
        pullRequest.href = `https://github.com/${preview.repository}/pull/${preview.pr}`;
        intro.append(pullRequest, '.');
        if (changed.includes(location.pathname)) {
            intro.append(element('strong', ' This page changed.'));
        }
        if (changed.length === 0) {
            intro.append(' No page changed.');
        }
        if (preview.removed.length > 0) {
            intro.append(` Removed pages: ${preview.removed.join(', ')}.`);
        }
        banner.append(intro);

        if (changed.length > 0) {
            const list = element('ul');
            for (const path of changed) {
                const title = navLinks.find((link) => pathOf(link) === path)?.textContent.trim();
                const link = element('a', title || path);
                link.href = path;
                const item = element('li');
                item.append(link);
                list.append(item);
            }
            banner.append(element('p', 'Changed pages:'), list);
        }
        document.querySelector('.md-content__inner')?.prepend(banner);
    };

    // With navigation.instant, the theme loads the next page without a reload and emits it on document$.
    if (window.document$) {
        window.document$.subscribe(mark);
    } else {
        mark();
    }
})();
