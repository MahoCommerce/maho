/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class tiptapWysiwygSetup {

    mediaBrowserCallback = null;
    mediaBrowserMetal = null;
    mediaBrowserValue = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(htmlId, config) {
        this.id = htmlId;
        this.selector = `#${htmlId}`;
        this.config = config;
        this.editor = null;

        if (typeof tiptapEditors === 'undefined') {
            window.tiptapEditors = new Map();
        }
        tiptapEditors.set(this.id, this);

        this.bindEventListeners();
        if (!config.hidden) {
            this.setup();
        }
    }

    bindEventListeners() {
        this.getToggleButton()?.addEventListener('click', this.toggle.bind(this));

        this.onFormValidation = this.onFormValidation.bind(this);
        varienGlobalEvents.attachEventHandler('formSubmit', this.onFormValidation);

        varienGlobalEvents.clearEventHandlers('open_browser_callback');
        varienGlobalEvents.attachEventHandler('open_browser_callback', this.openFileBrowser.bind(this));
    }

    waitForTiptapModules() {
        return new Promise((resolve) => {
            if (window.TiptapModules) {
                resolve();
            } else {
                window.addEventListener('tiptap-modules-loaded', resolve, { once: true });
            }
        });
    }

    unbindEventListeners() {
        varienGlobalEvents.removeEventHandler('formSubmit', this.onFormValidation);
    }

    destroy() {
        this.unbindEventListeners();
        if (this.editor) {
            // Save content before destroying
            this.updateTextArea();
            
            // Destroy the Tiptap instance
            this.editor.destroy();
            this.editor = null;
        }
        
        // Remove the wrapper which contains everything
        const wrapper = document.getElementById(`${this.id}_wrapper`);
        if (wrapper) {
            wrapper.remove();
        }
    }

    createToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'tiptap-toolbar';
        toolbar.id = `${this.id}_toolbar`;

        // Heading dropdown
        const headingGroup = document.createElement('div');
        headingGroup.className = 'toolbar-group';
        
        const headingSelect = document.createElement('select');
        headingSelect.innerHTML = `
            <option value="">Paragraph</option>
            <option value="1">Heading 1</option>
            <option value="2">Heading 2</option>
            <option value="3">Heading 3</option>
            <option value="4">Heading 4</option>
            <option value="5">Heading 5</option>
        `;
        headingSelect.addEventListener('change', (e) => {
            const level = e.target.value;
            if (level) {
                this.editor.chain().focus().toggleHeading({ level: parseInt(level) }).run();
            } else {
                this.editor.chain().focus().setParagraph().run();
            }
        });
        headingGroup.appendChild(headingSelect);
        toolbar.appendChild(headingGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // Text formatting buttons
        const formatGroup = document.createElement('div');
        formatGroup.className = 'toolbar-group';
        
        const boldBtn = this.createToolbarButton('Bold', this.getIcon('bold'), () => {
            this.editor.chain().focus().toggleBold().run();
        }, 'bold');
        formatGroup.appendChild(boldBtn);

        const italicBtn = this.createToolbarButton('Italic', this.getIcon('italic'), () => {
            this.editor.chain().focus().toggleItalic().run();
        }, 'italic');
        formatGroup.appendChild(italicBtn);

        const underlineBtn = this.createToolbarButton('Underline', this.getIcon('underline'), () => {
            this.editor.chain().focus().toggleUnderline().run();
        }, 'underline');
        formatGroup.appendChild(underlineBtn);

        const strikeBtn = this.createToolbarButton('Strike', this.getIcon('strike'), () => {
            this.editor.chain().focus().toggleStrike().run();
        }, 'strike');
        formatGroup.appendChild(strikeBtn);

        const blockquoteBtn = this.createToolbarButton('Blockquote', this.getIcon('blockquote'), () => {
            this.editor.chain().focus().toggleBlockquote().run();
        }, 'blockquote');
        formatGroup.appendChild(blockquoteBtn);

        toolbar.appendChild(formatGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // List buttons
        const listGroup = document.createElement('div');
        listGroup.className = 'toolbar-group';

        const bulletListBtn = this.createToolbarButton('Bullet List', this.getIcon('bullet-list'), () => {
            this.editor.chain().focus().toggleBulletList().run();
        }, 'bulletList');
        listGroup.appendChild(bulletListBtn);

        const orderedListBtn = this.createToolbarButton('Ordered List', this.getIcon('ordered-list'), () => {
            this.editor.chain().focus().toggleOrderedList().run();
        }, 'orderedList');
        listGroup.appendChild(orderedListBtn);

        toolbar.appendChild(listGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // Alignment buttons
        const alignGroup = document.createElement('div');
        alignGroup.className = 'toolbar-group';

        const alignLeftBtn = this.createToolbarButton('Align Left', this.getIcon('align-left'), () => {
            this.editor.chain().focus().setTextAlign('left').run();
        });
        alignGroup.appendChild(alignLeftBtn);

        const alignCenterBtn = this.createToolbarButton('Align Center', this.getIcon('align-center'), () => {
            this.editor.chain().focus().setTextAlign('center').run();
        });
        alignGroup.appendChild(alignCenterBtn);

        const alignRightBtn = this.createToolbarButton('Align Right', this.getIcon('align-right'), () => {
            this.editor.chain().focus().setTextAlign('right').run();
        });
        alignGroup.appendChild(alignRightBtn);

        toolbar.appendChild(alignGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // Insert buttons
        const insertGroup = document.createElement('div');
        insertGroup.className = 'toolbar-group';

        const linkBtn = this.createToolbarButton('Link', this.getIcon('link'), () => {
            this.linkHandler();
        });
        insertGroup.appendChild(linkBtn);

        const imageBtn = this.createToolbarButton('Image', this.getIcon('image'), () => {
            this.imageHandler();
        });
        insertGroup.appendChild(imageBtn);

        // Simple Insert Table button
        const insertTableBtn = this.createToolbarButton('Insert Table', this.getIcon('table'), () => {
            this.editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
        });
        insertGroup.appendChild(insertTableBtn);

        const widgetBtn = this.createToolbarButton('Insert Widget', this.getIcon('widget'), () => {
            this.widgetHandler();
        });
        insertGroup.appendChild(widgetBtn);

        const variableBtn = this.createToolbarButton('Insert Variable', this.getIcon('variable'), () => {
            this.variableHandler();
        });
        insertGroup.appendChild(variableBtn);

        toolbar.appendChild(insertGroup);

        return toolbar;
    }

    createSeparator() {
        const separator = document.createElement('div');
        separator.className = 'toolbar-separator';
        return separator;
    }

    createTableBubbleMenu() {
        const bubbleMenu = document.createElement('div');
        bubbleMenu.className = 'table-bubble-menu';
        bubbleMenu.id = `${this.id}_table_bubble_menu`;

        const tableCommands = [
            { label: 'Add Column Before', command: 'addColumnBefore', icon: this.getIcon('column-insert-left') },
            { label: 'Add Column After', command: 'addColumnAfter', icon: this.getIcon('column-insert-right') },
            { label: 'Delete Column', command: 'deleteColumn', icon: this.getIcon('column-remove') },
            { separator: true },
            { label: 'Add Row Before', command: 'addRowBefore', icon: this.getIcon('row-insert-top') },
            { label: 'Add Row After', command: 'addRowAfter', icon: this.getIcon('row-insert-bottom') },
            { label: 'Delete Row', command: 'deleteRow', icon: this.getIcon('row-remove') },
            { separator: true },
            { label: 'Merge Cells', command: 'mergeCells', icon: this.getIcon('arrows-join') },
            { label: 'Split Cell', command: 'splitCell', icon: this.getIcon('arrows-split') },
            { separator: true },
            { label: 'Toggle Header Column', command: 'toggleHeaderColumn', icon: this.getIcon('table-column') },
            { label: 'Toggle Header Row', command: 'toggleHeaderRow', icon: this.getIcon('table-row') },
            { separator: true },
            { label: 'Delete Table', command: 'deleteTable', icon: this.getIcon('trash') }
        ];

        tableCommands.forEach(item => {
            if (item.separator) {
                const separator = document.createElement('div');
                separator.className = 'table-bubble-separator';
                bubbleMenu.appendChild(separator);
            } else {
                const menuItem = document.createElement('button');
                menuItem.type = 'button';
                menuItem.innerHTML = item.icon;
                menuItem.title = item.label;
                menuItem.className = 'table-menu-item';
                menuItem.onmouseover = () => {
                    menuItem.style.backgroundColor = '#f1f5f9';
                    menuItem.style.color = '#1e293b';
                };
                menuItem.onmouseout = () => {
                    menuItem.style.backgroundColor = 'transparent';
                    menuItem.style.color = '#4b5563';
                };
                menuItem.onclick = () => {
                    if (this.editor) {
                        this.editor.chain().focus()[item.command]().run();
                    }
                };
                bubbleMenu.appendChild(menuItem);
            }
        });

        return bubbleMenu;
    }

    createToolbarButton(title, innerHTML, onClick, commandName) {
        const button = document.createElement('button');
        button.type = 'button';
        button.title = title;
        button.innerHTML = innerHTML;
        button.addEventListener('click', onClick);
        
        if (commandName) {
            button.dataset.command = commandName;
        }
        
        return button;
    }

    updateToolbarState() {
        if (!this.editor) return;

        // Update heading dropdown
        const headingSelect = document.querySelector(`#${this.id}_toolbar select`);
        if (headingSelect) {
            if (this.editor.isActive('heading', { level: 1 })) headingSelect.value = '1';
            else if (this.editor.isActive('heading', { level: 2 })) headingSelect.value = '2';
            else if (this.editor.isActive('heading', { level: 3 })) headingSelect.value = '3';
            else if (this.editor.isActive('heading', { level: 4 })) headingSelect.value = '4';
            else if (this.editor.isActive('heading', { level: 5 })) headingSelect.value = '5';
            else headingSelect.value = '';
        }

        // Update button states
        const toolbar = document.getElementById(`${this.id}_toolbar`);
        if (toolbar) {
            toolbar.querySelectorAll('button[data-command]').forEach(button => {
                const command = button.dataset.command;
                if (this.editor.isActive(command)) {
                    button.classList.add('is-active');
                } else {
                    button.classList.remove('is-active');
                }
            });
        }
    }

    async setup() {
        // Wait for Tiptap modules to be loaded
        await this.waitForTiptapModules();
        
        if (!window.TiptapModules) {
            console.error('Tiptap modules not loaded');
            return;
        }
        // Create wrapper container for Tiptap editor
        const textarea = this.getTextArea();
        const wrapper = document.createElement('div');
        wrapper.id = `${this.id}_wrapper`;
        wrapper.className = 'tiptap-wrapper';
        
        // Create and add toolbar
        const toolbar = this.createToolbar();
        wrapper.appendChild(toolbar);
        
        // Create container for Tiptap editor content
        const container = document.createElement('div');
        container.id = `${this.id}_editor`;
        wrapper.appendChild(container);
        
        // Insert wrapper after textarea
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

        // Get initial content
        let initialContent = this.getTextArea().value;

        // Convert media directives in img src to directive URLs
        initialContent = initialContent.replace(/<img([^>]*)>/gi, (match, attributes) => {
            // Check if this img tag has a media directive in the src
            const srcMatch = attributes.match(/\bsrc="(\{\{media\s+url="([^"]+)"\}\})"/i);
            if (srcMatch) {
                const fullDirective = srcMatch[1];
                const url = srcMatch[2];
                const directive = `{{media url="${url}"}}`;
                const encodedDirective = Base64.mageEncode(directive);
                const directiveUrl = this.makeDirectiveUrl(encodedDirective);
                
                // Replace only the src attribute value, preserving all other attributes
                const updatedAttributes = attributes.replace(/\bsrc="[^"]+"/i, `src="${directiveUrl}"`);
                return `<img${updatedAttributes}>`;
            }
            return match;
        });

        // Process content for widgets
        initialContent = this.encodeContent(initialContent);

        // Define custom image extension with additional attributes and resize support
        const CustomImage = window.TiptapModules.Image.extend({
            addAttributes() {
                return {
                    src: { default: null },
                    alt: { default: null },
                    title: { default: null },
                    width: { default: null },
                    height: { default: null },
                    id: { default: null },
                    class: { default: null },
                    'data-original': { default: null },
                    'data-width': { default: null },
                    'data-height': { default: null },
                    'style-data': { default: null }
                };
            },

            addCommands() {
                return {
                    ...this.parent?.(),
                    updateImageAttributes: (attributes) => ({ tr, state }) => {
                        const { selection } = state
                        const { from } = selection
                        const node = state.doc.nodeAt(from)
                        
                        if (node && node.type.name === 'image') {
                            tr.setNodeMarkup(from, undefined, {
                                ...node.attrs,
                                ...attributes
                            })
                            return true
                        }
                        return false
                    }
                }
            },
            
            addNodeView() {
                return ({ node, updateAttributes, editor }) => {
                    const container = document.createElement('div');
                    container.style.position = 'relative';
                    container.style.display = 'inline-block';
                    container.className = 'image-container';
                    
                    const img = document.createElement('img');
                    Object.entries(node.attrs).forEach(([key, value]) => {
                        if (value !== null) {
                            img.setAttribute(key, value);
                        }
                    });
                    
                    // Store update function reference
                    const updateImageAttributes = (attrs) => {
                        // First try the built-in updateAttributes if available
                        if (updateAttributes && typeof updateAttributes === 'function') {
                            updateAttributes(attrs);
                            return;
                        }
                        
                        // Fallback to command system
                        try {
                            const pos = editor.view.posAtDOM(img, 0);
                            if (pos >= 0) {
                                editor.chain().focus().command(({ tr, state }) => {
                                    const nodeAt = state.doc.nodeAt(pos);
                                    if (nodeAt && nodeAt.type.name === 'image') {
                                        tr.setNodeMarkup(pos, undefined, {
                                            ...nodeAt.attrs,
                                            ...attrs
                                        });
                                        return true;
                                    }
                                    return false;
                                }).run();
                            }
                        } catch (e) {
                            console.warn('Failed to update image attributes:', e);
                        }
                    };
                    
                    // Create resize handles
                    const createResizeHandle = (position) => {
                        const handle = document.createElement('div');
                        handle.className = `resize-handle resize-handle-${position}`;
                        handle.style.cssText = `
                            position: absolute;
                            width: 10px;
                            height: 10px;
                            background: #3b82f6;
                            border: 1px solid white;
                            cursor: ${position}-resize;
                            display: none;
                        `;
                        
                        // Position handles
                        switch(position) {
                            case 'nw': handle.style.top = '-5px'; handle.style.left = '-5px'; break;
                            case 'ne': handle.style.top = '-5px'; handle.style.right = '-5px'; break;
                            case 'sw': handle.style.bottom = '-5px'; handle.style.left = '-5px'; break;
                            case 'se': handle.style.bottom = '-5px'; handle.style.right = '-5px'; break;
                        }
                        
                        return handle;
                    };
                    
                    const handles = ['nw', 'ne', 'sw', 'se'].map(createResizeHandle);
                    
                    container.appendChild(img);
                    handles.forEach(handle => container.appendChild(handle));
                    
                    // Show/hide handles on hover
                    container.addEventListener('mouseenter', () => {
                        handles.forEach(h => h.style.display = 'block');
                    });
                    container.addEventListener('mouseleave', () => {
                        handles.forEach(h => h.style.display = 'none');
                    });
                    
                    // Add double-click handler
                    img.addEventListener('dblclick', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Check if this is a widget placeholder
                        if (node.attrs.class && node.attrs.class.includes('maho-widget-placeholder')) {
                            // This is a widget placeholder - open widget editor
                            if (node.attrs.id && editor.storage.wysiwygSetup) {
                                editor.storage.wysiwygSetup.openWidgetForEdit(node.attrs.id);
                            }
                        } else {
                            // Regular image - edit alt text
                            const currentAlt = node.attrs.alt || '';
                            const newAlt = window.prompt('Alternative text:', currentAlt);
                            
                            if (newAlt !== null) {
                                updateImageAttributes({ alt: newAlt });
                            }
                        }
                    });
                    
                    // Handle resizing
                    handles.forEach(handle => {
                        handle.addEventListener('mousedown', (e) => {
                            e.preventDefault();
                            const startX = e.clientX;
                            const startY = e.clientY;
                            const startWidth = img.offsetWidth;
                            const startHeight = img.offsetHeight;
                            const aspectRatio = startWidth / startHeight;
                            
                            const handleMouseMove = (e) => {
                                const deltaX = e.clientX - startX;
                                const deltaY = e.clientY - startY;
                                
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
                                
                                img.style.width = newWidth + 'px';
                                img.style.height = newHeight + 'px';
                            };
                            
                            const handleMouseUp = () => {
                                document.removeEventListener('mousemove', handleMouseMove);
                                document.removeEventListener('mouseup', handleMouseUp);
                                
                                // Update node attributes
                                updateImageAttributes({
                                    width: Math.round(img.offsetWidth),
                                    height: Math.round(img.offsetHeight)
                                });
                            };
                            
                            document.addEventListener('mousemove', handleMouseMove);
                            document.addEventListener('mouseup', handleMouseUp);
                        });
                    });
                    
                    return {
                        dom: container,
                        contentDOM: null,
                        update: (updatedNode) => {
                            if (updatedNode.type.name !== 'image') return false;
                            
                            Object.entries(updatedNode.attrs).forEach(([key, value]) => {
                                if (value !== null && key !== 'width' && key !== 'height') {
                                    img.setAttribute(key, value);
                                }
                            });
                            
                            if (updatedNode.attrs.width) {
                                img.style.width = updatedNode.attrs.width + 'px';
                            }
                            if (updatedNode.attrs.height) {
                                img.style.height = updatedNode.attrs.height + 'px';
                            }
                            
                            return true;
                        }
                    };
                };
            }
        });

        // Store reference to this instance for use in Widget extension
        const setupInstance = this;
        
        // Define custom Widget node for widget placeholders
        const Widget = window.TiptapModules.Node.create({
            name: 'widget',
            
            group: 'inline',
            inline: true,
            atom: true,
            
            addAttributes() {
                return {
                    id: { default: null },
                    src: { default: null },
                    title: { default: null },
                    class: { default: 'maho-widget-placeholder' }
                };
            },
            
            parseHTML() {
                return [{
                    tag: 'img.maho-widget-placeholder',
                }];
            },
            
            renderHTML({ HTMLAttributes }) {
                return ['img', window.TiptapModules.mergeAttributes(HTMLAttributes)];
            },
            
            addNodeView() {
                return ({ node }) => {
                    const dom = document.createElement('img');
                    dom.setAttribute('id', node.attrs.id);
                    dom.setAttribute('src', node.attrs.src);
                    dom.setAttribute('title', node.attrs.title);
                    dom.setAttribute('class', node.attrs.class);
                    
                    // Add double-click handler
                    dom.addEventListener('dblclick', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        if (node.attrs.id && setupInstance) {
                            setupInstance.openWidgetForEdit(node.attrs.id);
                        }
                    });
                    
                    return { dom };
                };
            }
        });

        // Initialize Tiptap
        // Create table bubble menu
        const tableBubbleMenu = this.createTableBubbleMenu();
        wrapper.appendChild(tableBubbleMenu);

        this.editor = new window.TiptapModules.Editor({
            element: container,
            extensions: [
                window.TiptapModules.StarterKit.configure({
                    heading: {
                        levels: [1, 2, 3, 4, 5]
                    }
                }),
                CustomImage,
                Widget,
                window.TiptapModules.Link.configure({
                    openOnClick: false,
                }),
                window.TiptapModules.Table.configure({
                    resizable: true,
                }),
                window.TiptapModules.TableRow,
                window.TiptapModules.TableCell,
                window.TiptapModules.TableHeader,
                window.TiptapModules.TextAlign.configure({
                    types: ['heading', 'paragraph'],
                }),
                window.TiptapModules.Underline,
                window.TiptapModules.BubbleMenu.configure({
                    element: tableBubbleMenu,
                    shouldShow: ({ editor, state }) => {
                        return editor.isActive('tableCell') || editor.isActive('tableHeader');
                    },
                    tippyOptions: {
                        placement: 'top',
                        arrow: false,
                        animation: 'fade',
                        duration: 150,
                        offset: [0, 8],
                    },
                }),
            ],
            content: initialContent,
            onCreate: ({ editor }) => {
                // Store reference to this setup instance for custom nodes
                editor.storage.wysiwygSetup = this;
            },
            onUpdate: ({ editor }) => {
                this.updateTextArea();
                this.onChangeContent();
            },
            onSelectionUpdate: ({ editor }) => {
                this.updateToolbarState();
            }
        });

        // Update toolbar state initially
        this.updateToolbarState();

        // Fire initialization event
        varienGlobalEvents.fireEvent('wysiwygEditorInitialized', this.editor);
    }

    linkHandler() {
        const previousUrl = this.editor.getAttributes('link').href;
        const url = window.prompt('URL', previousUrl);

        if (url === null) {
            return;
        }

        if (url === '') {
            this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }

    imageHandler() {
        if (this.config.files_browser_window_url) {
            // Store the current selection/cursor position before opening dialog
            const { from } = this.editor.state.selection;
            
            varienGlobalEvents.fireEvent("open_browser_callback", { 
                callback: (url) => {
                    // Insert the image at the stored position
                    this.editor.chain().focus().insertContentAt(from, {
                        type: 'image',
                        attrs: { src: url }
                    }).run();
                },
                value: '',
                meta: { filetype: 'image' }
            });
        }
    }


    widgetHandler() {
        if (this.config.widget_window_url) {
            // Clear any editing state when inserting a new widget
            this.editingWidgetId = null;
            delete window.widgetFormInitialValues;
            
            const url = this.config.widget_window_url + 'widget_target_id/' + this.id + '/';
            widgetTools.openDialog(url);
        }
    }


    openWidgetForEdit(widgetId) {
        if (!this.config.widget_window_url || !widgetId) {
            return;
        }

        // Decode the widget content from the ID
        const widgetCode = Base64.idDecode(widgetId);
        
        // Parse the widget code to extract parameters
        const widgetParams = this.parseWidgetCode(widgetCode);
        
        if (widgetParams && widgetParams.type) {
            // Store the widget element for later replacement
            this.editingWidgetId = widgetId;
            
            // Store widget parameters globally for the widget form to access
            window.widgetFormInitialValues = widgetParams;
            
            // Open the widget dialog
            const url = this.config.widget_window_url + 'widget_target_id/' + this.id + '/';
            widgetTools.openDialog(url);
        }
    }

    parseWidgetCode(widgetCode) {
        // Extract widget parameters from the widget directive
        const match = widgetCode.match(/\{\{widget\s+(.+?)\}\}/);
        if (!match) return null;
        
        const params = {};
        const paramString = match[1];
        
        // Parse key="value" pairs
        const regex = /(\w+)="([^"]*)"/g;
        let paramMatch;
        
        while ((paramMatch = regex.exec(paramString)) !== null) {
            params[paramMatch[1]] = paramMatch[2];
        }
        
        return params;
    }

    variableHandler() {
        if (this.config.variable_window_url) {
            // Store cursor position before opening dialog
            this.lastSelection = this.editor.state.selection;
            const url = this.config.variable_window_url + 'variable_target_id/' + this.id + '/';
            OpenmagevariablePlugin.loadChooser(url, this.id);
        }
    }

    openFileBrowser(o) {
        var typeTitle;
        var storeId = this.config.store_id !== null ? this.config.store_id : 0;
        var wUrl = this.config.files_browser_window_url +
            'target_element_id/' + this.id + '/' +
            'store/' + storeId + '/';

        this.mediaBrowserCallback = o.callback;
        this.mediaBrowserMeta = o.meta;
        this.mediaBrowserValue = o.value;

        if (typeof (o.meta.filetype) != 'undefined' && o.meta.filetype == "image") {
            typeTitle = 'image' == o.meta.filetype ? this.translate('Insert Image...') : this.translate('Insert Media...');
            wUrl = wUrl + "type/" + o.meta.filetype + "/";
        } else {
            typeTitle = this.translate('Insert File...');
        }

        MediabrowserUtility.openDialog(wUrl, false, false, typeTitle, {
            onBeforeShow: function (win) {
                win.element.setStyle({ zIndex: 300200 });
            }
        });
    }

    translate(string) {
        return typeof Translator !== 'undefined' ? Translator.translate(string) : string;
    }

    getToggleButton() {
        return document.getElementById(`toggle${this.id}`);
    }

    getPluginButtons() {
        return document.querySelectorAll(`#buttons${this.id} > button.plugin`);
    }

    getContainer() {
        return document.getElementById(`${this.id}_editor`);
    }

    getTextArea() {
        return document.getElementById(this.id);
    }

    turnOn() {
        this.setup();
        this.getPluginButtons().forEach((el) => el.classList.add('no-display'));
    }

    turnOff() {
        this.destroy();
        this.getTextArea().style.display = '';
        this.getPluginButtons().forEach((el) => el.classList.remove('no-display'));
    }

    toggle() {
        if (this.editor === null) {
            this.turnOn();
            return true;
        } else {
            this.turnOff();
            return false;
        }
    }

    onFormValidation() {
        if (this.editor) {
            this.updateTextArea();
        }
    }

    onChangeContent() {
        if (this.config.tab_id) {
            const tab = document.querySelector(`a[id$=${this.config.tab_id}]`);
            if (tab && tab.classList.contains('tab-item-link')) {
                tab.classList.add('changed');
            }
        }
    }

    updateTextArea() {
        if (this.editor) {
            const textarea = this.getTextArea();
            if (textarea) {
                const content = this.decodeContent(this.editor.getHTML());
                textarea.value = content;
                this.triggerChange(textarea);
            }
        }
    }

    triggerChange(element) {
        element.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        return element;
    }

    encodeContent(content) {
        if (!content) return '';
        // Only encode widgets
        return this.encodeWidgets(content);
    }

    decodeContent(content) {
        if (!content) return '';
        return this.decodeDirectives(this.decodeWidgets(content));
    }

    makeDirectiveUrl(directive) {
        return this.config.directives_url.replace('directive', 'directive/___directive/' + directive);
    }

    encodeDirectives(content) {
        return content.replace(/<([a-z0-9\-\_]+.+?)([a-z0-9\-\_]+=".*?\{\{.+?\}\}.*?".+?)>/gi, (match, p1, p2) => {
            const attributesString = p2.replace(/([a-z0-9\-\_]+)="(.*?)(\{\{.+?\}\})(.*?)"/gi, (m, attr, before, directive, after) => {
                return attr + '="' + before + this.makeDirectiveUrl(Base64.mageEncode(directive)) + after + '"';
            });
            return '<' + p1 + attributesString + '>';
        });
    }

    encodeWidgets(content) {
        return content.replace(/\{\{widget(.*?)\}\}/gi, (match, attributes) => {
            const attrs = this.parseAttributesString(attributes);
            if (attrs.type) {
                let placeholderFilename = attrs.type.replace(/\//g, "__") + ".svg";
                if (!this.widgetPlaceholderExist(placeholderFilename)) {
                    placeholderFilename = 'default.svg';
                }
                const attributesObj = {
                    id: Base64.idEncode(match),
                    src: this.config.widget_images_url + placeholderFilename,
                    title: 'Double-click to edit: ' + match.replace(/\{\{/g, '{').replace(/\}\}/g, '}').replace(/\"/g, '&quot;'),
                    class: 'maho-widget-placeholder'
                };
                const attributesString = Object.entries(attributesObj)
                      .map(([key, value]) => `${key}="${value}"`)
                      .join(' ');

                return `<img ${attributesString}>`;
            }
            return match;
        });
    }

    decodeDirectives(content) {
        // First decode directive URLs in img src attributes
        content = content.replace(/<img([^>]*)>/gi, (match, attributes) => {
            // Parse the complete attributes string to handle the src attribute properly
            const srcMatch = attributes.match(/\bsrc="([^"]+)"/i);
            if (!srcMatch) return match;
            
            const src = srcMatch[1];
            const urlPattern = this.config.directives_url
                .replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1')
                .replace('directive', 'directive/___directive/([a-zA-Z0-9,_-]+)(?:/key/[a-zA-Z0-9]+/?)?');
            
            // Check if the src contains a directive URL
            if (src.match(new RegExp(urlPattern))) {
                const decodedSrc = src.replace(new RegExp(urlPattern, 'g'), (m, directive) => {
                    return Base64.mageDecode(directive);
                });
                // Replace only the src attribute value, preserving all other attributes
                const updatedAttributes = attributes.replace(/\bsrc="[^"]+"/i, `src="${decodedSrc}"`);
                return `<img${updatedAttributes}>`;
            }
            return match;
        });
        
        // Then decode directive URLs in other contexts (but NOT media directives)
        return content.replace(/<([a-z0-9\-\_]+[^>]*?)>/gi, (match) => {
            // Skip img tags as we already handled them
            if (match.toLowerCase().startsWith('<img')) {
                return match;
            }
            
            const urlPattern = this.config.directives_url
                .replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1')
                .replace('directive', 'directive/___directive/([a-zA-Z0-9,_-]+)(?:/key/[a-zA-Z0-9]+/?)?');
            const reg = new RegExp(urlPattern, 'g');
            
            return match.replace(reg, (m, directive) => {
                const decoded = Base64.mageDecode(directive);
                // Only decode if it's NOT a media directive
                if (!decoded.includes('{{media url=')) {
                    return decoded;
                }
                return m; // Keep the directive URL if it's a media directive
            });
        });
    }

    decodeWidgets(content) {
        return content.replace(/<img([^>]+id=\"[^>]+)>/gi, (match, attributes) => {
            const attrs = this.parseAttributesString(attributes);
            if (attrs.id) {
                const widgetCode = Base64.idDecode(attrs.id);
                if (widgetCode.indexOf('{{widget') !== -1) {
                    return widgetCode;
                }
            }
            return match;
        });
    }

    parseAttributesString(attributes) {
        // Create a temporary element with unique ID
        const tempElement = document.createElement('div');
        tempElement.innerHTML = `<div ${attributes}></div>`;

        // Add to DOM temporarily (some browsers need this for full parsing)
        document.body.appendChild(tempElement);

        const element = tempElement.firstChild;
        const result = {};

        // Extract all attributes
        for (const attr of element.attributes) {
            result[attr.name] = attr.value;
        }

        // Clean up - remove the temporary element
        document.body.removeChild(tempElement);
        return result;
    }

    widgetPlaceholderExist(filename) {
        return this.config.widget_placeholders && this.config.widget_placeholders.indexOf(filename) !== -1;
    }

    getMediaBrowserCallback() {
        return this.mediaBrowserCallback;
    }

    // Method to insert content at cursor position (for widgets/variables)
    insertContent(content) {
        if (this.editor) {
            // Check if we're replacing an existing widget
            if (this.editingWidgetId) {
                // Find all widget nodes
                let targetPos = null;
                this.editor.state.doc.descendants((node, pos) => {
                    if (node.type.name === 'widget' && node.attrs.id === this.editingWidgetId) {
                        targetPos = pos;
                        return false; // Stop searching
                    }
                });
                
                if (targetPos !== null) {
                    // Process the new content to get widget attributes
                    const processedContent = this.encodeContent(content);
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = processedContent;
                    const newWidget = tempDiv.querySelector('img.maho-widget-placeholder');
                    
                    if (newWidget) {
                        // Replace the existing widget with new widget node
                        this.editor.chain()
                            .focus()
                            .setNodeSelection(targetPos)
                            .deleteSelection()
                            .insertContent({
                                type: 'widget',
                                attrs: {
                                    id: newWidget.getAttribute('id'),
                                    src: newWidget.getAttribute('src'),
                                    title: newWidget.getAttribute('title'),
                                    class: newWidget.getAttribute('class')
                                }
                            })
                            .run();
                    }
                    
                    // Clear the editing widget ID
                    this.editingWidgetId = null;
                    return;
                }
            }
            
            // Normal insertion at cursor position
            if (content.includes('{{widget') && content.includes('}}')) {
                // This is a widget, process it first
                const processedContent = this.encodeContent(content);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = processedContent;
                const widgetImg = tempDiv.querySelector('img.maho-widget-placeholder');
                
                if (widgetImg) {
                    // Insert as widget node
                    this.editor.chain().focus().insertContent({
                        type: 'widget',
                        attrs: {
                            id: widgetImg.getAttribute('id'),
                            src: widgetImg.getAttribute('src'),
                            title: widgetImg.getAttribute('title'),
                            class: widgetImg.getAttribute('class')
                        }
                    }).run();
                }
            } else if (content.includes('<') && content.includes('>')) {
                // Insert HTML content
                this.editor.chain().focus().insertContent(content).run();
            } else {
                // Insert plain text (including variables)
                this.editor.chain().focus().insertContent(content).run();
            }
        }
    }

    static iconRegistry = {
        // Formatting icons
        'bold': '<path d="M7 5h6a3.5 3.5 0 0 1 0 7h-6z"/><path d="M13 12h1a3.5 3.5 0 0 1 0 7h-7v-7"/>',
        'italic': '<path d="M11 5l6 0"/><path d="M7 19l6 0"/><path d="M14 5l-4 14"/>',
        'underline': '<path d="M7 5v5a5 5 0 0 0 10 0v-5"/><path d="M5 19h14"/>',
        'strike': '<path d="M5 12l14 0"/><path d="M16 6.5a4 2 0 0 0 -4 -1.5h-1a3.5 3.5 0 0 0 0 7h2a3.5 3.5 0 0 1 0 7h-1.5a4 2 0 0 1 -4 -1.5"/>',
        'blockquote': '<path d="M6 15h15"/><path d="M21 19h-15"/><path d="M15 11h6"/><path d="M21 7h-6"/><path d="M9 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2"/><path d="M3 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2"/>',
        
        // List icons
        'bullet-list': '<path d="M9 6l11 0"/><path d="M9 12l11 0"/><path d="M9 18l11 0"/><path d="M5 6l0 .01"/><path d="M5 12l0 .01"/><path d="M5 18l0 .01"/>',
        'ordered-list': '<path d="M11 6h9"/><path d="M11 12h9"/><path d="M12 18h8"/><path d="M4 16a2 2 0 1 1 4 0c0 .591 -.5 1 -1 1.5l-3 2.5h4"/><path d="M6 10v-6l-2 2"/>',
        
        // Alignment icons
        'align-left': '<path d="M4 6l16 0"/><path d="M4 12l10 0"/><path d="M4 18l14 0"/>',
        'align-center': '<path d="M4 6l16 0"/><path d="M8 12l8 0"/><path d="M6 18l12 0"/>',
        'align-right': '<path d="M4 6l16 0"/><path d="M10 12l10 0"/><path d="M6 18l14 0"/>',
        
        // Insert icons
        'link': '<path d="M9 15l6 -6"/><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/>',
        'image': '<path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/>',
        'table': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M3 10h18"/><path d="M10 3v18"/>',
        'widget': '<path d="M4 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M14 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M4 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M14 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/>',
        'variable': '<path d="M5 4c-2.5 5 -2.5 10 0 16m14 -16c2.5 5 2.5 10 0 16m-10 -11h1c1 0 1 1 2.016 3.527c.984 2.473 .984 3.473 1.984 3.473h1"/><path d="M8 16c1.5 0 3 -2 4 -3.5s2.5 -3.5 4 -3.5"/>',
        
        // Table operation icons
        'column-insert-left': '<path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M5 12l4 0"/><path d="M7 10l0 4"/>',
        'column-insert-right': '<path d="M6 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M15 12l4 0"/><path d="M17 10l0 4"/>',
        'column-remove': '<path d="M6 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1z"/><path d="M16 10l4 4"/><path d="M16 14l4 -4"/>',
        'row-insert-top': '<path d="M4 6h16a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1z"/><path d="M12 2l0 4"/><path d="M10 4l4 0"/>',
        'row-insert-bottom': '<path d="M20 14v4a1 1 0 0 1 -1 1h-14a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1h14a1 1 0 0 1 1 1z"/><path d="M12 2l0 4"/><path d="M10 4l4 0"/>',
        'row-remove': '<path d="M20 6v4a1 1 0 0 1 -1 1h-14a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1h14a1 1 0 0 1 1 1z"/><path d="M10 16l4 4"/><path d="M10 20l4 -4"/>',
        'arrows-join': '<path d="M12 21v-6m-3 3l3 3l3 -3m-3 -9v-6m-3 3l3 -3l3 3"/>',
        'arrows-split': '<path d="M21 17h-8l-3.5 -5h-6.5"/><path d="M21 7h-8l-3.5 5h-6.5"/><path d="M18 10l3 -3l-3 -3"/><path d="M18 20l3 -3l-3 -3"/>',
        'table-row': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M9 3l-6 6"/><path d="M14 3l-7 7"/><path d="M19 3l-7 7"/><path d="M21 6l-4 4"/><path d="M3 10h18"/><path d="M10 10v11"/>',
        'table-column': '<path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z"/><path d="M10 10h11"/><path d="M10 3v18"/><path d="M9 3l-6 6"/><path d="M10 7l-7 7"/><path d="M10 12l-7 7"/><path d="M10 17l-4 4"/>',
        'trash': '<path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>'
    };

    getIcon(name) {
        const iconPath = tiptapWysiwygSetup.iconRegistry[name];
        if (!iconPath) {
            console.warn(`Icon "${name}" not found in registry`);
            return '';
        }
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${iconPath}</svg>`;
    }
}