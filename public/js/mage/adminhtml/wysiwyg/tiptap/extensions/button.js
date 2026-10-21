// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { Extension } from 'https://esm.sh/@tiptap/core@3.31.3';
import { Plugin, PluginKey } from 'https://esm.sh/@tiptap/pm@3.31.3/state';

/**
 * The daisyUI classes of a storefront button. tailwind.css builds every one of them,
 * so a button in CMS content keeps its style on the storefront.
 */
export const BUTTON_COLORS = [['', 'Default'], ['primary', 'Primary'], ['secondary', 'Secondary'], ['accent', 'Accent'], ['neutral', 'Neutral']];
export const BUTTON_STYLES = [['', 'Solid'], ['outline', 'Outline'], ['soft', 'Soft'], ['ghost', 'Ghost'], ['link', 'Link']];
export const BUTTON_SIZES = [['sm', 'Small'], ['', 'Medium'], ['lg', 'Large'], ['xl', 'Extra Large']];

const valueOf = (classes, list) => list.find(([value]) => value !== '' && classes.includes(`btn-${value}`))?.[0] ?? '';

/** Read the dialog values from the class list of a button. */
export const buttonFromClasses = (classAttribute) => {
    const classes = String(classAttribute ?? '').split(/\s+/);
    return {
        color: valueOf(classes, BUTTON_COLORS),
        style: valueOf(classes, BUTTON_STYLES),
        size: valueOf(classes, BUTTON_SIZES),
        wide: classes.includes('btn-wide'),
        block: classes.includes('btn-block'),
    };
};

export const buttonClasses = (values) => ['btn',
    ...[values.color, values.style, values.size].filter(Boolean).map((value) => `btn-${value}`),
    ...(values.wide ? ['btn-wide'] : []),
    ...(values.block ? ['btn-block'] : []),
].join(' ');

const buttonHtml = (values) => {
    const attributes = [`class="${buttonClasses(values)}"`, `href="${escapeHtml(values.href, true)}"`];
    if (values.newTab) {
        attributes.push('target="_blank"', 'rel="noopener"');
    }
    return `<a ${attributes.join(' ')}>${escapeHtml(values.label)}</a>`;
};

const dialogHtml = (setup, values) => {
    const t = (text) => escapeHtml(setup.translate(text), true);
    const select = (name, options, current) => `<select id="tiptap-button-${name}">`
        + options.map(([value, label]) => `<option value="${value}"${value === current ? ' selected' : ''}>${t(label)}</option>`).join('')
        + '</select>';
    const check = (name, label, checked) => `<label class="tiptap-button-check"><input type="checkbox" id="tiptap-button-${name}"${checked ? ' checked' : ''}> ${t(label)}</label>`;
    return `<div class="tiptap-button-form">
        <label for="tiptap-button-label">${t('Label')}</label>
        <input type="text" id="tiptap-button-label" value="${escapeHtml(values.label, true)}">
        <label for="tiptap-button-href">${t('Link')}</label>
        <input type="text" id="tiptap-button-href" value="${escapeHtml(values.href, true)}" placeholder="/sale">
        <div class="tiptap-button-row">
            <span><label for="tiptap-button-color">${t('Color')}</label>${select('color', BUTTON_COLORS, values.color)}</span>
            <span><label for="tiptap-button-style">${t('Style')}</label>${select('style', BUTTON_STYLES, values.style)}</span>
            <span><label for="tiptap-button-size">${t('Size')}</label>${select('size', BUTTON_SIZES, values.size)}</span>
        </div>
        <div class="tiptap-button-row">
            ${check('wide', 'Wide', values.wide)}
            ${check('block', 'Full width', values.block)}
            ${check('new-tab', 'Open in a new tab', values.newTab)}
        </div>
        <p class="tiptap-button-error" hidden></p>
    </div>`;
};

const readDialog = (dialog) => {
    const field = (name) => dialog.querySelector(`#tiptap-button-${name}`);
    return {
        label: field('label').value.trim(),
        href: field('href').value.trim(),
        color: field('color').value,
        style: field('style').value,
        size: field('size').value,
        wide: field('wide').checked,
        block: field('block').checked,
        newTab: field('new-tab').checked,
    };
};

/**
 * A button is its own block: it goes into an empty text block, else into a new
 * paragraph after the block at the caret, inside the same column or cell.
 */
const insertButton = (editor, html) => {
    const { $from } = editor.state.selection;
    if ($from.parent.isTextblock && $from.parent.content.size === 0) {
        editor.chain().focus().insertContent(html).run();
        return;
    }
    const at = $from.parent.isTextblock ? $from.after() : editor.state.selection.to;
    editor.chain().focus().insertContentAt(at, `<p>${html}</p>`).run();
};

/** Open the button dialog. With an anchor of the content, edit that button. */
export const openButtonDialog = (setup, anchor = null) => {
    const editor = setup.editor;
    if (anchor) {
        const from = editor.view.posAtDOM(anchor, 0);
        editor.chain().focus().setTextSelection({ from, to: from }).extendMarkRange('link').run();
    }
    const link = editor.isActive('link') ? editor.getAttributes('link') : null;
    const editing = link !== null && String(link.class ?? '').split(/\s+/).includes('btn');
    if (editing && editor.state.selection.empty) {
        editor.chain().focus().extendMarkRange('link').run();
    }
    const { from, to } = editor.state.selection;
    const values = {
        label: editing || from !== to ? editor.state.doc.textBetween(from, to, ' ') : '',
        href: link?.href ?? '',
        newTab: link?.target === '_blank',
        ...buttonFromClasses(editing ? link.class : 'btn-primary'),
    };

    Dialog.confirm(dialogHtml(setup, values), {
        id: 'tiptap-button-dialog',
        title: setup.translate(editing ? 'Edit Button' : 'Insert Button'),
        className: 'maho-dialog tiptap-button-dialog',
        width: 480,
        cancel: true,
        okLabel: setup.translate(editing ? 'Update' : 'Insert'),
        onOpen: (dialog) => dialog.querySelector('#tiptap-button-label')?.focus(),
        onOk: (dialog) => {
            const form = readDialog(dialog);
            if (form.label === '' || form.href === '') {
                const error = dialog.querySelector('.tiptap-button-error');
                error.textContent = setup.translate('Enter a label and a link.');
                error.hidden = false;
                return false;
            }
            if (editing || from !== to) {
                editor.chain().focus().extendMarkRange('link').insertContent(buttonHtml(form)).run();
            } else {
                insertButton(editor, buttonHtml(form));
            }
            return true;
        },
    });
};

/** A click on a button of the content opens its dialog instead of moving the caret. */
export const MahoButton = Extension.create({
    name: 'mahoButton',

    addCommands() {
        return {
            openButtonDialog: () => ({ editor }) => {
                openButtonDialog(editor.options.wysiwygSetup);
                return true;
            },
        };
    },

    addProseMirrorPlugins() {
        const editor = this.editor;
        return [new Plugin({
            key: new PluginKey('mahoButton'),
            props: {
                handleDOMEvents: {
                    click: (view, event) => {
                        const anchor = event.target instanceof Element ? event.target.closest('a.btn') : null;
                        if (!anchor || !view.dom.contains(anchor) || !editor.isEditable) {
                            return false;
                        }
                        event.preventDefault();
                        openButtonDialog(editor.options.wysiwygSetup, anchor);
                        return true;
                    },
                },
            },
        })];
    },
});
