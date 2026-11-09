// SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: AFL-3.0

import { Editor, Node, Mark, Extension, mergeAttributes, ResizableNodeView, StarterKit, Image, TextAlign, Table, TableRow as BaseTableRow, TableCell, TableHeader, BubbleMenu, DragHandle } from '../../../../tiptap.js';
import { MahoColumns, MahoColumn, COLUMN_PRESETS } from './extensions/columns.js';
import { MahoBentoGrid, MahoBentoCell, BENTO_PRESETS } from './extensions/bento.js';
import { MahoAccordion, MahoDetails, DetailsSummary, DetailsContent, ACCORDION_STYLES } from './extensions/details.js';
import { MahoSvgBlock, MahoSvgInline } from './extensions/svg.js';
import { MahoHtml, EDITOR_ID_ATTRIBUTE, restoreIds } from './extensions/html.js';
import { mountDirectiveRender, FRAMED_TYPES, INLINE_TYPES } from './extensions/widget-render.js';
import { MahoBlockMove, STRUCTURAL_NODES } from './extensions/block-move.js';
import { MahoButton } from './extensions/button.js';

// prosemirror-tables has no parse rules for the table wrappers browsers (and TipTap itself) emit,
// so with `enableContentCheck` on TipTap's catch-all rule flags them and falsely warns that valid
// tables are "not supported". `skip` keeps the rows inside the row-group sections; `ignore` drops
// the presentational <colgroup> (and its <col> children) whose widths already live on the cells.
const TableRow = BaseTableRow.extend({
    parseHTML() {
        return [
            ...(this.parent?.() ?? [{ tag: 'tr' }]),
            { tag: 'tbody', skip: true },
            { tag: 'thead', skip: true },
            { tag: 'tfoot', skip: true },
            { tag: 'colgroup', ignore: true },
        ];
    },
});

export {
    Editor, Node, Mark, Extension, StarterKit, TextAlign,
    Table, TableRow, TableCell, TableHeader, BubbleMenu, DragHandle,
    MahoColumns, MahoColumn, COLUMN_PRESETS,
    MahoBentoGrid, MahoBentoCell, BENTO_PRESETS,
    MahoAccordion, MahoDetails, DetailsSummary, DetailsContent, ACCORDION_STYLES,
    MahoSvgBlock, MahoSvgInline, MahoHtml, restoreIds,
    MahoBlockMove, STRUCTURAL_NODES, MahoButton,
};

