// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { Extension, Mark, Node } from 'https://esm.sh/@tiptap/core@3.31.3';
import { Plugin } from 'https://esm.sh/@tiptap/pm@3.31.3/state';
import { groupingTags, hasOwnDivNode, inlineTags, isInlineContext, isTextContainer, rawTags } from './html-context.js';
import { buildSvgElement, parseSvgTree, renderSvgTree } from './svg.js';

const EMPTY_ALLOWLIST = { elements: [], attributes: [] };

/**
 * Below the default priority, so a node or a mark of the schema that reads the same tag goes
 * first. The generic rules take only what the schema rejects.
 */
const GENERIC_PRIORITY = 40;

/**
 * The HTML attributes that a node or a mark of the schema reads itself. HtmlAttributes keeps
 * every other attribute that the allowlist permits. A second copy of an attribute that the node
 * also reads would put the old value back after an edit.
 */
const CLASS_AND_STYLE = ['class', 'style'];
const CELL_ATTRIBUTES = [...CLASS_AND_STYLE, 'colspan', 'rowspan', 'colwidth', 'align'];
const OWNED_ATTRIBUTES = {
    paragraph: CLASS_AND_STYLE,
    heading: CLASS_AND_STYLE,
    blockquote: CLASS_AND_STYLE,
    bulletList: CLASS_AND_STYLE,
    orderedList: [...CLASS_AND_STYLE, 'start', 'type'],
    listItem: CLASS_AND_STYLE,
    codeBlock: CLASS_AND_STYLE,
    horizontalRule: [],
    hardBreak: [],
    table: CLASS_AND_STYLE,
    tableRow: CLASS_AND_STYLE,
    tableCell: CELL_ATTRIBUTES,
    tableHeader: CELL_ATTRIBUTES,
    mahoImage: [...CLASS_AND_STYLE, 'src', 'alt', 'title', 'width', 'height'],
    mahoDiv: ['id', 'class'],
    mahoSpan: CLASS_AND_STYLE,
    link: ['href', 'target', 'rel', 'class', 'title'],
    bold: [],
    italic: [],
    underline: [],
    strike: [],
    code: [],
};

/**
 * Attributes that change how the editor works, not how the content looks. The node views leave
 * them out of the editor, and the saved HTML keeps them.
 */
const EDITOR_ONLY_SKIP = ['inert', 'draggable', 'contenteditable'];

/** Copy the attributes of `element` that `allowlist` keeps and `skip` does not name, or null. */
const readAttributes = (element, allowlist, skip = []) => {
    const attributes = {};
    for (const { name, value } of element.attributes) {
        if (!skip.includes(name) && (name.startsWith('data-') || allowlist.attributes.includes(name))) {
            attributes[name] = value;
        }
    }
    return Object.keys(attributes).length > 0 ? attributes : null;
};

const applyAttributes = (dom, attributes) => {
    for (const [name, value] of Object.entries(attributes ?? {})) {
        if (!EDITOR_ONLY_SKIP.includes(name)) {
            dom.setAttribute(name, value);
        }
    }
};

/** The label of a node view, such as `section#intro.hero`. */
const describe = (tag, attributes) => {
    let label = tag;
    if (attributes?.id) {
        label += `#${attributes.id}`;
    }
    for (const className of (attributes?.class ?? '').split(/\s+/).filter(Boolean)) {
        label += `.${className}`;
    }
    return label;
};

const tagAndAttributes = (extension, defaultTag) => ({
    tag: {
        default: defaultTag,
        parseHTML: (element) => element.localName,
        rendered: false,
    },
    attributes: {
        default: null,
        parseHTML: (element) => readAttributes(element, extension.options.allowlist),
        rendered: false,
    },
});

/**
 * Keep the attributes that the schema does not read, such as `id`, `title`, `lang`, `data-*` and
 * `aria-*`, on the standard nodes and marks.
 */
