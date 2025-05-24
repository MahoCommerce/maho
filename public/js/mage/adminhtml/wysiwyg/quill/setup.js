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
        // Register custom button icons
        const icons = Quill.import('ui/icons');
        
        // Widget icon - gear/cog shape
        icons['widget'] = '<svg viewBox="0 0 18 18">' +
            '<path class="ql-stroke" d="M9 15l-1.5-0.3c-0.1-0.5-0.3-0.9-0.6-1.2l-1.2 0.9c-0.4-0.3-0.8-0.6-1.1-1l0.8-1.2c-0.3-0.3-0.7-0.6-1.2-0.7L3.8 9.8c-0.1-0.3-0.1-0.5-0.1-0.8s0-0.5 0.1-0.8l1.4-0.3c0.1-0.5 0.3-0.9 0.6-1.2l-0.9-1.2c0.3-0.4 0.6-0.8 1-1.1l1.2 0.8c0.3-0.3 0.7-0.5 1.2-0.6L9 3c0.3-0.1 0.5-0.1 0.8-0.1s0.5 0 0.8 0.1l0.3 1.5c0.5 0.1 0.9 0.3 1.2 0.6l1.2-0.8c0.4 0.3 0.8 0.6 1.1 1l-0.8 1.2c0.3 0.3 0.5 0.7 0.6 1.2l1.5 0.3c0.1 0.3 0.1 0.5 0.1 0.8s0 0.5-0.1 0.8l-1.5 0.3c-0.1 0.5-0.3 0.9-0.6 1.2l0.8 1.2c-0.3 0.4-0.6 0.8-1 1.1l-1.2-0.8c-0.3 0.3-0.7 0.5-1.2 0.6L9.8 15c-0.3 0.1-0.5 0.1-0.8 0.1s-0.5 0-0.8-0.1z"/>' +
            '<circle class="ql-fill" cx="9" cy="9" r="2.5"/>' +
            '</svg>';
        
        // Variable icon - curly braces
        icons['variable'] = '<svg viewBox="0 0 18 18">' +
            '<path class="ql-stroke" d="M4 3v3c0 1-0.5 1.5-1.5 1.5v3c1 0 1.5 0.5 1.5 1.5v3c0 1 1 2 2 2h1v-2h-0.5c-0.5 0-0.5-0.5-0.5-1v-2.5c0-1-0.5-1.5-1.5-1.5v-1c1 0 1.5-0.5 1.5-1.5V4.5c0-0.5 0-1 0.5-1H7V1.5H6c-1 0-2 1-2 2z"/>' +
            '<path class="ql-stroke" d="M14 3v3c0 1 0.5 1.5 1.5 1.5v3c-1 0-1.5 0.5-1.5 1.5v3c0 1-1 2-2 2h-1v-2h0.5c0.5 0 0.5-0.5 0.5-1v-2.5c0-1 0.5-1.5 1.5-1.5v-1c-1 0-1.5-0.5-1.5-1.5V4.5c0-0.5 0-1-0.5-1H11V1.5h1c1 0 2 1 2 2z"/>' +
            '</svg>';
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
        const toolbar = [
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'script': 'sub'}, { 'script': 'super' }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'indent': '-1'}, { 'indent': '+1' }],
            [{ 'direction': 'rtl' }],
            [{ 'align': [] }],
            ['link', 'image', 'video'],
            ['blockquote', 'code-block'],
            ['clean']
        ];

        // Add custom buttons if enabled
        const customButtons = [];
        if (this.config.add_widgets) {
            customButtons.push('widget');
        }
        if (this.config.add_variables) {
            customButtons.push('variable');
        }
        
        if (customButtons.length > 0) {
            toolbar.unshift(customButtons);
        }

        return toolbar;
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
            this.getTextArea().value = this.editor.root.innerHTML;
            this.triggerChange(this.getTextArea());
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
            const range = this.editor.getSelection();
            if (range) {
                // If it's HTML content, insert as HTML
                if (content.includes('<') && content.includes('>')) {
                    // In Quill 2.0, we use clipboard.convert and updateContents
                    const delta = this.editor.clipboard.convert({ html: content });
                    this.editor.updateContents(delta.ops, 'user');
                    // Set cursor after inserted content
                    const newLength = this.editor.getLength();
                    this.editor.setSelection(newLength - 1);
                } else {
                    this.editor.insertText(range.index, content);
                }
            }
        }
    }
}

// Compatibility layer for widget insertion
if (typeof widgetTools === 'undefined') {
    window.widgetTools = {
        openDialog: function(url) {
            // This will be overridden by the actual widget tools
            console.warn('Widget tools not loaded');
        }
    };
}