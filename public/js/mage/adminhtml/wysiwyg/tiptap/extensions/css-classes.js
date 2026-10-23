// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { Extension, getMarkRange } from 'https://esm.sh/@tiptap/core@3.31.3';
import { NodeSelection } from 'https://esm.sh/@tiptap/pm@3.31.3/state';
import { findUnknownClasses } from './class-check.js';

/** Read and write the `class` attribute of a node or a mark that keeps it as `class`. */
const CLASS_ATTRIBUTE = {
    get: (attrs) => attrs.class ?? '',
    set: (attrs, value) => ({ ...attrs, class: value || null }),
};

/** Read and write the `class` in the `attributes` of a generic HTML node or mark. */
const HTML_ATTRIBUTES = {
    get: (attrs) => attrs.attributes?.class ?? '',
    set: (attrs, value) => {
        const attributes = { ...attrs.attributes, class: value };
        if (!value) {
            delete attributes.class;
        }
        return { ...attrs, attributes: Object.keys(attributes).length > 0 ? attributes : null };
    },
};

const DIV_CLASS_LIST = {
    get: (attrs) => attrs.classList ?? '',
    set: (attrs, value) => ({ ...attrs, classList: value }),
};

const GENERIC_TYPES = ['mahoHtmlBlock', 'mahoHtmlText', 'mahoHtmlMark'];

/** The accessor of the classes of a node or a mark type, or null when the type keeps no class. */
const accessorOf = (type) => {
    if (type.name === 'mahoDiv') {
        return DIV_CLASS_LIST;
    }
    if (GENERIC_TYPES.includes(type.name)) {
        return HTML_ATTRIBUTES;
    }
    return Object.hasOwn(type.attrs ?? {}, 'class') ? CLASS_ATTRIBUTE : null;
};

const TAGS = {
    paragraph: 'p', blockquote: 'blockquote', bulletList: 'ul', orderedList: 'ol', listItem: 'li',
    codeBlock: 'pre', table: 'table', tableRow: 'tr', tableCell: 'td', tableHeader: 'th',
    mahoImage: 'img', mahoDiv: 'div', link: 'a', mahoSpan: 'span',
    details: 'details', detailsSummary: 'summary',
};

/** The Maho layout nodes are a <div> with a name that the author knows from the toolbar. */
const NAMES = {
    mahoColumns: 'Columns', mahoColumn: 'Column', mahoBentoGrid: 'Bento Grid', mahoBentoCell: 'Bento Cell',
    mahoAccordion: 'Accordion', detailsContent: 'Accordion Content',
};

const labelOf = (setup, type, attrs) => {
    if (NAMES[type.name]) {
        return `div (${setup.translate(NAMES[type.name])})`;
    }
    if (type.name === 'heading') {
        return `h${attrs.level}`;
    }
    return attrs.tag ?? TAGS[type.name] ?? type.name;
};

/** Split `value` into classes, without duplicates, and join them with one space. */
export const normalizeClasses = (value) => [...new Set(String(value).split(/\s+/).filter(Boolean))].join(' ');

/**
 * The elements at the selection that keep a class, the innermost first. A text selection
 * that no span covers can also get a new <span>.
 */
export const findClassTargets = (setup) => {
    const { state } = setup.editor;
    const { selection } = state;
    const { $from, $to, from, to, empty } = selection;
    const targets = [];

    const marks = empty ? $from.marks() : $from.marksAcross($to) ?? [];
    for (const mark of [...marks].reverse()) {
        const accessor = accessorOf(mark.type);
        if (accessor) {
            const range = getMarkRange($from, mark.type, mark.attrs);
            if (range) {
                targets.push({ label: labelOf(setup, mark.type, mark.attrs), value: accessor.get(mark.attrs), mark, range, accessor });
            }
        }
    }
    const spanType = state.schema.marks.mahoSpan;
    if (!empty && spanType && !(selection instanceof NodeSelection) && !targets.some((target) => target.mark?.type === spanType)) {
        targets.unshift({ label: setup.translate('New span around the selected text'), value: '', newSpan: { from, to } });
    }

    if (selection instanceof NodeSelection) {
        const accessor = accessorOf(selection.node.type);
        if (accessor) {
            targets.push({ label: labelOf(setup, selection.node.type, selection.node.attrs), value: accessor.get(selection.node.attrs), pos: from, accessor });
        }
    }
    for (let depth = $from.depth; depth > 0; depth--) {
        const node = $from.node(depth);
        const accessor = accessorOf(node.type);
        if (accessor) {
            targets.push({ label: labelOf(setup, node.type, node.attrs), value: accessor.get(node.attrs), pos: $from.before(depth), accessor });
        }
    }
    return targets;
};

