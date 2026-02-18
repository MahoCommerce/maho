/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Node, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.20.0';
import { GAP_SIZES, findParentNodeOfType, createGridNodeView } from './grid-utils.js';

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
        return createGridNodeView({
            nodeName: 'mahoBentoGrid',
            storageName: 'mahoBentoGrid',
            dataType: 'maho-bento',
            badgeLabel: 'Bento',
            layoutAttr: 'columns',

            setDataAttrs(dom, node) {
                dom.setAttribute('data-preset', node.attrs.preset);
                dom.setAttribute('data-gap', node.attrs.gap);
            },

            updateGridStyles(contentDOM, node, gap) {
                contentDOM.style.gridTemplateAreas = node.attrs.areas;
                contentDOM.style.gridTemplateColumns = node.attrs.columns;
                contentDOM.style.gridTemplateRows = node.attrs.rows;
                contentDOM.style.gap = gap;
            },

            positionHandles(handles, contentDOM, node, widths, activeCount) {
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
            },
        });
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
