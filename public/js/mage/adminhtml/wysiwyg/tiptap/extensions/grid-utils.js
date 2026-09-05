// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * Gap size options shared across grid extensions
 */
export const GAP_SIZES = {
    'none': '0',
    'small': '0.5rem',
    'medium': '1rem',
    'large': '2rem',
};

/**
 * Background options shared across grid extensions: a palette color applied
 * to a whole grid (a band) or to one cell (a card)
 */
export const BACKGROUNDS = ['none', 'muted', 'primary', 'neutral', 'accent'];

/**
 * The `background` attribute definition shared by grids and cells. It renders
 * as data-background and is omitted when 'none', so untouched content stays
 * untouched
 */
export function backgroundAttribute() {
    return {
        default: 'none',
        parseHTML: element => element.getAttribute('data-background') || 'none',
        renderHTML: attributes => attributes.background && attributes.background !== 'none' ? { 'data-background': attributes.background } : {},
    };
}

export function setBackgroundAttr(dom, node) {
    if (node.attrs.background && node.attrs.background !== 'none') {
        dom.setAttribute('data-background', node.attrs.background);
    } else {
        dom.removeAttribute('data-background');
    }
}

/**
 * The `bleed` attribute of a grid: 'boxed' keeps the band inside the content
 * column, 'full' stretches its background to the viewport edges
 */
export function bleedAttribute() {
    return {
        default: 'boxed',
        parseHTML: element => element.getAttribute('data-bleed') || 'boxed',
        renderHTML: attributes => attributes.bleed === 'full' ? { 'data-bleed': 'full' } : {},
    };
}

export function setBleedAttr(dom, node) {
    if (node.attrs.bleed === 'full') {
        dom.setAttribute('data-bleed', 'full');
    } else {
        dom.removeAttribute('data-bleed');
    }
}

/**
 * Command factory: set one attribute of the nearest node of one of the given types
 */
export function setNodeAttrCommand(nodeTypeNames, attrName) {
    const names = Array.isArray(nodeTypeNames) ? nodeTypeNames : [nodeTypeNames];
    return (value) => ({ state, tr, dispatch }) => {
        const { $from } = state.selection;
        for (let depth = $from.depth; depth > 0; depth--) {
            const node = $from.node(depth);
            if (!names.includes(node.type.name)) {
                continue;
            }
            if (dispatch) {
                tr.setNodeMarkup($from.before(depth), null, { ...node.attrs, [attrName]: value });
                dispatch(tr);
            }
            return true;
        }
        return false;
    };
}

/**
 * Sync the background select and the width buttons of a grid bubble menu
 */
export function syncGridMenu(bubbleMenu, gridNode) {
    const select = bubbleMenu.querySelector('select[data-background-select]');
    if (select) {
        select.value = gridNode.attrs.background || 'none';
    }
    const bleed = gridNode.attrs.bleed || 'boxed';
    for (const btn of bubbleMenu.querySelectorAll('[data-bleed]')) {
        btn.classList.toggle('is-active', btn.dataset.bleed === bleed);
    }
}

const SETTINGS_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';

/**
 * Convert pixel widths to fr values, normalized and rounded to 2 decimal places
 */
export function pixelsToFr(pixelWidths) {
    const total = pixelWidths.reduce((a, b) => a + b, 0);
    return pixelWidths
        .map(px => Math.max(Math.round((px / total) * 100) / 100, 0.05))
        .map(ratio => `${ratio}fr`)
        .join(' ');
}

/**
 * Helper function to find parent node of specific type
 */
export function findParentNodeOfType(nodeType) {
    return (selection) => {
        const { $from } = selection;
        for (let depth = $from.depth; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type === nodeType) {
                return {
                    node,
                    pos: $from.before(depth),
                    depth,
                };
            }
        }
        return null;
    };
}

/**
 * Badge button that opens an extension's bubble menu below itself
 *
 * @param {string} label - Text shown on the badge
 * @param {Object} editor - TipTap editor instance
 * @param {string} storageName - Key in editor.storage holding the bubble menu ref
 * @param {Function} [onOpen] - (bubbleMenu) => sync the menu to the current node before showing it
 */