/**
 * Write `value` into each target that changed, in one transaction. A change of a class
 * moves no content, so the positions of the other targets stay valid.
 */
export const applyClassTargets = (editor, targets) => {
    const { tr, doc, schema } = editor.state;
    for (const target of targets) {
        const value = normalizeClasses(target.edited ?? target.value);
        if (value === normalizeClasses(target.value)) {
            continue;
        }
        if (target.newSpan) {
            tr.addMark(target.newSpan.from, target.newSpan.to, schema.marks.mahoSpan.create({ class: value }));
        } else if (target.mark) {
            const attrs = target.accessor.set(target.mark.attrs, value);
            tr.removeMark(target.range.from, target.range.to, target.mark);
            // A <span> that keeps nothing more has no reason to stay
            const emptySpan = target.mark.type.name === 'mahoSpan' && !attrs.class && !attrs.style && !attrs.htmlAttributes;
            if (!emptySpan) {
                tr.addMark(target.range.from, target.range.to, target.mark.type.create(attrs));
            }
        } else {
            const node = doc.nodeAt(target.pos);
            tr.setNodeMarkup(target.pos, null, target.accessor.set(node.attrs, value));
        }
    }
    if (tr.docChanged) {
        editor.view.dispatch(tr);
    }
};

const dialogHtml = (setup, targets) => {
    const t = (text) => escapeHtml(setup.translate(text), true);
    return `<div class="tiptap-button-form tiptap-class-form">
        <label for="tiptap-class-target">${t('Element')}</label>
        <select id="tiptap-class-target">${targets.map((target, index) => `<option value="${index}">${escapeHtml(target.label, true)}</option>`).join('')}</select>
        <label for="tiptap-class-value">${t('CSS Classes')}</label>
        <input type="text" id="tiptap-class-value" spellcheck="false" autocomplete="off" placeholder="mt-4 text-center">
        <p class="tiptap-class-warning" hidden></p>
    </div>`;
};

/** Open the dialog that changes the classes of the elements at the selection. */
export const openClassDialog = (setup) => {
    const editor = setup.editor;
    const targets = findClassTargets(setup);
    if (targets.length === 0) {
        alert(setup.translate('Select text or a block to change its CSS classes.'));
        return;
    }

    Dialog.confirm(dialogHtml(setup, targets), {
        id: 'tiptap-class-dialog',
        title: setup.translate('CSS Classes'),
        className: 'maho-dialog tiptap-button-dialog',
        width: 480,
        cancel: true,
        okLabel: setup.translate('Update'),
        onOpen: (dialog) => {
            const select = dialog.querySelector('#tiptap-class-target');
            const input = dialog.querySelector('#tiptap-class-value');
            const warning = dialog.querySelector('.tiptap-class-warning');
            const current = () => targets[Number(select.value)];
            let check = 0;
            const showWarning = async () => {
                const run = ++check;
                const classes = normalizeClasses(input.value).split(' ').filter(Boolean);
                const unknown = await findUnknownClasses(setup, classes);
                // Only the answer to the last change counts
                if (run !== check) {
                    return;
                }
                warning.hidden = !unknown?.length;
                warning.textContent = unknown?.length
                    ? setup.translate('No style found for: %s. Check the spelling. A daisyUI class that the theme does not use gets its style after the save.', unknown.join(' '))
                    : '';
            };
            let timer = null;
            const load = () => {
                input.value = current().edited ?? current().value;
                showWarning();
            };
            select.addEventListener('change', load);
            input.addEventListener('input', () => {
                current().edited = input.value;
                clearTimeout(timer);
                timer = setTimeout(showWarning, 300);
            });
            load();
            input.focus();
        },
        onOk: () => {
            applyClassTargets(editor, targets);
            editor.commands.focus();
            return true;
        },
    });
};

export const MahoCssClasses = Extension.create({
    name: 'mahoCssClasses',

    addCommands() {
        return {
            openClassDialog: () => ({ editor }) => {
                openClassDialog(editor.options.wysiwygSetup);
                return true;
            },
        };
    },
});
