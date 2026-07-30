// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { Node, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.29.2';
import { Details, DetailsSummary, DetailsContent } from 'https://esm.sh/@tiptap/extension-details@3.29.2';
import { Plugin, PluginKey } from 'https://esm.sh/@tiptap/pm@3.29.2/state';
import { createBadge, setBadgeLabel, findParentNodeOfType } from './grid-utils.js';

export { DetailsSummary, DetailsContent };

/**
 * Accordion styles and the wording used for new items in each
 */
export const ACCORDION_STYLES = {
    'accordion': { label: 'Accordion', itemLabel: 'Section' },
    'tabs': { label: 'Tabs', itemLabel: 'Tab' },
};

const groupName = () => `maho-accordion-${Math.random().toString(36).slice(2, 10)}`;

const createItem = (title) => ({
    type: 'details',
    content: [
        { type: 'detailsSummary', content: [{ type: 'text', text: title }] },
        { type: 'detailsContent', content: [{ type: 'paragraph' }] },
    ],
});

/**
 * Details node with a `name` attribute, which is how the browser keeps a group of
 * items exclusive: opening one closes its siblings, as tabs require
 */
export const MahoDetails = Details.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            name: {
                default: null,
                parseHTML: (element) => element.getAttribute('name'),
                renderHTML: (attributes) => attributes.name ? { name: attributes.name } : {},
            },
        };
    },
});

/**
 * MahoAccordion Node
 *
 * Container for a group of <details> items, rendered on the storefront as an
 * accordion or as tabs depending on its style
 */