const parseDirective = (directiveStr) => {
    const directiveObj = {
        type: null,
        params: {},
    }
    directiveStr = (directiveStr ?? '').trim();
    if (directiveStr.startsWith('{{') && directiveStr.endsWith('}}')) {
        const [ type, attrStr ] = directiveStr.slice(2, -2).trim().split(/\s(.*)/);
        directiveObj.type = type;
        const trimmedAttr = (attrStr ?? '').trim();

        // Handle {{var variable_name}} format
        if (type === 'var' && trimmedAttr && !trimmedAttr.includes('=')) {
            directiveObj.params._value = trimmedAttr;
        }
        // Handle normal key="value" format
        else {
            for (const match of trimmedAttr.matchAll(/([\w\-]+)=(["'])(.*?)\2/g)) {
                directiveObj.params[match[1]] = match[3];
            }
        }
    }
    return directiveObj;
};

const renderDirective = (directiveObj) => {
    if (!directiveObj?.type) {
        return '';
    }
    let directiveStr = '{{' + directiveObj.type;

    // Handle {{var variable_name}} format
    if (directiveObj.type === 'var' && directiveObj.params._value) {
        directiveStr += ' ' + directiveObj.params._value;
    }
    // Handle normal key="value" format
    else {
        for (const [name, value] of Object.entries(directiveObj.params)) {
            if (name === '_value') continue; // Skip internal _value param
            if (value) {
                directiveStr += ` ${name}="${value}"`;
            } else {
                directiveStr += ` ${name}`;
            }
        }
    }
    directiveStr += '}}';
    return directiveStr
};

const renderDirectiveImageUrl = (src, directiveObj, directivesUrl) => {
    if (directiveObj?.type) {
        return setRouteParams(directivesUrl, {
            ___directive: Base64.mageEncode(renderDirective(directiveObj)),
        });
    }
    return src;
};

const getWidgetTypeForSelection = (state) => {
    const { from, to } = state.selection

    // If we have a selected maho widget, return the same type
    const selectedNode = state.doc.nodeAt(from, to);
    if (selectedNode?.type.name.startsWith('mahoWidget')) {
        return selectedNode.type.name;
    }

    // Otherwise traverse parent nodes skipping empty tags
    const pos = state.doc.resolve(from);

    let cur = pos.parent;
    while (cur.content.size === 0 && ['paragraph', 'heading'].includes(cur.type.name)) {
        cur = pos.node(pos.depth - 1);
    }

    // Use a block if allowed in this context
    if (cur.type.spec.content.includes('block')) {
        return 'mahoWidgetBlock';
    }

    return 'mahoWidgetInline';
}

/**
 * This extension preserves class and style attributes on all HTML elements
 */
export const GlobalAttributes = Extension.create({
    name: 'globalAttributes',

    addGlobalAttributes() {
        return [
            {
                types: [
                    'heading', 'paragraph', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'codeBlock',
                    'mahoImage',
                    'tableRow', 'tableCell', 'tableHeader', 'table',
                    'mahoColumns', 'mahoColumn',
                    'mahoBentoGrid', 'mahoBentoCell',
                    'mahoAccordion', 'details', 'detailsSummary', 'detailsContent',
                ],
                attributes: {
                    class: {
                        default: null,
                        parseHTML: element => element.getAttribute('class') || null,
                        renderHTML: attributes => {
                            if (!attributes.class) {
                                return {};
                            }
                            return {
                                class: attributes.class,
                            };
                        },
                    },
                    style: {
                        default: null,
                        parseHTML: element => element.getAttribute('style') || null,
                        renderHTML: attributes => {
                            if (!attributes.style) {
                                return {};
                            }
                            return {
                                style: attributes.style,
                            };
                        },
                    },
                },
            },
        ];
    },
});

/**
 * Vertical Align Extension for Table Cells
 *
 * Adds vertical-align support to table cells (top, middle, bottom)
 */
export const VerticalAlign = Extension.create({
    name: 'verticalAlign',

    addGlobalAttributes() {
        return [{
            types: ['tableCell', 'tableHeader'],
            attributes: {
                verticalAlign: {
                    default: null,
                    parseHTML: element => element.style.verticalAlign || null,
                    renderHTML: attributes => {
                        if (!attributes.verticalAlign) {
                            return {};
                        }
                        return {
                            style: `vertical-align: ${attributes.verticalAlign}`,
                        };
                    },
                },
            },
        }];
    },

    addCommands() {
        return {
            setVerticalAlign: (alignment) => ({ chain, state }) => {
                const { selection } = state;
                const isCellSelection = selection.constructor.name === 'CellSelection';

                if (isCellSelection) {
                    // Update all selected cells
                    return chain()
                        .updateAttributes('tableCell', { verticalAlign: alignment })
                        .updateAttributes('tableHeader', { verticalAlign: alignment })
                        .run();
                }

                // Single cell - find the parent cell
                const $pos = selection.$anchor;
                for (let depth = $pos.depth; depth > 0; depth--) {
                    const node = $pos.node(depth);
                    if (node.type.name === 'tableCell' || node.type.name === 'tableHeader') {
                        return chain()
                            .updateAttributes(node.type.name, { verticalAlign: alignment })
                            .run();
                    }
                }
                return false;
            },
        };
    },
});

/**
 * Maho Widget Node View Extension
 *
 * This extension adds widget and variable support
 */
export const MahoWidgetBlock = Node.create({
    name: 'mahoWidgetBlock',
    group: 'block',
    inline: false,
    draggable: true,
    atom: true,

    addAttributes() {
        return {
            directiveObj: {
                parseHTML: (element) => {
                    return parseDirective(element.getAttribute('data-directive'));
                },
                rendered: false,
            },
        }
    },

    parseHTML() {
        const tagName = this.name == 'mahoWidgetBlock' ? 'div' : 'span';
        return [{
            tag: tagName + '[data-type=maho-widget]',
        }];
    },

    renderHTML({ node }) {
        const tagName = this.name == 'mahoWidgetBlock' ? 'div' : 'span';
        const directiveStr = renderDirective(node.attrs.directiveObj);
        return [tagName, { 'data-type': 'maho-widget', 'data-directive': directiveStr }];
    },

    addNodeView() {
        return ({ node, editor }) => {
            const tagName = this.name == 'mahoWidgetBlock' ? 'div' : 'span';
            const dom = document.createElement(tagName);
            dom.dataset.type = `maho-${node.attrs.directiveObj.type}`;
            dom.contentEditable = 'false';

            let icon, label, dblclick;

            if (node.attrs.directiveObj.type === 'var') {
                icon = 'variable';
                label = node.attrs.directiveObj.params._value;
                dblclick = () => editor.commands.insertMahoVariable(node);
            }
            else if (node.attrs.directiveObj.type === 'config') {
                icon = 'variable';
                label = node.attrs.directiveObj.params.path;
                dblclick = () => editor.commands.insertMahoVariable(node);
            }
            else if (node.attrs.directiveObj.type === 'customvar') {
                icon = 'variable';
                label = node.attrs.directiveObj.params.code;
                dblclick = () => editor.commands.insertMahoVariable(node);
            }
            else if (node.attrs.directiveObj.type === 'widget') {
                icon = 'widget';
                label = node.attrs.directiveObj.params.type;
                dblclick = () => editor.commands.insertMahoWidget(node);
            }
            else if (node.attrs.directiveObj.type === 'block') {
                icon = 'block';
                label = node.attrs.directiveObj.params.block_id ?? node.attrs.directiveObj.params.id;
                // TODO: Add double-click to manage blocks
            }

            dom.innerHTML = '<span class="maho-widget-label">'
                + editor.options.wysiwygSetup.getIcon(icon ?? 'widget')
                + escapeHtml(label ?? renderDirective(node.attrs.directiveObj))
                + '</span>';

            if (dblclick) {
                dom.title = editor.options.wysiwygSetup.translate('Double-click to edit');
                dom.addEventListener('dblclick', dblclick);
            }

            const directive = renderDirective(node.attrs.directiveObj);
            const type = node.attrs.directiveObj.type;
            const inline = INLINE_TYPES.includes(type);
            const stopRender = inline || (this.name === 'mahoWidgetBlock' && FRAMED_TYPES.includes(type))
                ? mountDirectiveRender(editor.options.wysiwygSetup, dom, directive, { inline })
                : () => {};

            return {
                dom,
                // Keep the view, and its render, while the directive stays the same
                update: (updated) => updated.type === node.type && renderDirective(updated.attrs.directiveObj) === directive,
                destroy: stopRender,
                // The render adds the frame to the node view, and the document does not change
                ignoreMutation: () => true,
            };
        };
    },

    addCommands() {
        return {
            insertMahoWidget: (node) => ({ editor, state }) => {
                const { from, to } = state.selection;
                const type = getWidgetTypeForSelection(state);

                widgetTools.openDialog(this.options.widgetUrl, {
                    onOpen: () => {
                        widgetTools.initOptionValues(node?.attrs.directiveObj.params);
                    },
                    onOk: async () => {
                        try {
                            const directive = await window.wWidget.insertWidget(true);
                            if (directive) {
                                const directiveObj = parseDirective(directive);
                                editor.commands.insertContentAt({ from, to }, {
                                    type,
                                    attrs: { directiveObj },
                                });
                            }
                            return true;
                        } catch (error) {
                            console.error('Error inserting widget:', error);
                            return false;
                        }
                    },
                });
            },
            insertMahoVariable: (node) => ({ editor, state }) => {
                const { from, to } = state.selection;
                const type = getWidgetTypeForSelection(state);

                Variables.openDialog(this.options.variableUrl, {
                    onOpen: () => {
                        Variables.initSelected(renderDirective(node?.attrs.directiveObj));
                    },
                    onOk: (dialog) => {
                        const directiveObj = parseDirective(dialog.returnValue);
                        editor.commands.insertContentAt({ from, to }, {
                            type,
                            attrs: { directiveObj },
                        });
                    },
                });
            },
        }
    },
});

export const MahoWidgetInline = MahoWidgetBlock.extend({
    name: 'mahoWidgetInline',
    group: 'inline',
    inline: true,
});

/**
 * Maho Image Node View Extension
 *
 * This extension adds media browser and resize support
 */
export const MahoImage = Image.extend({
    name: 'mahoImage',

    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
            },
            height: {
                default: null,
            },
            directiveObj: {
                parseHTML: (element) => {
                    return parseDirective(element.getAttribute('src'));
                },
                rendered: false,
            },
        }
    },

    renderHTML({ node, HTMLAttributes }) {
        const src = renderDirective(node.attrs.directiveObj) || HTMLAttributes.src;
        return ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, { src })];
    },

    addNodeView() {
        return ({ node, editor, HTMLAttributes, getPos }) => {
            const img = document.createElement('img');

            for (const [key, value] of Object.entries(HTMLAttributes)) {
                if (key === 'src') {
                    img.src = renderDirectiveImageUrl(value, node.attrs.directiveObj, this.options.directivesUrl);
                } else if (key !== 'width' && key !== 'height' && value !== null) {
                    // width/height are applied as styles by ResizableNodeView from node attrs
                    img.setAttribute(key, value);
                }
            }

            img.addEventListener('error', () => {
                img.src = 'data:image/svg+xml,' + editor.options.wysiwygSetup.getIcon('image');
                img.classList.add('placeholder');
            }, { once: true });

            img.addEventListener('dblclick', () => {
                editor.commands.insertMahoImage(node);
            });

            const view = new ResizableNodeView({
                element: img,
                editor,
                node,
                getPos,
                onResize: (width, height) => {
                    img.style.width = `${width}px`;
                    img.style.height = `${height}px`;
                },
                onCommit: (width, height) => {
                    const pos = getPos();
                    if (pos !== undefined) {
                        // A width or height in the style overrides the new width and height attributes
                        const style = editor.state.doc.nodeAt(pos)?.attrs.style
                            ?.replace(/(?<=^|;)\s*(width|height)\s*:[^;]*(;|$)/gi, '').trim() || null;
                        editor.chain().setNodeSelection(pos).updateAttributes(this.name, { width, height, style }).run();
                    }
                },
                onUpdate: (updatedNode) => updatedNode.type === node.type,
                options: {
                    min: { width: 50, height: 50 },
                    preserveAspectRatio: true,
                    className: { container: 'image-container', handle: 'resize-handle' },
                },
            });

            // Tooltip lives on the container so the styled .image-container[title] hint applies
            view.dom.title = editor.options.wysiwygSetup.translate('Double-click to edit');

            return view;
        };
    },

    addCommands() {
        return {
            insertMahoImage: (node) => ({ editor, state }) => {
                const { from, to } = state.selection;

                const params = {}
                if (node?.attrs.directiveObj.params.url) {
                    const parts = node.attrs.directiveObj.params.url.split('/');
                    params.node = Base64.idEncode(parts.slice(1, -1).join('/'));
                    params.filename = Base64.idEncode(parts.pop());
                }
                if (node?.attrs.alt) {
                    params.alt = Base64.mageEncode(node.attrs.alt);
                }

                const url = setRouteParams(this.options.browserUrl, params);
                MediabrowserUtility.openDialog(url, null, null, this.options.title, {
                    ok: false, // Use Insert File button, not dialog OK button
                    onClose: (dialog) => {
                        // Dialog was cancelled or closed without selecting a file
                        const returnValue = dialog.returnValue || '';
                        if (!returnValue) {
                            return;
                        }

                        // Parse out the directive and alt text
                        let match;

                        match = returnValue.match(/src="({{.*?}})"/);
                        const directiveObj = parseDirective(match?.[1]);

                        match = returnValue.match(/alt="(.*?)"/);
                        const alt = unescapeHtml(match?.[1]);

                        // Keep some attributes of old image
                        const title = node?.attrs.title;
                        const width = node?.attrs.width;
                        const className = node?.attrs.class;
                        const style = node?.attrs.style;

                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { directiveObj, alt, title, width, class: className, style },
                        });
                    },
                });
            },
        }
    },
});


