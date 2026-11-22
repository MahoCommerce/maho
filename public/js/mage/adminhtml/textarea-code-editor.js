// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

const textareas = [...document.querySelectorAll('textarea[data-code-editor]')]
    .filter((textarea) => !textarea.disabled && !textarea.readOnly && !textarea.mahoCodeEditor);

if (textareas.length) {
    const [{ EditorView }, { MahoCodeEditor, createCssView, createXmlView }] = await Promise.all([
        import('../../codemirror.js'),
        import('./wysiwyg/tiptap/code-editor.js'),
    ]);
    const views = { css: createCssView, xml: createXmlView };

    for (const textarea of textareas) {
        const createView = views[textarea.dataset.codeEditor];
        if (!createView) {
            continue;
        }

        const label = textarea.labels[0];
        const editor = new MahoCodeEditor(textarea, textarea, (parent, doc, extensions) => createView(parent, doc, [
            EditorView.contentAttributes.of({ 'aria-label': label?.textContent.trim() || textarea.name }),
            EditorView.theme({
                '&': { width: textarea.style.width || '98%' },
                '.cm-scroller': { minHeight: textarea.style.height || '8rem' },
            }),
            ...extensions,
        ]));

        label?.addEventListener('click', (event) => {
            event.preventDefault();
            editor.view.focus();
        });
    }
}