export const HtmlAttributes = Extension.create({
    name: 'htmlAttributes',

    addOptions() {
        return {
            allowlist: EMPTY_ALLOWLIST,
        };
    },

    addGlobalAttributes() {
        return Object.entries(OWNED_ATTRIBUTES).map(([type, owned]) => ({
            types: [type],
            attributes: {
                htmlAttributes: {
                    default: null,
                    parseHTML: (element) => readAttributes(element, this.options.allowlist, owned),
                    renderHTML: (attributes) => attributes.htmlAttributes ?? {},
                },
            },
        }));
    },
});

/**
 * Keep a phrasing element that has no mark of its own, such as <label>, <small> or <abbr>. The
 * text inside stays editable.
 */
export const MahoHtmlMark = Mark.create({
    name: 'mahoHtmlMark',

    // A mark excludes its own type by default, which would collapse <small><sup> into one element
    excludes: '',

    addOptions() {
        return {
            allowlist: EMPTY_ALLOWLIST,
        };
    },

    addAttributes() {
        return tagAndAttributes(this, 'span');
    },

    parseHTML() {
        const tags = inlineTags(this.options.allowlist);
        return tags.length === 0 ? [] : [{
            tag: tags.join(','),
            priority: GENERIC_PRIORITY,
            // A link with an href is a Link mark. The schema refuses some hrefs, such as javascript:,
            // and this rule must not keep them.
            getAttrs: (element) => element.localName === 'a' && element.hasAttribute('href') ? false : null,
        }];
    },

    renderHTML({ mark }) {
        return [mark.attrs.tag, mark.attrs.attributes ?? {}, 0];
    },

    addProseMirrorPlugins() {
        return [new Plugin({
            props: {
                handleDOMEvents: {
                    // A click on a <label> focuses the field that its `for` names, which can be a
                    // field of the admin form around the editor.
                    click: (view, event) => {
                        if (event.target instanceof Element && event.target.closest('label')) {
                            event.preventDefault();
                        }
                        return false;
                    },
                },
            },
        })];
    },
});

const groupingRule = (extension, text) => {
    const tags = groupingTags(extension.options.allowlist);
    return tags.length === 0 ? [] : [{
        tag: tags.join(','),
        priority: GENERIC_PRIORITY,
        getAttrs: (element) => !hasOwnDivNode(element) && isTextContainer(element) === text ? null : false,
    }];
};

/**
 * Keep a grouping element that holds blocks, such as <section>, <figure> or <dl>. A grouping
 * element that holds only inline content is a MahoHtmlText.
 */
export const MahoHtmlBlock = Node.create({
    name: 'mahoHtmlBlock',
    group: 'block',
    content: 'block+',
    defining: true,

    addOptions() {
        return {
            allowlist: EMPTY_ALLOWLIST,
        };
    },

    addAttributes() {
        return tagAndAttributes(this, 'div');
    },

    parseHTML() {
        return groupingRule(this, false);
    },

    renderHTML({ node }) {
        return [node.attrs.tag, node.attrs.attributes ?? {}, 0];
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement(node.attrs.tag);
            applyAttributes(dom, node.attrs.attributes);
            dom.dataset.divInfo = describe(node.attrs.tag, node.attrs.attributes);
            return { dom, contentDOM: dom };
        };
    },
});

/** Keep a grouping element that holds only inline content, such as <figcaption> or <dt>. */
export const MahoHtmlText = MahoHtmlBlock.extend({
    name: 'mahoHtmlText',
    content: 'inline*',

    parseHTML() {
        return groupingRule(this, true);
    },
});

/**
 * Copy an element into a plain tree. Keep only the elements and attributes of the allowlists. An
 * <svg> inside it, such as the icon of a button, reads the SVG allowlist.
 */
const parseRawTree = (element, allowlist, svgAllowlist) => {
    if (element.localName === 'svg') {
        const tree = parseSvgTree(element, svgAllowlist);
        return tree ? { ...tree, svg: true } : null;
    }
    if (!allowlist.elements.includes(element.localName)) {
        return null;
    }

    const children = [];
    for (const child of element.childNodes) {
        if (child instanceof Text) {
            children.push(child.nodeValue);
        } else if (child instanceof Element) {
            const parsed = parseRawTree(child, allowlist, svgAllowlist);
            if (parsed) {
                children.push(parsed);
            }
        }
    }

    return { tag: element.localName, attrs: readAttributes(element, allowlist) ?? {}, children };
};

