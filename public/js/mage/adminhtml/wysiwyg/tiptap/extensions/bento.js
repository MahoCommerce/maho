/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
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
 * Parse grid-template-areas into a 2D array of area names
 * e.g. "'hero hero' 'a b'" → [['hero','hero'], ['a','b']]
 */
function parseAreas(areasStr) {
    const rows = areasStr.match(/'([^']+)'/g) || [];
    return rows.map(row => row.replace(/'/g, '').trim().split(/\s+/));
}

/**
 * Find the first and last row indices where a real column split exists
 * at the boundary between colIndex and colIndex+1
 */
function getBoundaryRowRange(areaGrid, colIndex) {
    let firstRow = -1, lastRow = -1;
    for (let r = 0; r < areaGrid.length; r++) {
        if (areaGrid[r][colIndex] !== areaGrid[r][colIndex + 1]) {
            if (firstRow === -1) firstRow = r;
            lastRow = r;
        }
    }
    return { firstRow, lastRow };
}

/**
 * Bento grid presets configuration
 */
export const BENTO_PRESETS = {
    // 2-Column Presets
    'hero-2': {
        label: 'Hero + 2 Cards',
        areas: `'hero hero' 'a b'`,
        columns: '1fr 1fr',
        rows: '2fr 1fr',
    },
    'feature-left': {
        label: 'Feature Left',
        areas: `'a b' 'a c'`,
        columns: '2fr 1fr',
        rows: '1fr 1fr',
    },
    'feature-right': {
        label: 'Feature Right',
        areas: `'a b' 'c b'`,
        columns: '1fr 2fr',
        rows: '1fr 1fr',
    },

    // 3-Column Presets
    'hero-3': {
        label: 'Hero + 3 Cards',
        areas: `'hero hero hero' 'a b c'`,
        columns: '1fr 1fr 1fr',
        rows: '2fr 1fr',
    },
    'dashboard': {
        label: 'Dashboard',
        areas: `'hero hero side' 'a b c'`,
        columns: '1fr 1fr 1fr',
        rows: '2fr 1fr',
    },
    'magazine': {
        label: 'Magazine',
        areas: `'feat feat side' 'feat feat extra'`,
        columns: '1fr 1fr 1fr',
        rows: '1fr 1fr',
    },
    'showcase': {
        label: 'Showcase',
        areas: `'a a b' 'c d d'`,
        columns: '1fr 1fr 1fr',
        rows: '1fr 1fr',
    },
    'mosaic': {
        label: 'Mosaic',
        areas: `'a b b' 'a c d'`,
        columns: '1fr 1fr 1fr',
        rows: '1fr 1fr',
    },

    // 4-Column Presets
    'hero-4': {
        label: 'Hero + 4 Cards',
        areas: `'hero hero hero hero' 'a b c d'`,
        columns: '1fr 1fr 1fr 1fr',
        rows: '2fr 1fr',
    },
    'gallery': {
        label: 'Gallery',
        areas: `'a a b c' 'd e e c'`,
        columns: '1fr 1fr 1fr 1fr',
        rows: '1fr 1fr',
    },
    'editorial': {
        label: 'Editorial',
        areas: `'a b b c' 'a d d c'`,
        columns: '1fr 1fr 1fr 1fr',
        rows: '1fr 1fr',
    },
    'banner-cards': {
        label: 'Banner + Cards',
        areas: `'hero hero hero hero' 'a a b b' 'c d d e'`,
        columns: '1fr 1fr 1fr 1fr',
        rows: '2fr 1fr 1fr',
    },
};

// Compute cells (unique area names) for each preset
for (const preset of Object.values(BENTO_PRESETS)) {
    const names = preset.areas.match(/[a-z][a-z0-9-]*/gi) || [];
    preset.cells = [...new Set(names)];
}

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
 * MahoBentoCell Node
 *
 * Individual cell container within a bento grid layout
 */
export const MahoBentoCell = Node.create({
    name: 'mahoBentoCell',
    group: 'block',
    content: 'block+',
    isolating: true,
    defining: true,

    addAttributes() {
        return {
            area: {
                default: null,
                parseHTML: element => {
                    const style = element.getAttribute('style') || '';
                    const match = style.match(/grid-area:\s*([^;]+)/);
                    return match ? match[1].trim() : null;
                },
                renderHTML: attributes => {
                    if (!attributes.area) {
                        return {};
                    }
                    return {
                        style: `grid-area: ${attributes.area}`,
                        'data-area': attributes.area,
                    };
                },
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="maho-bento-cell"]',
        }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'maho-bento-cell' }), 0];
    },
});

/**
 * MahoBentoGrid Node
 *
 * Container for bento grid layout using CSS Grid template areas
 */
