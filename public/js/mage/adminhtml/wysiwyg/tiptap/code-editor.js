// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import {
    EditorView, basicSetup, htmlLanguage, htmlCompletionSource, autoCloseTags, css, javascript, xml,
    HighlightStyle, LanguageSupport, syntaxHighlighting, syntaxTree, tags, linter,
    Decoration, MatchDecorator, ViewPlugin, WidgetType, autocompletion, hoverTooltip,
} from '../../../../codemirror.js';
import { classDetails, findUnknownClasses, listClasses, shortClasses, sortClasses } from './class-check.js';

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
    '.cm-scroller': { fontFamily: 'Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace', maxHeight: '70vh' },
    '.cm-gutters': { backgroundColor: 'var(--maho-surface-sunken, #f1f1f1)', color: 'var(--maho-ink-muted, #666)', borderRight: '1px solid var(--maho-border-soft, #ddd)' },
    '.cm-activeLine, .cm-activeLineGutter': { backgroundColor: 'light-dark(rgba(0, 0, 0, .04), rgba(255, 255, 255, .05))' },
    '.cm-cursor': { borderLeftColor: 'var(--maho-ink, #1c1c1c)' },
    '.cm-tw-swatch': {
        display: 'inline-block',
        boxSizing: 'border-box',
        width: '0.85em',
        height: '0.85em',
        marginRight: '0.25em',
        verticalAlign: '-0.1em',
        border: '1px solid light-dark(rgba(0, 0, 0, .25), rgba(255, 255, 255, .35))',
        borderRadius: '2px',
    },
    '.cm-tw-css': {
        margin: 0,
        padding: '0.4em 0.6em',
        fontFamily: 'Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
        fontSize: '12px',
        whiteSpace: 'pre-wrap',
        overflowWrap: 'anywhere',
        maxHeight: '20em',
        overflow: 'auto',
    },
    '.cm-tw-sort': { display: 'block', width: '100%', padding: '0.4em 0.6em', border: 'none', borderTop: '1px solid var(--maho-border-soft, #ddd)', background: 'none', color: 'inherit', font: 'inherit', textAlign: 'left', cursor: 'pointer' },
    '.cm-tw-sort:hover': { backgroundColor: 'light-dark(rgba(0, 0, 0, .05), rgba(255, 255, 255, .08))' },
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
 * The classes in the `class` attributes between `from` and `to`, as { from, to, name, value }.
 * `value` is the syntax node of the attribute value. A directive such as {{var}} is not a class.
 */
