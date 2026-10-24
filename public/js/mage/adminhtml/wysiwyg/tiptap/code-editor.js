// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import {
    EditorView, basicSetup, htmlLanguage, htmlCompletionSource, autoCloseTags, css, javascript,
    HighlightStyle, LanguageSupport, syntaxHighlighting, syntaxTree, tags, linter,
} from '../../../../codemirror.js';
import { findUnknownClasses, listClasses } from './class-check.js';

/** The syntax colors. `light-dark()` follows the color scheme of the admin. */
const HIGHLIGHT = HighlightStyle.define([
    { tag: [tags.tagName, tags.typeName], color: 'light-dark(#116329, #7ee787)' },
    { tag: [tags.attributeName, tags.number, tags.atom, tags.bool], color: 'light-dark(#0550ae, #79c0ff)' },
    { tag: [tags.attributeValue, tags.string], color: 'light-dark(#0a3069, #a5d6ff)' },
    { tag: [tags.comment, tags.angleBracket, tags.processingInstruction], color: 'light-dark(#6e7781, #8b949e)' },
    { tag: tags.comment, fontStyle: 'italic' },
    { tag: [tags.keyword, tags.invalid], color: 'light-dark(#cf222e, #ff7b72)' },
    { tag: tags.propertyName, color: 'light-dark(#953800, #ffa657)' },
    { tag: [tags.variableName, tags.function(tags.variableName)], color: 'light-dark(#8250df, #d2a8ff)' },
]);

const THEME = EditorView.theme({
    '&': {
        backgroundColor: 'var(--maho-surface, #fff)',
        color: 'var(--maho-ink, #1c1c1c)',
        border: '1px solid var(--maho-border, #ccc)',
        borderRadius: '4px',
        fontSize: '13px',
    },
    '&.cm-focused': { outline: '2px solid var(--maho-focus-ring, #647004)', outlineOffset: '-1px' },
    '.cm-scroller': { fontFamily: 'Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace', minHeight: '20rem', maxHeight: '70vh' },
    '.cm-gutters': { backgroundColor: 'var(--maho-surface-sunken, #f1f1f1)', color: 'var(--maho-ink-muted, #666)', borderRight: '1px solid var(--maho-border-soft, #ddd)' },
    '.cm-activeLine, .cm-activeLineGutter': { backgroundColor: 'light-dark(rgba(0, 0, 0, .04), rgba(255, 255, 255, .05))' },
    '.cm-cursor': { borderLeftColor: 'var(--maho-ink, #1c1c1c)' },
});

