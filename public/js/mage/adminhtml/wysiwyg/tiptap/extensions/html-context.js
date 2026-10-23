// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

/**
 * Media and controls. The browser draws them, so the author cannot type into them. Each one
 * becomes a MahoHtmlRaw, and the author changes it in the plain HTML editor. The default display
 * of these elements is inline, so only this list keeps them out of MahoHtmlMark.
 */
export const RAW_TAGS = ['audio', 'button', 'canvas', 'map', 'meter', 'picture', 'progress', 'video', 'wbr'];

/** Blocks that a node of the schema reads. MahoHtmlBlock and MahoHtmlText take the other blocks. */
const SCHEMA_BLOCK_TAGS = [
    'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th',
    'blockquote', 'pre', 'hr', 'details', 'summary',
];

const TEXT_BLOCK_SELECTOR = 'p,h1,h2,h3,h4,h5,h6';

const INLINE_DISPLAYS = ['inline', 'ruby', 'ruby-base', 'ruby-text', 'ruby-base-container', 'ruby-text-container'];

const displays = new Map();
let probeRoot = null;

/**
 * The display that the browser stylesheet gives `tag`. The probe sits in a shadow root, so the
 * styles of the admin page do not reach it.
 */
const defaultDisplay = (tag) => {
    if (!displays.has(tag)) {
        if (!probeRoot) {
            const host = document.createElement('div');
            host.style.cssText = 'position: absolute; width: 0; height: 0; overflow: hidden';
            document.body.append(host);
            probeRoot = host.attachShadow({ mode: 'closed' });
        }
        const element = document.createElement(tag);
        probeRoot.append(element);
        displays.set(tag, getComputedStyle(element).display);
        element.remove();
    }
    return displays.get(tag);
};

/** A void element such as <wbr> has no content, and a mark cannot be empty. */
const isVoid = (tag) => document.createElement(tag).outerHTML === `<${tag}>`;

/** The elements of `allowlist` that the editor keeps as MahoHtmlMark. */
export const inlineTags = (allowlist) => allowlist.elements.filter((tag) =>
    !RAW_TAGS.includes(tag) && !isVoid(tag) && INLINE_DISPLAYS.includes(defaultDisplay(tag)));

/** The elements of `allowlist` that the editor keeps as MahoHtmlBlock or MahoHtmlText. */
export const groupingTags = (allowlist) => allowlist.elements.filter((tag) =>
    !SCHEMA_BLOCK_TAGS.includes(tag) && defaultDisplay(tag) === 'block');

/** The elements of `allowlist` that the editor keeps as MahoHtmlRaw. */
export const rawTags = (allowlist) => RAW_TAGS.filter((tag) => allowlist.elements.includes(tag));

/** A <div> that MahoDiv or a structured Maho node reads. */
export const hasOwnDivNode = (element) => element.localName === 'div'
    && (element.id !== '' || element.classList.length > 0 || element.dataset.type?.startsWith('maho-'));

const isBlock = (element) => {
    const display = defaultDisplay(element.localName);
    return display !== 'none' && display !== 'contents' && !display.startsWith('inline') && !INLINE_DISPLAYS.includes(display);
};

/** True when no child of `element` is a block, so a text node can hold its content. */
export const isTextContainer = (element) => ![...element.children].some(isBlock);

const ATOM_SELECTOR = ['svg', ...RAW_TAGS].join(',');

/** True when `parent` holds text that is not inside an <svg> or one of the RAW_TAGS. */
const holdsText = (parent) => {
    const walker = document.createTreeWalker(parent, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const atom = walker.currentNode.parentElement.closest(ATOM_SELECTOR);
        if (walker.currentNode.nodeValue.trim() !== '' && !(atom && parent.contains(atom))) {
            return true;
        }
    }
    return false;
};

/**
 * True when the editor reads `element` as inline content: the closest block around it holds text.
 *
 * A node that has an inline and a block variant asks this, so each rule takes what the other one
 * rejects. Without that, an icon inside a sentence splits the paragraph in two.
 */
export const isInlineContext = (element) => {
    for (let parent = element.parentElement; parent && parent.localName !== 'body'; parent = parent.parentElement) {
        if (parent.matches(TEXT_BLOCK_SELECTOR)) {
            return true;
        }
        if (isBlock(parent)) {
            // A schema node or a MahoDiv puts lone inline content in a new <p>
            const ownsBlocks = SCHEMA_BLOCK_TAGS.includes(parent.localName) || hasOwnDivNode(parent);
            return isTextContainer(parent) && (!ownsBlocks || holdsText(parent));
        }
    }
    return false;
};
