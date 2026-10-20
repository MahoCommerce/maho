// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * The storefront side of the live preview of the content editor. The admin frames this
 * page, maybe from another origin, so the two talk through postMessage only.
 *
 * The scroll position is a ratio inside the previewed content, which the server puts
 * between two comments. The editor and the page do not share line heights, so a ratio
 * of the content keeps the two panes on the same passage.
 */
(() => {
    const SOURCE = 'maho-cms-preview';
    const START = 'cms-preview-start';
    const END = 'cms-preview-end';
    let parentOrigin = null;
    let locked = 0;

    const target = () => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
        let start = null;
        while (walker.nextNode()) {
            const text = walker.currentNode.data.trim();
            if (text === START && !start) {
                start = walker.currentNode;
            } else if (text === END && start) {
                const range = document.createRange();
                range.setStartAfter(start);
                range.setEndBefore(walker.currentNode);
                const rect = range.getBoundingClientRect();
                return { top: rect.top + window.scrollY, height: rect.height };
            }
        }
        return { top: 0, height: document.documentElement.scrollHeight };
    };

    const ratio = () => {
        const box = target();
        const span = Math.max(1, box.height - window.innerHeight);
        return Math.min(1, Math.max(0, (window.scrollY - box.top) / span));
    };

    const scrollTo = (value) => {
        const box = target();
        locked = performance.now() + 150;
        window.scrollTo(0, box.top + value * Math.max(0, box.height - window.innerHeight));
    };

    window.addEventListener('message', (event) => {
        const data = event.data;
        if (event.source !== window.parent || data?.source !== SOURCE) {
            return;
        }
        // The first message names the admin origin. Later messages must come from it.
        parentOrigin ??= event.origin;
        if (event.origin !== parentOrigin) {
            return;
        }
        if (data.type === 'scrollTo' && typeof data.ratio === 'number') {
            scrollTo(data.ratio);
        }
    });

    let frame = 0;
    window.addEventListener('scroll', () => {
        if (frame || parentOrigin === null || performance.now() < locked) {
            return;
        }
        frame = requestAnimationFrame(() => {
            frame = 0;
            window.parent.postMessage({ source: SOURCE, type: 'scroll', ratio: ratio() }, parentOrigin);
        });
    }, { passive: true });
})();