export function createBadge(label, editor, storageName, onOpen) {
    const badge = document.createElement('button');
    badge.type = 'button';
    badge.className = 'grid-badge';
    badge.innerHTML = `<span class="grid-badge-label"></span>${SETTINGS_ICON}`;
    badge.querySelector('.grid-badge-label').textContent = label;

    // A mousedown on the badge must not move focus out of the cell, or the
    // cell badge hides before its click lands
    badge.addEventListener('mousedown', (e) => e.preventDefault());
    badge.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const bubbleMenu = editor.storage[storageName]?.bubbleMenu;
        if (!bubbleMenu) return;

        onOpen?.(bubbleMenu);

        // Position below the badge, and pull the menu back when it would
        // leave the viewport (a cell badge sits at the right edge)
        const rect = badge.getBoundingClientRect();
        bubbleMenu.style.position = 'fixed';
        bubbleMenu.style.top = `${rect.bottom + 6}px`;
        bubbleMenu.style.left = `${rect.left}px`;
        bubbleMenu.style.display = 'flex';
        const overflow = rect.left + bubbleMenu.offsetWidth - (window.innerWidth - 8);
        if (overflow > 0) {
            bubbleMenu.style.left = `${Math.max(8, rect.left - overflow)}px`;
        }

        // Close when clicking outside
        const closeMenu = (event) => {
            if (!bubbleMenu.contains(event.target) && event.target !== badge) {
                bubbleMenu.style.display = 'none';
                document.removeEventListener('click', closeMenu);
            }
        };
        setTimeout(() => document.addEventListener('click', closeMenu), 0);
    });

    return badge;
}

/**
 * Update the text of a badge created by createBadge()
 */
export function setBadgeLabel(badge, label) {
    badge.querySelector('.grid-badge-label').textContent = label;
}

/**
 * Factory for creating grid-based TipTap NodeViews (Columns, Bento Grid)
 *
 * Handles the shared boilerplate: outer wrapper, badge button with bubble menu,
 * grid container, column resize handles, ignoreMutation, update, and destroy.
 *
 * @param {Object} config
 * @param {string} config.nodeName - TipTap node type name (e.g. 'mahoColumns')
 * @param {string} config.storageName - Key in editor.storage for bubble menu ref
 * @param {string} config.dataType - data-type attribute value (e.g. 'maho-columns')
 * @param {string} config.badgeLabel - Text shown on the badge button
 * @param {string} config.layoutAttr - Node attr storing column fr values ('layout' or 'columns')
 * @param {Function} config.setDataAttrs - (dom, node) => set data-* attributes on wrapper
 * @param {Function} config.updateGridStyles - (contentDOM, node, gap) => update grid CSS
 * @param {Function} config.positionHandles - (handles, contentDOM, node, widths, activeCount) => position resize handles
 * @param {Function} [config.onBadgeClick] - (node, bubbleMenu, editor) => extra bubble menu logic
 */
