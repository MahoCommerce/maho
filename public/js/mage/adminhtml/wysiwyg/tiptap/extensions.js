/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

import { Editor, Node, Mark, mergeAttributes } from 'https://esm.sh/@tiptap/core@2.14.0';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.14.0';
import Link from 'https://esm.sh/@tiptap/extension-link@2.14.0';
import Image from 'https://esm.sh/@tiptap/extension-image@2.14.0';
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.14.0';
import Underline from 'https://esm.sh/@tiptap/extension-underline@2.14.0';
import Table from 'https://esm.sh/@tiptap/extension-table@2.14.0';
import TableRow from 'https://esm.sh/@tiptap/extension-table-row@2.14.0';
import TableCell from 'https://esm.sh/@tiptap/extension-table-cell@2.14.0';
import TableHeader from 'https://esm.sh/@tiptap/extension-table-header@2.14.0';
import BubbleMenu from 'https://esm.sh/@tiptap/extension-bubble-menu@2.14.0';


const parseDirective = (directiveStr) => {
    const directiveObj = {
        type: null,
        params: {},
    }
    directiveStr = (directiveStr ?? '').trim();
    if (directiveStr.startsWith('{{') && directiveStr.endsWith('}}')) {
        const [ type, attrStr ] = directiveStr.slice(2, -2).trim().split(' ', 2);
        directiveObj.type = type;
        for (const match of (attrStr ?? '').matchAll(/([\w\-]+)="(.*?)"/g)) {
            directiveObj.params[match[1]] = match[2];
        }
    }
    return directiveObj;
};

const renderDirective = (directiveObj) => {
    if (!directiveObj?.type) {
        return '';
    }
    let directiveStr = '{{' + directiveObj.type;
    for (const [name, value] of Object.entries(directiveObj.params)) {
        if (value) {
            directiveStr += ` ${name}="${value}"`;
        } else {
            directiveStr += ` ${name}`;
        }
    }
    directiveStr += '}}';
    return directiveStr
};

/**
 * Maho Widget Node View Extension
 *
 * This extension adds widget and variable support
 */
const MahoWidget = Node.create({
    name: 'mahoWidget',
    group: 'inline',
    inline: true,
    draggable: true,
    atom: true,

    addAttributes() {
        return {
            directiveObj: {
                parseHTML: (element) => {
                    console.log(element.getAttribute('data-directive'))
                    console.log(JSON.stringify(parseDirective(element.getAttribute('data-directive'))))
                    return parseDirective(element.getAttribute('data-directive'));
                },
                rendered: false,
            },
        }
    },

    parseHTML() {
        return [{
            tag: 'span[data-type=maho-widget]',
        }];
    },

    renderHTML({ node }) {
        const directiveStr = renderDirective(node.attrs.directiveObj);
        return ['span', { 'data-type': 'maho-widget', 'data-directive': directiveStr }];
    },

    addNodeView() {
        return ({ node, editor }) => {
            const dom = document.createElement('span');
            dom.dataset.type = 'maho-widget';
            dom.contentEditable = 'false';

            let icon, label, dblclick;

            if (node.attrs.directiveObj.type === 'var') {
                icon = 'variable';
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

                widgetTools.openDialog(this.options.widgetUrl, {
                    onOpen: () => {
                        widgetTools.initOptionValues(node?.attrs.directiveObj.params);
                    },
                    onOk: (dialog) => {
                        const directiveObj = parseDirective(dialog.returnValue);
                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { directiveObj },
                        });
                    },
                });
            },
            insertMahoVariable: (node) => ({ editor, state }) => {
                const { from, to } = state.selection;

                Variables.openDialog(this.options.variableUrl, {
                    onOpen: () => {
                        Variables.initSelected(renderDirective(node?.attrs.directiveObj));
                    },
                    onOk: (dialog) => {
                        const directiveObj = parseDirective(dialog.returnValue);
                        editor.commands.insertContentAt({ from, to }, {
                            type: this.name,
                            attrs: { directiveObj },
                        });
                    },
                });
            },
        }
    },
});

/**
 * Maho Image Node View Extension
 *
 * This extension adds media browser and resize support
 */
const MahoImage = Image.extend({
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
                if (key === 'src' && node.attrs.directiveObj.type) {
                    img.src = setRouteParams(this.options.directivesUrl, {
                        ___directive: Base64.mageEncode(renderDirective(node.attrs.directiveObj)),
                    });
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
                    params.path = Base64.idEncode(parts.slice(1, -1).join('/'));
                    params.filename = Base64.idEncode(parts.pop());
                }
                if (node?.attrs.alt) {
                    params.alt = Base64.mageEncode(node.attrs.alt);
                }

                const url = setRouteParams(this.options.browserUrl, params);
                MediabrowserUtility.openDialog(url, null, null, this.options.title, {
                    onOk: (dialog) => {
                        //  Parse out the directive and alt text
                        let match;

                        match = dialog.returnValue.match(/src="({{.*?}})"/);
                        const directiveObj = parseDirective(match?.[1]);

                        match = dialog.returnValue.match(/alt="(.*?)"/);
                        const alt = match?.[1];

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

export default {
    Editor, Node, Mark, StarterKit, Link, TextAlign, Underline,
    Table, TableRow, TableCell, TableHeader, BubbleMenu,
    MahoWidget, MahoImage,
};