export const MahoBentoGrid = Node.create({
    name: 'mahoBentoGrid',
    group: 'block',
    content: 'mahoBentoCell+',
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
                default: 'hero-2',
                parseHTML: element => element.getAttribute('data-preset') || 'hero-2',
                renderHTML: attributes => ({ 'data-preset': attributes.preset }),
            },
            areas: {
                default: BENTO_PRESETS['hero-2'].areas,
                parseHTML: element => {
                    // Prefer data attribute (survives HTML processing), fall back to style
                    if (element.getAttribute('data-areas')) {
                        return element.getAttribute('data-areas');
                    }
                    const style = element.getAttribute('style') || '';
                    const match = style.match(/grid-template-areas:\s*((?:'[^']*'\s*)+)/);
                    return match ? match[1].trim() : BENTO_PRESETS['hero-2'].areas;
                },
                renderHTML: attributes => ({ 'data-areas': attributes.areas }),
            },
            columns: {
                default: BENTO_PRESETS['hero-2'].columns,
                parseHTML: element => {
                    if (element.getAttribute('data-columns')) {
                        return element.getAttribute('data-columns');
                    }
                    const style = element.getAttribute('style') || '';
                    const match = style.match(/grid-template-columns:\s*([^;]+)/);
                    return match ? match[1].trim() : BENTO_PRESETS['hero-2'].columns;
                },
                renderHTML: attributes => ({ 'data-columns': attributes.columns }),
            },
            rows: {
                default: BENTO_PRESETS['hero-2'].rows,
                parseHTML: element => {
                    if (element.getAttribute('data-rows')) {
                        return element.getAttribute('data-rows');
                    }
                    const style = element.getAttribute('style') || '';
                    const match = style.match(/grid-template-rows:\s*([^;]+)/);
                    return match ? match[1].trim() : BENTO_PRESETS['hero-2'].rows;
                },
                renderHTML: attributes => ({ 'data-rows': attributes.rows }),
            },
            gap: {
                default: 'medium',
                parseHTML: element => element.getAttribute('data-gap') || 'medium',
                renderHTML: attributes => ({ 'data-gap': attributes.gap }),
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="maho-bento"]',
        }];
    },

    renderHTML({ HTMLAttributes, node }) {
        const gap = GAP_SIZES[node.attrs.gap] || GAP_SIZES.medium;
        const style = `display: grid; grid-template-areas: ${node.attrs.areas}; grid-template-columns: ${node.attrs.columns}; grid-template-rows: ${node.attrs.rows}; gap: ${gap}`;

        return ['div', mergeAttributes(HTMLAttributes, {
            'data-type': 'maho-bento',
            'data-preset': node.attrs.preset,
            'data-gap': node.attrs.gap,
            'style': style,
        }), 0];
    },

    addNodeView() {
        return ({ node: initialNode, editor, getPos }) => {
            let node = initialNode;
            const gap = GAP_SIZES[node.attrs.gap] || GAP_SIZES.medium;

            // Wrapper for badge positioning
            const dom = document.createElement('div');
            dom.setAttribute('data-type', 'maho-bento');
            dom.setAttribute('data-preset', node.attrs.preset);
            dom.setAttribute('data-gap', node.attrs.gap);
            dom.style.position = 'relative';
            dom.style.width = '100%';

            // Badge button
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'bento-badge';
            badge.innerHTML = `Bento <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>`;

            badge.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const bubbleMenu = editor.storage.mahoBentoGrid?.bubbleMenu;
                if (!bubbleMenu) return;

                // Update gap button active states
                const currentGap = node.attrs.gap || 'medium';
                for (const btn of bubbleMenu.querySelectorAll('[data-gap]')) {
                    btn.classList.toggle('is-active', btn.dataset.gap === currentGap);
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

            // Grid container for cells (contentDOM)
            const contentDOM = document.createElement('div');
            contentDOM.className = 'bento-grid';
            contentDOM.style.cssText = `
                display: grid;
                grid-template-areas: ${node.attrs.areas};
                grid-template-columns: ${node.attrs.columns};
                grid-template-rows: ${node.attrs.rows};
                gap: ${gap};
            `;
            gridWrapper.appendChild(contentDOM);

            // Column resize handles — create all upfront to avoid DOM mutations later
            const MAX_HANDLES = 3; // max 4 columns = 3 boundaries
            const handles = [];
            const MIN_COL_WIDTH = 40;

            for (let i = 0; i < MAX_HANDLES; i++) {
                const handle = document.createElement('div');
                handle.className = 'bento-col-handle';
                handle.dataset.handleIndex = i;
                handle.style.display = 'none';
                const line = document.createElement('div');
                line.className = 'bento-col-handle-line';
                handle.appendChild(line);
                gridWrapper.appendChild(handle);
                handles.push(handle);

                handle.addEventListener('mousedown', onMouseDown);
            }

            function getColumnCount() {
                return node.attrs.columns.trim().split(/\s+/).length;
            }

            function getResolvedColumnWidths() {
                const computed = getComputedStyle(contentDOM).gridTemplateColumns;
                return computed.split(/\s+/).map(parseFloat);
            }

            function positionHandles() {
                const colCount = getColumnCount();
                const activeCount = colCount - 1;

                // Hide all handles first
                for (let i = 0; i < handles.length; i++) {
                    handles[i].style.display = 'none';
                }

                if (activeCount <= 0) return;

                const widths = getResolvedColumnWidths();
                if (widths.length <= 1 || isNaN(widths[0])) return;

                const styles = getComputedStyle(contentDOM);
                const colGap = parseFloat(styles.columnGap) || 0;
                const rowGap = parseFloat(styles.rowGap) || 0;
                const rowHeights = styles.gridTemplateRows.split(/\s+/).map(parseFloat);
                const areaGrid = parseAreas(node.attrs.areas);

                let cumulative = 0;
                for (let i = 0; i < activeCount; i++) {
                    cumulative += widths[i];
                    const { firstRow, lastRow } = getBoundaryRowRange(areaGrid, i);

                    // No split at this boundary (area spans both columns in all rows)
                    if (firstRow === -1) continue;

                    // Compute top: sum of row heights + row gaps before firstRow
                    let top = 0;
                    for (let r = 0; r < firstRow; r++) {
                        top += rowHeights[r] + rowGap;
                    }

                    // Compute height: row heights + gaps from firstRow to lastRow
                    let height = 0;
                    for (let r = firstRow; r <= lastRow; r++) {
                        height += rowHeights[r];
                        if (r < lastRow) height += rowGap;
                    }

                    const left = cumulative + (colGap * (i + 1)) - (colGap / 2);
                    const h = handles[i];
                    h.style.display = 'block';
                    h.style.left = `${left}px`;
                    h.style.top = `${top}px`;
                    h.style.height = `${height}px`;
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
                            columns: frValues,
                            preset: 'custom',
                        });
                        editor.view.dispatch(tr);
                    }
                };

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }

            // Only reposition handles on resize — no DOM mutations
            const resizeObserver = new ResizeObserver(() => {
                positionHandles();
            });
            resizeObserver.observe(contentDOM);

            return {
                dom,
                contentDOM,
                ignoreMutation: (mutation) => {
                    // Only let ProseMirror see childList changes inside contentDOM (cell management)
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
                    if (updatedNode.type.name !== 'mahoBentoGrid') {
                        return false;
                    }

                    node = updatedNode;

                    const updatedGap = GAP_SIZES[updatedNode.attrs.gap] || GAP_SIZES.medium;
                    dom.setAttribute('data-preset', updatedNode.attrs.preset);
                    dom.setAttribute('data-gap', updatedNode.attrs.gap);
                    contentDOM.style.gridTemplateAreas = updatedNode.attrs.areas;
                    contentDOM.style.gridTemplateColumns = updatedNode.attrs.columns;
                    contentDOM.style.gridTemplateRows = updatedNode.attrs.rows;
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
            insertBentoGrid: (presetKey) => ({ editor, state, tr, dispatch }) => {
                const preset = BENTO_PRESETS[presetKey];
                if (!preset) {
                    console.error(`Unknown bento preset: ${presetKey}`);
                    return false;
                }

                // Create cell nodes with empty paragraphs
                const cells = [];
                for (const areaName of preset.cells) {
                    const cell = state.schema.nodes.mahoBentoCell.create(
                        { area: areaName },
                        state.schema.nodes.paragraph.create()
                    );
                    cells.push(cell);
                }

                // Create the bento grid container
                const bentoNode = state.schema.nodes.mahoBentoGrid.create(
                    {
                        preset: presetKey,
                        areas: preset.areas,
                        columns: preset.columns,
                        rows: preset.rows,
                        gap: 'medium',
                    },
                    cells
                );

                // Insert at current position
                if (dispatch) {
                    tr.replaceSelectionWith(bentoNode);
                    dispatch(tr);
                }

                return true;
            },

            setBentoGap: (gap) => ({ editor, state, tr, dispatch }) => {
                const bentoNode = findParentNodeOfType(state.schema.nodes.mahoBentoGrid)(state.selection);

                if (!bentoNode) {
                    return false;
                }

                if (dispatch) {
                    tr.setNodeMarkup(bentoNode.pos, null, {
                        ...bentoNode.node.attrs,
                        gap,
                    });
                    dispatch(tr);
                }

                return true;
            },

            deleteBentoGrid: () => ({ editor, state, tr, dispatch }) => {
                const bentoNode = findParentNodeOfType(state.schema.nodes.mahoBentoGrid)(state.selection);

                if (!bentoNode) {
                    return false;
                }

                if (dispatch) {
                    tr.delete(bentoNode.pos, bentoNode.pos + bentoNode.node.nodeSize);
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
