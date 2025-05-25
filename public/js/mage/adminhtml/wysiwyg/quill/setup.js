/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class quillWysiwygSetup {

    mediaBrowserCallback = null;
    mediaBrowserMetal = null;
    mediaBrowserValue = null;
    directiveMap = new Map();
    directiveCounter = 0;
    lastCursorPosition = null;

    constructor() {
        this.initialize(...arguments);
    }

    initialize(htmlId, config) {
        this.id = htmlId;
        this.selector = `#${htmlId}`;
        this.config = config;
        this.editor = null;

        if (typeof quillEditors === 'undefined') {
            window.quillEditors = new Map();
        }
        quillEditors.set(this.id, this);

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

    registerCustomButtons() {
        const icons = Quill.import('ui/icons');
        icons['widget'] = '<svg class="ql-stroke" xmlns="http://www.w3.org/2000/svg" width="18" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-name="packages" data-variant="outline"><path d="M7 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M2 13.5v5.5l5 3"/><path d="M7 16.545l5 -3.03"/><path d="M17 16.5l-5 -3l5 -3l5 3v5.5l-5 3z"/><path d="M12 19l5 3"/><path d="M17 16.5l5 -3"/><path d="M12 13.5v-5.5l-5 -3l5 -3l5 3v5.5"/><path d="M7 5.03v5.455"/><path d="M12 8l5 -3"/></svg>';
        icons['variable'] = '<svg class="ql-stroke" xmlns="http://www.w3.org/2000/svg" width="18" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-name="variable" data-variant="outline"><path d="M5 4c-2.5 5 -2.5 10 0 16m14 -16c2.5 5 2.5 10 0 16m-10 -11h1c1 0 1 1 2.016 3.527c.984 2.473 .984 3.473 1.984 3.473h1"/><path d="M8 16c1.5 0 3 -2 4 -3.5s2.5 -3.5 4 -3.5"/></svg>';
    }

    unbindEventListeners() {
        varienGlobalEvents.removeEventHandler('formSubmit', this.onFormValidation);
    }

    destroy() {
        this.unbindEventListeners();
        if (this.editor) {
            // Save content before destroying
            this.updateTextArea();
            
            // Destroy the Quill instance
            this.editor = null;
        }
        
        // Remove the wrapper which contains everything
        const wrapper = document.getElementById(`${this.id}_wrapper`);
        if (wrapper) {
            wrapper.remove();
        }
    }

    setup() {
        // Register custom buttons before initializing Quill
        this.registerCustomButtons();
        
        // Create wrapper container for Quill editor
        const textarea = this.getTextArea();
        const wrapper = document.createElement('div');
        wrapper.id = `${this.id}_wrapper`;
        wrapper.className = 'quill-wrapper';
        
        // Create container for Quill editor content
        const container = document.createElement('div');
        container.id = `${this.id}_editor`;
        container.style.minHeight = '400px';
        container.style.backgroundColor = '#fff';
        
        // Append container to wrapper
        wrapper.appendChild(container);
        
        // Insert wrapper after textarea
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

        // Initialize Quill
        const toolbarOptions = this.getToolbarOptions();
        
        this.editor = new Quill(container, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: toolbarOptions,
                    handlers: {
                        'image': this.imageHandler.bind(this),
                        'widget': this.widgetHandler.bind(this),
                        'variable': this.variableHandler.bind(this),
                    }
                }
            },
            placeholder: 'Enter content...',
        });

        // Set initial content
        let initialContent = '';
        if (textarea.value) {
            initialContent = textarea.value;
        } else if (window.catalogWysiwygPendingContent && window.catalogWysiwygPendingContent.elementId === this.id) {
            // Check for content passed from catalog wysiwyg dialog
            initialContent = window.catalogWysiwygPendingContent.content;
            delete window.catalogWysiwygPendingContent;
        } else {
            // Last resort: check if this is a dialog editor and try to find the source
            const dialogEl = textarea.closest('dialog');
            if (dialogEl && this.id.endsWith('_editor')) {
                const sourceId = this.id.replace('_editor', '');
                const sourceTextarea = document.getElementById(sourceId);
                if (sourceTextarea && sourceTextarea.value) {
                    initialContent = sourceTextarea.value;
                }
            }
        }
        
        if (initialContent) {
            // Encode directives before setting content
            const encodedContent = this.encodeDirectives(initialContent);
            this.editor.root.innerHTML = encodedContent;
        }

        // Listen for changes
        this.editor.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user') {
                this.updateTextArea();
                this.onChangeContent();
            }
        });
        
        // Save cursor position when selection changes
        this.editor.on('selection-change', (range, oldRange, source) => {
            if (range) {
                this.lastCursorPosition = range;
            }
        });

        // Add titles to custom buttons
        const toolbar = this.editor.getModule('toolbar');
        if (toolbar) {
            const widgetButton = toolbar.container.querySelector('.ql-widget');
            if (widgetButton) {
                widgetButton.setAttribute('title', 'Insert Widget');
            }
            const variableButton = toolbar.container.querySelector('.ql-variable');
            if (variableButton) {
                variableButton.setAttribute('title', 'Insert Variable');
            }
        }

        // Fire initialization event
        varienGlobalEvents.fireEvent('wysiwygEditorInitialized', this.editor);
    }

    getToolbarOptions() {
        return [
            [{ 'header': [1, 2, 3, 4, 5, false] }],
            ['bold', 'italic', 'underline', 'strike', 'blockquote'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'align': [] }],
            ['link', 'image', 'widget', 'variable']
        ];
    }

    imageHandler() {
        if (this.config.files_browser_window_url) {
            // Store the current selection before opening the dialog
            const savedRange = this.editor.getSelection();
            
            varienGlobalEvents.fireEvent("open_browser_callback", { 
                callback: (content) => {
                    try {
                        // Use the saved range or default to current position
                        const range = savedRange || this.editor.getSelection() || { index: this.editor.getLength() - 1 };
                        this.editor.focus();
                        
                        // If content is HTML (contains img tag), insert it as HTML
                        if (content.includes('<img')) {
                            this.insertContent(content);
                        } else {
                            // Otherwise insert as embed (for backwards compatibility)
                            this.editor.insertEmbed(range.index, 'image', content);
                            this.editor.setSelection(range.index + 1);
                        }
                    } catch (error) {
                        console.error('Error inserting image:', error);
                        alert('Error inserting image: ' + error.message);
                    }
                },
                value: '',
                meta: { filetype: 'image' }
            });
        }
    }

    widgetHandler() {
        if (this.config.widget_window_url) {
            const url = this.config.widget_window_url + 'widget_target_id/' + this.id + '/';
            widgetTools.openDialog(url);
        }
    }

    variableHandler() {
        if (this.config.variable_window_url) {
            const url = this.config.variable_window_url + 'variable_target_id/' + this.id + '/';
            OpenmagevariablePlugin.setEditor(this.editor);
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
        // Clear directive map when turning on editor
        this.directiveMap.clear();
        this.directiveCounter = 0;
        
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
                // Get content and decode directives
                let content = this.editor.root.innerHTML;
                content = this.decodeDirectives(content);
                textarea.value = content;
                this.triggerChange(textarea);
            }
        }
    }

    triggerChange(element) {
        element.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        return element;
    }


    parseAttributesString(attributes) {
        const result = {};
        attributes.replace(/(\w+)(?:\s*=\s*(?:(?:"((?:\\.|[^"\\])*)")|(?:'((?:\\.|[^'\\])*)')|([^>\s]+)))?/g, (match, attr, val1, val2, val3) => {
            result[attr] = val1 || val2 || val3 || '';
            return match;
        });
        return result;
    }

    /**
     * Encode directives to protect them from HTML parsing
     */
    encodeDirectives(content) {
        // Reset the directive map for this encoding session
        this.directiveMap.clear();
        this.directiveCounter = 0;
        
        // Match all Maho directives {{...}}
        return content.replace(/\{\{([^}]+)\}\}/g, (match) => {
            const placeholder = `__MAHO_DIRECTIVE_${this.directiveCounter}__`;
            this.directiveMap.set(placeholder, match);
            this.directiveCounter++;
            return placeholder;
        });
    }

    /**
     * Decode directives back to their original form
     */
    decodeDirectives(content) {
        this.directiveMap.forEach((directive, placeholder) => {
            content = content.replace(new RegExp(placeholder, 'g'), directive);
        });
        return content;
    }


    getMediaBrowserCallback() {
        // If no callback is set, create a default one that inserts content
        if (!this.mediaBrowserCallback) {
            this.mediaBrowserCallback = (content) => {
                try {
                    this.insertContent(content);
                } catch (error) {
                    console.error('Error inserting content from media browser:', error);
                    alert('Error inserting content: ' + error.message);
                }
            };
        }
        return this.mediaBrowserCallback;
    }

    // Method to insert content at cursor position (for widgets/variables)
    insertContent(content) {
        if (this.editor) {
            // Try to get current selection, or use last saved position, or default to current position
            let range = this.editor.getSelection(true) || this.lastCursorPosition;
            if (!range) {
                // If no range, default to the current length (end of document)
                range = { index: this.editor.getLength() - 1, length: 0 };
            }
            const index = range.index;
            
            // Focus the editor first
            this.editor.focus();
            
            // If it's HTML content, insert as HTML
            if (content.includes('<') && content.includes('>')) {
                // Encode directives in the content to be inserted
                const encodedContent = this.encodeDirectives(content);
                
                // Delete any selected content first
                if (range.length > 0) {
                    this.editor.deleteText(index, range.length, 'user');
                }
                
                // Convert HTML to Delta and insert at the current position
                const delta = this.editor.clipboard.convert({ html: encodedContent });
                
                // Insert the delta at the current index
                this.editor.updateContents(delta.ops ? 
                    { ops: [{ retain: index }, ...delta.ops] } : 
                    delta, 'user');
                
                // Calculate new cursor position (approximate)
                const insertedLength = delta.ops ? delta.ops.reduce((len, op) => {
                    return len + (op.insert ? (typeof op.insert === 'string' ? op.insert.length : 1) : 0);
                }, 0) : 1;
                
                // Set cursor after inserted content
                this.editor.setSelection(index + insertedLength, 0, 'user');
            } else {
                // For plain text (variables), check if it's a directive
                const encodedContent = this.encodeDirectives(content);
                
                if (range && range.length > 0) {
                    this.editor.deleteText(range.index, range.length);
                }
                this.editor.insertText(index, encodedContent, 'user');
                // Move cursor after inserted text
                this.editor.setSelection(index + encodedContent.length);
            }
        }
    }
}