export function createGridNodeView(config) {
    return ({ node: initialNode, editor, getPos }) => {
        let node = initialNode;
        const gap = GAP_SIZES[node.attrs.gap] || GAP_SIZES.medium;

        // Outer wrapper
        const dom = document.createElement('div');
        dom.setAttribute('data-type', config.dataType);
        config.setDataAttrs(dom, node);
        dom.style.position = 'relative';
        dom.style.width = '100%';

        // Badge button
        const badge = createBadge(config.badgeLabel, editor, config.storageName, (bubbleMenu) => {
            // Update gap button active states
            const currentGap = node.attrs.gap || 'medium';
            for (const btn of bubbleMenu.querySelectorAll('[data-gap]')) {
                btn.classList.toggle('is-active', btn.dataset.gap === currentGap);
            }

            // Extension-specific bubble menu updates
            config.onBadgeClick?.(node, bubbleMenu, editor);
        });

        dom.appendChild(badge);

        // Grid wrapper (holds grid + resize handles)
        const gridWrapper = document.createElement('div');
        gridWrapper.style.position = 'relative';
        dom.appendChild(gridWrapper);

        // Content DOM (the actual grid)
        const contentDOM = document.createElement('div');
        contentDOM.className = 'grid-inner';
        contentDOM.style.display = 'grid';
        config.updateGridStyles(contentDOM, node, gap);
        gridWrapper.appendChild(contentDOM);

        // Column resize handles
        const MAX_HANDLES = 3;
        const handles = [];
        const MIN_COL_WIDTH = 40;

        for (let i = 0; i < MAX_HANDLES; i++) {
            const handle = document.createElement('div');
            handle.className = 'grid-col-handle';
            handle.dataset.handleIndex = i;
            handle.style.display = 'none';
            const line = document.createElement('div');
            line.className = 'grid-col-handle-line';
            handle.appendChild(line);
            gridWrapper.appendChild(handle);
            handles.push(handle);
            handle.addEventListener('mousedown', onMouseDown);
        }

        function getColumnCount() {
            return node.attrs[config.layoutAttr].trim().split(/\s+/).length;
        }

        function getResolvedColumnWidths() {
            const computed = getComputedStyle(contentDOM).gridTemplateColumns;
            return computed.split(/\s+/).map(parseFloat);
        }

        function repositionHandles() {
            const colCount = getColumnCount();
            const activeCount = colCount - 1;

            for (let i = 0; i < handles.length; i++) {
                handles[i].style.display = 'none';
            }

            if (activeCount <= 0) return;

            const widths = getResolvedColumnWidths();
            if (widths.length <= 1 || isNaN(widths[0])) return;

            config.positionHandles(handles, contentDOM, node, widths, activeCount);
        }

        function onMouseDown(e) {
            e.preventDefault();
            e.stopPropagation();

            const handleIndex = parseInt(e.currentTarget.dataset.handleIndex, 10);
            const startX = e.clientX;
            const startWidths = getResolvedColumnWidths();
            const activeHandle = e.currentTarget;

            activeHandle.classList.add('dragging');
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';

            const onMouseMove = (moveEvent) => {
                const delta = moveEvent.clientX - startX;
                const newWidths = [...startWidths];

                const leftCol = handleIndex;
                const rightCol = handleIndex + 1;

                let newLeft = startWidths[leftCol] + delta;
                let newRight = startWidths[rightCol] - delta;

                if (newLeft < MIN_COL_WIDTH) {
                    newRight -= (MIN_COL_WIDTH - newLeft);
                    newLeft = MIN_COL_WIDTH;
                }
                if (newRight < MIN_COL_WIDTH) {
                    newLeft -= (MIN_COL_WIDTH - newRight);
                    newRight = MIN_COL_WIDTH;
                }

                newWidths[leftCol] = Math.max(newLeft, MIN_COL_WIDTH);
                newWidths[rightCol] = Math.max(newRight, MIN_COL_WIDTH);

                contentDOM.style.gridTemplateColumns = newWidths.map(w => `${w}px`).join(' ');
                repositionHandles();
            };

            const onMouseUp = () => {
                activeHandle.classList.remove('dragging');
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                const finalWidths = getResolvedColumnWidths();
                const frValues = pixelsToFr(finalWidths);

                const pos = getPos();
                if (typeof pos === 'number') {
                    const tr = editor.state.tr.setNodeMarkup(pos, null, {
                        ...node.attrs,
                        [config.layoutAttr]: frValues,
                        preset: 'custom',
                    });
                    editor.view.dispatch(tr);
                }
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }

        const resizeObserver = new ResizeObserver(() => repositionHandles());
        resizeObserver.observe(contentDOM);

        return {
            dom,
            contentDOM,
            ignoreMutation: (mutation) => {
                if (contentDOM.contains(mutation.target) && mutation.target !== contentDOM) {
                    return false;
                }
                if (mutation.target === contentDOM && mutation.type === 'childList') {
                    return false;
                }
                return true;
            },
            update: (updatedNode) => {
                if (updatedNode.type.name !== config.nodeName) {
                    return false;
                }

                node = updatedNode;

                const updatedGap = GAP_SIZES[updatedNode.attrs.gap] || GAP_SIZES.medium;
                config.setDataAttrs(dom, updatedNode);
                config.updateGridStyles(contentDOM, updatedNode, updatedGap);

                requestAnimationFrame(() => repositionHandles());

                return true;
            },
            destroy: () => {
                resizeObserver.disconnect();
            },
        };
    };
}

/**
 * Factory for the NodeView of a grid cell (Column, Bento Cell): the cell
 * element itself, a badge that opens the cell bubble menu, and an inner
 * content container
 *
 * @param {Object} config
 * @param {string} config.nodeName - TipTap node type name (e.g. 'mahoColumn')
 * @param {string} config.storageName - Key in editor.storage holding the bubble menu ref
 * @param {string} config.dataType - data-type attribute value (e.g. 'maho-column')
 * @param {string} config.badgeLabel - Text shown on the badge button
 * @param {Function} config.setDataAttrs - (dom, node) => set attributes on the cell
 * @param {Function} [config.onBadgeClick] - (node, bubbleMenu) => sync the menu to the cell
 */
export function createCellNodeView(config) {
    return ({ node: initialNode, editor, getPos }) => {
        let node = initialNode;

        const dom = document.createElement('div');
        dom.setAttribute('data-type', config.dataType);
        config.setDataAttrs(dom, node);

        const badge = createBadge(config.badgeLabel, editor, config.storageName, (bubbleMenu) => {
            // The badge is clicked without moving the cursor, so the menu
            // remembers which cell it was opened for
            bubbleMenu.dataset.pos = getPos();
            config.onBadgeClick?.(node, bubbleMenu);
        });
        badge.classList.add('cell-badge');
        dom.appendChild(badge);

        const contentDOM = document.createElement('div');
        contentDOM.className = 'cell-inner';
        dom.appendChild(contentDOM);

        return {
            dom,
            contentDOM,
            ignoreMutation: (mutation) => {
                if (contentDOM.contains(mutation.target) && mutation.target !== contentDOM) {
                    return false;
                }
                if (mutation.target === contentDOM && mutation.type === 'childList') {
                    return false;
                }
                return true;
            },
            update: (updatedNode) => {
                if (updatedNode.type.name !== config.nodeName) {
                    return false;
                }
                node = updatedNode;
                config.setDataAttrs(dom, updatedNode);
                return true;
            },
        };
    };
}
