// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { Node } from 'https://esm.sh/@tiptap/core@3.31.3';

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

/**
 * Copy an element into a plain tree. Keep only the names that `allowlist` contains.
 *
 * PHP sends the allowlist, from \Maho\Security\SvgAllowlist. The editor therefore keeps the same
 * markup that a save keeps. If this function removed more, the check in setup.js would warn the
 * author about a loss that the save does not cause.
 */
const parseSvgTree = (element, allowlist) => {
    const tag = element.localName.toLowerCase();
    const allowed = allowlist[tag];
    if (!allowed) {
        return null;
    }

    const attrs = {};
    for (const { name, value } of element.attributes) {
        // Test the full name, not the prefix. A document can bind `xl:` to the xlink namespace,
        // so a test for `xlink:href` alone misses it.
        if (!name.includes(':') && allowed.includes(name.toLowerCase())) {
            attrs[name.toLowerCase()] = value;
        }
    }

    const children = [];
    for (const child of element.childNodes) {
        if (child instanceof Text) {
            children.push(child.nodeValue);
        } else if (child instanceof Element) {
            const parsed = parseSvgTree(child, allowlist);
            if (parsed) {
                children.push(parsed);
            }
        }
    }

    return { tag, attrs, children };
};

/** Convert a tree into a ProseMirror DOMOutputSpec. */
const renderSvgTree = (tree) =>
    [tree.tag, tree.attrs, ...tree.children.map((child) =>
        typeof child === 'string' ? child : renderSvgTree(child),
    )];

/** Convert a tree into real DOM elements for the node view. */
const buildSvgElement = (tree) => {
    const element = document.createElementNS(SVG_NAMESPACE, tree.tag);
    for (const [name, value] of Object.entries(tree.attrs)) {
        element.setAttribute(name, value);
    }
    for (const child of tree.children) {
        element.append(typeof child === 'string' ? child : buildSvgElement(child));
    }
    return element;
};

/**
 * Keep an inline <svg> through an edit.
 *
 * This node has no toolbar button. An SVG is source markup, not a text style. The author adds one
 * with a paste or through the source view. Both paths use the parse rules below.
 */
export const MahoSvgBlock = Node.create({
    name: 'mahoSvgBlock',
    group: 'block',
    inline: false,
    draggable: true,
    atom: true,

    addOptions() {
        return {
            allowlist: {},
        };
    },

    addAttributes() {
        return {
            tree: {
                default: null,
                parseHTML: (element) => parseSvgTree(element, this.options.allowlist),
                rendered: false,
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'svg',
            // An <svg> inside another <svg> is part of the tree of the outer node. It does not
            // become a second node.
            getAttrs: (element) => element.parentElement?.closest('svg') ? false : null,
        }];
    },

    renderHTML({ node }) {
        return node.attrs.tree ? renderSvgTree(node.attrs.tree) : ['svg', {}];
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement(this.name === 'mahoSvgBlock' ? 'div' : 'span');
            dom.dataset.svg = '';
            dom.contentEditable = 'false';
            if (node.attrs.tree) {
                dom.append(buildSvgElement(node.attrs.tree));
            }
            return { dom };
        };
    },
});

export const MahoSvgInline = MahoSvgBlock.extend({
    name: 'mahoSvgInline',
    group: 'inline',
    inline: true,
});
