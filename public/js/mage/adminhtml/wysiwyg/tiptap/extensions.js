/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Editor, Node, Mark, Extension, mergeAttributes } from 'https://esm.sh/@tiptap/core@3.19.0';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@3.19.0';
import Image from 'https://esm.sh/@tiptap/extension-image@3.19.0';
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@3.19.0';
import { Table, TableRow, TableCell, TableHeader } from 'https://esm.sh/@tiptap/extension-table@3.19.0';
import BubbleMenu from 'https://esm.sh/@tiptap/extension-bubble-menu@3.19.0';
import DragHandle from 'https://esm.sh/@tiptap/extension-drag-handle@3.19.0';
import { MahoColumns, MahoColumn, COLUMN_PRESETS } from './extensions/columns.js';

export {
    Editor, Node, Mark, Extension, StarterKit, TextAlign,
    Table, TableRow, TableCell, TableHeader, BubbleMenu, DragHandle,
    MahoColumns, MahoColumn, COLUMN_PRESETS,
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
            for (const match of trimmedAttr.matchAll(/([\w\-]+)="(.*?)"/g)) {
                directiveObj.params[match[1]] = match[2];
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
                    'tableRow', 'tableCell', 'tableHeader', 'table',
                    'mahoColumns', 'mahoColumn',
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
                label = node.attrs.directiveObj.params.block_id;
                // TODO: Add double-click to manage blocks
            }

            dom.innerHTML = editor.options.wysiwygSetup.getIcon(icon ?? 'widget')
                + escapeHtml(label ?? renderDirective(node.attrs.directiveObj));

            if (dblclick) {
                dom.title = editor.options.wysiwygSetup.translate('Double-click to edit');
                dom.addEventListener('dblclick', dblclick);
            }

            return { dom };
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
            const container = document.createElement('div');
            container.className = 'image-container';
            container.title = editor.options.wysiwygSetup.translate('Double-click to edit');

            const img = document.createElement('img');
            container.appendChild(img);

            for (const [key, value] of Object.entries(HTMLAttributes)) {
                if (key === 'src') {
                    img.src = renderDirectiveImageUrl(value, node.attrs.directiveObj, this.options.directivesUrl);
                } else if (value !== null) {
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

            // Create resize handles
            for (const position of ['nw', 'ne', 'sw', 'se']) {
                const handle = document.createElement('div');
                handle.className = `resize-handle resize-handle-${position}`;
                container.appendChild(handle);

                handle.addEventListener('mousedown', (event) => {
                    // Select the image when resizing
                    editor.commands.setNodeSelection(getPos());
                    event.preventDefault();

                    const startX = event.clientX;
                    const startY = event.clientY;
                    const startWidth = img.offsetWidth;
                    const startHeight = img.offsetHeight;
                    const aspectRatio = startWidth / startHeight;

                    const handleMouseMove = (event) => {
                        const deltaX = event.clientX - startX;
                        const deltaY = event.clientY - startY;

                        let newWidth = startWidth + deltaX;
                        let newHeight = startHeight + deltaY;

                        // Maintain aspect ratio
                        if (Math.abs(deltaX) > Math.abs(deltaY)) {
                            newHeight = newWidth / aspectRatio;
                        } else {
                            newWidth = newHeight * aspectRatio;
                        }

                        // Minimum size
                        newWidth = Math.max(50, newWidth);
                        newHeight = Math.max(50, newHeight);

                        img.width = Math.round(newWidth);
                        img.height = Math.round(newHeight);
                    };

                    const handleMouseUp = () => {
                        document.removeEventListener('mousemove', handleMouseMove);
                        document.removeEventListener('mouseup', handleMouseUp);

                        editor.commands.updateAttributes(this.name, {
                            width: img.width,
                            height: img.height,
                        });
                    };

                    document.addEventListener('mousemove', handleMouseMove);
                    document.addEventListener('mouseup', handleMouseUp);
                });
            }

            return {
                dom: container,
            };
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

                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { directiveObj, alt, title, width },
                        });
                    },
                });
            },
        }
    },
});


/**
 * Parse a `<div class="slideshow">` node into an array of slides `[{ src, alt, href }, ...]`
 */
const parseSlides = (div) => {
    // Parse each <li> child and add to our array if it's a valid slide, i.e. contains an image
    const slides = [];
    for (const li of div.querySelectorAll(':scope > ul > li')) {
        const img = li.querySelector('img');
        const link = li.querySelector('a');
        if (img) {
            slides.push({
                src: img.getAttribute('src') ?? '',
                alt: img.getAttribute('alt') ?? '',
                href: link?.getAttribute('href'),
                directiveObj: parseDirective(img.getAttribute('src')),
            });
        }
    }
    return slides;
};

/**
 * Render array of slides into a TipTap node object
 */
