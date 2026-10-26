// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { Extension, createNodeFromContent, getHTMLFromFragment } from 'https://esm.sh/@tiptap/core@3.31.3';
import { Plugin } from 'https://esm.sh/@tiptap/pm@3.31.3/state';
import { Fragment } from 'https://esm.sh/@tiptap/pm@3.31.3/model';

/** The HTML of `node`, as the plain HTML editor shows it. */
const nodeHtml = (setup, node) => setup.convertToPlain(getHTMLFromFragment(Fragment.from(node), node.type.schema)).trim();

/**
 * Open a dialog with the HTML of the node at `pos` in a code editor. Update replaces the node,
 * in one step that Undo reverts. HTML that the rich text editor cannot keep needs a confirmation.
 */
export const openElementSource = async (setup, pos) => {
    // CodeMirror is large, so it loads at the first use
    const { createHtmlView } = await import('./code-editor.js');
    const editor = setup.editor;
    const node = editor.state.doc.nodeAt(pos);
    if (!node) {
        return;
    }
    let view = null;
    Dialog.confirm('<div class="tiptap-element-source"></div>', {
        id: 'tiptap-element-source',
        title: setup.translate('Edit HTML'),
        className: 'maho-dialog tiptap-element-source-dialog',
        width: 900,
        cancel: true,
        okLabel: setup.translate('Update'),
        onOpen: (dialog) => {
            view = createHtmlView(setup, dialog.querySelector('.tiptap-element-source'), nodeHtml(setup, node));
            dialog.codeView = view;
            view.focus();
        },
        onOk: () => {
            const raw = view.state.doc.toString();
            const html = setup.convertFromPlain(raw);
            const kept = getHTMLFromFragment(Fragment.from(createNodeFromContent(html, editor.schema)), editor.schema);
            const dropped = setup.findDroppedContent(raw, kept);
            if (dropped && !confirm(setup.translate('The rich text editor cannot keep this HTML: %s. Continue and remove it?', setup.describeDroppedContent(6, dropped)))) {
                return false;
            }
            // insertContentAt() keeps whitespace by default, so the indentation between tags became paragraphs
            editor.chain().focus().insertContentAt({ from: pos, to: pos + node.nodeSize }, html, { parseOptions: { preserveWhitespace: false } }).run();
            return true;
        },
        onClose: () => view?.destroy(),
    });
};

/** The position of the node that `dom` shows, or null. */
const positionOf = (view, dom) => {
    const inner = view.posAtDOM(dom, 0);
    if (view.nodeDOM(inner) === dom) {
        return inner;
    }
    const $pos = view.state.doc.resolve(inner);
    for (let depth = $pos.depth; depth > 0; depth--) {
        if (view.nodeDOM($pos.before(depth)) === dom) {
            return $pos.before(depth);
        }
    }
    return null;
};

/** True when `event` hits the label of `element`, the ::before box that shows `div.card-body`. */
const isOnLabel = (element, event) => {
    const box = element.getBoundingClientRect();
    const label = getComputedStyle(element, '::before');
    const left = box.left + parseFloat(label.left);
    const top = box.top + parseFloat(label.top);
    const width = parseFloat(label.width) + parseFloat(label.paddingLeft) + parseFloat(label.paddingRight);
    const height = parseFloat(label.height) + parseFloat(label.paddingTop) + parseFloat(label.paddingBottom);
    return event.clientX >= left && event.clientX <= left + width && event.clientY >= top && event.clientY <= top + height;
};

/**
 * A click on the label of a <div> or of another generic element, and a double click on an element
 * that the editor shows but cannot edit, such as a <video>, open the HTML of that element.
 */
export const MahoElementSource = Extension.create({
    name: 'mahoElementSource',

    addProseMirrorPlugins() {
        const editor = this.editor;
        const open = (view, event, dom) => {
            const pos = dom && view.dom.contains(dom) ? positionOf(view, dom) : null;
            if (pos === null || !editor.isEditable) {
                return false;
            }
            event.preventDefault();
            openElementSource(editor.options.wysiwygSetup, pos);
            return true;
        };
        return [new Plugin({
            props: {
                handleDOMEvents: {
                    mousedown: (view, event) => event.target instanceof HTMLElement
                        && event.target.dataset.divInfo !== undefined
                        && isOnLabel(event.target, event)
                        && open(view, event, event.target),
                    dblclick: (view, event) => event.target instanceof Element
                        && open(view, event, event.target.closest('[data-html-raw]')),
                },
            },
        })];
    },
});