const classTokens = (state, from, to) => {
    const tokens = [];
    syntaxTree(state).iterate({
        from,
        to,
        enter: (node) => {
            if (!isClassValue(state, node.node)) {
                return;
            }
            const value = node.node;
            for (const match of state.sliceDoc(value.from, value.to).matchAll(/[^\s"']+/g)) {
                if (!/[{}]/.test(match[0])) {
                    tokens.push({ from: value.from + match.index, to: value.from + match.index + match[0].length, name: match[0], value });
                }
            }
        },
    });
    return tokens;
};

/** A square in the color `color`. */
const swatch = (color) => {
    const dom = document.createElement('span');
    dom.className = 'cm-tw-swatch';
    dom.style.background = color;
    return dom;
};

/** The CSS of a class, for a tooltip. */
const cssNode = (details) => {
    const dom = document.createElement('pre');
    dom.className = 'cm-tw-css';
    dom.textContent = details.css;
    return dom;
};

/** The swatch of a color class in the completion list. */
const SWATCH_OPTION = {
    render: (completion) => {
        const color = completion.details?.(completion.label)?.color;
        return color ? swatch(color) : null;
    },
    position: 30,
};

class SwatchWidget extends WidgetType {
    constructor(color) {
        super();
        this.color = color;
    }

    eq(other) {
        return other.color === this.color;
    }

    toDOM() {
        return swatch(this.color);
    }
}

/** A swatch before each color class in the visible part of the document. */
const swatches = (setup) => ViewPlugin.fromClass(class {
    constructor(view) {
        this.details = null;
        this.decorations = Decoration.none;
        classDetails(setup).then((details) => {
            if (details && !this.destroyed) {
                this.details = details;
                this.decorations = this.build(view);
                // An empty transaction draws the new decorations
                view.dispatch({});
            }
        });
    }

    update(update) {
        if (this.details && (update.docChanged || update.viewportChanged)) {
            this.decorations = this.build(update.view);
        }
    }

    build(view) {
        const ranges = [];
        for (const { from, to } of view.visibleRanges) {
            for (const token of classTokens(view.state, from, to)) {
                const color = this.details(token.name)?.color;
                if (color) {
                    ranges.push(Decoration.widget({ widget: new SwatchWidget(color), side: -1 }).range(token.from));
                }
            }
        }
        return Decoration.set(ranges, true);
    }

    destroy() {
        this.destroyed = true;
    }
}, { decorations: (plugin) => plugin.decorations });

/**
 * A tooltip on a class with the CSS that Tailwind makes for it, and a button that sorts the
 * classes of the attribute when they are not in the order of the Tailwind build.
 */
const classTooltip = (setup) => hoverTooltip(async (view, pos) => {
    const { state } = view;
    const token = classTokens(state, pos, pos).find((item) => item.from <= pos && item.to >= pos);
    if (!token) {
        return null;
    }
    const siblings = classTokens(state, token.value.from, token.value.to).filter((item) => item.value.from === token.value.from);
    const names = siblings.map((item) => item.name);
    const sortable = names.length > 1 && !/[{}]/.test(state.sliceDoc(token.value.from, token.value.to));
    const [details, sorted] = await Promise.all([classDetails(setup), sortable ? sortClasses(setup, names) : null]);
    const css = details?.(token.name) ?? null;
    const unsorted = sorted !== null && sorted.some((name, index) => name !== names[index]);
    if ((css === null && !unsorted) || view.state.doc !== state.doc) {
        return null;
    }
    return {
        pos: token.from,
        end: token.to,
        above: true,
        create: () => {
            const dom = document.createElement('div');
            if (css !== null) {
                dom.append(cssNode(css));
            }
            if (unsorted) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cm-tw-sort';
                button.textContent = setup.translate('Sort the classes');
                button.addEventListener('click', () => {
                    if (view.state.doc === state.doc) {
                        view.dispatch({ changes: { from: siblings[0].from, to: siblings.at(-1).to, insert: sorted.join(' ') } });
                    }
                });
                dom.append(button);
            }
            return { dom };
        },
    };
});

/** The completion options of each class list, so a list builds them once. */
const optionsOf = new WeakMap();

const completeClass = async (setup, context) => {
    const word = context.matchBefore(CLASS_NAME);
    if (word === null || (word.from === word.to && !context.explicit)) {
        return null;
    }
    const names = await listClasses(setup);
    if (!names) {
        return null;
    }
    if (!optionsOf.has(names)) {
        const details = await classDetails(setup);
        const info = (label) => {
            const css = details(label);
            return css === null ? null : cssNode(css);
        };
        optionsOf.set(names, [
            ...names.variants.map((label) => ({ label, type: 'keyword', boost: -1 })),
            ...names.classes.map((label) => (details ? { label, type: 'class', details, info: () => info(label) } : { label, type: 'class' })),
        ]);
    }
    // After a variant such as `md:`, the completion starts again with the next part
    return {
        from: word.from + word.text.lastIndexOf(':') + 1,
        options: optionsOf.get(names),
        validFor: /^[^\s"'{}:]*$/,
    };
};

const lintClasses = async (setup, view) => {
    const { state } = view;
    const found = classTokens(state, 0, state.doc.length);
    const names = [...new Set(found.map((item) => item.name))];
    const [unknownNames, short] = await Promise.all([findUnknownClasses(setup, names), shortClasses(setup, names)]);
    const unknown = new Set(unknownNames ?? []);
    // An edit during the check moved the positions. The linter runs again for that edit.
    if (view.state.doc !== state.doc) {
        return [];
    }
    return found.flatMap((item) => {
        if (unknown.has(item.name)) {
            return [{
                from: item.from,
                to: item.to,
                severity: 'warning',
                message: setup.translate('No style found for the class "%s". Check the spelling. A daisyUI class that the theme does not use gets its style after the save.', item.name),
            }];
        }
        const name = short.get(item.name);
        return name === undefined ? [] : [{
            from: item.from,
            to: item.to,
            severity: 'info',
            message: setup.translate('The short form of this class is "%s".', name),
            actions: [{ name: setup.translate('Replace'), apply: (target, from, to) => target.dispatch({ changes: { from, to, insert: name } }) }],
        }];
    });
};

/**
 * A CodeMirror view for the HTML of the editor of `setup`, in `parent`. Inside a `class`
 * attribute, the completion offers the classes of the store view with their CSS, and the linter
 * marks a class that the storefront does not style. A color class shows its color, and a tooltip
 * on a class shows its CSS.
 */
export const createHtmlView = (setup, parent, doc, extensions = []) => new EditorView({
    parent,
    doc,
    extensions: [
        basicSetup,
        // html() of lang-html, with one completion source. Its own source also answers in a
        // `class` value, with no options, and the list then opens at the start of the value.
        new LanguageSupport(htmlLanguage, [
            htmlLanguage.data.of({
                autocomplete: (context) => (isClassValue(context.state, syntaxTree(context.state).resolveInner(context.pos, -1))
                    ? completeClass(setup, context)
                    : htmlCompletionSource(context)),
            }),
            autoCloseTags,
            javascript().support,
            css().support,
        ]),
        syntaxHighlighting(HIGHLIGHT),
        THEME,
        EditorView.theme({ '.cm-scroller': { minHeight: '20rem' } }),
        EditorView.lineWrapping,
        autocompletion({ addToOptions: [SWATCH_OPTION] }),
        swatches(setup),
        classTooltip(setup),
        linter((view) => lintClasses(setup, view), { delay: 750 }),
        ...extensions,
    ],
});

const VARIABLE = new MatchDecorator({
    regexp: /\{\{[^{}]+\}\}/g,
    decoration: Decoration.mark({ class: 'cm-variable' }),
});

/** Colors a variable such as {{store_name}}, which the XML parser reads as plain text. */
const VARIABLES = ViewPlugin.define((view) => ({
    decorations: VARIABLE.createDeco(view),
    update(update) {
        this.decorations = VARIABLE.updateDeco(update, this.decorations);
    },
}), { decorations: (plugin) => plugin.decorations });

/** A CodeMirror view for XML with {{...}} variables, such as the header of a product feed. */
export const createXmlView = (parent, doc, extensions = []) => new EditorView({
    parent,
    doc,
    extensions: [
        basicSetup,
        xml(),
        VARIABLES,
        syntaxHighlighting(HIGHLIGHT),
        THEME,
        EditorView.theme({ '.cm-variable': { color: 'light-dark(#8250df, #d2a8ff)' } }),
        EditorView.lineWrapping,
        ...extensions,
    ],
});

/**
 * The source view of a content field: CodeMirror before `content`, the content of the rich text
 * editor. The textarea stays the form field, and it stays hidden. Each change writes it and fires
 * `input`, so the form, the sanitizer and the assistant read it as before.
 */
export class MahoCodeEditor {
    /** `createView(parent, doc, extensions)` makes the view, such as createXmlView(). */
    constructor(textarea, content, createView) {
        this.textarea = textarea;
        this.writing = false;
        this.changed = false;
        // CodeMirror gives its root `display: flex !important`, so the wrapper is what hides
        this.dom = document.createElement('div');
        this.dom.className = 'maho-code-editor';
        this.view = createView(this.dom, this.textarea.value, [
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
        ]);
        content.before(this.dom);
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
