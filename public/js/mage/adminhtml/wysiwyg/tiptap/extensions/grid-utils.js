/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Gap size options shared across grid extensions
 */
export const GAP_SIZES = {
    'none': '0',
    'small': '0.5rem',
    'medium': '1rem',
    'large': '2rem',
};

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
 * @param {Function} [config.onBadgeClick] - (node, bubbleMenu) => extra bubble menu logic
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
        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'grid-badge';
        badge.innerHTML = `${config.badgeLabel} ${SETTINGS_ICON}`;

        badge.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const bubbleMenu = editor.storage[config.storageName]?.bubbleMenu;
            if (!bubbleMenu) return;

            // Update gap button active states
            const currentGap = node.attrs.gap || 'medium';
            for (const btn of bubbleMenu.querySelectorAll('[data-gap]')) {
                btn.classList.toggle('is-active', btn.dataset.gap === currentGap);
            }

            // Extension-specific bubble menu updates
            config.onBadgeClick?.(node, bubbleMenu);

            // Position below the badge
            const rect = badge.getBoundingClientRect();
            bubbleMenu.style.position = 'fixed';
            bubbleMenu.style.top = `${rect.bottom + 6}px`;
            bubbleMenu.style.left = `${rect.left}px`;
            bubbleMenu.style.display = 'flex';

            // Close when clicking outside
            const closeMenu = (event) => {
                if (!bubbleMenu.contains(event.target) && event.target !== badge) {
                    bubbleMenu.style.display = 'none';
                    document.removeEventListener('click', closeMenu);
                }
            };
            setTimeout(() => document.addEventListener('click', closeMenu), 0);
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
