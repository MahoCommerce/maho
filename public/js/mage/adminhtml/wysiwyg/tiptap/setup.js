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
        
        const boldBtn = this.createToolbarButton('Bold', this.getBoldIcon(), () => {
            this.editor.chain().focus().toggleBold().run();
        }, 'bold');
        formatGroup.appendChild(boldBtn);

        const italicBtn = this.createToolbarButton('Italic', this.getItalicIcon(), () => {
            this.editor.chain().focus().toggleItalic().run();
        }, 'italic');
        formatGroup.appendChild(italicBtn);

        const underlineBtn = this.createToolbarButton('Underline', this.getUnderlineIcon(), () => {
            this.editor.chain().focus().toggleUnderline().run();
        }, 'underline');
        formatGroup.appendChild(underlineBtn);

        const strikeBtn = this.createToolbarButton('Strike', this.getStrikeIcon(), () => {
            this.editor.chain().focus().toggleStrike().run();
        }, 'strike');
        formatGroup.appendChild(strikeBtn);

        const blockquoteBtn = this.createToolbarButton('Blockquote', this.getBlockquoteIcon(), () => {
            this.editor.chain().focus().toggleBlockquote().run();
        }, 'blockquote');
        formatGroup.appendChild(blockquoteBtn);

        toolbar.appendChild(formatGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // List buttons
        const listGroup = document.createElement('div');
        listGroup.className = 'toolbar-group';

        const bulletListBtn = this.createToolbarButton('Bullet List', this.getBulletListIcon(), () => {
            this.editor.chain().focus().toggleBulletList().run();
        }, 'bulletList');
        listGroup.appendChild(bulletListBtn);

        const orderedListBtn = this.createToolbarButton('Ordered List', this.getOrderedListIcon(), () => {
            this.editor.chain().focus().toggleOrderedList().run();
        }, 'orderedList');
        listGroup.appendChild(orderedListBtn);

        toolbar.appendChild(listGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // Alignment buttons
        const alignGroup = document.createElement('div');
        alignGroup.className = 'toolbar-group';

        const alignLeftBtn = this.createToolbarButton('Align Left', this.getAlignLeftIcon(), () => {
            this.editor.chain().focus().setTextAlign('left').run();
        });
        alignGroup.appendChild(alignLeftBtn);

        const alignCenterBtn = this.createToolbarButton('Align Center', this.getAlignCenterIcon(), () => {
            this.editor.chain().focus().setTextAlign('center').run();
        });
        alignGroup.appendChild(alignCenterBtn);

        const alignRightBtn = this.createToolbarButton('Align Right', this.getAlignRightIcon(), () => {
            this.editor.chain().focus().setTextAlign('right').run();
        });
        alignGroup.appendChild(alignRightBtn);

        toolbar.appendChild(alignGroup);

        // Separator
        toolbar.appendChild(this.createSeparator());

        // Insert buttons
        const insertGroup = document.createElement('div');
        insertGroup.className = 'toolbar-group';

        const linkBtn = this.createToolbarButton('Link', this.getLinkIcon(), () => {
            this.linkHandler();
        });
        insertGroup.appendChild(linkBtn);

        const imageBtn = this.createToolbarButton('Image', this.getImageIcon(), () => {
            this.imageHandler();
        });
        insertGroup.appendChild(imageBtn);

        // Create table dropdown button
        const tableDropdown = document.createElement('div');
        tableDropdown.style.position = 'relative';
        tableDropdown.style.display = 'inline-block';
        
        const tableBtn = this.createToolbarButton('Table', this.getTableIcon(), (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleTableMenu(tableDropdown);
        });
        tableDropdown.appendChild(tableBtn);
        
        // Create table menu
        const tableMenu = document.createElement('div');
        tableMenu.className = 'table-menu';
        tableMenu.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px;
            margin-top: 4px;
            display: none;
            z-index: 1000;
            min-width: 200px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        `;
        
        const tableCommands = [
            { label: 'Insert Table', command: 'insertTable', params: { rows: 3, cols: 3, withHeaderRow: true } },
            { separator: true },
            { label: 'Add Column Before', command: 'addColumnBefore' },
            { label: 'Add Column After', command: 'addColumnAfter' },
            { label: 'Delete Column', command: 'deleteColumn' },
            { separator: true },
            { label: 'Add Row Before', command: 'addRowBefore' },
            { label: 'Add Row After', command: 'addRowAfter' },
            { label: 'Delete Row', command: 'deleteRow' },
            { separator: true },
            { label: 'Delete Table', command: 'deleteTable' },
            { separator: true },
            { label: 'Merge Cells', command: 'mergeCells' },
            { label: 'Split Cell', command: 'splitCell' },
            { separator: true },
            { label: 'Toggle Header Column', command: 'toggleHeaderColumn' },
            { label: 'Toggle Header Row', command: 'toggleHeaderRow' },
            { label: 'Toggle Header Cell', command: 'toggleHeaderCell' }
        ];
        
        tableCommands.forEach(item => {
            if (item.separator) {
                const separator = document.createElement('div');
                separator.style.cssText = 'height: 1px; background: #e2e8f0; margin: 4px 0;';
                tableMenu.appendChild(separator);
            } else {
                const menuItem = document.createElement('button');
                menuItem.type = 'button';
                menuItem.textContent = item.label;
                menuItem.style.cssText = `
                    display: block;
                    width: 100%;
                    text-align: left;
                    padding: 6px 12px;
                    border: none;
                    background: none;
                    cursor: pointer;
                    font-size: 14px;
                    color: #475569;
                    border-radius: 4px;
                    transition: all 0.15s ease;
                `;
                menuItem.onmouseover = () => {
                    menuItem.style.backgroundColor = '#f1f5f9';
                    menuItem.style.color = '#1e293b';
                };
                menuItem.onmouseout = () => {
                    menuItem.style.backgroundColor = 'transparent';
                    menuItem.style.color = '#475569';
                };
                menuItem.onclick = () => {
                    if (item.params) {
                        this.editor.chain().focus()[item.command](item.params).run();
                    } else {
                        this.editor.chain().focus()[item.command]().run();
                    }
                    tableMenu.style.display = 'none';
                };
                tableMenu.appendChild(menuItem);
            }
        });
        
        tableDropdown.appendChild(tableMenu);
        insertGroup.appendChild(tableDropdown);
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!tableDropdown.contains(e.target)) {
                tableMenu.style.display = 'none';
            }
        });

        const widgetBtn = this.createToolbarButton('Insert Widget', this.getWidgetIcon(), () => {
            this.widgetHandler();
        });
        insertGroup.appendChild(widgetBtn);

        const variableBtn = this.createToolbarButton('Insert Variable', this.getVariableIcon(), () => {
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
    
    toggleTableMenu(dropdown) {
        const menu = dropdown.querySelector('.table-menu');
        if (menu) {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
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

    // Icon SVGs for toolbar buttons
    getBoldIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>';
    }

    getItalicIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>';
    }

    getUnderlineIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>';
    }

    getStrikeIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M7.24 8.75c-.26-.48-.39-1.03-.39-1.67 0-.61.13-1.16.4-1.67.26-.5.63-.93 1.11-1.29.48-.35 1.05-.63 1.7-.83.66-.19 1.39-.29 2.18-.29.81 0 1.54.11 2.21.34.66.22 1.23.54 1.69.94.47.4.83.88 1.08 1.43.25.55.38 1.15.38 1.81h-3.01c0-.31-.05-.59-.15-.85-.09-.27-.24-.49-.44-.68-.2-.19-.45-.33-.75-.44-.3-.1-.66-.16-1.06-.16-.39 0-.74.04-1.03.13-.29.09-.53.21-.72.36-.19.16-.34.34-.44.55-.1.21-.15.43-.15.66 0 .48.25.88.74 1.21.38.25.77.48 1.41.7H7.39c-.05-.08-.11-.17-.15-.25zM21 12v-2H3v2h9.62c.18.07.4.14.55.2.37.17.66.34.87.51.21.17.35.36.43.57.07.2.11.43.11.69 0 .23-.05.45-.14.66-.09.2-.23.38-.42.53-.19.15-.42.26-.71.35-.29.08-.63.13-1.01.13-.43 0-.83-.04-1.18-.13s-.66-.23-.91-.42c-.25-.19-.45-.44-.59-.75-.14-.31-.25-.76-.25-1.21H6.4c0 .55.08 1.13.24 1.58.16.45.37.85.65 1.21.28.35.6.66.98.92.37.26.78.48 1.22.65.44.17.9.3 1.38.39.48.08.96.13 1.44.13.8 0 1.53-.09 2.18-.28s1.21-.45 1.67-.79c.46-.34.82-.77 1.07-1.27s.38-1.07.38-1.71c0-.6-.1-1.14-.31-1.61-.05-.11-.11-.23-.17-.33H21z"/></svg>';
    }

    getBlockquoteIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>';
    }

    getBulletListIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>';
    }

    getOrderedListIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>';
    }

    getAlignLeftIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>';
    }

    getAlignCenterIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M7 15v2h10v-2H7zm-4 6h18v-2H3v2zm0-8h18v-2H3v2zm4-6v2h10V7H7zM3 3v2h18V3H3z"/></svg>';
    }

    getAlignRightIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M3 21h18v-2H3v2zm6-4h12v-2H9v2zm-6-4h18v-2H3v2zm6-4h12V7H9v2zM3 3v2h18V3H3z"/></svg>';
    }

    getLinkIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';
    }

    getImageIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
    }

    getTableIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M5 4h14c.55 0 1 .45 1 1v14c0 .55-.45 1-1 1H5c-.55 0-1-.45-1-1V5c0-.55.45-1 1-1zm1 2v4h5V6H6zm7 0v4h5V6h-5zm-7 6v4h5v-4H6zm7 0v4h5v-4h-5z"/></svg>';
    }

    getWidgetIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M7 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M2 13.5v5.5l5 3"/><path d="M7 16.545l5 -3.03"/><path d="M17 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M12 19l5 3"/><path d="M17 16.5l5 -3"/><path d="M12 13.5v-5.5l-5 -3l5 -3l5 3v5.5"/><path d="M7 5.03v5.455"/><path d="M12 8l5 -3"/></svg>';
    }

    getVariableIcon() {
        return '<svg viewBox="0 0 24 24"><path d="M5 4c-2.5 5 -2.5 10 0 16m14 -16c2.5 5 2.5 10 0 16m-10 -11h1c1 0 1 1 2.016 3.527c.984 2.473 .984 3.473 1.984 3.473h1"/><path d="M8 16c1.5 0 3 -2 4 -3.5s2.5 -3.5 4 -3.5"/></svg>';
    }
}