/**
 * Bounds of a custom breakpoint, in pixels. A `<source>` applies up to its max-width and the first
 * matching `<source>` wins, so the narrowest breakpoint must come first in the `<picture>`.
 */
const BREAKPOINT_MIN = 1;
const BREAKPOINT_MAX = 9999;

/** Read a custom breakpoint: a whole number in range, or null. */
const toBreakpoint = (value) => {
    const number = typeof value === 'string' && value.trim() === '' ? NaN : Number(value ?? NaN);
    return Number.isInteger(number) && number >= BREAKPOINT_MIN && number <= BREAKPOINT_MAX ? number : null;
};

/**
 * Read the breakpoint of a `<source>`: `{{breakpoint code=mobile}}` gives a preset that the store
 * knows, and one max-width query with nothing else gives a custom breakpoint. Any other `media`
 * returns null, so a variant that the browser would read differently is dropped instead of rewritten.
 */
const parseBreakpoint = (media, presets) => {
    const preset = /^\s*\{\{\s*breakpoint\s+code\s*=\s*(["']?)([a-z0-9_]+)\1\s*\}\}\s*$/i.exec(media ?? '')?.[2];
    if (preset !== undefined) {
        return presets.some((item) => item.code === preset) ? { preset, breakpoint: null } : null;
    }
    const breakpoint = toBreakpoint(/^\s*\(\s*max-width\s*:\s*(\d+)\s*px\s*\)\s*$/i.exec(media ?? '')?.[1]);
    return breakpoint === null ? null : { preset: null, breakpoint };
};

/** The `media` of a source. A preset stays a directive, so the storefront reads its current width. */
const renderMedia = (source) => source.preset
    ? `{{breakpoint code=${source.preset}}}`
    : `(max-width: ${source.breakpoint}px)`;

/** The width in pixels up to which a source applies: the width of its preset, or its custom breakpoint. */
const sourceWidth = (source, presets) => source.preset
    ? presets.find((item) => item.code === source.preset)?.width ?? null
    : toBreakpoint(source.breakpoint);

const sortSources = (sources, presets) =>
    [...sources].sort((a, b) => (sourceWidth(a, presets) ?? 0) - (sourceWidth(b, presets) ?? 0));

/**
 * The URL of the first candidate in a `srcset`, for the preview. A directive is a candidate of its
 * own, spaces included, so it is read up to its closing braces.
 */
const firstCandidate = (srcset) => {
    const value = (srcset ?? '').trim();
    if (value.startsWith('{{')) {
        const end = value.indexOf('}}');
        return end === -1 ? value : value.slice(0, end + 2);
    }
    // A comma separates the candidates, so `a.jpg, b.jpg 2x` starts with `a.jpg,`
    return value.split(/\s+/)[0].replace(/,+$/, '');
};

/** The breakpoint of a new alternative: the first preset that the slide does not use, else a free width. */
const nextBreakpoint = (slide, presets) => {
    const sources = slide.sources ?? [];
    const preset = presets.find((item) => !sources.some((source) => source.preset === item.code));
    if (preset) {
        return { preset: preset.code, breakpoint: null };
    }
    const used = new Set(sources.map((source) => sourceWidth(source, presets)));
    let breakpoint = [768, 480, 991, 1200, 360, 1600].find((value) => !used.has(value)) ?? BREAKPOINT_MIN;
    while (used.has(breakpoint)) {
        breakpoint++;
    }
    return { preset: null, breakpoint };
};

/**
 * Read the text of the breakpoint field: a preset by its label or its code, or a width such as
 * `900` or `900px`. Returns null for any other text.
 */
const parseBreakpointInput = (text, presets) => {
    const value = (text ?? '').trim().toLowerCase();
    const preset = presets.find((item) => item.label.toLowerCase() === value || item.code === value);
    if (preset) {
        return { preset: preset.code, breakpoint: null };
    }
    const breakpoint = toBreakpoint(/^(\d+)\s*(?:px)?$/.exec(value)?.[1]);
    return breakpoint === null ? null : { preset: null, breakpoint };
};

/**
 * Read the image that the media browser returns, as a slide stores it: null when the dialog gave
 * no image back.
 */
const parseBrowserImage = (returnValue) => {
    const src = /src="({{.*?}})"/.exec(returnValue ?? '')?.[1];
    if (!src) {
        return null;
    }
    const alt = unescapeHtml(/alt="(.*?)"/.exec(returnValue ?? '')?.[1]);
    return { src, alt, directiveObj: parseDirective(src) };
};

/**
 * Parse a `<div class="slideshow">` node into an array of slides
 * `[{ src, alt, href, directiveObj, sources: [{ srcset, preset, breakpoint }] }, ...]`
 */
const parseSlides = (div, presets) => {
    // Parse each <li> child and add to our array if it's a valid slide, i.e. contains an image
    const slides = [];
    for (const li of div.querySelectorAll(':scope > ul > li')) {
        const img = li.querySelector('img');
        const link = li.querySelector('a');
        if (img) {
            // The alternatives live in the <source> elements of a <picture> around the fallback <img>
            const sources = [];
            for (const source of li.querySelectorAll(':scope > a > picture > source, :scope > picture > source')) {
                const srcset = (source.getAttribute('srcset') ?? '').trim();
                const breakpoint = parseBreakpoint(source.getAttribute('media'), presets);
                if (srcset && breakpoint !== null) {
                    sources.push({ srcset, ...breakpoint });
                }
            }

            slides.push({
                src: img.getAttribute('src') ?? '',
                alt: img.getAttribute('alt') ?? '',
                href: link?.getAttribute('href'),
                directiveObj: parseDirective(img.getAttribute('src')),
                sources: sortSources(sources, presets),
            });
        }
    }
    return slides;
};

/**
 * Render array of slides into a TipTap node object.
 *
 * A slide without alternatives renders the same `<li><a><img></a></li>` as before, so existing
 * content saves byte for byte. With alternatives the `<img>` stays as the fallback for a browser
 * that ignores `<picture>`.
 */
const renderSlides = (slides, presets) => {
    return slides.map((slide) => {
        const { src, alt, href, sources = [] } = slide;

        let media;
        if (sources.length) {
            const picture = ['picture', {}];
            for (const source of sortSources(sources, presets)) {
                picture.push(['source', { media: renderMedia(source), srcset: source.srcset }]);
            }
            picture.push(['img', { src, alt }]);
            media = picture;
        } else {
            media = ['img', { src, alt }];
        }

        const content = href ? ['a', { href }, media] : media;
        return ['li', {}, content];
    });
}

/**
 * Maho Slideshow Node View Extension
 *
 * This extension adds support for slideshow divs with full editing capabilities
 */
export const MahoSlideshow = Node.create({
    name: 'mahoSlideshow',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            slides: {
                default: [],
                rendered: false,
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div.slideshow',
            getAttrs: (div) => {
                const slides = parseSlides(div, this.options.breakpoints ?? []);
                return { slides };
            },
        }];
    },

    renderHTML({ node }) {
        const slides = renderSlides(node.attrs.slides, this.options.breakpoints ?? []);
        return ['div', { class: 'slideshow' }, ['ul', {}, ...slides]];
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const slides = node.attrs.slides;

            const dom = document.createElement('div');
            dom.dataset.type = 'maho-slideshow';
            dom.contentEditable = 'false';
            dom.title = editor.options.wysiwygSetup.translate('Double-click to edit');

            // The preview keeps showing the desktop image only; the flag marks the slides that have alternatives
            dom.dataset.hasMobile = slides.some((slide) => slide.sources?.length) ? 'true' : 'false';

            // Create slide container and show the first slide if it exists
            const slideshow = dom.appendChild(document.createElement('div'));
            slideshow.className = 'slideshow';

            if (slides.length) {
                const img = slideshow.appendChild(document.createElement('img'));
                img.src = renderDirectiveImageUrl(slides[0].src, slides[0].directiveObj, this.options.directivesUrl);
                img.alt = slides[0].alt ?? '';
            }

            // Create dots and show at least one dot even if there are no slides
            const dots = dom.appendChild(document.createElement('div'));
            dots.className = 'slideshow-dots';

            for (let i = 0; i < Math.max(slides.length, 1); i++) {
                const dot = dots.appendChild(document.createElement('span'));
                dot.classList.add('dot');
                if (i === 0) {
                    dot.classList.add('active');
                }
            }

            // Double-click to edit
            dom.addEventListener('dblclick', () => {
                editor.commands.insertMahoSlideshow(node);
            });

            return { dom };
        };
    },

    addCommands() {
        return {
            insertMahoSlideshow: (node) => ({ editor, state }) => {
                const { from, to } = state.selection;
                // Deep copy, so that cancelling the dialog leaves the content as it is
                let slides = (node?.attrs?.slides ?? []).map((slide) => ({
                    ...slide,
                    sources: (slide.sources ?? []).map((source) => ({ ...source })),
                }));
                let sortableInstance;

                // Create slideshow editor dialog content
                const presets = this.options.breakpoints ?? [];
                const dialogContent = `
                    <div class="slideshow-editor">
                        <ul class="slides-list"></ul>
                    </div>
                `;
                const translate = (string) => editor.options.wysiwygSetup.translate(string);
                const slideTemplate = `
                    <div class="slide-handle">
                        ${editor.options.wysiwygSetup.getIcon('hamburger')}
                    </div>
                    <div class="slide-preview">
                        <img src="" alt="">
                    </div>
                    <div class="slide-controls">
                        <input type="text" class="slide-alt" placeholder="Alt text">
                        <input type="text" class="slide-href" placeholder="Link URL (optional)">
                        <div class="slide-sources"></div>
                        <button type="button" class="add-source">
                            ${editor.options.wysiwygSetup.getIcon('plus')}
                            <span>${escapeHtml(translate('Add mobile image'), true)}</span>
                        </button>
                    </div>
                    <button type="button" class="remove-slide" title="Remove slide">
                        ${editor.options.wysiwygSetup.getIcon('trash')}
                    </button>
                `;
                const sourceTemplate = `
                    <img class="source-preview" src="" alt="" title="${escapeHtml(translate('Mobile image'), true)}">
                    <div class="source-breakpoint">
                        <label class="source-breakpoint-label">${escapeHtml(translate('Breakpoint'), true)}</label>
                        <div class="source-breakpoint-combo">
                            <input type="text" class="source-breakpoint-input" autocomplete="off"
                                   role="combobox" aria-autocomplete="none" aria-expanded="false">
                            <button type="button" class="source-breakpoint-toggle" tabindex="-1" aria-hidden="true">
                                ${editor.options.wysiwygSetup.getIcon('chevron-down')}
                            </button>
                            <ul class="source-breakpoint-menu" role="listbox" hidden>
                                ${presets.map((preset) => `
                                    <li role="option" data-code="${escapeHtml(preset.code, true)}">
                                        <span>${escapeHtml(preset.label, true)}</span>
                                        <span class="source-breakpoint-width">${preset.width} px</span>
                                    </li>`).join('')}
                            </ul>
                        </div>
                        <span class="source-breakpoint-unit"></span>
                    </div>
                    <button type="button" class="remove-source" title="${escapeHtml(translate('Remove mobile image'), true)}">
                        ${editor.options.wysiwygSetup.getIcon('trash')}
                    </button>
                `;

                Dialog.info(dialogContent, {
                    id: 'slideshow-editor-dialog',
                    title: 'Insert/Edit Slideshow',
                    className: 'magento slideshow-editor',
                    width: 900,
                    height: 600,
                    ok: true,
                    cancel: true,
                    okLabel: 'Insert Slideshow',
                    extraButtons: [{ label: 'Add Image', class: 'add-slide-btn' }],
                    onOpen: (dialog) => {
                        const container = dialog.querySelector('.slideshow-editor');
                        const slidesList = container.querySelector('.slides-list');

                        const addSource = (slide) => {
                            MediabrowserUtility.openDialog(this.options.browserUrl, null, null, null, {
                                ok: false, // Disable OK button - use Insert File button or double-click instead
                                onClose: (dialog) => {
                                    const image = parseBrowserImage(dialog.returnValue);
                                    if (!image) {
                                        if (dialog.returnValue) {
                                            console.error('Could not parse image from:', dialog.returnValue);
                                        }
                                        return;
                                    }
                                    slide.sources ??= [];
                                    slide.sources.push({ srcset: image.src, ...nextBreakpoint(slide, presets) });
                                    renderSlideRows();
                                },
                            });
                        };

                        // Render existing slides. Named apart from the module-level renderSlides,
                        // which builds the TipTap node.
                        const renderSlideRows = () => {
                            slidesList.innerHTML = '';
                            slides.forEach((slide, index) => {
                                const li = slidesList.appendChild(document.createElement('li'));
                                li.className = 'slide-item';
                                li.dataset.index = index;
                                li.innerHTML = slideTemplate;
                                li.querySelector('.slide-alt').value = slide.alt ?? '';
                                li.querySelector('.slide-href').value = slide.href ?? '';
                                li.querySelector('.slide-preview img').src =
                                    renderDirectiveImageUrl(slide.src, slide.directiveObj, this.options.directivesUrl);

                                const sourcesList = li.querySelector('.slide-sources');
                                (slide.sources ?? []).forEach((source, sourceIndex) => {
                                    const row = sourcesList.appendChild(document.createElement('div'));
                                    row.className = 'slide-source';
                                    row.innerHTML = sourceTemplate;
                                    const previewUrl = firstCandidate(source.srcset);
                                    row.querySelector('.source-preview').src =
                                        renderDirectiveImageUrl(previewUrl, parseDirective(previewUrl), this.options.directivesUrl);
                                    const input = row.querySelector('.source-breakpoint-input');
                                    const unit = row.querySelector('.source-breakpoint-unit');
                                    const showUnit = () => {
                                        const width = source.invalid === undefined ? sourceWidth(source, presets) : null;
                                        unit.textContent = source.preset && width !== null ? `${width} px` : 'px';
                                        input.setAttribute('aria-invalid', source.invalid === undefined ? 'false' : 'true');
                                    };
                                    // A text that is not valid stays in the field until the user corrects it,
                                    // also when the rows are drawn again
                                    const showValue = () => {
                                        if (source.invalid !== undefined) {
                                            input.value = source.invalid;
                                        } else {
                                            input.value = source.preset
                                                ? presets.find((item) => item.code === source.preset)?.label ?? source.preset
                                                : source.breakpoint ?? '';
                                        }
                                        showUnit();
                                    };
                                    showValue();

                                    input.id = `slide-${index}-source-${sourceIndex}-breakpoint`;
                                    row.querySelector('.source-breakpoint-label').htmlFor = input.id;

                                    // The menu lists every preset, whatever the field holds, and the field
                                    // also takes a width that the user types
                                    const menu = row.querySelector('.source-breakpoint-menu');
                                    menu.id = `${input.id}-menu`;
                                    input.setAttribute('aria-controls', menu.id);
                                    const options = [...menu.querySelectorAll('[role="option"]')];
                                    options.forEach((option, n) => {
                                        option.id = `${menu.id}-${n}`;
                                    });
                                    let active = -1;
                                    const highlight = (position) => {
                                        active = position;
                                        options.forEach((option, n) => {
                                            option.classList.toggle('active', n === position);
                                            option.setAttribute('aria-selected', n === position ? 'true' : 'false');
                                        });
                                        if (options[position]) {
                                            input.setAttribute('aria-activedescendant', options[position].id);
                                        } else {
                                            input.removeAttribute('aria-activedescendant');
                                        }
                                    };
                                    const openMenu = () => {
                                        if (options.length && menu.hidden) {
                                            menu.hidden = false;
                                            input.setAttribute('aria-expanded', 'true');
                                            highlight(options.findIndex((option) => option.dataset.code === source.preset));
                                        }
                                    };
                                    const closeMenu = () => {
                                        menu.hidden = true;
                                        input.setAttribute('aria-expanded', 'false');
                                        input.removeAttribute('aria-activedescendant');
                                    };
                                    const pick = (option) => {
                                        source.preset = option.dataset.code;
                                        source.breakpoint = null;
                                        delete source.invalid;
                                        showValue();
                                        closeMenu();
                                    };

                                    input.addEventListener('focus', () => {
                                        openMenu();
                                        // After the click that gave the focus, so that typing replaces the value
                                        setTimeout(() => input.select());
                                    });
                                    input.addEventListener('click', openMenu);
                                    // An empty field stays empty, so Insert Slideshow reports it
                                    input.addEventListener('blur', () => {
                                        closeMenu();
                                        showValue();
                                    });
                                    input.addEventListener('keydown', (event) => {
                                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                            event.preventDefault();
                                            openMenu();
                                            const step = event.key === 'ArrowDown' ? 1 : -1;
                                            highlight((active + step + options.length) % options.length);
                                        } else if (event.key === 'Enter' && !menu.hidden && options[active]) {
                                            event.preventDefault();
                                            pick(options[active]);
                                        } else if (event.key === 'Escape' && !menu.hidden) {
                                            // Close the menu, not the dialog
                                            event.preventDefault();
                                            event.stopPropagation();
                                            closeMenu();
                                        }
                                    });
                                    input.addEventListener('input', () => {
                                        closeMenu();
                                        const parsed = parseBreakpointInput(input.value, presets);
                                        if (parsed) {
                                            Object.assign(source, parsed);
                                            delete source.invalid;
                                        } else {
                                            source.invalid = input.value;
                                        }
                                        showUnit();
                                    });
                                    // mousedown, not click: a click comes after the blur of the field
                                    row.querySelector('.source-breakpoint-toggle').addEventListener('mousedown', (event) => {
                                        event.preventDefault();
                                        if (menu.hidden) {
                                            input.focus();
                                            openMenu();
                                        } else {
                                            closeMenu();
                                        }
                                    });
                                    for (const option of options) {
                                        option.addEventListener('mousedown', (event) => {
                                            event.preventDefault();
                                            pick(option);
                                        });
                                    }
                                    row.querySelector('.remove-source').addEventListener('click', () => {
                                        slide.sources.splice(sourceIndex, 1);
                                        renderSlideRows();
                                    });
                                });

                                // Attach event listeners
                                li.querySelector('.slide-alt').addEventListener('change', (event) => {
                                    slide.alt = event.target.value
                                });
                                li.querySelector('.slide-href').addEventListener('change', (event) => {
                                    slide.href = event.target.value
                                });
                                li.querySelector('.add-source').addEventListener('click', () => addSource(slide));
                                li.querySelector('.remove-slide').addEventListener('click', () => {
                                    slides.splice(index, 1);
                                    renderSlideRows();
                                });
                            });
                        };

                        // Initialize SortableJS for drag and drop
                        const bindSortable = () => {
                            sortableInstance = new Sortable(slidesList, {
                                animation: 150,
                                handle: '.slide-handle',
                                ghostClass: 'dragging',
                                onEnd: () => {
                                    // Reorder slides array based on new DOM order
                                    const newSlides = [];
                                    for (const item of slidesList.querySelectorAll('.slide-item')) {
                                        const oldIndex = parseInt(item.dataset.index);
                                        newSlides.push(slides[oldIndex]);
                                    }
                                    slides = newSlides;
                                    renderSlideRows();
                                }
                            });
                        };

                        const addSlide = (isInitialAdd = false) => {
                            MediabrowserUtility.openDialog(this.options.browserUrl, null, null, null, {
                                ok: false, // Disable OK button - use Insert File button or double-click instead
                                onClose: (dialog) => {
                                    // Handle the actual image insertion after the dialog closes with returnValue
                                    const image = parseBrowserImage(dialog.returnValue);
                                    if (!image) {
                                        if (dialog.returnValue) {
                                            console.error('Could not parse image from:', dialog.returnValue);
                                        }
                                        return;
                                    }
                                    slides.push({ directiveObj: image.directiveObj, src: image.src, alt: image.alt, sources: [] });
                                    renderSlideRows();
                                },
                                onCancel: () => {
                                    // If this was the initial add and user cancelled, close the slideshow dialog too
                                    if (isInitialAdd && slides.length === 0) {
                                        const slideshowDialog = document.getElementById('slideshow-editor-dialog');
                                        if (slideshowDialog) {
                                            slideshowDialog.close();
                                        }
                                    }
                                }
                            });
                        };

                        // Add slide button handler. The click event must not reach isInitialAdd as a truthy value
                        dialog.querySelector('.add-slide-btn').addEventListener('click', () => addSlide());

                        // Initial render
                        renderSlideRows();
                        bindSortable();

                        // If this is a new slideshow (no slides), automatically open the image browser
                        if (slides.length === 0) {
                            addSlide(true);
                        }
                    },
                    onOk: (dialog) => {
                        if (slides.length === 0) {
                            alert(translate('Please add at least one image to the slideshow'));
                            return false;
                        }

                        // Each variant gets a width in range, and the variants grow by width, because in a
                        // <picture> the first matching <source> wins
                        const normalized = [];
                        const reject = (message, index, sourceIndex) => {
                            alert(translate(message));
                            dialog.querySelector(`#slide-${index}-source-${sourceIndex}-breakpoint`)?.focus();
                            return false;
                        };
                        for (const [index, slide] of slides.entries()) {
                            const sources = [];
                            for (const [sourceIndex, source] of (slide.sources ?? []).entries()) {
                                const width = source.invalid === undefined ? sourceWidth(source, presets) : null;
                                if (width === null) {
                                    return reject('Select a breakpoint from the list, or enter a whole number between 1 and 9999', index, sourceIndex);
                                }
                                if (sources.some((item) => sourceWidth(item, presets) === width)) {
                                    return reject('Two images in a slide need different breakpoints', index, sourceIndex);
                                }
                                sources.push(source.preset
                                    ? { srcset: source.srcset, preset: source.preset, breakpoint: null }
                                    : { srcset: source.srcset, preset: null, breakpoint: width });
                            }
                            normalized.push({ ...slide, sources: sortSources(sources, presets) });
                        }

                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { slides: normalized },
                        });
                        return true;
                    },
                    onClose: () => {
                        sortableInstance?.destroy();
                    },
                });
            }
        };
    },
});

