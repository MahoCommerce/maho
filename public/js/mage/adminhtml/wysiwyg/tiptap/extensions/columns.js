/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Node, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.20.0';
import { findParentNodeOfType, createGridNodeView } from './grid-utils.js';

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
        return createGridNodeView({
            nodeName: 'mahoColumns',
            storageName: 'mahoColumns',
            dataType: 'maho-columns',
            badgeLabel: 'Columns',
            layoutAttr: 'layout',

            setDataAttrs(dom, node) {
                dom.setAttribute('data-preset', node.attrs.preset);
                dom.setAttribute('data-gap', node.attrs.gap);
                dom.setAttribute('data-style', node.attrs.style);
            },

            updateGridStyles(contentDOM, node, gap) {
                contentDOM.style.gridTemplateColumns = node.attrs.layout;
                contentDOM.style.gap = gap;
            },

            positionHandles(handles, contentDOM, node, widths, activeCount) {
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
            },

            onBadgeClick(node, bubbleMenu) {
                const currentStyle = node.attrs.style || 'none';
                for (const btn of bubbleMenu.querySelectorAll('[data-col-style]')) {
                    btn.classList.toggle('is-active', btn.dataset.colStyle === currentStyle);
                }
            },
        });
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
