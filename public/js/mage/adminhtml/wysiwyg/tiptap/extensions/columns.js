/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Node, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.20.0';

/**
 * Convert pixel widths to fr values, normalized and rounded to 2 decimal places
 */
function pixelsToFr(pixelWidths) {
    const total = pixelWidths.reduce((a, b) => a + b, 0);
    return pixelWidths
        .map(px => Math.max(Math.round((px / total) * 100) / 100, 0.05))
        .map(ratio => `${ratio}fr`)
        .join(' ');
}

/**
 * Column presets configuration
 */
export const COLUMN_PRESETS = {
    '2-equal': {
        label: '2 Columns',
        columns: 2,
        layout: '1fr 1fr',
    },
    '3-equal': {
        label: '3 Columns',
        columns: 3,
        layout: '1fr 1fr 1fr',
    },
    '4-equal': {
        label: '4 Columns',
        columns: 4,
        layout: '1fr 1fr 1fr 1fr',
    },
    'sidebar-left': {
        label: 'Sidebar Left',
        columns: 2,
        layout: '1fr 2fr',
    },
    'sidebar-right': {
        label: 'Sidebar Right',
        columns: 2,
        layout: '2fr 1fr',
    },
    'wide-center': {
        label: 'Wide Center',
        columns: 3,
        layout: '1fr 2fr 1fr',
    },
};

/**
 * Gap size options
 */
const GAP_SIZES = {
    'none': '0',
    'small': '0.5rem',
    'medium': '1rem',
    'large': '2rem',
};

/**
 * MahoColumn Node
 *
 * Individual column container within a columns layout
 */
export const MahoColumn = Node.create({
    name: 'mahoColumn',
    group: 'block',
    content: 'block+',
    isolating: true,
    defining: true,

    addAttributes() {
        return {};
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="maho-column"]',
        }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'maho-column' }), 0];
    },
});

/**
 * MahoColumns Node
 *
 * Container for column layout using CSS Grid
 */