/** A class name, up to the cursor. A directive such as {{var}} is not a class. */
const CLASS_NAME = /[^\s"'{}]*/;

/** The `class` attribute whose value holds the syntax node `node`, else false. */
const isClassValue = (state, node) => {
    if (node?.name !== 'AttributeValue' && node?.name !== 'UnquotedAttributeValue') {
        return false;
    }
    const name = node.parent?.getChild('AttributeName');
    return name !== null && name !== undefined && state.sliceDoc(name.from, name.to).toLowerCase() === 'class';
};

/**
 * The plain HTML editor of a content field: CodeMirror in place of the textarea. The textarea
 * stays the form field. Each change writes it and fires `input`, so the form, the sanitizer and
 * the assistant read it as before.
 *
 * Inside a `class` attribute, the completion offers the classes of the store view, and the linter
 * marks a class that the storefront does not style.
 */
export class MahoCodeEditor {
    constructor(setup) {
        this.setup = setup;
        this.textarea = setup.textarea;
        this.writing = false;
        this.changed = false;
        // CodeMirror gives its root `display: flex !important`, so the wrapper is what hides
        this.dom = document.createElement('div');
        this.dom.className = 'maho-code-editor';
        this.view = new EditorView({
            parent: this.dom,
            doc: this.textarea.value,
            extensions: [
                basicSetup,
                // html() of lang-html, with one completion source. Its own source also answers in a
                // `class` value, with no options, and the list then opens at the start of the value.
                new LanguageSupport(htmlLanguage, [
                    htmlLanguage.data.of({ autocomplete: (context) => this.complete(context) }),
                    autoCloseTags,
                    javascript().support,
                    css().support,
                ]),
                syntaxHighlighting(HIGHLIGHT),
                THEME,
                EditorView.lineWrapping,
                linter((view) => this.lintClasses(view), { delay: 750 }),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        this.write();
                    }
                    // A textarea fires `change` when it loses the focus after an edit
                    if (update.focusChanged && !update.view.hasFocus && this.changed) {
                        this.changed = false;
                        this.notify('change');
                    }
                }),
            ],
        });
        this.textarea.after(this.dom);
        toggleVis(this.textarea, false);
        this.textarea.mahoCodeEditor = this;

        // Another script that writes the textarea, such as the assistant, fires `input` or `change`
        this.reload = () => {
            if (!this.writing) {
                this.load();
            }
        };
        this.textarea.addEventListener('input', this.reload);
        this.textarea.addEventListener('change', this.reload);
    }

    /** Show the content of the textarea. */
    load() {
        const value = this.textarea.value;
        const doc = this.view.state.doc.toString();
        if (value !== doc) {
            this.view.dispatch({ changes: { from: 0, to: doc.length, insert: value } });
        }
    }

    write() {
        const value = this.view.state.doc.toString();
        if (value === this.textarea.value) {
            return;
        }
        this.textarea.value = value;
        this.changed = true;
        this.notify('input');
    }

    notify(type) {
        this.writing = true;
        try {
            this.textarea.dispatchEvent(new Event(type, { bubbles: true }));
        } finally {
            this.writing = false;
        }
    }

    /** Put `value` in place of the selection, as updateElementAtCursor() does in a textarea. */
    insert(value) {
        this.view.dispatch(this.view.state.replaceSelection(value));
        this.view.focus();
    }

    isVisible() {
        return this.dom.checkVisibility();
    }

    show(visible) {
        if (visible) {
            this.load();
        }
        toggleVis(this.dom, visible);
    }

    contains(element) {
        return this.dom.contains(element);
    }

    complete(context) {
        return isClassValue(context.state, syntaxTree(context.state).resolveInner(context.pos, -1))
            ? this.completeClass(context)
            : htmlCompletionSource(context);
    }

    async completeClass(context) {
        const word = context.matchBefore(CLASS_NAME);
        if (word === null || (word.from === word.to && !context.explicit)) {
            return null;
        }
        const names = await listClasses(this.setup);
        if (!names) {
            return null;
        }
        // The names change with the store view of the preview
        if (this.optionsOf !== names) {
            this.optionsOf = names;
            this.options = [
                ...names.variants.map((label) => ({ label, type: 'keyword', boost: -1 })),
                ...names.classes.map((label) => ({ label, type: 'class' })),
            ];
        }
        // After a variant such as `md:`, the completion starts again with the next part
        return {
            from: word.from + word.text.lastIndexOf(':') + 1,
            options: this.options,
            validFor: /^[^\s"'{}:]*$/,
        };
    }

    async lintClasses(view) {
        const { state } = view;
        const found = [];
        syntaxTree(state).iterate({
            enter: (node) => {
                if (!isClassValue(state, node.node)) {
                    return;
                }
                for (const match of state.sliceDoc(node.from, node.to).matchAll(/[^\s"']+/g)) {
                    if (!/[{}]/.test(match[0])) {
                        found.push({ from: node.from + match.index, to: node.from + match.index + match[0].length, name: match[0] });
                    }
                }
            },
        });
        const unknown = new Set(await findUnknownClasses(this.setup, [...new Set(found.map((item) => item.name))]) ?? []);
        // An edit during the check moved the positions. The linter runs again for that edit.
        if (unknown.size === 0 || view.state.doc !== state.doc) {
            return [];
        }
        return found.filter((item) => unknown.has(item.name)).map((item) => ({
            from: item.from,
            to: item.to,
            severity: 'warning',
            message: this.setup.translate('No style found for the class "%s". Check the spelling. A daisyUI class that the theme does not use gets its style after the save.', item.name),
        }));
    }

    destroy() {
        const visible = this.isVisible();
        this.textarea.removeEventListener('input', this.reload);
        this.textarea.removeEventListener('change', this.reload);
        delete this.textarea.mahoCodeEditor;
        this.view.destroy();
        this.dom.remove();
        toggleVis(this.textarea, visible);
    }
}