const renderRawTree = (tree) => tree.svg ? renderSvgTree(tree) : [
    tree.tag, tree.attrs,
    ...tree.children.map((child) => typeof child === 'string' ? child : renderRawTree(child)),
];

const buildRawElement = (tree) => {
    if (tree.svg) {
        return buildSvgElement(tree);
    }
    const element = document.createElement(tree.tag);
    for (const [name, value] of Object.entries(tree.attrs)) {
        element.setAttribute(name, value);
    }
    for (const child of tree.children) {
        element.append(typeof child === 'string' ? child : buildRawElement(child));
    }
    return element;
};

const rawRule = (extension, inline) => {
    const tags = rawTags(extension.options.allowlist);
    return tags.length === 0 ? [] : [{
        tag: tags.join(','),
        priority: GENERIC_PRIORITY,
        getAttrs: (element) => isInlineContext(element) === inline ? null : false,
    }];
};

/**
 * Keep a media element or a control, such as <video> or <button>, as it is. The editor shows it
 * but the author cannot type into it.
 */
export const MahoHtmlRaw = Node.create({
    name: 'mahoHtmlRaw',
    group: 'block',
    inline: false,
    atom: true,
    draggable: true,

    addOptions() {
        return {
            allowlist: EMPTY_ALLOWLIST,
            svgAllowlist: {},
        };
    },

    addAttributes() {
        return {
            tree: {
                default: null,
                parseHTML: (element) => parseRawTree(element, this.options.allowlist, this.options.svgAllowlist),
                rendered: false,
            },
        };
    },

    parseHTML() {
        return rawRule(this, false);
    },

    renderHTML({ node }) {
        return node.attrs.tree ? renderRawTree(node.attrs.tree) : ['span', {}];
    },

    addNodeView() {
        return ({ node, editor }) => {
            const dom = document.createElement(this.name === 'mahoHtmlRaw' ? 'div' : 'span');
            dom.contentEditable = 'false';
            dom.dataset.htmlRaw = node.attrs.tree?.tag ?? '';
            dom.title = editor.options.wysiwygSetup?.translate('Edit this element in the plain HTML editor.') ?? '';
            if (node.attrs.tree) {
                // A <button> inside the admin form would submit it, and a player would take the
                // click that selects the node. An inert preview does neither.
                const preview = document.createElement('span');
                preview.inert = true;
                preview.append(buildRawElement(node.attrs.tree));
                dom.append(preview);
            }
            return { dom };
        };
    },
});

export const MahoHtmlRawInline = MahoHtmlRaw.extend({
    name: 'mahoHtmlRawInline',
    group: 'inline',
    inline: true,

    parseHTML() {
        return rawRule(this, true);
    },
});

/**
 * Keep the HTML that a save keeps, also where the editor has no button for it.
 *
 * PHP sends the allowlist, from Mage_Core_Helper_Purifier::forEditor(). Without it, the editor
 * keeps only the markup of its own nodes, as before.
 */
export const MahoHtml = Extension.create({
    name: 'mahoHtml',

    addOptions() {
        return {
            allowlist: null,
            svgAllowlist: {},
        };
    },

    addExtensions() {
        const allowlist = this.options.allowlist ?? EMPTY_ALLOWLIST;
        return [
            HtmlAttributes.configure({ allowlist }),
            MahoHtmlMark.configure({ allowlist }),
            MahoHtmlBlock.configure({ allowlist }),
            MahoHtmlText.configure({ allowlist }),
            MahoHtmlRaw.configure({ allowlist, svgAllowlist: this.options.svgAllowlist }),
            MahoHtmlRawInline.configure({ allowlist, svgAllowlist: this.options.svgAllowlist }),
        ];
    },
});