export const MahoColumns = Node.create({
    name: 'mahoColumns',
    group: 'block',
    content: 'mahoColumn+',
    defining: true,
    isolating: true,

    addOptions() {
        return {
            bubbleMenu: null,
        };
    },

    addStorage() {
        return {
            bubbleMenu: this.options.bubbleMenu,
        };
    },

    addAttributes() {
        return {
            preset: {
                default: '2-equal',
                parseHTML: element => element.getAttribute('data-preset') || '2-equal',
                renderHTML: attributes => ({ 'data-preset': attributes.preset }),
            },
            layout: {
                default: '1fr 1fr',
                parseHTML: element => {
                    const style = element.getAttribute('style') || '';
                    const match = style.match(/grid-template-columns:\s*([^;]+)/);
                    return match ? match[1].trim() : '1fr 1fr';
                },
                renderHTML: attributes => ({
                    style: `grid-template-columns: ${attributes.layout}`,
                }),
            },
            gap: {
                default: 'medium',
                parseHTML: element => element.getAttribute('data-gap') || 'medium',
                renderHTML: attributes => ({ 'data-gap': attributes.gap }),
            },
            style: {
                default: 'none',
                parseHTML: element => element.getAttribute('data-style') || 'none',
                renderHTML: attributes => ({ 'data-style': attributes.style }),
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="maho-columns"]',
        }];
    },

    renderHTML({ HTMLAttributes, node }) {
        return ['div', mergeAttributes(HTMLAttributes, {
            'data-type': 'maho-columns',
            'data-preset': node.attrs.preset,
            'data-gap': node.attrs.gap,
            'data-style': node.attrs.style,
        }), 0];
    },

    addNodeView() {
        return ({ node: initialNode, editor, getPos }) => {
            let node = initialNode;
            const gap = GAP_SIZES[node.attrs.gap] || GAP_SIZES.medium;

            // Wrapper for badge positioning
            const dom = document.createElement('div');
            dom.setAttribute('data-type', 'maho-columns');
            dom.setAttribute('data-preset', node.attrs.preset);
            dom.setAttribute('data-gap', node.attrs.gap);
            dom.setAttribute('data-style', node.attrs.style);
            dom.style.position = 'relative';
            dom.style.width = '100%';

            // Badge button
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'columns-badge';
            badge.innerHTML = `Columns <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>`;

            badge.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const bubbleMenu = editor.storage.mahoColumns?.bubbleMenu;
                if (!bubbleMenu) return;

                // Update gap button active states
                const currentGap = node.attrs.gap || 'medium';
                for (const btn of bubbleMenu.querySelectorAll('[data-gap]')) {
                    btn.classList.toggle('is-active', btn.dataset.gap === currentGap);
                }

                // Update style button active states
                const currentStyle = node.attrs.style || 'none';
                for (const btn of bubbleMenu.querySelectorAll('[data-col-style]')) {
                    btn.classList.toggle('is-active', btn.dataset.colStyle === currentStyle);
                }

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

            // Wrapper holds the grid and resize handles together
            const gridWrapper = document.createElement('div');
            gridWrapper.style.position = 'relative';
            dom.appendChild(gridWrapper);

            // Grid container for columns (contentDOM)
            const contentDOM = document.createElement('div');
            contentDOM.className = 'columns-grid';
            contentDOM.style.cssText = `
                display: grid;
                grid-template-columns: ${node.attrs.layout};
                gap: ${gap};
            `;
            gridWrapper.appendChild(contentDOM);

            // Column resize handles
            const MAX_HANDLES = 3; // max 4 columns = 3 boundaries
            const handles = [];
            const MIN_COL_WIDTH = 40;

            for (let i = 0; i < MAX_HANDLES; i++) {
                const handle = document.createElement('div');
                handle.className = 'columns-col-handle';
                handle.dataset.handleIndex = i;
                handle.style.display = 'none';
                const line = document.createElement('div');
                line.className = 'columns-col-handle-line';
                handle.appendChild(line);
                gridWrapper.appendChild(handle);
                handles.push(handle);

                handle.addEventListener('mousedown', onMouseDown);
            }

            function getColumnCount() {
                return node.attrs.layout.trim().split(/\s+/).length;
            }

            function getResolvedColumnWidths() {
                const computed = getComputedStyle(contentDOM).gridTemplateColumns;
                return computed.split(/\s+/).map(parseFloat);
            }

            function positionHandles() {
                const colCount = getColumnCount();
                const activeCount = colCount - 1;

                for (let i = 0; i < handles.length; i++) {
                    handles[i].style.display = 'none';
                }

                if (activeCount <= 0) return;

                const widths = getResolvedColumnWidths();
                if (widths.length <= 1 || isNaN(widths[0])) return;

                const styles = getComputedStyle(contentDOM);
                const colGap = parseFloat(styles.columnGap) || 0;
                const gridHeight = contentDOM.offsetHeight;

                let cumulative = 0;
                for (let i = 0; i < activeCount; i++) {
                    cumulative += widths[i];
                    const left = cumulative + (colGap * (i + 1)) - (colGap / 2);
                    const h = handles[i];
                    h.style.display = 'block';
                    h.style.left = `${left}px`;
                    h.style.top = '0';
                    h.style.height = `${gridHeight}px`;
                }
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
                    positionHandles();
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
                            layout: frValues,
                            preset: 'custom',
                        });
                        editor.view.dispatch(tr);
                    }
                };

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }

            // Reposition handles on resize
            const resizeObserver = new ResizeObserver(() => {
                positionHandles();
            });
            resizeObserver.observe(contentDOM);

            return {
                dom,
                contentDOM,
                ignoreMutation: (mutation) => {
                    // Only let ProseMirror see childList changes inside contentDOM (column management)
                    if (contentDOM.contains(mutation.target) && mutation.target !== contentDOM) {
                        return false;
                    }
                    if (mutation.target === contentDOM && mutation.type === 'childList') {
                        return false;
                    }
                    // Ignore everything else (handles, badge, style changes on contentDOM/gridWrapper)
                    return true;
                },
                update: (updatedNode) => {
                    if (updatedNode.type.name !== 'mahoColumns') {
                        return false;
                    }

                    node = updatedNode;

                    const updatedGap = GAP_SIZES[updatedNode.attrs.gap] || GAP_SIZES.medium;
                    dom.setAttribute('data-preset', updatedNode.attrs.preset);
                    dom.setAttribute('data-gap', updatedNode.attrs.gap);
                    dom.setAttribute('data-style', updatedNode.attrs.style);
                    contentDOM.style.gridTemplateColumns = updatedNode.attrs.layout;
                    contentDOM.style.gap = updatedGap;

                    requestAnimationFrame(() => positionHandles());

                    return true;
                },
                destroy: () => {
                    resizeObserver.disconnect();
                },
            };
        };
    },

    addCommands() {
        return {
            insertColumns: (presetKey) => ({ editor, state, tr, dispatch }) => {
                const preset = COLUMN_PRESETS[presetKey];
                if (!preset) {
                    console.error(`Unknown column preset: ${presetKey}`);
                    return false;
                }

                // Create column nodes with empty paragraphs
                const columns = [];
                for (let i = 0; i < preset.columns; i++) {
                    const column = state.schema.nodes.mahoColumn.create(
                        {},
                        state.schema.nodes.paragraph.create()
                    );
                    columns.push(column);
                }

                // Create the columns container
                const columnsNode = state.schema.nodes.mahoColumns.create(
                    {
                        preset: presetKey,
                        layout: preset.layout,
                        gap: 'medium',
                    },
                    columns
                );

                // Insert at current position
                if (dispatch) {
                    tr.replaceSelectionWith(columnsNode);
                    dispatch(tr);
                }

                return true;
            },

            setColumnsGap: (gap) => ({ editor, state, tr, dispatch }) => {
                const columnsNode = findParentNodeOfType(state.schema.nodes.mahoColumns)(state.selection);

                if (!columnsNode) {
                    return false;
                }

                if (dispatch) {
                    tr.setNodeMarkup(columnsNode.pos, null, {
                        ...columnsNode.node.attrs,
                        gap,
                    });
                    dispatch(tr);
                }

                return true;
            },

            setColumnsStyle: (style) => ({ editor, state, tr, dispatch }) => {
                const columnsNode = findParentNodeOfType(state.schema.nodes.mahoColumns)(state.selection);

                if (!columnsNode) {
                    return false;
                }

                if (dispatch) {
                    tr.setNodeMarkup(columnsNode.pos, null, {
                        ...columnsNode.node.attrs,
                        style,
                    });
                    dispatch(tr);
                }

                return true;
            },

            deleteColumns: () => ({ editor, state, tr, dispatch }) => {
                const columnsNode = findParentNodeOfType(state.schema.nodes.mahoColumns)(state.selection);

                if (!columnsNode) {
                    return false;
                }

                if (dispatch) {
                    tr.delete(columnsNode.pos, columnsNode.pos + columnsNode.node.nodeSize);
                    dispatch(tr);
                }

                return true;
            },
        };
    },
});

/**
 * Helper function to find parent node of specific type
 */
function findParentNodeOfType(nodeType) {
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
