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
        if (textarea.value) {
            this.editor.root.innerHTML = textarea.value;
        } else if (window.catalogWysiwygPendingContent && window.catalogWysiwygPendingContent.elementId === this.id) {
            // Check for content passed from catalog wysiwyg dialog
            this.editor.root.innerHTML = window.catalogWysiwygPendingContent.content;
            delete window.catalogWysiwygPendingContent;
        } else {
            // Last resort: check if this is a dialog editor and try to find the source
            const dialogEl = textarea.closest('dialog');
            if (dialogEl && this.id.endsWith('_editor')) {
                const sourceId = this.id.replace('_editor', '');
                const sourceTextarea = document.getElementById(sourceId);
                if (sourceTextarea && sourceTextarea.value) {
                    this.editor.root.innerHTML = sourceTextarea.value;
                }
            }
        }

        // Listen for changes
        this.editor.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user') {
                this.updateTextArea();
                this.onChangeContent();
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
            varienGlobalEvents.fireEvent("open_browser_callback", { 
                callback: (url) => {
                    const range = this.editor.getSelection();
                    if (range) {
                        this.editor.insertEmbed(range.index, 'image', url);
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
                textarea.value = this.editor.root.innerHTML;
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


    getMediaBrowserCallback() {
        return this.mediaBrowserCallback;
    }

    // Method to insert content at cursor position (for widgets/variables)
    insertContent(content) {
        if (this.editor) {
            const range = this.editor.getSelection(true);
            const index = range ? range.index : this.editor.getLength() - 1;
            
            // Focus the editor first
            this.editor.focus();
            
            // If it's HTML content, insert as HTML
            if (content.includes('<') && content.includes('>')) {
                // In Quill 2.0, we use clipboard.convert and updateContents
                const delta = this.editor.clipboard.convert({ html: content });
                // Delete any selected content first
                if (range && range.length > 0) {
                    this.editor.deleteText(range.index, range.length);
                }
                // Insert the new content at the cursor position
                this.editor.updateContents(delta.compose(new Delta().retain(index)), 'user');
                // Set cursor after inserted content
                this.editor.setSelection(index + delta.length() - 1);
            } else {
                // For plain text (variables), insert at cursor position
                if (range && range.length > 0) {
                    this.editor.deleteText(range.index, range.length);
                }
                this.editor.insertText(index, content, 'user');
                // Move cursor after inserted text
                this.editor.setSelection(index + content.length);
            }
        }
    }
}