const renderSlides = (slides) => {
    return slides.map((slide) => {
        const { src, alt, href } = slide;
        const content = href
              ? ['a', { href }, ['img', { src, alt }]]
              : ['img', { src, alt }];

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
                const slides = parseSlides(div);
                return { slides };
            },
        }];
    },

    renderHTML({ node }) {
        const slides = renderSlides(node.attrs.slides);
        return ['div', { class: 'slideshow' }, ['ul', {}, ...slides]];
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const slides = node.attrs.slides;

            const dom = document.createElement('div');
            dom.dataset.type = 'maho-slideshow';
            dom.contentEditable = 'false';
            dom.title = editor.options.wysiwygSetup.translate('Double-click to edit');

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
                let slides = Array.from(node?.attrs?.slides ?? []);
                let sortableInstance;

                // Create slideshow editor dialog content
                const dialogContent = `
                    <div class="slideshow-editor">
                        <ul class="slides-list"></ul>
                    </div>
                `;
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
                    </div>
                    <button type="button" class="remove-slide" title="Remove slide">
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

                        // Render existing slides
                        const renderSlides = () => {
                            slidesList.innerHTML = '';
                            for (const [index, slide] of Object.entries(slides)) {
                                const li = slidesList.appendChild(document.createElement('li'));
                                li.className = 'slide-item';
                                li.dataset.index = index;
                                li.innerHTML = slideTemplate;
                                li.querySelector('.slide-alt').value = slide.alt ?? '';
                                li.querySelector('.slide-href').value = slide.href ?? '';
                                li.querySelector('.slide-preview img').src =
                                    renderDirectiveImageUrl(slide.src, slide.directiveObj, this.options.directivesUrl);

                                // Attach event listeners
                                li.querySelector('.slide-alt').addEventListener('change', (event) => {
                                    slides[index].alt = event.target.value
                                });
                                li.querySelector('.slide-href').addEventListener('change', (event) => {
                                    slides[index].href = event.target.value
                                });
                                li.querySelector('.remove-slide').addEventListener('click', () => {
                                    slides.splice(index, 1);
                                    renderSlides();
                                });
                            }
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
                                    renderSlides();
                                }
                            });
                        };

                        const addSlide = (isInitialAdd = false) => {
                            MediabrowserUtility.openDialog(this.options.browserUrl, null, null, null, {
                                ok: false, // Disable OK button - use Insert File button or double-click instead
                                onClose: (dialog) => {
                                    // Handle the actual image insertion after the dialog closes with returnValue
                                    const returnValue = dialog.returnValue || '';

                                    if (!returnValue) {
                                        // Dialog was cancelled or no image selected
                                        return;
                                    }

                                    //  Parse out the directive and alt text
                                    let match;
                                    match = returnValue.match(/src="({{.*?}})"/);
                                    const src = match?.[1];
                                    const directiveObj = parseDirective(src);

                                    match = returnValue.match(/alt="(.*?)"/);
                                    const alt = unescapeHtml(match?.[1]);

                                    if (src) {
                                        slides.push({ directiveObj, src, alt });
                                        renderSlides();
                                    } else {
                                        console.error('Could not parse image from:', dialog.returnValue);
                                    }
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

                        // Add slide button handler
                        dialog.querySelector('.add-slide-btn').addEventListener('click', addSlide);

                        // Initial render
                        renderSlides();
                        bindSortable();

                        // If this is a new slideshow (no slides), automatically open the image browser
                        if (slides.length === 0) {
                            addSlide(true);
                        }
                    },
                    onOk: (dialog) => {
                        if (slides.length === 0) {
                            alert(editor.options.wysiwygSetup.translate('Please add at least one image to the slideshow'));
                            return false;
                        }

                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { slides },
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
                renderHTML: (attributes) => attributes.id ? { id: attributes.id } : {},
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
                    wrapper.classList.remove('tiptap-fullscreen');
                    document.body.style.overflow = '';
                    this.storage.isFullscreen = false;
                } else {
                    // Enter fullscreen
                    wrapper.classList.add('tiptap-fullscreen');
                    document.body.style.overflow = 'hidden';
                    this.storage.isFullscreen = true;
                }

                // Update button state, icon, and title
                const button = wrapper.querySelector('button[data-command="toggleFullscreen"]');
                if (button) {
                    button.classList.toggle('is-active', !isFullscreen);
                    const wysiwygSetup = editor.options.wysiwygSetup;
                    if (!isFullscreen) {
                        // Entering fullscreen - show minimize icon
                        button.innerHTML = wysiwygSetup.getIcon('fullscreen-minimize');
                        button.title = wysiwygSetup.translate('Exit Fullscreen');
                    } else {
                        // Exiting fullscreen - show maximize icon
                        button.innerHTML = wysiwygSetup.getIcon('fullscreen-maximize');
                        button.title = wysiwygSetup.translate('Fullscreen');
                    }
                }

                // Focus back to editor
                editor.commands.focus();

                return true;
            },
        };
    },
});
