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
        const initialContent = this.encodeContent(textarea.value);
        if (initialContent) {
            this.editor.root.innerHTML = initialContent;
        }

        // Listen for changes
        this.editor.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user') {
                this.updateTextArea();
                this.onChangeContent();
            }
        });

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
            widgetTools.openDialog(url);
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
            const content = this.decodeContent(this.editor.root.innerHTML);
            this.getTextArea().value = content;
            this.triggerChange(this.getTextArea());
        }
    }

    triggerChange(element) {
        element.dispatchEvent(new Event('change', { bubbles: false, cancelable: true }));
        return element;
    }

    encodeContent(content) {
        if (!content) return '';
        
        if (this.config.add_widgets) {
            content = this.encodeDirectives(this.encodeWidgets(content));
        } else if (this.config.encode_directives) {
            content = this.encodeDirectives(content);
        }
        return content;
    }

    decodeContent(content) {
        if (!content) return '';
        
        if (this.config.add_widgets) {
            content = this.decodeDirectives(this.decodeWidgets(content));
        } else if (this.config.encode_directives) {
            content = this.decodeDirectives(content);
        }
        return content;
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
                let placeholderFilename = attrs.type.replace(/\//g, "__") + ".gif";
                if (!this.widgetPlaceholderExist(placeholderFilename)) {
                    placeholderFilename = 'default.gif';
                }
                const attributesObj = {
                    id: Base64.idEncode(match),
                    src: this.config.widget_images_url + placeholderFilename,
                    title: match.replace(/\{\{/g, '{').replace(/\}\}/g, '}').replace(/\"/g, '&quot;'),
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
        const url = this.makeDirectiveUrl('%directive%').replace(/([$^.?*!+:=()\[\]{}|\\])/g, '\\$1');
        const reg = new RegExp(url.replace('%directive%', '([a-zA-Z0-9,_-]+)'), 'g');
        return content.replace(reg, (match, directive) => Base64.mageDecode(directive));
    }

    decodeWidgets(content) {
        return content.replace(/<img([^>]+id=\"[^>]+)>/gi, (match, attributes) => {
            const attrs = this.parseAttributesString(attributes);
            if (attrs.id && attrs.class && attrs.class.includes('maho-widget-placeholder')) {
                const widgetCode = Base64.idDecode(attrs.id);
                if (widgetCode.indexOf('{{widget') !== -1) {
                    return widgetCode;
                }
            }
            return match;
        });
    }

    parseAttributesString(attributes) {
        const result = {};
        attributes.replace(/(\w+)(?:\s*=\s*(?:(?:"((?:\\.|[^"\\])*)")|(?:'((?:\\.|[^'\\])*)')|([^>\s]+)))?/g, (match, attr, val1, val2, val3) => {
            result[attr] = val1 || val2 || val3 || '';
            return match;
        });
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