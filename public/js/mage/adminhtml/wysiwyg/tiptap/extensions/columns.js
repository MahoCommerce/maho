/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Node, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.16';

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
        return ({ node, editor, getPos }) => {
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

            // Grid container for columns (contentDOM)
            const contentDOM = document.createElement('div');
            contentDOM.className = 'columns-grid';
            contentDOM.style.cssText = `
                display: grid;
                grid-template-columns: ${node.attrs.layout};
                gap: ${gap};
            `;
            dom.appendChild(contentDOM);

            return {
                dom,
                contentDOM,
                update: (updatedNode) => {
                    if (updatedNode.type.name !== 'mahoColumns') {
                        return false;
                    }

                    const updatedGap = GAP_SIZES[updatedNode.attrs.gap] || GAP_SIZES.medium;
                    dom.setAttribute('data-preset', updatedNode.attrs.preset);
                    dom.setAttribute('data-gap', updatedNode.attrs.gap);
                    dom.setAttribute('data-style', updatedNode.attrs.style);
                    contentDOM.style.gridTemplateColumns = updatedNode.attrs.layout;
                    contentDOM.style.gap = updatedGap;

                    return true;
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