/**
 * MahoDiv Node Extension
 *
 * Preserves div elements with visual highlighting showing class/id info
 */
export const MahoDiv = Node.create({
    name: 'mahoDiv',
    group: 'block',
    content: 'block*',
    defining: true,

    addAttributes() {
        return {
            id: {
                parseHTML: (element) => element.id,
                renderHTML: (attributes) => attributes.id ? { [EDITOR_ID_ATTRIBUTE]: attributes.id } : {},
            },
            classList: {
                parseHTML: (element) => element.classList,
                renderHTML: (attributes) => attributes.classList.length ? { class: attributes.classList } : {},
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'div',
            getAttrs: (element) => {
                // Structured Maho blocks (columns, bento, accordion, widgets) have their own
                // nodes; a class on them must not turn them into a plain div
                if (element.dataset.type?.startsWith('maho-')) {
                    return false;
                }
                // Only capture divs that have an id or class attribute
                if (!element.id && element.classList.length === 0) {
                    return false;
                }
            },
        }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', HTMLAttributes, 0];
    },

    addNodeView() {
        return ({ node, HTMLAttributes }) => {
            const div = document.createElement('div');

            // Apply original attributes
            for (const [key, value] of Object.entries(HTMLAttributes)) {
                div.setAttribute(key, value);
            }

            // Create label for visual identification
            let label = 'div';
            if (node.attrs.id) {
                label += `#${node.attrs.id}`;
            }
            for (const className of node.attrs.classList.values()) {
                label += `.${className}`;
            }

            // Add visual styling attributes
            div.setAttribute('data-div-info', label);
            div.contentEditable = 'true';

            return {
                dom: div,
                contentDOM: div,
            };
        };
    },
});

/**
 * MahoSpan Mark Extension: keeps <span> elements, the inline counterpart of MahoDiv.
 */
export const MahoSpan = Mark.create({
    name: 'mahoSpan',

    // A mark excludes its own type by default, which would collapse nested spans
    excludes: '',

    addAttributes() {
        return {
            class: {
                default: null,
                parseHTML: (element) => element.getAttribute('class') || null,
                renderHTML: (attributes) => attributes.class ? { class: attributes.class } : {},
            },
            style: {
                default: null,
                parseHTML: (element) => element.getAttribute('style') || null,
                renderHTML: (attributes) => attributes.style ? { style: attributes.style } : {},
            },
        };
    },

    parseHTML() {
        return [{
            tag: 'span',
            // ProseMirror tries mark rules before node rules, so without this guard the
            // mark would swallow inline widgets (MahoWidgetInline)
            getAttrs: (element) => element.dataset.type === 'maho-widget' ? false : null,
        }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', HTMLAttributes, 0];
    },
});

/**
 * Maho Fullscreen Extension
 *
 * Provides fullscreen editing capabilities for the Tiptap editor
 */
export const MahoFullscreen = Extension.create({
    name: 'mahoFullscreen',

    addOptions() {
        return {
            enabled: true,
        };
    },

    addStorage() {
        return {
            isFullscreen: false,
        };
    },

    addCommands() {
        return {
            toggleFullscreen: () => ({ editor, commands }) => {
                const wysiwygSetup = editor.options.wysiwygSetup;
                if (!wysiwygSetup) return false;

                const wrapper = wysiwygSetup.wrapper;
                if (!wrapper) return false;

                const isFullscreen = wrapper.classList.contains('tiptap-fullscreen');

                if (isFullscreen) {
                    // Exit fullscreen
                    wysiwygSetup.closePageBuilder?.();
                    wrapper.classList.remove('tiptap-fullscreen');
                    document.body.style.overflow = '';
                    this.storage.isFullscreen = false;
                } else {
                    // Enter fullscreen
                    wrapper.classList.add('tiptap-fullscreen');
                    document.body.style.overflow = 'hidden';
                    this.storage.isFullscreen = true;
                    wysiwygSetup.openPageBuilder?.();
                }

                // Update button state, icon, and title
                const button = wrapper.querySelector('button[data-command="toggleFullscreen"]');
                if (button) {
                    button.classList.toggle('is-active', !isFullscreen);
                    const wysiwygSetup = editor.options.wysiwygSetup;
                    if (!isFullscreen) {
                        // Entering fullscreen - show minimize icon
                        button.innerHTML = wysiwygSetup.getIcon('fullscreen-minimize');
                        button.title = wysiwygSetup.translate('Exit Fullscreen Page Builder');
                    } else {
                        // Exiting fullscreen - show maximize icon
                        button.innerHTML = wysiwygSetup.getIcon('fullscreen-maximize');
                        button.title = wysiwygSetup.translate('Fullscreen Page Builder');
                    }
                }

                // setContent() leaves the selection at the end, so a scroll to it would open the content at its bottom
                editor.commands.focus(null, { scrollIntoView: false });

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        return {
            // A dialog on top of the editor takes its own Escape
            Escape: () => this.storage.isFullscreen && !document.querySelector('dialog[open]')
                && this.editor.commands.toggleFullscreen(),
        };
    },
});