export const MahoAccordion = Node.create({
    name: 'mahoAccordion',
    group: 'block',
    content: 'details+',
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
            activePos: null,
        };
    },

    addAttributes() {
        return {
            // Not named `style`: that collides with the global style attribute preserved
            // on every node and leaves an empty style="" on the saved markup
            accordionStyle: {
                default: 'accordion',
                parseHTML: (element) => element.getAttribute('data-style') || 'accordion',
                renderHTML: (attributes) => ({ 'data-style': attributes.accordionStyle }),
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="maho-accordion"]',
        }];
    },

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: new PluginKey('mahoAccordionGroupName'),

                // Tabs are exclusive through a name shared by every item in the group, and
                // accordion items are independent, so the name is a property of the group
                // rather than something an item can be left holding after a paste or a split
                appendTransaction: (transactions, oldState, newState) => {
                    if (!transactions.some((transaction) => transaction.docChanged)) {
                        return null;
                    }

                    const { tr } = newState;
                    let changed = false;

                    newState.doc.descendants((node, pos) => {
                        if (node.type !== newState.schema.nodes[this.name]) {
                            return;
                        }

                        let name = null;
                        if (node.attrs.accordionStyle === 'tabs') {
                            node.forEach((child) => name ??= child.attrs.name);
                            name ??= groupName();
                        }

                        node.forEach((child, offset) => {
                            if (child.attrs.name !== name) {
                                tr.setNodeMarkup(pos + 1 + offset, null, { ...child.attrs, name });
                                changed = true;
                            }
                        });
                    });

                    return changed ? tr : null;
                },
            }),
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'maho-accordion' }), 0];
    },

    addNodeView() {
        return ({ node: initialNode, editor, getPos }) => {
            let node = initialNode;

            const dom = document.createElement('div');
            dom.setAttribute('data-type', 'maho-accordion');
            dom.setAttribute('data-style', node.attrs.accordionStyle);
            dom.style.position = 'relative';

            const badge = createBadge(ACCORDION_STYLES[node.attrs.accordionStyle].label, editor, this.name, (bubbleMenu) => {
                // The badge is clicked without moving the cursor, so remember which
                // accordion the menu was opened for
                editor.storage[this.name].activePos = getPos();

                for (const button of bubbleMenu.querySelectorAll('[data-accordion-style]')) {
                    button.classList.toggle('is-active', button.dataset.accordionStyle === node.attrs.accordionStyle);
                }
            });
            dom.append(badge);

            const contentDOM = document.createElement('div');
            contentDOM.className = 'accordion-inner';
            dom.append(contentDOM);

            return {
                dom,
                contentDOM,
                update: (updatedNode) => {
                    if (updatedNode.type.name !== this.name) {
                        return false;
                    }
                    node = updatedNode;
                    dom.setAttribute('data-style', node.attrs.accordionStyle);
                    setBadgeLabel(badge, ACCORDION_STYLES[node.attrs.accordionStyle].label);
                    return true;
                },
            };
        };
    },

    addCommands() {
        const translate = (editor, text) => editor.options.wysiwygSetup?.translate(text) ?? text;

        const findAccordion = (editor, state) => {
            const found = findParentNodeOfType(state.schema.nodes[this.name])(state.selection);
            if (found) {
                return found;
            }
            // Fall back to the accordion whose badge opened the bubble menu
            const pos = editor.storage[this.name].activePos;
            const node = typeof pos === 'number' ? state.doc.nodeAt(pos) : null;
            return node?.type === state.schema.nodes[this.name] ? { node, pos } : null;
        };

        return {
            insertAccordion: (style = 'accordion') => ({ editor, commands }) => {
                const { itemLabel } = ACCORDION_STYLES[style] ?? ACCORDION_STYLES.accordion;

                return commands.insertContent({
                    type: this.name,
                    attrs: { accordionStyle: style },
                    content: [1, 2].map((i) => createItem(`${translate(editor, itemLabel)} ${i}`)),
                });
            },

            setAccordionStyle: (style) => ({ editor, state, tr, dispatch }) => {
                const accordion = findAccordion(editor, state);
                if (!accordion || !ACCORDION_STYLES[style]) {
                    return false;
                }

                if (dispatch) {
                    tr.setNodeMarkup(accordion.pos, null, { ...accordion.node.attrs, accordionStyle: style });
                    dispatch(tr);
                }

                return true;
            },

            addAccordionItem: () => ({ editor, state, chain }) => {
                const accordion = findAccordion(editor, state);
                if (!accordion) {
                    return false;
                }

                const { itemLabel } = ACCORDION_STYLES[accordion.node.attrs.accordionStyle] ?? ACCORDION_STYLES.accordion;
                const title = `${translate(editor, itemLabel)} ${accordion.node.childCount + 1}`;

                return chain()
                    .insertContentAt(accordion.pos + accordion.node.nodeSize - 1, createItem(title))
                    .run();
            },

            deleteAccordionItem: () => ({ editor, state, tr, dispatch }) => {
                const accordion = findAccordion(editor, state);
                if (!accordion) {
                    return false;
                }

                // Without a cursor in a specific item (the menu was opened from the
                // badge) the last one is the only unambiguous target
                let item = findParentNodeOfType(state.schema.nodes.details)(state.selection);
                if (!item || item.pos < accordion.pos || item.pos > accordion.pos + accordion.node.nodeSize) {
                    const lastChild = accordion.node.lastChild;
                    item = {
                        node: lastChild,
                        pos: accordion.pos + accordion.node.nodeSize - 1 - lastChild.nodeSize,
                    };
                }

                if (dispatch) {
                    // The last item leaves an empty accordion behind, so drop the group with it
                    const target = accordion.node.childCount <= 1 ? accordion : item;
                    tr.delete(target.pos, target.pos + target.node.nodeSize);
                    dispatch(tr);
                }

                return true;
            },

            deleteAccordion: () => ({ editor, state, tr, dispatch }) => {
                const accordion = findAccordion(editor, state);
                if (!accordion) {
                    return false;
                }

                if (dispatch) {
                    tr.delete(accordion.pos, accordion.pos + accordion.node.nodeSize);
                    dispatch(tr);
                }

                return true;
            },
        };
    },
});
