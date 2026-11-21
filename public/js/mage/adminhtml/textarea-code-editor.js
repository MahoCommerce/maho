// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { EditorView } from '../../codemirror.js';
import { MahoCodeEditor, createCssView, createXmlView } from './wysiwyg/tiptap/code-editor.js';

const fields = [
    ['page_layout_update_xml', createXmlView],
    ['page_custom_layout_update_xml', createXmlView],
    ['template_styles', createCssView],
];

for (const [id, createView] of fields) {
    const textarea = document.getElementById(id);
    if (!(textarea instanceof HTMLTextAreaElement) || textarea.disabled || textarea.readOnly) {
        continue;
    }

    const label = textarea.labels[0];
    const editor = new MahoCodeEditor(textarea, textarea, (parent, doc, extensions) => createView(parent, doc, [
        EditorView.contentAttributes.of({ 'aria-label': label?.textContent.trim() || textarea.name }),
        EditorView.theme({
            '&': { width: '98%' },
            '.cm-scroller': { minHeight: textarea.style.height || '8rem' },
        }),
        ...extensions,
    ]));

    label?.addEventListener('click', (event) => {
        event.preventDefault();
        editor.view.focus();
    });
}
